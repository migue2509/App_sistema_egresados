<?php
// ============================================================
// config/db.php — Conexión a base de datos
// I.E. Dinamarca — Sistema de Egresados
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'dinamarca_egresados');
define('DB_USER', 'root');          // ← Cambiar por el usuario real
define('DB_PASS', '');              // ← Cambiar por la contraseña real
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:20px;color:#c00;">
                <strong>Error de conexión a la base de datos.</strong><br>
                Por favor contacte al administrador del sistema.<br>
                <small>' . htmlspecialchars($e->getMessage()) . '</small>
                </div>');
        }
    }
    return $pdo;
}
