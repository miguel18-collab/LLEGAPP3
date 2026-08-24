<?php
/**
 * API · GET /api/planificar.php?origen=ID&destino=ID
 * Planificador de viajes: ruta directa o con un transbordo.
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/rutas.php';

requireMethod('GET');

const VELOCIDAD_PROMEDIO = 22;   // km/h urbano
const TARIFA_BASE = 3200;        // COP
const TARIFA_TRANSBORDO = 5000;

try {
    $origenId  = (int)input('origen', 0);
    $destinoId = (int)input('destino', 0);
    if (!$origenId || !$destinoId) jsonError('Parámetros ?origen=&destino= requeridos', 422);

    $origen  = dbOne('SELECT s.*, r.route_id AS r_route FROM stops s JOIN routes r ON r.route_id = s.route_id WHERE s.id = ?', [$origenId]);
    $destino = dbOne('SELECT s.*, r.route_id AS r_route FROM stops s JOIN routes r ON r.route_id = s.route_id WHERE s.id = ?', [$destinoId]);
    if (!$origen || !$destino) jsonError('Paradas no encontradas', 404);

    avanzarFlota();

    // Rutas que pasan por cada parada
    $rutasOrigen  = array_column(dbAll('SELECT route_id FROM stops WHERE name = ?', [$origen['name']]), 'route_id');
    $rutasDestino = array_column(dbAll('SELECT route_id FROM stops WHERE name = ?', [$destino['name']]), 'route_id');

    $legs = [];
    $transbordos = 0;
    $kmTotal = 0.0;

    // ---------- OPCIÓN DIRECTA ----------
    $directas = array_intersect($rutasOrigen, $rutasDestino);
    if ($directas) {
        $routeId = array_values($directas)[0];
        $paradasRuta = obtenerParadas($routeId);
        $ordenO = (int)dbOne('SELECT stop_order FROM stops WHERE route_id = ? AND id = ?', [$routeId, $origenId])['stop_order'];
        $ordenD = (int)dbOne('SELECT stop_order FROM stops WHERE route_id = ? AND id = ?', [$routeId, $destinoId])['stop_order'];

        [$desde, $hasta, $intermedias] = tramoSegunSentido($paradasRuta, $origen['name'], $destino['name'], $ordenO, $ordenD);
        $minRuta = duracionTramo($paradasRuta, $desde, $hasta);

        // Espera estimada según flota real
        $buses = dbAll("SELECT * FROM buses WHERE route_id = ? AND status='operando'", [$routeId]);
        $espera = mejorEspera($buses, $paradasRuta, $ordenO);

        $legs[] = [
            'tipo' => 'bus', 'ruta' => $routeId,
            'ruta_nombre' => obtenerRuta($routeId)['name'],
            'color' => obtenerRuta($routeId)['color'],
            'desde' => $origen['name'], 'hasta' => $destino['name'],
            'min' => $minRuta, 'espera_min' => $espera,
            'paradas_intermedias' => $intermedias,
            'km' => distanciaTramoKm($paradasRuta, $desde, $hasta),
        ];
    } else {
        // ---------- CON TRANSBORDO ----------
        $mejorPlan = null;
        $paradasComunes = dbAll(
            'SELECT a.route_id AS r1, b.route_id AS r2, a.name
             FROM stops a JOIN stops b ON a.name = b.name
             WHERE a.route_id IN (' . implode(',', array_fill(0, max(1, count($rutasOrigen)), '?')) . ')
               AND b.route_id IN (' . implode(',', array_fill(0, max(1, count($rutasDestino)), '?')) . ')',
            [...$rutasOrigen ?: [''], ...$rutasDestino ?: ['']]
        );

        foreach ($paradasComunes as $pc) {
            $r1 = $pc['r1']; $r2 = $pc['r2'];
            $transferencia = $pc['name'];

            $p1 = obtenerParadas($r1);
            $p2 = obtenerParadas($r2);
            $oO = ordenDeParada($p1, $origen['name']);
            $oT1 = ordenDeParada($p1, $transferencia);
            $oT2 = ordenDeParada($p2, $transferencia);
            $oD = ordenDeParada($p2, $destino['name']);
            if ($oO === null || $oT1 === null || $oT2 === null || $oD === null) continue;

            [$d1a, $d1b] = tramoOrdenes($oO, $oT1, $p1);
            [$d2a, $d2b] = tramoOrdenes($oT2, $oD, $p2);

            $m1 = duracionTramo($p1, $d1a, $d1b);
            $m2 = duracionTramo($p2, $d2a, $d2b);
            if ($m1 === null || $m2 === null) continue;

            $km1 = distanciaTramoKm($p1, $d1a, $d1b);
            $km2 = distanciaTramoKm($p2, $d2a, $d2b);
            $total = $m1 + $m2 + 4; // +4 min caminar/transbordo

            if ($mejorPlan === null || $total < $mejorPlan['total']) {
                $buses1 = dbAll("SELECT * FROM buses WHERE route_id = ? AND status='operando'", [$r1]);
                $espera = mejorEspera($buses1, $p1, $oO);

                $mejorPlan = [
                    'total' => $total,
                    'legs' => [
                        [
                            'tipo' => 'bus', 'ruta' => $r1,
                            'ruta_nombre' => obtenerRuta($r1)['name'],
                            'color' => obtenerRuta($r1)['color'],
                            'desde' => $origen['name'], 'hasta' => $transferencia,
                            'min' => $m1, 'espera_min' => $espera,
                            'paradas_intermedias' => abs($oT1 - $oO) - 1, 'km' => $km1,
                        ],
                        ['tipo' => 'camine', 'desde' => "Transbordo en {$transferencia}", 'min' => 4, 'color' => '#94a69b'],
                        [
                            'tipo' => 'bus', 'ruta' => $r2,
                            'ruta_nombre' => obtenerRuta($r2)['name'],
                            'color' => obtenerRuta($r2)['color'],
                            'desde' => $transferencia, 'hasta' => $destino['name'],
                            'min' => $m2, 'espera_min' => random_int(3, (int)$p2[0]['stop_order'] ? 9 : 9),
                            'paradas_intermedias' => abs($oD - $oT2) - 1, 'km' => $km2,
                        ],
                    ],
                    'km' => $km1 + $km2 + 0.3,
                ];
            }
        }

        if ($mejorPlan === null) {
            jsonError('No existe una ruta directa ni con un transbordo entre esas paradas.', 404);
        }
        $legs = $mejorPlan['legs'];
        $kmTotal = $mejorPlan['km'];
        $transbordos = 1;
    }

    $esperaInicial = (int)($legs[0]['espera_min'] ?? 3);
    $totalMin = array_sum(array_map(fn($l) => $l['min'], $legs)) + $esperaInicial + $transbordos * 4;

    jsonResponse([
        'origen'       => $origen['name'],
        'destino'      => $destino['name'],
        'legs'         => $legs,
        'total_min'    => max(1, $totalMin),
        'transbordos'  => $transbordos,
        'km_total'     => round($kmTotal ?: array_sum(array_column($legs, 'km')), 2),
        'tarifa'       => $transbordos > 0 ? TARIFA_TRANSBORDO : TARIFA_BASE,
    ]);
} catch (Exception $e) {
    logMessage('error', 'Error en planificar.php', ['msg' => $e->getMessage()]);
    jsonError('Error calculando el viaje', 500);
}

// ================= helpers =================

function ordenDeParada(array $paradas, string $nombre): ?int {
    foreach ($paradas as $p) {
        if ($p['name'] === $nombre) return (int)$p['stop_order'];
    }
    return null;
}

/** Devuelve [nombreDesde, nombreHasta] según sentido de recorrido */
function tramoSegunSentido(array $paradas, string $nomA, string $nomB, int $ordenA, int $ordenB): array {
    return $ordenA <= $ordenB ? [$nomA, $nomB, abs($ordenB - $ordenA) - 1] : [$nomB, $nomA, abs($ordenB - $ordenA) - 1];
}

function tramoOrdenes(int $a, int $b, array $paradas): array {
    $nombres = [];
    foreach ($paradas as $p) $nombres[(int)$p['stop_order']] = $p['name'];
    return $a <= $b ? [$nombres[$a], $nombres[$b]] : [$nombres[$b], $nombres[$a]];
}

/** Duración en minutos de un tramo por nombres (ida más corta) */
function duracionTramo(array $paradas, string $desde, string $hasta): ?int {
    $idxDesde = $idxHasta = null;
    foreach ($paradas as $i => $p) {
        if ($p['name'] === $desde && $idxDesde === null) $idxDesde = $i;
        if ($p['name'] === $hasta) $idxHasta = $i;
    }
    if ($idxDesde === null || $idxHasta === null) return null;
    $minutos = 0;
    $paso = $idxDesde <= $idxHasta ? 1 : -1;
    for ($i = $idxDesde; $i !== $idxHasta; $i += $paso) {
        $sig = $i + $paso;
        if (!isset($paradas[$sig])) return null;
        $km = distanciaKm((float)$paradas[$i]['lat'], (float)$paradas[$i]['lng'], (float)$paradas[$sig]['lat'], (float)$paradas[$sig]['lng']);
        $minutos += ($km / VELOCIDAD_PROMEDIO) * 60 + BUS_DWELL_SECONDS / 60;
    }
    return max(2, (int)round($minutos));
}

function distanciaTramoKm(array $paradas, string $desde, string $hasta): float {
    $idxDesde = $idxHasta = null;
    foreach ($paradas as $i => $p) {
        if ($p['name'] === $desde && $idxDesde === null) $idxDesde = $i;
        if ($p['name'] === $hasta) $idxHasta = $i;
    }
    if ($idxDesde === null || $idxHasta === null) return 0;
    $km = 0;
    $paso = $idxDesde <= $idxHasta ? 1 : -1;
    for ($i = $idxDesde; $i !== $idxHasta; $i += $paso) {
        $sig = $i + $paso;
        if (!isset($paradas[$sig])) break;
        $km += distanciaKm((float)$paradas[$i]['lat'], (float)$paradas[$i]['lng'], (float)$paradas[$sig]['lat'], (float)$paradas[$sig]['lng']);
    }
    return round($km, 2);
}

function mejorEspera(array $buses, array $paradas, int $ordenObjetivo): int {
    $mejor = null;
    foreach ($buses as $bus) {
        $eta = etaBusHastaOrden($bus, $paradas, $ordenObjetivo);
        if ($eta !== null && ($mejor === null || $eta < $mejor)) $mejor = $eta;
    }
    return max(1, (int)ceil(($mejor ?? 300) / 60));
}
