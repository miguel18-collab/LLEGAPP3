<?php
/**
 * LLEGAPP 3 · Instalador
 * Crea la base de datos, tablas, vistas y datos de demostración.
 * Uso: abre http://localhost/LLEGAPP3/database/setup.php (o ejecuta vía CLI).
 */

require_once dirname(__DIR__) . '/config/seguridad.php';

$root = 'mysql:host=127.0.0.1;charset=utf8mb4';
$log = [];
$errores = [];

try {
    $pdo = new PDO($root, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $log[] = '✓ Conexión a MySQL establecida';

    // 1) Crear BD
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `llegapp3` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `llegapp3`');
    $log[] = '✓ Base de datos `llegapp3` verificada';

    // 2) Ejecutar schema.sql (sin vistas todavía)
    $schemaSql = file_get_contents(__DIR__ . '/schema.sql');
    ejecutarBloques($pdo, quitarVistasYDelimitadores(quitarComentarios($schemaSql)));
    $log[] = '✓ Esquema aplicado (tablas + claves foráneas)';

    // 3) Crear vistas manualmente (evita problemas con DELIMITER)
    $pdo->exec("CREATE OR REPLACE VIEW `v_rutas_resumen` AS
        SELECT r.route_id, r.name, r.color, r.emoji, r.active, r.frequency_min,
               COUNT(s.id) AS total_paradas
        FROM routes r LEFT JOIN stops s ON s.route_id = r.route_id
        GROUP BY r.route_id, r.name, r.color, r.emoji, r.active, r.frequency_min");
    $pdo->exec("CREATE OR REPLACE VIEW `v_flota_estado` AS
        SELECT b.id, b.code, b.plate, b.route_id, r.name AS route_name, r.color,
               b.stop_index, b.progress, b.speed_kmh, b.direction, b.passengers, b.capacity,
               b.status, b.last_update
        FROM buses b JOIN routes r ON r.route_id = b.route_id");
    $log[] = '✓ Vistas creadas';

    // 4) Seed si está vacío
    $hayRutas = (int)$pdo->query('SELECT COUNT(*) FROM routes')->fetchColumn();
    if ($hayRutas === 0) {
        ejecutarBloques($pdo, quitarUsuarios(quitarComentarios(file_get_contents(__DIR__ . '/seed.sql'))));

        // Usuarios con hash real generado en runtime
        $adminId = generarUUID();
        $demoId  = generarUUID();
        $stmtU = $pdo->prepare('INSERT IGNORE INTO users (id,name,email,password_hash,role,avatar_color,email_verified)
                                VALUES (?,?,?,?,?,?,1)');
        $stmtU->execute([$adminId, 'Administrador LlegAPP', 'admin@llegapp.com', password_hash('Admin123!', PASSWORD_BCRYPT), 'admin', '#00b25a']);
        $stmtU->execute([$demoId, 'Usuario Demostración', 'demo@llegapp.com', password_hash('Demo123!', PASSWORD_BCRYPT), 'usuario', '#3B82F6']);

        $pdo->prepare('INSERT IGNORE INTO user_preferences (user_id) VALUES (?)')
            ->execute([$demoId]);

        $pdo->prepare('INSERT INTO notifications (user_id,type,title,message,icon) VALUES (?,?,?,?,?)')
            ->execute([$demoId, 'sistema', '¡Bienvenido a LlegAPP!', 'Explora el mapa en tiempo real, guarda tus rutas favoritas y crea alertas de llegada.', '🎉']);

        $pdo->prepare('INSERT INTO system_alerts (route_id,severity,title,message,active,created_by)
                       SELECT "M301","media","Demoras en corredor sur","Tránsito lento por obras en la Avenida Las Vegas. Frecuencia ampliada a 12 minutos.",1,?
                       WHERE NOT EXISTS (SELECT 1 FROM system_alerts)')
            ->execute([$adminId]);

        $log[] = '✓ Datos de demostración insertados (6 rutas, 36 paradas)';
    } else {
        $log[] = '• La base ya contiene datos: seed omitido';
    }

    // 5) Flota inicial
    $hayBuses = (int)$pdo->query('SELECT COUNT(*) FROM buses')->fetchColumn();
    if ($hayBuses === 0) {
        $rutas = $pdo->query('SELECT route_id FROM routes WHERE active = 1')->fetchAll(PDO::FETCH_COLUMN);
        $stmtParadas = $pdo->prepare('SELECT MAX(stop_order) AS m FROM stops WHERE route_id = ?');
        $stmtBus = $pdo->prepare('INSERT IGNORE INTO buses (id, code, plate, route_id, stop_index, progress, speed_kmh, direction, passengers, capacity, status)
                                  VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $coloresPlaca = ['ABC','BDR','CFT','GHL','JKM'];
        foreach ($rutas as $ri => $ruta) {
            $stmtParadas->execute([$ruta]);
            $maxOrden = max(2, (int)$stmtParadas->fetchColumn());
            for ($i = 0; $i < 4; $i++) {
                $placa = sprintf('%s-%03d', $coloresPlaca[array_rand($coloresPlaca)], random_int(100, 999)) . 'L' . ($ri % 9);
                $stmtBus->execute([
                    "bus-{$ruta}-{$i}",
                    "{$ruta}-" . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT),
                    $placa,
                    $ruta,
                    random_int(1, max(2, $maxOrden - 1)),
                    round(random_int(0, 90) / 100, 2),
                    random_int(22, 42),
                    random_int(0, 1) ? 1 : -1,
                    random_int(5, 45),
                    random_int(50, 80),
                    'operando',
                ]);
            }
        }
        $log[] = '✓ Flota simulada creada (' . count($rutas) * 4 . ' buses operativos)';
    } else {
        $log[] = '• La flota ya existe: omitido';
    }

    // 6) Settings
    $pdo->exec("INSERT IGNORE INTO settings (clave, valor) VALUES ('fleet_last_tick', '" . time() . "')");
    $log[] = '✓ Configuración del sistema lista';

    $tablas = array_column($pdo->query('SHOW TABLES')->fetchAll(), 'TABLES_IN_llegapp3');
    $resumen = [
        'rutas'     => (int)$pdo->query('SELECT COUNT(*) FROM routes')->fetchColumn(),
        'paradas'   => (int)$pdo->query('SELECT COUNT(*) FROM stops')->fetchColumn(),
        'buses'     => (int)$pdo->query('SELECT COUNT(*) FROM buses')->fetchColumn(),
        'usuarios'  => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    ];
} catch (Exception $e) {
    $errores[] = $e->getMessage();
}

// ============================ helpers =====================================

function quitarComentarios(string $sql): string {
    $lineas = [];
    foreach (explode("\n", $sql) as $linea) {
        if (preg_match('/^\s*--/', $linea)) continue;
        $lineas[] = $linea;
    }
    return implode("\n", $lineas);
}

function quitarVistasYDelimitadores(string $sql): string {
    // El instalador crea las vistas por separado: elimina sus CREATE VIEW del script
    $sql = preg_replace('/CREATE\s+OR\s+REPLACE\s+VIEW[^;]+;/is', '', $sql);
    return $sql;
}

function quitarUsuarios(string $sql): string {
    // Los usuarios/notificaciones/alertas los maneja el instalador con hashes reales
    $sql = preg_replace('/SET @\w+ = UUID\(\);/i', '', $sql);
    $sql = preg_replace('/INSERT IGNORE INTO `users`.*?;/is', '', $sql);
    $sql = preg_replace('/INSERT IGNORE INTO `user_preferences`.+?;/is', '', $sql);
    $sql = preg_replace('/INSERT INTO `notifications`.+?;/is', '', $sql);
    $sql = preg_replace('/INSERT INTO `system_alerts`.+?;/is', '', $sql);
    return $sql;
}

function ejecutarBloques(PDO $pdo, string $sql): void {
    foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $bloque) {
        $bloque = trim(preg_replace('/^;/', '', $bloque));
        if ($bloque === '' || $bloque === ';') continue;
        try {
            $pdo->exec($bloque);
        } catch (PDOException $e) {
            // Ignorar errores idempotentes menores
            if (!str_contains($e->getMessage(), 'already exists')) throw $e;
        }
    }
}

function generarUUID(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

$ok = empty($errores);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Instalador · LlegAPP 3</title>
<style>
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f3f6f4;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.card{background:#fff;border-radius:18px;box-shadow:0 10px 40px rgba(0,0,0,.08);padding:38px;width:min(560px,92vw)}
h1{margin:0 0 6px;font-size:26px;color:#111}h1 b{color:#00b25a}
p.sub{color:#667;margin:0 0 22px}
ul{list-style:none;padding:0;margin:0 0 20px}
li{padding:7px 0;border-bottom:1px dashed #eee;font-size:14px;color:#333}
.err{background:#fef2f2;color:#b91c1c;padding:12px 16px;border-radius:10px;font-size:14px}
.ok-badge{display:inline-block;background:#dcfce7;color:#166534;padding:4px 10px;border-radius:99px;font-size:13px;font-weight:600}
.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:18px}
.stat{background:#f8faf9;border:1px solid #e6ebe8;border-radius:12px;text-align:center;padding:12px 4px}
.stat b{font-size:20px;color:#00b25a;display:block}.stat span{font-size:11px;color:#889}
a.btn{display:inline-block;background:#00b25a;color:#fff;text-decoration:none;padding:11px 22px;border-radius:10px;font-weight:600}
code{background:#f4f4f5;padding:2px 7px;border-radius:6px;font-size:13px}
</style>
</head>
<body>
<div class="card">
    <h1>Lleg<b>APP 3</b> · Instalador <span class="ok-badge"><?= $ok ? 'COMPLETADO' : 'CON ERRORES' ?></span></h1>
    <p class="sub">Base de datos <code>llegapp3</code></p>

    <?php if ($ok): ?>
        <div class="grid">
            <div class="stat"><b><?= $resumen['rutas'] ?></b><span>Rutas</span></div>
            <div class="stat"><b><?= $resumen['paradas'] ?></b><span>Paradas</span></div>
            <div class="stat"><b><?= $resumen['buses'] ?></b><span>Buses</span></div>
            <div class="stat"><b><?= $resumen['usuarios'] ?></b><span>Usuarios</span></div>
        </div>
    <?php endif; ?>

    <ul>
        <?php foreach ($log as $l): ?><li><?= htmlspecialchars($l) ?></li><?php endforeach; ?>
    </ul>

    <?php foreach ($errores as $er): ?>
        <div class="err"><strong>Error:</strong> <?= htmlspecialchars($er) ?></div>
    <?php endforeach; ?>

    <?php if ($ok): ?>
        <p style="font-size:13px;color:#556;margin-bottom:16px">
            Accesos demo:<br><code>admin@llegapp.com / Admin123!</code> · <code>demo@llegapp.com / Demo123!</code>
        </p>
        <a class="btn" href="../index.php">Abrir LlegAPP →</a>
    <?php endif; ?>
</div>
</body>
</html>
