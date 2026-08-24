<?php
/**
 * API admin · POST /api/admin/simulacion.php  {accion: "reset"|"pausar"|"reanudar"|"acelerar"}
 * Control de la flota simulada (requiere rol admin).
 */
require_once dirname(__DIR__, 2) . '/config/configuracion.php';
require_once dirname(__DIR__, 2) . '/includes/funciones.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

$admin = requerirAdmin();
requireMethod('POST');
requerirCSRF();

$accion = input('accion', '');
$ruta = preg_replace('/[^A-Za-z0-9]/', '', (string)input('ruta', ''));
$where = $ruta ? 'WHERE route_id = ?' : '';
$params = $ruta ? [$ruta] : [];

try {
    switch ($accion) {
        case 'reset':
            // Reinicia posiciones al azar
            $buses = dbAll("SELECT id FROM buses $where", $params);
            $stmt = db()->prepare('UPDATE buses SET stop_index = ?, progress = ?, direction = ?, passengers = ?, last_update = NOW() WHERE id = ?');
            foreach ($buses as $b) {
                $maxOrden = (int)dbVal('SELECT MAX(stop_order) FROM stops WHERE route_id = (SELECT route_id FROM buses WHERE id = ?)', [$b['id']], 2);
                $stmt->execute([
                    random_int(1, max(1, $maxOrden)),
                    round(random_int(0, 80) / 100, 2),
                    random_int(0, 1) ? 1 : -1,
                    random_int(5, 45),
                    $b['id'],
                ]);
            }
            dbq("UPDATE settings SET valor = ? WHERE clave = 'fleet_last_tick'", [time()]);
            jsonResponse(null, 200, 'Flota reiniciada (' . count($buses) . ' buses)');
            break;

        case 'pausar':
            dbq("UPDATE buses $where SET status = 'detenido'", $params);
            jsonResponse(null, 200, 'Flota pausada');
            break;

        case 'reanudar':
            dbq("UPDATE buses $where SET status = 'operando'", $params);
            jsonResponse(null, 200, 'Flota reanudada');
            break;

        case 'mantenimiento':
            dbq("UPDATE buses $where SET status = 'mantenimiento'", $params);
            jsonResponse(null, 200, 'Buses enviados a mantenimiento');
            break;

        default:
            jsonError('Acción no válida. Usa reset | pausar | reanudar | mantenimiento.', 422);
    }
} catch (Exception $e) {
    logMessage('error', 'Error simulación admin', ['msg' => $e->getMessage()]);
    jsonError('Error interno', 500);
}
