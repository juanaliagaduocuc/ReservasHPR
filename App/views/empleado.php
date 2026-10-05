<?php
session_start();

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] != 2) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

function getColumnList($conn, $table)
{
    $cols = [];
    $result = $conn->query("SHOW COLUMNS FROM `" . $table . "`");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $cols[] = $row['Field'];
        }
    }
    return $cols;
}

function formatFechaEs($fecha)
{
    if (!$fecha || $fecha === '0000-00-00' || preg_match('/^\d+$/', (string) $fecha)) {
        return '';
    }

    try {
        $date = new DateTime($fecha);
        return $date->format('d-m-Y');
    } catch (Exception $e) {
        return (string) $fecha;
    }
}

function detectarColumnaFecha($columns, $preferencias)
{
    foreach ($preferencias as $preferencia) {
        if (in_array($preferencia, $columns, true)) {
            return $preferencia;
        }
    }

    foreach ($columns as $column) {
        $lower = strtolower($column);
        if (str_contains($lower, 'fecha') || str_contains($lower, 'inicio') || str_contains($lower, 'fin') || str_contains($lower, 'desde') || str_contains($lower, 'hasta')) {
            if (!preg_match('/(?:^|_)(id|numero|codigo)(?:$|_)/i', $column)) {
                return $column;
            }
        }
    }

    return null;
}

$reservaColumns = getColumnList($conn, 'reserva');
$fechaInicioCol = detectarColumnaFecha($reservaColumns, ['fechaInicio', 'fecha_inicio', 'fecha_inicial', 'fechaEntrada', 'fecha_entrada']);
$fechaFinCol = detectarColumnaFecha($reservaColumns, ['fechaFin', 'fecha_fin', 'fecha_final', 'fechaSalida', 'fecha_salida']);
$ocupadas = $conn->query("SELECT r.*, h.numero, h.idCategoria, c.nombre AS nombreCliente FROM reserva r INNER JOIN habitacion h ON h.idHabitacion = r.idHabitacion INNER JOIN cliente c ON c.idCliente = r.idCliente WHERE r.estado <> 'Cancelada' ORDER BY r.idReserva DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Empleado</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-empleado">
    <header class="topbar">
        <a href="#" class="brand" aria-label="Hotel Pacific Reef">
            <span class="brand-mark" aria-hidden="true"></span>
            <span>Hotel Pacific Reef</span>
        </a>
        <nav class="top-nav" aria-label="Navegación principal">
            <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario']); ?></span>
            <a href="../index.php" class="cta">Cerrar sesión</a>
        </nav>
    </header>

    <div class="panel-wrap">
        <section class="panel-hero">
            <h1>Panel Empleado</h1>
            <div class="subtitle">Control y seguimiento de reservas y disponibilidad.</div>
        </section>

        <div class="container-fluid px-0">

        <div class="row g-4">
            <div class="col-md-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title">Reservas</h5>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title">Habitaciones Asignadas</h5>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title">Calendario</h5>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title">Clientes</h5>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-2">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Habitaciones reservadas</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Habitación</th>
                                    <th>Cliente</th>
                                    <th>Desde</th>
                                    <th>Hasta</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($ocupadas && $ocupadas->num_rows > 0): ?>
                                    <?php while ($reserva = $ocupadas->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($reserva['numero'] . ' - ' . ($reserva['idCategoria'] ?? '')); ?></td>
                                            <td><?php echo htmlspecialchars($reserva['nombreCliente']); ?></td>
                                            <td><?php echo htmlspecialchars(formatFechaEs($reserva[$fechaInicioCol] ?? '')); ?></td>
                                            <td><?php echo htmlspecialchars(formatFechaEs($reserva[$fechaFinCol] ?? '')); ?></td>
                                            <td><?php echo htmlspecialchars($reserva['estado']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No hay habitaciones reservadas.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>