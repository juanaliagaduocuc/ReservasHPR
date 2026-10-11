<?php
session_start();

if (isset($_SESSION['idCliente'])) {
    header('Location: views/cliente.php');
    exit();
}
if (isset($_SESSION['usuario'], $_SESSION['rol'])) {
    header('Location: ' . ($_SESSION['rol'] == 1 ? 'views/admin.php' : 'views/empleado.php'));
    exit();
}

$_SESSION['csrfRegistro'] = $_SESSION['csrfRegistro'] ?? bin2hex(random_bytes(32));
$mensajes = [
    'credenciales' => 'Correo o contraseña incorrectos.',
    'registro' => 'Tu cuenta se creó correctamente. Ya puedes iniciar sesión.',
    'email-ocupado' => 'Ese correo ya está registrado.',
    'run-ocupado' => 'Ese RUN ya está registrado.',
    'datos' => 'Revisa los datos ingresados e inténtalo nuevamente.',
    'error-registro' => 'No fue posible crear la cuenta. Inténtalo nuevamente.',
];
$estado = is_string($_GET['estado'] ?? null) ? $_GET['estado'] : '';
$mensaje = $mensajes[$estado] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#123c3a">
    <title>Hotel Pacific Reef | Bienvenido</title>
    <link rel="stylesheet" href="views/style.css?v=<?php echo filemtime(__DIR__ . '/views/style.css'); ?>">
</head>
<body class="page-login">
    <header class="login-topbar">
        <a class="login-brand" href="index.php">
            <span class="login-brand-mark" aria-hidden="true"></span>
            Hotel Pacific Reef
        </a>
        <span class="login-topbar-note">Tu refugio junto al Pacífico</span>
    </header>
    <main class="login-main">
        <section class="login-hero">
            <div class="login-hero-content">
                <span class="login-eyebrow">Hospitalidad frente al mar</span>
                <h1>Una estadía<br>para recordar.</h1>
                <p>Descubre un espacio pensado para descansar, disfrutar y sentirte como en casa junto al Pacífico.</p>
                <div class="login-highlights">
                    <span>Atención cercana</span>
                    <span>Descanso premium</span>
                    <span>Reservas simples</span>
                </div>
            </div>
        </section>

        <section class="login-panels" aria-label="Acceso al hotel">
            <?php if ($mensaje !== ''): ?>
                <p class="login-notice" role="status"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <section class="auth-card">
                <span class="auth-eyebrow">Bienvenido de nuevo</span>
                <h2>Iniciar sesión</h2>
                <p class="auth-copy">Ingresa para consultar y gestionar tus reservas.</p>
                <form action="controller/login.php" method="POST">
                    <label for="email">Correo electrónico</label>
                    <input type="email" id="email" name="email" maxlength="150" autocomplete="username" required>

                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" autocomplete="current-password" required>

                    <button type="submit" id="lgnBtn" class="auth-button">Iniciar sesión</button>
                </form>
            </section>

            <section class="auth-card auth-register">
                <span class="auth-eyebrow">¿Primera visita?</span>
                <h2>Crea tu cuenta</h2>
                <p class="auth-copy">Regístrate como cliente para acceder a reservas y estadías.</p>
                <form action="controller/registro.php" method="POST">
                    <input type="hidden" name="csrfRegistro" value="<?php echo htmlspecialchars($_SESSION['csrfRegistro'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="auth-fields">
                        <div>
                            <label for="registro-nombre">Nombre</label>
                            <input id="registro-nombre" name="nombre" type="text" maxlength="100" autocomplete="given-name" required>
                        </div>
                        <div>
                            <label for="registro-apellido">Apellido</label>
                            <input id="registro-apellido" name="apellido" type="text" maxlength="100" autocomplete="family-name" required>
                        </div>
                        <div class="auth-field-wide">
                            <label for="registro-email">Correo electrónico</label>
                            <input id="registro-email" name="email" type="email" maxlength="150" autocomplete="email" required>
                        </div>
                        <div>
                            <label for="registro-run">RUN <span>(opcional)</span></label>
                            <input id="registro-run" name="run" type="text" maxlength="12" pattern="[0-9]{7,8}-[0-9kK]" placeholder="12345678-5" autocomplete="off">
                        </div>
                        <div>
                            <label for="registro-telefono">Teléfono <span>(opcional)</span></label>
                            <input id="registro-telefono" name="telefono" type="tel" maxlength="20" pattern="[0-9+() .-]{7,20}" autocomplete="tel">
                        </div>
                        <div class="auth-field-wide">
                            <label for="registro-direccion">Dirección <span>(opcional)</span></label>
                            <input id="registro-direccion" name="direccion" type="text" maxlength="255" autocomplete="street-address">
                        </div>
                        <div>
                            <label for="registro-password">Contraseña</label>
                            <input id="registro-password" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
                        </div>
                        <div>
                            <label for="registro-confirmar">Confirmar contraseña</label>
                            <input id="registro-confirmar" name="confirmar_password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
                        </div>
                    </div>
                    <button type="submit" class="auth-button auth-button-secondary">Crear cuenta de cliente</button>
                    <p class="auth-footnote">La contraseña debe tener entre 8 y 72 caracteres.</p>
                </form>
            </section>
        </section>
    </main>
    <footer class="login-footer">Hotel Pacific Reef <span>·</span> Una experiencia junto al mar</footer>
</body>
</html>
