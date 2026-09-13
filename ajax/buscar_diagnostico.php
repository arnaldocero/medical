<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode([]);
    exit();
}

require_once '../config/db.php';

$term = trim($_GET['q'] ?? '');

if (empty($term)) {
    echo json_encode([]);
    exit();
}

try {
    // Buscamos coincidencia en código o descripción que estén activos
    $stmt = $pdo->prepare("
        SELECT id, codigo_cie, descripcion 
        FROM diagnosticos 
        WHERE (codigo_cie LIKE ? OR descripcion LIKE ?) AND estado = 1
        LIMIT 10
    ");
    
    $likeTerm = "%$term%";
    $stmt->execute([$likeTerm, $likeTerm]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formateamos la respuesta para que sea fácil de renderizar en el cliente
    $items = [];
    foreach ($resultados as $row) {
        $items[] = [
            'id' => $row['id'],
            'text' => '[' . $row['codigo_cie'] . '] ' . $row['descripcion']
        ];
    }

    echo json_encode($items);

} catch (PDOException $e) {
    echo json_encode([]);
}