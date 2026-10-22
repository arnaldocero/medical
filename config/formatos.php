<?php
/**
 * Formatos de historia clínica configurables.
 *
 * Un formato define qué SECCIONES muestra la historia y qué CAMPOS PERSONALIZADOS pide.
 * "Motivo / enfermedad actual" y "Diagnóstico CIE-10" se incluyen siempre (los exige el RIPS).
 */

// Secciones opcionales: clave => [nombre visible, icono Font Awesome]
define('SECCIONES_FORMATO', [
    'signos_vitales' => ['Signos vitales', 'fa-heartbeat'],
    'examen_fisico'  => ['Examen físico / hallazgos', 'fa-user-md'],
    'plan_formula'   => ['Plan / fórmula médica', 'fa-pills'],
    'anexos'         => ['Anexos y ayudas diagnósticas', 'fa-paperclip'],
    'odontograma'    => ['Odontograma', 'fa-tooth'],
    'vision'         => ['Examen de la vista', 'fa-eye'],
]);

// Tipos de campo personalizado: clave => nombre visible
define('TIPOS_CAMPO_FORMATO', [
    'texto'   => 'Texto corto',
    'parrafo' => 'Párrafo',
    'numero'  => 'Número',
    'fecha'   => 'Fecha',
    'si_no'   => 'Sí / No',
    'lista'   => 'Lista de opciones',
]);

/**
 * Limpia una lista de secciones dejando solo claves válidas.
 */
function normalizarSecciones($secciones) {
    if (is_string($secciones)) {
        $secciones = json_decode($secciones, true);
    }
    return array_values(array_intersect(array_keys(SECCIONES_FORMATO), (array) $secciones));
}

/**
 * Formatos activos que puede usar un médico: los asignados, o los predeterminados de la clínica si no tiene ninguno.
 */
function formatosDelMedico($pdo, $usuarioId, $clinicaId) {
    $stmt = $pdo->prepare("SELECT f.id, f.nombre, f.descripcion, f.secciones
                           FROM formatos_historia f
                           INNER JOIN formato_medicos fm ON fm.formato_id = f.id
                           WHERE fm.usuario_id = ? AND f.clinica_id = ? AND f.estado = 1
                           ORDER BY f.nombre");
    $stmt->execute([intval($usuarioId), $clinicaId]);
    $formatos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($formatos)) {
        $stmt = $pdo->prepare("SELECT id, nombre, descripcion, secciones FROM formatos_historia
                               WHERE clinica_id = ? AND estado = 1 AND es_predeterminado = 1 ORDER BY nombre");
        $stmt->execute([$clinicaId]);
        $formatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return $formatos;
}

/**
 * Formato completo (secciones decodificadas y campos activos ordenados), o null si no existe en la clínica.
 */
function obtenerFormato($pdo, $formatoId, $clinicaId) {
    $stmt = $pdo->prepare("SELECT * FROM formatos_historia WHERE id = ? AND clinica_id = ?");
    $stmt->execute([intval($formatoId), $clinicaId]);
    $formato = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$formato) {
        return null;
    }
    $formato['secciones'] = normalizarSecciones($formato['secciones']);

    $stmtC = $pdo->prepare("SELECT id, etiqueta, tipo, opciones, obligatorio, orden
                            FROM formato_campos WHERE formato_id = ? AND estado = 1 ORDER BY orden, id");
    $stmtC->execute([$formato['id']]);
    $formato['campos'] = $stmtC->fetchAll(PDO::FETCH_ASSOC);
    return $formato;
}

function formatoTieneSeccion($formato, $seccion) {
    return $formato && in_array($seccion, $formato['secciones'], true);
}

/**
 * Opciones de un campo tipo lista (una por línea).
 */
function opcionesCampo($campo) {
    return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $campo['opciones']))));
}
