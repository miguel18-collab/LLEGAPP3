<?php
/**
 * API · GET /api/rutas.php              → lista de rutas
 *         /api/rutas.php?ruta=M101&con_paradas=1 → detalle con paradas
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/rutas.php';

requireMethod('GET');

try {
    $routeId = preg_replace('/[^A-Za-z0-9]/', '', input('ruta', ''));

    if ($routeId) {
        $ruta = obtenerRuta($routeId);
        if (!$ruta) jsonError('Ruta no encontrada', 404);

        $data = ['ruta' => $ruta];
        if (input('con_paradas')) {
            $data['paradas'] = obtenerParadas($routeId);
        }
        jsonResponse($data);
    }

    jsonResponse(['rutas' => obtenerRutas()]);
} catch (Exception $e) {
    logMessage('error', 'Error en rutas.php', ['msg' => $e->getMessage()]);
    jsonError('Servicio no disponible', 503);
}
