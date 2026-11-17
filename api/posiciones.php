<?php
/**
 * API · GET /api/posiciones.php[?ruta=M101]
 * Devuelve la flota en vivo con indicador de honestidad del dato.
 *
 * Mejoras vs versión anterior:
 * - Manejo de errores explícito y respuestas JSON consistentes incluso en fallo
 * - Campo 'fuente' y 'fuente_tipo' para honestidad con el dato (en_vivo / horario_programado / simulado)
 * - Throttle de simulación respetado
 * - Headers CORS y anti-cache
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/rutas.php';

requireMethod('GET');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Avanzar simulación global (throttle interno evita carreras)
    avanzarFlota();

    $ruta = preg_replace('/[^A-Za-z0-9]/', '', input('ruta', ''));

    // Obtener buses con posición calculada
    $buses = obtenerFlotaEnVivo($ruta ?: null);

    // Obtener fuente de datos por ruta (para honestidad con el dato)
    $fuentes = [];
    $rows = dbAll('SELECT route_id, source_type, source_name FROM data_sources');
    foreach ($rows as $f) {
        $fuentes[$f['route_id']] = $f;
    }

    // Enriquecer cada bus con información de fuente
    foreach ($buses as &$bus) {
        $fuente = $fuentes[$bus['route_id']] ?? ['source_type' => 'simulado', 'source_name' => 'LLEGAPP 3 (simulación)'];
        $bus['fuente_tipo'] = $fuente['source_type'];
        $bus['fuente_nombre'] = $fuente['source_name'];
        // Marcar claramente si el dato NO es en vivo real
        $bus['es_dato_real'] = ($fuente['source_type'] === 'en_vivo');
    }
    unset($bus);

    jsonResponse([
        'success'   => true,
        'buses'     => $buses,
        'total'     => count($buses),
        'activos'   => count(array_filter($buses, fn($b) => $b['status'] === 'operando')),
        'servidor'  => date('c'),
        'fuente'    => 'sistema_llegapp3',
        'nota'      => 'Los datos son de simulación hasta que se conecte una fuente oficial de transporte.',
    ]);

} catch (Exception $e) {
    logMessage('error', 'Error en posiciones.php', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    // Respuesta JSON consistente para que el frontend no rompa
    jsonResponse([
        'success' => false,
        'buses'   => [],
        'total'   => 0,
        'error'   => 'Servicio de flota no disponible. Verifica que la base de datos esté instalada.',
    ], 503);
}
