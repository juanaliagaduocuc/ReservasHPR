<?php
session_start();

if (!isset($_SESSION['cliente'], $_SESSION['idCliente'])) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$_SESSION['csrfReserva'] = $_SESSION['csrfReserva'] ?? bin2hex(random_bytes(32));

$habitaciones = $conn->query(
    "SELECT h.*, c.nombre AS categoria
     FROM habitacion h
     LEFT JOIN categoria c ON c.idCategoria = h.idCategoria
     WHERE h.estado NOT IN ('Deshabilitada', 'Eliminada')
        OR EXISTS (
            SELECT 1 FROM reserva r
            WHERE r.idHabitacion = h.idHabitacion
              AND COALESCE(r.estado, '') <> 'Cancelada'
              AND r.fechaSalida >= CURDATE()
        )
     ORDER BY h.valorDiario ASC, h.numero ASC"
);

if (!$habitaciones) {
    die('No se pudieron consultar las habitaciones: ' . $conn->error);
}

$result = $conn->query(
    "SELECT idHabitacion, fechaIngreso, fechaSalida
     FROM reserva
     WHERE COALESCE(estado, '') <> 'Cancelada' AND fechaSalida >= CURDATE()
     ORDER BY fechaIngreso ASC"
);

if (!$result) {
    die('No se pudo consultar la disponibilidad: ' . $conn->error);
}

$reservasPorHabitacion = [];
while ($reserva = $result->fetch_assoc()) {
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

function formatearPrecioHabitacion($valor)
{
    return '$ ' . number_format((float) $valor, 0, ',', '.');
}

function fechaHabitacion($fecha)
{
    return date('d-m-Y', strtotime($fecha));
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Habitaciones | Hotel Pacific Reef</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-habitaciones">
    <header class="topbar">
        <a class="brand" href="cliente.php">Hotel Pacific Reef</a>
        <nav class="nav" aria-label="Navegación">
            <a href="cliente.php">Catálogo principal</a>
            <a href="mis_reservas.php">Mis reservas</a>
        </nav>
    </header>
    <main>
        <section class="intro">
            <h1>Habitaciones</h1>
            <p>Consulta tarifas, características y fechas ocupadas. Los días reservados incluyen el checkout; la siguiente estadía comienza al día siguiente.</p>
        </section>
        <section class="room-grid" aria-label="Habitaciones y disponibilidad">
            <?php if ($habitaciones->num_rows > 0): ?>
                <?php while ($habitacion = $habitaciones->fetch_assoc()): ?>
                    <?php
                    $reservasHabitacion = $reservasPorHabitacion[$habitacion['idHabitacion']] ?? [];
                    $hoy = date('Y-m-d');
                    $reservaActual = null;
                    $proximaReserva = null;
                    $disponibleDesde = null;
                    foreach ($reservasHabitacion as $reserva) {
                        if ($reserva['inicio'] <= $hoy && $reserva['fin'] >= $hoy) {
                            $reservaActual = $reserva;
                            $disponibleDesde = date('Y-m-d', strtotime($reserva['fin'] . ' +1 day'));
                        } elseif ($reserva['inicio'] > $hoy && $proximaReserva === null) {
                            $proximaReserva = $reserva;
                        }
                    }
                    if ($disponibleDesde === null && $proximaReserva !== null) {
                        $disponibleDesde = date('Y-m-d', strtotime($proximaReserva['fin'] . ' +1 day'));
                    }
                    if ($disponibleDesde !== null) {
                        do {
                            $anterior = $disponibleDesde;
                            foreach ($reservasHabitacion as $reserva) {
                                if ($reserva['inicio'] <= $disponibleDesde && $reserva['fin'] >= $disponibleDesde) {
                                    $disponibleDesde = date('Y-m-d', strtotime($reserva['fin'] . ' +1 day'));
                                }
                            }
                        } while ($anterior !== $disponibleDesde);
                    }
                    $bloqueoFijo = !in_array($habitacion['estado'], ['Disponible', 'Ocupada'], true)
                        || ($habitacion['estado'] === 'Ocupada' && $reservaActual === null);
                    $reservada = $reservaActual !== null || $proximaReserva !== null;
                    $categoria = $habitacion['categoria'] ?? 'Turista';
                    $id = (int) $habitacion['idHabitacion'];
                    ?>
                    <article class="room-card<?php echo $reservada ? ' reserved' : ''; ?>">
                        <div class="room-image">
                            <img src="<?php echo htmlspecialchars($imagenes[$categoria] ?? $imagenes['Turista']); ?>" alt="Habitación <?php echo $id; ?>">
                            <?php if ($reservaActual): ?>
                                <div class="room-status">Reservada ahora · disponible nuevamente desde <?php echo htmlspecialchars(fechaHabitacion($disponibleDesde)); ?></div>
                            <?php elseif ($proximaReserva): ?>
                                <div class="room-status">Reservada del <?php echo htmlspecialchars(fechaHabitacion($proximaReserva['inicio'])); ?> al <?php echo htmlspecialchars(fechaHabitacion($proximaReserva['fin'])); ?> · disponible desde <?php echo htmlspecialchars(fechaHabitacion($disponibleDesde)); ?></div>
                            <?php elseif ($bloqueoFijo): ?>
                                <div class="room-status">No disponible</div>
                            <?php else: ?>
                                <div class="room-status">Disponible para reservar</div>
                            <?php endif; ?>
                        </div>
                        <div class="room-body">
                            <div class="room-heading">
                                <h2>Habitación <?php echo htmlspecialchars($habitacion['numero']); ?></h2>
                                <span class="category"><?php echo htmlspecialchars($categoria); ?></span>
                            </div>
                            <div class="price"><?php echo htmlspecialchars(formatearPrecioHabitacion($habitacion['valorDiario'])); ?> <small>por noche</small></div>
                            <div class="details">
                                <span>Piso <?php echo (int) $habitacion['piso']; ?></span>
                                <span><?php echo $bloqueoFijo ? 'Mantenimiento / inactiva' : ($reservada ? 'Con reserva' : 'Sin reservas'); ?></span>
                            </div>
                            <?php if (!empty($habitacion['descripcion'])): ?>
                                <p class="description"><?php echo htmlspecialchars($habitacion['descripcion']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($habitacion['equipamiento'])): ?>
                                <p class="equipment"><strong>Incluye:</strong> <?php echo htmlspecialchars($habitacion['equipamiento']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="calendar">
                            <p class="calendar-title">Calendario · <?php echo htmlspecialchars(date('F Y')); ?></p>
                            <div class="calendar-grid" data-calendar="<?php echo htmlspecialchars(json_encode($reservasHabitacion, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8'); ?>" data-fixed="<?php echo $bloqueoFijo ? 'true' : 'false'; ?>" data-month="<?php echo date('Y-m'); ?>"></div>
                            <div class="legend">Los días grises incluyen el día anterior y posterior a cada reserva.</div>
                        </div>
                        <?php if (!$bloqueoFijo): ?>
                            <form class="booking-form" method="POST" action="cliente.php">
                                <input type="hidden" name="csrfReserva" value="<?php echo htmlspecialchars($_SESSION['csrfReserva']); ?>">
                                <input type="hidden" name="idHabitacion" value="<?php echo $id; ?>">
                                <div>
                                    <label for="ingreso-<?php echo $id; ?>">Ingreso</label>
                                    <input id="ingreso-<?php echo $id; ?>" type="date" name="fechaIngreso" min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div>
                                    <label for="salida-<?php echo $id; ?>">Salida</label>
                                    <input id="salida-<?php echo $id; ?>" type="date" name="fechaSalida" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                                </div>
                                <button id="consultar-disponibilidad-<?php echo $id; ?>" class="button" type="submit" name="accion" value="consultar_disponibilidad">Consultar fechas y reservar</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty">No hay habitaciones disponibles.</div>
            <?php endif; ?>
        </section>
    </main>
    <script>
        const weekdayNames = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa', 'Do'];
        const offsetDateKey = (key, days) => {
            const date = new Date(`${key}T00:00:00Z`);
            date.setUTCDate(date.getUTCDate() + days);
            return date.toISOString().slice(0, 10);
        };
        document.querySelectorAll('[data-calendar]').forEach(grid => {
            const [year, month] = grid.dataset.month.split('-').map(Number);
            const booked = JSON.parse(grid.dataset.calendar);
            const firstDay = (new Date(year, month - 1, 1).getDay() + 6) % 7;
            const days = new Date(year, month, 0).getDate();
            weekdayNames.forEach(name => {
                const day = document.createElement('span');
                day.className = 'calendar-day weekdays';
                day.textContent = name;
                grid.append(day);
            });
            for (let i = 0; i < firstDay; i++) grid.append(document.createElement('span'));
            for (let n = 1; n <= days; n++) {
                const key = `${year}-${String(month).padStart(2, '0')}-${String(n).padStart(2, '0')}`;
                const unavailable = grid.dataset.fixed === 'true' || booked.some(range =>
                    offsetDateKey(range.inicio, -1) <= key && key <= offsetDateKey(range.fin, 1)
                );
                const cell = document.createElement('span');
                cell.className = `calendar-day${unavailable ? ' booked' : ''}`;
                cell.textContent = n;
                grid.append(cell);
            }
        });
        document.querySelectorAll('.booking-form').forEach(form => {
            const arrival = form.querySelector('[name="fechaIngreso"]');
            const departure = form.querySelector('[name="fechaSalida"]');
            arrival.addEventListener('change', () => {
                const nextDay = new Date(`${arrival.value}T00:00:00`);
                nextDay.setDate(nextDay.getDate() + 1);
                departure.min = `${nextDay.getFullYear()}-${String(nextDay.getMonth() + 1).padStart(2, '0')}-${String(nextDay.getDate()).padStart(2, '0')}`;
                if (departure.value < departure.min) departure.value = departure.min;
            });
        });
    </script>
</body>
</html>
