<?php
/**
 * ADMIN · Gestión de la flota de buses
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/rutas.php';

$admin = requerirAdmin();
$titulo = 'Flota';
$seccionActiva = 'buses';
$msg = null; $msgTipo = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validarCSRF()) {
    $accion = $_POST['accion'] ?? '';
    try {
        switch ($accion) {
            case 'crear_bus':
                $routeId = preg_replace('/[^A-Za-z0-9]/', '', $_POST['route_id'] ?? '');
                $code = strtoupper(trim($_POST['code'] ?? ''));
                if (!$routeId || !obtenerRuta($routeId)) throw new RuntimeException('Ruta inválida.');
                if (strlen($code) < 3) throw new RuntimeException('Código del bus muy corto.');
                if (dbVal('SELECT COUNT(*) FROM buses WHERE code = ?', [$code])) throw new RuntimeException('Ya existe un bus con ese código.');
                dbq(
                    'INSERT INTO buses (id, code, plate, route_id, stop_index, progress, speed_kmh, direction, passengers, capacity, status)
                     VALUES (?,?,?,?,1,0,?,1,0,?,?)',
                    [
                        uniqid('bus-'), $code,
                        trim($_POST['plate'] ?? '') ?: null,
                        $routeId,
                        max(15, min(60, (int)($_POST['speed_kmh'] ?? 28))),
                        max(10, min(120, (int)($_POST['capacity'] ?? 60))),
                        in_array($_POST['status'] ?? '', ['operando', 'detenido', 'mantenimiento'], true) ? $_POST['status'] : 'operando',
                    ]
                );
                $msg = "Bus $code añadido a la flota.";
                break;

            case 'editar_bus':
                dbq(
                    'UPDATE buses SET plate = ?, speed_kmh = ?, capacity = ?, status = ? WHERE id = ?',
                    [
                        trim($_POST['plate'] ?? '') ?: null,
                        max(10, min(90, (int)($_POST['speed_kmh'] ?? 28))),
                        max(10, min(120, (int)($_POST['capacity'] ?? 60))),
                        in_array($_POST['status'] ?? '', ['operando', 'detenido', 'mantenimiento', 'fuera_servicio'], true) ? $_POST['status'] : 'operando',
                        $_POST['bus_id'] ?? '',
                    ]
                );
                $msg = 'Bus actualizado.';
                break;

            case 'eliminar_bus':
                dbq('DELETE FROM buses WHERE id = ?', [$_POST['bus_id'] ?? '']);
                $msg = 'Bus retirado de la flota.'; $msgTipo = 'info';
                break;
        }
    } catch (RuntimeException | PDOException $e) {
        $msg = 'Error: ' . $e->getMessage(); $msgTipo = 'error';
    }
}

$filtroRuta = preg_replace('/[^A-Za-z0-9]/', '', $_GET['ruta'] ?? '');
$rutas = obtenerRutas(false);

$sql = 'SELECT b.*, r.short_name, r.color, r.name AS route_name FROM buses b JOIN routes r ON r.route_id = b.route_id';
$params = [];
if ($filtroRuta) { $sql .= ' WHERE b.route_id = ?'; $params[] = $filtroRuta; }
$sql .= ' ORDER BY r.route_id, b.code';
$buses = dbAll($sql, $params);

// Estado en vivo
avanzarFlota();

require __DIR__ . '/_layout.php';
?>

<div class="flex-between" style="margin-bottom:20px">
    <div>
        <h1 class="page-title">🚌 Flota</h1>
        <p class="page-subtitle"><?= count($buses) ?> buses registrados<?= $filtroRuta ? " en ruta $filtroRuta" : '' ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <select class="select" style="max-width:190px" onchange="if(this.value)location='?ruta='+this.value;else location='?'">
            <option value="">Todas las rutas</option>
            <?php foreach ($rutas as $r): ?>
            <option value="<?= e($r['route_id']) ?>" <?= $filtroRuta === $r['route_id'] ? 'selected' : '' ?>><?= e($r['short_name'] ?: $r['route_id']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="btn btn-primary btn-sm" onclick="mostrarModal('modalCrearBus')">➕ Añadir bus</button>
    </div>
</div>

<?php if ($msg): ?><div class="alert <?= $msgTipo === 'error' ? 'alert-danger' : ($msgTipo === 'info' ? 'alert-info' : 'alert-success') ?>"><?= e($msg) ?></div><?php endif; ?>

<div class="table-wrap">
    <table class="table">
        <thead><tr>
            <th>Código</th><th>Ruta</th><th>Placa</th><th>Ocupación</th><th>Velocidad</th><th>Estado</th><th style="text-align:right">Acciones</th>
        </tr></thead>
        <tbody>
        <?php foreach ($buses as $b): ?>
            <tr>
                <td><b><?= e($b['code']) ?></b></td>
                <td><span class="badge badge-neutral" style="--c:<?= e($b['color']) ?>;border-left:3px solid <?= e($b['color']) ?>"><?= e($b['short_name']) ?></span></td>
                <td style="font-family:monospace;font-size:13px"><?= e($b['plate'] ?? '—') ?></td>
                <td style="min-width:120px">
                    <div class="flex-between" style="font-size:12px;margin-bottom:2px"><span><?= (int)$b['passengers'] ?>/<?= (int)$b['capacity'] ?></span></div>
                    <div class="skeleton" style="height:6px;border-radius:99px">
                        <div style="height:100%;width:<?= round(((int)$b['passengers'] / max(1, (int)$b['capacity'])) * 100) ?>%;
                            background:<?= ((int)$b['passengers'] / max(1, (int)$b['capacity'])) > .85 ? '#ef4444' : '#00b25a' ?>;border-radius:99px"></div>
                    </div>
                </td>
                <td><?= (int)$b['speed_kmh'] ?> km/h</td>
                <td>
                    <?php $estados = ['operando' => ['🟢 Operando', 'badge-success'], 'detenido' => ['🟡 Detenido', 'badge-warning'], 'mantenimiento' => ['🔴 Mantenimiento', 'badge-danger'], 'fuera_servicio' => ['⚫ Fuera', 'badge-neutral']]; ?>
                    <span class="badge <?= $estados[$b['status']][1] ?>"><?= $estados[$b['status']][0] ?></span>
                </td>
                <td>
                    <form method="post" style="display:flex;gap:5px;justify-content:flex-end;align-items:center;flex-wrap:wrap">
                        <?= csrfField() ?>
                        <input type="hidden" name="bus_id" value="<?= e($b['id']) ?>">
                        <input type="hidden" name="accion" value="editar_bus">
                        <input class="input" name="plate" value="<?= e($b['plate'] ?? '') ?>" placeholder="Placa" style="width:92px;padding:5px 8px;font-size:12.5px;font-family:monospace">
                        <input type="number" class="input" name="speed_kmh" value="<?= (int)$b['speed_kmh'] ?>" min="10" max="90" style="width:64px;padding:5px 7px;font-size:12.5px">
                        <input type="number" class="input" name="capacity" value="<?= (int)$b['capacity'] ?>" min="10" max="120" style="width:64px;padding:5px 7px;font-size:12.5px">
                        <select class="select" name="status" style="width:auto;padding:5px 8px;font-size:12.5px">
                            <?php foreach (['operando','detenido','mantenimiento','fuera_servicio'] as $s): ?>
                            <option value="<?= $s ?>" <?= $b['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-ghost btn-sm">💾</button>
                    </form>
                    <form method="post" style="display:contents"><?= csrfField() ?>
                        <input type="hidden" name="bus_id" value="<?= e($b['id']) ?>">
                        <button type="submit" name="accion" value="eliminar_bus" class="btn btn-danger btn-sm"
                                onclick="return confirm('¿Retirar el bus <?= e($b['code']) ?>?')">🗑</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal crear bus -->
<div class="modal-backdrop" id="modalCrearBus" hidden onclick="if(event.target===this)this.hidden=true">
    <div class="modal card">
        <strong style="font-size:16px">➕ Añadir bus a la flota</strong>
        <form method="post" style="margin-top:14px">
            <?= csrfField() ?>
            <input type="hidden" name="accion" value="crear_bus">
            <div class="field">
                <label class="label">Código *</label>
                <input class="input" name="code" required placeholder="M101-05" maxlength="20">
            </div>
            <div class="field">
                <label class="label">Ruta *</label>
                <select class="select" name="route_id" required>
                    <?php foreach ($rutas as $r): ?>
                    <option value="<?= e($r['route_id']) ?>"><?= e($r['short_name'] ?: $r['route_id']) ?> — <?= e($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="field">
                    <label class="label">Placa</label>
                    <input class="input" name="plate" placeholder="ABC-123" maxlength="12">
                </div>
                <div class="field">
                    <label class="label">Capacidad</label>
                    <input type="number" class="input" name="capacity" value="60" min="10" max="120">
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Añadir a la flota</button>
        </form>
    </div>
</div>

<style>
.modal-backdrop { position: fixed; inset: 0; background: rgba(10,15,12,.55); z-index: 1200; display: grid; place-items: center; padding: 18px; }
.modal { width: min(500px, 100%); animation: slideUp .22s ease; }
</style>
<script>
function mostrarModal(id){ document.getElementById(id).hidden = false; }
</script>

<?php require __DIR__ . '/_layout_end.php'; ?>
