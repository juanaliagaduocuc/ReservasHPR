<?php
session_start();

if (!isset($_SESSION['cliente'], $_SESSION['idCliente'])) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$mensajeReserva = $_SESSION['mensajeReserva'] ?? '';
unset($_SESSION['mensajeReserva']);
$_SESSION['csrfReserva'] = $_SESSION['csrfReserva'] ?? bin2hex(random_bytes(32));
$consultaDisponibilidad = null;
$fechaIngresoConsulta = date('Y-m-d');
$fechaSalidaConsulta = date('Y-m-d', strtotime('+1 day'));
$accionesReserva = ['consultar_disponibilidad', 'reservar_habitacion'];

function validarFechasReserva($fechaIngreso, $fechaSalida)
{
    $ingreso = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaIngreso);
    $salida = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaSalida);

    if (!$ingreso || $ingreso->format('Y-m-d') !== $fechaIngreso || !$salida || $salida->format('Y-m-d') !== $fechaSalida) {
        return 'Selecciona fechas válidas.';
    }
    if ($ingreso < new DateTimeImmutable('today') || $salida <= $ingreso) {
        return 'El ingreso debe ser hoy o una fecha futura y la salida debe ser posterior al ingreso.';
    }

    return '';
}

function consultarDisponibilidad($conn, $idHabitacion, $fechaIngreso, $fechaSalida)
{
    $stmt = $conn->prepare(
        "SELECT h.estado,
                EXISTS(
                    SELECT 1 FROM reserva r
                    WHERE r.idHabitacion = h.idHabitacion
                      AND COALESCE(r.estado, '') <> 'Cancelada'
                      AND r.fechaIngreso <= CURDATE()
                      AND r.fechaSalida >= CURDATE()
                ) AS reservaActual
         FROM habitacion h WHERE h.idHabitacion = ?"
    );
    $stmt->bind_param('i', $idHabitacion);
    $stmt->execute();
    $stmt->bind_result($estado, $reservaActual);
    if (!$stmt->fetch()) {
        $stmt->close();
        return [false, 'La habitación seleccionada no existe.'];
    }
    $stmt->close();

    if (!in_array($estado, ['Disponible', 'Ocupada'], true)) {
        return [false, 'La habitación no está habilitada para reservas.'];
    }
    if ($estado === 'Ocupada' && (int) $reservaActual === 0) {
        return [false, 'La habitación está ocupada y no tiene una fecha de salida registrada.'];
    }

    $stmt = $conn->prepare(
        "SELECT COUNT(*) FROM reserva
         WHERE idHabitacion = ? AND COALESCE(estado, '') <> 'Cancelada'
           AND fechaIngreso <= ? AND fechaSalida >= ?"
    );
    $stmt->bind_param('iss', $idHabitacion, $fechaSalida, $fechaIngreso);
    $stmt->execute();
    $stmt->bind_result($reservasEnConflicto);
    $stmt->fetch();
    $stmt->close();

    return (int) $reservasEnConflicto === 0
        ? [true, 'La habitación está disponible para las fechas seleccionadas.']
        : [false, 'La habitación no está disponible para esas fechas. Debe dejarse un día entre estadías.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['accion'] ?? '', $accionesReserva, true) && (!isset($_POST['csrfReserva']) || !is_string($_POST['csrfReserva']) || !hash_equals($_SESSION['csrfReserva'], $_POST['csrfReserva']))) {
    $mensajeReserva = 'La solicitud expiró. Actualiza la página e inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['accion'] ?? '', $accionesReserva, true)) {
    $accionReserva = $_POST['accion'];
    $transaccionActiva = false;
    $idHabitacionEntrada = $_POST['idHabitacion'] ?? null;
    $idHabitacionConsulta = is_scalar($idHabitacionEntrada) ? filter_var($idHabitacionEntrada, FILTER_VALIDATE_INT) : false;
    $fechaIngresoConsulta = is_string($_POST['fechaIngreso'] ?? null) ? trim($_POST['fechaIngreso']) : '';
    $fechaSalidaConsulta = is_string($_POST['fechaSalida'] ?? null) ? trim($_POST['fechaSalida']) : '';
    $errorFechas = validarFechasReserva($fechaIngresoConsulta, $fechaSalidaConsulta);
    $disponible = false;
    $resultadoConsulta = $errorFechas;

    if ($idHabitacionConsulta && $errorFechas === '') {
        try {
            if ($accionReserva === 'reservar_habitacion') {
                $conn->begin_transaction();
                $transaccionActiva = true;
                $stmt = $conn->prepare("SELECT valorDiario FROM habitacion WHERE idHabitacion = ? AND estado IN ('Disponible', 'Ocupada') FOR UPDATE");
                $stmt->bind_param('i', $idHabitacionConsulta);
                $stmt->execute();
                $stmt->bind_result($valorDiario);
                $habitacionEncontrada = $stmt->fetch();
                $stmt->close();

                if (!$habitacionEncontrada) {
                    throw new RuntimeException('La habitación no está habilitada para reservas.');
                }
            }

            [$disponible, $resultadoConsulta] = consultarDisponibilidad($conn, $idHabitacionConsulta, $fechaIngresoConsulta, $fechaSalidaConsulta);

            if ($accionReserva === 'reservar_habitacion' && $disponible) {
                $cantidadDias = (int) ((strtotime($fechaSalidaConsulta) - strtotime($fechaIngresoConsulta)) / 86400);
                $valorTotal = $valorDiario * $cantidadDias;
                $valorAnticipo = $valorTotal * 0.30;
                $stmt = $conn->prepare(
                    "INSERT INTO reserva (fechaIngreso, fechaSalida, cantidadDias, valorTotal, valorAnticipo, estado, idHabitacion, idCliente)
                     VALUES (?, ?, ?, ?, ?, 'Confirmada', ?, ?)"
                );
                $idCliente = (int) $_SESSION['idCliente'];
                $stmt->bind_param('ssiddii', $fechaIngresoConsulta, $fechaSalidaConsulta, $cantidadDias, $valorTotal, $valorAnticipo, $idHabitacionConsulta, $idCliente);
                $stmt->execute();
                $stmt->close();
                $conn->commit();
                $transaccionActiva = false;
                $_SESSION['mensajeReserva'] = '¡La reserva ha sido exitosa!';
                header('Location: cliente.php');
                exit();
            }

            if ($accionReserva === 'reservar_habitacion') {
                $conn->rollback();
            }
        } catch (Throwable $error) {
            if ($transaccionActiva) {
                $conn->rollback();
            }
            $resultadoConsulta = 'No se pudo completar la operación: ' . $error->getMessage();
        }
    } elseif (!$idHabitacionConsulta) {
        $resultadoConsulta = 'Selecciona una habitación válida.';
    }

    $consultaDisponibilidad = [
        'idHabitacion' => $idHabitacionConsulta,
        'disponible' => $disponible,
        'mensaje' => $resultadoConsulta,
    ];
}

$habitaciones = $conn->query(
    "SELECT h.*, c.nombre AS categoria 
     FROM habitacion h
     LEFT JOIN categoria c ON c.idCategoria = h.idCategoria
     WHERE h.estado NOT IN ('Deshabilitada', 'Eliminada')
     ORDER BY h.valorDiario ASC"
);

if (!$habitaciones) {
    die('No se pudieron cargar las habitaciones.');
}

$reservas = $conn->query(
    "SELECT idHabitacion, fechaIngreso, fechaSalida
     FROM reserva
     WHERE COALESCE(estado, '') <> 'Cancelada' AND fechaSalida >= CURDATE()
     ORDER BY fechaIngreso ASC"
);

if (!$reservas) {
    die('No se pudo consultar la disponibilidad de las habitaciones.');
}

$reservasPorHabitacion = [];
while ($reserva = $reservas->fetch_assoc()) {
    $reservasPorHabitacion[$reserva['idHabitacion']][] = [
        'inicio' => $reserva['fechaIngreso'],
        'fin' => $reserva['fechaSalida'],
    ];
}

$imagenes = [
    'Premium' => 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1000&q=80',
    'Turista' => 'https://images.unsplash.com/photo-1494526585095-c41746248156?auto=format&fit=crop&w=1000&q=80',
    'Económica' => 'https://images.unsplash.com/photo-1484154218962-a197022b5858?auto=format&fit=crop&w=1000&q=80',
];

function getRoomType($categoriaNombre)
{
    $categoria = strtolower(trim((string) $categoriaNombre));
    if ($categoria === 'premium') {
        return 'premium';
    }
    return 'economica';
}

function formatPrice($valor)
{
    return '$ ' . number_format((float) $valor, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Pacific Reef</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-cliente">
    <div class="page-shell">
        <header class="topbar">
            <a href="#" class="brand" aria-label="Hotel Pacific Reef">
                <span class="brand-mark" aria-hidden="true"></span>
                <span>Hotel Pacific Reef</span>
            </a>
            <nav class="top-nav" aria-label="Navegación principal">
                <a href="habitaciones.php">Detalles habitaciones</a>
                <a href="#">Destinos</a>
                <a href="#">Hoteles</a>
                <a href="#">Inspiración</a>
                <a href="mis_reservas.php" id='cta'  class="cta">Mis reservas</a>
            </nav>
        </header>

        <main>
            <section class="hero">
                <div class="hero-inner">
                    <span class="hero-tag">Colección premium · 38 hoteles</span>
                    <h1>Estancias que merecen ser recordadas.</h1>
                    <p class="hero-copy">Arquitectura con historia, paisajes extraordinarios y un servicio que anticipa cada detalle.</p>
                    <div class="chip-row">
                        <span class="chip"><span class="check"></span> Mejora de habitación</span>
                        <span class="chip"><span class="check"></span> Desayuno incluido</span>
                        <span class="chip"><span class="check"></span> Atención prioritaria</span>
                    </div>
                </div>
            </section>

            <div class="content-wrap">
                <?php if ($mensajeReserva !== ''): ?>
                    <div id="mensaje-reserva" class="reservation-success" role="status" aria-live="polite"><?php echo htmlspecialchars($mensajeReserva); ?></div>
                <?php endif; ?>
                <?php if ($consultaDisponibilidad !== null): ?>
                    <div class="type-toolbar" role="status"><span class="pill"><?php echo htmlspecialchars($consultaDisponibilidad['mensaje']); ?></span></div>
                <?php endif; ?>
                <div class="selector" aria-label="Filtra habitaciones">
                    <button id="id" class="selector-option active" type="button" data-filter="all">
                        <span class="icon">◇</span>
                        <span>Todas</span>
                    </button>
                    <button id="reserved" class="selector-option" type="button" data-filter="reserved">
                        <span class="icon">●</span>
                        <span>Reservadas</span>
                    </button>
                    <button id="premium" class="selector-option" type="button" data-filter="premium">
                        <span class="icon">◌</span>
                        <span>Premium</span>
                    </button>
                    <button id="economica" class="selector-option" type="button" data-filter="economica">
                        <span class="icon">⌂</span>
                        <span>Económica</span>
                    </button>
                </div>

                <div class="listing-head">
                    <div>
                        <h2 class="listing-title">Todas las habitaciones</h2>
                        <div class="listing-sub" id="room-count"><?php echo $habitaciones->num_rows; ?> habitaciones</div>
                    </div>
                    <div class="sort">Ordenar: Recomendados ▾</div>
                </div>

                <div class="type-toolbar" aria-label="Filtros">
                    <span class="pill">Fechas no disponibles marcadas en el calendario</span>
                </div>

                <div class="room-grid" id="room-list">
                    <?php if ($habitaciones && $habitaciones->num_rows > 0): ?>
                        <?php while ($habitacion = $habitaciones->fetch_assoc()): ?>
                            <?php $tipo = getRoomType($habitacion['categoria'] ?? 'Turista'); ?>
                            <?php $precio = (float) ($habitacion['valorDiario'] ?? 0); ?>
                            <?php
                            $estado = $habitacion['estado'] ?? '';
                            $fechasOcupadas = $reservasPorHabitacion[$habitacion['idHabitacion']] ?? [];
                            $hoy = date('Y-m-d');
                            $reservaActual = false;
                            $proximaReserva = null;
                            $disponibleDesde = null;
                            foreach ($fechasOcupadas as $fechaOcupada) {
                                if ($fechaOcupada['inicio'] <= $hoy && $fechaOcupada['fin'] >= $hoy) {
                                    $reservaActual = true;
                                    $disponibleDesde = date('Y-m-d', strtotime($fechaOcupada['fin'] . ' +1 day'));
                                } elseif ($fechaOcupada['inicio'] > $hoy && $proximaReserva === null) {
                                    $proximaReserva = $fechaOcupada;
                                }
                            }
                            if ($disponibleDesde === null && $proximaReserva !== null) {
                                $disponibleDesde = date('Y-m-d', strtotime($proximaReserva['fin'] . ' +1 day'));
                            }
                            if ($disponibleDesde !== null) {
                                do {
                                    $fechaDisponibleAnterior = $disponibleDesde;
                                    foreach ($fechasOcupadas as $fechaOcupada) {
                                        if ($fechaOcupada['inicio'] <= $disponibleDesde && $fechaOcupada['fin'] >= $disponibleDesde) {
                                            $disponibleDesde = date('Y-m-d', strtotime($fechaOcupada['fin'] . ' +1 day'));
                                        }
                                    }
                                } while ($disponibleDesde !== $fechaDisponibleAnterior);
                            }
                            $bloqueoFijo = $estado !== 'Disponible' && !($estado === 'Ocupada' && $reservaActual);
                            $noDisponible = $estado !== 'Disponible' || $reservaActual;
                            $disponibilidad = htmlspecialchars(
                                json_encode([
                                    'reservas' => $fechasOcupadas,
                                    'bloqueoFijo' => $bloqueoFijo,
                                    'reservadaAhora' => $reservaActual,
                                    'proximaReserva' => $proximaReserva,
                                    'disponibleDesde' => $disponibleDesde,
                                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP),
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>
                            <?php $esResultadoConsulta = $consultaDisponibilidad && (int) $consultaDisponibilidad['idHabitacion'] === (int) $habitacion['idHabitacion']; ?>
                            <article id="habitacion-<?php echo (int) $habitacion['idHabitacion']; ?>" class="room-card<?php echo $noDisponible ? ' unavailable' : ''; ?><?php echo ($reservaActual || $proximaReserva !== null) ? ' reserved' : ''; ?>" data-result="<?php echo $esResultadoConsulta ? 'true' : 'false'; ?>" data-type="<?php echo htmlspecialchars($tipo); ?>">
                                <div class="room-image">
                                    <img src="<?php echo htmlspecialchars($imagenes[$habitacion['categoria']] ?? $imagenes['Turista']); ?>" alt="Habitación <?php echo (int) $habitacion['idHabitacion']; ?>">
                                    <?php if ($reservaActual): ?>
                                        <span class="room-status">Reservada · disponible nuevamente desde <?php echo htmlspecialchars(date('d-m-Y', strtotime($disponibleDesde))); ?></span>
                                    <?php elseif ($proximaReserva !== null): ?>
                                        <span class="room-status">Reservada del <?php echo htmlspecialchars(date('d-m-Y', strtotime($proximaReserva['inicio']))); ?> al <?php echo htmlspecialchars(date('d-m-Y', strtotime($proximaReserva['fin']))); ?> · disponible nuevamente desde <?php echo htmlspecialchars(date('d-m-Y', strtotime($disponibleDesde))); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="room-card_body">
                                    <div class="room-meta">
                                        <div class="room-price"><?php echo htmlspecialchars(formatPrice($precio)); ?></div>
                                        <div class="rating">★ 4,5</div>
                                    </div>
                                    <div class="room-num"><?php echo htmlspecialchars($habitacion['numero']); ?></div>
                                    <div class="room-little">
                                        <div class="price-tag">
                                            <strong><?php echo htmlspecialchars(formatPrice($precio)); ?></strong>
                                            <span>por noche · tasas incluidas</span>
                                        </div>
                                        <button id="consultar-disponibilidad-calendario-<?php echo (int) $habitacion['idHabitacion']; ?>" class="reserve-btn availability-toggle" type="button" aria-expanded="false" data-availability="<?php echo $disponibilidad; ?>">Consultar disponibilidad</button>
                                    </div>
                                </div>
                                <section class="availability-panel" <?php echo $esResultadoConsulta ? '' : 'hidden'; ?> aria-label="Calendario de disponibilidad de habitación <?php echo htmlspecialchars($habitacion['numero']); ?>">
                                    <div class="availability-summary"><?php echo $esResultadoConsulta ? htmlspecialchars($consultaDisponibilidad['mensaje']) : ''; ?></div>
                                    <form method="POST" class="booking-search">
                                        <input type="hidden" name="csrfReserva" value="<?php echo htmlspecialchars($_SESSION['csrfReserva']); ?>">
                                        <input type="hidden" name="idHabitacion" value="<?php echo (int) $habitacion['idHabitacion']; ?>">
                                        <div class="mb-2">
                                            <label for="ingreso-<?php echo (int) $habitacion['idHabitacion']; ?>">Ingreso</label>
                                            <input id="ingreso-<?php echo (int) $habitacion['idHabitacion']; ?>" type="date" name="fechaIngreso" min="<?php echo date('Y-m-d'); ?>" value="<?php echo $consultaDisponibilidad && (int) $consultaDisponibilidad['idHabitacion'] === (int) $habitacion['idHabitacion'] ? htmlspecialchars($fechaIngresoConsulta) : date('Y-m-d'); ?>" required>
                                        </div>
                                        <div class="mb-2">
                                            <label for="salida-<?php echo (int) $habitacion['idHabitacion']; ?>">Salida</label>
                                            <input id="salida-<?php echo (int) $habitacion['idHabitacion']; ?>" type="date" name="fechaSalida" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" value="<?php echo $consultaDisponibilidad && (int) $consultaDisponibilidad['idHabitacion'] === (int) $habitacion['idHabitacion'] ? htmlspecialchars($fechaSalidaConsulta) : date('Y-m-d', strtotime('+1 day')); ?>" required>
                                        </div>
                                        <button id="consultar-disponibilidad-fechas-<?php echo (int) $habitacion['idHabitacion']; ?>" class="reserve-btn" type="submit" name="accion" value="consultar_disponibilidad">Consultar disponibilidad</button>
                                    </form>
                                    <?php if ($esResultadoConsulta && $consultaDisponibilidad['disponible']): ?>
                                        <form method="POST" class="booking-search">
                                            <input type="hidden" name="csrfReserva" value="<?php echo htmlspecialchars($_SESSION['csrfReserva']); ?>">
                                            <input type="hidden" name="accion" value="reservar_habitacion">
                                            <input type="hidden" name="idHabitacion" value="<?php echo (int) $habitacion['idHabitacion']; ?>">
                                            <input type="hidden" name="fechaIngreso" value="<?php echo htmlspecialchars($fechaIngresoConsulta); ?>">
                                            <input type="hidden" name="fechaSalida" value="<?php echo htmlspecialchars($fechaSalidaConsulta); ?>">
                                            <button class="reserve-btn" type="submit">Confirmar reserva</button>
                                        </form>
                                    <?php endif; ?>
                                    <div class="calendar-controls">
                                        <button type="button" class="calendar-nav" data-direction="-1" aria-label="Mes anterior">‹</button>
                                        <strong class="calendar-month"></strong>
                                        <button type="button" class="calendar-nav" data-direction="1" aria-label="Mes siguiente">›</button>
                                    </div>
                                    <div class="calendar-grid" role="grid"></div>
                                    <div class="calendar-legend"><span></span> No disponible</div>
                                </section>
                            </article>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No hay habitaciones disponibles en este momento.</p>
                    <?php endif; ?>
                </div>

                <section class="journey" aria-label="Cómo quieres vivir tu próxima estancia">
                    <div class="experience">
                        <span class="eyebrow">Colección signature</span>
                        <h3>Premium</h3>
                        <p>Hoteles con alma, diseño excepcional y atenciones que transforman cada noche.</p>
                        <button class="cta" type="button">Explorar premium</button>
                    </div>
                    <div class="journey-card">
                        <div class="image" aria-label="Habitación económica"></div>
                        <div class="content">
                            <h4>Económica</h4>
                            <p>Estancias inteligentes, actuales y bien ubicadas para aprovechar más cada viaje.</p>
                            <a class="cta" href="#">Explorar económica</a>
                        </div>
                    </div>
                </section>

                <footer class="footer-strip">
                    <div>
                        <h3 class="footer-title">Viajar bien empieza eligiéndonos.</h3>
                        <div class="footer-sub">Precios transparentes, asistencia local y reserva segura en cada estancia.</div>
                    </div>
                    <div class="footer-links">
                        <a href="#">Ayuda 24/7</a>
                        <a href="#">Condiciones</a>
                        <a href="#">Privacidad</a>
                    </div>
                </footer>
            </div>
        </main>
    </div>

    <script>
        const selectorButtons = document.querySelectorAll('.selector-option');
        const cards = document.querySelectorAll('.room-card');
        const resultCard = document.querySelector('.room-card[data-result="true"]');
        if (resultCard) {
            resultCard.scrollIntoView({ block: 'center' });
        }

        document.querySelectorAll('.booking-search').forEach(form => {
            const arrival = form.querySelector('[name="fechaIngreso"]');
            const departure = form.querySelector('[name="fechaSalida"]');
            if (!arrival || !departure) return;
            arrival.addEventListener('change', () => {
                const nextDay = new Date(`${arrival.value}T00:00:00`);
                nextDay.setDate(nextDay.getDate() + 1);
                const minDeparture = `${nextDay.getFullYear()}-${String(nextDay.getMonth() + 1).padStart(2, '0')}-${String(nextDay.getDate()).padStart(2, '0')}`;
                departure.min = minDeparture;
                if (departure.value < minDeparture) departure.value = minDeparture;
            });
        });

        selectorButtons.forEach(button => {
            button.addEventListener('click', () => {
                selectorButtons.forEach(btn => btn.classList.toggle('active', btn === button));
                const filter = button.dataset.filter;
                const title = document.querySelector('.listing-title');
                const titles = {
                    all: 'Todas las habitaciones',
                    reserved: 'Habitaciones reservadas',
                    premium: 'Nuestra selección Premium',
                    economica: 'Nuestra selección Económica',
                };
                title.textContent = titles[filter];
                let visibleCount = 0;

                cards.forEach(card => {
                    const match = filter === 'all'
                        || (filter === 'reserved' && card.classList.contains('reserved'))
                        || card.dataset.type === filter;
                    card.style.display = match ? 'block' : 'none';
                    if (match) visibleCount++;
                });
                document.querySelector('#room-count').textContent = `${visibleCount} habitaciones`;
            });
        });

        const weekdayNames = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa', 'Do'];
        const monthFormatter = new Intl.DateTimeFormat('es-CL', { month: 'long', year: 'numeric' });
        const dateKey = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        const offsetDateKey = (key, days) => {
            const date = new Date(`${key}T00:00:00Z`);
            date.setUTCDate(date.getUTCDate() + days);
            return date.toISOString().slice(0, 10);
        };

        document.querySelectorAll('.availability-toggle').forEach(button => {
            const card = button.closest('.room-card');
            const panel = card.querySelector('.availability-panel');
            const data = JSON.parse(button.dataset.availability);
            let month = new Date();
            month.setDate(1);

            const renderCalendar = () => {
                const grid = panel.querySelector('.calendar-grid');
                const year = month.getFullYear();
                const monthIndex = month.getMonth();
                const firstDay = (new Date(year, monthIndex, 1).getDay() + 6) % 7;
                const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();
                panel.querySelector('.calendar-month').textContent = monthFormatter.format(month);
                grid.replaceChildren();

                weekdayNames.forEach(name => {
                    const weekday = document.createElement('span');
                    weekday.className = 'calendar-day calendar-weekday';
                    weekday.textContent = name;
                    grid.append(weekday);
                });

                for (let i = 0; i < firstDay; i++) {
                    grid.append(document.createElement('span'));
                }

                for (let day = 1; day <= daysInMonth; day++) {
                    const date = new Date(year, monthIndex, day);
                    const key = dateKey(date);
                    const unavailable = data.bloqueoFijo || data.reservas.some(range =>
                        offsetDateKey(range.inicio, -1) <= key && key <= offsetDateKey(range.fin, 1)
                    );
                    const cell = document.createElement('span');
                    cell.className = `calendar-day${unavailable ? ' booked' : ''}`;
                    cell.textContent = day;
                    cell.setAttribute('role', 'gridcell');
                    if (unavailable) {
                        cell.setAttribute('aria-label', `${day} no disponible`);
                    }
                    grid.append(cell);
                }
            };

            button.addEventListener('click', () => {
                const isOpen = button.getAttribute('aria-expanded') === 'true';
                button.setAttribute('aria-expanded', String(!isOpen));
                panel.hidden = isOpen;
                if (!isOpen) {
                    const summary = panel.querySelector('.availability-summary');
                    if (data.reservadaAhora && data.disponibleDesde) {
                        const formattedDate = new Intl.DateTimeFormat('es-CL', { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(`${data.disponibleDesde}T00:00:00Z`));
                        summary.textContent = `Habitación reservada. Disponible nuevamente desde el ${formattedDate}.`;
                    } else if (data.proximaReserva) {
                        const fechaInicio = new Intl.DateTimeFormat('es-CL', { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(`${data.proximaReserva.inicio}T00:00:00Z`));
                        const fechaFin = new Intl.DateTimeFormat('es-CL', { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(`${data.proximaReserva.fin}T00:00:00Z`));
                        const fechaDisponible = new Intl.DateTimeFormat('es-CL', { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(`${data.disponibleDesde}T00:00:00Z`));
                        summary.textContent = `Próxima reserva del ${fechaInicio} al ${fechaFin}. Disponible nuevamente desde el ${fechaDisponible}.`;
                    } else if (data.bloqueoFijo) {
                        summary.textContent = 'No disponible actualmente. No hay una fecha de disponibilidad registrada.';
                    } else if (data.disponibleDesde) {
                        const formattedDate = new Intl.DateTimeFormat('es-CL', { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(`${data.disponibleDesde}T00:00:00Z`));
                        summary.textContent = `No disponible actualmente. Disponible nuevamente desde el ${formattedDate}.`;
                    } else {
                        summary.textContent = 'Consulta los días ocupados en el calendario.';
                    }
                    renderCalendar();
                }
            });

            panel.querySelectorAll('.calendar-nav').forEach(nav => {
                nav.addEventListener('click', () => {
                    month.setMonth(month.getMonth() + Number(nav.dataset.direction));
                    renderCalendar();
                });
            });
        });
    </script>
</body>
</html>
