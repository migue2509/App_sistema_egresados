<?php
define('BASE_DIR', __DIR__ . '/');
define('BASE_URL', ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST'].'/egresados/');
require_once BASE_DIR . 'config/funciones.php';
iniciarSesion();
requiereLogin('egresado');

$db  = getDB();
$uid = (int)$_SESSION['usuario_id'];

$stmt = $db->prepare("
    SELECT u.*, u.estado,
           p.foto, p.genero, p.telefono, p.ciudad, p.logros,
           eb.nombres, eb.apellidos, eb.anno_graduacion, eb.fecha_nacimiento
    FROM usuarios u
    LEFT JOIN perfiles p       ON p.usuario_id = u.id
    LEFT JOIN egresados_base eb ON eb.documento = u.documento
    WHERE u.id = ?
");
$stmt->execute([$uid]);
$user = $stmt->fetch();

$estudios = $db->prepare("SELECT * FROM estudios WHERE usuario_id = ? ORDER BY anno_inicio DESC LIMIT 3");
$estudios->execute([$uid]); $estudios = $estudios->fetchAll();

$trabajos = $db->prepare("SELECT * FROM trabajos WHERE usuario_id = ? ORDER BY actualmente DESC, anno_inicio DESC LIMIT 3");
$trabajos->execute([$uid]); $trabajos = $trabajos->fetchAll();

// Calcular % completitud
$campos = ['foto','genero','telefono','ciudad'];
$llenos = 0;
foreach ($campos as $c) { if (!empty($user[$c])) $llenos++; }
if (!empty($estudios)) $llenos++;
if (!empty($trabajos)) $llenos++;
$pct = round(($llenos / 6) * 100);

$titulo_pagina = 'Mi Panel';
$nav_activo    = 'inicio';
require_once BASE_DIR . 'includes/header.php';
?>
<main class="eg-main ancho">
  <?= getFlash() ?>

  <!-- Bienvenida -->
  <div class="card mb-3">
    <div class="perfil-cabecera">
      <?php if (!empty($user['foto'])): ?>
        <img src="<?= BASE_URL ?>uploads/fotos/<?= limpiar($user['foto']) ?>" alt="Foto" class="foto-perfil">
      <?php else: ?>
        <div class="foto-placeholder"><?= strtoupper(substr($user['nombres'] ?? 'E', 0, 1)) ?></div>
      <?php endif; ?>
      <div class="info-nombre">
        <h2>¡Bienvenido/a, <?= limpiar($user['nombres'] ?? 'Egresado') ?>!</h2>
        <p> Promoción <?= (int)$user['anno_graduacion'] ?> &nbsp;|&nbsp;  <?= limpiar($user['email']) ?></p>
        <span class="badge badge-<?= $user['estado'] ?>"><?= ucfirst($user['estado']) ?></span>
      </div>
    </div>
    <div class="card-body">
      <div class="flex-entre mb-2">
        <div>
          <strong>Completitud de tu perfil: <?= $pct ?>%</strong>
          <p class="form-ayuda">Un perfil completo ayuda al colegio a conocer tus logros.</p>
        </div>
        <a href="perfil.php" class="btn btn-sm">✏ Completar perfil</a>
      </div>
      <div class="barra-wrap">
        <div class="barra-fill <?= $pct >= 70 ? 'verde' : ($pct >= 40 ? 'acento' : '') ?>"
             style="width:<?= $pct ?>%"></div>
      </div>
    </div>
  </div>

  <!-- Estudios y Trabajo -->
  <div class="grid-2">
    <div class="card">
      <div class="card-header">
        <span class="icono">🎓</span><h3>Mis Estudios</h3>
        <a href="perfil.php?tab=estudio" class="btn btn-sm ml-auto" style="background:rgba(255,255,255,.15)">+ Agregar</a>
      </div>
      <?php if (empty($estudios)): ?>
        <div class="card-body texto-center texto-muted">
          No has registrado estudios aún.<br>
          <a href="perfil.php?tab=estudio" class="mt-1 d-flex" style="display:inline-block">Agregar estudios →</a>
        </div>
      <?php else: ?>
        <?php foreach ($estudios as $e): ?>
          <div class="eg-card-lista">
            <span style="font-size:1.4rem"></span>
            <div>
              <div class="eg-nombre"><?= limpiar($e['carrera']) ?></div>
              <div class="eg-sub"><?= limpiar($e['institucion']) ?> &nbsp;·&nbsp;
                <?= $e['anno_inicio'] ?> — <?= $e['en_curso'] ? '<span class="texto-verde">En curso</span>' : $e['anno_fin'] ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-header">
        <span class="icono"></span><h3>Mi Información Laboral</h3>
        <a href="perfil.php?tab=trabajo" class="btn btn-sm ml-auto" style="background:rgba(255,255,255,.15)">+ Agregar</a>
      </div>
      <?php if (empty($trabajos)): ?>
        <div class="card-body texto-center texto-muted">
          No has registrado información laboral.<br>
          <a href="perfil.php?tab=trabajo" style="display:inline-block;margin-top:4px">Agregar trabajo →</a>
        </div>
      <?php else: ?>
        <?php foreach ($trabajos as $t): ?>
          <div class="eg-card-lista">
            <span style="font-size:1.4rem"></span>
            <div>
              <div class="eg-nombre">
                <?= limpiar($t['cargo'] ?? 'Sin cargo') ?>
                <?php if ($t['actualmente']): ?> <span class="badge badge-verificado">Actual</span><?php endif; ?>
              </div>
              <div class="eg-sub"><?= limpiar($t['empresa'] ?? '') ?><?= $t['area'] ? ' · '.$t['area'] : '' ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Acciones rápidas -->
  <div class="card mt-2">
    <div class="card-header"><span class="icono"></span><h3>Acciones rápidas</h3></div>
    <div class="card-body acciones-rapidas">
      <a href="perfil.php"               class="btn"> Editar perfil personal</a>
      <a href="perfil.php?tab=estudio"   class="btn btn-secundario"> Actualizar estudios</a>
      <a href="perfil.php?tab=trabajo"   class="btn btn-secundario"> Actualizar trabajo</a>
      <a href="perfil.php?tab=redes"     class="btn btn-secundario"> Redes sociales</a>
      <a href="perfil.php?tab=password"  class="btn btn-secundario"> Cambiar contraseña</a>
    </div>
  </div>
</main>
<?php require_once BASE_DIR . 'includes/footer.php'; ?>
