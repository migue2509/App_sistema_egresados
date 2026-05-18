<?php
define('BASE_DIR', dirname(__DIR__) . '/');
define('BASE_URL', ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST'].'/egresados/');
require_once BASE_DIR . 'config/funciones.php';
iniciarSesion(); requiereLogin('comite');
$db=getDB();
$total=$db->query("SELECT COUNT(*) FROM usuarios WHERE rol='egresado'")->fetchColumn();
$verf =$db->query("SELECT COUNT(*) FROM usuarios WHERE rol='egresado' AND estado='verificado'")->fetchColumn();
$dest =$db->query("SELECT COUNT(*) FROM usuarios WHERE rol='egresado' AND estado='destacado'")->fetchColumn();
$pend =$db->query("SELECT COUNT(*) FROM usuarios WHERE rol='egresado' AND estado='pendiente'")->fetchColumn();
$est  =$db->query("SELECT COUNT(DISTINCT usuario_id) FROM estudios")->fetchColumn();
$trab =$db->query("SELECT COUNT(DISTINCT usuario_id) FROM trabajos WHERE actualmente=1")->fetchColumn();

$por_revisar=$db->query("SELECT u.id,u.estado,u.created_at,eb.nombres,eb.apellidos,eb.anno_graduacion FROM usuarios u LEFT JOIN egresados_base eb ON eb.documento=u.documento WHERE u.rol='egresado' AND u.estado='pendiente' ORDER BY u.created_at ASC LIMIT 8")->fetchAll();
$ultimos=$db->query("SELECT u.id,u.estado,u.created_at,eb.nombres,eb.apellidos,eb.anno_graduacion,p.ciudad FROM usuarios u LEFT JOIN egresados_base eb ON eb.documento=u.documento LEFT JOIN perfiles p ON p.usuario_id=u.id WHERE u.rol='egresado' ORDER BY u.created_at DESC LIMIT 6")->fetchAll();

$titulo_pagina='Panel Comité'; $nav_activo='inicio';
require_once BASE_DIR . 'includes/header.php';
?>
<main class="eg-main ancho">
  <?= getFlash() ?>
  <p class="page-title">👥 Panel del Comité — Seguimiento de Egresados</p>

  <div class="stats-grid">
    <div class="stat-card"><div class="num"><?=$total?></div><div class="lbl">Egresados registrados</div></div>
    <div class="stat-card verde"><div class="num"><?=$verf?></div><div class="lbl">Verificados</div></div>
    <div class="stat-card morado"><div class="num"><?=$dest?></div><div class="lbl">Destacados</div></div>
    <div class="stat-card rojo"><div class="num"><?=$pend?></div><div class="lbl">Pendientes revisión</div></div>
    <div class="stat-card acento"><div class="num"><?=$est?></div><div class="lbl">Que estudian</div></div>
    <div class="stat-card verde"><div class="num"><?=$trab?></div><div class="lbl">Que trabajan</div></div>
  </div>

  <div class="grid-2">
    <!-- Pendientes -->
    <div class="card">
      <div class="card-header <?= $pend>0?'alerta':'' ?>">
        <span class="icono">⏳</span><h3>Pendientes de revisión (<?=$pend?>)</h3>
        <a href="egresados.php?estado=pendiente" class="btn btn-sm ml-auto" style="background:rgba(255,255,255,.2)">Ver todos →</a>
      </div>
      <div style="padding:0">
        <?php if(empty($por_revisar)): ?>
          <p class="texto-center texto-muted" style="padding:20px">✔ No hay perfiles pendientes de revisión.</p>
        <?php else: foreach($por_revisar as $e): ?>
          <div class="eg-card-lista">
            <div class="avatar avatar-sm" style="background:var(--ac);color:#1a1a1a"><?=strtoupper(substr($e['nombres']??'E',0,1))?></div>
            <div>
              <div class="eg-nombre"><?=limpiar($e['nombres'].' '.$e['apellidos'])?></div>
              <div class="eg-sub">Promoción <?=(int)$e['anno_graduacion']?> &nbsp;·&nbsp; Registro: <?=date('d/m/Y',strtotime($e['created_at']))?></div>
            </div>
            <a href="../perfil.php?uid=<?=$e['id']?>" class="btn btn-sm">Ver perfil</a>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Últimos -->
    <div class="card">
      <div class="card-header">
        <span class="icono"></span><h3>Últimos registros</h3>
        <a href="egresados.php" class="btn btn-sm ml-auto" style="background:rgba(255,255,255,.15)">Ver todos →</a>
      </div>
      <?php foreach($ultimos as $e): ?>
        <div class="eg-card-lista">
          <div class="avatar avatar-sm"><?=strtoupper(substr($e['nombres']??'E',0,1))?></div>
          <div>
            <div class="eg-nombre"><?=limpiar($e['nombres'].' '.$e['apellidos'])?></div>
            <div class="eg-sub"><?=(int)$e['anno_graduacion']?><?=$e['ciudad']?' &nbsp;·&nbsp; 📍'.limpiar($e['ciudad']):''?></div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:5px">
            <span class="badge badge-<?=$e['estado']?>"><?=ucfirst($e['estado'])?></span>
            <a href="../perfil.php?uid=<?=$e['id']?>" class="btn btn-sm">Ver</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Acciones -->
  <div class="card mt-2">
    <div class="card-header"><span class="icono"></span><h3>Acciones rápidas</h3></div>
    <div class="card-body acciones-rapidas">
      <a href="egresados.php"                    class="btn">👥 Ver todos los egresados</a>
      <a href="egresados.php?estado=pendiente"   class="btn btn-secundario"> Ver pendientes</a>
      <a href="egresados.php?estado=destacado"   class="btn btn-secundario"> Ver destacados</a>
      <a href="estadisticas.php"                 class="btn btn-secundario"> Ver estadísticas</a>
    </div>
  </div>
</main>
<?php require_once BASE_DIR . 'includes/footer.php'; ?>
