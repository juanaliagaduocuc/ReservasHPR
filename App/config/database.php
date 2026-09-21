<?php

    $host = "localhost";
    $user = "root";
    $port = 3307;
    $password = "";
    $db = "hotel_pacific_reef";

    $conn = new mysqli($host, $user, $password, $db, $port);

    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }

?>