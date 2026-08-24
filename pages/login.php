<?php
/**
 * LLEGAPP 3 · Inicio de sesión
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if (estaAutenticado()) {
    header('Location: ' . APP_BASE . '/pages/mapa.php');
    exit;
}

$error = $_SESSION['login_error'] ?? null;
$exito = $_SESSION['registro_exito'] ?? null;
unset($_SESSION['login_error'], $_SESSION['registro_exito']);

$pageTitle = 'Ingresar · ' . APP_NAME;
$dataPage = 'login';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="auth-layout">
    <div class="auth-card">
        <div class="auth-hero">
            <div style="font-size:40px;margin-bottom:8px">🚌</div>
            <h1 style="font-size:24px;letter-spacing:-.02em">Bienvenido de nuevo</h1>
            <p style="color:var(--text-muted);font-size:14px">Ingresa para seguir tu flota favorita</p>
        </div>

        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <?php if ($exito): ?><div class="alert alert-success"><?= e($exito) ?></div><?php endif; ?>

        <?php if (isset($_GET['redirect'])): ?>
            <div class="alert alert-info">Inicia sesión para continuar.</div>
        <?php endif; ?>

        <form method="post" action="<?= APP_BASE ?>/processes/login.php" class="card" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="redirect" value="<?= e($_GET['redirect'] ?? '') ?>">

            <div class="field">
                <label class="label" for="email">Correo electrónico</label>
                <input class="input" type="email" id="email" name="email" required
                       placeholder="tu@correo.com" autocomplete="email" autofocus
                       value="<?= e($_SESSION['prefill_email'] ?? '') ?>">
                <?php unset($_SESSION['prefill_email']); ?>
            </div>

            <div class="field">
                <label class="label" for="password">Contraseña</label>
                <input class="input" type="password" id="password" name="password" required
                       placeholder="••••••••" autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary btn-block">Ingresar</button>

            <p style="text-align:center;font-size:13.5px;color:var(--text-muted);margin-top:16px">
                ¿No tienes cuenta? <a href="<?= APP_BASE ?>/pages/registro.php" style="color:var(--primary);font-weight:600">Regístrate gratis</a>
            </p>
        </form>

        <div class="demo-note" style="margin-top:18px;font-size:12.5px;text-align:center">
            Demo: <code>admin@llegapp.com / Admin123!</code> · <code>demo@llegapp.com / Demo123!</code>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
