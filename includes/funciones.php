<?php
/**
 * LLEGAPP 3 - Funciones auxiliares generales
 */

/**
 * Respuesta JSON estandarizada y termina ejecución
 */
function jsonResponse(mixed $data = null, int $status = 200, string $message = ''): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => $status < 400, 'status' => $status];
    if ($message !== '') $response['message'] = $message;
    if ($data !== null)  $response['data'] = $data;
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonError(string $message, int $status = 400, mixed $details = null): never {
    jsonResponse($details === null ? null : ['details' => $details], $status, $message);
}

/**
 * Exige método HTTP
 */
function requireMethod(string ...$methods): void {
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', $methods, true)) {
        jsonError('Método no permitido. Se requiere: ' . implode('|', $methods), 405);
    }
}

/**
 * Cuerpo JSON de la petición
 */
function jsonBody(): array {
    static $body = null;
    if ($body === null) {
        $raw = file_get_contents('php://input');
        $body = json_decode($raw ?: '', true);
        if (!is_array($body)) $body = [];
    }
    return $body;
}

/**
 * Parámetros de entrada unificados (JSON body + POST + GET)
 */
function input(?string $key = null, $default = null) {
    $source = array_merge($_GET, $_POST, jsonBody());
    if ($key === null) return $source;
    return $source[$key] ?? $default;
}

/**
 * Distancia Haversine en km
 */
function distanciaKm(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $r = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/**
 * Interpola posición entre dos coordenadas
 */
function interpolar(float $lat1, float $lng1, float $lat2, float $lng2, float $progress): array {
    return [
        'lat' => round($lat1 + ($lat2 - $lat1) * $progress, 6),
        'lng' => round($lng1 + ($lng2 - $lng1) * $progress, 6),
    ];
}

/**
 * Formatea duración en minutos a texto legible
 */
function formatoDuracion(int $minutos): string {
    if ($minutos < 60) return $minutos . ' min';
    $h = intdiv($minutos, 60);
    $m = $minutos % 60;
    return $h . ' h' . ($m > 0 ? ' ' . $m . ' min' : '');
}

function formatoDistancia(float $km): string {
    return $km < 1 ? round($km * 1000) . ' m' : round($km, 1) . ' km';
}

function tiempoRelativo(string $fecha): string {
    $ts = strtotime($fecha);
    if (!$ts) return '';
    $diff = time() - $ts;
    return match (true) {
        $diff < 60     => 'hace un momento',
        $diff < 3600   => 'hace ' . floor($diff / 60) . ' min',
        $diff < 86400  => 'hace ' . floor($diff / 3600) . ' h',
        $diff < 604800 => 'hace ' . floor($diff / 86400) . ' d',
        default        => date('d/m/Y', $ts),
    };
}

function inicialesNombre(string $nombre): string {
    $partes = preg_split('/\s+/', trim($nombre));
    $ini = strtoupper(substr($partes[0] ?? 'U', 0, 1));
    if (count($partes) > 1) $ini .= strtoupper(substr(end($partes), 0, 1));
    return $ini;
}

function colorAvatar(string $semilla): string {
    $colores = ['#00A651', '#3B82F6', '#8B5CF6', '#F59E0B', '#EF4444', '#14B8A6', '#EC4899'];
    return $colores[crc32($semilla) % count($colores)];
}

/**
 * Validación de email y contraseña segura
 */
function emailValido(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && strlen($email) <= 120;
}

function passwordSegura(string $pass): bool {
    return strlen($pass) >= 8
        && preg_match('/[A-Za-z]/', $pass)
        && preg_match('/\d/', $pass);
}
