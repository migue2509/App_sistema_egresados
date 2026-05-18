<?php
define('BASE_DIR', dirname(__DIR__) . '/');
define('BASE_URL', ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST'].'/egresados/');
require_once BASE_DIR . 'config/funciones.php';
iniciarSesion(); requiereLogin('rectoria');
$db=$getDB=getDB(); $uid_yo=(int)$_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    validarCsrf();
    $accion=$_POST['accion']??''; $uid=(int)($_POST['uid']??0);

    if ($accion==='crear_usuario') {
        $doc=soloNumeros($_POST['documento']??''); $email=trim($_POST['email']??'');
        $rol=$_POST['rol']??''; $pass=$_POST['password']??'';
        if (!$doc||!$email||!in_array($rol,['comite','rectoria'])||!$pass) { setFlash('error','Complete todos los campos.'); }
        elseif (!filter_var($email,FILTER_VALIDATE_EMAIL)) { setFlash('error','Correo inválido.'); }
        elseif (!passwordSegura($pass)) { setFlash('error','La contraseña debe tener 8+ caracteres, mayúscula y número.'); }
        else {
            $ch=$db->prepare("SELECT id FROM usuarios WHERE email=? OR documento=?"); $ch->execute([$email,$doc]);
            if ($ch->fetch()) { setFlash('error','Ya existe un usuario con ese correo o documento.'); }
            else {
                $db->prepare("INSERT INTO usuarios (documento,email,password,rol,estado) VALUES (?,?,?,?,'verificado')")->execute([$doc,$email,hashPassword($pass),$rol]);
                $nid=$db->lastInsertId();
                $db->prepare("INSERT INTO perfiles (usuario_id) VALUES (?)")->execute([$nid]);
                registrarLog('CREAR_USUARIO',"Nuevo $rol: $email"); setFlash('success','Usuario creado correctamente.');
            }
        }
        header('Location: usuarios.php'); exit;
    }
    if ($accion==='cambiar_estado_u'&&$uid&&$uid!==$uid_yo) {
        $est=$_POST['estado']??'';
        if (in_array($est,['pendiente','verificado','destacado','inactivo'])) { $db->prepare("UPDATE usuarios SET estado=? WHERE id=?")->execute([$est,$uid]); setFlash('success','Estado actualizado.'); }
        header('Location: usuarios.php'); exit;
    }
    if ($accion==='reset_pass'&&$uid) {
        $np=$_POST['nueva_pass']??'';
        if (!passwordSegura($np)) { setFlash('error','La contraseña debe tener 8+ caracteres, mayúscula y número.'); }
        else { $db->prepare("UPDATE usuarios SET password=? WHERE id=?")->execute([hashPassword($np),$uid]); setFlash('success','Contraseña restablecida correctamente.'); }
        header('Location: usuarios.php'); exit;
    }
    if ($accion==='eliminar'&&$uid&&$uid!==$uid_yo) {
        $cr=$db->prepare("SELECT rol FROM usuarios WHERE id=?"); $cr->execute([$uid]); $rr=$cr->fetch();
        if ($rr&&$rr['rol']!=='rectoria') { $db->prepare("DELETE FROM usuarios WHERE id=?")->execute([$uid]); setFlash('success','Usuario eliminado.'); }
        else { setFlash('error','No se puede eliminar a un administrador de rectoría.'); }
        header('Location: usuarios.php'); exit;
    }
}

$fr=$_GET['rol']??'';
$wh=$fr?"WHERE u.rol=".$db->quote($fr):"";
$usuarios=$db->query("SELECT u.id,u.documento,u.email,u.rol,u.estado,u.created_at,u.last_login,eb.nombres,eb.apellidos FROM usuarios u LEFT JOIN egresados_base eb ON eb.documento=u.documento $wh ORDER BY FIELD(u.rol,'rectoria','comite','egresado'),u.created_at DESC")->fetchAll();

$titulo_pagina='Gestión de Usuarios'; $nav_activo='usuarios';
require_once BASE_DIR . 'includes/header.php';
?>
<main class="eg-main ancho">
  <?= getFlash() ?>
  <div class="flex-entre mb-2">
    <p class="page-title"> Gestión de Usuarios del Sistema</p>
    <button class="btn btn-sm" data-modal-abrir="modal-crear">+ Nuevo usuario</button>
  </div>

  <!-- Filtro rol -->
  <div class="card mb-3">
    <div class="card-body" style="padding:12px 18px">
      <div class="acciones-rapidas">
        <a href="usuarios.php" class="btn btn-sm <?= !$fr?'':'btn-secundario' ?>">Todos</a>
        <a href="?rol=rectoria" class="btn btn-sm <?= $fr==='rectoria'?'':'btn-secundario' ?>"> Rectoría</a>
        <a href="?rol=comite"   class="btn btn-sm <?= $fr==='comite'?'':'btn-secundario' ?>"> Comité</a>
        <a href="?rol=egresado" class="btn btn-sm <?= $fr==='egresado'?'':'btn-secundario' ?>"> Egresados</a>
      </div>
    </div>
  </div>

  <!-- Tabla -->
  <div class="card">
    <div class="card-header"><span class="icono"></span><h3><?= count($usuarios) ?> usuario<?= count($usuarios)!=1?'s':'' ?></h3></div>
    <div class="tabla-wrapper">
      <table class="tabla">
        <thead><tr><th>Usuario</th><th>Documento</th><th>Rol</th><th>Estado</th><th>Último acceso</th><th>Acciones</th></tr></thead>
        <tbody>
          <?php foreach($usuarios as $u):
            $es_yo=($u['id']===$uid_yo);
            $nombre=$u['nombres']?limpiar($u['nombres'].' '.$u['apellidos']):limpiar($u['email']);
            $icons=['rectoria'=>'🏛','comite'=>'👥','egresado'=>'🎓'];
          ?>
          <tr <?= $es_yo?'class="fila-yo"':'' ?>>
            <td>
              <strong><?=$nombre?></strong>
              <?php if($es_yo): ?> <span class="badge badge-destacado">Tú</span><?php endif; ?><br>
              <small><?=limpiar($u['email'])?></small>
            </td>
            <td><?=limpiar($u['documento'])?></td>
            <td><?=($icons[$u['rol']]??'').' '.ucfirst($u['rol'])?></td>
            <td>
              <?php if (!$es_yo): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
                <input type="hidden" name="accion" value="cambiar_estado_u">
                <input type="hidden" name="uid" value="<?=$u['id']?>">
                <select name="estado" class="estado-sel" onchange="this.form.submit()">
                  <?php foreach(['pendiente','verificado','destacado','inactivo'] as $st): ?>
                    <option value="<?=$st?>" <?=$u['estado']===$st?'selected':''?>><?=ucfirst($st)?></option>
                  <?php endforeach; ?>
                </select>
              </form>
              <?php else: ?><span class="badge badge-<?=$u['estado']?>"><?=ucfirst($u['estado'])?></span><?php endif; ?>
            </td>
            <td><small><?=$u['last_login']?date('d/m/Y H:i',strtotime($u['last_login'])):'Nunca'?></small></td>
            <td>
              <?php if (!$es_yo): ?>
              <div style="display:flex;gap:5px;flex-wrap:wrap">
                <?php if($u['rol']==='egresado'): ?>
                  <a href="../perfil.php?uid=<?=$u['id']?>" class="btn btn-sm">👁 Ver</a>
                <?php endif; ?>
                <button class="btn btn-secundario btn-sm" data-modal-abrir="modal-reset-<?=$u['id']?>">🔑 Reset</button>
                <?php if($u['rol']!=='rectoria'): ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="uid" value="<?=$u['id']?>">
                  <button type="submit" class="btn btn-rojo btn-sm" data-confirmar="¿Eliminar a <?=limpiar($u['email'])?>?">🗑</button>
                </form>
                <?php endif; ?>
              </div>
              <!-- Modal reset -->
              <div class="modal-overlay" id="modal-reset-<?=$u['id']?>">
                <div class="modal">
                  <div class="modal-header"><h3> Restablecer contraseña — <?=limpiar($u['email'])?></h3><button class="modal-cerrar">×</button></div>
                  <form method="POST">
                    <div class="modal-body">
                      <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
                      <input type="hidden" name="accion" value="reset_pass">
                      <input type="hidden" name="uid" value="<?=$u['id']?>">
                      <div class="form-grupo"><label>Nueva contraseña</label><input type="password" name="nueva_pass" class="form-control" placeholder="Mínimo 8 caracteres, mayúscula y número" required></div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secundario modal-cerrar">Cancelar</button>
                      <button type="submit" class="btn">Restablecer</button>
                    </div>
                  </form>
                </div>
              </div>
              <?php else: ?><span class="texto-muted" style="font-size:.78rem">— cuenta actual —</span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- Modal crear usuario -->
<div class="modal-overlay" id="modal-crear">
  <div class="modal">
    <div class="modal-header"><h3> Crear nuevo usuario</h3><button class="modal-cerrar">×</button></div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
        <input type="hidden" name="accion" value="crear_usuario">
        <div class="form-grupo"><label>Documento <span class="req">*</span></label><input type="text" name="documento" class="form-control" placeholder="Número de cédula" required></div>
        <div class="form-grupo"><label>Correo electrónico <span class="req">*</span></label><input type="email" name="email" class="form-control" placeholder="correo@dinamarca.edu.co" required></div>
        <div class="form-grupo"><label>Rol <span class="req">*</span></label>
          <select name="rol" class="form-control" required>
            <option value="comite"> Comité</option>
            <option value="rectoria"> Rectoría</option>
          </select>
        </div>
        <div class="form-grupo"><label>Contraseña temporal <span class="req">*</span></label><input type="password" name="password" class="form-control" placeholder="Mínimo 8 caracteres, mayúscula y número" required></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secundario modal-cerrar">Cancelar</button>
        <button type="submit" class="btn">Crear usuario</button>
      </div>
    </form>
  </div>
</div>
<?php require_once BASE_DIR . 'includes/footer.php'; ?>
