<?php
define('BASE_DIR', dirname(__DIR__) . '/');
define('BASE_URL', ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST'].'/egresados/');
require_once BASE_DIR . 'config/funciones.php';
iniciarSesion(); requiereLogin('comite');
$db=getDB();
$tb   =$db->query("SELECT COUNT(*) FROM egresados_base")->fetchColumn();
$tr   =$db->query("SELECT COUNT(*) FROM usuarios WHERE rol='egresado'")->fetchColumn();
$pct  =$tb>0?round(($tr/$tb)*100,1):0;
$est  =$db->query("SELECT COUNT(DISTINCT usuario_id) FROM estudios")->fetchColumn();
$trab =$db->query("SELECT COUNT(DISTINCT usuario_id) FROM trabajos WHERE actualmente=1")->fetchColumn();
$dest =$db->query("SELECT COUNT(*) FROM usuarios WHERE rol='egresado' AND estado='destacado'")->fetchColumn();
$pes  =$db->query("SELECT estado,COUNT(*) as n FROM usuarios WHERE rol='egresado' GROUP BY estado")->fetchAll();
$carr =$db->query("SELECT carrera,COUNT(*) as n FROM estudios GROUP BY carrera ORDER BY n DESC LIMIT 8")->fetchAll();
$ciu  =$db->query("SELECT ciudad,COUNT(*) as n FROM perfiles WHERE ciudad IS NOT NULL AND ciudad!='' GROUP BY ciudad ORDER BY n DESC LIMIT 8")->fetchAll();
$anns =$db->query("SELECT eb.anno_graduacion,COUNT(eb.id) as tb,COUNT(u.id) as reg FROM egresados_base eb LEFT JOIN usuarios u ON u.documento=eb.documento AND u.rol='egresado' GROUP BY eb.anno_graduacion ORDER BY eb.anno_graduacion DESC LIMIT 10")->fetchAll();

$titulo_pagina='Estadísticas — Comité'; $nav_activo='estadisticas';
require_once BASE_DIR . 'includes/header.php';
?>
<main class="eg-main ancho">
  <p class="page-title"> Estadísticas de Egresados</p>

  <div class="stats-grid">
    <div class="stat-card"><div class="num"><?=$tr?></div><div class="lbl">Registrados</div></div>
    <div class="stat-card acento"><div class="num"><?=$pct?>%</div><div class="lbl">Cobertura</div></div>
    <div class="stat-card verde"><div class="num"><?=$est?></div><div class="lbl">Estudiando</div></div>
    <div class="stat-card morado"><div class="num"><?=$trab?></div><div class="lbl">Trabajando</div></div>
    <div class="stat-card"><div class="num"><?=$dest?></div><div class="lbl">Destacados</div></div>
  </div>

  <div class="grid-2">
    <!-- Estados -->
    <div class="card">
      <div class="card-header"><span class="icono"></span><h3>Perfiles por estado</h3></div>
      <div class="card-body">
        <?php foreach($pes as $e): $p=$tr>0?round(($e['n']/$tr)*100):0; ?>
        <div class="mini-bar-item">
          <div class="mini-bar-row"><span><span class="badge badge-<?=$e['estado']?>"><?=ucfirst($e['estado'])?></span></span><strong><?=$e['n']?> (<?=$p?>%)</strong></div>
          <div class="mini-bar-wrap"><div class="mini-bar-fill" style="width:<?=$p?>%"></div></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Cobertura por promoción -->
    <div class="card">
      <div class="card-header"><span class="icono"></span><h3>Cobertura por promoción</h3></div>
      <div class="tabla-wrapper">
        <table class="tabla">
          <thead><tr><th>Año</th><th>Base</th><th>Reg.</th><th>Progreso</th></tr></thead>
          <tbody>
            <?php foreach($anns as $a): $p=$a['tb']>0?round(($a['reg']/$a['tb'])*100):0; ?>
            <tr>
              <td><strong><?=$a['anno_graduacion']?></strong></td>
              <td><?=$a['tb']?></td><td><?=$a['reg']?></td>
              <td>
                <div style="display:flex;align-items:center;gap:8px">
                  <div class="barra-wrap" style="flex:1">
                    <div class="barra-fill <?=$p>=70?'verde':($p>=40?'acento':'') ?>" style="width:<?=$p?>%"></div>
                  </div>
                  <span style="font-size:.76rem;font-weight:700;min-width:32px"><?=$p?>%</span>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Carreras -->
    <div class="card">
      <div class="card-header"><span class="icono">🎓</span><h3>Carreras más estudiadas</h3></div>
      <div class="card-body" style="padding:0">
        <?php if(empty($carr)): ?><p class="texto-muted texto-center" style="padding:20px">Sin datos de estudios aún.</p>
        <?php else: $mc=$carr[0]['n']; foreach($carr as $i=>$c): ?>
        <div style="padding:9px 16px;border-bottom:1px solid var(--gb2)">
          <div class="mini-bar-row" style="margin-bottom:3px"><span><strong class="texto-azul"><?=$i+1?>.</strong> <?=limpiar($c['carrera'])?></span><strong><?=$c['n']?></strong></div>
          <div class="mini-bar-wrap"><div class="mini-bar-fill" style="width:<?=round(($c['n']/$mc)*100)?>%"></div></div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Ciudades -->
    <div class="card">
      <div class="card-header"><span class="icono"></span><h3>Ciudades de residencia</h3></div>
      <div class="card-body" style="padding:0">
        <?php if(empty($ciu)): ?><p class="texto-muted texto-center" style="padding:20px">Sin datos de ciudades aún.</p>
        <?php else: $mci=$ciu[0]['n']; foreach($ciu as $i=>$c): ?>
        <div style="padding:9px 16px;border-bottom:1px solid var(--gb2)">
          <div class="mini-bar-row" style="margin-bottom:3px"><span><strong class="texto-acento"><?=$i+1?>.</strong> 📍 <?=limpiar($c['ciudad'])?></span><strong><?=$c['n']?></strong></div>
          <div class="mini-bar-wrap"><div class="mini-bar-fill acento" style="width:<?=round(($c['n']/$mci)*100)?>%"></div></div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</main>
<?php require_once BASE_DIR . 'includes/footer.php'; ?>
