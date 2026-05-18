<?php
define('BASE_DIR', __DIR__ . '/');
define('BASE_URL', ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST'].'/egresados/');
require_once BASE_DIR . 'config/funciones.php';
iniciarSesion();
if (sesionActiva()) redirigirSegunRol();

$paso    = (int)($_SESSION['reg_paso'] ?? 1);
$error   = '';
$egresado_validado = $_SESSION['reg_egresado'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validar_identidad'])) {
    validarCsrf();
    $doc      = soloNumeros($_POST['documento'] ?? '');
    $fechaNac = trim($_POST['fecha_nacimiento'] ?? '');
    $annoGrad = (int)($_POST['anno_graduacion'] ?? 0);
    if (!$doc || !$fechaNac || !$annoGrad) {
        $error = 'Complete todos los campos de validación.';
    } else {
        $resultado = validarEgresado($doc, $fechaNac, (string)$annoGrad);
        if ($resultado['ok']) {
            $_SESSION['reg_egresado'] = $resultado['egresado'];
            $_SESSION['reg_paso']     = 2;
            $egresado_validado        = $resultado['egresado'];
            $paso = 2;
        } else {
            $error = $resultado['msg'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_cuenta'])) {
    validarCsrf();
    if (!$egresado_validado) { $error = 'Sesión expirada.'; $paso = 1; }
    else {
        $email = trim($_POST['email'] ?? '');
        $pass1 = $_POST['password']  ?? '';
        $pass2 = $_POST['password2'] ?? '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ingrese un correo electrónico válido.';
        } elseif (!passwordSegura($pass1)) {
            $error = 'La contraseña debe tener mínimo 8 caracteres, una mayúscula y un número.';
        } elseif ($pass1 !== $pass2) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            $db = getDB();
            $check = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $error = 'Ese correo ya está registrado en el sistema.';
            } else {
                try {
                    $db->beginTransaction();
                    $db->prepare("INSERT INTO usuarios (documento,email,password,rol,estado) VALUES (?,?,?,'egresado','pendiente')")
                       ->execute([$egresado_validado['documento'], $email, hashPassword($pass1)]);
                    $userId = (int)$db->lastInsertId();
                    $db->prepare("INSERT INTO perfiles (usuario_id,tipo_documento) VALUES (?,'CC')")->execute([$userId]);
                    $db->prepare("UPDATE egresados_base SET registrado=1 WHERE documento=?")->execute([$egresado_validado['documento']]);
                    $db->commit();
                    unset($_SESSION['reg_egresado'], $_SESSION['reg_paso']);
                    header('Location: '.BASE_URL.'index.php?msg=registrado'); exit;
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Error al crear la cuenta. Intente nuevamente.';
                }
            }
        }
    }
}

if (isset($_GET['cancelar'])) {
    unset($_SESSION['reg_egresado'], $_SESSION['reg_paso']);
    header('Location: '.BASE_URL.'registro.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registro de Egresado — I.E. Dinamarca</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>css/egresados.css">
</head>
<body>
<div class="login-page">
  <div class="login-box" style="max-width:480px">
    <div class="login-header">
      <div class="login-logo">
        <img src="<?= BASE_URL ?>imagenes/escudoAnimacion-unscreen.gif"
             alt="Escudo" style="width:100%;height:100%;object-fit:cover;border-radius:50%"
             onerror="this.parentElement.textContent='D'">
      </div>
      <h2>Registro de Egresado</h2>
      <p>I.E. Dinamarca &mdash; <?= date('Y') ?></p>
    </div>

    <div class="login-body">
      <!-- Pasos -->
      <div class="pasos">
        <div class="paso <?= $paso > 1 ? 'ok' : 'activo' ?>">
          <div class="paso-num"><?= $paso > 1 ? '✔' : '1' ?></div>
          <div class="paso-label">Validar Identidad</div>
        </div>
        <div class="paso-linea <?= $paso > 1 ? 'ok' : '' ?>"></div>
        <div class="paso <?= $paso === 2 ? 'activo' : '' ?>">
          <div class="paso-num">2</div>
          <div class="paso-label">Crear Cuenta</div>
        </div>
      </div>

      <?php if ($error): ?>
        <div class="alerta alerta-error">✖ <?= limpiar($error) ?></div>
      <?php endif; ?>

      <?php if ($paso === 1): ?>
      <div class="alerta alerta-info">ℹ Para registrarse, su información debe estar en la base de egresados del colegio.</div>
      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="form-grupo">
          <label>Número de documento <span class="req">*</span></label>
          <input type="text" name="documento" class="form-control"
                 placeholder="Ej: 1001234567" maxlength="20"
                 value="<?= limpiar($_POST['documento'] ?? '') ?>" required>
        </div>
        <div class="form-grupo">
          <label>Fecha de nacimiento <span class="req">*</span></label>
          <input type="date" name="fecha_nacimiento" class="form-control"
                 value="<?= limpiar($_POST['fecha_nacimiento'] ?? '') ?>" required>
        </div>
        <div class="form-grupo">
          <label>Año de graduación <span class="req">*</span></label>
          <select name="anno_graduacion" class="form-control" required>
            <option value="">— Seleccione —</option>
            <?php for ($y = date('Y'); $y >= 1980; $y--): ?>
              <option value="<?= $y ?>" <?= (($_POST['anno_graduacion'] ?? '') == $y ? 'selected' : '') ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <button type="submit" name="validar_identidad" class="btn btn-bloque">✔ Validar Identidad</button>
      </form>

      <?php else: ?>
      <div class="alerta alerta-success">✔ Identidad verificada: <strong><?= limpiar($egresado_validado['nombres'].' '.$egresado_validado['apellidos']) ?></strong> &mdash; Promoción <?= (int)$egresado_validado['anno_graduacion'] ?></div>
      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="form-grupo">
          <label>Correo electrónico <span class="req">*</span></label>
          <input type="email" name="email" class="form-control"
                 placeholder="correo@ejemplo.com"
                 value="<?= limpiar($_POST['email'] ?? '') ?>" required autofocus>
          <p class="form-ayuda">Este correo será su usuario de acceso al sistema.</p>
        </div>
        <div class="form-grupo">
          <label>Contraseña <span class="req">*</span></label>
          <input type="password" name="password" id="pass1" class="form-control"
                 placeholder="Mínimo 8 caracteres" required>
          <p class="form-ayuda">Mínimo 8 caracteres, una mayúscula y un número.</p>
        </div>
        <div class="form-grupo">
          <label>Confirmar contraseña <span class="req">*</span></label>
          <input type="password" name="password2" id="pass2" class="form-control"
                 placeholder="Repita la contraseña" required>
          <div id="pass-msg" class="form-ayuda"></div>
        </div>
        <div style="display:flex;gap:10px">
          <a href="?cancelar=1" class="btn btn-secundario" style="flex:1;justify-content:center">← Atrás</a>
          <button type="submit" name="crear_cuenta" class="btn btn-verde" style="flex:2">✔ Crear mi cuenta</button>
        </div>
      </form>
      <script>
        const p1=document.getElementById('pass1'),p2=document.getElementById('pass2'),msg=document.getElementById('pass-msg');
        function chk(){
          if(!p2.value){msg.textContent='';return;}
          if(p1.value===p2.value){msg.textContent='✔ Las contraseñas coinciden';msg.style.color='#2e7d32';}
          else{msg.textContent='✖ Las contraseñas no coinciden';msg.style.color='#c62828';}
        }
        p1.addEventListener('input',chk); p2.addEventListener('input',chk);
      </script>
      <?php endif; ?>
    </div>

    <div class="login-footer">
      ¿Ya tiene cuenta? <a href="<?= BASE_URL ?>index.php">Iniciar sesión</a>
    </div>
  </div>
  <p class="login-pie">© <?= date('Y') ?> I.E. Dinamarca &nbsp;|&nbsp; Plataforma de Egresados</p>
</div>
</body>
</html>
