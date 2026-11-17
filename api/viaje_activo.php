<?php
/**
 * API · Viajes activos (modo viaje en segundo plano)
 * POST   /api/viaje_activo.php   {route_id, stop_origen_id, stop_destino_id, bus_id}
 * GET    /api/viaje_activo.php   → mis viajes activos
 * PUT    /api/viaje_activo.php   {id, estado, alerta_enviada}
 * DELETE /api/viaje_activo.php?id=N
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = requerirAuth();

try {
    // GET → listar viajes activos del usuario
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $viajes = dbAll('
            SELECT v.*, r.short_name AS route_name, r.color,
                   so.name AS stop_origen, sd.name AS stop_destino,
                   b.code AS bus_code
            FROM viajes_activos v
            JOIN routes r ON r.route_id = v.route_id
            LEFT JOIN stops so ON so.id = v.stop_origen_id
            LEFT JOIN stops sd ON sd.id = v.stop_destino_id
            LEFT JOIN buses b ON b.id = v.bus_id
            WHERE v.user_id = ?
            ORDER BY v.iniciado_en DESC
        ', [$currentUser['id']]);
        jsonResponse(['viajes' => $viajes]);
    }

    requireMethod('POST');
    requerirCSRF();

    $accion = input('accion', 'iniciar');

    if ($accion === 'iniciar') {
        $routeId = preg_replace('/[^A-Za-z0-9]/', '', (string)input('route_id', ''));
        $stopOrigen = (int)input('stop_origen_id', 0);
        $stopDestino = (int)input('stop_destino_id', 0);
        $busId = input('bus_id', '') ?: null;

        if (!$routeId) jsonError('Ruta requerida', 422);

        // Cancelar viajes activos previos del usuario
        dbq('UPDATE viajes_activos SET estado = "cancelado", finalizado_en = NOW() WHERE user_id = ? AND estado IN ("esperando","en_camino","cerca_destino")', [$currentUser['id']]);

        dbq(
            'INSERT INTO viajes_activos (user_id, route_id, stop_origen_id, stop_destino_id, bus_id, estado)
             VALUES (?, ?, ?, ?, ?, "esperando")',
            [$currentUser['id'], $routeId, $stopOrigen ?: null, $stopDestino ?: null, $busId]
        );
        jsonResponse(['id' => (int)db()->lastInsertId()], 201, 'Viaje iniciado. Te avisaremos al acercarte a tu parada.');
    }

    if ($accion === 'actualizar_estado') {
        $id = (int)input('id', 0);
        $estado = input('estado', '');
        if (!$id || !in_array($estado, ['esperando','en_camino','cerca_destino','completado','cancelado'], true)) {
            jsonError('Datos inválidos', 422);
        }
        $extra = $estado === 'completado' || $estado === 'cancelado' ? ', finalizado_en = NOW()' : '';
        dbq("UPDATE viajes_activos SET estado = ?{$extra} WHERE id = ? AND user_id = ?", [$estado, $id, $currentUser['id']]);
        jsonResponse(null, 200, 'Estado actualizado.');
    }

    if ($accion === 'marcar_alerta') {
        $id = (int)input('id', 0);
        dbq('UPDATE viajes_activos SET alerta_enviada = 1 WHERE id = ? AND user_id = ?', [$id, $currentUser['id']]);
        jsonResponse(null, 200, 'Alerta marcada como enviada.');
    }

    jsonError('Acción desconocida', 422);

} catch (Exception $e) {
    logMessage('error', 'Error viaje_activo', ['msg' => $e->getMessage()]);
    jsonError('Error interno', 500);
}
