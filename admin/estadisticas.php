<?php
define('BASE_DIR', dirname(__DIR__) . '/');
define('BASE_URL', ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST'].'/egresados/');
require_once BASE_DIR . 'config/funciones.php';
iniciarSesion(); requiereLogin('rectoria');
$db = getDB();

if (isset($_GET['exportar']) && $_GET['exportar']==='csv') {
    $rows=$db->query("SELECT eb.documento,eb.nombres,eb.apellidos,eb.anno_graduacion,eb.fecha_nacimiento,u.email,u.estado,u.created_at,p.genero,p.telefono,p.ciudad,p.pais_nacimiento,(SELECT GROUP_CONCAT(carrera SEPARATOR ' | ') FROM estudios WHERE usuario_id=u.id) AS carreras,(SELECT GROUP_CONCAT(empresa SEPARATOR ' | ') FROM trabajos WHERE usuario_id=u.id AND actualmente=1) AS empresas FROM egresados_base eb JOIN usuarios u ON u.documento=eb.documento AND u.rol='egresado' LEFT JOIN perfiles p ON p.usuario_id=u.id ORDER BY eb.anno_graduacion DESC,eb.apellidos")->fetchAll();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="egresados_dinamarca_'.date('Ymd').'.csv"');
    echo "\xEF\xBB\xBF";
    $out=fopen('php://output','w');
    fputcsv($out,['Documento','Nombres','Apellidos','Año Grad.','Fecha Nac.','Email','Estado','Género','Teléfono','Ciudad','País','Carreras','Empresa Actual','Fecha Registro']);
    foreach($rows as $r) fputcsv($out,[$r['documento'],$r['nombres'],$r['apellidos'],$r['anno_graduacion'],$r['fecha_nacimiento'],$r['email'],$r['estado'],$r['genero'],$r['telefono'],$r['ciudad'],$r['pais_nacimiento'],$r['carreras'],$r['empresas'],$r['created_at']]);
    fclose($out); exit;
}

$total_base    = $db->query("SELECT COUNT(*) FROM egresados_base")->fetchColumn();
$total_reg     = $db->query("SELECT COUNT(*) FROM usuarios WHERE rol='egresado'")->fetchColumn();
$pct           = $total_base>0 ? round(($total_reg/$total_base)*100,1) : 0;
$estudian      = $db->query("SELECT COUNT(DISTINCT usuario_id) FROM estudios")->fetchColumn();
$trabajan      = $db->query("SELECT COUNT(DISTINCT usuario_id) FROM trabajos WHERE actualmente=1")->fetchColumn();
$por_estado    = $db->query("SELECT estado,COUNT(*) as n FROM usuarios WHERE rol='egresado' GROUP BY estado")->fetchAll();
$top_carreras  = $db->query("SELECT carrera,COUNT(*) as n FROM estudios GROUP BY carrera ORDER BY n DESC LIMIT 10")->fetchAll();
$top_ciudades  = $db->query("SELECT ciudad,COUNT(*) as n FROM perfiles WHERE ciudad IS NOT NULL AND ciudad!='' GROUP BY ciudad ORDER BY n DESC LIMIT 10")->fetchAll();
$por_anno      = $db->query("SELECT eb.anno_graduacion,COUNT(eb.id) as tb,COUNT(u.id) as reg FROM egresados_base eb LEFT JOIN usuarios u ON u.documento=eb.documento AND u.rol='egresado' GROUP BY eb.anno_graduacion ORDER BY eb.anno_graduacion")->fetchAll();
$generos       = $db->query("SELECT genero,COUNT(*) as n FROM perfiles WHERE genero IS NOT NULL GROUP BY genero")->fetchAll();

$titulo_pagina='Estadísticas'; $nav_activo='estadisticas';
require_once BASE_DIR . 'includes/header.php';
?>
<main class="eg-main ancho">
  <div class="flex-entre mb-2">
    <p class="page-title"> Estadísticas Generales</p>
    <a href="?exportar=csv" class="btn btn-verde">⬇ Exportar todo a CSV</a>
  </div>

  <!-- Cobertura -->
  <div class="card mb-3">
    <div class="card-header"><span class="icono"></span><h3>Cobertura del Sistema</h3></div>
    <div class="card-body">
      <div class="stats-grid" style="margin-bottom:18px">
        <div class="stat-card"><div class="num"><?=$total_base?></div><div class="lbl">En base oficial</div></div>
        <div class="stat-card verde"><div class="num"><?=$total_reg?></div><div class="lbl">Registrados</div></div>
        <div class="stat-card acento"><div class="num"><?=$pct?>%</div><div class="lbl">Cobertura</div></div>
        <div class="stat-card morado"><div class="num"><?=$estudian?></div><div class="lbl">Estudian</div></div>
        <div class="stat-card verde"><div class="num"><?=$trabajan?></div><div class="lbl">Trabajan (actual)</div></div>
      </div>
      <div class="flex-entre mb-1" style="font-size:.85rem">
        <span>Participación: <strong><?=$pct?>%</strong></span><span><?=$total_reg?> / <?=$total_base?></span>
      </div>
      <div class="barra-cobertura"><div class="fill" style="width:<?=$pct?>%"></div></div>
    </div>
  </div>

  <div class="grid-2">
    <!-- Estados -->
    <div class="card">
      <div class="card-header"><span class="icono"></span><h3>Perfiles por Estado</h3></div>
      <div class="card-body">
        <?php foreach($por_estado as $e): $pct_e=$total_reg>0?round(($e['n']/$total_reg)*100):0; ?>
        <div class="mini-bar-item">
          <div class="mini-bar-row">
            <span><span class="badge badge-<?=$e['estado']?>"><?=ucfirst($e['estado'])?></span></span>
            <strong><?=$e['n']?> (<?=$pct_e?>%)</strong>
          </div>
          <div class="mini-bar-wrap"><div class="mini-bar-fill" style="width:<?=$pct_e?>%"></div></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Géneros -->
    <div class="card">
      <div class="card-header"><span class="icono"></span><h3>Distribución por Género</h3></div>
      <div class="card-body">
        <?php $tg=array_sum(array_column($generos,'n'))?:1;
        $cg=['Masculino'=>'var(--az3)','Femenino'=>'#e91e8c','Otro'=>'#6c63ff','Prefiero no decir'=>'#78909c'];
        foreach($generos as $g): $pct_g=round(($g['n']/$tg)*100); ?>
        <div class="mini-bar-item">
          <div class="mini-bar-row"><span><?=limpiar($g['genero']?:'No especificado')?></span><strong><?=$g['n']?> (<?=$pct_g?>%)</strong></div>
          <div class="mini-bar-wrap"><div class="mini-bar-fill" style="width:<?=$pct_g?>%;background:<?=$cg[$g['genero']]??'var(--ac)'?>"></div></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Top carreras -->
    <div class="card">
      <div class="card-header"><span class="icono"></span><h3>Top 10 Carreras Más Estudiadas</h3></div>
      <div class="card-body" style="padding:0">
        <?php if(empty($top_carreras)): ?><p class="texto-muted texto-center" style="padding:20px">Sin datos aún.</p>
        <?php else: $mc=$top_carreras[0]['n']; foreach($top_carreras as $i=>$c): ?>
        <div style="padding:9px 16px;border-bottom:1px solid var(--gb2)">
          <div class="mini-bar-row" style="margin-bottom:3px"><span><strong class="texto-azul"><?=$i+1?>.</strong> <?=limpiar($c['carrera'])?></span><strong><?=$c['n']?></strong></div>
          <div class="mini-bar-wrap"><div class="mini-bar-fill" style="width:<?=round(($c['n']/$mc)*100)?>%"></div></div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Top ciudades -->
    <div class="card">
      <div class="card-header"><span class="icono"></span><h3>Top 10 Ciudades de Residencia</h3></div>
      <div class="card-body" style="padding:0">
        <?php if(empty($top_ciudades)): ?><p class="texto-muted texto-center" style="padding:20px">Sin datos aún.</p>
        <?php else: $mci=$top_ciudades[0]['n']; foreach($top_ciudades as $i=>$c): ?>
        <div style="padding:9px 16px;border-bottom:1px solid var(--gb2)">
          <div class="mini-bar-row" style="margin-bottom:3px"><span><strong class="texto-acento"><?=$i+1?>.</strong> 📍 <?=limpiar($c['ciudad'])?></span><strong><?=$c['n']?></strong></div>
          <div class="mini-bar-wrap"><div class="mini-bar-fill acento" style="width:<?=round(($c['n']/$mci)*100)?>%"></div></div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

  <!-- Tabla por año -->
  <?php if(!empty($por_anno)): ?>
  <div class="card mt-2">
    <div class="card-header"><span class="icono"></span><h3>Participación por Año de Graduación</h3></div>
    <div class="tabla-wrapper">
      <table class="tabla">
        <thead><tr><th>Año</th><th>En base</th><th>Registrados</th><th>Cobertura</th><th>Progreso</th></tr></thead>
        <tbody>
          <?php foreach($por_anno as $a): $p=($a['tb']>0)?round(($a['reg']/$a['tb'])*100):0; ?>
          <tr>
            <td><strong><?=$a['anno_graduacion']?></strong></td>
            <td><?=$a['tb']?></td>
            <td><?=$a['reg']?></td>
            <td><strong><?=$p?>%</strong></td>
            <td style="width:180px">
              <div class="barra-wrap">
                <div class="barra-fill <?=$p>=70?'verde':($p>=40?'acento':'') ?>" style="width:<?=$p?>%"></div>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</main>
<?php require_once BASE_DIR . 'includes/footer.php'; ?>
