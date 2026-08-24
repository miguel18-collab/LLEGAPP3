<?php
/**
 * API · Favoritos
 * GET    /api/favoritos.php          → lista
 * POST   /api/favoritos.php          {item_type, item_key} → alterna
 * DELETE /api/favoritos.php?id=N     → elimina
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = requerirAuth();

try {
    $metodo = $_SERVER['REQUEST_METHOD'];

    if ($metodo === 'GET') {
        jsonResponse(['favoritos' => dbAll(
            'SELECT f.*, r.name AS route_name, r.color, r.emoji, s.name AS stop_name
             FROM favorites f
             LEFT JOIN routes r ON f.item_type = "ruta" AND r.route_id = f.item_key
             LEFT JOIN stops s  ON f.item_type = "parada" AND s.id = f.item_key
             WHERE f.user_id = ? ORDER BY f.created_at DESC',
            [$currentUser['id']]
        )]);
    }

    if ($metodo === 'POST') {
        $tipo = input('item_type', '');
        $clave = (string)input('item_key', '');

        if (!in_array($tipo, ['ruta', 'parada'], true) || $clave === '') {
            jsonError('Datos incompletos', 422);
        }
        if ($tipo === 'ruta') $clave = preg_replace('/[^A-Za-z0-9]/', '', $clave);
        else if (!ctype_digit($clave)) jsonError('Clave inválida', 422);

        // ¿Existe ya?
        $existe = dbOne('SELECT id FROM favorites WHERE user_id = ? AND item_type = ? AND item_key = ?', [$currentUser['id'], $tipo, $clave]);

        if ($existe) {
            dbq('DELETE FROM favorites WHERE id = ?', [$existe['id']]);
            jsonResponse(['favorito' => false], 200, 'Eliminado de favoritos');
        }

        // Validar existencia del elemento
        if ($tipo === 'ruta') {
            $ok = dbVal('SELECT COUNT(*) FROM routes WHERE route_id = ?', [$clave]);
            $label = dbVal('SELECT short_name FROM routes WHERE route_id = ?', [$clave], $clave);
        } else {
            $ok = dbVal('SELECT COUNT(*) FROM stops WHERE id = ?', [$clave]);
            $label = dbVal('SELECT name FROM stops WHERE id = ?', [$clave], '');
        }
        if (!$ok) jsonError('El elemento no existe', 404);

        dbq('INSERT IGNORE INTO favorites (user_id, item_type, item_key, label) VALUES (?,?,?,?)', [$currentUser['id'], $tipo, $clave, $label]);
        dbq('INSERT INTO trip_history (user_id, action, route_id) VALUES (?, "viewed", NULL)', [$currentUser['id']]); // actividad ligera
        jsonResponse(['favorito' => true], 200, 'Añadido a favoritos');
    }

    if ($metodo === 'DELETE') {
        $id = (int)(input('id', 0));
        dbq('DELETE FROM favorites WHERE id = ? AND user_id = ?', [$id, $currentUser['id']]);
        jsonResponse(null, 200, 'Favorito eliminado');
    }

    requireMethod('GET', 'POST', 'DELETE');
} catch (Exception $e) {
    logMessage('error', 'Error favoritos', ['msg' => $e->getMessage()]);
    jsonError('Error interno', 500);
}
