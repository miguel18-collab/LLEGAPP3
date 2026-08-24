<?php
/**
 * ADMIN · Dashboard principal con KPIs y gráficas
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/rutas.php';

$titulo = 'Dashboard';
$seccionActiva = 'dashboard';
require __DIR__ . '/_layout.php';

$kpis = estadisticasSistema();
$alertasActivas = (int)dbVal('SELECT COUNT(*) FROM system_alerts WHERE active = 1');
$notificacionesHoy = (int)dbVal('SELECT COUNT(*) FROM notifications WHERE DATE(created_at) = CURDATE()');
$usuariosNuevos7d = (int)dbVal('SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
$flotaPorEstado = dbAll('SELECT status, COUNT(*) AS total FROM buses GROUP BY status');

// Serie de viajes últimos 14 días
$dias = []; $viajes = [];
for ($i = 13; $i >= 0; $i--) {
    $fecha = date('Y-m-d', strtotime("-$i days"));
    $dias[] = date('d/m', strtotime($fecha));
    $viajes[] = (int)dbVal('SELECT COUNT(*) FROM trip_history WHERE DATE(created_at) = ?', [$fecha]);
}

// Popularidad de rutas
$rutasPopulares = dbAll('
    SELECT r.short_name, r.color, COUNT(h.id) AS usos,
           (SELECT COUNT(*) FROM favorites f WHERE f.item_type = "ruta" AND f.item_key = r.route_id) AS favoritos
    FROM routes r LEFT JOIN trip_history h ON h.route_id = r.route_id
    GROUP BY r.route_id, r.short_name, r.color ORDER BY usos DESC LIMIT 6
');

// Últimos usuarios registrados
$ultimosUsuarios = dbAll('SELECT name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5');

$estadosFlota = ['operando' => 0, 'detenido' => 0, 'mantenimiento' => 0, 'fuera_servicio' => 0];
foreach ($flotaPorEstado as $f) $estadosFlota[$f['status']] = (int)$f['total'];
?>

<div class="flex-between" style="margin-bottom:22px">
    <div>
        <h1 class="page-title">Panel de control</h1>
        <p class="page-subtitle">Resumen general del sistema · <?= date('d/m/Y H:i') ?></p>
    </div>
    <div style="display:flex;gap:8px">
        <button type="button" class="btn btn-ghost btn-sm" id="btnPausarFlota">⏸ Pausar flota</button>
        <button type="button" class="btn btn-primary btn-sm" id="btnResetFlota">🔄 Reiniciar simulación</button>
    </div>
</div>

<div class="grid grid-4" style="margin-bottom:20px">
    <div class="card kpi-card">
        <div class="kpi-icon" style="background:var(--primary-light)">👥</div>
        <div><div class="kpi-value"><?= number_format($kpis['usuarios']) ?></div><div class="kpi-label">Usuarios activos (+<?= $usuariosNuevos7d ?> esta semana)</div></div>
    </div>
    <div class="card kpi-card">
        <div class="kpi-icon" style="background:#dbeafe">🛣️</div>
        <div><div class="kpi-value"><?= $kpis['rutas'] ?></div><div class="kpi-label">Rutas activas</div></div>
    </div>
    <div class="card kpi-card">
        <div class="kpi-icon" style="background:#fef3c7">🚌</div>
        <div><div class="kpi-value"><?= $kpis['buses'] ?></div><div class="kpi-label">Buses en operación</div></div>
    </div>
    <div class="card kpi-card">
        <div class="kpi-icon" style="background:#fee2e2">🔔</div>
        <div><div class="kpi-value"><?= $alertasActivas ?></div><div class="kpi-label">Incidencias activas</div></div>
    </div>
</div>

<div class="grid" style="grid-template-columns: 2fr 1fr; margin-bottom:20px" id="chartsRow">
    <div class="card">
        <strong>📈 Actividad de la plataforma (14 días)</strong>
        <canvas id="chartViajes" height="110"></canvas>
    </div>
    <div class="card">
        <strong>🚦 Estado de la flota</strong>
        <canvas id="chartFlota" height="140"></canvas>
        <div style="display:flex;gap:12px;justify-content:center;font-size:12px;color:var(--text-muted);margin-top:10px;flex-wrap:wrap">
            <span>🟢 <?= $estadosFlota['operando'] ?> operando</span>
            <span>🟡 <?= $estadosFlota['detenido'] ?> detenidos</span>
            <span>🔴 <?= $estadosFlota['mantenimiento'] ?> mantenimiento</span>
        </div>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <strong style="display:block;margin-bottom:12px">🏆 Rutas más consultadas</strong>
        <?php foreach ($rutasPopulares as $i => $r): $max = max(1, $rutasPopulares[0]['usos']); ?>
        <div style="margin-bottom:11px">
            <div class="flex-between" style="font-size:13px;margin-bottom:3px">
                <span style="--route-color:<?= e($r['color']) ?>"><b><?= e($r['short_name']) ?></b> · <?= (int)$r['favoritos'] ?> ★</span>
                <span style="color:var(--text-muted)"><?= (int)$r['usos'] ?> consultas</span>
            </div>
            <div class="skeleton" style="height:8px;border-radius:99px">
                <div style="height:100%;width:<?= round(($r['usos'] / $max) * 100) ?>%;background:<?= e($r['color']) ?>;border-radius:99px"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <strong style="display:block;margin-bottom:12px">🆕 Últimos usuarios</strong>
        <?php foreach ($ultimosUsuarios as $u): ?>
        <div class="flex-between" style="padding:9px 0;border-bottom:1px dashed var(--border)">
            <div style="display:flex;gap:10px;align-items:center">
                <span class="avatar-btn" style="--av:<?= e(colorAvatar($u['email'])) ?>;width:34px;height:34px;font-size:12px"><?= e(inicialesNombre($u['name'])) ?></span>
                <div>
                    <b style="font-size:13.5px"><?= e($u['name']) ?></b>
                    <p style="font-size:12px;color:var(--text-muted)"><?= e($u['email']) ?></p>
                </div>
            </div>
            <span class="badge badge-neutral"><?= tiempoRelativo($u['created_at']) ?></span>
        </div>
        <?php endforeach; ?>
        <a href="<?= APP_BASE ?>/admin/usuarios.php" class="btn btn-ghost btn-sm btn-block" style="margin-top:12px">Gestionar usuarios →</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const css = getComputedStyle(document.body);
    const colorTexto = css.getPropertyValue('--text').trim();
    const colorBorde = css.getPropertyValue('--border').trim();

    new Chart($('#chartViajes'), {
        type: 'line',
        data: {
            labels: <?= json_encode($dias) ?>,
            datasets: [{
                label: 'Interacciones',
                data: <?= json_encode($viajes) ?>,
                borderColor: '#00b25a',
                backgroundColor: 'rgba(0,178,90,.12)',
                fill: true, tension: .38, pointRadius: 3, borderWidth: 2.5,
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: colorTexto }, grid: { display: false } },
                y: { ticks: { color: colorTexto, precision: 0 }, grid: { color: colorBorde }, beginAtZero: true }
            }
        }
    });

    new Chart($('#chartFlota'), {
        type: 'doughnut',
        data: {
            labels: ['Operando', 'Detenidos', 'Mantenimiento'],
            datasets: [{
                data: [<?= $estadosFlota['operando'] ?>, <?= $estadosFlota['detenido'] ?>, <?= $estadosFlota['mantenimiento'] ?>],
                backgroundColor: ['#00b25a', '#f59e0b', '#ef4444'],
                borderWidth: 0,
            }]
        },
        options: { cutout: '62%', plugins: { legend: { display: false } } }
    });

    async function accionFlota(accion) {
        const data = await api(`${APP.base}/api/admin/simulacion.php`, { method: 'POST', body: JSON.stringify({ accion }) });
        if (data?.success) { toast('Listo', data.message, 'success'); setTimeout(() => location.reload(), 800); }
        else toast('Error', data?.message || 'No se pudo ejecutar.', 'error');
    }

    $('#btnResetFlota').addEventListener('click', () => confirm('¿Reiniciar posiciones de toda la flota?') && accionFlota('reset'));
    $('#btnPausarFlota').addEventListener('click', function () {
        const pausar = !this.dataset.pausado;
        this.dataset.pausado = pausar;
        this.textContent = pausar ? '▶ Reanudar flota' : '⏸ Pausar flota';
        accionFlota(pausar ? 'pausar' : 'reanudar');
    });
});
</script>

<?php require __DIR__ . '/_layout_end.php'; ?>
