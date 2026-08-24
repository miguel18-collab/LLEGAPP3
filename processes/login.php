<?php
/**
 * LLEGAPP 3 · Procesar inicio de sesión
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE . '/pages/login.php');
    exit;
}

if (!validarCSRF()) {
    $_SESSION['login_error'] = 'Sesión expirada. Inténtalo de nuevo.';
    header('Location: ' . APP_BASE . '/pages/login.php');
    exit;
}

$email    = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$redirect = $_POST['redirect'] ?? '';

$_SESSION['prefill_email'] = $email;

if ($email === '' || $password === '') {
    $_SESSION['login_error'] = 'Completa todos los campos.';
    header('Location: ' . APP_BASE . '/pages/login.php');
    exit;
}

if (intentosLoginExcedidos($email)) {
    $_SESSION['login_error'] = 'Demasiados intentos fallidos. Espera ' . LOGIN_BLOQUEO_MINUTOS . ' minutos.';
    logMessage('warning', 'Cuenta bloqueada temporalmente', ['email' => $email]);
    header('Location: ' . APP_BASE . '/pages/login.php');
    exit;
}

try {
    $user = dbOne('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);
} catch (Exception $e) {
    $_SESSION['login_error'] = 'Servicio no disponible. Verifica que la base de datos esté instalada (database/setup.php).';
    header('Location: ' . APP_BASE . '/pages/login.php');
    exit;
}

if (!$user || !password_verify($password, $user['password_hash'])) {
    registrarIntentoFallido($email);
    $_SESSION['login_error'] = 'Correo o contraseña incorrectos.';
    header('Location: ' . APP_BASE . '/pages/login.php');
    exit;
}

if (!(int)$user['is_active']) {
    $_SESSION['login_error'] = 'Tu cuenta está desactivada. Contacta al administrador.';
    header('Location: ' . APP_BASE . '/pages/login.php');
    exit;
}

limpiarIntentosLogin($email);
iniciarSesionUsuario($user);

$destino = APP_BASE . '/pages/mapa.php';
if ($redirect && str_starts_with($redirect, '/') && !str_starts_with($redirect, '//')) {
    $destino = $redirect;
} elseif ($user['role'] === 'admin') {
    $destino = APP_BASE . '/admin/dashboard.php';
}

header('Location: ' . $destino);
exit;
