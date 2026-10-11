<?php
session_start();

if (!isset($_SESSION['idCliente'], $_SESSION['cliente'])) {
    header('Location: ../index.php');
    exit();
}

$dias = filter_var($_POST['dias'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
if (!$dias) {
    http_response_code(400);
    exit('Cantidad de noches inválida.');
}

$valorDiario = 50000;

$total = $valorDiario * $dias;

$anticipo = $total * 0.30;

echo "Total: ".$total;
echo "<br>";
echo "Anticipo: ".$anticipo;

?>