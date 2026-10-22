<?php
session_start();
// Seguridad: sesión activa + permiso del módulo
require_once __DIR__ . '/../config/security.php';
requerirPermisoAJAX('farmacia.medicamentos');
if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida.']);
    exit();
}

require_once '../config/db.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$clinica_id = intval($_SESSION['clinica']);

switch ($action) {
    case 'listar':
        try {
            // Trae el catálogo de la clínica actual
            $stmt = $pdo->prepare("SELECT m.*, c.nombre_centro FROM medicamentos m LEFT JOIN centros_medicos c ON c.id = m.centro_id WHERE m.clinica_id = ? AND " . sqlEnSedes('m.centro_id') . " ORDER BY m.nombre_generico ASC");
            $stmt->execute([$clinica_id]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $data]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => mensajeErrorInterno($e)]);
        }
        break;

    case 'guardar':
        $nombre_generico = trim($_POST['nombre_generico'] ?? '');
        $nombre_comercial = trim($_POST['nombre_comercial'] ?? '') ?: null;
        $presentacion = trim($_POST['presentacion'] ?? '');
        $stock_actual = intval($_POST['stock_actual'] ?? 0);
        $stock_minimo = intval($_POST['stock_minimo'] ?? 10);
        $codigo_barras = trim($_POST['codigo_barras'] ?? '') ?: null;

        if (empty($nombre_generico) || empty($presentacion)) {
            echo json_encode(['status' => 'error', 'message' => 'El nombre genérico y la presentación son obligatorios.']);
            exit();
        }

        try {
            $centro_id = sedeParaRegistrarAJAX();
            $sql = "INSERT INTO medicamentos (clinica_id, centro_id, nombre_generico, nombre_comercial, presentacion, stock_actual, stock_minimo, codigo_barras) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$clinica_id, $centro_id, $nombre_generico, $nombre_comercial, $presentacion, $stock_actual, $stock_minimo, $codigo_barras]);

            echo json_encode(['status' => 'success', 'message' => 'Medicamento registrado exitosamente en inventario.']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => mensajeErrorInterno($e)]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no permitida.']);
        break;
}