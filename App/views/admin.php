<?php
session_start();

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] != 1) {
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

function safeString($conn, $value)
{
    return $conn->real_escape_string(trim($value));
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

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar_habitacion') {
        $id = $_POST['id_habitacion'] ?? '';
        $numero = safeString($conn, $_POST['numero'] ?? '');
        $tipo = intval($_POST['idCategoria'] ?? $_POST['tipo'] ?? 0);
        $piso = intval($_POST['piso'] ?? 1);
        $descripcion = safeString($conn, $_POST['descripcion'] ?? '');
        $equipamiento = safeString($conn, $_POST['equipamiento'] ?? '');
        $precio = floatval($_POST['precio'] ?? $_POST['valorDiario'] ?? 0);
        $estado = safeString($conn, $_POST['estado'] ?? 'Disponible');

        if ($tipo <= 0) {
            $tipo = 1;
        }

        if ($id !== '') {
            $sql = "UPDATE habitacion SET numero='$numero', piso='$piso', descripcion='$descripcion', equipamiento='$equipamiento', valorDiario='$precio', estado='$estado', idCategoria='$tipo' WHERE idHabitacion='$id'";
        } else {
            $sql = "INSERT INTO habitacion (numero, piso, descripcion, equipamiento, valorDiario, estado, idCategoria) VALUES ('$numero', '$piso', '$descripcion', '$equipamiento', '$precio', '$estado', '$tipo')";
        }

        if ($conn->query($sql)) {
            $mensaje = 'Habitación guardada correctamente.';
        } else {
            $mensaje = 'Error al guardar la habitación: ' . $conn->error;
        }
    }

    if ($accion === 'eliminar_habitacion') {
        $id = $_POST['id'] ?? 0;
        $sql = "UPDATE habitacion SET estado='Deshabilitada' WHERE idHabitacion='$id'";
        if ($conn->query($sql)) {
            $mensaje = 'Habitación deshabilitada. Puede reactivarla desde la sección de inactivas.';
        } else {
            $mensaje = 'Error al deshabilitar la habitación: ' . $conn->error;
        }
    }

    if ($accion === 'reactivar_habitacion') {
        $id = $_POST['id'] ?? 0;
        $sql = "UPDATE habitacion SET estado='Disponible' WHERE idHabitacion='$id'";
        if ($conn->query($sql)) {
            $mensaje = 'Habitación reactivada correctamente.';
        } else {
            $mensaje = 'Error al reactivar la habitación: ' . $conn->error;
        }
    }

    if ($accion === 'guardar_cliente') {
        $id = $_POST['id_cliente'] ?? '';
        $nombre = safeString($conn, $_POST['nombre'] ?? '');
        $email = safeString($conn, $_POST['email'] ?? '');
        $telefono = safeString($conn, $_POST['telefono'] ?? '');
        $password = safeString($conn, $_POST['password'] ?? '');

        if ($id !== '') {
            $sql = "UPDATE cliente SET nombre='$nombre', email='$email', telefono='$telefono', password='$password' WHERE idCliente='$id'";
        } else {
            $sql = "INSERT INTO cliente (nombre, email, telefono, password) VALUES ('$nombre', '$email', '$telefono', '$password')";
        }

        if ($conn->query($sql)) {
            $mensaje = 'Cliente guardado correctamente.';
        } else {
            $mensaje = 'Error al guardar el cliente: ' . $conn->error;
        }
    }

    if ($accion === 'eliminar_cliente') {
        $id = $_POST['id'] ?? 0;
        $sql = "DELETE FROM cliente WHERE idCliente='$id'";
        if ($conn->query($sql)) {
            $mensaje = 'Cliente eliminado.';
        } else {
            $mensaje = 'Error al eliminar el cliente: ' . $conn->error;
        }
    }

    if ($accion === 'guardar_empleado') {
        $id = $_POST['id_empleado'] ?? '';
        $nombre = safeString($conn, $_POST['nombre'] ?? '');
        $email = safeString($conn, $_POST['email'] ?? '');
        $password = safeString($conn, $_POST['password'] ?? '');
        $rol = intval($_POST['idRol'] ?? 2);

        if ($id !== '') {
            $sql = "UPDATE empleado SET nombre='$nombre', email='$email', password='$password', idRol='$rol' WHERE idEmpleado='$id'";
        } else {
            $sql = "INSERT INTO empleado (nombre, email, password, idRol) VALUES ('$nombre', '$email', '$password', '$rol')";
        }

        if ($conn->query($sql)) {
            $mensaje = 'Empleado guardado correctamente.';
        } else {
            $mensaje = 'Error al guardar el empleado: ' . $conn->error;
        }
    }

    if ($accion === 'eliminar_empleado') {
        $id = $_POST['id'] ?? 0;
        $sql = "DELETE FROM empleado WHERE idEmpleado='$id'";
        if ($conn->query($sql)) {
            $mensaje = 'Empleado eliminado.';
        } else {
            $mensaje = 'Error al eliminar el empleado: ' . $conn->error;
        }
    }
}

$editHabitacion = $_GET['edit_habitacion'] ?? null;
$editCliente = $_GET['edit_cliente'] ?? null;
$editEmpleado = $_GET['edit_empleado'] ?? null;

$habitacionEdit = null;
if ($editHabitacion) {
    $result = $conn->query("SELECT * FROM habitacion WHERE idHabitacion='$editHabitacion' LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $habitacionEdit = $result->fetch_assoc();
    }
}

$clienteEdit = null;
if ($editCliente) {
    $result = $conn->query("SELECT * FROM cliente WHERE idCliente='$editCliente' LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $clienteEdit = $result->fetch_assoc();
    }
}

$empleadoEdit = null;
if ($editEmpleado) {
    $result = $conn->query("SELECT * FROM empleado WHERE idEmpleado='$editEmpleado' LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $empleadoEdit = $result->fetch_assoc();
    }
}

$reservaColumns = getColumnList($conn, 'reserva');
$fechaInicioCol = detectarColumnaFecha($reservaColumns, ['fechaInicio', 'fecha_inicio', 'fecha_inicial', 'fechaEntrada', 'fecha_entrada']);
$fechaFinCol = detectarColumnaFecha($reservaColumns, ['fechaFin', 'fecha_fin', 'fecha_final', 'fechaSalida', 'fecha_salida']);

$habitaciones = $conn->query("SELECT * FROM habitacion WHERE estado <> 'Deshabilitada' AND estado <> 'Eliminada' ORDER BY idHabitacion DESC");
$habitacionesInactivas = $conn->query("SELECT * FROM habitacion WHERE estado IN ('Deshabilitada', 'Eliminada') ORDER BY idHabitacion DESC");
$clientes = $conn->query("SELECT * FROM cliente ORDER BY idCliente DESC");
$empleados = $conn->query("SELECT * FROM empleado ORDER BY idEmpleado DESC");
$reservasActivas = $conn->query("SELECT r.*, h.numero, h.idCategoria, c.nombre AS nombreCliente FROM reserva r INNER JOIN habitacion h ON h.idHabitacion = r.idHabitacion INNER JOIN cliente c ON c.idCliente = r.idCliente WHERE r.estado <> 'Cancelada' ORDER BY r.idReserva DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrador</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        :root {
            --bg-sand: #e5e0d9;
            --bg-deep: #0b2d31;
            --panel: rgba(255,255,255,0.18);
            --soft: #f5f1ed;
            --line: rgba(18, 60, 58, 0.2);
            --text: #123c3a;
            --muted: rgba(18, 60, 58, 0.7);
            --teal: #0f4f57;
            --teal-deep: #0a363a;
            --teal-strong: #0d6b6e;
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
            letter-spacing: 0.02em;
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

        .btn-primary,
        .btn-success,
        .btn-warning,
        .btn-outline-primary,
        .btn-outline-danger,
        .btn-outline-warning,
        .btn-outline-success {
            border-radius: 999px;
        }

        .btn-primary {
            background: linear-gradient(180deg, rgba(7,72,78,1), rgba(13,95,87,1));
            border: none;
        }

        .btn-success {
            background: linear-gradient(180deg, #0d5d63, #0a4a4d);
            border: none;
        }

        .btn-warning {
            background: linear-gradient(180deg, #d8d0bf, #d0c4b0);
            border: none;
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
            <h1>Panel Administrador</h1>
            <div class="subtitle">Gestión de habitaciones, clientes, empleados y reservas.</div>
        </section>

        <div class="container-fluid px-0 mb-5">
        <?php if ($mensaje): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title">Habitaciones</h5>
                        <p class="mb-0"><?php echo $habitaciones ? $habitaciones->num_rows : 0; ?> registradas</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title">Clientes</h5>
                        <p class="mb-0"><?php echo $clientes ? $clientes->num_rows : 0; ?> registrados</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title">Empleados</h5>
                        <p class="mb-0"><?php echo $empleados ? $empleados->num_rows : 0; ?> registrados</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title">Reportes</h5>
                        <p class="mb-0">Listado general</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Habitaciones</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="accion" value="guardar_habitacion">
                            <input type="hidden" name="id_habitacion" value="<?php echo htmlspecialchars($habitacionEdit['idHabitacion'] ?? ''); ?>">

                            <div class="mb-3">
                                <label class="form-label">Número</label>
                                <input type="text" name="numero" class="form-control" value="<?php echo htmlspecialchars($habitacionEdit['numero'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Categoría</label>
                                <input type="number" name="idCategoria" class="form-control" value="<?php echo htmlspecialchars($habitacionEdit['idCategoria'] ?? 1); ?>" min="1" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Piso</label>
                                <input type="number" name="piso" class="form-control" value="<?php echo htmlspecialchars($habitacionEdit['piso'] ?? 1); ?>" min="1" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Precio diario</label>
                                <input type="number" step="0.01" name="valorDiario" class="form-control" value="<?php echo htmlspecialchars($habitacionEdit['valorDiario'] ?? 0); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descripción</label>
                                <textarea name="descripcion" class="form-control" rows="2"><?php echo htmlspecialchars($habitacionEdit['descripcion'] ?? ''); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Equipamiento</label>
                                <textarea name="equipamiento" class="form-control" rows="2"><?php echo htmlspecialchars($habitacionEdit['equipamiento'] ?? ''); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Estado</label>
                                <select name="estado" class="form-select">
                                    <option value="Disponible" <?php echo (($habitacionEdit['estado'] ?? 'Disponible') === 'Disponible') ? 'selected' : ''; ?>>Disponible</option>
                                    <option value="Ocupada" <?php echo (($habitacionEdit['estado'] ?? 'Disponible') === 'Ocupada') ? 'selected' : ''; ?>>Ocupada</option>
                                    <option value="Mantenimiento" <?php echo (($habitacionEdit['estado'] ?? 'Disponible') === 'Mantenimiento') ? 'selected' : ''; ?>>Mantenimiento</option>
                                    <option value="Deshabilitada" <?php echo (($habitacionEdit['estado'] ?? '') === 'Deshabilitada') ? 'selected' : ''; ?>>Deshabilitada</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary w-100"><?php echo $habitacionEdit ? 'Actualizar' : 'Guardar'; ?> habitación</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Clientes</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="accion" value="guardar_cliente">
                            <input type="hidden" name="id_cliente" value="<?php echo htmlspecialchars($clienteEdit['idCliente'] ?? ''); ?>">

                            <div class="mb-3">
                                <label class="form-label">Nombre</label>
                                <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($clienteEdit['nombre'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($clienteEdit['email'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="text" name="telefono" class="form-control" value="<?php echo htmlspecialchars($clienteEdit['telefono'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contraseña</label>
                                <input type="text" name="password" class="form-control" value="<?php echo htmlspecialchars($clienteEdit['password'] ?? ''); ?>" required>
                            </div>

                            <button type="submit" class="btn btn-success w-100"><?php echo $clienteEdit ? 'Actualizar' : 'Guardar'; ?> cliente</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Empleados</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="accion" value="guardar_empleado">
                            <input type="hidden" name="id_empleado" value="<?php echo htmlspecialchars($empleadoEdit['idEmpleado'] ?? ''); ?>">

                            <div class="mb-3">
                                <label class="form-label">Nombre</label>
                                <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($empleadoEdit['nombre'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($empleadoEdit['email'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contraseña</label>
                                <input type="text" name="password" class="form-control" value="<?php echo htmlspecialchars($empleadoEdit['password'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Rol</label>
                                <select name="idRol" class="form-select">
                                    <option value="1" <?php echo (($empleadoEdit['idRol'] ?? 2) == 1) ? 'selected' : ''; ?>>Administrador</option>
                                    <option value="2" <?php echo (($empleadoEdit['idRol'] ?? 2) == 2) ? 'selected' : ''; ?>>Empleado</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-warning w-100 text-dark"><?php echo $empleadoEdit ? 'Actualizar' : 'Guardar'; ?> empleado</button>
                        </form>
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
                                <?php if ($reservasActivas && $reservasActivas->num_rows > 0): ?>
                                    <?php while ($reserva = $reservasActivas->fetch_assoc()): ?>
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

        <div class="row g-4 mt-2">
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Listado de habitaciones</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Número</th>
                                    <th>Tipo</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($habitaciones && $habitaciones->num_rows > 0): ?>
                                    <?php while ($habitacion = $habitaciones->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($habitacion['numero']); ?></td>
                                            <td><?php echo htmlspecialchars($habitacion['idCategoria']); ?></td>
                                            <td><?php echo htmlspecialchars($habitacion['estado']); ?></td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="?edit_habitacion=<?php echo $habitacion['idHabitacion']; ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="accion" value="eliminar_habitacion">
                                                        <input type="hidden" name="id" value="<?php echo $habitacion['idHabitacion']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-warning">Deshabilitar</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No hay habitaciones.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Habitaciones inactivas</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Número</th>
                                    <th>Tipo</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($habitacionesInactivas && $habitacionesInactivas->num_rows > 0): ?>
                                    <?php while ($habitacion = $habitacionesInactivas->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($habitacion['numero']); ?></td>
                                            <td><?php echo htmlspecialchars($habitacion['idCategoria']); ?></td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="accion" value="reactivar_habitacion">
                                                    <input type="hidden" name="id" value="<?php echo $habitacion['idHabitacion']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-success">Reintegrar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">No hay habitaciones inactivas.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Listado de clientes</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($clientes && $clientes->num_rows > 0): ?>
                                    <?php while ($cliente = $clientes->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($cliente['email']); ?></td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="?edit_cliente=<?php echo $cliente['idCliente']; ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="accion" value="eliminar_cliente">
                                                        <input type="hidden" name="id" value="<?php echo $cliente['idCliente']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">No hay clientes.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Listado de empleados</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($empleados && $empleados->num_rows > 0): ?>
                                    <?php while ($empleado = $empleados->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($empleado['nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($empleado['email']); ?></td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="?edit_empleado=<?php echo $empleado['idEmpleado']; ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="accion" value="eliminar_empleado">
                                                        <input type="hidden" name="id" value="<?php echo $empleado['idEmpleado']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">No hay empleados.</td>
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