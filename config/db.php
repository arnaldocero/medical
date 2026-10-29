<?php
// Las credenciales se leen del archivo .env (no se sube a git). Ver .env.example
require_once __DIR__ . '/env.php';

// Errores de PHP: en producción nunca se muestran en pantalla (solo se registran en el log).
// Para verlos en desarrollo, poner APP_DEBUG=true en el .env local.
$appDebug = in_array(strtolower((string) env('APP_DEBUG', 'false')), ['1', 'true', 'si'], true);
ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('log_errors', '1');

// Zona horaria única para PHP y MySQL (por defecto Colombia). Evita que "hoy" sea distinto
// en PHP y en la base de datos (sala de espera, panel médico, agenda, bloqueos de login...).
date_default_timezone_set(env('APP_TIMEZONE', 'America/Bogota'));

$pdo = (function () {
    $host    = env('DB_HOST');
    $db      = env('DB_NAME');
    $user    = env('DB_USER');
    $pass    = env('DB_PASS', '');
    $charset = env('DB_CHARSET', 'utf8mb4');

    if (!$host || !$db || !$user) {
        error_log('Configuración de base de datos incompleta: revise el archivo .env');
        http_response_code(500);
        exit('Error de configuración del sistema. Contacte al administrador.');
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

    try {
        $conexion = new PDO($dsn, $user, $pass, $options);
        // MySQL usa el mismo desfase horario que PHP (ej. -05:00), así NOW()/CURDATE() coinciden con date()
        $conexion->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
        return $conexion;
    } catch (\PDOException $e) {
        // Registrar el detalle en el log del servidor sin mostrar datos de conexión al usuario
        error_log('Error de conexión a la base de datos: ' . $e->getMessage());
        http_response_code(500);
        exit('No se pudo conectar con la base de datos. Intente más tarde.');
    }
})();
