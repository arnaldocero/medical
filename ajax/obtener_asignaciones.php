<?php
session_start();
require_once '../config/db.php';
// Importamos tu motor de seguridad
require_once '../config/security.php'; 

// Validamos que tenga el permiso para gestionar la asignación antes de leer
requerirPermisoAJAX('usuarios.asignar_sede');

$usuario_id = $_POST['usuario_id'];
$clinica_id = $_SESSION['clinica'];

// 1. Obtener todos los centros de la clínica[cite: 29]
$centros = $pdo->prepare("SELECT id, nombre_centro FROM centros_medicos WHERE clinica_id = ? AND estado = 1");
$centros->execute([$clinica_id]);

// 2. Obtener lo que el usuario ya tiene asignado[cite: 29]
$asignados = $pdo->prepare("SELECT centro_id FROM asignacion_centros WHERE usuario_id = ?");
$asignados->execute([$usuario_id]);
$listaAsignados = $asignados->fetchAll(PDO::FETCH_COLUMN);

foreach ($centros as $c) {
    $checked = in_array($c['id'], $listaAsignados) ? 'checked' : '';
    echo "
    <div class='custom-control custom-checkbox'>
        <input class='custom-control-input' type='checkbox' name='centros[]' id='centro_{$c['id']}' value='{$c['id']}' $checked>
        <label for='centro_{$c['id']}' class='custom-control-label'>{$c['nombre_centro']}</label>
    </div>";
}