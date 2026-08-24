<?php
/**
 * LLEGAPP 3 - Configuración global de la aplicación
 * Sistema profesional de rastreo de transporte urbano en tiempo real
 */

// ============================================================
// Cargar módulos base
// ============================================================
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/seguridad.php';

// ============================================================
// Constantes de la aplicación
// ============================================================
define('APP_NAME', 'LlegAPP');
define('APP_TAGLINE', 'Tu bus en tiempo real');
define('APP_VERSION', '3.0.0');
define('APP_ENV', 'development'); // development | production
define('APP_TIMEZONE', 'America/Bogota');

// URL base detectada automáticamente (soporta /LLEGAPP3 y subcarpetas)
if (!defined('APP_BASE')) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    // Normalizar: si estamos dentro de pages/, admin/, api/ o processes/, subir un nivel
    foreach (['/pages', '/admin', '/api', '/processes'] as $sub) {
        if (substr($scriptDir, -strlen($sub)) === $sub) {
            $scriptDir = substr($scriptDir, 0, -strlen($sub));
        }
    }
    define('APP_BASE', rtrim($scriptDir, '/'));
}

// ============================================================
// Simulación de flota
// ============================================================
define('FLEET_TICK_MIN_SECONDS', 2);      // Mínimo entre ticks globales (anti-race)
define('BUS_DWELL_SECONDS', 20);          // Permanencia en parada
define('SIM_MAX_CATCHUP_SECONDS', 45);    // Máx. tiempo simulado por tick
define('BUSES_POR_RUTA_DEFAULT', 4);

// ============================================================
// Mapa (Medellín, Colombia)
// ============================================================
define('MAP_CENTER_LAT', 6.2518);
define('MAP_CENTER_LNG', -75.5636);
define('MAP_DEFAULT_ZOOM', 13);

// ============================================================
// Paginación y límites
// ============================================================
define('REGISTROS_POR_PAGINA', 10);
define('MAX_LOGIN_INTENTOS', 5);
define('LOGIN_BLOQUEO_MINUTOS', 15);

// ============================================================
// Logging
// ============================================================
define('LOG_PATH', dirname(__DIR__) . '/logs/');
if (!is_dir(LOG_PATH)) {
    @mkdir(LOG_PATH, 0755, true);
}

function logMessage(string $level, string $message, array $context = []): void {
    if ($level !== 'error' && APP_ENV !== 'development') return;
    $entry = sprintf(
        "[%s] [%s] %s %s%s",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $message,
        json_encode($context, JSON_UNESCAPED_UNICODE),
        PHP_EOL
    );
    @file_put_contents(LOG_PATH . 'app-' . date('Y-m-d') . '.log', $entry, FILE_APPEND | LOCK_EX);
}

/**
 * Bootstrap del entorno (se ejecuta al incluir este archivo)
 */
function bootstrapApp(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.use_strict_mode', '1');
        session_name('LLEGAPP3_SESSION');
        session_start();
    }

    date_default_timezone_set(APP_TIMEZONE);

    setSecurityHeaders();

    if (APP_ENV === 'production') {
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    } else {
        ini_set('display_errors', '1');
        error_reporting(E_ALL);
    }

    csrfToken(); // Garantiza existencia de token CSRF
}

bootstrapApp();
