<?php
/**
 * API · GET /api/etas_ruta.php?ruta=M101
 * ETA (min) del próximo bus por cada parada de la ruta → {stopId: minutos}
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/rutas.php';

requireMethod('GET');

$routeId = preg_replace('/[^A-Za-z0-9]/', '', input('ruta', ''));
if (!$routeId || !obtenerRuta($routeId)) jsonError('Ruta no encontrada', 404);

try {
    avanzarFlota();
    $paradas = obtenerParadas($routeId);
    $buses = dbAll("SELECT * FROM buses WHERE route_id = ? AND status = 'operando'", [$routeId]);

    $resultado = [];
    foreach ($paradas as $p) {
        $mejorEta = null;
        foreach ($buses as $bus) {
            $eta = etaBusHastaOrden($bus, $paradas, (int)$p['stop_order']);
            if ($eta !== null && ($mejorEta === null || $eta < $mejorEta)) {
                $mejorEta = $eta;
            }
        }
        $resultado[$p['id']] = $mejorEta === null ? null : max(1, (int)ceil($mejorEta / 60));
    }

    jsonResponse($resultado);
} catch (Exception $e) {
    logMessage('error', 'Error en etas_ruta.php', ['msg' => $e->getMessage()]);
    jsonError('Servicio no disponible', 503);
}
