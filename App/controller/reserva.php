<?php

$valorDiario = 50000;

$dias = $_POST["dias"];

$total = $valorDiario * $dias;

$anticipo = $total * 0.30;

echo "Total: ".$total;
echo "<br>";
echo "Anticipo: ".$anticipo;

?>