<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit();
}

$token = $_POST['csrfRegistro'] ?? '';
if (!is_string($token) || !isset($_SESSION['csrfRegistro']) || !hash_equals($_SESSION['csrfRegistro'], $token)) {
    header('Location: ../index.php?estado=datos');
    exit();
}

require_once '../config/database.php';

$nombre = is_string($_POST['nombre'] ?? null) ? trim($_POST['nombre']) : '';
$apellido = is_string($_POST['apellido'] ?? null) ? trim($_POST['apellido']) : '';
$email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
$runEntrada = is_string($_POST['run'] ?? null) ? strtoupper(str_replace('.', '', trim($_POST['run']))) : '';
$telefonoEntrada = is_string($_POST['telefono'] ?? null) ? trim($_POST['telefono']) : '';
$direccionEntrada = is_string($_POST['direccion'] ?? null) ? trim($_POST['direccion']) : '';
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
$confirmarPassword = is_string($_POST['confirmar_password'] ?? null) ? $_POST['confirmar_password'] : '';

$run = $runEntrada === '' ? null : $runEntrada;
$telefono = $telefonoEntrada === '' ? null : $telefonoEntrada;
$direccion = $direccionEntrada === '' ? null : $direccionEntrada;
$runValido = $run === null;

if ($run !== null && preg_match('/^(\d{7,8})-([\dK])$/', $run, $partes)) {
    $digitos = strrev($partes[1]);
    $suma = 0;
    for ($i = 0; $i < strlen($digitos); $i++) {
        $suma += (int) $digitos[$i] * (($i % 6) + 2);
    }
    $resto = 11 - ($suma % 11);
    $digitoVerificador = $resto === 11 ? '0' : ($resto === 10 ? 'K' : (string) $resto);
    $runValido = hash_equals($digitoVerificador, $partes[2]);
}

$nombreValido = $nombre !== '' && strlen($nombre) <= 100 && !preg_match('/[\x00-\x1F\x7F]/', $nombre);
$apellidoValido = $apellido !== '' && strlen($apellido) <= 100 && !preg_match('/[\x00-\x1F\x7F]/', $apellido);
$telefonoValido = $telefono === null || (strlen($telefono) <= 20 && preg_match('/^[0-9+() .-]{7,20}$/', $telefono));
$direccionValida = $direccion === null || (strlen($direccion) <= 255 && !preg_match('/[\x00-\x1F\x7F]/', $direccion));

if (
    !$nombreValido || !$apellidoValido
    || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150
    || !$runValido || !$telefonoValido || !$direccionValida
    || strlen($password) < 8 || strlen($password) > 72
    || !hash_equals($password, $confirmarPassword)
) {
    header('Location: ../index.php?estado=datos');
    exit();
}

$emailStmt = $conn->prepare(
    'SELECT 1 FROM cliente WHERE email = ?
     UNION SELECT 1 FROM empleado WHERE email = ? LIMIT 1'
);
$emailStmt->bind_param('ss', $email, $email);
$emailStmt->execute();
$emailOcupado = $emailStmt->get_result()->num_rows > 0;
$emailStmt->close();

if ($emailOcupado) {
    header('Location: ../index.php?estado=email-ocupado');
    exit();
}

if ($run !== null) {
    $runStmt = $conn->prepare(
        'SELECT 1 FROM cliente WHERE run = ?
         UNION SELECT 1 FROM empleado WHERE run = ? LIMIT 1'
    );
    $runStmt->bind_param('ss', $run, $run);
    $runStmt->execute();
    $runOcupado = $runStmt->get_result()->num_rows > 0;
    $runStmt->close();

    if ($runOcupado) {
        header('Location: ../index.php?estado=run-ocupado');
        exit();
    }
}

$claveHash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare(
    'INSERT INTO cliente (nombre, apellido, run, email, telefono, direccion, password, estado, idRol)
     VALUES (?, ?, ?, ?, ?, ?, ?, 1, 3)'
);
$stmt->bind_param('sssssss', $nombre, $apellido, $run, $email, $telefono, $direccion, $claveHash);

if (!$stmt->execute()) {
    if ($stmt->errno === 1062) {
        header('Location: ../index.php?estado=email-ocupado');
    } else {
        error_log('No se pudo registrar un cliente: ' . $stmt->error);
        header('Location: ../index.php?estado=error-registro');
    }
    $stmt->close();
    exit();
}

$stmt->close();
unset($_SESSION['csrfRegistro']);
header('Location: ../index.php?estado=registro');
exit();
