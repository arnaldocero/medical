<?php
session_start();
// Seguridad: sesión activa + permiso del módulo
require_once __DIR__ . '/../config/security.php';
requerirPermisoAJAX('infraestructura.consultorios');
require_once '../config/db.php';

$id = $_POST['idConsultorio'] ?? null;
$centro_id = $_POST['centro_id'];
$nombre = $_POST['nombre_consultorio'];
$tipo = $_POST['tipo'];
$clinica_id = $_SESSION['clinica'];

// La sede destino debe ser de su sede de trabajo; al editar, el consultorio también
validarSedeAJAX($centro_id);
if (!empty($id)) {
    $stmtSede = $pdo->prepare("SELECT centro_id FROM consultorios WHERE id = ? AND clinica_id = ?");
    $stmtSede->execute([$id, $clinica_id]);
    validarSedeAJAX($stmtSede->fetchColumn());
}

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