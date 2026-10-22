<?php
/**
 * Gestión de formatos de historia clínica: crear/editar, campos personalizados y asignación a médicos.
 */
session_start();
require_once __DIR__ . '/../config/security.php';
requerirPermisoAJAX('formatos.administrar');
require_once '../config/db.php';
require_once '../config/roles.php';
require_once '../config/formatos.php';

header('Content-Type: application/json; charset=utf-8');

$clinica_id = intval($_SESSION['clinica']);
$action = $_POST['action'] ?? $_GET['action'] ?? '';

/**
 * Devuelve el formato si pertenece a la clínica, o termina la petición.
 */
function formatoDeLaClinica($pdo, $id, $clinica_id) {
    $stmt = $pdo->prepare("SELECT id FROM formatos_historia WHERE id = ? AND clinica_id = ?");
    $stmt->execute([intval($id), $clinica_id]);
    if (!$stmt->fetchColumn()) {
        echo json_encode(['status' => 'error', 'message' => 'Formato no encontrado.']);
        exit();
    }
    return intval($id);
}

try {
    switch ($action) {

        case 'listar':
            $stmt = $pdo->prepare("
                SELECT f.id, f.nombre, f.descripcion, f.secciones, f.es_predeterminado, f.estado,
                       (SELECT COUNT(*) FROM formato_campos c WHERE c.formato_id = f.id AND c.estado = 1) AS total_campos,
                       (SELECT COUNT(*) FROM formato_medicos m WHERE m.formato_id = f.id) AS total_medicos,
                       (SELECT COUNT(*) FROM historias_clinicas h WHERE h.formato_id = f.id) AS total_historias
                FROM formatos_historia f
                WHERE f.clinica_id = ?
                ORDER BY f.estado DESC, f.nombre ASC");
            $stmt->execute([$clinica_id]);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($filas as &$f) {
                $f['secciones'] = array_map(function ($s) { return SECCIONES_FORMATO[$s][0]; }, normalizarSecciones($f['secciones']));
            }
            echo json_encode($filas, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $formato = obtenerFormato($pdo, $_GET['id'] ?? 0, $clinica_id);
            echo json_encode($formato ? ['status' => 'success', 'formato' => $formato] : ['status' => 'error', 'message' => 'Formato no encontrado.'], JSON_UNESCAPED_UNICODE);
            break;

        case 'guardar':
            $id = intval($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '') ?: null;
            $secciones = normalizarSecciones($_POST['secciones'] ?? []);
            $predeterminado = !empty($_POST['es_predeterminado']) ? 1 : 0;
            $campos = (array) ($_POST['campos'] ?? []);

            if ($nombre === '') {
                echo json_encode(['status' => 'warning', 'message' => 'El nombre del formato es obligatorio.']);
                exit();
            }

            // Validar campos personalizados
            $camposValidos = [];
            foreach (array_values($campos) as $i => $c) {
                $etiqueta = trim($c['etiqueta'] ?? '');
                $tipo = $c['tipo'] ?? 'texto';
                $opciones = trim($c['opciones'] ?? '');
                if ($etiqueta === '') {
                    continue; // filas vacías se ignoran
                }
                if (!array_key_exists($tipo, TIPOS_CAMPO_FORMATO)) {
                    echo json_encode(['status' => 'warning', 'message' => "Tipo de campo no válido en \"$etiqueta\"."]);
                    exit();
                }
                if ($tipo === 'lista' && $opciones === '') {
                    echo json_encode(['status' => 'warning', 'message' => "El campo \"$etiqueta\" es una lista: escriba sus opciones (una por línea)."]);
                    exit();
                }
                $camposValidos[] = [
                    'id' => intval($c['id'] ?? 0),
                    'etiqueta' => $etiqueta,
                    'tipo' => $tipo,
                    'opciones' => $tipo === 'lista' ? $opciones : null,
                    'obligatorio' => !empty($c['obligatorio']) ? 1 : 0,
                    'orden' => $i,
                ];
            }

            // Nombre único por clínica
            $stmtDup = $pdo->prepare("SELECT COUNT(*) FROM formatos_historia WHERE clinica_id = ? AND nombre = ? AND id != ?");
            $stmtDup->execute([$clinica_id, $nombre, $id]);
            if ($stmtDup->fetchColumn() > 0) {
                echo json_encode(['status' => 'warning', 'message' => 'Ya existe un formato con ese nombre.']);
                exit();
            }

            $pdo->beginTransaction();

            if ($id > 0) {
                formatoDeLaClinica($pdo, $id, $clinica_id);
                $pdo->prepare("UPDATE formatos_historia SET nombre = ?, descripcion = ?, secciones = ?, es_predeterminado = ? WHERE id = ? AND clinica_id = ?")
                    ->execute([$nombre, $descripcion, json_encode($secciones), $predeterminado, $id, $clinica_id]);
            } else {
                $pdo->prepare("INSERT INTO formatos_historia (clinica_id, nombre, descripcion, secciones, es_predeterminado) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$clinica_id, $nombre, $descripcion, json_encode($secciones), $predeterminado]);
                $id = intval($pdo->lastInsertId());
            }

            // Solo un formato predeterminado por clínica
            if ($predeterminado) {
                $pdo->prepare("UPDATE formatos_historia SET es_predeterminado = 0 WHERE clinica_id = ? AND id != ?")->execute([$clinica_id, $id]);
            }

            // Campos: actualizar los existentes, crear los nuevos y desactivar los quitados
            // (se desactivan en vez de borrarse para conservar las respuestas ya guardadas)
            $stmtExist = $pdo->prepare("SELECT id FROM formato_campos WHERE formato_id = ?");
            $stmtExist->execute([$id]);
            $existentes = array_map('intval', $stmtExist->fetchAll(PDO::FETCH_COLUMN));

            $stmtUpd = $pdo->prepare("UPDATE formato_campos SET etiqueta = ?, tipo = ?, opciones = ?, obligatorio = ?, orden = ?, estado = 1 WHERE id = ? AND formato_id = ?");
            $stmtIns = $pdo->prepare("INSERT INTO formato_campos (formato_id, etiqueta, tipo, opciones, obligatorio, orden) VALUES (?, ?, ?, ?, ?, ?)");
            $conservados = [];
            foreach ($camposValidos as $c) {
                if ($c['id'] && in_array($c['id'], $existentes, true)) {
                    $stmtUpd->execute([$c['etiqueta'], $c['tipo'], $c['opciones'], $c['obligatorio'], $c['orden'], $c['id'], $id]);
                    $conservados[] = $c['id'];
                } else {
                    $stmtIns->execute([$id, $c['etiqueta'], $c['tipo'], $c['opciones'], $c['obligatorio'], $c['orden']]);
                }
            }
            $quitados = array_diff($existentes, $conservados);
            if (!empty($quitados)) {
                $pdo->prepare("UPDATE formato_campos SET estado = 0 WHERE formato_id = ? AND id IN (" . implode(',', $quitados) . ")")->execute([$id]);
            }

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Formato guardado correctamente.', 'id' => $id]);
            break;

        case 'cambiar_estado':
            $id = formatoDeLaClinica($pdo, $_POST['id'] ?? 0, $clinica_id);
            $estado = intval($_POST['estado'] ?? 0) === 1 ? 1 : 0;
            $pdo->prepare("UPDATE formatos_historia SET estado = ? WHERE id = ? AND clinica_id = ?")->execute([$estado, $id, $clinica_id]);
            echo json_encode(['status' => 'success', 'message' => $estado ? 'Formato activado.' : 'Formato desactivado.']);
            break;

        // Médicos de la sede (o de todas, para el Admin) con la marca de si tienen el formato asignado
        case 'medicos':
            $id = formatoDeLaClinica($pdo, $_GET['id'] ?? 0, $clinica_id);
            $stmt = $pdo->prepare("
                SELECT u.id, u.nombre, e.nombre AS especialidad,
                       EXISTS (SELECT 1 FROM formato_medicos fm WHERE fm.formato_id = ? AND fm.usuario_id = u.id) AS asignado
                FROM usuarios_clinicas u
                INNER JOIN datos_medicos dm ON dm.usuario_id = u.id
                LEFT JOIN especialidades e ON e.id = dm.especialidad
                WHERE u.clinica_id = ? AND u.estado = 1 AND dm.estado_medico = 1
                  AND u.rol_id IN (" . listaSqlRoles(ROLES_MEDICOS) . ")
                  AND " . sqlUsuarioEnSedes('u.id') . "
                ORDER BY u.nombre");
            $stmt->execute([$id, $clinica_id]);
            echo json_encode(['status' => 'success', 'medicos' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
            break;

        case 'guardar_medicos':
            $id = formatoDeLaClinica($pdo, $_POST['id'] ?? 0, $clinica_id);
            $marcados = array_map('intval', (array) ($_POST['medicos'] ?? []));

            // Solo se modifican los médicos visibles para quien edita (su sede)
            $stmtVis = $pdo->prepare("
                SELECT u.id FROM usuarios_clinicas u
                INNER JOIN datos_medicos dm ON dm.usuario_id = u.id
                WHERE u.clinica_id = ? AND u.rol_id IN (" . listaSqlRoles(ROLES_MEDICOS) . ") AND " . sqlUsuarioEnSedes('u.id'));
            $stmtVis->execute([$clinica_id]);
            $visibles = array_map('intval', $stmtVis->fetchAll(PDO::FETCH_COLUMN));

            $pdo->beginTransaction();
            if (!empty($visibles)) {
                $pdo->prepare("DELETE FROM formato_medicos WHERE formato_id = ? AND usuario_id IN (" . implode(',', $visibles) . ")")->execute([$id]);
            }
            $stmtIns = $pdo->prepare("INSERT IGNORE INTO formato_medicos (formato_id, usuario_id) VALUES (?, ?)");
            foreach (array_intersect($marcados, $visibles) as $medico) {
                $stmtIns->execute([$id, $medico]);
            }
            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Médicos asignados correctamente.']);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('formatos_action: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error en el servidor al procesar el formato.']);
}
