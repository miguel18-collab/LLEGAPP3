<?php
/**
 * LLEGAPP 3 - Motor de simulación de flota en tiempo real (server-side)
 *
 * La posición se calcula en el servidor según el tiempo transcurrido
 * (velocidad constante entre paradas), por lo que NO requiere cron:
 * cualquier cliente que consulte la API dispara el "tick" global,
 * con throttle para evitar carreras entre peticiones concurrentes.
 */

require_once __DIR__ . '/funciones.php';

/**
 * Avanza toda la flota operativa hasta "ahora".
 */
function avanzarFlota(): void {
    $pdo = db();

    // Throttle global: un tick cada FLEET_TICK_MIN_SECONDS como máximo
    $last = dbVal("SELECT valor FROM settings WHERE clave = 'fleet_last_tick'");
    if ($last !== null && (time() - (int)$last) < FLEET_TICK_MIN_SECONDS) {
        return;
    }
    dbq("UPDATE settings SET valor = ? WHERE clave = 'fleet_last_tick'", [time()]);

    $buses = $pdo->query("
        SELECT b.*, r.active AS route_active
        FROM buses b JOIN routes r ON r.route_id = b.route_id
        WHERE b.status = 'operando' AND r.active = 1
    ")->fetchAll();

    if (!$buses) return;

    // Cargar paradas agrupadas por ruta
    $paradasPorRuta = [];
    $stmtParadas = $pdo->query('SELECT * FROM stops ORDER BY stop_order');
    foreach ($stmtParadas as $p) {
        $paradasPorRuta[$p['route_id']][] = $p;
    }

    $updates = [];

    foreach ($buses as $bus) {
        $paradas = $paradasPorRuta[$bus['route_id']] ?? [];
        if (count($paradas) < 2) continue;

        $dt = min(SIM_MAX_CATCHUP_SECONDS, max(0, time() - strtotime($bus['last_update'])));
        if ($dt <= 0) continue;

        $dir      = (int)$bus['direction'];
        $stopIdx  = (int)$bus['stop_index'];       // parada destino actual (1-based)
        $progress = (float)$bus['progress'];
        $total    = count($paradas);

        $kmh = max(8, (int)$bus['speed_kmh']);
        $guardia = 0;

        while ($dt > 0 && $guardia++ < 200) {
            $origenIdx  = ($dir === 1) ? $stopIdx - 1 : $stopIdx + 1;   // parada de origen (0-based)
            $destinoIdx = $stopIdx - 1;                                  // parada destino (0-based)

            if ($origenIdx < 0 || $origenIdx >= $total || $destinoIdx < 0 || $destinoIdx >= $total) {
                // Corrección de estado inconsistente
                $stopIdx  = ($dir === 1) ? 2 : $total - 1;
                $progress = 0.0;
                continue;
            }

            $o = $paradas[$origenIdx];
            $d = $paradas[$destinoIdx];

            $kmTramo = max(0.05, distanciaKm((float)$o['lat'], (float)$o['lng'], (float)$d['lat'], (float)$d['lng']));
            $segTramo = ($kmTramo / $kmh) * 3600 + BUS_DWELL_SECONDS; // incluye permanencia en parada

            $restanteTramo = $segTramo * (1.0 - $progress);

            if ($dt >= $restanteTramo) {
                // El bus llega a la parada destino dentro de este tick
                $dt -= $restanteTramo;
                $progress = 0.0;

                $nuevoIdx = $stopIdx + $dir;
                if ($nuevoIdx > $total) {           // llegó al final del recorrido ida → regresa
                    $dir = -1;
                    $nuevoIdx = $total - 1;
                } elseif ($nuevoIdx < 1) {          // llegó al inicio vuelta → vuelve a salir
                    $dir = 1;
                    $nuevoIdx = 2;
                }
                $stopIdx = $nuevoIdx;

                // Variación realista de pasajeros al arribar a parada
                $cap = max(10, (int)$bus['capacity']);
                $cambio = random_int(-12, 15);
                $pass = max(0, min($cap, (int)$bus['passengers'] + $cambio));
                $updates[] = [$bus['id'], $stopIdx, $progress, $dir, $pass];
                $bus['passengers'] = $pass;
            } else {
                // Avanza parcialmente hacia la parada destino
                $progress += $dt / $segTramo;
                $progress = min(0.9999, $progress);
                $dt = 0;
                $updates[] = [$bus['id'], $stopIdx, $progress, $dir, (int)$bus['passengers']];
            }
        }

        if ($guardia >= 200) {
            $updates[] = [$bus['id'], $stopIdx, min(0.99, $progress), $dir, (int)$bus['passengers']];
        }
    }

    // Persistir cambios en batch
    if ($updates) {
        $stmt = $pdo->prepare('UPDATE buses SET stop_index = ?, progress = ?, direction = ?, passengers = ?, last_update = NOW() WHERE id = ?');
        foreach ($updates as $u) {
            $stmt->execute([$u[1], $u[2], $u[3], $u[4], $u[0]]);
        }
    }

    generarAlertasProximidad();
}

/**
 * Genera notificaciones cuando un bus está por llegar a una parada vigilada
 */
function generarAlertasProximidad(): void {
    $alertas = dbAll('
        SELECT ua.*, s.name AS stop_name, s.stop_order, s.route_id AS alert_route
        FROM user_alerts ua
        JOIN stops s ON s.id = ua.stop_id
        JOIN users u ON u.id = ua.user_id AND u.is_active = 1
        JOIN user_preferences up ON up.user_id = ua.user_id AND up.notifications_enabled = 1
        WHERE ua.active = 1
    ');
    if (!$alertas) return;

    foreach ($alertas as $alerta) {
        $paradas = obtenerParadasCache($alerta['alert_route']);
        $buses = dbAll("SELECT * FROM buses WHERE route_id = ? AND status = 'operando'", [$alerta['alert_route']]);

        foreach ($buses as $bus) {
            $eta = etaBusHastaOrden($bus, $paradas, (int)$alerta['stop_order']);
            if ($eta === null || $eta > ((int)$alerta['stops_ahead'] * 150)) continue;

            // Evitar duplicados: misma parada+bus no notificada hace < 5 min
            $existe = dbVal(
                'SELECT COUNT(*) FROM notifications
                 WHERE user_id = ? AND meta LIKE ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)',
                [$alerta['user_id'], '%' . $bus['code'] . '|' . $alerta['stop_name'] . '%']
            );
            if ($existe) continue;

            $min = max(1, (int)ceil($eta / 60));
            dbq(
                'INSERT INTO notifications (user_id, type, title, message, icon, link_url, meta)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $alerta['user_id'],
                    'bus_cerca',
                    'Tu bus está por llegar',
                    "El bus {$bus['code']} llega a {$alerta['stop_name']} en ~{$min} min",
                    '🚌',
                    APP_BASE . '/pages/detalle_ruta.php?ruta=' . urlencode($alerta['alert_route']),
                    json_encode(['bus' => $bus['code'], 'stop' => $alerta['stop_name']], JSON_UNESCAPED_UNICODE),
                ]
            );
        }
    }
}

/** Cache interno de paradas por ruta durante la petición (autocontenido) */
function obtenerParadasCache(string $routeId): array {
    static $cache = [];
    if (!isset($cache[$routeId])) {
        $cache[$routeId] = dbAll('SELECT * FROM stops WHERE route_id = ? ORDER BY stop_order', [$routeId]);
    }
    return $cache[$routeId];
}
