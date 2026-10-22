<?php
session_start();
// Seguridad: sesión activa + permiso del módulo
require_once __DIR__ . '/../config/security.php';
requerirPermisoAJAX('financiero.dian');
if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida o expirada.']);
    exit();
}

require_once '../config/db.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$clinica_id = intval($_SESSION['clinica']);

switch ($action) {
    case 'listar':
        try {
            $stmt = $pdo->prepare("SELECT d.*, c.nombre_centro FROM dian_resoluciones d LEFT JOIN centros_medicos c ON c.id = d.centro_id WHERE d.clinica_id = ? AND " . sqlEnSedes('d.centro_id') . " ORDER BY d.id DESC");
            $stmt->execute([$clinica_id]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $data]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => mensajeErrorInterno($e)]);
        }
        break;

    case 'guardar':
        $numero_resolucion = trim($_POST['numero_resolucion'] ?? '');
        $prefijo = trim($_POST['prefijo'] ?? '') ?: null;
        $numero_inicial = intval($_POST['numero_inicial'] ?? 0);
        $numero_final = intval($_POST['numero_final'] ?? 0);
        $fecha_resolucion = $_POST['fecha_resolucion'] ?? '';
        $vigencia_meses = intval($_POST['vigencia_meses'] ?? 0);
        $clave_tecnica = trim($_POST['clave_tecnica'] ?? '') ?: null;

        // Validaciones de negocio
        if (empty($numero_resolucion) || empty($fecha_resolucion) || $numero_final <= $numero_inicial || $vigencia_meses <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Datos incompletos o rangos numéricos inválidos.']);
            exit();
        }

        // Calcular fecha de vencimiento en base a la vigencia
        $date = new DateTime($fecha_resolucion);
        $date->modify("+$vigencia_meses months");
        $fecha_vencimiento = $date->format('Y-m-d');

        // Cada sede tiene su propia resolución
        $centro_id = sedeParaRegistrarAJAX();

        try {
            $pdo->beginTransaction();

            // Si se guarda como 'activa', inactivamos cualquier otra resolución previa de esta sede
            $stmtInactivar = $pdo->prepare("UPDATE dian_resoluciones SET estado = 'inactiva' WHERE clinica_id = ? AND centro_id = ? AND estado = 'activa'");
            $stmtInactivar->execute([$clinica_id, $centro_id]);

            $sql = "INSERT INTO dian_resoluciones 
                    (clinica_id, centro_id, numero_resolucion, prefijo, numero_inicial, numero_final, consecutivo_actual, fecha_resolucion, vigencia_meses, fecha_vencimiento, clave_tecnica, estado) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'activa')";
            
            // Consecutivo actual inicia en (numero_inicial - 1) para que el primer tiquete tome el número inicial exacto
            $consecutivo_inicial = $numero_inicial - 1;

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $clinica_id, $centro_id, $numero_resolucion, $prefijo, $numero_inicial, 
                $numero_final, $consecutivo_inicial, $fecha_resolucion, 
                $vigencia_meses, $fecha_vencimiento, $clave_tecnica
            ]);

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Resolución registrada y activada correctamente.']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => mensajeErrorInterno($e)]);
        }
        break;

    case 'cambiar_estado':
        $id = intval($_POST['id'] ?? 0);
        $nuevo_estado = $_POST['estado'] ?? '';

        if (!in_array($nuevo_estado, ['activa', 'inactiva'])) {
            echo json_encode(['status' => 'error', 'message' => 'Estado no permitido.']);
            exit();
        }

        // La resolución debe ser de su sede de trabajo
        $stmtSede = $pdo->prepare("SELECT centro_id FROM dian_resoluciones WHERE id = ? AND clinica_id = ?");
        $stmtSede->execute([$id, $clinica_id]);
        $centro_res = validarSedeAJAX($stmtSede->fetchColumn());

        try {
            $pdo->beginTransaction();

            if ($nuevo_estado === 'activa') {
                // Desactivar el resto de resoluciones de la misma sede
                $stmtInactivar = $pdo->prepare("UPDATE dian_resoluciones SET estado = 'inactiva' WHERE clinica_id = ? AND centro_id = ? AND estado = 'activa'");
                $stmtInactivar->execute([$clinica_id, $centro_res]);
            }

            $stmt = $pdo->prepare("UPDATE dian_resoluciones SET estado = ? WHERE id = ? AND clinica_id = ?");
            $stmt->execute([$nuevo_estado, $id, $clinica_id]);

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Estado actualizado exitosamente.']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => mensajeErrorInterno($e)]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no definida.']);
        break;
}