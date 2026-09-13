<?php
session_start();
require_once '../config/db.php';

$usuario_id = $_POST['idMedicoAsignar'];
$especialidades = $_POST['especialidades'] ?? [];

try {
    $pdo->beginTransaction();

    // Limpiar asignaciones previas
    $del = $pdo->prepare("DELETE FROM medico_especialidad WHERE usuario_id = ?");
    $del->execute([$usuario_id]);

    // Insertar nuevas
    if (!empty($especialidades)) {
        $ins = $pdo->prepare("INSERT INTO medico_especialidad (usuario_id, especialidad_id) VALUES (?, ?)");
        foreach ($especialidades as $esp_id) {
            $ins->execute([$usuario_id, $esp_id]);
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Especialidades actualizadas']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}