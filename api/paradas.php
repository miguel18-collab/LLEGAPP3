<?php
/**
 * API · GET /api/paradas.php?ruta=M101   → paradas de una ruta
 *         /api/paradas.php?lat=6.25&lng=-75.56 → paradas cercanas
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/rutas.php';

requireMethod('GET');

try {
    $routeId = preg_replace('/[^A-Za-z0-9]/', '', input('ruta', ''));
    $lat = input('lat'); $lng = input('lng');

    if ($routeId) {
        jsonResponse(['paradas' => obtenerParadas($routeId)]);
    }

    if (is_numeric($lat) && is_numeric($lng)) {
        jsonResponse([
            'paradas'  => paradasCercanas((float)$lat, (float)$lng, 8),
            'distancias' => true,
        ]);
    }

    jsonError('Indica ?ruta= o coordenadas ?lat=&lng=', 422);
} catch (Exception $e) {
    jsonError('Servicio no disponible', 503);
}
