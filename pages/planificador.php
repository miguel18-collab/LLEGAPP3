<?php
/**
 * LLEGAPP 3 · Planificador de viajes A → B
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/rutas.php';

$currentUser = usuarioActual();

// Todas las paradas con su ruta para los selectores
$paradas = dbAll('
    SELECT s.id, s.name, s.route_id, r.name AS route_name, r.color,
           CONCAT(s.name, " · ", r.short_name) AS etiqueta
    FROM stops s JOIN routes r ON r.route_id = s.route_id
    WHERE r.active = 1 ORDER BY s.name, s.stop_order
');

// Deduplicar por nombre para el selector (mantiene primera ruta)
$vistas = [];
$opciones = [];
foreach ($paradas as $p) {
    if (isset($vistas[$p['name']])) continue;
    $vistas[$p['name']] = true;
    $opciones[] = $p;
}

$origenSel = (int)($_GET['origen'] ?? 0);
$pageTitle = 'Planificador · ' . APP_NAME;
$dataPage = 'planificador';
require dirname(__DIR__) . '/includes/header.php';

if ($currentUser) {
    dbq('INSERT INTO trip_history (user_id, action) VALUES (?, "planned")', [$currentUser['id']]);
}
?>

<div class="page-header container">
    <h1 class="page-title">🧭 Planifica tu viaje</h1>
    <p class="page-subtitle">Elige origen y destino: calculamos ruta, transbordos y tiempos</p>
</div>

<section class="section container">
    <div class="grid" style="grid-template-columns: minmax(280px,380px) 1fr; align-items:start" id="planLayout">
        <!-- Formulario -->
        <div class="card">
            <form id="formPlan" autocomplete="off">
                <div class="field">
                    <label class="label" for="selOrigen">📍 Origen</label>
                    <select class="select" id="selOrigen" required>
                        <option value="">Selecciona una parada…</option>
                        <?php foreach ($opciones as $o): ?>
                            <option value="<?= (int)$o['id'] ?>" <?= $origenSel === (int)$o['id'] ? 'selected' : '' ?>>
                                <?= e($o['etiqueta']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="text-align:center;margin:-6px 0 -4px">
                    <button type="button" class="icon-btn" id="btnIntercambiar" title="Intercambiar origen y destino"
                            style="border:1.5px solid var(--border);background:var(--surface)">
                        <svg viewBox="0 0 24 24"><path d="M7 16V4M7 4L3 8M7 4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
                    </button>
                </div>

                <div class="field">
                    <label class="label" for="selDestino">🏁 Destino</label>
                    <select class="select" id="selDestino" required>
                        <option value="">Selecciona una parada…</option>
                        <?php foreach ($opciones as $o): ?>
                        <option value="<?= (int)$o['id'] ?>"><?= e($o['etiqueta']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Calcular mi ruta</button>
            </form>

            <hr>
            <p style="font-size:12px;color:var(--text-muted)">
                💡 El planificador considera recorridos directos y con un transbordo,
                usando la posición actual de la flota para estimar esperas.
            </p>
        </div>

        <!-- Resultado -->
        <div id="resultadoPlan">
            <div class="card list-empty" id="placeholderPlan">
                <div class="emoji">🗺️</div>
                <strong>Tu plan aparecerá aquí</strong>
                <p>Selecciona origen y destino para comenzar.</p>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.body.dataset.auth = '<?= $currentUser ? "1" : "0" ?>';

    $('#btnIntercambiar').addEventListener('click', () => {
        const a = $('#selOrigen'), b = $('#selDestino');
        [a.value, b.value] = [b.value, a.value];
    });

    $('#formPlan').addEventListener('submit', async e => {
        e.preventDefault();
        const origen = $('#selOrigen').value;
        const destino = $('#selDestino').value;

        if (!origen || !destino) return toast('Faltan datos', 'Selecciona origen y destino.', 'error');
        if (origen === destino) return toast('Mismo punto', 'Origen y destino no pueden ser iguales.', 'error');

        const cont = $('#resultadoPlan');
        cont.innerHTML = '<div class="card"><div class="skeleton" style="height:18px;width:40%;margin-bottom:14px"></div><div class="skeleton" style="height:120px"></div></div>';

        const data = await api(`${APP.base}/api/planificar.php?origen=${origen}&destino=${destino}`);
        if (!data?.success) {
            cont.innerHTML = '<div class="card list-empty"><div class="emoji">😕</div><strong>No encontramos ruta</strong><p>' + (data?.message || 'Intenta con otras paradas.') + '</p></div>';
            return;
        }

        renderizarPlan(data.data);
    });

    function renderizarPlan(plan) {
        const legsHtml = plan.legs.map((leg, i) => `
            <div class="plan-leg" style="--leg-color:${leg.color}">
                <div class="plan-line">
                    <span class="plan-dot"></span>
                    ${i < plan.legs.length - 1 ? '<span class="plan-stick"></span>' : ''}
                </div>
                <div class="plan-body">
                    ${leg.tipo === 'camine' ? `
                        <span class="chip">🚶 Camina ~${leg.min} min</span>
                        <p style="font-size:13.5px;color:var(--text-muted);margin-top:6px">${leg.desde}</p>
                    ` : `
                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                            <span class="plan-route-badge">${leg.ruta}</span>
                            <strong>${leg.desde} → ${leg.hasta}</strong>
                            <span class="badge badge-neutral">~${leg.min} min</span>
                            <span class="badge badge-neutral">🚌 sale en ~${leg.espera_min} min</span>
                        </div>
                        <p style="font-size:12.5px;color:var(--text-muted);margin-top:5px">${leg.paradas_intermedias} paradas intermedias · ${formatoDist(leg.km)}</p>
                    `}
                </div>
            </div>`).join('');

        $('#resultadoPlan').innerHTML = `
            <div class="card">
                <div class="summary-strip" style="margin-bottom:20px">
                    <div><div class="val">${plan.total_min}</div><div class="lbl">minutos totales</div></div>
                    <div><div class="val">${plan.transbordos}</div><div class="lbl">transbordo(s)</div></div>
                    <div><div class="val">${formatoDist(plan.km_total)}</div><div class="lbl">distancia</div></div>
                    <div><div class="val">$${plan.tarifa.toLocaleString('es-CO')}</div><div class="lbl">tarifa estimada</div></div>
                </div>
                <h3 style="font-size:15px;margin-bottom:16px">Tu itinerario</h3>
                ${legsHtml}
                <p style="font-size:11.5px;color:var(--text-muted);margin-top:10px">⏱️ Cálculo basado en la posición en vivo de la flota (${new Date().toLocaleTimeString('es-CO')}). Los tiempos son aproximados.</p>
            </div>`;
    }

    function formatoDist(km) { return km < 1 ? Math.round(km * 1000) + ' m' : km.toFixed(1).replace('.0', '') + ' km'; }
});
</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
