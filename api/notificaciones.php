<?php
/**
 * API · Notificaciones
 * GET  /api/notificaciones.php            → últimas + no leídas
 * PUT  /api/notificaciones.php            {id} | {todas:true}
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = requerirAuth();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $noLeidas = (int)dbVal('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$currentUser['id']]);
        jsonResponse([
            'no_leidas' => $noLeidas,
            'notificaciones' => dbAll(
                'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 30',
                [$currentUser['id']]
            ),
        ]);
    }

    requireMethod('PUT');
    requerirCSRF();

    if (input('todas')) {
        dbq('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$currentUser['id']]);
        jsonResponse(null, 200, 'Todas marcadas como leídas');
    }

    $id = (int)input('id', 0);
    if (!$id) jsonError('Falta el identificador', 422);
    dbq('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [$id, $currentUser['id']]);
    jsonResponse(null, 200, 'Notificación marcada como leída');
} catch (Exception $e) {
    jsonError('Error interno', 500);
}
