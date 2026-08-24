<?php
/**
 * LLEGAPP 3 - Autenticación y control de acceso por roles
 */

require_once __DIR__ . '/funciones.php';

/**
 * Usuario autenticado actual (cachea por petición) o null
 */
function usuarioActual(): ?array {
    static $cache = false;
    if ($cache !== false) return $cache;

    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) return $cache = null;

    try {
        $user = dbOne(
            'SELECT id, name, email, role, phone, avatar_color, email_verified, is_active, created_at
             FROM users WHERE id = ? AND is_active = 1',
            [$userId]
        );
        return $cache = $user;
    } catch (Exception $e) {
        logMessage('error', 'Error verificando sesión', ['msg' => $e->getMessage()]);
        return $cache = null;
    }
}

function estaAutenticado(): bool {
    return usuarioActual() !== null;
}

function esAdmin(): bool {
    return (usuarioActual()['role'] ?? '') === 'admin';
}

/**
 * Exige autenticación (JSON 401 o redirect a login)
 */
function requerirAuth(): array {
    $user = usuarioActual();
    if ($user) return $user;

    if (esPeticionAPI()) {
        jsonError('No autenticado', 401);
    }
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? APP_BASE . '/');
    header('Location: ' . APP_BASE . '/pages/login.php?redirect=' . $redirect);
    exit;
}

/**
 * Exige rol admin
 */
function requerirAdmin(): array {
    $user = requerirAuth();
    if ($user['role'] !== 'admin') {
        if (esPeticionAPI()) jsonError('Se requiere rol de administrador', 403);
        abortar(403, 'Esta sección está reservada para administradores del sistema.');
    }
    return $user;
}

/**
 * Inicia sesión de forma segura
 */
function iniciarSesionUsuario(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];
    $_SESSION['login_time'] = time();
    csrfToken(); // Renovar token tras login
    dbq('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);
    logMessage('info', 'Login exitoso', ['user' => $user['email']]);
}

/**
 * Cierra la sesión
 */
function cerrarSesion(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
