<?php
define('BASE_DIR', dirname(__DIR__) . '/');
define('BASE_URL', ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST'].'/egresados/');
require_once BASE_DIR . 'config/funciones.php';
iniciarSesion(); requiereLogin('rectoria');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $accion = $_POST['accion']??''; $uid=(int)($_POST['uid']??0);
    if ($accion==='cambiar_estado'&&$uid) {
        $est=$_POST['estado']??'';
        if (in_array($est,['pendiente','verificado','destacado','inactivo'])) {
            $db->prepare("UPDATE usuarios SET estado=? WHERE id=? AND rol='egresado'")->execute([$est,$uid]);
            registrarLog('CAMBIO_ESTADO',"Usuario $uid → $est");
            setFlash('success','Estado actualizado correctamente.');
        }
    }
    if ($accion==='eliminar'&&$uid) {
        $doc=$db->prepare("SELECT documento FROM usuarios WHERE id=?"); $doc->execute([$uid]); $doc=$doc->fetchColumn();
        $db->prepare("DELETE FROM usuarios WHERE id=? AND rol='egresado'")->execute([$uid]);
        if ($doc) $db->prepare("UPDATE egresados_base SET registrado=0 WHERE documento=?")->execute([$doc]);
        registrarLog('ELIMINAR_USUARIO',"Eliminado $uid");
        setFlash('success','Egresado eliminado del sistema.');
    }
    header('Location: egresados.php'); exit;
}

$buscar=$_GET['q']??''; $estado=$_GET['estado']??''; $anno=(int)($_GET['anno']??0);
$page=max(1,(int)($_GET['pag']??1)); $pp=20; $off=($page-1)*$pp;
$where=["u.rol='egresado'"]; $params=[];
if ($buscar) { $where[]="(eb.nombres LIKE ? OR eb.apellidos LIKE ? OR u.documento LIKE ? OR u.email LIKE ?)"; $like="%$buscar%"; $params=array_merge($params,[$like,$like,$like,$like]); }
if ($estado) { $where[]="u.estado=?"; $params[]=$estado; }
if ($anno)   { $where[]="eb.anno_graduacion=?"; $params[]=$anno; }
$wh='WHERE '.implode(' AND ',$where);

$cnt=$db->prepare("SELECT COUNT(*) FROM usuarios u LEFT JOIN egresados_base eb ON eb.documento=u.documento $wh");
$cnt->execute($params); $total=(int)$cnt->fetchColumn(); $tpags=ceil($total/$pp);

$stmt=$db->prepare("SELECT u.id,u.email,u.estado,u.created_at,eb.nombres,eb.apellidos,eb.anno_graduacion,eb.documento,p.ciudad,p.foto,(SELECT COUNT(*) FROM estudios WHERE usuario_id=u.id) AS ne,(SELECT COUNT(*) FROM trabajos WHERE usuario_id=u.id AND actualmente=1) AS nt FROM usuarios u LEFT JOIN egresados_base eb ON eb.documento=u.documento LEFT JOIN perfiles p ON p.usuario_id=u.id $wh ORDER BY u.created_at DESC LIMIT $pp OFFSET $off");
$stmt->execute($params); $egresados=$stmt->fetchAll();
$annos=$db->query("SELECT DISTINCT anno_graduacion FROM egresados_base ORDER BY anno_graduacion DESC")->fetchAll(PDO::FETCH_COLUMN);

$titulo_pagina='Gestión de Egresados'; $nav_activo='egresados';
require_once BASE_DIR . 'includes/header.php';
?>
<main class="eg-main ancho">
  <?= getFlash() ?>
  <div class="flex-entre mb-2">
    <p class="page-title"> Egresados Registrados</p>
    <div class="acciones-rapidas">
      <a href="cargar_base.php" class="btn btn-secundario btn-sm"> Cargar base</a>
      <a href="estadisticas.php?exportar=csv" class="btn btn-verde btn-sm">⬇ Exportar CSV</a>
    </div>
  </div>

  <div class="card mb-3">
    <form method="GET">
      <div class="filtros-barra">
        <input type="text" name="q" class="form-control" placeholder=" Buscar por nombre, documento o correo..." value="<?= limpiar($buscar) ?>" style="flex:1;max-width:340px">
        <select name="estado" class="form-control" style="max-width:180px">
          <option value="">— Todos los estados —</option>
          <?php foreach(['pendiente','verificado','destacado','inactivo'] as $e): ?>
            <option value="<?=$e?>" <?= $estado===$e?'selected':'' ?>><?= ucfirst($e) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="anno" class="form-control" style="max-width:160px">
          <option value="">— Todos los años —</option>
          <?php foreach($annos as $a): ?>
            <option value="<?=$a?>" <?= $anno==$a?'selected':'' ?>>Promoción <?=$a?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-sm">Filtrar</button>
        <a href="egresados.php" class="btn btn-secundario btn-sm">✖ Limpiar</a>
      </div>
    </form>
    <div class="card-header">
      <span class="icono"></span><h3>Resultados: <?= number_format($total) ?> egresado<?= $total!=1?'s':'' ?></h3>
    </div>
    <div class="tabla-wrapper">
      <table class="tabla">
        <thead><tr><th>Egresado</th><th>Documento</th><th>Prom.</th><th>Ciudad</th><th>Estudia</th><th>Trabaja</th><th>Estado</th><th>Registro</th><th>Acciones</th></tr></thead>
        <tbody>
          <?php if (empty($egresados)): ?>
            <tr><td colspan="9" class="texto-center texto-muted" style="padding:30px">No se encontraron egresados con esos filtros.</td></tr>
          <?php else: foreach($egresados as $e): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <?php if (!empty($e['foto'])): ?>
                  <img src="<?=BASE_URL?>uploads/fotos/<?=limpiar($e['foto'])?>" class="foto-perfil-sm" alt="">
                <?php else: ?>
                  <div class="avatar avatar-sm"><?= strtoupper(substr($e['nombres']??'E',0,1)) ?></div>
                <?php endif; ?>
                <div><strong><?= limpiar($e['nombres'].' '.$e['apellidos']) ?></strong><br><small><?= limpiar($e['email']) ?></small></div>
              </div>
            </td>
            <td><?= limpiar($e['documento']) ?></td>
            <td><?= (int)$e['anno_graduacion'] ?></td>
            <td><?= limpiar($e['ciudad']?:'—') ?></td>
            <td class="texto-center"><?= $e['ne']>0?'<span class="texto-verde">✔</span>':'<span class="texto-muted">—</span>' ?></td>
            <td class="texto-center"><?= $e['nt']>0?'<span class="texto-verde">✔</span>':'<span class="texto-muted">—</span>' ?></td>
            <td>
              <form method="POST">
                <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
                <input type="hidden" name="accion" value="cambiar_estado">
                <input type="hidden" name="uid" value="<?=$e['id']?>">
                <select name="estado" class="estado-sel" onchange="this.form.submit()">
                  <?php foreach(['pendiente','verificado','destacado','inactivo'] as $st): ?>
                    <option value="<?=$st?>" <?= $e['estado']===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
            <td><small><?= date('d/m/Y',strtotime($e['created_at'])) ?></small></td>
            <td>
              <div style="display:flex;gap:5px">
                <a href="../perfil.php?uid=<?=$e['id']?>" class="btn btn-sm">👁 Ver</a>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="uid" value="<?=$e['id']?>">
                  <button type="submit" class="btn btn-rojo btn-sm" data-confirmar="¿Eliminar permanentemente a <?=limpiar($e['nombres'])?>? Esta acción no se puede deshacer.">🗑</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($tpags>1): ?>
    <div class="card-footer">
      <div class="paginacion">
        <?php for($p=1;$p<=$tpags;$p++): ?>
          <a href="?q=<?=urlencode($buscar)?>&estado=<?=urlencode($estado)?>&anno=<?=$anno?>&pag=<?=$p?>" class="btn btn-sm <?=$p==$page?'':'btn-secundario'?>"><?=$p?></a>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</main>
<?php require_once BASE_DIR . 'includes/footer.php'; ?>
