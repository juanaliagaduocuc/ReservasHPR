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
    <style>
        :root { --sand: #e5e0d9; --text: #123c3a; --muted: rgba(18,60,58,.7); --teal: #0f4f57; --line: rgba(18,60,58,.25); }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--sand); color: var(--text); font-family: Inter, sans-serif; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 2rem; background: rgba(246,244,240,.96); border-block: 2px solid rgba(13,123,154,.7); }
        .brand, .nav a { color: var(--text); text-decoration: none; }
        .brand { font-family: 'Cormorant Garamond',serif; font-size: 1.2rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
        .nav { display: flex; flex-wrap: wrap; gap: .7rem; }
        .nav a { border: 1px solid var(--line); border-radius: 999px; padding: .55rem .9rem; font-size: .8rem; }
        main { max-width: 1250px; margin: 2rem auto; padding: 0 1rem 3rem; }
        .intro { margin-bottom: 1.5rem; }
        h1 { margin: 0; font: 500 clamp(2.8rem,6vw,4.7rem)/.95 'Cormorant Garamond',serif; }
        .intro p { color: var(--muted); line-height: 1.5; }
        .room-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(290px,1fr)); gap: 1.2rem; }
        .room-card { overflow: hidden; border: 1px solid var(--line); border-radius: 1rem; background: rgba(255,255,255,.35); box-shadow: 0 8px 22px rgba(15,58,60,.06); }
        .room-image { position: relative; height: 210px; background-size: cover; background-position: center; }
        .room-card.reserved .room-image { filter: grayscale(1) brightness(.72); }
        .room-status { position: absolute; inset: auto 0 0; padding: .7rem .8rem; background: rgba(11,45,49,.86); color: #fff; text-align: center; font-size: .78rem; line-height: 1.45; font-weight: 700; }
        .room-body { padding: 1rem; }
        .room-heading { display: flex; justify-content: space-between; align-items: start; gap: .8rem; }
        h2 { margin: 0; font: 500 2.3rem/.95 'Cormorant Garamond',serif; }
        .category, .badge { display: inline-block; border: 1px solid var(--line); border-radius: 999px; padding: .35rem .65rem; font-size: .76rem; }
        .price { margin: .75rem 0; font-size: 1.25rem; font-weight: 700; }
        .price small { color: var(--muted); font-size: .76rem; font-weight: 400; }
        .details { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; margin: .8rem 0; font-size: .85rem; }
        .description, .equipment { margin: .6rem 0; color: var(--muted); font-size: .86rem; line-height: 1.5; }
        .calendar { padding: .9rem; border-top: 1px solid var(--line); }
        .calendar-title { margin: 0 0 .7rem; font-size: .9rem; font-weight: 700; }
        .calendar-grid { display: grid; grid-template-columns: repeat(7,minmax(0,1fr)); gap: .22rem; text-align: center; font-size: .73rem; }
        .calendar-day { min-height: 1.8rem; display: grid; place-items: center; border-radius: 50%; }
        .calendar-day.booked { background: #737b78; color: white; }
        .weekdays { color: var(--muted); font-size: .65rem; font-weight: 700; }
        .legend { margin-top: .6rem; color: var(--muted); font-size: .72rem; }
        .booking-form { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; padding: 0 1rem 1rem; }
        .booking-form label { display: block; margin-bottom: .25rem; font-size: .75rem; }
        .booking-form input { width: 100%; min-height: 2.4rem; border: 1px solid var(--line); border-radius: .5rem; padding: .35rem; }
        .button { grid-column: 1/-1; display: block; padding: .75rem 1rem; border: 0; border-radius: 999px; background: linear-gradient(180deg,#07484e,#0d5f57); color: white; text-align: center; text-decoration: none; font-size: .78rem; font-weight: 700; text-transform: uppercase; cursor: pointer; }
        .empty { padding: 2rem; text-align: center; border: 1px solid var(--line); border-radius: 1rem; }
        @media(max-width:650px) { .topbar { padding: 1rem; flex-wrap: wrap; } .booking-form { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
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
                        <div class="room-image" style="background-image:url('<?php echo htmlspecialchars($imagenes[$categoria] ?? $imagenes['Turista']); ?>')">
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
                            <div class="legend">Los días grises no están disponibles.</div>
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
                                <button class="button" type="submit" name="accion" value="consultar_disponibilidad">Consultar fechas y reservar</button>
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
                const unavailable = grid.dataset.fixed === 'true' || booked.some(range => range.inicio <= key && key <= range.fin);
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
