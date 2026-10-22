<?php
header('Content-Type: application/json');
require_once '../config/db.php';

$token = trim($_POST['token'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($token) || strlen($password) < 6) {
    echo json_encode(['status' => 'error', 'message' => 'Contraseña no válida (mínimo 6 caracteres) o token ausente.']);
    exit();
}

$stmt = $pdo->prepare("SELECT id FROM usuarios_clinicas WHERE token_recuperacion = ? AND expira_recuperacion > NOW() AND estado = 1");
$stmt->execute([hash('sha256', $token)]); // En BD se guarda solo el hash del token
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $passHash = password_hash($password, PASSWORD_DEFAULT);

    // Actualizar contraseña, invalidar token y cerrar los "Recordarme" activos
    $update = $pdo->prepare("
        UPDATE usuarios_clinicas
        SET password = ?, token_recuperacion = NULL, expira_recuperacion = NULL, remember_token = NULL, intentos_fallidos = 0, bloqueado_hasta = NULL
        WHERE id = ?
    ");
    $update->execute([$passHash, $user['id']]);

    echo json_encode(['status' => 'success', 'message' => 'Contraseña actualizada correctamente. Redirigiendo al login...']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'El token ha expirado o ya fue utilizado.']);
}