<?php
// Las credenciales se leen del archivo .env (no se sube a git). Ver .env.example
require_once __DIR__ . '/env.php';

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
        return new PDO($dsn, $user, $pass, $options);
    } catch (\PDOException $e) {
        // Registrar el detalle en el log del servidor sin mostrar datos de conexión al usuario
        error_log('Error de conexión a la base de datos: ' . $e->getMessage());
        http_response_code(500);
        exit('No se pudo conectar con la base de datos. Intente más tarde.');
    }
})();
