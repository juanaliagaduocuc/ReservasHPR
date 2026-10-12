<?php
session_start();

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] != 1) {
    header('Location: ../index.php');
    exit();
}
$_SESSION['csrfLogout'] = $_SESSION['csrfLogout'] ?? bin2hex(random_bytes(32));

require_once '../config/database.php';

function getReservaColumnList($conn)
{
    $cols = [];
    $result = $conn->query('SHOW COLUMNS FROM `reserva`');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $cols[] = $row['Field'];
        }
    }
    return $cols;
}

function textoEntrada($value)
{
    return is_string($value) ? trim($value) : '';
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
$tipoMensaje = 'info';
$idMensaje = 'mensaje-admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar_habitacion') {
        $id = filter_var($_POST['id_habitacion'] ?? null, FILTER_VALIDATE_INT);
        $id = $id !== false && $id > 0 ? $id : 0;
        $numero = textoEntrada($_POST['numero'] ?? null);
        $tipo = filter_var($_POST['idCategoria'] ?? $_POST['tipo'] ?? 0, FILTER_VALIDATE_INT);
        $tipo = $tipo !== false && $tipo > 0 ? $tipo : 1;
        $piso = intval($_POST['piso'] ?? 1);
        $descripcion = textoEntrada($_POST['descripcion'] ?? null);
        $equipamiento = textoEntrada($_POST['equipamiento'] ?? null);
        $precio = floatval($_POST['precio'] ?? $_POST['valorDiario'] ?? 0);
        $estado = textoEntrada($_POST['estado'] ?? 'Disponible');

        if ($id > 0) {
            $stmt = $conn->prepare('UPDATE habitacion SET numero = ?, piso = ?, descripcion = ?, equipamiento = ?, valorDiario = ?, estado = ?, idCategoria = ? WHERE idHabitacion = ?');
            $stmt->bind_param('sissdsii', $numero, $piso, $descripcion, $equipamiento, $precio, $estado, $tipo, $id);
        } else {
            $stmt = $conn->prepare('INSERT INTO habitacion (numero, piso, descripcion, equipamiento, valorDiario, estado, idCategoria) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('sissdsi', $numero, $piso, $descripcion, $equipamiento, $precio, $estado, $tipo);
        }

        if ($stmt && $stmt->execute()) {
            $stmt->close();
            if ($id > 0) {
                $mensaje = '¡Habitación actualizada exitosamente!';
                $tipoMensaje = 'success';
                $idMensaje = 'mensaje-habitacion-exitoso';
            } else {
                $mensaje = 'Habitación guardada correctamente.';
            }
        } else {
            $error = $stmt ? $stmt->error : $conn->error;
            if ($stmt) {
                $stmt->close();
            }
            $mensaje = ($id > 0 ? 'No se pudo editar la habitación: ' : 'Error al guardar la habitación: ') . $error;
            $tipoMensaje = 'danger';
            $idMensaje = 'mensaje-habitacion-error';
        }
    }

    if ($accion === 'eliminar_habitacion') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $stmt = $conn->prepare("UPDATE habitacion SET estado = 'Deshabilitada' WHERE idHabitacion = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $mensaje = 'Habitación deshabilitada. Puede reactivarla desde la sección de inactivas.';
        } else {
            $mensaje = 'Error al deshabilitar la habitación: ' . $stmt->error;
        }
        $stmt->close();
    }

    if ($accion === 'reactivar_habitacion') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $stmt = $conn->prepare("UPDATE habitacion SET estado = 'Disponible' WHERE idHabitacion = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $mensaje = 'Habitación reactivada correctamente.';
        } else {
            $mensaje = 'Error al reactivar la habitación: ' . $stmt->error;
        }
        $stmt->close();
    }

    if ($accion === 'guardar_cliente') {
        $id = filter_var($_POST['id_cliente'] ?? null, FILTER_VALIDATE_INT);
        $id = $id !== false && $id > 0 ? $id : 0;
        $nombre = textoEntrada($_POST['nombre'] ?? null);
        $email = textoEntrada($_POST['email'] ?? null);
        $telefono = textoEntrada($_POST['telefono'] ?? null);
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        if ($id > 0) {
            if ($password !== '') {
                $password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare('UPDATE cliente SET nombre = ?, email = ?, telefono = ?, password = ? WHERE idCliente = ?');
                $stmt->bind_param('ssssi', $nombre, $email, $telefono, $password, $id);
            } else {
                $stmt = $conn->prepare('UPDATE cliente SET nombre = ?, email = ?, telefono = ? WHERE idCliente = ?');
                $stmt->bind_param('sssi', $nombre, $email, $telefono, $id);
            }
        } else {
            $password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('INSERT INTO cliente (nombre, email, telefono, password) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('ssss', $nombre, $email, $telefono, $password);
        }

        if ($stmt && $stmt->execute()) {
            $mensaje = 'Cliente guardado correctamente.';
        } else {
            $mensaje = 'Error al guardar el cliente: ' . ($stmt ? $stmt->error : $conn->error);
        }
        if ($stmt) {
            $stmt->close();
        }
    }

    if ($accion === 'eliminar_cliente') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $stmt = $conn->prepare('DELETE FROM cliente WHERE idCliente = ?');
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $mensaje = 'Cliente eliminado.';
        } else {
            $mensaje = 'Error al eliminar el cliente: ' . $stmt->error;
        }
        $stmt->close();
    }

    if ($accion === 'guardar_empleado') {
        $id = filter_var($_POST['id_empleado'] ?? null, FILTER_VALIDATE_INT);
        $id = $id !== false && $id > 0 ? $id : 0;
        $nombre = textoEntrada($_POST['nombre'] ?? null);
        $email = textoEntrada($_POST['email'] ?? null);
        $password = textoEntrada($_POST['password'] ?? null);
        $rol = filter_var($_POST['idRol'] ?? 2, FILTER_VALIDATE_INT);
        $rol = $rol !== false && $rol > 0 ? $rol : 2;

        $verificarEmail = $conn->prepare('SELECT idEmpleado FROM empleado WHERE email = ? AND idEmpleado <> ? LIMIT 1');
        if (!$verificarEmail) {
            throw new RuntimeException('No se pudo verificar el correo del empleado: ' . $conn->error);
        }
        $idEmpleadoActual = $id;
        $verificarEmail->bind_param('si', $email, $idEmpleadoActual);
        if (!$verificarEmail->execute()) {
            $errorVerificacion = $verificarEmail->error;
            $verificarEmail->close();
            throw new RuntimeException('No se pudo verificar el correo del empleado: ' . $errorVerificacion);
        }
        $verificarEmail->store_result();
        $emailEnUso = $verificarEmail->num_rows > 0;
        $verificarEmail->close();

        if ($emailEnUso) {
            $mensaje = 'Este correo ya está en uso por otro empleado.';
            $tipoMensaje = 'danger';
            $idMensaje = 'mensaje-empleado-error';
        } else {
            if ($id > 0) {
                $stmt = $conn->prepare('UPDATE empleado SET nombre = ?, email = ?, password = ?, idRol = ? WHERE idEmpleado = ?');
                $stmt->bind_param('sssii', $nombre, $email, $password, $rol, $id);
            } else {
                $stmt = $conn->prepare('INSERT INTO empleado (nombre, email, password, idRol) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('sssi', $nombre, $email, $password, $rol);
            }

            if ($stmt && $stmt->execute()) {
                $mensaje = $id > 0 ? '¡Empleado actualizado exitosamente!' : '¡Empleado creado exitosamente!';
                $tipoMensaje = 'success';
                $idMensaje = 'mensaje-empleado-exitoso';
            } else {
                $mensaje = 'Error al guardar el empleado: ' . ($stmt ? $stmt->error : $conn->error);
            }
            if ($stmt) {
                $stmt->close();
            }
        }
    }

    if ($accion === 'eliminar_empleado') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $stmt = $conn->prepare('DELETE FROM empleado WHERE idEmpleado = ?');
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $mensaje = 'Empleado eliminado.';
        } else {
            $mensaje = 'Error al eliminar el empleado: ' . $stmt->error;
        }
        $stmt->close();
    }
}

$editHabitacion = $_GET['edit_habitacion'] ?? null;
$editCliente = $_GET['edit_cliente'] ?? null;
$editEmpleado = $_GET['edit_empleado'] ?? null;

$habitacionEdit = null;
if (is_scalar($editHabitacion) && ($idEdicion = filter_var($editHabitacion, FILTER_VALIDATE_INT)) && $idEdicion > 0) {
    $stmt = $conn->prepare('SELECT * FROM habitacion WHERE idHabitacion = ? LIMIT 1');
    $stmt->bind_param('i', $idEdicion);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $habitacionEdit = $result->fetch_assoc();
    }
    $stmt->close();
}

$clienteEdit = null;
if (is_scalar($editCliente) && ($idEdicion = filter_var($editCliente, FILTER_VALIDATE_INT)) && $idEdicion > 0) {
    $stmt = $conn->prepare('SELECT * FROM cliente WHERE idCliente = ? LIMIT 1');
    $stmt->bind_param('i', $idEdicion);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $clienteEdit = $result->fetch_assoc();
    }
    $stmt->close();
}

$empleadoEdit = null;
if (is_scalar($editEmpleado) && ($idEdicion = filter_var($editEmpleado, FILTER_VALIDATE_INT)) && $idEdicion > 0) {
    $stmt = $conn->prepare('SELECT * FROM empleado WHERE idEmpleado = ? LIMIT 1');
    $stmt->bind_param('i', $idEdicion);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $empleadoEdit = $result->fetch_assoc();
    }
    $stmt->close();
}

$reservaColumns = getReservaColumnList($conn);
$fechaInicioCol = detectarColumnaFecha($reservaColumns, ['fechaInicio', 'fecha_inicio', 'fecha_inicial', 'fechaEntrada', 'fecha_entrada']) ?? 'fechaIngreso';
$fechaFinCol = detectarColumnaFecha($reservaColumns, ['fechaFin', 'fecha_fin', 'fecha_final', 'fechaSalida', 'fecha_salida']) ?? 'fechaSalida';
$columnaInicioReserva = $fechaInicioCol ? "r.`$fechaInicioCol`" : 'r.fechaIngreso';
$columnaFinReserva = $fechaFinCol ? "r.`$fechaFinCol`" : 'r.fechaSalida';
$estadoReservaSql = "CASE
    WHEN r.estado = 'Cancelada' THEN 'Cancelada'
    WHEN $columnaFinReserva < CURDATE() THEN 'Finalizada'
    WHEN $columnaInicioReserva <= CURDATE() AND $columnaFinReserva >= CURDATE() THEN 'En proceso'
    ELSE r.estado
END";

$habitaciones = $conn->query("SELECT * FROM habitacion WHERE estado <> 'Deshabilitada' AND estado <> 'Eliminada' ORDER BY idHabitacion DESC");
$habitacionesInactivas = $conn->query("SELECT * FROM habitacion WHERE estado IN ('Deshabilitada', 'Eliminada') ORDER BY idHabitacion DESC");
$clientes = $conn->query("SELECT * FROM cliente ORDER BY idCliente DESC");
$empleados = $conn->query("SELECT * FROM empleado ORDER BY idEmpleado DESC");
$estadosReservas = [];
$resultadoEstados = $conn->query("SELECT DISTINCT $estadoReservaSql AS estado FROM reserva r ORDER BY estado");
if (!$resultadoEstados) {
    die('No se pudieron cargar los estados de las reservas: ' . $conn->error);
}
while ($filaEstado = $resultadoEstados->fetch_assoc()) {
    if ($filaEstado['estado'] !== null && $filaEstado['estado'] !== '') {
        $estadosReservas[] = $filaEstado['estado'];
    }
}

$estadoFiltroReserva = $_GET['estadoReserva'] ?? '';
if (!is_string($estadoFiltroReserva) || !in_array($estadoFiltroReserva, $estadosReservas, true)) {
    $estadoFiltroReserva = '';
}
$paginaReservaEntrada = $_GET['paginaReservas'] ?? 1;
$paginaReservas = is_scalar($paginaReservaEntrada)
    ? filter_var($paginaReservaEntrada, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$paginaReservas = $paginaReservas ?: 1;
$condicionEstado = $estadoFiltroReserva === ''
    ? ''
    : ' WHERE (' . $estadoReservaSql . ') = ?';
$conteoReservas = $conn->prepare('SELECT COUNT(*) AS total FROM reserva r' . $condicionEstado);
if ($estadoFiltroReserva !== '') {
    $conteoReservas->bind_param('s', $estadoFiltroReserva);
}
if (!$conteoReservas->execute()) {
    die('No se pudo contar las reservas: ' . $conteoReservas->error);
}
$resultadoConteoReservas = $conteoReservas->get_result();
$totalReservas = (int) $resultadoConteoReservas->fetch_assoc()['total'];
$conteoReservas->close();
$reservasPorPagina = 10;
$totalPaginasReservas = max(1, (int) ceil($totalReservas / $reservasPorPagina));
$paginaReservas = min($paginaReservas, $totalPaginasReservas);
$inicioReservas = ($paginaReservas - 1) * $reservasPorPagina;
$reservasActivas = $conn->prepare(
    "SELECT r.*, h.numero, h.idCategoria, c.nombre AS nombreCliente
     FROM reserva r
     LEFT JOIN habitacion h ON h.idHabitacion = r.idHabitacion
     LEFT JOIN cliente c ON c.idCliente = r.idCliente" .
    $condicionEstado .
    ' ORDER BY r.idReserva DESC LIMIT ? OFFSET ?'
);
if ($estadoFiltroReserva !== '') {
    $reservasActivas->bind_param('sii', $estadoFiltroReserva, $reservasPorPagina, $inicioReservas);
} else {
    $reservasActivas->bind_param('ii', $reservasPorPagina, $inicioReservas);
}
if (!$reservasActivas->execute()) {
    die('No se pudieron cargar las reservas: ' . $reservasActivas->error);
}
$reservasActivas = $reservasActivas->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrador</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-admin">
    <header class="topbar">
        <a href="#" class="brand" aria-label="Hotel Pacific Reef">
            <span class="brand-mark" aria-hidden="true"></span>
            <span>Hotel Pacific Reef</span>
        </a>
        <nav class="top-nav" aria-label="Navegación principal">
            <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario']); ?></span>
            <form class="logout-form" action="../controller/logout.php" method="POST">
                <input type="hidden" name="csrfLogout" value="<?php echo htmlspecialchars($_SESSION['csrfLogout'], ENT_QUOTES, 'UTF-8'); ?>">
                <button id="cerrar-sesion" type="submit" class="logout-button cta">Cerrar sesión</button>
            </form>
        </nav>
    </header>

    <div class="panel-wrap">
        <section class="panel-hero">
            <h1>Panel Administrador</h1>
            <div class="subtitle">Gestión de habitaciones, clientes, empleados y reservas.</div>
        </section>

        <div class="container-fluid px-0 mb-5">
        <?php if ($mensaje): ?>
            <div id="<?php echo $idMensaje; ?>" class="alert alert-<?php echo $tipoMensaje; ?>" role="status"><?php echo htmlspecialchars($mensaje); ?></div>
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
                            <input id="accion-habitacion" type="hidden" name="accion" value="guardar_habitacion">
                            <input id="id-habitacion-edicion" type="hidden" name="id_habitacion" value="<?php echo htmlspecialchars($habitacionEdit['idHabitacion'] ?? ''); ?>">

                            <div class="mb-3">
                                <label class="form-label">Número</label>
                                <input id="numero-habitacion" type="text" name="numero" class="form-control" value="<?php echo htmlspecialchars($habitacionEdit['numero'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Categoría</label>
                                <input id="categoria-habitacion" type="number" name="idCategoria" class="form-control" value="<?php echo htmlspecialchars($habitacionEdit['idCategoria'] ?? 1); ?>" min="1" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Piso</label>
                                <input id="piso-habitacion" type="number" name="piso" class="form-control" value="<?php echo htmlspecialchars($habitacionEdit['piso'] ?? 1); ?>" min="1" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Precio diario</label>
                                <input id="precio-habitacion" type="number" step="0.01" name="valorDiario" class="form-control" value="<?php echo htmlspecialchars($habitacionEdit['valorDiario'] ?? 0); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descripción</label>
                                <textarea id="descripcion-habitacion" name="descripcion" class="form-control" rows="2"><?php echo htmlspecialchars($habitacionEdit['descripcion'] ?? ''); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Equipamiento</label>
                                <textarea id="equipamiento-habitacion" name="equipamiento" class="form-control" rows="2"><?php echo htmlspecialchars($habitacionEdit['equipamiento'] ?? ''); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Estado</label>
                                <select id="estado-habitacion" name="estado" class="form-select">
                                    <option value="Disponible" <?php echo (($habitacionEdit['estado'] ?? 'Disponible') === 'Disponible') ? 'selected' : ''; ?>>Disponible</option>
                                    <option value="Ocupada" <?php echo (($habitacionEdit['estado'] ?? 'Disponible') === 'Ocupada') ? 'selected' : ''; ?>>Ocupada</option>
                                    <option value="Mantenimiento" <?php echo (($habitacionEdit['estado'] ?? 'Disponible') === 'Mantenimiento') ? 'selected' : ''; ?>>Mantenimiento</option>
                                    <option value="Deshabilitada" <?php echo (($habitacionEdit['estado'] ?? '') === 'Deshabilitada') ? 'selected' : ''; ?>>Deshabilitada</option>
                                </select>
                            </div>

                            <button id="guardar-habitacion" type="submit" class="btn btn-primary w-100"><?php echo $habitacionEdit ? 'Actualizar' : 'Guardar'; ?> habitación</button>
                        </form>
                    </div>
                </div>
            </div>

            <?php if ($clienteEdit): ?>
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Editar cliente</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input id="accion-cliente" type="hidden" name="accion" value="guardar_cliente">
                            <input id="id-cliente-edicion" type="hidden" name="id_cliente" value="<?php echo htmlspecialchars($clienteEdit['idCliente'] ?? ''); ?>">

                            <div class="mb-3">
                                <label class="form-label">Nombre</label>
                                <input id="nombre-cliente" type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($clienteEdit['nombre'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input id="email-cliente" type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($clienteEdit['email'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Teléfono</label>
                                <input id="telefono-cliente" type="text" name="telefono" class="form-control" value="<?php echo htmlspecialchars($clienteEdit['telefono'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contraseña</label>
                                <input id="password-cliente" type="password" name="password" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" placeholder="Dejar vacío para mantener la actual">
                            </div>

                            <button id="guardar-cliente" type="submit" class="btn btn-success w-100">Actualizar cliente</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Empleados</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input id="accion-empleado" type="hidden" name="accion" value="guardar_empleado">
                            <input id="id-empleado-edicion" type="hidden" name="id_empleado" value="<?php echo htmlspecialchars($empleadoEdit['idEmpleado'] ?? ''); ?>">

                            <div class="mb-3">
                                <label class="form-label">Nombre</label>
                                <input id="nombre-empleado" type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($empleadoEdit['nombre'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input id="email-empleado" type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($empleadoEdit['email'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contraseña</label>
                                <input id="password-empleado" type="text" name="password" class="form-control" value="<?php echo htmlspecialchars($empleadoEdit['password'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Rol</label>
                                <select id="rol-empleado" name="idRol" class="form-select">
                                    <option value="1" <?php echo (($empleadoEdit['idRol'] ?? 2) == 1) ? 'selected' : ''; ?>>Administrador</option>
                                    <option value="2" <?php echo (($empleadoEdit['idRol'] ?? 2) == 2) ? 'selected' : ''; ?>>Empleado</option>
                                </select>
                            </div>

                            <button id="guardar-empleado" type="submit" class="btn btn-warning w-100 text-dark"><?php echo $empleadoEdit ? 'Actualizar' : 'Guardar'; ?> empleado</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-2">
            <div class="col-12">
                <div id="lista-reservas" class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Habitaciones reservadas</h5>
                    </div>
                    <form method="GET" class="reservation-filter">
                        <label for="estadoReserva">Filtrar por estado</label>
                        <select id="estadoReserva" name="estadoReserva" class="form-select">
                            <option value="">Todos los estados</option>
                            <?php foreach ($estadosReservas as $estadoDisponible): ?>
                                <option value="<?php echo htmlspecialchars($estadoDisponible); ?>" <?php echo $estadoFiltroReserva === $estadoDisponible ? 'selected' : ''; ?>><?php echo htmlspecialchars($estadoDisponible); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button id="filtrar-reservas" type="submit" class="btn btn-primary">Filtrar</button>
                    </form>
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
                                <?php if ($reservasActivas->num_rows > 0): ?>
                                    <?php while ($reserva = $reservasActivas->fetch_assoc()): ?>
                                        <?php
                                        $estadoReserva = $reserva['estado'];
                                        $fechaInicioReserva = $fechaInicioCol ? substr((string) ($reserva[$fechaInicioCol] ?? ''), 0, 10) : '';
                                        $fechaFinReserva = $fechaFinCol ? substr((string) ($reserva[$fechaFinCol] ?? ''), 0, 10) : '';
                                        $hoy = date('Y-m-d');
                                        if ($estadoReserva !== 'Cancelada' && $fechaInicioReserva !== '' && $fechaFinReserva !== '') {
                                            if ($fechaFinReserva < $hoy) {
                                                $estadoReserva = 'Finalizada';
                                            } elseif ($fechaInicioReserva <= $hoy && $fechaFinReserva >= $hoy) {
                                                $estadoReserva = 'En proceso';
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(($reserva['numero'] ?? 'Habitación eliminada') . (isset($reserva['idCategoria']) ? ' - ' . $reserva['idCategoria'] : '')); ?></td>
                                            <td><?php echo htmlspecialchars($reserva['nombreCliente'] ?? 'Cliente eliminado'); ?></td>
                                            <td><?php echo htmlspecialchars(formatFechaEs($reserva[$fechaInicioCol] ?? '')); ?></td>
                                            <td><?php echo htmlspecialchars(formatFechaEs($reserva[$fechaFinCol] ?? '')); ?></td>
                                            <td><?php echo htmlspecialchars($estadoReserva); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No hay reservas para este estado.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <div class="reservation-pagination">
                            <span>Mostrando <?php echo $totalReservas === 0 ? 0 : $inicioReservas + 1; ?>–<?php echo min($inicioReservas + $reservasActivas->num_rows, $totalReservas); ?> de <?php echo $totalReservas; ?> reservas</span>
                            <nav aria-label="Paginación de reservas">
                                <?php if ($paginaReservas > 1): ?>
                                    <a id="pagina-reservas-anterior" class="btn btn-outline-primary btn-sm" href="?<?php echo htmlspecialchars(http_build_query(['estadoReserva' => $estadoFiltroReserva, 'paginaReservas' => $paginaReservas - 1])); ?>">Anterior</a>
                                <?php endif; ?>
                                <span>Página <?php echo $paginaReservas; ?> de <?php echo $totalPaginasReservas; ?></span>
                                <?php if ($paginaReservas < $totalPaginasReservas): ?>
                                    <a id="pagina-reservas-siguiente" class="btn btn-outline-primary btn-sm" href="?<?php echo htmlspecialchars(http_build_query(['estadoReserva' => $estadoFiltroReserva, 'paginaReservas' => $paginaReservas + 1])); ?>">Siguiente</a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-2">
            <div class="col-lg-4">
                <details id="lista-habitaciones" class="card shadow-sm admin-list">
                    <summary class="card-header">
                        <h5 class="mb-0">Listado de habitaciones</h5>
                    </summary>
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
                                                    <a id="editar-habitacion-<?php echo (int) $habitacion['idHabitacion']; ?>" href="?edit_habitacion=<?php echo $habitacion['idHabitacion']; ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                                    <form method="POST" class="d-inline">
                                                        <input id="accion-eliminar-habitacion-<?php echo (int) $habitacion['idHabitacion']; ?>" type="hidden" name="accion" value="eliminar_habitacion">
                                                        <input id="id-eliminar-habitacion-<?php echo (int) $habitacion['idHabitacion']; ?>" type="hidden" name="id" value="<?php echo (int) $habitacion['idHabitacion']; ?>">
                                                        <button id="deshabilitar-habitacion-<?php echo (int) $habitacion['idHabitacion']; ?>" type="submit" class="btn btn-sm btn-outline-warning">Deshabilitar</button>
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
                </details>
            </div>

            <div class="col-lg-4">
                <details id="lista-habitaciones-inactivas" class="card shadow-sm admin-list">
                    <summary class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Habitaciones inactivas</h5>
                    </summary>
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
                                                    <input id="accion-reactivar-habitacion-<?php echo (int) $habitacion['idHabitacion']; ?>" type="hidden" name="accion" value="reactivar_habitacion">
                                                    <input id="id-reactivar-habitacion-<?php echo (int) $habitacion['idHabitacion']; ?>" type="hidden" name="id" value="<?php echo (int) $habitacion['idHabitacion']; ?>">
                                                    <button id="reactivar-habitacion-<?php echo (int) $habitacion['idHabitacion']; ?>" type="submit" class="btn btn-sm btn-success">Reintegrar</button>
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
                </details>
            </div>

            <div class="col-lg-4">
                <details id="lista-clientes" class="card shadow-sm admin-list">
                    <summary class="card-header">
                        <h5 class="mb-0">Listado de clientes</h5>
                    </summary>
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
                                                    <a id="editar-cliente-<?php echo (int) $cliente['idCliente']; ?>" href="?edit_cliente=<?php echo $cliente['idCliente']; ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                                    <form method="POST" class="d-inline">
                                                        <input id="accion-eliminar-cliente-<?php echo (int) $cliente['idCliente']; ?>" type="hidden" name="accion" value="eliminar_cliente">
                                                        <input id="id-eliminar-cliente-<?php echo (int) $cliente['idCliente']; ?>" type="hidden" name="id" value="<?php echo (int) $cliente['idCliente']; ?>">
                                                        <button id="eliminar-cliente-<?php echo (int) $cliente['idCliente']; ?>" type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
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
                </details>
            </div>

            <div class="col-lg-4">
                <details id="lista-empleados" class="card shadow-sm admin-list">
                    <summary class="card-header">
                        <h5 class="mb-0">Listado de empleados</h5>
                    </summary>
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
                                                    <a id="editar-empleado-<?php echo (int) $empleado['idEmpleado']; ?>" href="?edit_empleado=<?php echo $empleado['idEmpleado']; ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                                    <form method="POST" class="d-inline">
                                                        <input id="accion-eliminar-empleado-<?php echo (int) $empleado['idEmpleado']; ?>" type="hidden" name="accion" value="eliminar_empleado">
                                                        <input id="id-eliminar-empleado-<?php echo (int) $empleado['idEmpleado']; ?>" type="hidden" name="id" value="<?php echo (int) $empleado['idEmpleado']; ?>">
                                                        <button id="eliminar-empleado-<?php echo (int) $empleado['idEmpleado']; ?>" type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
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
                </details>
            </div>
        </div>
    </div>
</body>
</html>