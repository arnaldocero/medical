<?php
session_start();
require_once '../config/db.php';

$usuario_id = $_POST['usuario_id'];
$clinica_id = $_SESSION['clinica'];

// 1. Todas las especialidades de la clínica
$esp = $pdo->prepare("SELECT id, nombre FROM especialidades WHERE clinica_id = ? AND estado = 1");
$esp->execute([$clinica_id]);

// 2. Especialidades ya asignadas a este médico
$asignadas = $pdo->prepare("SELECT especialidad_id FROM medico_especialidad WHERE usuario_id = ?");
$asignadas->execute([$usuario_id]);
$listaAsignadas = $asignadas->fetchAll(PDO::FETCH_COLUMN);

foreach ($esp as $e) {
    $checked = in_array($e['id'], $listaAsignadas) ? 'checked' : '';
    echo "
    <div class='form-check mb-2'>
        <input class='form-check-input' type='checkbox' name='especialidades[]' id='esp_{$e['id']}' value='{$e['id']}' $checked>
        <label class='form-check-label ml-2' for='esp_{$e['id']}'>{$e['nombre']}</label>
    </div>";
}