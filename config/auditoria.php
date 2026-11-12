<?php
/**
 * Auditoría de cambios: quién, cuándo, desde dónde y qué cambió (antes / después).
 * Los registros son inalterables (triggers en la tabla `auditoria`, migración 006).
 *
 * Uso: auditar($pdo, 'paciente', 'editar', $id, 'Editó al paciente X', $antes, $despues);
 */

// Campos que nunca se guardan en la auditoría
define('AUDITORIA_CAMPOS_OCULTOS', '/pass|token|clave_tecnica|csrf/i');

/**
 * Limpia los datos antes de guardarlos: quita contraseñas/tokens y los convierte a JSON.
 */
function auditoriaJson($datos) {
    if ($datos === null) {
        return null;
    }
    if (is_array($datos)) {
        array_walk_recursive($datos, function (&$valor, $clave) {
            if (is_string($clave) && preg_match(AUDITORIA_CAMPOS_OCULTOS, $clave)) {
                $valor = '[oculto]';
            }
        });
        foreach (array_keys($datos) as $clave) {
            if (is_string($clave) && preg_match(AUDITORIA_CAMPOS_OCULTOS, $clave)) {
                $datos[$clave] = '[oculto]';
            }
        }
    }
    return json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
}

/**
 * Registra una acción en la auditoría. Nunca interrumpe la operación: si falla, lo deja en el log.
 *
 * @param string     $modulo      historia, formula, factura, usuario, medico, paciente, rol, cita...
 * @param string     $accion      crear, editar, desactivar, despachar, emitir, vincular...
 * @param mixed      $registroId  ID del registro afectado
 * @param string     $descripcion Texto legible de lo que pasó
 * @param array|null $antes       Datos antes del cambio
 * @param array|null $despues     Datos después del cambio
 * @param array      $actor       Opcional: ['id' => .., 'nombre' => .., 'clinica' => ..] cuando no hay sesión
 */
function auditar($pdo, $modulo, $accion, $registroId, $descripcion, $antes = null, $despues = null, $actor = []) {
    try {
        $centro = $_SESSION['centro'] ?? null;
        $pdo->prepare("INSERT INTO auditoria
                (clinica_id, centro_id, usuario_id, usuario_nombre, modulo, accion, registro_id, descripcion, datos_antes, datos_despues, ip)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([
                $actor['clinica'] ?? ($_SESSION['clinica'] ?? null),
                $centro ? intval($centro) : null,
                $actor['id'] ?? ($_SESSION['id'] ?? null),
                mb_substr($actor['nombre'] ?? ($_SESSION['nombre'] ?? 'Sistema'), 0, 150),
                mb_substr($modulo, 0, 40),
                mb_substr($accion, 0, 40),
                $registroId !== null ? mb_substr((string) $registroId, 0, 40) : null,
                mb_substr($descripcion, 0, 500),
                auditoriaJson($antes),
                auditoriaJson($despues),
                substr($_SERVER['REMOTE_ADDR'] ?? 'cli', 0, 45),
            ]);
    } catch (Exception $e) {
        error_log('No se pudo registrar la auditoría (' . $modulo . '/' . $accion . '): ' . $e->getMessage());
    }
}

/**
 * Devuelve una fila como arreglo (para guardar el "antes" de un cambio).
 */
function filaAuditoria($pdo, $sql, $params) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        return null;
    }
}
