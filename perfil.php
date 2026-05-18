<?php
define('BASE_DIR', __DIR__ . '/');
define('BASE_URL', ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST'].'/egresados/');
require_once BASE_DIR . 'config/funciones.php';
iniciarSesion();
requiereLogin();

$db  = getDB();
$uid = (int)$_SESSION['usuario_id'];

$ver_uid    = $uid;
if (in_array($_SESSION['rol'], ['rectoria','comite']) && isset($_GET['uid'])) {
    $ver_uid = (int)$_GET['uid'];
}
$solo_lectura = ($ver_uid !== $uid);
$tab = $_GET['tab'] ?? 'personal';

$stmt = $db->prepare("
    SELECT u.*, p.*,
           eb.nombres, eb.apellidos, eb.anno_graduacion, eb.fecha_nacimiento
    FROM usuarios u
    LEFT JOIN perfiles p        ON p.usuario_id = u.id
    LEFT JOIN egresados_base eb ON eb.documento  = u.documento
    WHERE u.id = ?
");
$stmt->execute([$ver_uid]);
$user = $stmt->fetch();
if (!$user) { header('Location: '.BASE_URL.'index.php'); exit; }

$estudios = $db->prepare("SELECT * FROM estudios WHERE usuario_id=? ORDER BY anno_inicio DESC"); $estudios->execute([$ver_uid]); $estudios=$estudios->fetchAll();
$trabajos = $db->prepare("SELECT * FROM trabajos  WHERE usuario_id=? ORDER BY actualmente DESC, anno_inicio DESC"); $trabajos->execute([$ver_uid]); $trabajos=$trabajos->fetchAll();
$redes    = $db->prepare("SELECT * FROM redes_sociales WHERE usuario_id=?"); $redes->execute([$ver_uid]); $redes=$redes->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$solo_lectura) {
    validarCsrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'personal') {
        $foto_actual = $user['foto'] ?? null;
        if (!empty($_FILES['foto']['tmp_name'])) {
            $res = subirFoto($_FILES['foto'], $ver_uid);
            if ($res['ok']) {
                if ($foto_actual && file_exists(BASE_DIR.'uploads/fotos/'.$foto_actual)) unlink(BASE_DIR.'uploads/fotos/'.$foto_actual);
                $foto_actual = $res['nombre'];
            } else { setFlash('error', $res['msg']); }
        }
        $db->prepare("UPDATE perfiles SET foto=?,tipo_documento=?,genero=?,telefono=?,ciudad=?,pais_nacimiento=?,direccion=?,logros=? WHERE usuario_id=?")
           ->execute([$foto_actual,$_POST['tipo_documento']??'CC',$_POST['genero']??'',trim($_POST['telefono']??''),trim($_POST['ciudad']??''),trim($_POST['pais_nacimiento']??'Colombia'),trim($_POST['direccion']??''),trim($_POST['logros']??''),$ver_uid]);
        registrarLog('PERFIL_PERSONAL'); setFlash('success','Información personal actualizada correctamente.');
        header('Location: perfil.php?tab=personal'); exit;
    }
    if ($accion === 'estudio_add') {
        $inst=$_POST['institucion']??''; $car=$_POST['carrera']??''; $ini=(int)($_POST['anno_inicio']??0);
        $enc=($_POST['en_curso']??'')==='1'?1:0; $fin=$enc?null:((int)($_POST['anno_fin']??0)?:null);
        if ($inst&&$car&&$ini) {
            $db->prepare("INSERT INTO estudios (usuario_id,institucion,carrera,anno_inicio,anno_fin,en_curso,titulo) VALUES (?,?,?,?,?,?,?)")
               ->execute([$ver_uid,trim($inst),trim($car),$ini,$fin,$enc,trim($_POST['titulo']??'')]);
            setFlash('success','Estudio agregado correctamente.');
        } else { setFlash('error','Complete los campos obligatorios.'); }
        header('Location: perfil.php?tab=estudio'); exit;
    }
    if ($accion === 'estudio_del') {
        $db->prepare("DELETE FROM estudios WHERE id=? AND usuario_id=?")->execute([(int)$_POST['estudio_id'],$ver_uid]);
        setFlash('success','Estudio eliminado.'); header('Location: perfil.php?tab=estudio'); exit;
    }
    if ($accion === 'trabajo_add') {
        $db->prepare("INSERT INTO trabajos (usuario_id,empresa,cargo,area,actualmente,descripcion) VALUES (?,?,?,?,?,?)")
           ->execute([$ver_uid,trim($_POST['empresa']??''),trim($_POST['cargo']??''),trim($_POST['area']??''),($_POST['actualmente']??'')==='1'?1:0,trim($_POST['descripcion']??'')]);
        setFlash('success','Información laboral guardada.'); header('Location: perfil.php?tab=trabajo'); exit;
    }
    if ($accion === 'trabajo_del') {
        $db->prepare("DELETE FROM trabajos WHERE id=? AND usuario_id=?")->execute([(int)$_POST['trabajo_id'],$ver_uid]);
        setFlash('success','Registro laboral eliminado.'); header('Location: perfil.php?tab=trabajo'); exit;
    }
    if ($accion === 'red_add') {
        $red=trim($_POST['red']??''); $url=trim($_POST['url']??'');
        if ($red&&$url) { $db->prepare("INSERT INTO redes_sociales (usuario_id,red,url) VALUES (?,?,?)")->execute([$ver_uid,$red,$url]); setFlash('success','Red social agregada.'); }
        header('Location: perfil.php?tab=redes'); exit;
    }
    if ($accion === 'red_del') {
        $db->prepare("DELETE FROM redes_sociales WHERE id=? AND usuario_id=?")->execute([(int)$_POST['red_id'],$ver_uid]);
        setFlash('success','Red social eliminada.'); header('Location: perfil.php?tab=redes'); exit;
    }
    if ($accion === 'password') {
        $actual=$_POST['pass_actual']??''; $nueva=$_POST['pass_nueva']??''; $conf=$_POST['pass_conf']??'';
        if (!verificarPassword($actual,$user['password'])) { setFlash('error','La contraseña actual no es correcta.'); }
        elseif (!passwordSegura($nueva))                    { setFlash('error','La nueva contraseña debe tener 8+ caracteres, mayúscula y número.'); }
        elseif ($nueva !== $conf)                           { setFlash('error','Las contraseñas no coinciden.'); }
        else { $db->prepare("UPDATE usuarios SET password=? WHERE id=?")->execute([hashPassword($nueva),$ver_uid]); setFlash('success','Contraseña actualizada correctamente.'); registrarLog('CAMBIO_PASSWORD'); }
        header('Location: perfil.php?tab=password'); exit;
    }
}

$edad = !empty($user['fecha_nacimiento']) ? calcularEdad($user['fecha_nacimiento']) : '—';
$titulo_pagina = 'Mi Perfil';
$nav_activo    = 'perfil';
require_once BASE_DIR . 'includes/header.php';
?>
<main class="eg-main ancho">
  <?= getFlash() ?>

  <!-- Cabecera de perfil -->
  <div class="card mb-3">
    <div class="perfil-cabecera">
      <?php if (!empty($user['foto'])): ?>
        <img src="<?= BASE_URL ?>uploads/fotos/<?= limpiar($user['foto']) ?>" alt="Foto" class="foto-perfil">
      <?php else: ?>
        <div class="foto-placeholder"><?= strtoupper(substr($user['nombres']??'E',0,1)) ?></div>
      <?php endif; ?>
      <div class="info-nombre">
        <h2><?= limpiar($user['nombres'].' '.$user['apellidos']) ?></h2>
        <p>Promoción <?= (int)$user['anno_graduacion'] ?> &nbsp;·&nbsp; <?= limpiar($user['email']) ?></p>
        <p>📍 <?= limpiar($user['ciudad'] ?: 'Ciudad no registrada') ?> &nbsp;·&nbsp; 📞 <?= limpiar($user['telefono'] ?: 'Sin teléfono') ?></p>
        <span class="badge badge-<?= $user['estado'] ?>"><?= ucfirst($user['estado']) ?></span>
      </div>
      <?php if (!$solo_lectura): ?>
        <a href="dashboard.php" class="btn btn-secundario btn-sm ml-auto">← Volver</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tabs -->
  <div class="card">
    <div class="card-body" style="padding-bottom:0">
      <div class="tabs" data-tabs>
        <button class="tab-btn <?= $tab==='personal' ?'activo':'' ?>" data-tab="tab-personal">👤 Personal</button>
        <button class="tab-btn <?= $tab==='estudio'  ?'activo':'' ?>" data-tab="tab-estudio">🎓 Estudios</button>
        <button class="tab-btn <?= $tab==='trabajo'  ?'activo':'' ?>" data-tab="tab-trabajo">💼 Trabajo</button>
        <button class="tab-btn <?= $tab==='redes'    ?'activo':'' ?>" data-tab="tab-redes">🌐 Redes</button>
        <?php if (!$solo_lectura): ?>
          <button class="tab-btn <?= $tab==='password'?'activo':'' ?>" data-tab="tab-password">🔒 Contraseña</button>
        <?php endif; ?>
      </div>

      <!-- TAB: PERSONAL -->
      <div id="tab-personal" class="tab-panel <?= $tab==='personal'?'activo':'' ?>">
        <?php if (!$solo_lectura): ?>
        <form method="POST" action="" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="accion"     value="personal">
          <p class="seccion-titulo">Foto de Perfil</p>
          <div class="form-grupo">
            <label class="upload-zona" for="foto-input">
              <input type="file" id="foto-input" name="foto" accept="image/jpeg,image/png,image/webp">
              📷 Haz clic para seleccionar foto (JPG, PNG, WEBP · máx. 3 MB)
              <?php if (!empty($user['foto'])): ?><br><small class="texto-azul">Foto actual cargada ✔</small><?php endif; ?>
            </label>
          </div>
          <p class="seccion-titulo">Datos Básicos</p>
          <div class="form-fila">
            <div class="form-grupo"><label>Nombres</label><input class="form-control readonly" value="<?= limpiar($user['nombres']) ?>" readonly></div>
            <div class="form-grupo"><label>Apellidos</label><input class="form-control readonly" value="<?= limpiar($user['apellidos']) ?>" readonly></div>
          </div>
          <div class="form-fila">
            <div class="form-grupo">
              <label>Tipo documento</label>
              <select name="tipo_documento" class="form-control">
                <?php foreach(['CC','TI','CE','PA','NIT'] as $td): ?>
                  <option value="<?=$td?>" <?= ($user['tipo_documento']??'')===$td?'selected':'' ?>><?=$td?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-grupo"><label>Número de documento</label><input class="form-control readonly" value="<?= limpiar($user['documento']) ?>" readonly></div>
          </div>
          <div class="form-fila">
            <div class="form-grupo"><label>Fecha de nacimiento</label><input class="form-control readonly" value="<?= $user['fecha_nacimiento'] ?>" readonly></div>
            <div class="form-grupo"><label>Edad calculada</label><input class="form-control readonly" value="<?= $edad ?> años" readonly></div>
          </div>
          <div class="form-fila">
            <div class="form-grupo">
              <label>Género</label>
              <select name="genero" class="form-control">
                <option value="">— Seleccione —</option>
                <?php foreach(['Masculino','Femenino','Otro','Prefiero no decir'] as $g): ?>
                  <option value="<?=$g?>" <?= ($user['genero']??'')===$g?'selected':'' ?>><?=$g?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-grupo"><label>Teléfono</label><input type="tel" name="telefono" class="form-control" value="<?= limpiar($user['telefono']??'') ?>" placeholder="300 000 0000"></div>
          </div>
          <div class="form-fila">
            <div class="form-grupo"><label>Ciudad de residencia</label><input type="text" name="ciudad" class="form-control" value="<?= limpiar($user['ciudad']??'') ?>" placeholder="Medellín"></div>
            <div class="form-grupo"><label>País de nacimiento</label><input type="text" name="pais_nacimiento" class="form-control" value="<?= limpiar($user['pais_nacimiento']??'Colombia') ?>"></div>
          </div>
          <div class="form-grupo"><label>Dirección</label><input type="text" name="direccion" class="form-control" value="<?= limpiar($user['direccion']??'') ?>" placeholder="Calle, número, barrio"></div>
          <p class="seccion-titulo">Logros Importantes</p>
          <div class="form-grupo">
            <label>Logros, reconocimientos o méritos destacados</label>
            <textarea name="logros" class="form-control" rows="4" placeholder="Describe tus logros más importantes después del colegio..."><?= limpiar($user['logros']??'') ?></textarea>
          </div>
          <button type="submit" class="btn mb-2">💾 Guardar información personal</button>
        </form>
        <?php else: ?>
        <div class="info-grid mt-2">
          <div class="info-item"><label>Nombre completo</label><p><?= limpiar($user['nombres'].' '.$user['apellidos']) ?></p></div>
          <div class="info-item"><label>Documento</label><p><?= limpiar(($user['tipo_documento']??'CC').': '.$user['documento']) ?></p></div>
          <div class="info-item"><label>Fecha Nacimiento</label><p><?= $user['fecha_nacimiento'] ?> (<?= $edad ?> años)</p></div>
          <div class="info-item"><label>Género</label><p><?= limpiar($user['genero']?:'—') ?></p></div>
          <div class="info-item"><label>Teléfono</label><p><?= limpiar($user['telefono']?:'—') ?></p></div>
          <div class="info-item"><label>Ciudad</label><p><?= limpiar($user['ciudad']?:'—') ?></p></div>
          <div class="info-item"><label>País de nacimiento</label><p><?= limpiar($user['pais_nacimiento']?:'—') ?></p></div>
          <div class="info-item"><label>Correo</label><p><?= limpiar($user['email']) ?></p></div>
        </div>
        <?php if (!empty($user['logros'])): ?>
          <p class="seccion-titulo mt-2">Logros</p>
          <p><?= nl2br(limpiar($user['logros'])) ?></p>
        <?php endif; ?>
        <?php endif; ?>
      </div>

      <!-- TAB: ESTUDIOS -->
      <div id="tab-estudio" class="tab-panel <?= $tab==='estudio'?'activo':'' ?>">
        <?php if (!empty($estudios)): ?>
        <div class="tabla-wrapper mb-2">
          <table class="tabla">
            <thead><tr><th>Institución</th><th>Carrera</th><th>Inicio</th><th>Fin</th><th>Título</th><?php if(!$solo_lectura):?><th>Acción</th><?php endif;?></tr></thead>
            <tbody>
              <?php foreach ($estudios as $e): ?>
              <tr>
                <td><?= limpiar($e['institucion']) ?></td>
                <td><?= limpiar($e['carrera']) ?></td>
                <td><?= $e['anno_inicio'] ?></td>
                <td><?= $e['en_curso'] ? '<span class="badge badge-verificado">En curso</span>' : $e['anno_fin'] ?></td>
                <td><?= limpiar($e['titulo']?:'—') ?></td>
                <?php if (!$solo_lectura): ?>
                <td>
                  <form method="POST"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="accion" value="estudio_del"><input type="hidden" name="estudio_id" value="<?=$e['id']?>">
                  <button type="submit" class="btn btn-rojo btn-sm" data-confirmar="¿Eliminar este estudio?">✖ Eliminar</button></form>
                </td>
                <?php endif; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
          <p class="texto-center texto-muted mt-2 mb-2">No hay estudios registrados aún.</p>
        <?php endif; ?>

        <?php if (!$solo_lectura): ?>
        <p class="seccion-titulo">Agregar Estudio</p>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="accion"     value="estudio_add">
          <div class="form-fila">
            <div class="form-grupo"><label>Institución <span class="req">*</span></label><input type="text" name="institucion" class="form-control" placeholder="Universidad de Antioquia" required></div>
            <div class="form-grupo"><label>Carrera / Programa <span class="req">*</span></label><input type="text" name="carrera" class="form-control" placeholder="Ingeniería de Sistemas" required></div>
          </div>
          <div class="form-fila-3">
            <div class="form-grupo"><label>Año inicio <span class="req">*</span></label><input type="number" name="anno_inicio" class="form-control" min="1990" max="<?=date('Y')?>" placeholder="<?=date('Y')?>" required></div>
            <div class="form-grupo"><label>Año finalización</label><input type="number" name="anno_fin" id="af" class="form-control" min="1990" max="<?=date('Y')+6?>" placeholder="<?=date('Y')?>"></div>
            <div class="form-grupo"><label>&nbsp;</label>
              <label class="check-label"><input type="checkbox" name="en_curso" id="enc" value="1" onchange="document.getElementById('af').disabled=this.checked"> Actualmente en curso</label>
            </div>
          </div>
          <div class="form-grupo"><label>Título obtenido o a obtener</label><input type="text" name="titulo" class="form-control" placeholder="Ingeniero de Sistemas, Tecnólogo..."></div>
          <button type="submit" class="btn mb-2">➕ Agregar estudio</button>
        </form>
        <?php endif; ?>
      </div>

      <!-- TAB: TRABAJO -->
      <div id="tab-trabajo" class="tab-panel <?= $tab==='trabajo'?'activo':'' ?>">
        <?php if (!empty($trabajos)): ?>
          <?php foreach ($trabajos as $t): ?>
          <div class="eg-card-lista">
            <span style="font-size:1.5rem">💼</span>
            <div>
              <div class="eg-nombre"><?= limpiar($t['cargo']?:'Sin cargo') ?><?= $t['actualmente']?' <span class="badge badge-verificado">Actual</span>':'' ?></div>
              <div class="eg-sub"><?= limpiar($t['empresa']?:'—') ?><?= $t['area']?' · '.limpiar($t['area']):'' ?></div>
              <?php if ($t['descripcion']): ?><p style="font-size:.82rem;margin-top:4px"><?= nl2br(limpiar($t['descripcion'])) ?></p><?php endif; ?>
            </div>
            <?php if (!$solo_lectura): ?>
            <form method="POST"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="accion" value="trabajo_del"><input type="hidden" name="trabajo_id" value="<?=$t['id']?>">
            <button type="submit" class="btn btn-rojo btn-sm" data-confirmar="¿Eliminar este registro laboral?">✖</button></form>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="texto-center texto-muted mt-2 mb-2">No hay información laboral registrada.</p>
        <?php endif; ?>

        <?php if (!$solo_lectura): ?>
        <p class="seccion-titulo mt-2">Agregar Trabajo</p>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="accion"     value="trabajo_add">
          <div class="form-fila">
            <div class="form-grupo"><label>Empresa / Organización</label><input type="text" name="empresa" class="form-control" placeholder="Nombre de la empresa"></div>
            <div class="form-grupo"><label>Cargo</label><input type="text" name="cargo" class="form-control" placeholder="Analista, Desarrollador..."></div>
          </div>
          <div class="form-fila">
            <div class="form-grupo"><label>Área / Departamento</label><input type="text" name="area" class="form-control" placeholder="Tecnología, Ventas..."></div>
            <div class="form-grupo"><label>&nbsp;</label>
              <label class="check-label"><input type="checkbox" name="actualmente" value="1"> Trabajo actual</label>
            </div>
          </div>
          <div class="form-grupo"><label>Descripción de actividades</label><textarea name="descripcion" class="form-control" rows="3" placeholder="Describe brevemente tus funciones..."></textarea></div>
          <button type="submit" class="btn mb-2">➕ Agregar trabajo</button>
        </form>
        <?php endif; ?>
      </div>

      <!-- TAB: REDES -->
      <div id="tab-redes" class="tab-panel <?= $tab==='redes'?'activo':'' ?>">
        <?php if (!empty($redes)): ?>
          <?php foreach ($redes as $r): ?>
          <div class="eg-card-lista">
            <span style="font-size:1.4rem">🌐</span>
            <div>
              <div class="eg-nombre"><?= limpiar($r['red']) ?></div>
              <div class="eg-sub"><a href="<?= limpiar($r['url']) ?>" target="_blank" rel="noopener"><?= limpiar($r['url']) ?></a></div>
            </div>
            <?php if (!$solo_lectura): ?>
            <form method="POST"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="accion" value="red_del"><input type="hidden" name="red_id" value="<?=$r['id']?>">
            <button type="submit" class="btn btn-rojo btn-sm" data-confirmar="¿Eliminar esta red social?">✖</button></form>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="texto-center texto-muted mt-2 mb-2">No hay redes sociales registradas.</p>
        <?php endif; ?>

        <?php if (!$solo_lectura): ?>
        <p class="seccion-titulo mt-2">Agregar Red Social</p>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="accion"     value="red_add">
          <div class="form-fila">
            <div class="form-grupo"><label>Red social</label>
              <select name="red" class="form-control">
                <option value="">— Seleccione —</option>
                <?php foreach(['LinkedIn','Instagram','Facebook','Twitter/X','GitHub','YouTube','TikTok','Otra'] as $r2): ?>
                  <option value="<?=$r2?>"><?=$r2?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-grupo"><label>URL / Enlace</label><input type="url" name="url" class="form-control" placeholder="https://linkedin.com/in/..."></div>
          </div>
          <button type="submit" class="btn mb-2">➕ Agregar red social</button>
        </form>
        <?php endif; ?>
      </div>

      <!-- TAB: CONTRASEÑA -->
      <?php if (!$solo_lectura): ?>
      <div id="tab-password" class="tab-panel <?= $tab==='password'?'activo':'' ?>">
        <div style="max-width:440px">
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="accion"     value="password">
            <div class="form-grupo"><label>Contraseña actual <span class="req">*</span></label><input type="password" name="pass_actual" class="form-control" required></div>
            <div class="form-grupo"><label>Nueva contraseña <span class="req">*</span></label><input type="password" name="pass_nueva" class="form-control" placeholder="Mínimo 8 caracteres" required><p class="form-ayuda">Mínimo 8 caracteres, una mayúscula y un número.</p></div>
            <div class="form-grupo"><label>Confirmar nueva contraseña <span class="req">*</span></label><input type="password" name="pass_conf" class="form-control" required></div>
            <button type="submit" class="btn mb-2">🔒 Actualizar contraseña</button>
          </form>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</main>
<script>
const tabParam = new URLSearchParams(location.search).get('tab');
if (tabParam) {
  document.querySelectorAll('.tab-btn').forEach(b => {
    if (b.dataset.tab === 'tab-' + tabParam) b.click();
  });
}
</script>
<?php require_once BASE_DIR . 'includes/footer.php'; ?>
