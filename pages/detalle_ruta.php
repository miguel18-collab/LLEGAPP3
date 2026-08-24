<?php
/**
 * LLEGAPP 3 · Detalle de ruta: recorrido, paradas y flota en vivo
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/rutas.php';

$routeId = preg_replace('/[^A-Za-z0-9]/', '', $_GET['ruta'] ?? '');
$ruta = $routeId ? obtenerRuta($routeId) : null;
if (!$ruta || !(int)$ruta['active']) {
    abortar(404, 'La ruta solicitada no existe o está inactiva.');
}

$paradas = obtenerParadas($routeId);
$favorita = false;
$currentUser = usuarioActual();
if ($currentUser) {
    $favorita = (bool)dbVal(
        "SELECT COUNT(*) FROM favorites WHERE user_id = ? AND item_type = 'ruta' AND item_key = ?",
        [$currentUser['id'], $routeId]
    );
    // Registrar vista en historial
    dbq('INSERT INTO trip_history (user_id, action, route_id) VALUES (?, "viewed", ?)', [$currentUser['id'], $routeId]);
}

// Distancia total del recorrido
$distanciaTotal = 0;
for ($i = 1; $i < count($paradas); $i++) {
    $distanciaTotal += distanciaKm((float)$paradas[$i-1]['lat'], (float)$paradas[$i-1]['lng'], (float)$paradas[$i]['lat'], (float)$paradas[$i]['lng']);
}
$duracionEstimada = (int)(($distanciaTotal / 22) * 60 + count($paradas) * (BUS_DWELL_SECONDS / 60));

$pageTitle = 'Ruta ' . ($ruta['short_name'] ?: $routeId) . ' · ' . APP_NAME;
$dataPage = 'detalle_ruta';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header container">
    <div class="flex-between" style="align-items:flex-start">
        <div style="display:flex;gap:14px;align-items:center">
            <span class="route-dot" style="--route-color:<?= e($ruta['color']) ?>;width:52px;height:52px;font-size:13px"><?= e($ruta['short_name'] ?: $routeId) ?></span>
            <div>
                <h1 class="page-title"><?= e($ruta['emoji']) ?> <?= e($ruta['name']) ?></h1>
                <p class="page-subtitle"><?= e($ruta['description']) ?></p>
            </div>
        </div>
        <?php if ($currentUser): ?>
        <button type="button" class="btn <?= $favorita ? 'btn-secondary' : 'btn-ghost' ?> btn-sm btnFavorito"
                data-tipo="ruta" data-clave="<?= e($routeId) ?>">
            <?= $favorita ? '★ En favoritos' : '☆ Añadir a favoritos' ?>
        </button>
        <?php endif; ?>
    </div>
</div>

<style>
.detalle-grid { display:grid; grid-template-columns: minmax(300px, 420px) 1fr; gap:20px; align-items:start; }
@media (max-width:900px){ .detalle-grid { grid-template-columns: 1fr; } }
</style>

<section class="section container detalle-grid">
    <!-- Panel de información -->
    <div>
        <div class="card" style="margin-bottom:16px">
            <strong style="font-size:15px">📊 Información del servicio</strong>
            <div class="grid grid-2" style="gap:12px;margin-top:14px">
                <div><small style="color:var(--text-muted)">Distancia ida</small><br><b><?= formatoDistancia($distanciaTotal) ?></b></div>
                <div><small style="color:var(--text-muted)">Duración estimada</small><br><b>~<?= formatoDuracion($duracionEstimada) ?></b></div>
                <div><small style="color:var(--text-muted)">Frecuencia</small><br><b>Cada <?= (int)$ruta['frequency_min'] ?> min</b></div>
                <div><small style="color:var(--text-muted)">Horario</small><br><b><?= date('g:i a', strtotime($ruta['first_departure'])) ?> – <?= date('g:i a', strtotime($ruta['last_departure'])) ?></b></div>
                <div><small style="color:var(--text-muted)">Flota asignada</small><br><b><?= (int)$ruta['total_buses'] ?> buses</b></div>
                <div><small style="color:var(--text-muted)">Estado</small><br><b style="color:var(--primary)">● Operativa</b></div>
            </div>
            <a href="<?= APP_BASE ?>/pages/planificador.php?origen=<?= urlencode($paradas[0]['id'] ?? '') ?>" class="btn btn-primary btn-block" style="margin-top:16px">🧭 Planificar viaje en esta ruta</a>
        </div>

        <div class="card">
            <div class="flex-between" style="margin-bottom:10px">
                <strong style="font-size:15px">🚏 Recorrido (<?= count($paradas) ?> paradas)</strong>
                <span class="live-indicator"><span class="live-dot"></span> EN VIVO</span>
            </div>
            <ul class="stop-list">
                <?php foreach ($paradas as $i => $p): ?>
                <li class="stop-item" style="--route-color:<?= e($ruta['color']) ?>">
                    <span class="stop-num"><?= $i + 1 ?></span>
                    <div style="flex:1">
                        <div class="stop-name flex-between">
                            <span><?= e($p['name']) ?></span>
                            <?php if ((int)$p['is_terminal']): ?><span class="badge badge-info">Terminal</span><?php endif; ?>
                        </div>
                        <div class="stop-meta">Zona <?= e($p['zone'] ?? '—') ?></div>
                    </div>
                    <div style="text-align:right;font-size:11.5px;color:var(--text-muted)" id="eta-parada-<?= (int)$p['id'] ?>">
                        —
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Mapa -->
    <div class="card" style="padding:0;overflow:hidden;position:relative;height:min(620px, 70vh)">
        <div id="mapaLeaflet" style="position:absolute;inset:0"></div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.body.dataset.auth = '<?= $currentUser ? "1" : "0" ?>';

    APP.mapa = crearMapa('mapaLeaflet');
    const paradas = <?= json_encode(array_map(fn($p) => [
        'id' => (int)$p['id'], 'name' => $p['name'],
        'lat' => (float)$p['lat'], 'lng' => (float)$p['lng'],
        'is_terminal' => (int)$p['is_terminal'],
    ], $paradas)) ?>;
    dibujarRuta(APP.mapa, paradas, '<?= e($ruta['color']) ?>');

    window.seleccionarParadaMapa = async p => {
        const data = await api(`${APP.base}/api/eta.php?parada=${p.id}`);
        if (!data?.success) return;
        const filas = data.data.buses.map(b =>
            `<tr><td><strong>${b.bus_code}</strong></td><td>${b.eta_min} min</td></tr>`).join('');
        L.popup().setLatLng([p.lat, p.lng]).setContent(
            `<strong>🚏 ${p.name}</strong>${filas ? '<table style="margin-top:6px;width:100%"><tr><td>Bus</td><td>Llega en</td></tr>' + filas + '</table>' : '<br><em>Sin buses próximos</em>'}`
        ).openOn(APP.mapa);
    };

    iniciarPollingFlota({ rutaFiltro: '<?= e($routeId) ?>', intervaloMs: 3000, onDatos: datos => {
        // Actualizar mini-ETAs por parada
        fetch(`${APP.base}/api/etas_ruta.php?ruta=<?= urlencode($routeId) ?>`, {headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r => r.json()).then(d => {
                if (!d.success) return;
                Object.entries(d.data).forEach(([stopId, eta]) => {
                    const el = document.getElementById(`eta-parada-${stopId}`);
                    if (el) el.innerHTML = `<b style="color:${eta <= 3 ? 'var(--primary)' : 'inherit'}">${eta} min</b>`;
                });
            });
    }});

    registrarHistorial('viewed', '<?= e($routeId) ?>');

    $('.btnFavorito')?.addEventListener('click', function () {
        alternarFavorito(this.dataset.tipo, this.dataset.clave, this);
        this.classList.toggle('btn-secondary'); this.classList.toggle('btn-ghost');
        this.textContent = this.textContent.includes('Añadir') ? '★ En favoritos' : '☆ Añadir a favoritos';
    });
});
</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
