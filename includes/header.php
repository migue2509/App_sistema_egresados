<?php
// includes/header.php
// Uso: require_once BASE_DIR . 'includes/header.php';
// Variables esperadas: $titulo_pagina (string), $nav_activo (string)

if (!defined('BASE_DIR')) define('BASE_DIR', dirname(__DIR__) . '/');
if (!defined('BASE_URL')) {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    define('BASE_URL', $proto . '://' . $_SERVER['HTTP_HOST'] . '/egresados/');
}

$titulo_pagina = $titulo_pagina ?? 'Sistema de Egresados';
$nav_activo    = $nav_activo    ?? '';

$usuario_actual = function_exists('usuarioActual') ? usuarioActual() : [];
$rol            = $usuario_actual['rol'] ?? '';
$nombres        = $usuario_actual['nombres'] ?? ($usuario_actual['email'] ?? '');

function navLink(string $href, string $label, string $activo, string $clave): string {
    $cls = ($activo === $clave) ? ' activo' : '';
    return '<a href="' . BASE_URL . $href . '" class="' . $cls . '">' . $label . '</a>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= htmlspecialchars($titulo_pagina) ?> — I.E. Dinamarca</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>css/egresados.css">
  <link rel="icon" type="image/png" href="<?= BASE_URL ?>imagenes/escudoAnimacion-unscreen.gif">
</head>
<body>

<header class="eg-header">
  <div class="eg-header-top">
    <img src="<?= BASE_URL ?>imagenes/escudoAnimacion-unscreen.gif"
         alt="Escudo I.E. Dinamarca" class="logo"
         onerror="this.style.display='none'">
    <div class="titulo-bloque">
      <h1>I.E. Dinamarca — Sistema de Egresados</h1>
      <p>Plataforma de seguimiento a graduados · <?= date('Y') ?></p>
    </div>
  </div>

  <nav class="eg-nav">
    <?php if (!empty($usuario_actual)): ?>
      <?php if ($rol === 'egresado'): ?>
        <?= navLink('dashboard.php',        ' Inicio',      $nav_activo, 'inicio') ?>
        <?= navLink('perfil.php',           ' Mi Perfil',   $nav_activo, 'perfil') ?>
        <?= navLink('perfil.php?tab=estudio',' Estudios',   $nav_activo, 'estudios') ?>
        <?= navLink('perfil.php?tab=trabajo',' Trabajo',    $nav_activo, 'trabajo') ?>

      <?php elseif ($rol === 'comite'): ?>
        <?= navLink('comite/index.php',       ' Panel Comité',    $nav_activo, 'inicio') ?>
        <?= navLink('comite/egresados.php',   ' Egresados',       $nav_activo, 'egresados') ?>
        <?= navLink('comite/estadisticas.php',' Estadísticas',    $nav_activo, 'estadisticas') ?>

      <?php elseif ($rol === 'rectoria'): ?>
        <?= navLink('admin/index.php',        ' Panel Admin',     $nav_activo, 'inicio') ?>
        <?= navLink('admin/egresados.php',    ' Egresados',       $nav_activo, 'egresados') ?>
        <?= navLink('admin/estadisticas.php', ' Estadísticas',    $nav_activo, 'estadisticas') ?>
        <?= navLink('admin/cargar_base.php',  ' Cargar Base',     $nav_activo, 'cargar') ?>
        <?= navLink('admin/usuarios.php',     ' Usuarios',         $nav_activo, 'usuarios') ?>
      <?php endif; ?>

      <div class="nav-derecha">
        <span style="color:rgba(255,255,255,.7);font-size:.82rem;padding:8px 8px;">
           <?= htmlspecialchars($nombres) ?> 
          <small>(<?= ucfirst($rol) ?>)</small>
        </span>
        <a href="<?= BASE_URL ?>logout.php"> Salir</a>
      </div>
    <?php else: ?>
      <a href="<?= BASE_URL ?>index.php">Iniciar sesión</a>
      <a href="<?= BASE_URL ?>registro.php">Registrarse</a>
    <?php endif; ?>
  </nav>
</header>
