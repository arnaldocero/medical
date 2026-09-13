<?php
session_start();
require_once '../config/db.php';

// Validar que el usuario esté logueado
if (!isset($_SESSION['clinica'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
    exit;
}

$idCentro = $_POST['idCentro'] ?? null;
$clinica_id = $_SESSION['clinica']; // Capturamos el ID del cliente de la sesión
$nombre = $_POST['nombre_centro'];
$nit = $_POST['nit'];
$ciudad = $_POST['ciudad'];
$direccion = $_POST['direccion'];

try {
    if ($idCentro) {
        // UPDATE: Validamos que el centro pertenezca a la clínica del usuario por seguridad
        $sql = "UPDATE centros_medicos SET nombre_centro=?, nit=?, ciudad=?, direccion=? 
                WHERE id=? AND clinica_id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nombre, $nit, $ciudad, $direccion, $idCentro, $clinica_id]);
        $mensaje = "Centro actualizado correctamente";
    } else {
        // INSERT: Incluimos el clinica_id
        $sql = "INSERT INTO centros_medicos (clinica_id, nombre_centro, nit, ciudad, direccion) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$clinica_id, $nombre, $nit, $ciudad, $direccion]);
        $mensaje = "Centro médico registrado con éxito";
    }

    echo json_encode(['status' => 'success', 'message' => $mensaje]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}