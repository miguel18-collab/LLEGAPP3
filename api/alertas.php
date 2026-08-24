<?php
/**
 * API · Alertas de proximidad
 * GET    /api/alertas.php            → mis alertas
 * POST   /api/alertas.php            {route_id, stop_id, stops_ahead}
 * PUT    /api/alertas.php            {id, active} | {stops_ahead}
 * DELETE /api/alertas.php?id=N
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = requerirAuth();

try {
    $metodo = $_SERVER['REQUEST_METHOD'];

    if ($metodo === 'GET') {
        jsonResponse(['alertas' => dbAll(
            'SELECT ua.*, s.name AS stop_name, r.name AS route_name, r.color, r.short_name
             FROM user_alerts ua
             JOIN stops s ON s.id = ua.stop_id
             JOIN routes r ON r.route_id = ua.route_id
             WHERE ua.user_id = ? ORDER BY ua.created_at DESC',
            [$currentUser['id']]
        )]);
    }

    requerirCSRF();

    if ($metodo === 'POST') {
        $routeId = preg_replace('/[^A-Za-z0-9]/', '', (string)input('route_id', ''));
        $stopId  = (int)input('stop_id', 0);
        $stopsAhead = min(3, max(1, (int)input('stops_ahead', 2)));

        if (!$routeId || !$stopId) jsonError('Datos incompletos', 422);

        $stop = dbOne('SELECT * FROM stops WHERE id = ? AND route_id = ?', [$stopId, $routeId]);
        if (!$stop) jsonError('La parada no pertenece a esa ruta', 422);

        if (dbVal('SELECT COUNT(*) FROM user_alerts WHERE user_id = ? AND stop_id = ?', [$currentUser['id'], $stopId])) {
            jsonError('Ya tienes una alerta para esa parada', 409);
        }

        dbq(
            'INSERT INTO user_alerts (user_id, route_id, stop_id, stops_ahead) VALUES (?,?,?,?)',
            [$currentUser['id'], $routeId, $stopId, $stopsAhead]
        );
        dbq('INSERT INTO trip_history (user_id, action, route_id) VALUES (?, "alert_created", ?)', [$currentUser['id'], $routeId]);
        dbq(
            'INSERT INTO notifications (user_id, type, title, message, icon) VALUES (?, "sistema", ?, ?, ?)',
            [$currentUser['id'], 'Alerta creada', "Te avisaremos cuando un bus de {$routeId} esté cerca de {$stop['name']}.", '✅']
        );

        jsonResponse(['id' => (int)db()->lastInsertId()], 201, 'Alerta creada');
    }

    if ($metodo === 'PUT') {
        $id = (int)input('id', 0);
        if (!$id) jsonError('Falta el identificador', 422);

        $alerta = dbOne('SELECT * FROM user_alerts WHERE id = ? AND user_id = ?', [$id, $currentUser['id']]);
        if (!$alerta) jsonError('Alerta no encontrada', 404);

        $active = input('active');
        $ahead = input('stops_ahead');

        if ($active !== null) {
            dbq('UPDATE user_alerts SET active = ? WHERE id = ?', [(int)(bool)$active, $id]);
        }
        if ($ahead !== null) {
            dbq('UPDATE user_alerts SET stops_ahead = ? WHERE id = ?', [min(3, max(1, (int)$ahead)), $id]);
        }
        jsonResponse(null, 200, 'Alerta actualizada');
    }

    if ($metodo === 'DELETE') {
        $id = (int)input('id', 0);
        dbq('DELETE FROM user_alerts WHERE id = ? AND user_id = ?', [$id, $currentUser['id']]);
        jsonResponse(null, 200, 'Alerta eliminada');
    }

    requireMethod('GET', 'POST', 'PUT', 'DELETE');
} catch (Exception $e) {
    logMessage('error', 'Error alertas', ['msg' => $e->getMessage()]);
    jsonError('Error interno', 500);
}
