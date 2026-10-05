<?php
session_start();

if (!isset($_SESSION['cliente'], $_SESSION['idCliente'])) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$stmt = $conn->prepare(
    "SELECT r.idReserva, r.fechaIngreso, r.fechaSalida, r.cantidadDias,
            r.valorTotal, r.valorAnticipo, r.estado, h.numero, c.nombre AS categoria
     FROM reserva r
     INNER JOIN habitacion h ON h.idHabitacion = r.idHabitacion
     LEFT JOIN categoria c ON c.idCategoria = h.idCategoria
     WHERE r.idCliente = ?
     ORDER BY r.fechaIngreso DESC, r.idReserva DESC"
);

if (!$stmt) {
    die('No se pudieron consultar tus reservas: ' . $conn->error);
}

$idCliente = (int) $_SESSION['idCliente'];
$stmt->bind_param('i', $idCliente);

if (!$stmt->execute()) {
    die('No se pudieron consultar tus reservas: ' . $stmt->error);
}

$reservas = $stmt->get_result();

function estadoReservaCliente($estado, $ingreso, $salida)
{
    $hoy = date('Y-m-d');
    if ($estado === 'Cancelada') {
        return 'Cancelada';
    }
    if ($salida < $hoy) {
        return 'Finalizada';
    }
    if ($ingreso <= $hoy && $salida >= $hoy) {
        return 'En proceso';
    }
    return $estado;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis reservas | Hotel Pacific Reef</title>
    <style>
        :root { --text: #123c3a; --line: rgba(18,60,58,.2); --sand: #e5e0d9; --teal: #0f4f57; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: linear-gradient(180deg,#e2e4e0 0%,var(--sand) 100%); color: var(--text); font-family: 'Segoe UI',sans-serif; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 2rem; background: rgba(246,244,240,.96); border-block: 2px solid rgba(13,123,154,.7); }
        .brand { color: var(--text); text-decoration: none; text-transform: uppercase; font-weight: 700; letter-spacing: .06em; }
        .back { color: var(--text); text-decoration: none; border: 1px solid rgba(14,81,93,.5); border-radius: 999px; padding: .55rem 1rem; }
        main { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        h1 { margin: 0 0 .4rem; font-family: Georgia,serif; font-size: clamp(2.5rem,5vw,4rem); }
        .intro { margin: 0 0 1.5rem; color: rgba(18,60,58,.75); }
        .table-wrap { overflow-x: auto; border: 1px solid var(--line); border-radius: 1rem; background: rgba(255,255,255,.35); }
        table { width: 100%; border-collapse: collapse; min-width: 760px; }
        th,td { padding: .9rem 1rem; text-align: left; border-bottom: 1px solid var(--line); }
        th { background: rgba(10,75,82,.94); color: #f5efe7; }
        tr:last-child td { border-bottom: 0; }
        .status { display: inline-block; padding: .35rem .7rem; border: 1px solid var(--line); border-radius: 999px; font-size: .85rem; }
        .empty { padding: 2rem; text-align: center; color: rgba(18,60,58,.7); }
        @media (max-width: 600px) { .topbar { padding: 1rem; flex-wrap: wrap; } }
    </style>
</head>
<body>
    <header class="topbar">
        <a href="cliente.php" class="brand">Hotel Pacific Reef</a>
        <a href="habitaciones.php" class="back">Volver a habitaciones</a>
    </header>
    <main>
        <h1>Mis reservas</h1>
        <p class="intro">Consulta tus estadías, fechas e importes.</p>
        <div class="table-wrap">
            <?php if ($reservas->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Habitación</th>
                            <th>Categoría</th>
                            <th>Ingreso</th>
                            <th>Salida</th>
                            <th>Noches</th>
                            <th>Total</th>
                            <th>Anticipo</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($reserva = $reservas->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($reserva['numero']); ?></td>
                                <td><?php echo htmlspecialchars($reserva['categoria'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars(date('d-m-Y', strtotime($reserva['fechaIngreso']))); ?></td>
                                <td><?php echo htmlspecialchars(date('d-m-Y', strtotime($reserva['fechaSalida']))); ?></td>
                                <td><?php echo (int) $reserva['cantidadDias']; ?></td>
                                <td>$ <?php echo number_format((float) $reserva['valorTotal'], 0, ',', '.'); ?></td>
                                <td>$ <?php echo number_format((float) $reserva['valorAnticipo'], 0, ',', '.'); ?></td>
                                <td><span class="status"><?php echo htmlspecialchars(estadoReservaCliente($reserva['estado'], $reserva['fechaIngreso'], $reserva['fechaSalida'])); ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty">Todavía no tienes reservas. <a href="cliente.php">Consulta la disponibilidad de las habitaciones.</a></div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
<?php $stmt->close(); ?>
