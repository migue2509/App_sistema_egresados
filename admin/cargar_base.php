<?php
define('BASE_DIR', dirname(__DIR__) . '/');
define('BASE_URL', ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST'].'/egresados/');
require_once BASE_DIR . 'config/funciones.php';
iniciarSesion(); requiereLogin('rectoria');
$db = getDB(); $resultado = null;

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['archivo'])) {
    validarCsrf();
    $arc=$_FILES['archivo']; $modo=$_POST['modo']??'agregar';
    if ($arc['error']!==UPLOAD_ERR_OK) { setFlash('error','Error al subir el archivo.'); }
    elseif ($arc['size']>5*1024*1024) { setFlash('error','El archivo no debe superar 5 MB.'); }
    else {
        $h=fopen($arc['tmp_name'],'r');
        $bom=fread($h,3); if($bom!=="\xEF\xBB\xBF") rewind($h);
        $cab=fgetcsv($h);
        if (!$cab) { setFlash('error','El archivo CSV está vacío.'); }
        else {
            $cab=array_map(fn($c)=>strtolower(trim($c)),$cab);
            $req=['documento','nombres','apellidos','fecha_nacimiento','anno_graduacion'];
            $falt=array_diff($req,$cab);
            if (!empty($falt)) { setFlash('error','Faltan columnas: '.implode(', ',$falt)); }
            else {
                $idx=array_flip($cab); $ins=$act=$err=0; $errd=[];
                if ($modo==='reemplazar') $db->exec("DELETE FROM egresados_base WHERE registrado=0");
                $st=$db->prepare("INSERT INTO egresados_base (documento,nombres,apellidos,fecha_nacimiento,anno_graduacion) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE nombres=VALUES(nombres),apellidos=VALUES(apellidos),fecha_nacimiento=VALUES(fecha_nacimiento),anno_graduacion=VALUES(anno_graduacion)");
                $fn=1;
                while(($f=fgetcsv($h))!==false) {
                    $fn++;
                    $doc=soloNumeros(trim($f[$idx['documento']]??''));
                    $nom=trim($f[$idx['nombres']]??''); $ape=trim($f[$idx['apellidos']]??'');
                    $fnac=trim($f[$idx['fecha_nacimiento']]??''); $anno=(int)trim($f[$idx['anno_graduacion']]??'');
                    if (!$doc||!$nom||!$ape||!$fnac||!$anno) { $err++; $errd[]="Fila $fn: datos incompletos"; continue; }
                    $dt=DateTime::createFromFormat('Y-m-d',$fnac)?:DateTime::createFromFormat('d/m/Y',$fnac)?:DateTime::createFromFormat('d-m-Y',$fnac);
                    if (!$dt) { $err++; $errd[]="Fila $fn: fecha '$fnac' inválida"; continue; }
                    $ex=$db->prepare("SELECT id FROM egresados_base WHERE documento=?"); $ex->execute([$doc]); $es_nuevo=!$ex->fetch();
                    try { $st->execute([$doc,$nom,$ape,$dt->format('Y-m-d'),$anno]); $es_nuevo?$ins++:$act++; }
                    catch(Exception $e){ $err++; $errd[]="Fila $fn: ".$e->getMessage(); }
                }
                fclose($h);
                registrarLog('CARGA_BASE',"Ins:$ins | Act:$act | Err:$err");
                $resultado=compact('ins','act','err','errd');
                // alias para template
                $resultado['insertados']=$ins; $resultado['actualizados']=$act; $resultado['errores']=$err; $resultado['errores_detalle']=$errd;
            }
        }
    }
    if (!$resultado) { header('Location: cargar_base.php'); exit; }
}

$total_base  = $db->query("SELECT COUNT(*) FROM egresados_base")->fetchColumn();
$registrados = $db->query("SELECT COUNT(*) FROM egresados_base WHERE registrado=1")->fetchColumn();
$sin_reg     = $total_base - $registrados;
$ultimas     = $db->query("SELECT documento,nombres,apellidos,anno_graduacion,registrado,fecha_carga FROM egresados_base ORDER BY fecha_carga DESC LIMIT 10")->fetchAll();

$titulo_pagina='Cargar Base de Egresados'; $nav_activo='cargar';
require_once BASE_DIR . 'includes/header.php';
?>
<main class="eg-main angosto">
  <?= getFlash() ?>
  <p class="page-title"> Cargar Base Oficial de Egresados</p>

  <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:18px">
    <div class="stat-card"><div class="num"><?=$total_base?></div><div class="lbl">Total en base</div></div>
    <div class="stat-card verde"><div class="num"><?=$registrados?></div><div class="lbl">Ya registrados</div></div>
    <div class="stat-card acento"><div class="num"><?=$sin_reg?></div><div class="lbl">Sin registrar</div></div>
  </div>

  <!-- Resultado -->
  <?php if ($resultado): ?>
  <div class="card mb-3">
    <div class="card-header <?= $resultado['errores']===0?'verde':'alerta' ?>">
      <span class="icono"></span><h3>Resultado de la carga</h3>
    </div>
    <div class="card-body">
      <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:<?=$resultado['errores']?'14':'0'?>px">
        <div class="stat-card verde"><div class="num"><?=$resultado['insertados']?></div><div class="lbl">Registros nuevos</div></div>
        <div class="stat-card"><div class="num"><?=$resultado['actualizados']?></div><div class="lbl">Actualizados</div></div>
        <div class="stat-card rojo"><div class="num"><?=$resultado['errores']?></div><div class="lbl">Con errores</div></div>
      </div>
      <?php if (!empty($resultado['errores_detalle'])): ?>
      <div class="alerta alerta-warning">
        <div><strong>Detalle de errores:</strong><br>
        <?php foreach(array_slice($resultado['errores_detalle'],0,10) as $er): ?>
          <small>• <?=limpiar($er)?></small><br>
        <?php endforeach; ?>
        <?php if(count($resultado['errores_detalle'])>10): ?><small>... y <?=count($resultado['errores_detalle'])-10?> errores más.</small><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Formulario -->
  <div class="card mb-3">
    <div class="card-header"><span class="icono">⬆</span><h3>Subir archivo CSV</h3></div>
    <div class="card-body">
      <div class="alerta alerta-info">
        ℹ El archivo CSV debe tener estas columnas en la primera fila:<br>
        <code style="background:rgba(255,255,255,.3);padding:3px 8px;border-radius:4px;font-size:.82rem">documento, nombres, apellidos, fecha_nacimiento, anno_graduacion</code><br>
        <small>Fecha en formato <strong>YYYY-MM-DD</strong> (ej: 2000-03-15) o DD/MM/YYYY</small>
      </div>
      <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
        <div class="form-grupo">
          <label>Archivo CSV <span class="req">*</span></label>
          <label class="upload-zona" for="csv-inp">
            <input type="file" id="csv-inp" name="archivo" accept=".csv,text/csv" required
                   onchange="document.getElementById('fname').textContent=this.files[0]?.name||''">
             Haz clic para seleccionar el archivo CSV<br>
            <small id="fname" class="texto-azul" style="margin-top:4px;display:block"></small>
          </label>
        </div>
        <div class="form-grupo">
          <label>Modo de carga</label>
          <div class="radio-grupo">
            <label class="radio-opcion">
              <input type="radio" name="modo" value="agregar" checked>
              <div><strong>Agregar / Actualizar</strong><span>Agrega nuevos registros y actualiza los existentes. No elimina nada.</span></div>
            </label>
            <label class="radio-opcion">
              <input type="radio" name="modo" value="reemplazar">
              <div><strong>Reemplazar base (sin cuenta)</strong><span>Elimina los que no tienen cuenta creada y carga el nuevo listado.</span></div>
            </label>
          </div>
        </div>
        <button type="submit" class="btn"> Cargar base de datos</button>
      </form>
    </div>
    <div class="card-footer">
      <strong>Plantilla de ejemplo:</strong>
      <code class="bloque-codigo">documento,nombres,apellidos,fecha_nacimiento,anno_graduacion
1001234567,Juan Camilo,García Ríos,2000-03-15,2018
1009876543,María Alejandra,López Herrera,2001-07-22,2019</code>
      <a href="data:text/csv;charset=utf-8,%EF%BB%BFdocumento%2Cnombres%2Capellidos%2Cfecha_nacimiento%2Canno_graduacion%0A1001234567%2CJuan%20Camilo%2CGarc%C3%ADa%20R%C3%ADos%2C2000-03-15%2C2018" download="plantilla_egresados.csv" class="btn btn-secundario btn-sm mt-1">⬇ Descargar plantilla</a>
    </div>
  </div>

  <!-- Últimas entradas -->
  <?php if (!empty($ultimas)): ?>
  <div class="card">
    <div class="card-header"><span class="icono"></span><h3>Últimas entradas en la base</h3></div>
    <div class="tabla-wrapper">
      <table class="tabla">
        <thead><tr><th>Documento</th><th>Nombres</th><th>Apellidos</th><th>Año Grad.</th><th>Estado</th><th>Cargado</th></tr></thead>
        <tbody>
          <?php foreach($ultimas as $r): ?>
          <tr>
            <td><?=limpiar($r['documento'])?></td>
            <td><?=limpiar($r['nombres'])?></td>
            <td><?=limpiar($r['apellidos'])?></td>
            <td><?=(int)$r['anno_graduacion']?></td>
            <td><?=$r['registrado']?'<span class="badge badge-verificado">Con cuenta</span>':'<span class="badge badge-pendiente">Sin registrar</span>'?></td>
            <td><small><?=date('d/m/Y H:i',strtotime($r['fecha_carga']))?></small></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</main>
<?php require_once BASE_DIR . 'includes/footer.php'; ?>
