<?php
session_start();
require_once '../config/db.php';

header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['clinica'])) {
    echo '<option value="">Sesión expirada</option>';
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'getEspecialidades':
        $medico_id = $_POST['id'] ?? '';
        
        if (!empty($medico_id)) {
            // Hacemos el JOIN utilizando el ID de especialidad guardado en datos_medicos
            $sql = "SELECT e.id, e.nombre 
                    FROM datos_medicos dm
                    INNER JOIN especialidades e ON dm.especialidad = e.id
                    WHERE dm.usuario_id = ? AND dm.estado_medico = 1 LIMIT 1";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$medico_id]);
            $especialidad_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($especialidad_data) {
                echo '<option value="">Seleccione Especialidad...</option>';
                echo '<option value="' . htmlspecialchars($especialidad_data['id']) . '">' . htmlspecialchars($especialidad_data['nombre']) . '</option>';
            } else {
                echo '<option value="">El médico no tiene una especialidad válida asignada</option>';
            }
        } else {
            echo '<option value="">Seleccione médico primero...</option>';
        }
        break;

    case 'getConsultorios':
        $centro_id = $_POST['id'] ?? '';
        
        if (!empty($centro_id)) {
            $stmt = $pdo->prepare("SELECT id, nombre_consultorio FROM consultorios WHERE centro_id = ? AND estado = 1");
            $stmt->execute([$centro_id]);
            $consultorios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($consultorios) > 0) {
                echo '<option value="">Seleccione Consultorio...</option>';
                foreach ($consultorios as $con) {
                    echo '<option value="' . $con['id'] . '">' . $con['nombre_consultorio'] . '</option>';
                }
            } else {
                echo '<option value="">No hay consultorios en esta sede</option>';
            }
        } else {
            echo '<option value="">Seleccione sede primero...</option>';
        }
        break;
        
    default:
        echo '<option value="">Acción no permitida</option>';
        break;
}