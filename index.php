<?php
define('BASE_DIR', __DIR__ . '/');
define('BASE_URL', ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST'].'/egresados/');
require_once BASE_DIR . 'config/funciones.php';
iniciarSesion();
if (sesionActiva()) redirigirSegunRol();

$error = '';
$msg   = match($_GET['msg'] ?? '') {
    'session'    => 'Su sesión ha expirado. Por favor inicie sesión de nuevo.',
    'permisos'   => 'No tiene permisos para acceder a esa sección.',
    'registrado' => '¡Registro exitoso! Ya puede iniciar sesión con su correo.',
    'logout'     => 'Ha cerrado sesión correctamente.',
    default      => ''
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    if (!$email || !$password) {
        $error = 'Por favor complete todos los campos.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ? AND estado != 'inactivo'");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();
        if ($usuario && verificarPassword($password, $usuario['password'])) {
            $db->prepare("UPDATE usuarios SET last_login = NOW() WHERE id = ?")->execute([$usuario['id']]);
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['rol']        = $usuario['rol'];
            $_SESSION['documento']  = $usuario['documento'];
            registrarLog('LOGIN', 'Acceso exitoso');
            redirigirSegunRol();
        } else {
            $error = 'Correo o contraseña incorrectos, o cuenta inactiva.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar Sesión — Egresados I.E. Dinamarca</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>css/egresados.css">
</head>
<body>
<div class="login-page">
  <div class="login-box">
    <div class="login-header">
      <div class="login-logo">
        <img src="<?= BASE_URL ?>imagenes/escudoAnimacion-unscreen.gif"
             alt="Escudo" style="width:100%;height:100%;object-fit:cover;border-radius:50%"
             onerror="this.parentElement.textContent='D'">
      </div>
      <h2>Sistema de Egresados</h2>
      <p>I.E. Dinamarca &mdash; Medellín</p>
    </div>

    <div class="login-body">
      <?php if ($msg): ?>
        <div class="alerta alerta-info">ℹ <?= limpiar($msg) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alerta alerta-error">✖ <?= limpiar($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="form-grupo">
          <label for="email">Correo electrónico <span class="req">*</span></label>
          <input type="email" id="email" name="email" class="form-control"
                 value="<?= limpiar($_POST['email'] ?? '') ?>"
                 placeholder="correo@ejemplo.com" required autofocus>
        </div>
        <div class="form-grupo">
          <label for="password">Contraseña <span class="req">*</span></label>
          <input type="password" id="password" name="password" class="form-control"
                 placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn btn-bloque mt-2"> Iniciar Sesión</button>
      </form>
    </div>

    <div class="login-footer">
      ¿Es egresado y aún no tiene cuenta?
      <a href="<?= BASE_URL ?>registro.php"><strong>Regístrese aquí</strong></a>
    </div>
  </div>
  <p class="login-pie">© <?= date('Y') ?> I.E. Dinamarca &nbsp;|&nbsp; Plataforma de Egresados</p>
</div>
</body>
</html>
