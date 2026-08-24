<?php
/**
 * LLEGAPP 3 - Seguridad: CSRF, sanitización, headers, rate limiting
 */

/**
 * Obtiene (o genera) el token CSRF de la sesión
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Campo oculto listo para formularios
 */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

/**
 * Valida token CSRF desde POST o header X-CSRF-Token
 */
function validarCSRF(?string $token = null): bool {
    $token = $token
        ?? ($_POST['csrf_token'] ?? null)
        ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Exige CSRF válido en peticiones mutantes; responde JSON o redirige
 */
function requerirCSRF(): void {
    if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !validarCSRF()) {
        if (esPeticionAPI()) {
            jsonError('Token CSRF inválido o expirado', 403);
        }
        abortar(403, 'Token de seguridad inválido. Recarga la página e inténtalo de nuevo.');
    }
}

/**
 * Escapa salida HTML
 */
function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Headers de seguridad HTTP
 */
function setSecurityHeaders(): void {
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');
    header('Permissions-Policy: geolocation=(self), microphone=(), camera=()');
}

/**
 * Detecta si la petición actual es AJAX/API
 */
function esPeticionAPI(): bool {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return str_contains($uri, '/api/')
        || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || str_contains($accept, 'application/json');
}

/**
 * Rate limiter simple basado en sesión (login)
 */
function intentosLoginExcedidos(string $identidad): bool {
    $key = 'login_attempts_' . md5($identidad . ($_SERVER['REMOTE_ADDR'] ?? ''));
    $data = $_SESSION[$key] ?? ['count' => 0, 'start' => time()];
    if ((time() - $data['start']) > LOGIN_BLOQUEO_MINUTOS * 60) {
        $data = ['count' => 0, 'start' => time()];
    }
    $_SESSION[$key] = $data;
    return $data['count'] >= MAX_LOGIN_INTENTOS;
}

function registrarIntentoFallido(string $identidad): void {
    $key = 'login_attempts_' . md5($identidad . ($_SERVER['REMOTE_ADDR'] ?? ''));
    $data = $_SESSION[$key] ?? ['count' => 0, 'start' => time()];
    $data['count']++;
    $_SESSION[$key] = $data;
    logMessage('warning', 'Intento de login fallido', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'email' => $identidad]);
}

function limpiarIntentosLogin(string $identidad): void {
    unset($_SESSION['login_attempts_' . md5($identidad . ($_SERVER['REMOTE_ADDR'] ?? ''))]);
}

/**
 * Aborta con página de error amigable
 */
function abortar(int $codigo, string $mensaje = ''): never {
    http_response_code($codigo);
    $titulo = match ($codigo) {
        403 => 'Acceso denegado',
        404 => 'Página no encontrada',
        default => 'Error del sistema',
    };
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>' . e($titulo) . ' · ' . APP_NAME . '</title>';
    echo '<link rel="stylesheet" href="' . APP_BASE . '/assets/css/styles.css"></head><body class="error-page">';
    echo '<div class="error-card"><div class="error-code">' . $codigo . '</div><h1>' . e($titulo) . '</h1>';
    echo '<p>' . e($mensaje ?: 'Ha ocurrido un problema. Por favor inténtalo nuevamente.') . '</p>';
    echo '<a href="' . APP_BASE . '/index.php" class="btn btn-primary">Volver al inicio</a></div></body></html>';
    exit;
}
