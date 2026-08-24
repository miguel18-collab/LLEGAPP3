<?php
/**
 * API · GET /api/posiciones.php[?ruta=M101]
 * Devuelve la flota en vivo (avanza la simulación global).
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/rutas.php';

requireMethod('GET');

$ruta = preg_replace('/[^A-Za-z0-9]/', '', input('ruta', ''));
header('Cache-Control: no-store');

try {
    $buses = obtenerFlotaEnVivo($ruta ?: null);
    jsonResponse([
        'buses'      => $buses,
        'total'      => count($buses),
        'servidor'   => date('c'),
    ]);
} catch (Exception $e) {
    logMessage('error', 'Error en posiciones.php', ['msg' => $e->getMessage()]);
    jsonError('Servicio de flota no disponible', 503);
}
