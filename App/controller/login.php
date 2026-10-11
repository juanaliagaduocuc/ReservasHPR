<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150 || $password === '') {
    header('Location: ../index.php?estado=credenciales');
    exit();
}

$empleadoStmt = $conn->prepare('SELECT idEmpleado, nombre, idRol, password FROM empleado WHERE email = ? LIMIT 1');
$empleadoStmt->bind_param('s', $email);
$empleadoStmt->execute();
$empleado = $empleadoStmt->get_result()->fetch_assoc();
$empleadoStmt->close();

$stmt = $empleado
    ? null
    : $conn->prepare('SELECT idCliente, nombre, password FROM cliente WHERE email = ? AND estado = 1 LIMIT 1');

if (!$empleado) {
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $cliente = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    $cliente = null;
}

$cuenta = $empleado ?? $cliente;
$idCuenta = $empleado ? (int) $empleado['idEmpleado'] : (int) ($cliente['idCliente'] ?? 0);
$claveGuardada = $cuenta['password'] ?? '';
$claveValida = $claveGuardada !== ''
    && (password_verify($password, $claveGuardada) || hash_equals($claveGuardada, $password));

if (!$cuenta || !$claveValida) {
    header('Location: ../index.php?estado=credenciales');
    exit();
}

if (!$empleado && password_needs_rehash($claveGuardada, PASSWORD_DEFAULT)) {
    $claveHash = password_hash($password, PASSWORD_DEFAULT);
    $actualizarClave = $conn->prepare('UPDATE cliente SET password = ? WHERE idCliente = ?');
    $actualizarClave->bind_param('si', $claveHash, $idCuenta);
    if (!$actualizarClave->execute()) {
        error_log('No se pudo actualizar el hash de contraseña durante el inicio de sesión.');
    }
    $actualizarClave->close();
}

session_regenerate_id(true);
unset($_SESSION['cliente'], $_SESSION['idCliente'], $_SESSION['usuario'], $_SESSION['rol']);
if ($empleado) {
    $_SESSION['usuario'] = $empleado['nombre'];
    $_SESSION['rol'] = $empleado['idRol'];
    header('Location: ../views/' . ($empleado['idRol'] == 1 ? 'admin.php' : 'empleado.php'));
} else {
    $_SESSION['cliente'] = $cliente['nombre'];
    $_SESSION['idCliente'] = $cliente['idCliente'];
    header('Location: ../views/cliente.php');
}
exit();
