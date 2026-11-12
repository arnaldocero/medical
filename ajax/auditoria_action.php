<?php
/**
 * Consulta de la auditoría (solo lectura): listado con filtros y detalle de un registro.
 */
session_start();
require_once __DIR__ . '/../config/security.php';
requerirPermisoAJAX('auditoria.ver');
require_once '../config/db.php';
header('Content-Type: application/json; charset=utf-8');

$clinica_id = intval($_SESSION['clinica']);
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// En una sede concreta se ven sus registros y los generales (sin sede); en "Todas las sedes", todo
$filtroSede = sedeActual() === null ? '1 = 1' : '(a.centro_id = ' . intval(sedeActual()) . ' OR a.centro_id IS NULL)';

try {
    switch ($action) {

        case 'filtros':
            $mods = $pdo->prepare("SELECT DISTINCT modulo FROM auditoria a WHERE a.clinica_id = ? ORDER BY modulo");
            $mods->execute([$clinica_id]);
            $usrs = $pdo->prepare("SELECT DISTINCT a.usuario_id, a.usuario_nombre FROM auditoria a WHERE a.clinica_id = ? AND a.usuario_id IS NOT NULL ORDER BY a.usuario_nombre");
            $usrs->execute([$clinica_id]);
            echo json_encode(['status' => 'success', 'modulos' => $mods->fetchAll(PDO::FETCH_COLUMN), 'usuarios' => $usrs->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
            break;

        case 'listar':
            $desde = DateTime::createFromFormat('Y-m-d', $_POST['desde'] ?? '') ?: new DateTime('-7 days');
            $hasta = DateTime::createFromFormat('Y-m-d', $_POST['hasta'] ?? '') ?: new DateTime();
            $sql = "SELECT a.id, a.fecha, a.usuario_nombre, a.modulo, a.accion, a.registro_id, a.descripcion, a.ip, c.nombre_centro,
                           (a.datos_antes IS NOT NULL OR a.datos_despues IS NOT NULL) AS tiene_detalle
                    FROM auditoria a
                    LEFT JOIN centros_medicos c ON c.id = a.centro_id
                    WHERE a.clinica_id = ? AND $filtroSede AND a.fecha >= ? AND a.fecha < ? + INTERVAL 1 DAY";
            $params = [$clinica_id, $desde->format('Y-m-d'), $hasta->format('Y-m-d')];
            if (!empty($_POST['modulo'])) { $sql .= " AND a.modulo = ?"; $params[] = $_POST['modulo']; }
            if (!empty($_POST['usuario_id'])) { $sql .= " AND a.usuario_id = ?"; $params[] = intval($_POST['usuario_id']); }
            if (trim($_POST['texto'] ?? '') !== '') {
                $sql .= " AND (a.descripcion LIKE ? OR a.registro_id = ?)";
                $params[] = '%' . trim($_POST['texto']) . '%';
                $params[] = trim($_POST['texto']);
            }
            $sql .= " ORDER BY a.id DESC LIMIT 2000";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
            break;

        case 'detalle':
            $stmt = $pdo->prepare("SELECT a.*, c.nombre_centro FROM auditoria a LEFT JOIN centros_medicos c ON c.id = a.centro_id
                                   WHERE a.id = ? AND a.clinica_id = ? AND $filtroSede");
            $stmt->execute([intval($_POST['id'] ?? 0), $clinica_id]);
            $reg = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$reg) {
                echo json_encode(['status' => 'error', 'message' => 'Registro no encontrado.']);
                break;
            }
            $antes = json_decode((string) $reg['datos_antes'], true);
            $despues = json_decode((string) $reg['datos_despues'], true);
            // Tabla campo por campo, marcando lo que cambió
            $campos = [];
            foreach (array_unique(array_merge(array_keys((array) $antes), array_keys((array) $despues))) as $campo) {
                $va = is_array($antes) && array_key_exists($campo, $antes) ? $antes[$campo] : null;
                $vd = is_array($despues) && array_key_exists($campo, $despues) ? $despues[$campo] : null;
                $campos[] = [
                    'campo'   => $campo,
                    'antes'   => is_array($va) ? json_encode($va, JSON_UNESCAPED_UNICODE) : $va,
                    'despues' => is_array($vd) ? json_encode($vd, JSON_UNESCAPED_UNICODE) : $vd,
                    'cambio'  => $va != $vd,
                ];
            }
            unset($reg['datos_antes'], $reg['datos_despues']);
            echo json_encode(['status' => 'success', 'registro' => $reg, 'campos' => $campos], JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => mensajeErrorInterno($e)]);
}
