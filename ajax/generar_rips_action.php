<?php
session_start();
if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión expirada.']);
    exit();
}

require_once '../config/db.php';

$action = $_REQUEST['action'] ?? '';

// --- ACCIÓN 1: LISTAR LAS CONSULTAS REALIZADAS ---
if ($action === 'listar_atenciones') {
    $fecha_inicio = $_GET['fecha_inicio'] ?? '';
    $fecha_fin = $_GET['fecha_fin'] ?? '';

    if (empty($fecha_inicio) || empty($fecha_fin)) {
        echo json_encode(['status' => 'error', 'message' => 'Fechas inválidas']);
        exit();
    }

    try {
        // Consultamos historias clínicas cerradas uniendo citas, diagnósticos y datos de la clínica
        $stmt = $pdo->prepare("
            SELECT 
                hc.id AS historia_id,
                cm.fecha_cita AS fecha_atencion,
                cm.hora_inicio,
                pd.documento_identidad,
                uc_p.nombre AS paciente,
                cie.codigo_cie AS codigo_cie10,
                cie.descripcion AS nombre_diagnostico,
                uc_m.nombre AS medico
            FROM historias_clinicas hc
            INNER JOIN citas_medicas cm ON hc.cita_id = cm.id
            INNER JOIN pacientes_datos pd ON hc.paciente_id = pd.usuario_id
            INNER JOIN usuarios_clinicas uc_p ON pd.usuario_id = uc_p.id
            INNER JOIN usuarios_clinicas uc_m ON cm.medico_id = uc_m.id
            INNER JOIN diagnosticos cie ON hc.diagnostico_id = cie.id
            WHERE cm.clinica_id = ? AND cm.fecha_cita BETWEEN ? AND ?
            ORDER BY cm.fecha_cita DESC, cm.hora_inicio DESC
        ");
        $stmt->execute([$_SESSION['clinica'], $fecha_inicio, $fecha_fin]);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'data' => $resultados]);
        exit();

    } catch (PDOException $e) {
        // Si la base de datos falla, devolvemos el error exacto como JSON
        echo json_encode([
            'status' => 'error', 
            'message' => 'Error en la consulta de base de datos: ' . $e->getMessage()
        ]);
        exit();
    }
}

// (El resto del código de la ACCIÓN 2: descargar_rips se mantiene exactamente igual...)

// --- ACCIÓN 2: GENERAR OBJETO JSON RIPS OFICIAL ---
if ($action === 'descargar_rips') {
    $ids_json = $_POST['historias_ids'] ?? '[]';
    $ids_array = json_decode($ids_json, true);

    if (empty($ids_array)) {
        die("No se enviaron registros válidos.");
    }

    // Marcador de posición (?,?,?) para PDO
    $in_clause = implode(',', array_fill(0, count($ids_array), '?'));

    // Traemos toda la data médica detallada de los registros seleccionados
    $stmt = $pdo->prepare("
        SELECT 
            hc.*, 
            cm.fecha_cita, cm.hora_inicio,
            pd.documento_identidad, pd.genero, pd.fecha_nacimiento,
            cie.codigo_cie AS cie10_codigo,
            clinica.nit_empresa, clinica.nombre_clinica
        FROM historias_clinicas hc
        INNER JOIN citas_medicas cm ON hc.cita_id = cm.id
        INNER JOIN pacientes_datos pd ON hc.paciente_id = pd.usuario_id
        INNER JOIN diagnosticos cie ON hc.diagnostico_id = cie.id
        INNER JOIN clientes_clinicas clinica ON cm.clinica_id = clinica.id
        WHERE hc.id IN ($in_clause)
    ");
    $stmt->execute($ids_array);
    $atenciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($atenciones)) {
        die("No se encontró información en la base de datos.");
    }

    // Obtenemos los datos comunes de la clínica/prestador usando el primer registro
    $info_clinica = $atenciones[0];

    // Estructura raíz del nuevo estándar RIPS JSON de MinSalud
    $rips_json = [
        "numRadicado" => "RAD-" . date('Ymd-His'),
        "fechaRadicacion" => date('Y-m-d'),
        "prestador" => [
            "codPrestador" => $info_clinica['codigo_prestador'] ?? "000000000001", // Código habilitación REPS
            "tipoInscripcion" => "P",
            "razonSocial" => $info_clinica['razon_social']
        ],
        "usuarios" => [],
        "consultas" => []
    ];

    // Procesamos cada atención e inyectamos los datos en la estructura JSON
    foreach ($atenciones as $index => $at) {
        
        // Formatear tipo de documento según estándares de salud (ej: CC, TI, CE, RC)
        $tipo_doc = "CC"; 

        // 1. Agregar bloque de "Usuarios" (Evitando duplicar usuarios en el mismo reporte)
        $usuario_existe = false;
        foreach ($rips_json['usuarios'] as $u) {
            if ($u['numIdentificacion'] === $at['documento_identidad']) {
                $usuario_existe = true;
                break;
            }
        }

        if (!$usuario_existe) {
            $rips_json['usuarios'][] = [
                "tipoIdentificacion" => $tipo_doc,
                "numIdentificacion" => $at['documento_identidad'],
                "tipoUsuario" => "01", // 01 = Contributivo, 02 = Subsidiado, etc.
                "fechaNacimiento" => $at['fecha_nacimiento'],
                "codSexo" => ($at['genero'] === 'masculino' || $at['genero'] === 'M') ? "M" : "F",
                "codPaisResidencia" => "170", // Colombia
                "codMunicipioResidencia" => "08001" // Ejemplo: Barranquilla
            ];
        }

        // 2. Agregar bloque de "Consultas" (Equivalente al antiguo archivo AC)
        $rips_json['consultas'][] = [
            "codPrestador" => $info_clinica['codigo_prestador'] ?? "000000000001",
            "numFactura" => "FE-" . $at['cita_id'], // Vinculación obligatoria a la factura
            "tipoIdentificacion" => $tipo_doc,
            "numIdentificacion" => $at['documento_identidad'],
            "fechaInicioConsulta" => $at['fecha_cita'] . "T" . $at['hora_inicio'] . ":00",
            "numAutorizacion" => null,
            "codConsulta" => "890201", // Código CUPS estándar para consulta médica general
            "finalidadTecnologiaSalud" => "44", // 44 = Promoción y prevención / General
            "causaExterna" => "13", // 13 = Enfermedad general
            "codDiagnosticoPrincipal" => $at['cie10_codigo'],
            "codDiagnosticoRelacionado1" => null,
            "tipoDiagnosticoPrincipal" => "01", // 01 = Impresión diagnóstica, 02 = Confirmado nuevo
            "vlrCoopagoShared" => 0,
            "vlrConcepto" => 0 // Valor del servicio preestablecido
        ];
    }

    // Convertimos el array de PHP a un archivo de texto formateado como JSON limpio
    $json_string = json_encode($rips_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    // Forzar al navegador web a descargar el archivo .json en lugar de mostrarlo
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="RIPS_Minsalud_' . date('Ymd_His') . '.json"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . strlen($json_string));
    
    echo $json_string;
    exit();
}