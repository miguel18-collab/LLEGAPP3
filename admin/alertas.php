<?php
/**
 * ADMIN · Gestión de incidencias del sistema (system_alerts)
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/rutas.php';

$admin = requerirAdmin();
$titulo = 'Incidencias';
$seccionActiva = 'alertas';
$msg = null; $msgTipo = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validarCSRF()) {
    $accion = $_POST['accion'] ?? '';
    try {
        if ($accion === 'crear') {
            $routeId = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['route_id'] ?? '')) ?: null;
            dbq(
                'INSERT INTO system_alerts (route_id, severity, title, message, active, expires_at, created_by)
                 VALUES (?,?,?,?,1,?,?)',
                [
                    $routeId,
                    in_array($_POST['severity'] ?? '', ['info','media','alta','critica'], true) ? $_POST['severity'] : 'info',
                    trim($_POST['title'] ?? '') ?: 'Incidencia',
                    trim($_POST['message'] ?? ''),
                    ($_POST['expires_hours'] ?? 0) > 0 ? date('Y-m-d H:i:s', time() + ((int)$_POST['expires_hours'] * 3600)) : null,
                    $admin['id'],
                ]
            );
            // Notificar a todos los usuarios activos
            foreach (dbAll('SELECT id FROM users WHERE is_active = 1 AND role != "admin"') as $u) {
                dbq('INSERT INTO notifications (user_id, type, title, message, icon) VALUES (?, "incidencia", ?, ?, "📢")',
                    [$u['id'], trim($_POST['title'] ?? ''), trim($_POST['message'] ?? '')]);
            }
            $msg = 'Incidencia publicada y notificada a los usuarios.';
        }
        if ($accion === 'alternar') {
            dbq('UPDATE system_alerts SET active = 1 - active WHERE id = ?', [(int)$_POST['alerta_id']]);
            $msg = 'Estado actualizado.';
        }
        if ($accion === 'eliminar') {
            dbq('DELETE FROM system_alerts WHERE id = ?', [(int)$_POST['alerta_id']]);
            $msg = 'Incidencia eliminada.'; $msgTipo = 'info';
        }
    } catch (Exception $e) {
        $msg = 'Error: ' . $e->getMessage(); $msgTipo = 'error';
    }
}

$incidencias = dbAll('
    SELECT a.*, r.short_name FROM system_alerts a
    LEFT JOIN routes r ON r.route_id = a.route_id
    ORDER BY a.active DESC,
             FIELD(a.severity,"critica","alta","media","info"),
             a.created_at DESC
');
$rutas = obtenerRutas(false);

$severidades = ['critica' => ['badge-danger', '🔴 Crítica'], 'alta' => ['badge-danger', '🟠 Alta'], 'media' => ['badge-warning', '🟡 Media'], 'info' => ['badge-info', '🔵 Info']];

require __DIR__ . '/_layout.php';
?>

<div class="flex-between" style="margin-bottom:20px">
    <div>
        <h1 class="page-title">📢 Incidencias</h1>
        <p class="page-subtitle">Avisos visibles en el mapa y la app</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm" onclick="mostrarModal('modalCrear')">➕ Nueva incidencia</button>
</div>

<?php if ($msg): ?><div class="alert <?= $msgTipo === 'error' ? 'alert-danger' : ($msgTipo === 'info' ? 'alert-info' : 'alert-success') ?>"><?= e($msg) ?></div><?php endif; ?>

<div style="display:grid;gap:10px;max-width:840px">
    <?php foreach ($incidencias as $i): ?>
    <article class="card <?= !(int)$i['active'] ? '' : '' ?>" style="<?= (int)$i['active'] ? '' : 'opacity:.55' ?>">
        <div class="flex-between">
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <span class="badge <?= $severidades[$i['severity']][0] ?>"><?= $severidades[$i['severity']][1] ?></span>
                <?php if ($i['short_name']): ?><span class="chip">Ruta <?= e($i['short_name']) ?></span><?php endif; ?>
                <strong><?= e($i['title']) ?></strong>
            </div>
            <div class="flex-between" style="gap:6px">
                <form method="post" style="display:contents"><?= csrfField() ?>
                    <input type="hidden" name="alerta_id" value="<?= (int)$i['id'] ?>">
                    <button name="accion" value="alternar" class="btn btn-ghost btn-sm"><?= (int)$i['active'] ? '⏸ Desactivar' : '▶ Activar' ?></button>
                    <button name="accion" value="eliminar" class="btn btn-danger btn-sm"
                            onclick="return confirm('¿Eliminar esta incidencia?')">🗑</button>
                </form>
            </div>
        </div>
        <p style="font-size:13.5px;color:var(--text-muted);margin-top:8px"><?= e($i['message']) ?></p>
        <p style="font-size:11.5px;color:var(--text-muted);margin-top:6px">
            Publicada <?= tiempoRelativo($i['created_at']) ?>
            <?= $i['expires_at'] ? ' · expira ' . date('d/m H:i', strtotime($i['expires_at'])) : '' ?>
        </p>
    </article>
    <?php endforeach; ?>
</div>

<!-- Modal -->
<div class="modal-backdrop" id="modalCrear" hidden onclick="if(event.target===this)this.hidden=true">
    <div class="modal card">
        <strong style="font-size:16px">📢 Publicar incidencia</strong>
        <form method="post" style="margin-top:14px">
            <?= csrfField() ?>
            <input type="hidden" name="accion" value="crear">
            <div class="field">
                <label class="label">Título *</label>
                <input class="input" name="title" required placeholder="Demoras en corredor norte">
            </div>
            <div class="field">
                <label class="label">Mensaje *</label>
                <textarea class="textarea" name="message" required placeholder="Describe la situación para los pasajeros…"></textarea>
            </div>
            <div class="form-row">
                <div class="field">
                    <label class="label">Severidad</label>
                    <select class="select" name="severity">
                        <option value="info">🔵 Informativa</option>
                        <option value="media" selected>🟡 Media</option>
                        <option value="alta">🟠 Alta</option>
                        <option value="critica">🔴 Crítica</option>
                    </select>
                </div>
                <div class="field">
                    <label class="label">Ruta afectada</label>
                    <select class="select" name="route_id">
                        <option value="">Todas / general</option>
                        <?php foreach ($rutas as $r): ?>
                        <option value="<?= e($r['route_id']) ?>"><?= e($r['short_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="field">
                <label class="label">Expira en (horas, opcional)</label>
                <input type="number" class="input" name="expires_hours" min="0" max="72" value="6">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Publicar y notificar</button>
        </form>
    </div>
</div>

<style>
.modal-backdrop { position: fixed; inset: 0; background: rgba(10,15,12,.55); z-index: 1200; display: grid; place-items: center; padding: 18px; }
.modal { width: min(520px, 100%); animation: slideUp .22s ease; }
</style>
<script>function mostrarModal(id){ document.getElementById(id).hidden = false; }</script>

<?php require __DIR__ . '/_layout_end.php'; ?>
