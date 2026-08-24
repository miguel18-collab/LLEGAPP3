<?php
/**
 * LLEGAPP 3 · Cerrar sesión
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validarCSRF()) {
    cerrarSesion();
}

header('Location: ' . APP_BASE . '/index.php');
exit;
