<?php
session_start();

if (!isset($_SESSION['cliente'], $_SESSION['idCliente'])) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$_SESSION['csrfCancelacion'] = $_SESSION['csrfCancelacion'] ?? bin2hex(random_bytes(32));
$mensajeCancelacion = $_SESSION['mensajeCancelacion'] ?? '';
unset($_SESSION['mensajeCancelacion']);
$idCliente = (int) $_SESSION['idCliente'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cancelar_reserva') {
    $token = $_POST['csrfCancelacion'] ?? '';
    $idReserva = filter_var($_POST['idReserva'] ?? null, FILTER_VALIDATE_INT);

    if (!is_string($token) || !hash_equals($_SESSION['csrfCancelacion'], $token)) {
        $_SESSION['mensajeCancelacion'] = 'La solicitud expiró. Actualiza la página e inténtalo de nuevo.';
    } elseif (!$idReserva) {
        $_SESSION['mensajeCancelacion'] = 'No se pudo identificar la reserva.';
    } else {
        $cancelar = $conn->prepare(
            "UPDATE reserva SET estado = 'Cancelada'
             WHERE idReserva = ? AND idCliente = ? AND estado <> 'Cancelada'
               AND fechaIngreso > DATE_ADD(CURDATE(), INTERVAL 1 DAY)"
        );

        if (!$cancelar) {
            die('No se pudo preparar la cancelación: ' . $conn->error);
        }

        $cancelar->bind_param('ii', $idReserva, $idCliente);
        if (!$cancelar->execute()) {
            die('No se pudo cancelar la reserva: ' . $cancelar->error);
        }

        if ($cancelar->affected_rows === 1) {
            $_SESSION['mensajeCancelacion'] = 'La reserva fue cancelada correctamente.';
        } else {
            $_SESSION['mensajeCancelacion'] = 'No se pudo cancelar: la reserva no existe, ya fue cancelada o el ingreso es mañana o ya pasó.';
        }
        $cancelar->close();
    }

    header('Location: mis_reservas.php');
    exit();
}

$stmt = $conn->prepare(
    "SELECT r.idReserva, r.fechaIngreso, r.fechaSalida, r.cantidadDias,
            r.valorTotal, r.valorAnticipo, r.estado, h.numero, c.nombre AS categoria
     FROM reserva r
     INNER JOIN habitacion h ON h.idHabitacion = r.idHabitacion
     LEFT JOIN categoria c ON c.idCategoria = h.idCategoria
     WHERE r.idCliente = ?
     ORDER BY r.fechaIngreso DESC, r.idReserva DESC"
);

if (!$stmt) {
    die('No se pudieron consultar tus reservas: ' . $conn->error);
}

$stmt->bind_param('i', $idCliente);

if (!$stmt->execute()) {
    die('No se pudieron consultar tus reservas: ' . $stmt->error);
}

$reservas = $stmt->get_result();

function estadoReservaCliente($estado, $ingreso, $salida)
{
    $hoy = date('Y-m-d');
    if ($estado === 'Cancelada') {
        return 'Cancelada';
    }
    if ($salida < $hoy) {
        return 'Finalizada';
    }
    if ($ingreso <= $hoy && $salida >= $hoy) {
        return 'En proceso';
    }
    return $estado;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis reservas | Hotel Pacific Reef</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-reservas">
    <header class="topbar">
        <a href="cliente.php" class="brand">Hotel Pacific Reef</a>
        <a href="habitaciones.php" class="back">Volver a habitaciones</a>
    </header>
    <main>
        <h1>Mis reservas</h1>
        <p class="intro">Consulta tus estadías, fechas e importes.</p>
        <?php if ($mensajeCancelacion !== ''): ?>
            <div class="notice" role="status"><?php echo htmlspecialchars($mensajeCancelacion); ?></div>
        <?php endif; ?>
        <div class="table-wrap">
            <?php if ($reservas->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Habitación</th>
                            <th>Categoría</th>
                            <th>Ingreso</th>
                            <th>Salida</th>
                            <th>Noches</th>
                            <th>Total</th>
                            <th>Anticipo</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $numeroFila = 0; ?>
                        <?php while ($reserva = $reservas->fetch_assoc()): ?>
                            <?php
                            $numeroFila++;
                            $estadoReserva = estadoReservaCliente($reserva['estado'], $reserva['fechaIngreso'], $reserva['fechaSalida']);
                            $puedeCancelar = $reserva['estado'] !== 'Cancelada'
                                && $reserva['fechaIngreso'] > date('Y-m-d', strtotime('+1 day'));
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($reserva['numero']); ?></td>
                                <td><?php echo htmlspecialchars($reserva['categoria'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars(date('d-m-Y', strtotime($reserva['fechaIngreso']))); ?></td>
                                <td><?php echo htmlspecialchars(date('d-m-Y', strtotime($reserva['fechaSalida']))); ?></td>
                                <td><?php echo (int) $reserva['cantidadDias']; ?></td>
                                <td>$ <?php echo number_format((float) $reserva['valorTotal'], 0, ',', '.'); ?></td>
                                <td>$ <?php echo number_format((float) $reserva['valorAnticipo'], 0, ',', '.'); ?></td>
                                <td id="estado-<?php echo $numeroFila; ?>"><span class="status"><?php echo htmlspecialchars($estadoReserva); ?></span></td>
                                <td>
                                    <?php if ($puedeCancelar): ?>
                                        <form class="cancel-form" method="POST" onsubmit="return confirm('¿Cancelar esta reserva?');">
                                            <input type="hidden" name="accion" value="cancelar_reserva">
                                            <input type="hidden" name="idReserva" value="<?php echo (int) $reserva['idReserva']; ?>">
                                            <input type="hidden" name="csrfCancelacion" value="<?php echo htmlspecialchars($_SESSION['csrfCancelacion']); ?>">
                                            <button class="cancel-button" type="submit">Cancelar</button>
                                        </form>
                                    <?php else: ?>
                                        <span>—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty">Todavía no tienes reservas. <a href="cliente.php">Consulta la disponibilidad de las habitaciones.</a></div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
<?php $stmt->close(); ?>
