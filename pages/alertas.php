<?php
/**
 * LLEGAPP 3 · Gestión de alertas de llegada
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/rutas.php';

$currentUser = requerirAuth();

$alertas = dbAll('
    SELECT ua.*, s.name AS stop_name, r.name AS route_name, r.color, r.short_name
    FROM user_alerts ua
    JOIN stops s ON s.id = ua.stop_id
    JOIN routes r ON r.route_id = ua.route_id
    WHERE ua.user_id = ?
    ORDER BY ua.created_at DESC
', [$currentUser['id']]);

$rutas = obtenerRutas();
$alertasSistema = alertasSistemaActivas();

$pageTitle = 'Alertas · ' . APP_NAME;
$dataPage = 'alertas';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header container">
    <h1 class="page-title">🔔 Alertas de llegada</h1>
    <p class="page-subtitle">Te avisamos cuando tu bus esté cerca de tu parada</p>
</div>

<section class="section container">
    <?php if ($alertasSistema): ?>
        <div style="margin-bottom:22px">
            <strong style="font-size:15px;display:block;margin-bottom:10px">📢 Incidencias del sistema</strong>
            <?php foreach ($alertasSistema as $a): ?>
            <div class="alert <?= in_array($a['severity'], ['critica','alta']) ? 'alert-danger' : 'alert-info' ?>">
                <?= $a['route_id'] ? "<strong>[{$a['route_id']}]</strong> " : '' ?><b><?= e($a['title']) ?></b> — <?= e($a['message']) ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="grid" style="grid-template-columns:minmax(280px,360px) 1fr" id="alertasLayout">
        <!-- Crear alerta -->
        <div class="card" style="align-self:start">
            <strong style="font-size:15px">➕ Nueva alerta</strong>
            <form id="formAlerta" style="margin-top:14px">
                <div class="field">
                    <label class="label" for="selRutaAlerta">Ruta</label>
                    <select class="select" id="selRutaAlerta" required>
                        <option value="">Selecciona…</option>
                        <?php foreach ($rutas as $r): ?>
                        <option value="<?= e($r['route_id']) ?>"><?= e($r['short_name']) ?> — <?= e($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label class="label" for="selParadaAlerta">Parada donde te subes</label>
                    <select class="select" id="selParadaAlerta" required disabled>
                        <option value="">Elige una ruta primero…</option>
                    </select>
                </div>

                <div class="field">
                    <label class="label" for="selAntelacion">Avisar con antelación</label>
                    <select class="select" id="selAntelacion">
                        <option value="1">1 parada antes (~2-3 min)</option>
                        <option value="2" selected>2 paradas antes (~4-6 min)</option>
                        <option value="3">3 paradas antes (~7-9 min)</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Crear alerta</button>
            </form>
        </div>

        <!-- Lista de alertas -->
        <div>
            <?php if (!$alertas): ?>
                <div class="card list-empty">
                    <div class="emoji">🔕</div>
                    <strong>No tienes alertas configuradas</strong>
                    <p>Crea tu primera alerta eligiendo ruta y parada.</p>
                </div>
            <?php else: ?>
            <div class="grid grid-2">
                <?php foreach ($alertas as $a): ?>
                <article class="card" style="--route-color:<?= e($a['color']) ?>">
                    <div class="flex-between">
                        <span class="route-pill" style="--route-color:<?= e($a['color']) ?>">
                            <span class="route-dot" style="--route-color:<?= e($a['color']) ?>"><?= e($a['short_name']) ?></span>
                            <?= e($a['stop_name']) ?>
                        </span>
                        <span class="badge <?= (int)$a['active'] ? 'badge-success' : 'badge-neutral' ?>" id="estado-alerta-<?= (int)$a['id'] ?>">
                            <?= (int)$a['active'] ? 'Activa' : 'Pausada' ?>
                        </span>
                    </div>
                    <p style="font-size:13px;color:var(--text-muted);margin-top:8px">
                        Aviso cuando el bus esté a <b><?= (int)$a['stops_ahead'] ?></b> parada(s) de llegar.
                    </p>
                    <div class="flex-between" style="margin-top:12px">
                        <button type="button" class="btn btn-ghost btn-sm btnToggleAlerta"
                                data-id="<?= (int)$a['id'] ?>" data-activo="<?= (int)$a['active'] ?>">
                            <?= (int)$a['active'] ? '⏸ Pausar' : '▶ Activar' ?>
                        </button>
                        <button type="button" class="btn btn-danger btn-sm btnBorrarAlerta" data-id="<?= (int)$a['id'] ?>">🗑 Eliminar</button>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Cargar paradas al cambiar ruta
    $('#selRutaAlerta').addEventListener('change', async function () {
        const sel = $('#selParadaAlerta');
        if (!this.value) { sel.disabled = true; sel.innerHTML = '<option value="">Elige una ruta primero…</option>'; return; }
        sel.disabled = true;
        sel.innerHTML = '<option value="">Cargando…</option>';
        const data = await api(`${APP.base}/api/rutas.php?ruta=${encodeURIComponent(this.value)}&con_paradas=1`);
        if (!data?.success) { toast('Error', 'No se pudieron cargar las paradas.', 'error'); return; }
        sel.innerHTML = data.data.paradas.map(p =>
            `<option value="${p.id}">${p.stop_order}. ${p.name}</option>`).join('');
        sel.disabled = false;
    });

    // Crear alerta
    $('#formAlerta').addEventListener('submit', async e => {
        e.preventDefault();
        const data = await api(`${APP.base}/api/alertas.php`, {
            method: 'POST',
            body: JSON.stringify({
                route_id: $('#selRutaAlerta').value,
                stop_id: parseInt($('#selParadaAlerta').value),
                stops_ahead: parseInt($('#selAntelacion').value),
            }),
        });
        if (data?.success) {
            toast('Alerta creada', 'Te notificaremos la próxima llegada.', 'success');
            setTimeout(() => location.reload(), 900);
        }
    });

    // Pausar / activar
    $$('.btnToggleAlerta').forEach(btn => btn.addEventListener('click', async () => {
        const nuevo = btn.dataset.activo === '1' ? 0 : 1;
        const data = await api(`${APP.base}/api/alertas.php`, {
            method: 'PUT',
            body: JSON.stringify({ id: parseInt(btn.dataset.id), active: nuevo }),
        });
        if (data?.success) location.reload();
    }));

    // Eliminar
    $$('.btnBorrarAlerta').forEach(btn => btn.addEventListener('click', async () => {
        if (!confirm('¿Eliminar esta alerta?')) return;
        const data = await api(`${APP.base}/api/alertas.php?id=${btn.dataset.id}`, { method: 'DELETE' });
        if (data?.success) { toast('Alerta eliminada', '', 'info'); setTimeout(() => location.reload(), 700); }
    }));

    if (window.innerWidth <= 900) $('#alertasLayout').style.gridTemplateColumns = '1fr';
});
</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
