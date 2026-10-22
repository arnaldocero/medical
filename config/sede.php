<?php
/**
 * Control de acceso por SEDE (centro de atención).
 *
 * Variables de sesión:
 *   $_SESSION['sedes']         IDs de sedes a las que el usuario tiene acceso (Admin: todas las activas)
 *   $_SESSION['centro']        Sede seleccionada (int). 0 = "Todas las sedes" (solo Admin)
 *   $_SESSION['centro_nombre'] Nombre de la sede seleccionada
 *
 * Los IDs se guardan como enteros, por eso las funciones sql*() pueden concatenarlos
 * en un IN (...) de forma segura.
 */

if (!defined('ROL_ADMIN')) {
    define('ROL_ADMIN', 1);
}

function esAdmin() {
    return isset($_SESSION['rol']) && intval($_SESSION['rol']) === ROL_ADMIN;
}

/**
 * Sedes activas a las que puede entrar el usuario en sesión: [['id' => .., 'nombre_centro' => ..], ...]
 */
function obtenerSedesUsuario($pdo) {
    if (esAdmin()) {
        $stmt = $pdo->prepare("SELECT id, nombre_centro FROM centros_medicos
                               WHERE clinica_id = ? AND estado = 1 ORDER BY nombre_centro ASC");
        $stmt->execute([$_SESSION['clinica']]);
    } else {
        $stmt = $pdo->prepare("SELECT c.id, c.nombre_centro FROM centros_medicos c
                               INNER JOIN asignacion_centros a ON a.centro_id = c.id
                               WHERE a.usuario_id = ? AND c.clinica_id = ? AND c.estado = 1
                               ORDER BY c.nombre_centro ASC");
        $stmt->execute([$_SESSION['id'], $_SESSION['clinica']]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Selecciona la sede de trabajo. $centroId = 0 significa "Todas las sedes" (solo Admin).
 * @return bool True si la sede es válida para el usuario.
 */
function seleccionarSede($pdo, $centroId) {
    $centroId = intval($centroId);
    $sedes = obtenerSedesUsuario($pdo);
    $ids = array_map('intval', array_column($sedes, 'id'));

    if ($centroId === 0) {
        if (!esAdmin()) {
            return false;
        }
        $nombre = 'Todas las sedes';
    } elseif (in_array($centroId, $ids, true)) {
        $nombre = $sedes[array_search($centroId, $ids, true)]['nombre_centro'];
    } else {
        return false;
    }

    $_SESSION['sedes'] = $ids;
    $_SESSION['centro'] = $centroId;
    $_SESSION['centro_nombre'] = $nombre;
    return true;
}

/**
 * Si el usuario (no Admin) tiene una sola sede, la selecciona automáticamente.
 * @return bool True si ya hay una sede seleccionada al terminar.
 */
function autoseleccionarSede($pdo) {
    if (sedeSeleccionada()) {
        return true;
    }
    if (!esAdmin()) {
        $sedes = obtenerSedesUsuario($pdo);
        if (count($sedes) === 1) {
            return seleccionarSede($pdo, $sedes[0]['id']);
        }
    }
    return false;
}

function olvidarSede() {
    unset($_SESSION['sedes'], $_SESSION['centro'], $_SESSION['centro_nombre']);
}

function sedeSeleccionada() {
    return isset($_SESSION['centro'], $_SESSION['sedes']);
}

/**
 * Sede de trabajo actual, o null si el Admin está en "Todas las sedes".
 */
function sedeActual() {
    $centro = intval($_SESSION['centro'] ?? 0);
    return $centro > 0 ? $centro : null;
}

/**
 * IDs de sedes cuya información se puede ver ahora mismo.
 */
function sedesVisibles() {
    $actual = sedeActual();
    if ($actual !== null) {
        return [$actual];
    }
    return array_map('intval', $_SESSION['sedes'] ?? []);
}

function puedeVerSede($centroId) {
    return in_array(intval($centroId), sedesVisibles(), true);
}

/**
 * Condición SQL: "columna IN (sedes visibles)". Nunca vacía: sin sedes devuelve una condición falsa.
 */
function sqlEnSedes($columna) {
    $ids = sedesVisibles();
    return empty($ids) ? '1 = 0' : $columna . ' IN (' . implode(',', $ids) . ')';
}

/**
 * Condición SQL: el usuario de la columna indicada está asignado a alguna sede visible.
 * En "Todas las sedes" (Admin) también incluye a los usuarios aún sin sede, para poder asignarlas.
 */
function sqlUsuarioEnSedes($columnaUsuarioId) {
    $existe = "EXISTS (SELECT 1 FROM asignacion_centros ac_s WHERE ac_s.usuario_id = $columnaUsuarioId AND "
            . sqlEnSedes('ac_s.centro_id') . ")";
    if (sedeActual() === null && esAdmin()) {
        $existe = "($existe OR NOT EXISTS (SELECT 1 FROM asignacion_centros ac_n WHERE ac_n.usuario_id = $columnaUsuarioId))";
    }
    return $existe;
}

/**
 * Comprueba si un usuario (paciente, médico o personal) está asignado a alguna sede visible.
 */
function usuarioEnSedesVisibles($pdo, $usuarioId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios_clinicas u WHERE u.id = ? AND u.clinica_id = ? AND " . sqlUsuarioEnSedes('u.id'));
    $stmt->execute([intval($usuarioId), $_SESSION['clinica']]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Comprueba si un usuario está asignado a una sede concreta.
 */
function usuarioEnSede($pdo, $usuarioId, $centroId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM asignacion_centros WHERE usuario_id = ? AND centro_id = ?");
    $stmt->execute([intval($usuarioId), intval($centroId)]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Comprueba que un consultorio pertenezca a la sede indicada.
 */
function consultorioEnSede($pdo, $consultorioId, $centroId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM consultorios WHERE id = ? AND centro_id = ? AND clinica_id = ?");
    $stmt->execute([intval($consultorioId), intval($centroId), $_SESSION['clinica']]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Asigna un usuario a una sede (si no lo estaba).
 */
function asignarUsuarioASede($pdo, $usuarioId, $centroId) {
    $pdo->prepare("INSERT IGNORE INTO asignacion_centros (clinica_id, usuario_id, centro_id) VALUES (?, ?, ?)")
        ->execute([$_SESSION['clinica'], intval($usuarioId), intval($centroId)]);
}

/* ------------------------------------------------------------------
 * Ayudas para peticiones AJAX (responden JSON y detienen la ejecución)
 * ------------------------------------------------------------------ */

function responderErrorSede($mensaje, $codigo = 403) {
    if (!headers_sent()) {
        header('Content-Type: application/json', true, $codigo);
    }
    echo json_encode(['status' => 'error', 'message' => $mensaje]);
    exit();
}

/**
 * Sede donde se registrará un dato nuevo. En "Todas las sedes" no se puede crear.
 */
function sedeParaRegistrarAJAX() {
    $actual = sedeActual();
    if ($actual === null) {
        responderErrorSede('Está en "Todas las sedes". Cambie a una sede específica (menú superior) para registrar información.', 400);
    }
    return $actual;
}

/**
 * Detiene la petición si la sede indicada no es visible para el usuario.
 */
function validarSedeAJAX($centroId) {
    if (!puedeVerSede($centroId)) {
        responderErrorSede('La sede indicada no corresponde a su sede de trabajo.');
    }
    return intval($centroId);
}

/**
 * Detiene la petición si el usuario indicado no pertenece a las sedes visibles.
 */
function validarUsuarioEnSedeAJAX($pdo, $usuarioId, $mensaje = 'El registro no pertenece a su sede de trabajo.') {
    if (!usuarioEnSedesVisibles($pdo, $usuarioId)) {
        responderErrorSede($mensaje);
    }
}
