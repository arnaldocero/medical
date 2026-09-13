<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Validar que exista sesión activa
if (!isset($_SESSION['id'])) {
    echo json_encode(['results' => []]);
    exit();
}

// Asegúrate de que esta ruta hacia la base de datos sea la correcta según tu estructura
require_once '../config/db.php'; 

// Obtener el término de búsqueda enviado por Select2
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($busqueda)) {
    echo json_encode(['results' => []]);
    exit();
}

try {
    // Buscamos coincidencia en el nombre completo o en el documento de identidad
    $sql = "SELECT 
                p.id AS paciente_id,
                p.documento_identidad,
                u.nombre AS nombre_completo
            FROM pacientes_datos p
            INNER JOIN usuarios_clinicas u ON p.usuario_id = u.id
            WHERE p.documento_identidad LIKE :query 
               OR u.nombre LIKE :query
            LIMIT 20";

    $stmt = $pdo->prepare($sql);
    $param = '%' . $busqueda . '%';
    $stmt->bindParam(':query', $param, PDO::PARAM_STR);
    $stmt->execute();

    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];

    foreach ($pacientes as $row) {
        $nombreCompleto = trim($row['nombre_completo']);
        $doc = !empty($row['documento_identidad']) ? $row['documento_identidad'] : 'Sin documento';

        // Select2 requiere de manera obligatoria las llaves 'id' y 'text'
        $results[] = [
            'id' => $row['paciente_id'],
            'text' => $nombreCompleto . ' (' . $doc . ')',
            'documento' => $doc
        ];
    }

    echo json_encode(['results' => $results]);

} catch (PDOException $e) {
    // Enviar respuesta estructurada para depurar si falla la base de datos
    echo json_encode(['results' => [], 'error' => $e->getMessage()]);
}