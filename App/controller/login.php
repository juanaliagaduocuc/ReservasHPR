<?php

session_start();

include("../config/database.php");

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

$sql = "SELECT * FROM empleado WHERE email='$email' AND password='$password'";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();

    $_SESSION["usuario"] = $user["nombre"];
    $_SESSION["rol"] = $user["idRol"];

    if ($user["idRol"] == 1) {
        header("Location: ../views/admin.php");
        exit();
    }

    if ($user["idRol"] == 2) {
        header("Location: ../views/empleado.php");
        exit();
    }
}

$sqlCliente = "SELECT * FROM cliente WHERE email='$email' AND password='$password'";
$resultadoCliente = $conn->query($sqlCliente);

if ($resultadoCliente && $resultadoCliente->num_rows > 0) {
    $cliente = $resultadoCliente->fetch_assoc();

    $_SESSION["cliente"] = $cliente["nombre"];
    $_SESSION["idCliente"] = $cliente["idCliente"];

    header("Location: ../views/cliente.php");
    exit();
}

echo "Credenciales inválidas";

?>