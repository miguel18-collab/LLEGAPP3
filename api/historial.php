<?php
/**
 * API · Historial de actividad
 * GET  /api/historial.php?limite=20
 * POST /api/historial.php {action, route_id, origin_stop, dest_stop}
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = requerirAuth();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $limite = min(100, max(1, (int)input('limite', 30)));
        jsonResponse(['historial' => dbAll(
            'SELECT h.*, r.name AS route_name, r.color
             FROM trip_history h LEFT JOIN routes r ON r.route_id = h.route_id
             WHERE h.user_id = ? ORDER BY h.created_at DESC LIMIT ' . $limite,
            [$currentUser['id']]
        )]);
    }

    requireMethod('POST');
    requerirCSRF();

    $accion = input('action', 'viewed');
    if (!in_array($accion, ['viewed', 'tracked', 'planned', 'alert_created'], true)) {
        jsonError('Acción inválida', 422);
    }

    $routeId = preg_replace('/[^A-Za-z0-9]/', '', (string)input('route_id', '')) ?: null;

    dbq(
        'INSERT INTO trip_history (user_id, action, route_id, origin_stop, dest_stop)
         VALUES (?, ?, ?, ?, ?)',
        [
            $currentUser['id'],
            $accion,
            $routeId,
            input('origin_stop') ?: null,
            input('dest_stop') ?: null,
        ]
    );
    jsonResponse(null, 201);
} catch (Exception $e) {
    logMessage('error', 'Error historial', ['msg' => $e->getMessage()]);
    jsonError('Error interno', 500);
}
