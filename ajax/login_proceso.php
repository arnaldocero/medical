<?php
session_start();
require_once '../config/db.php';
// Importamos el motor de seguridad para poder registrar los permisos en la sesión
require_once '../config/security.php'; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userOrEmail = $_POST['usuario'];
    $password = $_POST['password'];

    // Buscamos por usuario o email y que esté ACTIVO (estado = 1)
    $stmt = $pdo->prepare("SELECT * FROM usuarios_clinicas WHERE (usuario = ? OR email = ?) AND estado = 1 LIMIT 1");
    $stmt->execute([$userOrEmail, $userOrEmail]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($password, $usuario['password'])) {
        // Creamos la sesión básica
        $_SESSION['id'] = $usuario['id'];
        $_SESSION['nombre'] = $usuario['nombre'];
        $_SESSION['rol'] = $usuario['rol_id'];
        $_SESSION['clinica'] = $usuario['clinica_id'];

        // Cargar los permisos en la sesión utilizando la función de tu security.php
        cargarPermisosUsuario($pdo, $usuario['rol_id']);

        echo json_encode(['status' => 'success', 'message' => 'Acceso concedido']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Credenciales incorrectas o usuario inactivo']);
    }
}