<?php
session_start();

$token = $_POST['csrfLogout'] ?? '';
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    || !is_string($token)
    || !isset($_SESSION['csrfLogout'])
    || !hash_equals($_SESSION['csrfLogout'], $token)
) {
    http_response_code(403);
    exit('Solicitud de cierre de sesión inválida.');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

header('Location: ../index.php');
exit();
