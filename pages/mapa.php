<?php
/**
 * LLEGAPP 3 · Mapa de flota en tiempo real
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/rutas.php';

$currentUser = usuarioActual();
$rutaFiltro = preg_replace('/[^A-Za-z0-9]/', '', $_GET['ruta'] ?? '');
$rutas = obtenerRutas();
$favoritas = [];
if ($currentUser) {
    $favoritas = array_column(
        dbAll("SELECT item_key FROM favorites WHERE user_id = ? AND item_type = 'ruta'", [$currentUser['id']]),
        'item_key'
    );
}
$alertas = alertasSistemaActivas();

$pageTitle = 'Mapa en vivo · ' . APP_NAME;
$dataPage = 'mapa';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="map-layout">
    <!-- SIDEBAR -->
    <aside class="map-sidebar">
        <div style="margin-bottom:14px">
            <span class="live-indicator"><span class="live-dot"></span> FLOTA EN VIVO</span>
            <h2 style="font-size:18px;margin-top:4px">Rutas del sistema</h2>
        </div>

        <?php if ($alertas): ?>
            <?php foreach (array_slice($alertas, 0, 2) as $a): ?>
            <div class="alert <?= in_array($a['severity'], ['critica','alta']) ? 'alert-danger' : 'alert-info' ?>" style="font-size:12.5px;padding:10px 12px">
                <?= $a['route_id'] ? "<strong>[{$a['route_id']}]</strong> " : '' ?><?= e($a['title']) ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="search-box" style="margin-bottom:12px">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="search" class="input" id="buscarRutaSidebar" placeholder="Buscar ruta o parada…" autocomplete="off">
        </div>

        <div id="listaRutasSidebar">
            <button type="button" class="card card-hover ruta-item flex-between" data-ruta=""
                    style="width:100%;text-align:left;margin-bottom:10px;<?= $rutaFiltro === '' ? 'outline:2px solid var(--primary)' : '' ?>">
                <strong style="font-size:14px">🌐 Todas las rutas</strong>
                <span class="badge badge-neutral"><?= count($rutas) ?></span>
            </button>

            <?php foreach ($rutas as $r): ?>
            <button type="button"
                    class="card card-hover ruta-item"
                    data-ruta="<?= e($r['route_id']) ?>"
                    data-buscar="<?= e(mb_strtolower($r['name'] . ' ' . $r['route_id'])) ?>"
                    style="width:100%;text-align:left;margin-bottom:10px;border-left:4px solid <?= e($r['color']) ?>;
                           <?= $rutaFiltro === $r['route_id'] ? 'outline:2px solid var(--primary)' : '' ?>">
                <div class="flex-between">
                    <div>
                        <strong style="font-size:14px"><?= e($r['emoji']) ?> <?= e($r['short_name'] ?: $r['route_id']) ?></strong>
                        <p style="font-size:12px;color:var(--text-muted);max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($r['name']) ?></p>
                    </div>
                    <div style="text-align:right">
                        <span class="badge badge-success" title="Buses operando">🚌 <?= (int)$r['total_buses'] ?></span>
                        <?php if ($currentUser && in_array($r['route_id'], $favoritas)): ?>
                            <span style="color:#f59e0b;font-size:13px">★</span>
                        <?php endif; ?>
                    </div>
                </div>
            </button>
            <?php endforeach; ?>
        </div>
    </aside>

    <!-- MAPA -->
    <section class="map-canvas">
        <div id="mapaLeaflet"></div>

        <div class="map-toolbar">
            <button type="button" class="map-tool-btn" id="btnMiUbicacion" title="Centrar en mi ubicación">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg>
            </button>
            <button type="button" class="map-tool-btn" id="btnReportar" title="Reportar problema con un bus o ruta">
                <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M12 17l-5 5 10-10z"/><path d="M12 12L9 15l6-6z"/></svg>
            </button>
            <button type="button" class="map-tool-btn" id="btnSeguirActivo" title="Pausar/reanudar actualización">
                <svg viewBox="0 0 24 24"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
            </button>
        </div>

        <div id="contadorFlota" class="chip" style="position:absolute;top:14px;left:14px;z-index:800;background:var(--surface)">
            <span class="live-dot"></span> <span id="txtFlota">Cargando flota…</span>
        </div>
        <div id="indicadorFuente" class="chip" style="position:absolute;top:14px;left:170px;z-index:800;background:var(--surface);font-size:12px">
            Cargando fuente…
        </div>
    </section>
</div>

<?php
// Variables PHP → JS mediante data attributes (evita problemas de comillas en strings inline)
?>
<script>
    // Configuración del mapa desde PHP
    document.body.dataset.filtroRuta = '<?= e($rutaFiltro) ?>';
    document.body.dataset.centerLat = '<?= MAP_CENTER_LAT ?>';
    document.body.dataset.centerLng = '<?= MAP_CENTER_LNG ?>';
    document.body.dataset.defaultZoom = '<?= MAP_DEFAULT_ZOOM ?>';
    document.body.dataset.auth = '<?= $currentUser ? '1' : '0' ?>';
</script>
<script src="<?= APP_BASE ?>/assets/js/mapa.js?v=<?= APP_VERSION ?>"></script>
?>
