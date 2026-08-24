<?php
/**
 * LLEGAPP 3 - Lógica de negocio: rutas, paradas, buses y cálculo de ETAs
 */

require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/simulacion.php';

// ============================================================
// RUTAS
// ============================================================

/**
 * Lista de rutas activas con contadores
 */
function obtenerRutas(bool $soloActivas = true): array {
    $where = $soloActivas ? 'WHERE r.active = 1' : '';
    return dbAll("
        SELECT r.*,
               (SELECT COUNT(*) FROM stops s WHERE s.route_id = r.route_id) AS total_paradas,
               (SELECT COUNT(*) FROM buses b WHERE b.route_id = r.route_id AND b.status = 'operando') AS total_buses
        FROM routes r $where
        ORDER BY r.route_id
    ");
}

function obtenerRuta(string $routeId): ?array {
    return dbOne('
        SELECT r.*,
               (SELECT COUNT(*) FROM stops s WHERE s.route_id = r.route_id) AS total_paradas,
               (SELECT COUNT(*) FROM buses b WHERE b.route_id = r.route_id AND b.status = "operando") AS total_buses
        FROM routes r WHERE r.route_id = ?
    ', [$routeId]);
}

/**
 * Paradas de una ruta en orden (con posición previa/siguiente para polyline)
 */
function obtenerParadas(string $routeId): array {
    return dbAll(
        'SELECT * FROM stops WHERE route_id = ? ORDER BY stop_order',
        [$routeId]
    );
}

/**
 * Polyline de la ruta [[lat,lng], ...]
 */
function polylineRuta(string $routeId): array {
    return array_map(
        fn($p) => [(float)$p['lat'], (float)$p['lng']],
        obtenerParadas($routeId)
    );
}

// ============================================================
// BUSES EN TIEMPO REAL
// ============================================================

/**
 * Estado completo de la flota con posiciones calculadas.
 * Avanza la simulación antes de leer (tick throttled).
 */
function obtenerFlotaEnVivo(?string $routeId = null): array {
    avanzarFlota();

    $sql = "
        SELECT b.id, b.code, b.route_id, b.stop_index, b.progress, b.speed_kmh,
               b.direction, b.passengers, b.capacity, b.status, b.plate,
               b.last_update, r.name AS route_name, r.color AS route_color
        FROM buses b
        JOIN routes r ON r.route_id = b.route_id AND r.active = 1
    ";
    $params = [];
    if ($routeId) {
        $sql .= ' WHERE b.route_id = ?';
        $params[] = $routeId;
    }
    $buses = dbAll($sql, $params);

    // Cargar paradas por ruta involucrada
    $paradasPorRuta = [];
    foreach ($buses as $bus) {
        $paradasPorRuta[$bus['route_id']] ??= obtenerParadas($bus['route_id']);
    }

    $resultado = [];
    foreach ($buses as $bus) {
        $resultado[] = calcularEstadoBus($bus, $paradasPorRuta[$bus['route_id']]);
    }
    return $resultado;
}

/**
 * Convierte una fila de bus en estado calculado (posición, próxima parada, ETA)
 */
function calcularEstadoBus(array $bus, array $paradas): array {
    $total = count($paradas);
    if ($total < 2) return $bus;

    $dir = (int)$bus['direction'];
    $idx = max(1, min((int)$bus['stop_index'], $total)); // parada destino (1-based)

    // Parada de origen del tramo según el sentido
    $destinoIdx = ($dir === 1) ? $idx - 1 : $idx - 1;
    $origenIdx  = ($dir === 1) ? $idx - 2 : $idx;

    $origenIdx  = max(0, min($total - 1, $origenIdx));
    $destinoIdx = max(0, min($total - 1, $destinoIdx));
    if ($origenIdx === $destinoIdx) {
        $origenIdx = ($dir === 1) ? max(0, $destinoIdx - 1) : min($total - 1, $destinoIdx + 1);
    }

    $actual  = $paradas[$origenIdx];
    $destino = $paradas[$destinoIdx];

    $pos = interpolar(
        (float)$actual['lat'], (float)$actual['lng'],
        (float)$destino['lat'], (float)$destino['lng'],
        (float)$bus['progress']
    );

    return [
        'id'            => $bus['id'],
        'code'          => $bus['code'],
        'plate'         => $bus['plate'] ?? '',
        'route_id'      => $bus['route_id'],
        'route_name'    => $bus['route_name'] ?? '',
        'color'         => $bus['route_color'] ?? '#00A651',
        'status'        => $bus['status'],
        'direction'     => $dir,
        'speed_kmh'     => (int)$bus['speed_kmh'],
        'passengers'    => (int)$bus['passengers'],
        'capacity'      => (int)$bus['capacity'],
        'lat'           => $pos['lat'],
        'lng'           => $pos['lng'],
        'progress'      => round((float)$bus['progress'], 4),
        'next_stop'     => $destino['name'],
        'next_stop_id'  => (int)$destino['id'],
        'eta_next_min'  => max(1, (int)ceil(etaSegundosEntre($actual, $destino, $bus) / 60)),
        'last_update'   => $bus['last_update'],
        '_total_stops'  => $total,
    ];
}

/**
 * Segundos estimados para completar el tramo actual según velocidad real
 */
function etaSegundosEntre(array $origen, array $destino, array $bus): float {
    $km = distanciaKm((float)$origen['lat'], (float)$origen['lng'], (float)$destino['lat'], (float)$destino['lng']);
    $kmh = max(8, (int)$bus['speed_kmh']); // velocidad mínima operativa
    return ($km / $kmh) * 3600 * (1.0 - min(0.99, (float)$bus['progress']));
}

/**
 * Próximos buses que llegan a una parada (ETA global)
 */
function proximosBusesParada(int $stopId, int $limite = 5): array {
    $stop = dbOne('SELECT * FROM stops WHERE id = ?', [$stopId]);
    if (!$stop) return [];

    avanzarFlota();

    $buses = dbAll('SELECT * FROM buses WHERE route_id = ? AND status = "operando"', [$stop['route_id']]);
    $paradas = obtenerParadas($stop['route_id']);
    $ordenObjetivo = (int)$stop['stop_order'];
    $resultados = [];

    foreach ($buses as $bus) {
        $segundos = etaBusHastaOrden($bus, $paradas, $ordenObjetivo);
        if ($segundos !== null) {
            $resultados[] = [
                'bus_code'   => $bus['code'],
                'plate'      => $bus['plate'],
                'passengers' => (int)$bus['passengers'],
                'capacity'   => (int)$bus['capacity'],
                'eta_min'    => max(1, (int)ceil($segundos / 60)),
                'eta_seg'    => $segundos,
            ];
        }
    }

    usort($resultados, fn($a, $b) => $a['eta_seg'] <=> $b['eta_seg']);
    return array_slice($resultados, 0, $limite);
}

/**
 * ETA en segundos desde un bus hasta la parada con stop_order objetivo (respeta sentido)
 */
function etaBusHastaOrden(array $bus, array $paradas, int $ordenObjetivo): ?float {
    $total = count($paradas);
    $dir = (int)$bus['direction'];
    $actualIdx = ($dir === 1) ? (int)$bus['stop_index'] - 1 : (int)$bus['stop_index'] + 1; // parada actual (0-based)
    $actualIdx = max(0, min($actualIdx, $total - 1));

    $objetivo = $ordenObjetivo - 1; // 0-based
    if ($objetivo === $actualIdx) return 0.0; // está llegando/está aquí

    $segundos = 0.0;
    $i = $actualIdx;
    $guardia = 0;

    while ($guardia++ < $total * 2) {
        $siguienteIdx = $i + $dir;
        if ($siguienteIdx < 0 || $siguienteIdx >= $total) return null; // no pasa por ahí en este sentido

        $tramo = etaSegundosEntre($paradas[$i], $paradas[$siguienteIdx], $bus);
        if ($i === $actualIdx) $tramo += BUS_DWELL_SECONDS * 0.2;
        else $tramo += BUS_DWELL_SECONDS;

        $segundos += $tramo;

        if ($siguienteIdx === $objetivo) return $segundos;
        $i = $siguienteIdx;
    }
    return null;
}

// ============================================================
// PARADAS CERCANAS Y ESTADÍSTICAS PÚBLICAS
// ============================================================

function paradasCercanas(float $lat, float $lng, int $limite = 6): array {
    $todas = dbAll('SELECT s.*, r.name AS route_name, r.color FROM stops s JOIN routes r ON r.route_id = s.route_id WHERE r.active = 1');
    usort($todas, function ($a, $b) use ($lat, $lng) {
        return distanciaKm($lat, $lng, (float)$a['lat'], (float)$a['lng'])
             <=> distanciaKm($lat, $lng, (float)$b['lat'], (float)$b['lng']);
    });
    return array_slice($todas, 0, $limite);
}

function estadisticasSistema(): array {
    return [
        'rutas'       => (int)dbVal('SELECT COUNT(*) FROM routes WHERE active = 1', [], 0),
        'paradas'     => (int)dbVal('SELECT COUNT(*) FROM stops', [], 0),
        'buses'       => (int)dbVal("SELECT COUNT(*) FROM buses WHERE status = 'operando'", [], 0),
        'pasajeros'   => (int)dbVal("SELECT COALESCE(SUM(passengers),0) FROM buses WHERE status = 'operando'", [], 0),
        'usuarios'    => (int)dbVal('SELECT COUNT(*) FROM users WHERE is_active = 1', [], 0),
        'viajes_hoy'  => (int)dbVal("SELECT COUNT(*) FROM trip_history WHERE DATE(created_at) = CURDATE()", [], 0),
    ];
}

/**
 * Alertas del sistema activas (incidencias publicadas por admin)
 */
function alertasSistemaActivas(): array {
    return dbAll("
        SELECT a.*, r.name AS route_name, r.color
        FROM system_alerts a LEFT JOIN routes r ON r.route_id = a.route_id
        WHERE a.active = 1 AND (a.expires_at IS NULL OR a.expires_at > NOW())
        ORDER BY FIELD(a.severity,'critica','alta','media','info'), a.created_at DESC
        LIMIT 10
    ");
}
