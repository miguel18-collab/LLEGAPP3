<?php
/**
 * API · GET /api/eta.php?parada=ID → próximos buses a una parada
 *         GET /api/etas_ruta.php?ruta=M101 (alias incluido aquí vía acción)
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/rutas.php';

requireMethod('GET');

try {
    $stopId = (int)input('parada', 0);
    if ($stopId <= 0) jsonError('Parámetro ?parada= requerido', 422);

    $stop = dbOne('SELECT s.*, r.color FROM stops s JOIN routes r ON r.route_id = s.route_id WHERE s.id = ?', [$stopId]);
    if (!$stop) jsonError('Parada no encontrada', 404);

    jsonResponse([
        'parada' => ['id' => $stopId, 'name' => $stop['name'], 'route_id' => $stop['route_id']],
        'buses'  => proximosBusesParada($stopId, 5),
    ]);
} catch (Exception $e) {
    logMessage('error', 'Error en eta.php', ['msg' => $e->getMessage()]);
    jsonError('Servicio no disponible', 503);
}
