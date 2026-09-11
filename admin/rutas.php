<?php
/**
 * ADMIN · Gestión de rutas y paradas (CRUD)
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/rutas.php';

$admin = requerirAdmin();
$titulo = 'Rutas';
$seccionActiva = 'rutas';
$msg = null; $msgTipo = 'success';

// ---------- ACCIONES ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validarCSRF()) {
    $accion = $_POST['accion'] ?? '';
    try {
        switch ($accion) {
            case 'crear_ruta':
                $routeId = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['route_id'] ?? ''));
                if (strlen($routeId) < 2 || strlen($routeId) > 10) throw new RuntimeException('Código de ruta inválido (2-10 caracteres).');
                if (dbVal('SELECT COUNT(*) FROM routes WHERE route_id = ?', [$routeId])) throw new RuntimeException('Ya existe una ruta con ese código.');
                dbq(
                    'INSERT INTO routes (route_id, name, short_name, color, emoji, description, frequency_min, first_departure, last_departure)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $routeId,
                        trim($_POST['name'] ?? '') ?: "Ruta $routeId",
                        trim($_POST['short_name'] ?? '') ?: $routeId,
                        preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#00A651',
                        '🚌',
                        trim($_POST['description'] ?? '') ?: null,
                        max(2, min(60, (int)($_POST['frequency_min'] ?? 10))),
                        $_POST['first_departure'] ?: '05:00',
                        $_POST['last_departure'] ?: '22:30',
                    ]
                );
                $msg = "Ruta $routeId creada. Ahora añade sus paradas.";
                break;

            case 'editar_ruta':
                $routeId = preg_replace('/[^A-Za-z0-9]/', '', $_POST['route_id'] ?? '');
                dbq(
                    'UPDATE routes SET name = ?, short_name = ?, color = ?, description = ?, frequency_min = ?, active = ? WHERE route_id = ?',
                    [
                        trim($_POST['name'] ?? ''),
                        trim($_POST['short_name'] ?? '') ?: $routeId,
                        preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#00A651',
                        trim($_POST['description'] ?? '') ?: null,
                        max(2, min(60, (int)($_POST['frequency_min'] ?? 10))),
                        isset($_POST['active']) ? 1 : 0,
                        $routeId,
                    ]
                );
                $msg = 'Ruta actualizada.';
                break;

            case 'alternar_ruta':
                $routeId = preg_replace('/[^A-Za-z0-9]/', '', $_POST['route_id'] ?? '');
                dbq('UPDATE routes SET active = 1 - active WHERE route_id = ?', [$routeId]);
                $msg = 'Estado de la ruta cambiado.';
                break;

            case 'eliminar_ruta':
                dbq('DELETE FROM routes WHERE route_id = ?', [preg_replace('/[^A-Za-z0-9]/', '', $_POST['route_id'] ?? '')]);
                $msg = 'Ruta eliminada con sus paradas y buses.'; $msgTipo = 'info';
                break;

            case 'agregar_parada':
                $routeId = preg_replace('/[^A-Za-z0-9]/', '', $_POST['route_id'] ?? '');
                $siguiente = (int)dbVal('SELECT COALESCE(MAX(stop_order),0)+1 FROM stops WHERE route_id = ?', [$routeId]);
                dbq(
                    'INSERT INTO stops (route_id, name, lat, lng, stop_order, is_terminal, zone) VALUES (?,?,?,?,?,?,?)',
                    [
                        $routeId,
                        trim($_POST['stop_name'] ?? '') ?: 'Nueva parada',
                        (float)str_replace(',', '.', $_POST['lat'] ?? '6.25'),
                        (float)str_replace(',', '.', $_POST['lng'] ?? '-75.56'),
                        $siguiente,
                        isset($_POST['is_terminal']) ? 1 : 0,
                        trim($_POST['zone'] ?? '') ?: null,
                    ]
                );
                $msg = 'Parada añadida al final del recorrido.';
                break;

            case 'editar_parada':
                dbq(
                    'UPDATE stops SET name = ?, lat = ?, lng = ? WHERE id = ?',
                    [
                        trim($_POST['stop_name'] ?? ''),
                        (float)str_replace(',', '.', $_POST['lat'] ?? '6.25'),
                        (float)str_replace(',', '.', $_POST['lng'] ?? '-75.56'),
                        (int)$_POST['stop_id'],
                    ]
                );
                $msg = 'Parada actualizada.';
                break;

            case 'subir_parada':
                $stopId = (int)$_POST['stop_id'];
                $parada = dbOne('SELECT * FROM stops WHERE id = ?', [$stopId]);
                if ($parada && (int)$parada['stop_order'] > 1) {
                    $otra = dbOne('SELECT * FROM stops WHERE route_id = ? AND stop_order = ?', [$parada['route_id'], (int)$parada['stop_order'] - 1]);
                    if ($otra) {
                        db()->beginTransaction();
                        dbq('UPDATE stops SET stop_order = 9999 WHERE id = ?', [$stopId]);
                        dbq('UPDATE stops SET stop_order = ? WHERE id = ?', [(int)$parada['stop_order'], $otra['id']]);
                        dbq('UPDATE stops SET stop_order = ? WHERE id = ?', [(int)$parada['stop_order'] - 1, $stopId]);
                        db()->commit();
                    }
                }
                $msg = 'Orden actualizado.';
                break;

            case 'eliminar_parada':
                dbq('DELETE FROM stops WHERE id = ?', [(int)$_POST['stop_id']]);
                // Reordenar
                $routeId = $_POST['route_id'] ?? '';
                $restantes = dbAll('SELECT id FROM stops WHERE route_id = ? ORDER BY stop_order', [$routeId]);
                foreach ($restantes as $i => $r) dbq('UPDATE stops SET stop_order = ? WHERE id = ?', [$i + 1, $r['id']]);
                $msg = 'Parada eliminada y recorrido reordenado.';
                $msgTipo = 'info';
                break;
        }
    } catch (RuntimeException | PDOException $e) {
        $msg = 'Error: ' . $e->getMessage(); $msgTipo = 'error';
    }
}

$rutas = obtenerRutas(false);
$detalleId = preg_replace('/[^A-Za-z0-9]/', '', $_GET['ruta'] ?? '');
$detalle = $detalleId ? obtenerRuta($detalleId) : null;
$paradasDetalle = $detalle ? obtenerParadas($detalleId) : [];

require __DIR__ . '/_layout.php';
?>

<div class="flex-between" style="margin-bottom:20px">
    <div>
        <h1 class="page-title">🛣️ Rutas <?= $detalle ? '/ ' . e($detalleId) : '' ?></h1>
        <p class="page-subtitle"><?= count($rutas) ?> rutas en el sistema</p>
    </div>
    <?php if ($detalle): ?>
        <a href="<?= APP_BASE ?>/admin/rutas.php" class="btn btn-ghost btn-sm">← Volver al listado</a>
    <?php else: ?>
        <button type="button" class="btn btn-primary btn-sm" onclick="mostrarModal('modalCrearRuta')">➕ Nueva ruta</button>
    <?php endif; ?>
</div>

<?php if ($msg): ?>
<div class="alert <?= $msgTipo === 'error' ? 'alert-danger' : ($msgTipo === 'info' ? 'alert-info' : 'alert-success') ?>" style="max-width:760px"><?= e($msg) ?></div>
<?php endif; ?>

<?php if (!$detalle): ?>
    <!-- LISTADO DE RUTAS -->
    <div class="grid grid-3">
        <?php foreach ($rutas as $r): ?>
        <article class="card card-hover" style="--route-color:<?= e($r['color']) ?>">
            <div class="flex-between">
                <span class="route-dot" style="--route-color:<?= e($r['color']) ?>"><?= e($r['short_name'] ?: $r['route_id']) ?></span>
                <span class="badge <?= (int)$r['active'] ? 'badge-success' : 'badge-neutral' ?>"><?= (int)$r['active'] ? 'Activa' : 'Inactiva' ?></span>
            </div>
            <strong style="display:block;margin-top:10px;font-size:14.5px"><?= e($r['name']) ?></strong>
            <p style="font-size:12.5px;color:var(--text-muted);margin-top:4px"><?= (int)$r['total_paradas'] ?> paradas · <?= (int)$r['total_buses'] ?> buses · cada <?= (int)$r['frequency_min'] ?> min</p>
            <div style="display:flex;gap:6px;margin-top:14px;flex-wrap:wrap">
                <a href="?ruta=<?= urlencode($r['route_id']) ?>" class="btn btn-secondary btn-sm">Editar</a>
                <form method="post" style="display:contents"><?= csrfField() ?>
                    <input type="hidden" name="route_id" value="<?= e($r['route_id']) ?>">
                    <button type="submit" name="accion" value="alternar_ruta" class="btn btn-ghost btn-sm"
                            title="<?= (int)$r['active'] ? 'Desactivar' : 'Activar' ?> ruta"><?= (int)$r['active'] ? '⏸' : '▶' ?></button>
                    <button type="submit" name="accion" value="eliminar_ruta" class="btn btn-danger btn-sm"
                            onclick="return confirm('¿Eliminar la ruta <?= e($r['route_id']) ?> con TODAS sus paradas y buses?')">🗑</button>
                </form>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <!-- Modal crear ruta -->
    <div class="modal-backdrop" id="modalCrearRuta" hidden onclick="if(event.target===this)this.hidden=true">
        <div class="modal card">
            <strong style="font-size:16px">➕ Crear nueva ruta</strong>
            <form method="post" style="margin-top:14px">
                <?= csrfField() ?>
                <input type="hidden" name="accion" value="crear_ruta">
                <div class="form-row">
                    <div class="field">
                        <label class="label">Código *</label>
                        <input class="input" name="route_id" required maxlength="10" placeholder="M606">
                    </div>
                    <div class="field">
                        <label class="label">Color</label>
                        <input type="color" class="input" name="color" value="#00b25a" style="height:42px;padding:4px">
                    </div>
                </div>
                <div class="field">
                    <label class="label">Nombre completo</label>
                    <input class="input" name="name" placeholder="Terminal ↔ Destino" required>
                </div>
                <div class="field">
                    <label class="label">Descripción</label>
                    <textarea class="textarea" name="description" placeholder="Zonas que cubre…"></textarea>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label class="label">Frecuencia (min)</label>
                        <input type="number" class="input" name="frequency_min" value="10" min="2" max="60">
                    </div>
                    <div class="field">
                        <label class="label">Horario</label>
                        <div style="display:flex;gap:6px;align-items:center">
                            <input type="time" class="input" name="first_departure" value="05:00">
                            <input type="time" class="input" name="last_departure" value="22:30">
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Crear ruta</button>
            </form>
        </div>
    </div>

<?php else: ?>
    <!-- DETALLE / EDICIÓN DE RUTA -->
    <div class="grid" style="grid-template-columns:minmax(280px,400px) 1fr" id="adminRutasGrid">
        <!-- Formulario de la ruta -->
        <div class="card" style="align-self:start">
            <form method="post">
                <?= csrfField() ?>
                <input type="hidden" name="accion" value="editar_ruta">
                <input type="hidden" name="route_id" value="<?= e($detalleId) ?>">
                <div class="flex-between" style="margin-bottom:12px">
                    <strong>✏️ Datos de la ruta</strong>
                    <input type="color" name="color" value="<?= e($detalle['color']) ?>"
                           style="width:44px;height:34px;border:none;background:none;cursor:pointer">
                </div>
                <div class="field">
                    <label class="label">Nombre</label>
                    <input class="input" name="name" value="<?= e($detalle['name']) ?>" required>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label class="label">Nombre corto</label>
                        <input class="input" name="short_name" value="<?= e($detalle['short_name'] ?? $detalleId) ?>" maxlength="20">
                    </div>
                    <div class="field">
                        <label class="label">Frecuencia (min)</label>
                        <input type="number" class="input" name="frequency_min" value="<?= (int)$detalle['frequency_min'] ?>" min="2" max="60">
                    </div>
                </div>
                <div class="field">
                    <label class="label">Descripción</label>
                    <textarea class="textarea" name="description"><?= e($detalle['description']) ?></textarea>
                </div>
                <label class="flex-between" style="font-size:14px;margin-bottom:16px;cursor:pointer">
                    <span>Ruta activa</span>
                    <input type="checkbox" name="active" value="1" <?= (int)$detalle['active'] ? 'checked' : '' ?>
                           style="width:20px;height:20px;accent-color:var(--primary)">
                </label>
                <button type="submit" class="btn btn-primary btn-block">Guardar cambios</button>
            </form>
        </div>

        <!-- Paradas -->
        <div class="card">
            <strong style="display:block;margin-bottom:12px">🚏 Paradas (<?= count($paradasDetalle) ?>)</strong>
            <ul class="stop-list">
                <?php foreach ($paradasDetalle as $i => $p): ?>
                <li class="stop-item" style="--route-color:<?= e($detalle['color']) ?>">
                    <span class="stop-num"><?= $i + 1 ?></span>
                    <form method="post" style="flex:1;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                        <?= csrfField() ?>
                        <input type="hidden" name="accion" value="editar_parada">
                        <input type="hidden" name="stop_id" value="<?= (int)$p['id'] ?>">
                        <input type="hidden" name="route_id" value="<?= e($detalleId) ?>">
                        <input class="input" name="stop_name" value="<?= e($p['name']) ?>" style="flex:2;min-width:140px;padding:7px 11px">
                        <input class="input" name="lat" value="<?= e($p['lat']) ?>" style="width:105px;padding:7px 9px;font-size:12.5px" required>
                        <input class="input" name="lng" value="<?= e($p['lng']) ?>" style="width:110px;padding:7px 9px;font-size:12.5px" required>
                        <button type="submit" class="btn btn-ghost btn-sm" title="Guardar">💾</button>
                    </form>
                    <div style="display:flex;gap:5px">
                        <?php if ($i > 0): ?>
                        <form method="post" style="display:contents"><?= csrfField() ?>
                            <input type="hidden" name="accion" value="subir_parada">
                            <input type="hidden" name="stop_id" value="<?= (int)$p['id'] ?>">
                            <button type="submit" class="icon-btn" title="Subir" style="width:32px;height:32px">↑</button>
                        </form>
                        <?php endif; ?>
                        <form method="post" style="display:contents"><?= csrfField() ?>
                            <input type="hidden" name="accion" value="eliminar_parada">
                            <input type="hidden" name="stop_id" value="<?= (int)$p['id'] ?>">
                            <input type="hidden" name="route_id" value="<?= e($detalleId) ?>">
                            <button type="submit" class="icon-btn" title="Eliminar" style="width:32px;height:32px;color:var(--danger)">🗑</button>
                        </form>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>

            <hr>
            <form method="post" class="flex-between" style="gap:8px;flex-wrap:wrap">
                <?= csrfField() ?>
                <input type="hidden" name="accion" value="agregar_parada">
                <input type="hidden" name="route_id" value="<?= e($detalleId) ?>">
                <input class="input" name="stop_name" placeholder="Nombre de la parada" required style="flex:2;min-width:160px">
                <input class="input" name="lat" placeholder="Latitud" value="6.250000" required pattern="-?\d+(\.\d+)?" style="width:115px">
                <input class="input" name="lng" placeholder="Longitud" value="-75.580000" required pattern="-?\d+(\.\d+)?" style="width:118px">
                <button type="submit" class="btn btn-primary btn-sm">➕ Añadir</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Modal crear ruta -->
<div class="modal-backdrop" id="modalCrearRuta" hidden onclick="if(event.target===this)cerrarModal(this.id)">
    <div class="modal card">
        <div class="flex-between" style="margin-bottom:12px">
            <strong style="font-size:16px">➕ Crear nueva ruta</strong>
            <button type="button" class="icon-btn" onclick="cerrarModal('modalCrearRuta')" aria-label="Cerrar" style="width:32px;height:32px">✕</button>
        </div>
        <form method="post" style="margin-top:14px">
            <?= csrfField() ?>
            <input type="hidden" name="accion" value="crear_ruta">
            <div class="form-row">
                <div class="field">
                    <label class="label">Código *</label>
                    <input class="input" name="route_id" required maxlength="10" placeholder="M606">
                </div>
                <div class="field">
                    <label class="label">Color</label>
                    <input type="color" class="input" name="color" value="#00b25a" style="height:42px;padding:4px">
                </div>
            </div>
            <div class="field">
                <label class="label">Nombre completo</label>
                <input class="input" name="name" placeholder="Terminal ↔ Destino" required>
            </div>
            <div class="field">
                <label class="label">Descripción</label>
                <textarea class="textarea" name="description" placeholder="Zonas que cubre…"></textarea>
            </div>
            <div class="form-row">
                <div class="field">
                    <label class="label">Frecuencia (min)</label>
                    <input type="number" class="input" name="frequency_min" value="10" min="2" max="60">
                </div>
                <div class="field">
                    <label class="label">Horario</label>
                    <div style="display:flex;gap:6px;align-items:center">
                        <input type="time" class="input" name="first_departure" value="05:00">
                        <input type="time" class="input" name="last_departure" value="22:30">
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Crear ruta</button>
        </form>
    </div>
</div>

<style>
.modal-backdrop { position: fixed; inset: 0; background: rgba(10,15,12,.55); backdrop-filter: blur(3px); z-index: 1200; display: grid; place-items: center; padding: 18px; }
.modal-backdrop[hidden] { display: none !important; }
.modal { width: min(520px, 100%); max-height: 88vh; overflow-y: auto; animation: slideUp .22s ease; }
@media (max-width:900px){ #adminRutasGrid { grid-template-columns: 1fr !important; } }
</style>
<script>
function mostrarModal(id){ const el = document.getElementById(id); if (el) el.hidden = false; }
function cerrarModal(id){ const el = document.getElementById(id); if (el) el.hidden = true; }
document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.modal-backdrop:not([hidden])').forEach(m => m.hidden = true); });
</script>

<?php require __DIR__ . '/_layout_end.php'; ?>
