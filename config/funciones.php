<?php
// ============================================================
// config/funciones.php — Funciones compartidas del sistema
// I.E. Dinamarca — Sistema de Egresados
// ============================================================

require_once __DIR__ . '/db.php';

// ── Sesión segura ────────────────────────────────────────────
function iniciarSesion(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 7200,
            'path'     => '/',
            'secure'   => false,   // true en producción con HTTPS
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

// ── Autenticación ────────────────────────────────────────────
function requiereLogin(string $rol = ''): void {
    iniciarSesion();
    if (empty($_SESSION['usuario_id'])) {
        header('Location: ' . BASE_URL . 'index.php?msg=session');
        exit;
    }
    if ($rol && $_SESSION['rol'] !== $rol && $_SESSION['rol'] !== 'rectoria') {
        header('Location: ' . BASE_URL . 'index.php?msg=permisos');
        exit;
    }
}

function sesionActiva(): bool {
    iniciarSesion();
    return !empty($_SESSION['usuario_id']);
}

function rolActual(): string {
    return $_SESSION['rol'] ?? '';
}

// ── Sanitización ─────────────────────────────────────────────
function limpiar(string $valor): string {
    return htmlspecialchars(trim($valor), ENT_QUOTES, 'UTF-8');
}

function soloNumeros(string $valor): string {
    return preg_replace('/\D/', '', $valor);
}

// ── Contraseñas ──────────────────────────────────────────────
function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verificarPassword(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

function passwordSegura(string $p): bool {
    // Mínimo 8 caracteres, al menos 1 mayúscula, 1 número
    return strlen($p) >= 8 && preg_match('/[A-Z]/', $p) && preg_match('/[0-9]/', $p);
}

// ── Validación de identidad ──────────────────────────────────
function validarEgresado(string $doc, string $fechaNac, string $annoGrad): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT * FROM egresados_base 
        WHERE documento = ? AND fecha_nacimiento = ? AND anno_graduacion = ?
    ");
    $stmt->execute([$doc, $fechaNac, $annoGrad]);
    $egresado = $stmt->fetch();

    if (!$egresado) {
        return ['ok' => false, 'msg' => 'Los datos no coinciden con nuestra base de egresados.'];
    }
    if ($egresado['registrado']) {
        return ['ok' => false, 'msg' => 'Este documento ya tiene una cuenta registrada.'];
    }
    return ['ok' => true, 'egresado' => $egresado];
}

// ── Usuario actual ────────────────────────────────────────────
function usuarioActual(): array {
    if (empty($_SESSION['usuario_id'])) return [];
    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.*, p.foto, p.genero, p.telefono, p.ciudad,
               eb.nombres, eb.apellidos, eb.anno_graduacion
        FROM usuarios u
        LEFT JOIN perfiles p ON p.usuario_id = u.id
        LEFT JOIN egresados_base eb ON eb.documento = u.documento
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['usuario_id']]);
    return $stmt->fetch() ?: [];
}

// ── Calcular edad ────────────────────────────────────────────
function calcularEdad(string $fechaNac): int {
    return (int) (new DateTime($fechaNac))->diff(new DateTime())->y;
}

// ── Log de acciones ──────────────────────────────────────────
function registrarLog(string $accion, string $detalle = ''): void {
    try {
        $db = getDB();
        $db->prepare("INSERT INTO log_acciones (usuario_id, accion, detalle, ip) VALUES (?,?,?,?)")
           ->execute([
               $_SESSION['usuario_id'] ?? null,
               $accion,
               $detalle,
               $_SERVER['REMOTE_ADDR'] ?? ''
           ]);
    } catch (Exception $e) { /* silencioso */ }
}

// ── Subir foto de perfil ─────────────────────────────────────
function subirFoto(array $archivo, int $usuarioId): array {
    $permitidos = ['image/jpeg', 'image/png', 'image/webp'];
    $maxSize    = 3 * 1024 * 1024; // 3 MB

    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'msg' => 'Error al subir el archivo.'];
    }
    if (!in_array($archivo['type'], $permitidos)) {
        return ['ok' => false, 'msg' => 'Solo se permiten imágenes JPG, PNG o WEBP.'];
    }
    if ($archivo['size'] > $maxSize) {
        return ['ok' => false, 'msg' => 'La imagen no debe superar 3 MB.'];
    }

    $dir  = __DIR__ . '/../uploads/fotos/';
    $ext  = pathinfo($archivo['name'], PATHINFO_EXTENSION);
    $name = 'foto_' . $usuarioId . '_' . time() . '.' . $ext;
    $dest = $dir . $name;

    if (!move_uploaded_file($archivo['tmp_name'], $dest)) {
        return ['ok' => false, 'msg' => 'No se pudo guardar la imagen.'];
    }
    return ['ok' => true, 'nombre' => $name];
}

// ── Redirigir según rol ──────────────────────────────────────
function redirigirSegunRol(): void {
    switch ($_SESSION['rol']) {
        case 'rectoria': header('Location: ' . BASE_URL . 'admin/index.php'); break;
        case 'comite':   header('Location: ' . BASE_URL . 'comite/index.php'); break;
        default:         header('Location: ' . BASE_URL . 'dashboard.php'); break;
    }
    exit;
}

// ── Alerta flash ─────────────────────────────────────────────
function setFlash(string $tipo, string $msg): void {
    iniciarSesion();
    $_SESSION['flash'] = ['tipo' => $tipo, 'msg' => $msg];
}

function getFlash(): string {
    iniciarSesion();
    if (empty($_SESSION['flash'])) return '';
    $f    = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $icon = match($f['tipo']) {
        'success' => '✔',
        'error'   => '✖',
        'warning' => '⚠',
        default   => 'ℹ',
    };
    return '<div class="alerta alerta-' . limpiar($f['tipo']) . '">' . $icon . ' ' . limpiar($f['msg']) . '</div>';
}

// ── CSRF ─────────────────────────────────────────────────────
function csrfToken(): string {
    iniciarSesion();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validarCsrf(): void {
    iniciarSesion();
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        die('Solicitud inválida. Por favor recargue la página.');
    }
}

// Definir BASE_URL si no está definida
if (!defined('BASE_URL')) {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    // Subir hasta la carpeta egresados/
    define('BASE_URL', $proto . '://' . $host . '/egresados/');
}
