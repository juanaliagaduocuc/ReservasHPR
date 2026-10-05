<?php
session_start();

if (!isset($_SESSION['cliente'], $_SESSION['idCliente'])) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$mensajeReserva = $_SESSION['mensajeReserva'] ?? '';
unset($_SESSION['mensajeReserva']);
$_SESSION['csrfReserva'] = $_SESSION['csrfReserva'] ?? bin2hex(random_bytes(32));
$consultaDisponibilidad = null;
$fechaIngresoConsulta = date('Y-m-d');
$fechaSalidaConsulta = date('Y-m-d', strtotime('+1 day'));
$accionesReserva = ['consultar_disponibilidad', 'reservar_habitacion'];

function validarFechasReserva($fechaIngreso, $fechaSalida)
{
    $ingreso = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaIngreso);
    $salida = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaSalida);

    if (!$ingreso || $ingreso->format('Y-m-d') !== $fechaIngreso || !$salida || $salida->format('Y-m-d') !== $fechaSalida) {
        return 'Selecciona fechas válidas.';
    }
    if ($ingreso < new DateTimeImmutable('today') || $salida <= $ingreso) {
        return 'El ingreso debe ser hoy o una fecha futura y la salida debe ser posterior al ingreso.';
    }

    return '';
}

function consultarDisponibilidad($conn, $idHabitacion, $fechaIngreso, $fechaSalida)
{
    $stmt = $conn->prepare(
        "SELECT h.estado,
                EXISTS(
                    SELECT 1 FROM reserva r
                    WHERE r.idHabitacion = h.idHabitacion
                      AND COALESCE(r.estado, '') <> 'Cancelada'
                      AND r.fechaIngreso <= CURDATE()
                      AND r.fechaSalida >= CURDATE()
                ) AS reservaActual
         FROM habitacion h WHERE h.idHabitacion = ?"
    );
    $stmt->bind_param('i', $idHabitacion);
    $stmt->execute();
    $stmt->bind_result($estado, $reservaActual);
    if (!$stmt->fetch()) {
        $stmt->close();
        return [false, 'La habitación seleccionada no existe.'];
    }
    $stmt->close();

    if (!in_array($estado, ['Disponible', 'Ocupada'], true)) {
        return [false, 'La habitación no está habilitada para reservas.'];
    }
    if ($estado === 'Ocupada' && (int) $reservaActual === 0) {
        return [false, 'La habitación está ocupada y no tiene una fecha de salida registrada.'];
    }

    $stmt = $conn->prepare(
        "SELECT COUNT(*) FROM reserva
         WHERE idHabitacion = ? AND COALESCE(estado, '') <> 'Cancelada'
           AND fechaIngreso <= ? AND fechaSalida >= ?"
    );
    $stmt->bind_param('iss', $idHabitacion, $fechaSalida, $fechaIngreso);
    $stmt->execute();
    $stmt->bind_result($reservasEnConflicto);
    $stmt->fetch();
    $stmt->close();

    return (int) $reservasEnConflicto === 0
        ? [true, 'La habitación está disponible para las fechas seleccionadas.']
        : [false, 'La habitación no está disponible para esas fechas. Debe dejarse un día entre estadías.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['accion'] ?? '', $accionesReserva, true) && (!isset($_POST['csrfReserva']) || !is_string($_POST['csrfReserva']) || !hash_equals($_SESSION['csrfReserva'], $_POST['csrfReserva']))) {
    $mensajeReserva = 'La solicitud expiró. Actualiza la página e inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['accion'] ?? '', $accionesReserva, true)) {
    $accionReserva = $_POST['accion'];
    $transaccionActiva = false;
    $idHabitacionEntrada = $_POST['idHabitacion'] ?? null;
    $idHabitacionConsulta = is_scalar($idHabitacionEntrada) ? filter_var($idHabitacionEntrada, FILTER_VALIDATE_INT) : false;
    $fechaIngresoConsulta = is_string($_POST['fechaIngreso'] ?? null) ? trim($_POST['fechaIngreso']) : '';
    $fechaSalidaConsulta = is_string($_POST['fechaSalida'] ?? null) ? trim($_POST['fechaSalida']) : '';
    $errorFechas = validarFechasReserva($fechaIngresoConsulta, $fechaSalidaConsulta);
    $disponible = false;
    $resultadoConsulta = $errorFechas;

    if ($idHabitacionConsulta && $errorFechas === '') {
        try {
            if ($accionReserva === 'reservar_habitacion') {
                $conn->begin_transaction();
                $transaccionActiva = true;
                $stmt = $conn->prepare("SELECT valorDiario FROM habitacion WHERE idHabitacion = ? AND estado IN ('Disponible', 'Ocupada') FOR UPDATE");
                $stmt->bind_param('i', $idHabitacionConsulta);
                $stmt->execute();
                $stmt->bind_result($valorDiario);
                $habitacionEncontrada = $stmt->fetch();
                $stmt->close();

                if (!$habitacionEncontrada) {
                    throw new RuntimeException('La habitación no está habilitada para reservas.');
                }
            }

            [$disponible, $resultadoConsulta] = consultarDisponibilidad($conn, $idHabitacionConsulta, $fechaIngresoConsulta, $fechaSalidaConsulta);

            if ($accionReserva === 'reservar_habitacion' && $disponible) {
                $cantidadDias = (int) ((strtotime($fechaSalidaConsulta) - strtotime($fechaIngresoConsulta)) / 86400);
                $valorTotal = $valorDiario * $cantidadDias;
                $valorAnticipo = $valorTotal * 0.30;
                $stmt = $conn->prepare(
                    "INSERT INTO reserva (fechaIngreso, fechaSalida, cantidadDias, valorTotal, valorAnticipo, estado, idHabitacion, idCliente)
                     VALUES (?, ?, ?, ?, ?, 'Confirmada', ?, ?)"
                );
                $idCliente = (int) $_SESSION['idCliente'];
                $stmt->bind_param('ssiddii', $fechaIngresoConsulta, $fechaSalidaConsulta, $cantidadDias, $valorTotal, $valorAnticipo, $idHabitacionConsulta, $idCliente);
                $stmt->execute();
                $stmt->close();
                $conn->commit();
                $transaccionActiva = false;
                $_SESSION['mensajeReserva'] = 'Reserva confirmada correctamente.';
                header('Location: cliente.php');
                exit();
            }

            if ($accionReserva === 'reservar_habitacion') {
                $conn->rollback();
            }
        } catch (Throwable $error) {
            if ($transaccionActiva) {
                $conn->rollback();
            }
            $resultadoConsulta = 'No se pudo completar la operación: ' . $error->getMessage();
        }
    } elseif (!$idHabitacionConsulta) {
        $resultadoConsulta = 'Selecciona una habitación válida.';
    }

    $consultaDisponibilidad = [
        'idHabitacion' => $idHabitacionConsulta,
        'disponible' => $disponible,
        'mensaje' => $resultadoConsulta,
    ];
}

$habitaciones = $conn->query(
    "SELECT h.*, c.nombre AS categoria 
     FROM habitacion h
     LEFT JOIN categoria c ON c.idCategoria = h.idCategoria
     WHERE h.estado NOT IN ('Deshabilitada', 'Eliminada')
     ORDER BY h.valorDiario ASC"
);

if (!$habitaciones) {
    die('No se pudieron cargar las habitaciones.');
}

$reservas = $conn->query(
    "SELECT idHabitacion, fechaIngreso, fechaSalida
     FROM reserva
     WHERE COALESCE(estado, '') <> 'Cancelada' AND fechaSalida >= CURDATE()
     ORDER BY fechaIngreso ASC"
);

if (!$reservas) {
    die('No se pudo consultar la disponibilidad de las habitaciones.');
}

$reservasPorHabitacion = [];
while ($reserva = $reservas->fetch_assoc()) {
    $reservasPorHabitacion[$reserva['idHabitacion']][] = [
        'inicio' => $reserva['fechaIngreso'],
        'fin' => $reserva['fechaSalida'],
    ];
}

$imagenes = [
    'Premium' => 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1000&q=80',
    'Turista' => 'https://images.unsplash.com/photo-1494526585095-c41746248156?auto=format&fit=crop&w=1000&q=80',
    'Económica' => 'https://images.unsplash.com/photo-1484154218962-a197022b5858?auto=format&fit=crop&w=1000&q=80',
];

function getRoomType($categoriaNombre)
{
    $categoria = strtolower(trim((string) $categoriaNombre));
    if ($categoria === 'premium') {
        return 'premium';
    }
    return 'economica';
}

function formatPrice($valor)
{
    return '$ ' . number_format((float) $valor, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Pacific Reef</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-sand: #e5e0d9;
            --text: #123c3a;
            --text-warm: #f1efe7;
            --muted: rgba(18, 60, 58, 0.7);
            --green: #0f6a6d;
        }

        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; background: var(--bg-sand); color: var(--text); font-family: 'Inter', sans-serif; }
        .page-shell { max-width: 1600px; margin: 0 auto; background: var(--bg-sand); }
        .topbar {
            background: rgba(246, 244, 240, 0.96);
            border: 2px solid rgba(13, 123, 154, 0.7);
            border-left: none; border-right: none;
            display: flex; align-items: center; justify-content: space-between;
            padding: 1.05rem 2.2rem 0.95rem; position: sticky; top: 0; z-index: 20;
        }
        .brand { display: inline-flex; align-items: center; gap: 0.8rem; font-weight: 600; letter-spacing: 0.06em; font-size: 1.05rem; text-transform: uppercase; color: var(--text); text-decoration: none; font-family: 'Cormorant Garamond', serif; }
        .brand-mark { width: 1.1rem; height: 1.1rem; border: 2px solid rgba(14, 81, 93, 0.9); border-radius: 50%; display: inline-block; position: relative; }
        .brand-mark::after { content: ""; position: absolute; inset: 0.2rem; border-radius: 50%; border: 1px solid rgba(14,81,93,0.9); }
        .top-nav { display: flex; align-items: center; gap: 1.1rem; font-size: 0.84rem; text-transform: uppercase; }
        .top-nav a { color: var(--text); text-decoration: none; padding: 0.5rem 0.9rem; border: 1px solid rgba(14, 81, 93, 0.5); border-radius: 999px; background: transparent; }
        .top-nav .cta { background: rgba(11, 65, 67, 0.06); border-color: rgba(13, 123, 154, 0.85); font-weight: 700; letter-spacing: 0.08em; padding-inline: 1.2rem; }
        .hero { position: relative; min-height: 610px; background: linear-gradient(90deg, rgba(4,21,22,0.76) 0%, rgba(8,31,34,0.56) 35%, rgba(8,31,34,0.36) 100%), url('https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1600&q=80') center/cover no-repeat; color: var(--text-warm); padding: 4.2rem 3.8rem 2.6rem; }
        .hero-inner { max-width: 1220px; margin: 0 auto; }
        .hero-tag { display: inline-block; font-size: 0.73rem; letter-spacing: 0.18em; text-transform: uppercase; color: rgba(247, 242, 234, 0.9); margin-bottom: 1.2rem; font-weight: 600; }
        .hero h1 { margin: 0; max-width: 1100px; font-family: 'Cormorant Garamond', serif; font-size: clamp(3.1rem, 5vw, 6rem); line-height: 0.9; letter-spacing: -0.05em; font-weight: 500; color: #f5efe7; }
        .hero-copy { max-width: 800px; margin-top: 1.5rem; font-size: 1.05rem; line-height: 1.5; color: rgba(246,241,235,0.9); }
        .chip-row { display: flex; flex-wrap: wrap; gap: 0.7rem; margin-top: 2rem; }
        .chip { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.52rem 0.9rem; border-radius: 999px; background: rgba(255,255,255,0.09); border: 1px solid rgba(255,255,255,0.28); font-size: 0.78rem; color: rgba(245,239,231,0.94); }
        .check { width: 0.82rem; height: 0.82rem; border-radius: 50%; border: 1px solid rgba(255,255,255,0.84); display: inline-block; position: relative; }
        .check::after { content: ""; position: absolute; inset: 0.16rem; border-radius: 50%; background: rgba(255,255,255,0.9); }
        .content-wrap { padding: 2.6rem 2.2rem 0; background: var(--bg-sand); }
        .selector { max-width: 1220px; margin: 0 auto; background: rgba(255,255,255,0.2); border: 1px solid rgba(16,78,90,0.25); border-radius: 999px; padding: 0.45rem; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.5rem; box-shadow: 0 6px 18px rgba(18, 60, 58, 0.08); }
        .selector-option { appearance: none; border: none; background: transparent; padding: 1rem 1.2rem; border-radius: 999px; color: var(--text); font-size: 1rem; font-weight: 500; display: flex; align-items: center; justify-content: center; gap: 0.72rem; cursor: pointer; }
        .selector-option .icon { width: 1.45rem; height: 1.45rem; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; border: 1px solid rgba(18,60,58,0.4); font-size: 0.8rem; background: rgba(255,255,255,0.22); }
        .selector-option.active { background: linear-gradient(180deg, rgba(7,72,78,1), rgba(13,95,87,1)); color: #f6f3ee; }
        .listing-head { max-width: 1220px; margin: 2.6rem auto 1.3rem; display: flex; align-items: end; justify-content: space-between; gap: 1rem; }
        .listing-title { margin: 0; font-family: 'Cormorant Garamond', serif; font-size: clamp(2.6rem, 3.2vw, 4rem); line-height: 0.96; letter-spacing: -0.04em; font-weight: 500; color: var(--text); }
        .listing-sub { margin-top: 0.5rem; color: var(--muted); font-size: 0.92rem; }
        .sort { border: 1px solid rgba(18,60,58,0.4); border-radius: 999px; padding: 0.7rem 1rem; background: rgba(255,255,255,0.15); color: var(--muted); font-size: 0.8rem; white-space: nowrap; }
        .type-toolbar { max-width: 1220px; margin: 0 auto 1.2rem; display: flex; align-items: center; gap: 0.8rem; flex-wrap: wrap; color: var(--muted); }
        .pill { border: 1px solid rgba(18,60,58,0.35); border-radius: 999px; padding: 0.45rem 0.8rem; font-size: 0.73rem; background: rgba(255,255,255,0.08); color: var(--text); }
        .room-grid { max-width: 1220px; margin: 0 auto; display: grid; grid-template-columns: repeat(3, minmax(220px, 1fr)); gap: 1.3rem; padding-bottom: 3.5rem; }
        .room-card { background: rgba(255,255,255,0.14); border: 1px solid rgba(18,60,58,0.28); border-radius: 1.1rem; overflow: hidden; box-shadow: 0 8px 22px rgba(15, 58, 60, 0.06); }
        .room-image { height: 235px; background-size: cover; background-position: center; position: relative; }
        .room-card.unavailable .room-image,
        .room-card.reserved .room-image { filter: grayscale(1) brightness(0.75); }
        .room-status { position: absolute; inset: auto 0 0; padding: 0.7rem 1rem; background: rgba(11,45,49,0.82); color: #fff; font-size: 0.82rem; font-weight: 700; text-align: center; }
        .room-card_body { padding: 1rem 1rem 1.1rem; }
        .room-meta { display: flex; align-items: center; justify-content: space-between; gap: 0.8rem; margin-bottom: 0.8rem; }
        .room-price { font-size: 1.12rem; font-weight: 700; color: var(--text); }
        .rating { font-size: 0.82rem; color: var(--text); display: inline-flex; align-items: center; gap: 0.28rem; font-weight: 600; }
        .room-num { font-size: clamp(2rem, 4vw, 2.8rem); font-family: 'Cormorant Garamond', serif; letter-spacing: -0.04em; line-height: 1; color: var(--text); margin: 0; font-weight: 500; }
        .room-little { display: flex; align-items: center; justify-content: space-between; gap: 1rem; border-top: 1px solid rgba(18,60,58,0.22); padding-top: 0.9rem; margin-top: 0.8rem; }
        .price-tag { display: flex; flex-direction: column; gap: 0.18rem; color: var(--text); }
        .price-tag strong { font-size: 1.1rem; font-weight: 700; }
        .price-tag span { font-size: 0.7rem; color: var(--muted); text-transform: lowercase; }
        .reserve-btn { border: 1px solid rgba(11, 90, 97, 0.65); background: rgba(14, 81, 93, 0.04); color: var(--green); border-radius: 999px; padding: 0.7rem 1.1rem; font-size: 0.76rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; cursor: pointer; }
        .room-card.unavailable .reserve-btn { border-color: #777; color: #555; }
        .availability-panel { border-top: 1px solid rgba(18,60,58,0.22); padding: 1rem; }
        .availability-summary { margin-bottom: 0.9rem; font-size: 0.85rem; line-height: 1.5; }
        .booking-search { display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem; margin: 0.8rem 0; }
        .booking-search label { display: block; margin-bottom: 0.25rem; font-size: 0.78rem; }
        .booking-search input[type="date"] { width: 100%; min-height: 2.5rem; border: 1px solid rgba(18,60,58,0.35); border-radius: 0.6rem; padding: 0.4rem; background: rgba(255,255,255,0.75); color: var(--text); }
        .booking-search .reserve-btn { grid-column: 1 / -1; width: 100%; }
        .calendar-controls { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.65rem; }
        .calendar-nav { border: 1px solid rgba(18,60,58,0.35); border-radius: 50%; background: transparent; color: var(--text); width: 2rem; height: 2rem; cursor: pointer; font-size: 1.2rem; }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 0.25rem; text-align: center; font-size: 0.78rem; }
        .calendar-day { min-height: 2rem; display: grid; place-items: center; border-radius: 50%; }
        .calendar-weekday { color: var(--muted); font-size: 0.68rem; font-weight: 700; }
        .calendar-day.booked { background: #777; color: #fff; }
        .calendar-legend { display: flex; align-items: center; gap: 0.45rem; margin-top: 0.65rem; color: var(--muted); font-size: 0.75rem; }
        .calendar-legend span { width: 0.8rem; height: 0.8rem; border-radius: 50%; background: #777; }
        .journey { max-width: 1220px; margin: 0 auto; display: grid; grid-template-columns: 1.1fr 1fr; gap: 1.4rem; padding-bottom: 2rem; }
        .experience { background: linear-gradient(rgba(12, 49, 52, 0.48), rgba(12,49,52,0.48)), url('https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1200&q=80') center/cover no-repeat; color: #f5efe7; border-radius: 1.4rem; min-height: 500px; display: flex; flex-direction: column; justify-content: flex-end; padding: 2rem 2rem 1.4rem; border: 1px solid rgba(12, 50, 54, 0.3); }
        .experience .eyebrow { display: inline-block; width: fit-content; font-size: 0.7rem; letter-spacing: 0.15em; text-transform: uppercase; padding: 0.45rem 0.7rem; border-radius: 999px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.25); }
        .experience h3 { font-family: 'Cormorant Garamond', serif; font-size: clamp(2.6rem, 3vw, 4rem); margin: 1.1rem 0 0.8rem; letter-spacing: -0.04em; line-height: 0.92; }
        .experience p { margin: 0; max-width: 520px; line-height: 1.55; color: rgba(245,239,231,0.88); }
        .experience .cta { margin-top: 1.6rem; width: fit-content; background: transparent; color: #f5efe7; border: 1px solid rgba(245,239,231,0.7); border-radius: 999px; padding: 0.8rem 1.2rem; letter-spacing: 0.08em; text-transform: uppercase; font-size: 0.76rem; font-weight: 700; }
        .journey-card { border-radius: 1.4rem; overflow: hidden; background: rgba(255,255,255,0.15); border: 1px solid rgba(18,60,58,0.2); }
        .journey-card .image { height: 260px; background: url('https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1200&q=80') center/cover no-repeat; }
        .journey-card .content { padding: 1.4rem 1.5rem 1.5rem; background: rgba(255,255,255,0.12); }
        .journey-card h4 { font-family: 'Cormorant Garamond', serif; font-size: clamp(2.2rem, 3vw, 3rem); margin: 0; letter-spacing: -0.04em; color: var(--text); }
        .journey-card p { margin: 0.8rem 0 1.2rem; color: var(--muted); line-height: 1.5; }
        .journey-card .cta { display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; border: 1px solid rgba(12, 78, 88, 0.75); background: rgba(17,94,88,0.05); color: var(--text); padding: 0.7rem 1.2rem; text-transform: uppercase; letter-spacing: 0.08em; font-size: 0.74rem; font-weight: 700; text-decoration: none; }
        .footer-strip { max-width: 1220px; margin: 0 auto; padding: 2rem 0 2.8rem; display: flex; align-items: end; justify-content: space-between; gap: 1rem; border-top: 1px solid rgba(18,60,58,0.18); }
        .footer-title { margin: 0; font-family: 'Cormorant Garamond', serif; font-size: clamp(2.1rem, 3vw, 3.1rem); letter-spacing: -0.04em; font-weight: 500; color: var(--text); }
        .footer-sub { margin-top: 0.45rem; color: var(--muted); font-size: 0.85rem; }
        .footer-links { display: flex; align-items: center; gap: 1.4rem; color: var(--muted); font-size: 0.82rem; }
        .footer-links a { color: var(--muted); text-decoration: none; }
        @media (max-width: 1040px) { .hero { padding-left: 1.4rem; padding-right: 1.4rem; } .content-wrap, .listing-head, .type-toolbar, .room-grid, .journey, .footer-strip { max-width: calc(100% - 1.8rem); } .room-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 760px) { .topbar { flex-wrap: wrap; gap: 0.8rem; padding-inline: 1rem; } .top-nav { width: 100%; justify-content: flex-end; flex-wrap: wrap; } .hero { min-height: 500px; padding-top: 3rem; } .listing-head, .footer-strip { flex-direction: column; align-items: flex-start; } .room-grid, .journey { grid-template-columns: 1fr; } .selector { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="page-shell">
        <header class="topbar">
            <a href="#" class="brand" aria-label="Hotel Pacific Reef">
                <span class="brand-mark" aria-hidden="true"></span>
                <span>Hotel Pacific Reef</span>
            </a>
            <nav class="top-nav" aria-label="Navegación principal">
                <a href="habitaciones.php">Detalles habitaciones</a>
                <a href="#">Destinos</a>
                <a href="#">Hoteles</a>
                <a href="#">Inspiración</a>
                <a href="mis_reservas.php" class="cta">Mis reservas</a>
            </nav>
        </header>

        <main>
            <section class="hero">
                <div class="hero-inner">
                    <span class="hero-tag">Colección premium · 38 hoteles</span>
                    <h1>Estancias que merecen ser recordadas.</h1>
                    <p class="hero-copy">Arquitectura con historia, paisajes extraordinarios y un servicio que anticipa cada detalle.</p>
                    <div class="chip-row">
                        <span class="chip"><span class="check"></span> Mejora de habitación</span>
                        <span class="chip"><span class="check"></span> Desayuno incluido</span>
                        <span class="chip"><span class="check"></span> Atención prioritaria</span>
                    </div>
                </div>
            </section>

            <div class="content-wrap">
                <?php if ($mensajeReserva !== ''): ?>
                    <div class="type-toolbar" role="status"><span class="pill"><?php echo htmlspecialchars($mensajeReserva); ?></span></div>
                <?php endif; ?>
                <?php if ($consultaDisponibilidad !== null): ?>
                    <div class="type-toolbar" role="status"><span class="pill"><?php echo htmlspecialchars($consultaDisponibilidad['mensaje']); ?></span></div>
                <?php endif; ?>
                <div class="selector" aria-label="Filtra habitaciones">
                    <button class="selector-option active" type="button" data-filter="all">
                        <span class="icon">◇</span>
                        <span>Todas</span>
                    </button>
                    <button class="selector-option" type="button" data-filter="reserved">
                        <span class="icon">●</span>
                        <span>Reservadas</span>
                    </button>
                    <button class="selector-option" type="button" data-filter="premium">
                        <span class="icon">◌</span>
                        <span>Premium</span>
                    </button>
                    <button class="selector-option" type="button" data-filter="economica">
                        <span class="icon">⌂</span>
                        <span>Económica</span>
                    </button>
                </div>

                <div class="listing-head">
                    <div>
                        <h2 class="listing-title">Todas las habitaciones</h2>
                        <div class="listing-sub" id="room-count"><?php echo $habitaciones->num_rows; ?> habitaciones</div>
                    </div>
                    <div class="sort">Ordenar: Recomendados ▾</div>
                </div>

                <div class="type-toolbar" aria-label="Filtros">
                    <span class="pill">Fechas no disponibles marcadas en el calendario</span>
                </div>

                <div class="room-grid" id="room-list">
                    <?php if ($habitaciones && $habitaciones->num_rows > 0): ?>
                        <?php while ($habitacion = $habitaciones->fetch_assoc()): ?>
                            <?php $tipo = getRoomType($habitacion['categoria'] ?? 'Turista'); ?>
                            <?php $precio = (float) ($habitacion['valorDiario'] ?? 0); ?>
                            <?php
                            $estado = $habitacion['estado'] ?? '';
                            $fechasOcupadas = $reservasPorHabitacion[$habitacion['idHabitacion']] ?? [];
                            $hoy = date('Y-m-d');
                            $reservaActual = false;
                            $proximaReserva = null;
                            $disponibleDesde = null;
                            foreach ($fechasOcupadas as $fechaOcupada) {
                                if ($fechaOcupada['inicio'] <= $hoy && $fechaOcupada['fin'] >= $hoy) {
                                    $reservaActual = true;
                                    $disponibleDesde = date('Y-m-d', strtotime($fechaOcupada['fin'] . ' +1 day'));
                                } elseif ($fechaOcupada['inicio'] > $hoy && $proximaReserva === null) {
                                    $proximaReserva = $fechaOcupada;
                                }
                            }
                            if ($disponibleDesde === null && $proximaReserva !== null) {
                                $disponibleDesde = date('Y-m-d', strtotime($proximaReserva['fin'] . ' +1 day'));
                            }
                            if ($disponibleDesde !== null) {
                                do {
                                    $fechaDisponibleAnterior = $disponibleDesde;
                                    foreach ($fechasOcupadas as $fechaOcupada) {
                                        if ($fechaOcupada['inicio'] <= $disponibleDesde && $fechaOcupada['fin'] >= $disponibleDesde) {
                                            $disponibleDesde = date('Y-m-d', strtotime($fechaOcupada['fin'] . ' +1 day'));
                                        }
                                    }
                                } while ($disponibleDesde !== $fechaDisponibleAnterior);
                            }
                            $bloqueoFijo = $estado !== 'Disponible' && !($estado === 'Ocupada' && $reservaActual);
                            $noDisponible = $estado !== 'Disponible' || $reservaActual;
                            $disponibilidad = htmlspecialchars(
                                json_encode([
                                    'reservas' => $fechasOcupadas,
                                    'bloqueoFijo' => $bloqueoFijo,
                                    'reservadaAhora' => $reservaActual,
                                    'proximaReserva' => $proximaReserva,
                                    'disponibleDesde' => $disponibleDesde,
                                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP),
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>
                            <?php $esResultadoConsulta = $consultaDisponibilidad && (int) $consultaDisponibilidad['idHabitacion'] === (int) $habitacion['idHabitacion']; ?>
                            <article id="habitacion-<?php echo (int) $habitacion['idHabitacion']; ?>" class="room-card<?php echo $noDisponible ? ' unavailable' : ''; ?><?php echo ($reservaActual || $proximaReserva !== null) ? ' reserved' : ''; ?>" data-result="<?php echo $esResultadoConsulta ? 'true' : 'false'; ?>" data-type="<?php echo htmlspecialchars($tipo); ?>">
                                <div class="room-image" style="background-image: url('<?php echo htmlspecialchars($imagenes[$habitacion['categoria']] ?? $imagenes['Turista']); ?>');">
                                    <?php if ($reservaActual): ?>
                                        <span class="room-status">Reservada · disponible nuevamente desde <?php echo htmlspecialchars(date('d-m-Y', strtotime($disponibleDesde))); ?></span>
                                    <?php elseif ($proximaReserva !== null): ?>
                                        <span class="room-status">Reservada del <?php echo htmlspecialchars(date('d-m-Y', strtotime($proximaReserva['inicio']))); ?> al <?php echo htmlspecialchars(date('d-m-Y', strtotime($proximaReserva['fin']))); ?> · disponible nuevamente desde <?php echo htmlspecialchars(date('d-m-Y', strtotime($disponibleDesde))); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="room-card_body">
                                    <div class="room-meta">
                                        <div class="room-price"><?php echo htmlspecialchars(formatPrice($precio)); ?></div>
                                        <div class="rating">★ 4,5</div>
                                    </div>
                                    <div class="room-num"><?php echo htmlspecialchars($habitacion['numero']); ?></div>
                                    <div class="room-little">
                                        <div class="price-tag">
                                            <strong><?php echo htmlspecialchars(formatPrice($precio)); ?></strong>
                                            <span>por noche · tasas incluidas</span>
                                        </div>
                                        <button class="reserve-btn availability-toggle" type="button" aria-expanded="false" data-availability="<?php echo $disponibilidad; ?>">Consultar disponibilidad</button>
                                    </div>
                                </div>
                                <section class="availability-panel" <?php echo $esResultadoConsulta ? '' : 'hidden'; ?> aria-label="Calendario de disponibilidad de habitación <?php echo htmlspecialchars($habitacion['numero']); ?>">
                                    <div class="availability-summary"><?php echo $esResultadoConsulta ? htmlspecialchars($consultaDisponibilidad['mensaje']) : ''; ?></div>
                                    <form method="POST" class="booking-search">
                                        <input type="hidden" name="csrfReserva" value="<?php echo htmlspecialchars($_SESSION['csrfReserva']); ?>">
                                        <input type="hidden" name="idHabitacion" value="<?php echo (int) $habitacion['idHabitacion']; ?>">
                                        <div class="mb-2">
                                            <label for="ingreso-<?php echo (int) $habitacion['idHabitacion']; ?>">Ingreso</label>
                                            <input id="ingreso-<?php echo (int) $habitacion['idHabitacion']; ?>" type="date" name="fechaIngreso" min="<?php echo date('Y-m-d'); ?>" value="<?php echo $consultaDisponibilidad && (int) $consultaDisponibilidad['idHabitacion'] === (int) $habitacion['idHabitacion'] ? htmlspecialchars($fechaIngresoConsulta) : date('Y-m-d'); ?>" required>
                                        </div>
                                        <div class="mb-2">
                                            <label for="salida-<?php echo (int) $habitacion['idHabitacion']; ?>">Salida</label>
                                            <input id="salida-<?php echo (int) $habitacion['idHabitacion']; ?>" type="date" name="fechaSalida" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" value="<?php echo $consultaDisponibilidad && (int) $consultaDisponibilidad['idHabitacion'] === (int) $habitacion['idHabitacion'] ? htmlspecialchars($fechaSalidaConsulta) : date('Y-m-d', strtotime('+1 day')); ?>" required>
                                        </div>
                                        <button class="reserve-btn" type="submit" name="accion" value="consultar_disponibilidad">Consultar disponibilidad</button>
                                    </form>
                                    <?php if ($esResultadoConsulta && $consultaDisponibilidad['disponible']): ?>
                                        <form method="POST" class="booking-search">
                                            <input type="hidden" name="csrfReserva" value="<?php echo htmlspecialchars($_SESSION['csrfReserva']); ?>">
                                            <input type="hidden" name="accion" value="reservar_habitacion">
                                            <input type="hidden" name="idHabitacion" value="<?php echo (int) $habitacion['idHabitacion']; ?>">
                                            <input type="hidden" name="fechaIngreso" value="<?php echo htmlspecialchars($fechaIngresoConsulta); ?>">
                                            <input type="hidden" name="fechaSalida" value="<?php echo htmlspecialchars($fechaSalidaConsulta); ?>">
                                            <button class="reserve-btn" type="submit">Confirmar reserva</button>
                                        </form>
                                    <?php endif; ?>
                                    <div class="calendar-controls">
                                        <button type="button" class="calendar-nav" data-direction="-1" aria-label="Mes anterior">‹</button>
                                        <strong class="calendar-month"></strong>
                                        <button type="button" class="calendar-nav" data-direction="1" aria-label="Mes siguiente">›</button>
                                    </div>
                                    <div class="calendar-grid" role="grid"></div>
                                    <div class="calendar-legend"><span></span> No disponible</div>
                                </section>
                            </article>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No hay habitaciones disponibles en este momento.</p>
                    <?php endif; ?>
                </div>

                <section class="journey" aria-label="Cómo quieres vivir tu próxima estancia">
                    <div class="experience">
                        <span class="eyebrow">Colección signature</span>
                        <h3>Premium</h3>
                        <p>Hoteles con alma, diseño excepcional y atenciones que transforman cada noche.</p>
                        <button class="cta" type="button">Explorar premium</button>
                    </div>
                    <div class="journey-card">
                        <div class="image" aria-label="Habitación económica"></div>
                        <div class="content">
                            <h4>Económica</h4>
                            <p>Estancias inteligentes, actuales y bien ubicadas para aprovechar más cada viaje.</p>
                            <a class="cta" href="#">Explorar económica</a>
                        </div>
                    </div>
                </section>

                <footer class="footer-strip">
                    <div>
                        <h3 class="footer-title">Viajar bien empieza eligiéndonos.</h3>
                        <div class="footer-sub">Precios transparentes, asistencia local y reserva segura en cada estancia.</div>
                    </div>
                    <div class="footer-links">
                        <a href="#">Ayuda 24/7</a>
                        <a href="#">Condiciones</a>
                        <a href="#">Privacidad</a>
                    </div>
                </footer>
            </div>
        </main>
    </div>

    <script>
        const selectorButtons = document.querySelectorAll('.selector-option');
        const cards = document.querySelectorAll('.room-card');
        const resultCard = document.querySelector('.room-card[data-result="true"]');
        if (resultCard) {
            resultCard.scrollIntoView({ block: 'center' });
        }

        document.querySelectorAll('.booking-search').forEach(form => {
            const arrival = form.querySelector('[name="fechaIngreso"]');
            const departure = form.querySelector('[name="fechaSalida"]');
            if (!arrival || !departure) return;
            arrival.addEventListener('change', () => {
                const nextDay = new Date(`${arrival.value}T00:00:00`);
                nextDay.setDate(nextDay.getDate() + 1);
                const minDeparture = `${nextDay.getFullYear()}-${String(nextDay.getMonth() + 1).padStart(2, '0')}-${String(nextDay.getDate()).padStart(2, '0')}`;
                departure.min = minDeparture;
                if (departure.value < minDeparture) departure.value = minDeparture;
            });
        });

        selectorButtons.forEach(button => {
            button.addEventListener('click', () => {
                selectorButtons.forEach(btn => btn.classList.toggle('active', btn === button));
                const filter = button.dataset.filter;
                const title = document.querySelector('.listing-title');
                const titles = {
                    all: 'Todas las habitaciones',
                    reserved: 'Habitaciones reservadas',
                    premium: 'Nuestra selección Premium',
                    economica: 'Nuestra selección Económica',
                };
                title.textContent = titles[filter];
                let visibleCount = 0;

                cards.forEach(card => {
                    const match = filter === 'all'
                        || (filter === 'reserved' && card.classList.contains('reserved'))
                        || card.dataset.type === filter;
                    card.style.display = match ? 'block' : 'none';
                    if (match) visibleCount++;
                });
                document.querySelector('#room-count').textContent = `${visibleCount} habitaciones`;
            });
        });

        const weekdayNames = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa', 'Do'];
        const monthFormatter = new Intl.DateTimeFormat('es-CL', { month: 'long', year: 'numeric' });
        const dateKey = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

        document.querySelectorAll('.availability-toggle').forEach(button => {
            const card = button.closest('.room-card');
            const panel = card.querySelector('.availability-panel');
            const data = JSON.parse(button.dataset.availability);
            let month = new Date();
            month.setDate(1);

            const renderCalendar = () => {
                const grid = panel.querySelector('.calendar-grid');
                const year = month.getFullYear();
                const monthIndex = month.getMonth();
                const firstDay = (new Date(year, monthIndex, 1).getDay() + 6) % 7;
                const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();
                panel.querySelector('.calendar-month').textContent = monthFormatter.format(month);
                grid.replaceChildren();

                weekdayNames.forEach(name => {
                    const weekday = document.createElement('span');
                    weekday.className = 'calendar-day calendar-weekday';
                    weekday.textContent = name;
                    grid.append(weekday);
                });

                for (let i = 0; i < firstDay; i++) {
                    grid.append(document.createElement('span'));
                }

                for (let day = 1; day <= daysInMonth; day++) {
                    const date = new Date(year, monthIndex, day);
                    const key = dateKey(date);
                    const unavailable = data.bloqueoFijo || data.reservas.some(range => range.inicio <= key && key <= range.fin);
                    const cell = document.createElement('span');
                    cell.className = `calendar-day${unavailable ? ' booked' : ''}`;
                    cell.textContent = day;
                    cell.setAttribute('role', 'gridcell');
                    if (unavailable) {
                        cell.setAttribute('aria-label', `${day} no disponible`);
                    }
                    grid.append(cell);
                }
            };

            button.addEventListener('click', () => {
                const isOpen = button.getAttribute('aria-expanded') === 'true';
                button.setAttribute('aria-expanded', String(!isOpen));
                panel.hidden = isOpen;
                if (!isOpen) {
                    const summary = panel.querySelector('.availability-summary');
                    if (data.reservadaAhora && data.disponibleDesde) {
                        const formattedDate = new Intl.DateTimeFormat('es-CL', { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(`${data.disponibleDesde}T00:00:00Z`));
                        summary.textContent = `Habitación reservada. Disponible nuevamente desde el ${formattedDate}.`;
                    } else if (data.proximaReserva) {
                        const fechaInicio = new Intl.DateTimeFormat('es-CL', { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(`${data.proximaReserva.inicio}T00:00:00Z`));
                        const fechaFin = new Intl.DateTimeFormat('es-CL', { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(`${data.proximaReserva.fin}T00:00:00Z`));
                        const fechaDisponible = new Intl.DateTimeFormat('es-CL', { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(`${data.disponibleDesde}T00:00:00Z`));
                        summary.textContent = `Próxima reserva del ${fechaInicio} al ${fechaFin}. Disponible nuevamente desde el ${fechaDisponible}.`;
                    } else if (data.bloqueoFijo) {
                        summary.textContent = 'No disponible actualmente. No hay una fecha de disponibilidad registrada.';
                    } else if (data.disponibleDesde) {
                        const formattedDate = new Intl.DateTimeFormat('es-CL', { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(`${data.disponibleDesde}T00:00:00Z`));
                        summary.textContent = `No disponible actualmente. Disponible nuevamente desde el ${formattedDate}.`;
                    } else {
                        summary.textContent = 'Consulta los días ocupados en el calendario.';
                    }
                    renderCalendar();
                }
            });

            panel.querySelectorAll('.calendar-nav').forEach(nav => {
                nav.addEventListener('click', () => {
                    month.setMonth(month.getMonth() + Number(nav.dataset.direction));
                    renderCalendar();
                });
            });
        });
    </script>
</body>
</html>
