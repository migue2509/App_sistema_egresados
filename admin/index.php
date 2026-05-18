<?php
define('BASE_DIR', dirname(__DIR__) . '/');
define('BASE_URL', ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST'].'/egresados/');
require_once BASE_DIR . 'config/funciones.php';
iniciarSesion(); requiereLogin('rectoria');
$db = getDB();

$total_base  = $db->query("SELECT COUNT(*) FROM egresados_base")->fetchColumn();
$total_reg   = $db->query("SELECT COUNT(*) FROM usuarios WHERE rol='egresado'")->fetchColumn();
$pendientes  = $db->query("SELECT COUNT(*) FROM usuarios WHERE rol='egresado' AND estado='pendiente'")->fetchColumn();
$verificados = $db->query("SELECT COUNT(*) FROM usuarios WHERE rol='egresado' AND estado='verificado'")->fetchColumn();
$destacados  = $db->query("SELECT COUNT(*) FROM usuarios WHERE rol='egresado' AND estado='destacado'")->fetchColumn();
$estudian    = $db->query("SELECT COUNT(DISTINCT usuario_id) FROM estudios")->fetchColumn();
$trabajan    = $db->query("SELECT COUNT(DISTINCT usuario_id) FROM trabajos WHERE actualmente=1")->fetchColumn();

$ultimos = $db->query("SELECT u.id,u.email,u.estado,u.created_at,eb.nombres,eb.apellidos,eb.anno_graduacion FROM usuarios u LEFT JOIN egresados_base eb ON eb.documento=u.documento WHERE u.rol='egresado' ORDER BY u.created_at DESC LIMIT 8")->fetchAll();
$carreras= $db->query("SELECT carrera,COUNT(*) as n FROM estudios GROUP BY carrera ORDER BY n DESC LIMIT 6")->fetchAll();
$ciudades= $db->query("SELECT ciudad,COUNT(*) as n FROM perfiles WHERE ciudad IS NOT NULL AND ciudad!='' GROUP BY ciudad ORDER BY n DESC LIMIT 6")->fetchAll();
$por_anno= $db->query("SELECT eb.anno_graduacion,COUNT(u.id) as reg FROM egresados_base eb LEFT JOIN usuarios u ON u.documento=eb.documento AND u.rol='egresado' GROUP BY eb.anno_graduacion ORDER BY eb.anno_graduacion DESC LIMIT 8")->fetchAll();

$titulo_pagina='Panel Rectoría'; $nav_activo='inicio';
require_once BASE_DIR . 'includes/header.php';
?>
<main class="eg-main ancho">
  <?= getFlash() ?>
  <p class="page-title"> Panel de Rectoría — Sistema de Egresados</p>

  <div class="stats-grid">
    <div class="stat-card"><div class="num"><?= number_format($total_base) ?></div><div class="lbl">En base oficial</div></div>
    <div class="stat-card verde"><div class="num"><?= number_format($total_reg) ?></div><div class="lbl">Cuentas registradas</div></div>
    <div class="stat-card acento"><div class="num"><?= number_format($estudian) ?></div><div class="lbl">Egresados que estudian</div></div>
    <div class="stat-card morado"><div class="num"><?= number_format($trabajan) ?></div><div class="lbl">Egresados que trabajan</div></div>
    <div class="stat-card rojo"><div class="num"><?= number_format($pendientes) ?></div><div class="lbl">Perfiles pendientes</div></div>
    <div class="stat-card verde"><div class="num"><?= number_format($verificados+$destacados) ?></div><div class="lbl">Perfiles verificados</div></div>
  </div>

  <div class="card mb-3">
    <div class="card-body" style="padding:14px 18px">
      <div class="acciones-rapidas">
        <strong class="texto-azul">Acciones rápidas:</strong>
        <a href="egresados.php"  class="btn btn-sm"> Ver egresados</a>
        <a href="estadisticas.php" class="btn btn-secundario btn-sm"> Estadísticas</a>
        <a href="cargar_base.php"  class="btn btn-secundario btn-sm"> Cargar base</a>
        <a href="usuarios.php"     class="btn btn-secundario btn-sm"> Usuarios</a>
        <a href="estadisticas.php?exportar=csv" class="btn btn-verde btn-sm"> Exportar CSV</a>
      </div>
    </div>
  </div>

  <div class="grid-2">
    <!-- Últimos registros -->
    <div class="card">
      <div class="card-header">
        <span class="icono"></span><h3>Últimos registros</h3>
        <a href="egresados.php" class="btn btn-sm ml-auto" style="background:rgba(255,255,255,.15)">Ver todos →</a>
      </div>
      <div class="tabla-wrapper">
        <table class="tabla">
          <thead><tr><th>Egresado</th><th>Prom.</th><th>Estado</th><th>Fecha</th><th>Acción</th></tr></thead>
          <tbody>
            <?php foreach ($ultimos as $u): ?>
            <tr>
              <td><strong><?= limpiar($u['nombres'].' '.$u['apellidos']) ?></strong><br><small><?= limpiar($u['email']) ?></small></td>
              <td><?= (int)$u['anno_graduacion'] ?></td>
              <td><span class="badge badge-<?= $u['estado'] ?>"><?= ucfirst($u['estado']) ?></span></td>
              <td><small><?= date('d/m/Y', strtotime($u['created_at'])) ?></small></td>
              <td>
                <a href="../perfil.php?uid=<?= $u['id'] ?>" class="btn btn-sm">Ver</a>
                <a href="egresados.php" class="btn btn-secundario btn-sm">Editar</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:18px">
      <!-- Carreras -->
      <div class="card">
        <div class="card-header"><span class="icono"></span><h3>Carreras más estudiadas</h3></div>
        <div class="card-body">
          <?php if (empty($carreras)): ?>
            <p class="texto-muted">Sin datos de estudios aún.</p>
          <?php else: $mc=$carreras[0]['n']??1; foreach ($carreras as $c): ?>
            <div class="mini-bar-item">
              <div class="mini-bar-row"><span><?= limpiar($c['carrera']) ?></span><strong><?= $c['n'] ?></strong></div>
              <div class="mini-bar-wrap"><div class="mini-bar-fill" style="width:<?= round(($c['n']/$mc)*100) ?>%"></div></div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <!-- Ciudades -->
      <div class="card">
        <div class="card-header"><span class="icono"></span><h3>Ciudades de residencia</h3></div>
        <div class="card-body" style="padding:0">
          <?php foreach ($ciudades as $c): ?>
            <div class="eg-card-lista">
              <span></span>
              <span><?= limpiar($c['ciudad']) ?></span>
              <span class="badge badge-verificado"><?= $c['n'] ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Barras por año -->
  <?php if (!empty($por_anno)): ?>
  <div class="card mt-2">
    <div class="card-header"><span class="icono"></span><h3>Participación por año de graduación</h3></div>
    <div class="card-body">
      <div class="chart-barras">
        <?php $mr=max(array_column($por_anno,'reg'))?:1; foreach($por_anno as $a):
          $h=max(14, round(($a['reg']/$mr)*100)); ?>
          <div class="chart-barra-item">
            <div class="val"><?= $a['reg'] ?></div>
            <div class="col" style="height:<?= $h ?>px"></div>
            <div class="lbl"><?= $a['anno_graduacion'] ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
</main>
<?php require_once BASE_DIR . 'includes/footer.php'; ?>
