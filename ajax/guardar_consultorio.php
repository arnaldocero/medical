<?php
session_start();
require_once '../config/db.php';

$id = $_POST['idConsultorio'] ?? null;
$centro_id = $_POST['centro_id'];
$nombre = $_POST['nombre_consultorio'];
$tipo = $_POST['tipo'];
$clinica_id = $_SESSION['clinica'];

try {
    if (empty($id)) {
        $stmt = $pdo->prepare("INSERT INTO consultorios (clinica_id, centro_id, nombre_consultorio, tipo) VALUES (?, ?, ?, ?)");
        $stmt->execute([$clinica_id, $centro_id, $nombre, $tipo]);
        $res = "Consultorio creado";
    } else {
        $stmt = $pdo->prepare("UPDATE consultorios SET centro_id = ?, nombre_consultorio = ?, tipo = ? WHERE id = ? AND clinica_id = ?");
        $stmt->execute([$centro_id, $nombre, $tipo, $id, $clinica_id]);
        $res = "Consultorio actualizado";
    }
    echo json_encode(['status' => 'success', 'message' => $res]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}