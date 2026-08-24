<?php
/**
 * ADMIN · Estadísticas globales con gráficas Chart.js
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$admin = requerirAdmin();
$titulo = 'Estadísticas';
$seccionActiva = 'estadisticas';

// Serie 30 días
$dias = []; $viajes = []; $usuariosNuevos = [];
for ($i = 29; $i >= 0; $i--) {
    $f = date('Y-m-d', strtotime("-$i days"));
    $dias[] = date('d/m', strtotime($f));
    $viajes[] = (int)dbVal('SELECT COUNT(*) FROM trip_history WHERE DATE(created_at)=?', [$f]);
    $usuariosNuevos[] = (int)dbVal('SELECT COUNT(*) FROM users WHERE DATE(created_at)=?', [$f]);
}

$usoRutas = dbAll('
    SELECT r.route_id, r.short_name, r.color,
           COUNT(h.id) AS interacciones,
           SUM(h.action="tracked") AS seguidos,
           SUM(h.action="planned") AS planificados
    FROM routes r LEFT JOIN trip_history h ON h.route_id = r.route_id
    GROUP BY r.route_id, r.short_name, r.color ORDER BY interacciones DESC
');

$favoritosRuta = dbAll('
    SELECT r.short_name, COUNT(f.id) AS total FROM favorites f
    JOIN routes r ON f.item_type="ruta" AND r.route_id = f.item_key
    GROUP BY r.short_name ORDER BY total DESC LIMIT 8
');

$resumen = [
    'total_interacciones' => array_sum($viajes),
    'promedio_diario' => round(array_sum($viajes) / 30, 1),
    'mejor_dia' => max($viajes),
    'alertas_creadas' => (int)dbVal('SELECT COUNT(*) FROM user_alerts'),
    'notificaciones_enviadas' => (int)dbVal('SELECT COUNT(*) FROM notifications'),
];

require __DIR__ . '/_layout.php';
?>

<div class="flex-between" style="margin-bottom:20px">
    <div>
        <h1 class="page-title">📈 Estadísticas</h1>
        <p class="page-subtitle">Métricas de uso de los últimos 30 días</p>
    </div>
</div>

<div class="grid grid-4" style="margin-bottom:20px">
    <div class="card kpi-card"><div class="kpi-icon" style="background:var(--primary-light)">📊</div><div><div class="kpi-value"><?= $resumen['total_interacciones'] ?></div><div class="kpi-label">Interacciones totales</div></div></div>
    <div class="card kpi-card"><div class="kpi-icon" style="background:#dbeafe">📅</div><div><div class="kpi-value"><?= $resumen['promedio_diario'] ?></div><div class="kpi-label">Promedio diario</div></div></div>
    <div class="card kpi-card"><div class="kpi-icon" style="background:#fef3c7">🏆</div><div><div class="kpi-value"><?= $resumen['mejor_dia'] ?></div><div class="kpi-label">Mejor día (interacciones)</div></div></div>
    <div class="card kpi-card"><div class="kpi-icon" style="background:#fee2e2">🔔</div><div><div class="kpi-value"><?= $resumen['notificaciones_enviadas'] ?></div><div class="kpi-label">Notificaciones enviadas</div></div></div>
</div>

<div class="card" style="margin-bottom:20px">
    <strong>📈 Actividad diaria (30 días)</strong>
    <canvas id="chartActividad" height="90"></canvas>
</div>

<div class="grid grid-2" style="margin-bottom:20px">
    <div class="card">
        <strong>🛣️ Interacciones por ruta</strong>
        <canvas id="chartRutas" height="170"></canvas>
    </div>
    <div class="card">
        <strong>⭐ Favoritos por ruta</strong>
        <canvas id="chartFavoritos" height="170"></canvas>
    </div>
</div>

<div class="table-wrap" style="max-width:760px">
    <table class="table">
        <thead><tr><th>Ruta</th><th>Interacciones</th><th>Buses seguidos</th><th>Viajes planificados</th></tr></thead>
        <tbody>
            <?php foreach ($usoRutas as $r): ?>
            <tr>
                <td><b><?= e($r['short_name']) ?></b></td>
                <td><?= (int)$r['interacciones'] ?></td>
                <td><?= (int)$r['seguidos'] ?></td>
                <td><?= (int)$r['planificados'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const css = getComputedStyle(document.body);
    const txt = css.getPropertyValue('--text').trim();
    const borde = css.getPropertyValue('--border').trim();
    const opts = {
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color: txt, maxRotation: 0, autoSkip: true }, grid: { display: false } },
            y: { ticks: { color: txt, precision: 0 }, grid: { color: borde }, beginAtZero: true }
        }
    };

    new Chart($('#chartActividad'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($dias) ?>,
            datasets: [
                { label:'Interacciones', data: <?= json_encode($viajes) ?>, backgroundColor:'#00b25a', borderRadius:4 },
                { label:'Usuarios', data: <?= json_encode($usuariosNuevos) ?>, backgroundColor:'#0ea5e9', borderRadius:4 },
            ]
        },
        options: { ...opts, plugins: { legend: { display:true, labels:{color:txt} } } }
    });

    new Chart($('#chartRutas'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($usoRutas, 'short_name')) ?>,
            datasets: [{ data: <?= json_encode(array_map('intval', array_column($usoRutas, 'interacciones'))) ?>,
                backgroundColor: <?= json_encode(array_column($usoRutas, 'color')) ?>, borderRadius:6 }]
        },
        options: { indexAxis:'y', ...opts }
    });

    new Chart($('#chartFavoritos'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($favoritosRuta, 'short_name') ?: ['Sin datos']) ?>,
            datasets: [{ data: <?= json_encode(array_map('intval', array_column($favoritosRuta, 'total')) ?: [1]) ?>,
                backgroundColor:['#00b25a','#ef4444','#f59e0b','#3b82f6','#8b5cf6','#14b8a6','#ec4899','#84cc16'], borderWidth:0 }]
        },
        options: { cutout:'58%', plugins:{ legend:{ position:'right', labels:{color:txt} } } }
    });
});
</script>

<?php require __DIR__ . '/_layout_end.php'; ?>
