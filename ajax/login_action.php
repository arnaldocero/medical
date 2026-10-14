<?php
session_start();
header('Content-Type: application/json');
require_once '../config/db.php';

$usuario = trim($_POST['usuario'] ?? '');
$password = $_POST['password'] ?? '';
$recordarme = isset($_POST['recordarme']) ? true : false;

if(empty($usuario) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Llene todos los campos.']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM usuarios_clinicas WHERE (usuario = ? OR email = ?) AND estado = 1 LIMIT 1");
$stmt->execute([$usuario, $usuario]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if($user && password_verify($password, $user['password'])) {
    
    // Iniciar variables de sesión
    $_SESSION['id'] = $user['id'];
    $_SESSION['nombre'] = $user['nombre'];
    $_SESSION['rol'] = $user['rol_id'];
    $_SESSION['clinica'] = $user['clinica_id'];

    // LÓGICA DE RECORDARME
    if($recordarme) {
        $token = bin2hex(random_bytes(32)); // Token seguro de 64 chars
        $tokenHash = hash('sha256', $token); // Guardamos el hash por seguridad
        
        $pdo->prepare("UPDATE usuarios_clinicas SET remember_token = ? WHERE id = ?")->execute([$tokenHash, $user['id']]);
        
        // Formato Cookie: ID:Token_Sin_Hash
        $valorCookie = $user['id'] . ':' . $token;
        setcookie('c_remember', $valorCookie, time() + (30 * 24 * 60 * 60), '/', '', false, true); // 30 días, HttpOnly
    }

    echo json_encode(['status' => 'success', 'message' => 'Bienvenido']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Credenciales incorrectas o usuario inactivo.']);
}