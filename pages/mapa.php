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
            <button type="button" class="map-tool-btn" id="btnSeguirActivo" title="Pausar/reanudar actualización">
                <svg viewBox="0 0 24 24"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
            </button>
        </div>

        <div id="contadorFlota" class="chip" style="position:absolute;top:14px;left:14px;z-index:800;background:var(--surface)">
            <span class="live-dot"></span> <span id="txtFlota">Cargando flota…</span>
        </div>
    </section>
</div>

<?php
$pageScripts = '<script>
document.addEventListener("DOMContentLoaded", () => {
    const filtroInicial = ' . json_encode($rutaFiltro) . ';
    APP.mapa = crearMapa("mapaLeaflet", [' . MAP_CENTER_LAT . ', ' . MAP_CENTER_LNG . '], ' . MAP_DEFAULT_ZOOM . ');
    if (!APP.mapa) return;

    document.body.dataset.auth = ' . ($currentUser ? '1' : '0') . ';

    // Cargar polilínea si hay ruta filtrada
    if (filtroInicial) {
        fetch(`${APP.base}/api/rutas.php?ruta=${encodeURIComponent(filtroInicial)}&con_paradas=1`, {headers:{ "X-Requested-With":"XMLHttpRequest"}})
            .then(r => r.json())
            .then(d => { if (d.success) dibujarRuta(APP.mapa, d.data.paradas, d.data.ruta.color); });
    }

    iniciarPollingFlota({ rutaFiltro: filtroInicial, intervaloMs: 3000, onDatos: datos => {
        const activos = datos.buses.filter(b => b.status === "operando").length;
        $("#txtFlota").textContent = `${activos} buses en línea`;
    }});

    // Filtro por ruta desde sidebar
    let capaRutaActual = null;
    $$(".ruta-item").forEach(btn => btn.addEventListener("click", () => {
        const ruta = btn.dataset.ruta;
        detenerPolling();
        Object.values(APP.marcadoresBuses).forEach(m => APP.mapa.removeLayer(m));
        APP.marcadoresBuses = {};
        APP.busSeguido = null;

        if (capaRutaActual) { capaRutaActual.forEach(l => APP.mapa.removeLayer(l)); capaRutaActual = null; }

        if (ruta) {
            fetch(`${APP.base}/api/rutas.php?ruta=${encodeURIComponent(ruta)}&con_paradas=1`, {headers:{"X-Requested-With":"XMLHttpRequest"}})
                .then(r => r.json())
                .then(d => { if (d.success) capaRutaActual = dibujarRutaConCapas(APP.mapa, d.data.paradas, d.data.ruta.color); });
            history.replaceState(null, "", `?ruta=${ruta}`);
            iniciarPollingFlota({ rutaFiltro: ruta, intervaloMs: 3000 });
        } else {
            history.replaceState(null, "", location.pathname);
            APP.mapa.setView([' . MAP_CENTER_LAT . ', ' . MAP_CENTER_LNG . '], ' . MAP_DEFAULT_ZOOM . ');
            iniciarPollingFlota({ intervaloMs: 3000 });
        }
    }));

    // Búsqueda en sidebar
    $("#buscarRutaSidebar").addEventListener("input", e => {
        const q = e.target.value.toLowerCase().trim();
        $$(".ruta-item").forEach(item => {
            item.style.display = !q || (item.dataset.buscar || "todas").includes(q) ? "" : "none";
        });
    });

    // Mi ubicación
    $("#btnMiUbicacion").addEventListener("click", () => {
        if (!navigator.geolocation) return toast("Geolocalización no disponible", "", "error");
        navigator.geolocation.getCurrentPosition(pos => {
            APP.mapa.flyTo([pos.coords.latitude, pos.coords.longitude], 15);
            L.circleMarker([pos.coords.latitude, pos.coords.longitude], {radius:8, color:"#0ea5e9", fillColor:"#0ea5e9", fillOpacity:.9})
                .bindTooltip("Estás aquí").addTo(APP.mapa);
        }, () => toast("No se pudo obtener tu ubicación", "Verifica los permisos del navegador.", "error"));
    });

    // Pausar polling
    let pausado = false;
    $("#btnSeguirActivo").addEventListener("click", () => {
        pausado = !pausado;
        if (pausado) detenerPolling();
        else iniciarPollingFlota({ rutaFiltro: filtroInicial, intervaloMs: 3000 });
        toast(pausado ? "Actualización pausada" : "Actualización reanudada", "", "info", 2200);
    });

    window.seleccionarParadaMapa = async p => {
        const data = await api(`${APP.base}/api/eta.php?parada=${p.id}`);
        if (!data?.success) return;
        const filas = data.data.buses.map(b =>
            `<tr><td><strong>${b.bus_code}</strong></td><td>${b.passengers}/${b.capacity} 👥</td><td><strong style="color:${b.eta_min<=3 ? "var(--primary)" : "inherit"}">${b.eta_min} min</strong></td></tr>`
        ).join("");
        L.popup().setLatLng([p.lat, p.lng]).setContent(`
            <strong>🚏 ${p.name}</strong><br>
            <span style="color:var(--text-muted);font-size:12px">Próximos buses:</span>
            ${filas || "<em style=\"font-size:12px\">Sin buses próximos</em>"}`).openOn(APP.mapa);
    };
});

function dibujarRutaConCapas(mapa, paradas, color) {
    const capas = [];
    const puntos = paradas.map(p => [parseFloat(p.lat), parseFloat(p.lng)]);
    if (puntos.length > 1) {
        capas.push(L.polyline(puntos, {color, weight:5, opacity:.85}).addTo(mapa));
    }
    paradas.forEach((p, i) => {
        capas.push(L.circleMarker([parseFloat(p.lat), parseFloat(p.lng)], {
            radius: parseInt(p.is_terminal) === 1 ? 9 : 6, color,
            fillColor: "#fff", fillOpacity: 1, weight: 3.5
        }).bindTooltip(`${i+1}. ${p.name}`, {direction:"top"}).addTo(mapa));
        capas[capas.length-1].on("click", () => window.seleccionarParadaMapa?.(p));
    });
    mapa.fitBounds(L.latLngBounds(puntos).pad(.15));
    return capas;
}
</script>';
require dirname(__DIR__) . '/includes/footer.php';
?>
