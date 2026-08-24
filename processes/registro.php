<?php
/**
 * LLEGAPP 3 · Procesar registro de usuarios
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE . '/pages/registro.php');
    exit;
}

if (!validarCSRF()) {
    $_SESSION['registro_error'] = 'Sesión expirada. Inténtalo de nuevo.';
    header('Location: ' . APP_BASE . '/pages/registro.php');
    exit;
}

$name     = trim($_POST['name'] ?? '');
$email    = strtolower(trim($_POST['email'] ?? ''));
$phone    = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$password2 = $_POST['password_confirm'] ?? '';

$_SESSION['registro_campos'] = ['name' => $name, 'email' => $email, 'phone' => $phone];
$volver = fn() => header('Location: ' . APP_BASE . '/pages/registro.php');

if ($name === '' || mb_strlen($name) < 3) {
    $_SESSION['registro_error'] = 'Ingresa tu nombre completo (mín. 3 caracteres).';
    $volver(); exit;
}
if (!emailValido($email)) {
    $_SESSION['registro_error'] = 'Ingresa un correo electrónico válido.';
    $volver(); exit;
}
if (!passwordSegura($password)) {
    $_SESSION['registro_error'] = 'La contraseña debe tener mínimo 8 caracteres, con al menos una letra y un número.';
    $volver(); exit;
}
if ($password !== $password2) {
    $_SESSION['registro_error'] = 'Las contraseñas no coinciden.';
    $volver(); exit;
}

try {
    if (dbVal('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0) {
        $_SESSION['registro_error'] = 'Ya existe una cuenta con ese correo.';
        $volver(); exit;
    }

    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

    dbq(
        'INSERT INTO users (id, name, email, password_hash, role, phone, avatar_color, email_verified)
         VALUES (?, ?, ?, ?, "usuario", ?, ?, 1)',
        [$uuid, $name, $email, password_hash($password, PASSWORD_BCRYPT), $phone ?: null, colorAvatar($email)]
    );
    dbq('INSERT INTO user_preferences (user_id) VALUES (?)', [$uuid]);
    dbq(
        'INSERT INTO notifications (user_id, type, title, message, icon) VALUES (?, "sistema", ?, ?, ?)',
        [$uuid, '¡Bienvenido a LlegAPP!', 'Explora el mapa en tiempo real y configura tus alertas de llegada.', '🎉']
    );

    unset($_SESSION['registro_campos']);
    $_SESSION['registro_exito'] = 'Cuenta creada. Ahora puedes iniciar sesión.';
    logMessage('info', 'Registro de usuario', ['email' => $email]);
    header('Location: ' . APP_BASE . '/pages/login.php');
    exit;

} catch (Exception $e) {
    logMessage('error', 'Error en registro', ['msg' => $e->getMessage()]);
    $_SESSION['registro_error'] = 'Error del sistema. Inténtalo nuevamente.';
    $volver(); exit;
}
