<?php
session_start();
header('Content-Type: application/json');
require_once '../config/db.php';
// Motor de seguridad + utilidades de sesión / "Recordarme"
require_once '../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userOrEmail = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $recordarme = !empty($_POST['recordarme']);

    if ($userOrEmail === '' || $password === '') {
        echo json_encode(['status' => 'error', 'message' => 'Llene todos los campos.']);
        exit();
    }

    // Buscamos por usuario o email y que esté ACTIVO (estado = 1)
    $stmt = $pdo->prepare("SELECT * FROM usuarios_clinicas WHERE (usuario = ? OR email = ?) AND estado = 1 LIMIT 1");
    $stmt->execute([$userOrEmail, $userOrEmail]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($password, $usuario['password'])) {
        // Creamos la sesión y cargamos los permisos del rol
        iniciarSesionUsuario($pdo, $usuario);

        // LÓGICA DE RECORDARME: cookie de 30 días con token rotativo
        if ($recordarme) {
            crearTokenRecordarme($pdo, $usuario['id']);
        } else {
            borrarTokenRecordarme($pdo, $usuario['id']);
        }

        echo json_encode(['status' => 'success', 'message' => 'Acceso concedido']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Credenciales incorrectas o usuario inactivo']);
    }
}
