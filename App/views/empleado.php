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
    <style>
        :root {
            --bg-sand: #e5e0d9;
            --text: #123c3a;
            --line: rgba(18, 60, 58, 0.2);
            --teal: #0f4f57;
        }

        body {
            background: linear-gradient(180deg, #e2e4e0 0%, #e8e0d5 100%);
            color: var(--text);
            font-family: 'Segoe UI', sans-serif;
        }

        .topbar {
            background: rgba(246, 244, 240, 0.96);
            border: 2px solid rgba(13, 123, 154, 0.7);
            border-left: none;
            border-right: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.05rem 2.2rem 0.95rem;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            font-size: 1.05rem;
            text-transform: uppercase;
            color: var(--text);
            text-decoration: none;
        }

        .brand-mark {
            width: 1.1rem;
            height: 1.1rem;
            border: 2px solid rgba(14, 81, 93, 0.9);
            border-radius: 50%;
            position: relative;
            display: inline-block;
        }

        .brand-mark::after {
            content: "";
            position: absolute;
            inset: 0.2rem;
            border-radius: 50%;
            border: 1px solid rgba(14,81,93,0.9);
        }

        .top-nav {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            color: var(--text);
        }

        .top-nav a {
            color: var(--text);
            text-decoration: none;
            border: 1px solid rgba(14, 81, 93, 0.45);
            border-radius: 999px;
            padding: 0.5rem 0.85rem;
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .top-nav .cta {
            background: rgba(14, 81, 93, 0.04);
            font-weight: 700;
        }

        .panel-wrap {
            max-width: 1400px;
            margin: 2rem auto 3rem;
            padding: 0 1.2rem;
        }

        .panel-hero {
            background: linear-gradient(90deg, rgba(7,36,38,0.9), rgba(9,55,60,0.7));
            border-radius: 1.3rem;
            padding: 2rem 2rem 2.2rem;
            margin-bottom: 2rem;
            box-shadow: 0 16px 30px rgba(12, 49, 52, 0.12);
        }

        .panel-hero h1 {
            margin: 0;
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(2.8rem, 5vw, 4.1rem);
            color: #f5efe7;
            letter-spacing: -0.04em;
            line-height: 0.9;
        }

        .panel-hero .subtitle {
            margin-top: 0.9rem;
            color: rgba(245,239,231,0.8);
            font-size: 1.02rem;
        }

        .card {
            background: rgba(255,255,255,0.18);
            border: 1px solid var(--line);
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(14, 58, 61, 0.08);
        }

        .card-header {
            background: rgba(10, 75, 82, 0.94) !important;
            color: #f5efe7 !important;
            border-bottom: none !important;
            border-radius: 1rem 1rem 0 0 !important;
            padding: 0.9rem 1rem;
        }

        .card-body {
            background: rgba(255,255,255,0.08);
        }

        .table {
            --bs-table-bg: transparent;
            color: var(--text);
        }

        @media (max-width: 760px) {
            .topbar { padding-inline: 1rem; flex-wrap: wrap; gap: 0.8rem; }
            .top-nav { width: 100%; justify-content: flex-end; flex-wrap: wrap; }
            .panel-hero { padding: 1.4rem 1.2rem; }
        }
    </style>
</head>
<body>
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