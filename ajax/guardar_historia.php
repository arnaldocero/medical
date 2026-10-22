<?php
session_start();
// Seguridad: sesión activa + permiso del módulo
require_once __DIR__ . '/../config/security.php';
requerirPermisoAJAX('medico.panel');
header('Content-Type: application/json');

if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión expirada.']);
    exit();
}

require_once '../config/db.php';

// Captura de datos generales de la historia
$cita_id = intval($_POST['cita_id'] ?? 0);
$paciente_id = intval($_POST['paciente_id'] ?? 0); // ID de usuarios_clinicas
$motivo_consulta = trim($_POST['motivo_consulta'] ?? '');
$diagnostico_id = intval($_POST['diagnostico_id'] ?? 0); // Tu buscador dinámico de CIE-10
$enfermedad_actual = trim($_POST['enfermedad_actual'] ?? '');

// Signos Vitales
$presion_arterial = trim($_POST['presion_arterial'] ?? '');
$frecuencia_cardiaca = trim($_POST['frecuencia_cardiaca'] ?? '');
$temperatura = trim($_POST['temperatura'] ?? '');
$saturacion_oxigeno = trim($_POST['saturacion_oxigeno'] ?? '');
$peso = !empty($_POST['peso']) ? floatval($_POST['peso']) : null;
$estatura = !empty($_POST['estatura']) ? floatval($_POST['estatura']) : null;
$imc = trim($_POST['imc'] ?? '');
$examen_fisico = trim($_POST['examen_fisico'] ?? '');

// Captura de Datos de la Receta Médica integrada
$medicamentos = $_POST['med_items'] ?? [];
$observaciones_formula = trim($_POST['observaciones_formula'] ?? '');

// CAPTURA DEL ODONTOGRAMA: Recibe la cadena estructurada JSON generada por JS
$odontograma_raw = $_POST['odontograma_json'] ?? '[]';

if ($cita_id === 0 || $paciente_id === 0 || empty($motivo_consulta) || $diagnostico_id === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Los campos con asterisco (*) son obligatorios, incluyendo el Diagnóstico.']);
    exit();
}

// La cita debe ser del médico en sesión, del paciente indicado y de su sede de trabajo
$stmtCita = $pdo->prepare("SELECT centro_id FROM citas_medicas WHERE id = ? AND medico_id = ? AND paciente_id = ? AND clinica_id = ?");
$stmtCita->execute([$cita_id, intval($_SESSION['id']), $paciente_id, intval($_SESSION['clinica'])]);
$centro_cita = $stmtCita->fetchColumn();
if ($centro_cita === false || !puedeVerSede($centro_cita)) {
    echo json_encode(['status' => 'error', 'message' => 'La cita no corresponde a su agenda en esta sede.']);
    exit();
}

// FORMATO DE HISTORIA: debe ser uno de los formatos disponibles para el médico
require_once '../config/formatos.php';
$formato_id = intval($_POST['formato_id'] ?? 0);
$idsPermitidos = array_map('intval', array_column(formatosDelMedico($pdo, $_SESSION['id'], $_SESSION['clinica']), 'id'));
if (!in_array($formato_id, $idsPermitidos, true)) {
    echo json_encode(['status' => 'error', 'message' => 'El formato de historia clínica no está asignado a su usuario.']);
    exit();
}
$formato = obtenerFormato($pdo, $formato_id, $_SESSION['clinica']);

// Solo se procesan las secciones activas del formato
if (!formatoTieneSeccion($formato, 'odontograma')) {
    $odontograma_raw = '[]';
}
if (!formatoTieneSeccion($formato, 'plan_formula')) {
    $medicamentos = [];
}
$guardar_vision = formatoTieneSeccion($formato, 'vision');

// CAMPOS PERSONALIZADOS: obligatorios y tipo de dato
$valoresCampos = [];
$respuestas = (array) ($_POST['campos_formato'] ?? []);
foreach ($formato['campos'] as $campo) {
    $valor = trim((string) ($respuestas[$campo['id']] ?? ''));

    if ($valor === '') {
        if (intval($campo['obligatorio']) === 1) {
            echo json_encode(['status' => 'warning', 'message' => 'El campo "' . $campo['etiqueta'] . '" es obligatorio.']);
            exit();
        }
        continue;
    }

    $valido = true;
    switch ($campo['tipo']) {
        case 'numero': $valido = is_numeric($valor); break;
        case 'fecha':  $valido = (bool) DateTime::createFromFormat('Y-m-d', $valor); break;
        case 'si_no':  $valido = in_array($valor, ['SI', 'NO'], true); break;
        case 'lista':  $valido = in_array($valor, opcionesCampo($campo), true); break;
    }
    if (!$valido) {
        echo json_encode(['status' => 'warning', 'message' => 'El valor del campo "' . $campo['etiqueta'] . '" no es válido.']);
        exit();
    }
    $valoresCampos[] = [$campo['id'], $campo['etiqueta'], $valor];
}

try {
    $pdo->beginTransaction();

    $medico_usuario_id = intval($_SESSION['id']);
    $clinica_id = intval($_SESSION['clinica']);

    // 1. OBTENER RELACIONES INTERNAS DE LLAVES FORÁNEAS
    // A. ID de pacientes_datos desde usuario_id
    $stmtP = $pdo->prepare("SELECT id FROM pacientes_datos WHERE usuario_id = ? LIMIT 1");
    $stmtP->execute([$paciente_id]);
    $paciente_datos = $stmtP->fetch();
    $paciente_datos_id = $paciente_datos ? intval($paciente_datos['id']) : 0;

    // B. ID de datos_medicos desde usuario_id
    $stmtM = $pdo->prepare("SELECT id FROM datos_medicos WHERE usuario_id = ? LIMIT 1");
    $stmtM->execute([$medico_usuario_id]);
    $medico_datos = $stmtM->fetch();
    $medico_datos_id = $medico_datos ? intval($medico_datos['id']) : 0;

    if ($paciente_datos_id === 0 || $medico_datos_id === 0) {
        throw new Exception("Error de consistencia en los perfiles relacionales del médico o paciente.");
    }

    // C. Obtener el código CIE-10 de forma interna para guardarlo en la fórmula tradicional
    $stmtCie = $pdo->prepare("SELECT codigo_cie FROM diagnosticos WHERE id = ? LIMIT 1");
    $stmtCie->execute([$diagnostico_id]);
    $cie_res = $stmtCie->fetch();
    $codigo_cie_string = $cie_res ? $cie_res['codigo_cie'] : 'U00';

    // 2. GUARDAR HISTORIA CLÍNICA
    $sqlHC = "INSERT INTO historias_clinicas (
        cita_id, paciente_id, medico_id, formato_id, motivo_consulta, enfermedad_actual, 
        presion_arterial, frecuencia_cardiaca, temperatura, saturacion_oxigeno, 
        peso, estatura, imc, examen_physical, diagnostico_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmtHC = $pdo->prepare($sqlHC);
    $stmtHC->execute([
        $cita_id, $paciente_id, $medico_usuario_id, $formato_id, $motivo_consulta, $enfermedad_actual,
        $presion_arterial, $frecuencia_cardiaca, $temperatura, $saturacion_oxigeno,
        $peso, $estatura, $imc, $examen_fisico, $diagnostico_id
    ]);
    
    // CAPTURA RELACIONAL: Extrae el ID asignado a la historia clínica que se acaba de crear
    $historia_clinica_id = $pdo->lastInsertId();

    // Respuestas de los campos personalizados del formato (se guarda la etiqueta por si luego cambia)
    if (!empty($valoresCampos)) {
        $stmtValor = $pdo->prepare("INSERT INTO historia_campos_valores (historia_id, campo_id, etiqueta, valor) VALUES (?, ?, ?, ?)");
        foreach ($valoresCampos as [$campoId, $etiqueta, $valor]) {
            $stmtValor->execute([$historia_clinica_id, $campoId, $etiqueta, $valor]);
        }
    }

    // 3. PROCESAR Y GUARDAR EL ODONTOGRAMA CLÍNICO
    $datosOdontograma = json_decode($odontograma_raw, true);

    if (is_array($datosOdontograma) && !empty($datosOdontograma)) {
        // Query adaptado exactamente a las columnas de tu tabla `historias_odontogramas`
        $sqlOdon = "INSERT INTO historias_odontogramas (
                        historia_clinica_id, numero_diente, cara_diente, estado_diente, observaciones
                    ) VALUES (?, ?, ?, ?, ?)";
        $stmtOdon = $pdo->prepare($sqlOdon);

        foreach ($datosOdontograma as $registro) {
            $numDiente     = intval($registro['diente'] ?? 0);
            $caraDiente    = trim($registro['cara'] ?? '');
            $estadoDiente  = trim($registro['estado'] ?? '');
            $observaciones = ""; // Campo disponible en tu tabla por si requieres notas personalizadas por pieza

            // Validación de seguridad para que no intente guardar filas vacías
            if ($numDiente > 0 && !empty($caraDiente) && !empty($estadoDiente)) {
                $stmtOdon->execute([
                    $historia_clinica_id, 
                    $numDiente, 
                    $caraDiente, 
                    $estadoDiente, 
                    $observaciones
                ]);
            }
        }
    }
    // =========================================================================
    // 3.5. PROCESAR Y GUARDAR EL EXAMEN DE LA VISTA / OPTOMETRÍA (NUEVO)
    // =========================================================================
    // Captura de los campos enviados desde la pestaña de Visión
    $av_lejos_od            = trim($_POST['av_lejos_od'] ?? '');
    $av_lejos_oi            = trim($_POST['av_lejos_oi'] ?? '');
    $av_cerca_od            = trim($_POST['av_cerca_od'] ?? '');
    $av_cerca_oi            = trim($_POST['av_cerca_oi'] ?? '');
    
    $ref_esfera_od          = trim($_POST['ref_esfera_od'] ?? '');
    $ref_cilindro_od        = trim($_POST['ref_cilindro_od'] ?? '');
    $ref_eje_od             = trim($_POST['ref_eje_od'] ?? '');
    $ref_adicion_od         = trim($_POST['ref_adicion_od'] ?? '');
    
    $ref_esfera_oi          = trim($_POST['ref_esfera_oi'] ?? '');
    $ref_cilindro_oi        = trim($_POST['ref_cilindro_oi'] ?? '');
    $ref_eje_oi             = trim($_POST['ref_eje_oi'] ?? '');
    $ref_adicion_oi         = trim($_POST['ref_adicion_oi'] ?? '');
    
    $presion_intraocular_od = trim($_POST['presion_intraocular_od'] ?? '');
    $presion_intraocular_oi = trim($_POST['presion_intraocular_oi'] ?? '');
    
    $examen_lente_hendidura = trim($_POST['examen_lente_hendidura'] ?? '');
    $examen_fondo_ojo       = trim($_POST['examen_fondo_ojo'] ?? '');

    // Condición de seguridad: Guardar solo si al menos uno de los campos principales de visión 
    // contiene información (así evitamos insertar filas totalmente vacías cuando atienda un Médico General u Odontólogo)
    if ($guardar_vision && (!empty($av_lejos_od) || !empty($av_lejos_oi) || !empty($ref_esfera_od) || !empty($ref_esfera_oi) || !empty($examen_lente_hendidura))) {
        
        $sqlVision = "INSERT INTO historias_vision (
            historia_clinica_id, av_lejos_od, av_lejos_oi, av_cerca_od, av_cerca_oi, 
            ref_esfera_od, ref_cilindro_od, ref_eje_od, ref_adicion_od, 
            ref_esfera_oi, ref_cilindro_oi, ref_eje_oi, ref_adicion_oi, 
            presion_intraocular_od, presion_intraocular_oi, examen_lente_hendidura, examen_fondo_ojo
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmtVision = $pdo->prepare($sqlVision);
        $stmtVision->execute([
            $historia_clinica_id, // Usamos la variable relacional que ya genera tu archivo automáticamente
            $av_lejos_od, 
            $av_lejos_oi, 
            $av_cerca_od, 
            $av_cerca_oi, 
            $ref_esfera_od, 
            $ref_cilindro_od, 
            $ref_eje_od, 
            $ref_adicion_od, 
            $ref_esfera_oi, 
            $ref_cilindro_oi, 
            $ref_eje_oi, 
            $ref_adicion_oi, 
            $presion_intraocular_od, 
            $presion_intraocular_oi, 
            $examen_lente_hendidura, 
            $examen_fondo_ojo
        ]);
    }

    // 4. GUARDAR FÓRMULA MÉDICA (SOLO SI TIENE FÁRMACOS ASIGNADOS)
    if (!empty($medicamentos)) {
        
        // A. Insertar cabecera de la fórmula médica
        $sqlCabecera = "INSERT INTO formulas_medicas (clinica_id, centro_id, paciente_id, medico_id, diagnostico_cie10, observaciones, estado) 
                        VALUES (?, ?, ?, ?, ?, ?, 'PENDIENTE')";
        $stmtCabecera = $pdo->prepare($sqlCabecera);
        $stmtCabecera->execute([
            $clinica_id, 
            $centro_cita, 
            $paciente_datos_id, 
            $medico_datos_id, 
            $codigo_cie_string, 
            $observaciones_formula
        ]);
        $formula_id = $pdo->lastInsertId();

        // B. Insertar desglose de fármacos
        $sqlDetalle = "INSERT INTO formula_detalles (formula_id, medicamento_id, cantidad_solicitada, dosificacion) 
                       VALUES (?, ?, ?, ?)";
        $stmtDetalle = $pdo->prepare($sqlDetalle);

        foreach ($medicamentos as $item) {
            $med_id = intval($item['medicamento_id']);
            $cant = intval($item['cantidad']);
            $dosis = trim($item['dosificacion']);

            if ($med_id > 0 && $cant > 0 && !empty($dosis)) {
                $stmtDetalle->execute([$formula_id, $med_id, $cant, $dosis]);
            }
        }
    }

    // 5. CAMBIAR EL ESTADO DE LA CITA A FINALIZADA
    $stmtCita = $pdo->prepare("UPDATE citas_medicas SET estado = 'Atendido' WHERE id = ?");
    $stmtCita->execute([$cita_id]);

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Atención médica, odontograma y receta guardados correctamente.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}