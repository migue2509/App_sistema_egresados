<?php
define('BASE_DIR', dirname(__DIR__) . '/');
define('BASE_URL', ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST'].'/egresados/');
require_once BASE_DIR . 'config/funciones.php';
iniciarSesion(); requiereLogin('comite');
$db=getDB();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    validarCsrf();
    $ac=$_POST['accion']??''; $uid=(int)($_POST['uid']??0);
    if ($ac==='cambiar_estado'&&$uid) {
        $est=$_POST['estado']??'';
        if (in_array($est,['pendiente','verificado','destacado'])) {
            $db->prepare("UPDATE usuarios SET estado=? WHERE id=? AND rol='egresado'")->execute([$est,$uid]);
            registrarLog('COMITE_ESTADO',"Usuario $uid → $est"); setFlash('success','Estado actualizado.');
        }
    }
    if ($ac==='agregar_nota'&&$uid) {
        $nota=trim($_POST['nota']??'');
        if ($nota) {
            $ac2=$db->prepare("SELECT logros FROM perfiles WHERE usuario_id=?"); $ac2->execute([$uid]); $act=$ac2->fetchColumn();
            $nuevo=trim($act."\n\n[COMITÉ ".date('d/m/Y')."]: ".$nota);
            $db->prepare("UPDATE perfiles SET logros=? WHERE usuario_id=?")->execute([$nuevo,$uid]);
            registrarLog('COMITE_NOTA',"Nota a usuario $uid"); setFlash('success','Nota agregada al perfil.');
        }
    }
    header('Location: egresados.php'.($estado??''?'?estado='.$_GET['estado']:'')); exit;
}

$buscar=$_GET['q']??''; $estado=$_GET['estado']??''; $anno=(int)($_GET['anno']??0);
$page=max(1,(int)($_GET['pag']??1)); $pp=20; $off=($page-1)*$pp;
$where=["u.rol='egresado'"]; $params=[];
if ($buscar) { $where[]="(eb.nombres LIKE ? OR eb.apellidos LIKE ? OR u.documento LIKE ?)"; $l="%$buscar%"; $params=array_merge($params,[$l,$l,$l]); }
if ($estado) { $where[]="u.estado=?"; $params[]=$estado; }
if ($anno)   { $where[]="eb.anno_graduacion=?"; $params[]=$anno; }
$wh='WHERE '.implode(' AND ',$where);

$cnt=$db->prepare("SELECT COUNT(*) FROM usuarios u LEFT JOIN egresados_base eb ON eb.documento=u.documento $wh"); $cnt->execute($params); $total=(int)$cnt->fetchColumn(); $tp=ceil($total/$pp);
$stmt=$db->prepare("SELECT u.id,u.email,u.estado,u.created_at,eb.nombres,eb.apellidos,eb.anno_graduacion,eb.documento,p.ciudad,p.foto,(SELECT COUNT(*) FROM estudios WHERE usuario_id=u.id) AS ne,(SELECT COUNT(*) FROM trabajos WHERE usuario_id=u.id AND actualmente=1) AS nt,(SELECT carrera FROM estudios WHERE usuario_id=u.id ORDER BY anno_inicio DESC LIMIT 1) AS uc,(SELECT cargo FROM trabajos WHERE usuario_id=u.id AND actualmente=1 LIMIT 1) AS ca FROM usuarios u LEFT JOIN egresados_base eb ON eb.documento=u.documento LEFT JOIN perfiles p ON p.usuario_id=u.id $wh ORDER BY u.created_at DESC LIMIT $pp OFFSET $off");
$stmt->execute($params); $egresados=$stmt->fetchAll();
$annos=$db->query("SELECT DISTINCT anno_graduacion FROM egresados_base ORDER BY anno_graduacion DESC")->fetchAll(PDO::FETCH_COLUMN);

$titulo_pagina='Egresados — Comité'; $nav_activo='egresados';
require_once BASE_DIR . 'includes/header.php';
?>
<main class="eg-main ancho">
  <?= getFlash() ?>
  <p class="page-title"> Consulta de Egresados</p>

  <div class="card mb-3">
    <form method="GET">
      <div class="filtros-barra">
        <input type="text" name="q" class="form-control" placeholder=" Buscar por nombre o documento..." value="<?=limpiar($buscar)?>" style="flex:1;max-width:320px">
        <select name="estado" class="form-control" style="max-width:180px">
          <option value="">— Todos los estados —</option>
          <?php foreach(['pendiente','verificado','destacado','inactivo'] as $e): ?>
            <option value="<?=$e?>" <?=$estado===$e?'selected':''?>><?=ucfirst($e)?></option>
          <?php endforeach; ?>
        </select>
        <select name="anno" class="form-control" style="max-width:160px">
          <option value="">— Todos los años —</option>
          <?php foreach($annos as $a): ?>
            <option value="<?=$a?>" <?=$anno==$a?'selected':''?>>Promoción <?=$a?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-sm">Filtrar</button>
        <a href="egresados.php" class="btn btn-secundario btn-sm">✖ Limpiar</a>
      </div>
    </form>
    <div class="card-header"><span class="icono"></span><h3><?=number_format($total)?> egresado<?=$total!=1?'s':''?> encontrado<?=$total!=1?'s':''?></h3></div>
    <div class="tabla-wrapper">
      <table class="tabla">
        <thead><tr><th>Egresado</th><th>Prom.</th><th>Ciudad</th><th>Estudia</th><th>Trabaja</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody>
          <?php if(empty($egresados)): ?>
            <tr><td colspan="7" class="texto-center texto-muted" style="padding:30px">No se encontraron egresados con esos criterios.</td></tr>
          <?php else: foreach($egresados as $e): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <?php if(!empty($e['foto'])): ?><img src="<?=BASE_URL?>uploads/fotos/<?=limpiar($e['foto'])?>" class="foto-perfil-sm" alt="">
                <?php else: ?><div class="avatar avatar-sm"><?=strtoupper(substr($e['nombres']??'E',0,1))?></div><?php endif; ?>
                <div><strong><?=limpiar($e['nombres'].' '.$e['apellidos'])?></strong><br><small>Doc: <?=limpiar($e['documento'])?></small></div>
              </div>
            </td>
            <td><?=(int)$e['anno_graduacion']?></td>
            <td><?=limpiar($e['ciudad']?:'—')?></td>
            <td><?=$e['ne']>0?'<span class="texto-verde">✔</span> <small>'.limpiar($e['uc']??'').'</small>':'<span class="texto-muted">—</span>'?></td>
            <td><?=$e['nt']>0?'<span class="texto-verde">✔</span> <small>'.limpiar($e['ca']??'').'</small>':'<span class="texto-muted">—</span>'?></td>
            <td>
              <form method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
                <input type="hidden" name="accion" value="cambiar_estado">
                <input type="hidden" name="uid" value="<?=$e['id']?>">
                <select name="estado" class="estado-sel" onchange="this.form.submit()">
                  <?php foreach(['pendiente','verificado','destacado'] as $st): ?>
                    <option value="<?=$st?>" <?=$e['estado']===$st?'selected':''?>><?=ucfirst($st)?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
            <td>
              <div style="display:flex;gap:5px">
                <a href="../perfil.php?uid=<?=$e['id']?>" class="btn btn-sm">👁 Ver</a>
                <button class="btn btn-secundario btn-sm" data-modal-abrir="modal-nota-<?=$e['id']?>">📝</button>
              </div>
              <!-- Modal nota -->
              <div class="modal-overlay" id="modal-nota-<?=$e['id']?>">
                <div class="modal">
                  <div class="modal-header"><h3> Agregar nota — <?=limpiar($e['nombres'])?></h3><button class="modal-cerrar">×</button></div>
                  <form method="POST">
                    <div class="modal-body">
                      <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
                      <input type="hidden" name="accion" value="agregar_nota">
                      <input type="hidden" name="uid" value="<?=$e['id']?>">
                      <div class="form-grupo">
                        <label>Nota de seguimiento</label>
                        <textarea name="nota" class="form-control" rows="4" placeholder="Observaciones, seguimiento, contacto realizado..." required></textarea>
                        <p class="form-ayuda">La nota quedará registrada con la fecha de hoy en el perfil del egresado.</p>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secundario modal-cerrar">Cancelar</button>
                      <button type="submit" class="btn">Guardar nota</button>
                    </div>
                  </form>
                </div>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php if($tp>1): ?>
    <div class="card-footer">
      <div class="paginacion">
        <?php for($p=1;$p<=$tp;$p++): ?>
          <a href="?q=<?=urlencode($buscar)?>&estado=<?=urlencode($estado)?>&anno=<?=$anno?>&pag=<?=$p?>" class="btn btn-sm <?=$p==$page?'':'btn-secundario'?>"><?=$p?></a>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</main>
<?php require_once BASE_DIR . 'includes/footer.php'; ?>
