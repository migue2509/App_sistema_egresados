<?php
// logout.php
define('BASE_DIR', __DIR__ . '/');
define('BASE_URL', ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST'].'/egresados/');
require_once BASE_DIR . 'config/funciones.php';
iniciarSesion();
registrarLog('LOGOUT');
session_unset();
session_destroy();
header('Location: ' . BASE_URL . 'index.php?msg=logout');
exit;
