<?php
/**
 * API · Reportar problema del usuario
 * POST /api/reporte_problema.php
 * Body JSON: { bus_id, route_id, stop_id, tipo, descripcion, lat, lng }
 * GET  /api/reporte_problema.php → listar reportes (admin)
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';

try {
    // GET → listar reportes (solo admin)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $admin = requerirAdmin();
        $reportes = dbAll('
            SELECT r.*, u.name AS user_name, rte.short_name AS route_name, s.name AS stop_name
            FROM reportes_problema r
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN routes rte ON r.route_id = rte.route_id
            LEFT JOIN stops s ON r.stop_id = s.id
            ORDER BY r.created_at DESC
        ');
        jsonResponse(['reportes' => $reportes]);
    }

    // POST → crear reporte (usuario autenticado o anónimo)
    requireMethod('POST');
    requerirCSRF();

    $user = usuarioActual(); // puede ser null (anónimo permitido)
    $busId     = input('bus_id', '');
    $routeId   = preg_replace('/[^A-Za-z0-9]/', '', (string)input('route_id', ''));
    $stopId    = input('stop_id', '');
    $tipo      = input('tipo', 'otro');
    $desc      = trim((string)input('descripcion', ''));
    $lat       = input('lat');
    $lng       = input('lng');

    if (!in_array($tipo, ['bus_fantasma','ruta_incorrecta','tiempo_inaccurato','bus_detenido','otro'], true)) {
        $tipo = 'otro';
    }
    if ($desc === '' && empty($busId) && empty($routeId)) {
        jsonError('Datos incompletos para el reporte', 422);
    }

    $stopIdInt = $stopId === '' ? null : (int)$stopId;

    dbq(
        'INSERT INTO reportes_problema (user_id, bus_id, route_id, stop_id, tipo, descripcion, lat, lng)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $user ? $user['id'] : null,
            $busId ?: null,
            $routeId ?: null,
            $stopIdInt,
            $tipo,
            $desc ?: null,
            $lat !== null ? (float)$lat : null,
            $lng !== null ? (float)$lng : null,
        ]
    );

    // Notificar a admins
    foreach (dbAll('SELECT id FROM users WHERE role = "admin" AND is_active = 1') as $a) {
        dbq(
            'INSERT INTO notifications (user_id, type, title, message, icon) VALUES (?, "incidencia", ?, ?, "🚨")',
            [
                $a['id'],
                'Nuevo reporte de problema',
                "Se reportó: {$tipo}" . ($desc ? " — {$desc}" : ''),
            ]
        );
    }

    jsonResponse(['id' => (int)db()->lastInsertId()], 201, 'Reporte enviado. Gracias por ayudarnos a mejorar.');

} catch (Exception $e) {
    logMessage('error', 'Error reporte_problema', ['msg' => $e->getMessage()]);
    jsonError('No se pudo enviar el reporte', 500);
}
