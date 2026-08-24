<?php
/**
 * API admin · GET /api/admin/dashboard.php
 * KPIs y series temporales para el panel (requiere rol admin).
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$admin = requerirAdmin();

try {
    $kpis = estadisticasSistema();
    $kpis['alertas_activas'] = (int)dbVal('SELECT COUNT(*) FROM system_alerts WHERE active = 1', [], 0);
    $kpis['notificaciones_hoy'] = (int)dbVal('SELECT COUNT(*) FROM notifications WHERE DATE(created_at) = CURDATE()', [], 0);

    // Actividad últimos 14 días
    $serie = [];
    for ($i = 13; $i >= 0; $i--) {
        $dia = date('Y-m-d', strtotime("-$i days"));
        $serie[] = [
            'fecha'   => $dia,
            'viajes'  => (int)dbVal('SELECT COUNT(*) FROM trip_history WHERE DATE(created_at) = ?', [$dia]),
            'usuarios' => (int)dbVal('SELECT COUNT(*) FROM users WHERE DATE(created_at) = ?', [$dia]),
        ];
    }

    jsonResponse([
        'kpis'  => $kpis,
        'serie' => $serie,
        'flota' => dbAll('
            SELECT b.route_id, r.color, r.short_name,
                   SUM(b.passengers) AS pasajeros, COUNT(*) AS buses
            FROM buses b JOIN routes r ON r.route_id = b.route_id
            GROUP BY b.route_id, r.color, r.short_name ORDER BY r.short_name
        '),
    ]);
} catch (Exception $e) {
    jsonError('Error interno', 500);
}
