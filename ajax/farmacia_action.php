<?php
session_start();
if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida.']);
    exit();
}

require_once '../config/db.php';
$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

switch ($action) {
    case 'buscar_formula':
        // Buscaremos la fórmula activa por documento del paciente o ID de fórmula
        $busqueda = trim($_POST['busqueda'] ?? '');
        
        try {
            $stmt = $pdo->prepare("
                SELECT f.id AS formula_id, f.diagnostico_cie10, f.observaciones, f.fecha_prescripcion,
                       uc.nombre AS paciente_nombre, pd.documento_identidad
                FROM formulas_medicas f
                INNER JOIN pacientes_datos pd ON f.paciente_id = pd.id
                INNER JOIN usuarios_clinicas uc ON pd.usuario_id = uc.id
                WHERE (pd.documento_identidad = ? OR f.id = ?)
                  AND f.estado = 'pendiente' -- Solo fórmulas sin entregar
                  AND uc.clinica_id = ?
                LIMIT 1
            ");
            $stmt->execute([$busqueda, $busqueda, $_SESSION['clinica']]);
            $formula = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$formula) {
                echo json_encode(['status' => 'error', 'message' => 'No se encontró ninguna fórmula pendiente para este criterio.']);
                exit();
            }

            // Traer los medicamentos asociados a esa fórmula
            $stmtDetalle = $pdo->prepare("
                SELECT df.id AS detalle_id, df.medicamento_id, df.cantidad_solicitada, df.dosificacion,
                       m.nombre_comercial, m.presentacion, m.stock_actual
                FROM formula_detalles df
                INNER JOIN medicamentos m ON df.medicamento_id = m.id
                WHERE df.formula_id = ?
            ");
            $stmtDetalle->execute([$formula['formula_id']]);
            $medicamentos = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'formula' => $formula,
                'medicamentos' => $medicamentos
            ]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'despachar_formula':
        $formula_id = intval($_POST['formula_id'] ?? 0);

        try {
            // Iniciamos una transacción ATÓMICA
            $pdo->beginTransaction();

            // 1. Verificar que la fórmula siga pendiente
            $stmtCheck = $pdo->prepare("SELECT estado FROM formulas_medicas WHERE id = ? FOR UPDATE");
            $stmtCheck->execute([$formula_id]);
            $estado = $stmtCheck->fetchColumn();

            if ($estado !== 'PENDIENTE') {
                throw new Exception("Esta fórmula ya fue procesada o no es válida.");
            }

            // 2. Traer los medicamentos que se deben descontar
            $stmtDetalle = $pdo->prepare("SELECT medicamento_id, cantidad_solicitada FROM formula_detalles WHERE formula_id = ?");
            $stmtDetalle->execute([$formula_id]);
            $items = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

            // 3. Descontar el stock y validar existencias
            $stmtUpdateStock = $pdo->prepare("UPDATE medicamentos SET stock_actual = stock_actual - ? WHERE id = ? AND stock_actual >= ?");
            
            foreach ($items as $item) {
                $stmtUpdateStock->execute([$item['cantidad_solicitada'], $item['medicamento_id'], $item['cantidad_solicitada']]);
                
                // Si ninguna fila se modificó, significa que no había stock suficiente
                if ($stmtUpdateStock->rowCount() === 0) {
                    throw new Exception("Stock insuficiente para uno de los medicamentos recetados.");
                }
            }

            // 4. Cambiar el estado de la fórmula a entregada
            $stmtFinalizar = $pdo->prepare("UPDATE formulas_medicas SET estado = 'DESPACHADA', entregado_por = ?, fecha_entrega = NOW() WHERE id = ?");
            $stmtFinalizar->execute([$_SESSION['id'], $formula_id]);

            // Si todo salió bien, confirmamos los cambios en la BD
            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Fórmula médica despachada y stock actualizado correctamente.']);

        } catch (Exception $e) {
            // Si algo falla, revertimos absolutamente TODO el proceso
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;
}