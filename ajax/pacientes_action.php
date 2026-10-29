<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
session_start();
// Seguridad: sesión activa + permiso del módulo
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/roles.php';
requerirPermisoAJAX('pacientes.gestionar');

if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión expirada o clínica no asignada.']);
    exit();
}

require_once '../config/db.php'; 

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$clinica_id = intval($_SESSION['clinica']);

/**
 * Busca un paciente por documento: devuelve usuario_id, nombre y clinica_id, o null.
 */
function buscarPacientePorDocumento($pdo, $documento) {
    $stmt = $pdo->prepare("SELECT p.id AS paciente_id, u.id AS usuario_id, u.nombre, u.clinica_id
                           FROM pacientes_datos p
                           INNER JOIN usuarios_clinicas u ON u.id = p.usuario_id
                           WHERE p.documento_identidad = ? LIMIT 1");
    $stmt->execute([$documento]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Verifica que la ficha (pacientes_datos.id) sea de la clínica y de una sede visible.
 * Devuelve el usuario_id del paciente o detiene la petición.
 */
function validarPacienteDatosAJAX($pdo, $idPaciente, $clinica_id) {
    $stmt = $pdo->prepare("SELECT p.usuario_id FROM pacientes_datos p
                           INNER JOIN usuarios_clinicas u ON u.id = p.usuario_id
                           WHERE p.id = ? AND u.clinica_id = ?");
    $stmt->execute([intval($idPaciente), $clinica_id]);
    $usuarioId = $stmt->fetchColumn();
    if ($usuarioId === false) {
        responderErrorSede('Paciente no encontrado.', 404);
    }
    validarUsuarioEnSedeAJAX($pdo, $usuarioId, 'El paciente no pertenece a su sede de trabajo.');
    return intval($usuarioId);
}

switch ($action) {

    // Vincular a la sede actual un paciente ya registrado en otra sede de la clínica
    case 'vincular':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $sedeNuevo = sedeParaRegistrarAJAX();
            $existente = buscarPacientePorDocumento($pdo, trim($_POST['documento'] ?? ''));

            if (!$existente || $existente['clinica_id'] != $clinica_id) {
                echo json_encode(['status' => 'error', 'message' => 'Paciente no encontrado.']);
                exit();
            }

            asignarUsuarioASede($pdo, $existente['usuario_id'], $sedeNuevo);
            echo json_encode(['status' => 'success', 'message' => 'Paciente ' . $existente['nombre'] . ' vinculado a ' . $_SESSION['centro_nombre'] . '.']);
        }
        break;

    case 'crear':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Sede donde se registra el paciente (no se permite en "Todas las sedes")
            $sedeNuevo = sedeParaRegistrarAJAX();
            try {
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->beginTransaction();

                $nombre = trim($_POST['nombre'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $usuario = trim($_POST['usuario'] ?? '');
                $password = $_POST['password'] ?? '';
                $rol_id = intval($_POST['rol_id'] ?? 5);
                if (!esRolPaciente($rol_id)) {
                    echo json_encode(['status' => 'error', 'message' => 'Rol no válido para un paciente.']);
                    exit();
                }
                $estado = intval($_POST['estado'] ?? 1);

                $documento = trim($_POST['documento_identidad'] ?? '');
                $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
                $genero = !empty($_POST['genero']) ? $_POST['genero'] : null;
                $tipo_sangre = !empty($_POST['tipo_sangre']) ? $_POST['tipo_sangre'] : null;
                $telefono = trim($_POST['telefono_contacto'] ?? '');
                $eps_id = intval($_POST['eps_id'] ?? 0);
                $direccion = trim($_POST['direccion'] ?? '');
                $contacto_emergencia_nombre = !empty($_POST['contacto_emergencia_nombre']) ? trim($_POST['contacto_emergencia_nombre']) : null;
                $contacto_emergencia_telefono = !empty($_POST['contacto_emergencia_telefono']) ? trim($_POST['contacto_emergencia_telefono']) : null;

                if (empty($nombre) || empty($email) || empty($usuario) || empty($password) || empty($documento) || empty($eps_id)) {
                    $pdo->rollBack();
                    echo json_encode(['status' => 'warning', 'message' => 'Todos los campos con (*) son obligatorios.']);
                    exit();
                }

                // Validar si el documento de identidad ya existe (antes que usuario/correo,
                // para poder ofrecer la vinculación a esta sede de un paciente de otra sede)
                $existente = buscarPacientePorDocumento($pdo, $documento);
                if ($existente) {
                    $pdo->rollBack();
                    if ($existente['clinica_id'] != $clinica_id) {
                        echo json_encode(['status' => 'warning', 'message' => 'El documento de identidad ya está registrado.']);
                    } elseif (usuarioEnSedesVisibles($pdo, $existente['usuario_id'])) {
                        echo json_encode(['status' => 'warning', 'message' => 'Este paciente ya está registrado en su sede: ' . $existente['nombre'] . '.']);
                    } else {
                        echo json_encode([
                            'status'    => 'vincular',
                            'message'   => 'El paciente ' . $existente['nombre'] . ' (documento ' . $documento . ') ya está registrado en otra sede. ¿Desea vincularlo a ' . ($_SESSION['centro_nombre'] ?? 'esta sede') . '? Se conservará su historia clínica.',
                            'documento' => $documento
                        ]);
                    }
                    exit();
                }

                // Validar si el usuario o email ya existen
                $stmtCheck = $pdo->prepare("SELECT id FROM usuarios_clinicas WHERE (usuario = ? OR email = ?) LIMIT 1");
                $stmtCheck->execute([$usuario, $email]);
                if ($stmtCheck->fetch()) {
                    $pdo->rollBack();
                    echo json_encode(['status' => 'warning', 'message' => 'El nombre de usuario o correo ya existen.']);
                    exit();
                }

                // 1. Insertar en usuarios_clinicas
                $passHash = password_hash($password, PASSWORD_DEFAULT);
                $sqlUser = "INSERT INTO usuarios_clinicas (clinica_id, rol_id, nombre, usuario, email, password, estado, fecha_creacion) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmtUser = $pdo->prepare($sqlUser);
                $stmtUser->execute([$clinica_id, $rol_id, $nombre, $usuario, $email, $passHash, $estado]);
                
                $idUsuarioCreado = $pdo->lastInsertId();

                // El paciente queda vinculado a la sede de trabajo actual
                asignarUsuarioASede($pdo, $idUsuarioCreado, $sedeNuevo);

                // 2. Insertar en pacientes_datos
                $sqlPac = "INSERT INTO pacientes_datos 
                           (usuario_id, fecha_nacimiento, genero, tipo_sangre, documento_identidad, telefono_contacto, direccion, eps_id, contacto_emergencia_nombre, contacto_emergencia_telefono) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmtPac = $pdo->prepare($sqlPac);
                $stmtPac->execute([
                    $idUsuarioCreado,
                    $fecha_nacimiento,
                    $genero,
                    $tipo_sangre,
                    $documento,
                    $telefono,
                    $direccion,
                    $eps_id,
                    $contacto_emergencia_nombre,
                    $contacto_emergencia_telefono
                ]);

                $pdo->commit();
                echo json_encode(['status' => 'success', 'message' => 'Paciente registrado exitosamente.']);

            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => mensajeErrorInterno($e)]);
            }
        }
        break;

    case 'editar':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->beginTransaction();

                $idPaciente = intval($_POST['idPaciente'] ?? 0);
                $idUsuario  = intval($_POST['idUsuario'] ?? 0);

                if ($idPaciente === 0 || $idUsuario === 0) {
                    throw new Exception("Identificadores de paciente inválidos para la edición.");
                }

                // La ficha debe ser de su sede y corresponder al usuario indicado
                if (validarPacienteDatosAJAX($pdo, $idPaciente, $clinica_id) !== $idUsuario) {
                    throw new Exception("Los datos del paciente no coinciden.");
                }

                $nombre = trim($_POST['nombre'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $usuario = trim($_POST['usuario'] ?? '');
                $password = $_POST['password'] ?? '';
                $rol_id = intval($_POST['rol_id'] ?? 5);
                if (!esRolPaciente($rol_id)) {
                    echo json_encode(['status' => 'error', 'message' => 'Rol no válido para un paciente.']);
                    exit();
                }
                $estado = intval($_POST['estado'] ?? 1);

                $documento = trim($_POST['documento_identidad'] ?? '');
                $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
                $genero = !empty($_POST['genero']) ? $_POST['genero'] : null;
                $tipo_sangre = !empty($_POST['tipo_sangre']) ? $_POST['tipo_sangre'] : null;
                $telefono = trim($_POST['telefono_contacto'] ?? '');
                $eps_id = intval($_POST['eps_id'] ?? 0);
                $direccion = trim($_POST['direccion'] ?? '');
                $contacto_emergencia_nombre = !empty($_POST['contacto_emergencia_nombre']) ? trim($_POST['contacto_emergencia_nombre']) : null;
                $contacto_emergencia_telefono = !empty($_POST['contacto_emergencia_telefono']) ? trim($_POST['contacto_emergencia_telefono']) : null;

                // Validar credenciales duplicadas excluyendo el usuario actual
                $stmtCheck = $pdo->prepare("SELECT id FROM usuarios_clinicas WHERE (usuario = ? OR email = ?) AND id != ? LIMIT 1");
                $stmtCheck->execute([$usuario, $email, $idUsuario]);
                if ($stmtCheck->fetch()) {
                    $pdo->rollBack();
                    echo json_encode(['status' => 'warning', 'message' => 'El usuario o correo ya está en uso.']);
                    exit();
                }

                // Validar documento duplicado excluyendo el paciente actual
                $stmtCheckDoc = $pdo->prepare("SELECT id FROM pacientes_datos WHERE documento_identidad = ? AND id != ? LIMIT 1");
                $stmtCheckDoc->execute([$documento, $idPaciente]);
                if ($stmtCheckDoc->fetch()) {
                    $pdo->rollBack();
                    echo json_encode(['status' => 'warning', 'message' => 'El documento ya está asignado a otro paciente.']);
                    exit();
                }

                // 1. Actualizar cuenta de usuario
                $sqlUser = "UPDATE usuarios_clinicas SET rol_id = ?, nombre = ?, usuario = ?, email = ?, estado = ? WHERE id = ? AND clinica_id = ?";
                $stmtUser = $pdo->prepare($sqlUser);
                $stmtUser->execute([$rol_id, $nombre, $usuario, $email, $estado, $idUsuario, $clinica_id]);

                if (!empty($password)) {
                    $passHash = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE usuarios_clinicas SET password = ? WHERE id = ?")->execute([$passHash, $idUsuario]);
                }

                // 2. Actualizar ficha médica
                $sqlPac = "UPDATE pacientes_datos SET 
                                fecha_nacimiento = ?, genero = ?, tipo_sangre = ?, 
                                documento_identidad = ?, telefono_contacto = ?, direccion = ?, 
                                eps_id = ?, contacto_emergencia_nombre = ?, contacto_emergencia_telefono = ? 
                           WHERE id = ?";
                $stmtPac = $pdo->prepare($sqlPac);
                $stmtPac->execute([
                    $fecha_nacimiento,
                    $genero,
                    $tipo_sangre,
                    $documento,
                    $telefono,
                    $direccion,
                    $eps_id,
                    $contacto_emergencia_nombre,
                    $contacto_emergencia_telefono,
                    $idPaciente
                ]);

                $pdo->commit();
                echo json_encode(['status' => 'success', 'message' => 'Información del paciente actualizada con éxito.']);

            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => mensajeErrorInterno($e)]);
            }
        }
        break;

    case 'eliminar':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $idPaciente = intval($_POST['id'] ?? 0);
            $idUsuario  = intval($_POST['usuario_id'] ?? 0);

            if ($idPaciente && $idUsuario) {
                if (validarPacienteDatosAJAX($pdo, $idPaciente, $clinica_id) !== $idUsuario) {
                    echo json_encode(['status' => 'error', 'message' => 'Los datos del paciente no coinciden.']);
                    exit();
                }
                try {
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $pdo->beginTransaction();

                    // Borrado lógico para proteger integridad referencial de citas y facturación
                    $stmtUser = $pdo->prepare("UPDATE usuarios_clinicas SET estado = 0 WHERE id = ? AND clinica_id = ?");
                    $stmtUser->execute([$idUsuario, $clinica_id]);

                    $pdo->commit();
                    echo json_encode(['status' => 'success', 'message' => 'Paciente desactivado correctamente.']);
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    echo json_encode(['status' => 'error', 'message' => mensajeErrorInterno($e)]);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Parámetros inválidos.']);
            }
        }
        break;

    case 'obtener_historial':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $paciente_id = intval($_POST['paciente_id'] ?? 0);

            if ($paciente_id === 0) {
                echo '<div class="alert alert-warning">ID de paciente no válido.</div>';
                exit();
            }

            // Solo pacientes vinculados a la sede de trabajo (la historia incluye todas sus sedes)
            validarPacienteDatosAJAX($pdo, $paciente_id, $clinica_id);

            try {
                $sqlHC = "SELECT hc.*, 
                                 u.nombre AS nombre_medico, 
                                 d.codigo_cie, d.descripcion AS descripcion_cie,
                                 fh.nombre AS nombre_formato
                          FROM historias_clinicas hc
                          LEFT JOIN formatos_historia fh ON fh.id = hc.formato_id
                          INNER JOIN pacientes_datos p ON hc.paciente_id = p.usuario_id
                          INNER JOIN usuarios_clinicas u ON hc.medico_id = u.id
                          LEFT JOIN diagnosticos d ON hc.diagnostico_id = d.id
                          WHERE p.id = ? 
                          ORDER BY hc.id DESC";
                
                $stmtHC = $pdo->prepare($sqlHC);
                $stmtHC->execute([$paciente_id]);
                $historias = $stmtHC->fetchAll(PDO::FETCH_ASSOC);

                if (empty($historias)) {
                    echo '<div class="alert alert-info text-center m-3"><i class="fas fa-folder-open"></i> Este paciente aún no cuenta con consultas médicas registradas.</div>';
                    exit();
                }

                foreach ($historias as $hc) {
                    $hc_id = $hc['id'];

                    // Fórmula médica
                    $stmtF = $pdo->prepare("SELECT * FROM formulas_medicas WHERE paciente_id = ? AND diagnostico_cie10 = ? LIMIT 1");
                    $stmtF->execute([$hc['paciente_id'], $hc['codigo_cie']]);
                    $formula = $stmtF->fetch(PDO::FETCH_ASSOC);

                    $detalles_formula = [];
                    if ($formula) {
                        $stmtFD = $pdo->prepare("SELECT fd.*, m.nombre_comercial, m.presentacion 
                                                 FROM formula_detalles fd
                                                 LEFT JOIN medicamentos m ON fd.medicamento_id = m.id
                                                 WHERE fd.formula_id = ?");
                        $stmtFD->execute([$formula['id']]);
                        $detalles_formula = $stmtFD->fetchAll(PDO::FETCH_ASSOC);
                    }

                    // Odontograma
                    $stmtO = $pdo->prepare("SELECT * FROM historias_odontogramas WHERE historia_clinica_id = ? ORDER BY numero_diente ASC");
                    $stmtO->execute([$hc_id]);
                    $odontograma = $stmtO->fetchAll(PDO::FETCH_ASSOC);

                    // Historia de visión
                    $stmtV = $pdo->prepare("SELECT * FROM historias_vision WHERE historia_clinica_id = ? LIMIT 1");
                    $stmtV->execute([$hc_id]);
                    $historiaVision = $stmtV->fetch(PDO::FETCH_ASSOC);

                    // Respuestas de los campos personalizados del formato
                    $stmtCV = $pdo->prepare("SELECT etiqueta, valor FROM historia_campos_valores WHERE historia_id = ? ORDER BY id");
                    $stmtCV->execute([$hc_id]);
                    $camposValores = $stmtCV->fetchAll(PDO::FETCH_ASSOC);

                    // Adjuntos
                    $stmtA = $pdo->prepare("SELECT * FROM pacientes_adjuntos WHERE historia_id = ?");
                    $stmtA->execute([$hc_id]);
                    $adjuntos = $stmtA->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <div class="card card-outline card-indigo mb-4 shadow">
                        <div class="card-header bg-light">
                            <h5 class="card-title text-indigo m-0 font-weight-bold">
                                <i class="fas fa-user-md"></i> Atención por: <?= htmlspecialchars($hc['nombre_medico']) ?>
                            </h5>
                            <div class="card-tools">
                                <span class="badge badge-success py-1 px-2"><i class="fas fa-file-signature"></i> <?= htmlspecialchars($hc['nombre_formato'] ?? 'Historia clínica') ?></span>
                                <span class="badge badge-secondary py-1 px-2">ID Atención: #<?= $hc_id ?></span>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <div class="row border-bottom pb-3">
                                <div class="col-md-6 border-right">
                                    <p class="mb-1"><strong>Motivo de Consulta:</strong></p>
                                    <blockquote class="blockquote-footer text-dark bg-light p-2 rounded"><?= htmlspecialchars($hc['motivo_consulta']) ?></blockquote>
                                    <p class="mb-1"><strong>Enfermedad Actual:</strong></p>
                                    <p class="text-muted small"><?= htmlspecialchars($hc['enfermedad_actual'] ?: 'No especificado.') ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-2"><strong><i class="fas fa-heartbeat text-danger"></i> Constantes y Signos Vitales:</strong></p>
                                    <table class="table table-sm table-bordered text-center small m-0">
                                        <thead class="bg-gray-light">
                                            <tr>
                                                <th>P. Arterial</th>
                                                <th>Frec. Cardíaca</th>
                                                <th>Temp.</th>
                                                <th>Saturación O₂</th>
                                                <th>Peso / Estatura</th>
                                                <th>IMC</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><?= htmlspecialchars($hc['presion_arterial'] ?: '-') ?></td>
                                                <td><?= htmlspecialchars($hc['frecuencia_cardiaca'] ?: '-') ?> lpm</td>
                                                <td><?= htmlspecialchars($hc['temperatura'] ?: '-') ?> °C</td>
                                                <td><?= htmlspecialchars($hc['saturacion_oxigeno'] ?: '-') ?>%</td>
                                                <td><?= $hc['peso'] ? $hc['peso'].' Kg' : '-' ?> / <?= $hc['estatura'] ? $hc['estatura'].' m' : '-' ?></td>
                                                <td><span class="badge badge-info"><?= htmlspecialchars($hc['imc'] ?: '-') ?></span></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <p class="mt-2 mb-0 small"><strong>Examen Físico:</strong> <?= htmlspecialchars($hc['examen_physical'] ?: 'Sin observaciones.') ?></p>
                                </div>
                            </div>

                            <div class="row pt-3">
                                <div class="col-12 mb-3">
                                    <div class="p-2 bg-gradient-light rounded border-left border-primary">
                                        <strong><i class="fas fa-diagnoses text-primary"></i> Diagnóstico Médico (CIE-10):</strong> 
                                        <span class="text-primary font-weight-bold">[<?= htmlspecialchars($hc['codigo_cie']) ?>]</span> 
                                        <?= htmlspecialchars($hc['descripcion_cie'] ?? 'Diagnóstico General') ?>
                                    </div>
                                </div>

                                <?php if (!empty($camposValores)): ?>
                                <div class="col-12 mb-3">
                                    <div class="card card-outline card-success mb-0">
                                        <div class="card-header py-1">
                                            <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-list-ul"></i> <?= htmlspecialchars($hc['nombre_formato'] ?? 'Campos del formato') ?></h6>
                                        </div>
                                        <div class="card-body p-2">
                                            <dl class="row mb-0 small">
                                                <?php foreach ($camposValores as $cv): ?>
                                                    <dt class="col-sm-4"><?= htmlspecialchars($cv['etiqueta']) ?></dt>
                                                    <dd class="col-sm-8"><?= nl2br(htmlspecialchars($cv['valor'])) ?></dd>
                                                <?php endforeach; ?>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($odontograma)): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card card-warning card-inner shadow-sm">
                                        <div class="card-header py-1 text-dark">
                                            <h6 class="m-0 font-weight-bold"><i class="fas fa-tooth"></i> Hallazgos en Odontograma</h6>
                                        </div>
                                        <div class="card-body p-2" style="max-height: 200px; overflow-y: auto;">
                                            <table class="table table-xs table-hover m-0 table-striped text-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Diente</th>
                                                        <th>Cara</th>
                                                        <th>Estado Clínico</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($odontograma as $odon): ?>
                                                    <tr>
                                                        <td class="font-weight-bold text-center">#<?= $odon['numero_diente'] ?></td>
                                                        <td><span class="badge badge-secondary"><?= htmlspecialchars($odon['cara_diente']) ?></span></td>
                                                        <td>
                                                            <span class="badge badge-danger"><?= htmlspecialchars($odon['estado_diente']) ?></span>
                                                            <?= !empty($odon['observaciones']) ? '<small class="d-block text-muted">'.htmlspecialchars($odon['observaciones']).'</small>' : '' ?>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($hc['tratamiento'])): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card card-warning card-inner shadow-sm">
                                        <div class="card-header py-1 text-dark">
                                            <h6 class="m-0 font-weight-bold"><i class="fas fa-notes-medical"></i> Hallazgos y Plan Odontológico</h6>
                                        </div>
                                        <div class="card-body p-2 small"><?= nl2br(htmlspecialchars($hc['tratamiento'])) ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if ($formula): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card card-success card-inner shadow-sm">
                                        <div class="card-header py-1 text-white">
                                            <h6 class="m-0 font-weight-bold"><i class="fas fa-prescription-bottle-alt"></i> Prescripción Médica</h6>
                                        </div>
                                        <div class="card-body p-2">
                                            <p class="small text-muted mb-1"><strong>Estado:</strong> <span class="badge badge-pill badge-warning"><?= $formula['estado'] ?></span> | <strong>Emisión:</strong> <?= date('d/m/Y', strtotime($formula['fecha_prescripcion'] ?? 'now')) ?></p>
                                            <ul class="list-group list-group-unbordered pl-2 text-sm">
                                                <?php foreach ($detalles_formula as $med): ?>
                                                    <li class="mb-1">
                                                        <i class="fas fa-pills text-success"></i> 
                                                        <strong><?= htmlspecialchars($med['nombre_comercial'] ?? 'Medicamento') ?></strong> 
                                                        (Cant: <?= $med['cantidad_solicitada'] ?>) 
                                                        <br><small class="text-muted border-left pl-2 ml-3">Dosificación: <?= htmlspecialchars($med['dosificacion']) ?></small>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if ($historiaVision): ?>
                                <div class="col-12 mb-3">
                                    <div class="card card-teal card-outline shadow-sm">
                                        <div class="card-header py-2">
                                            <h6 class="m-0 font-weight-bold text-teal">
                                                <i class="fas fa-eye"></i> Examen y Valoración de Optometría / Oftalmología
                                            </h6>
                                        </div>
                                        <div class="card-body p-3">
                                            <table class="table table-bordered table-sm text-center text-sm mb-3">
                                                <thead class="bg-teal text-white">
                                                    <tr>
                                                        <th rowspan="2" class="align-middle">Parámetro / Ojo</th>
                                                        <th colspan="2">Agudeza Visual (AV)</th>
                                                        <th colspan="4">Refracción Subjetiva</th>
                                                        <th rowspan="2" class="align-middle">PIO</th>
                                                    </tr>
                                                    <tr>
                                                        <th>AV Lejos</th>
                                                        <th>AV Cerca</th>
                                                        <th>Esfera</th>
                                                        <th>Cilindro</th>
                                                        <th>Eje</th>
                                                        <th>Adición</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td class="font-weight-bold bg-light">Ojo Derecho (OD)</td>
                                                        <td><?= htmlspecialchars($historiaVision['av_lejos_od'] ?: '-') ?></td>
                                                        <td><?= htmlspecialchars($historiaVision['av_cerca_od'] ?: '-') ?></td>
                                                        <td><?= htmlspecialchars($historiaVision['ref_esfera_od'] ?: '-') ?></td>
                                                        <td><?= htmlspecialchars($historiaVision['ref_cilindro_od'] ?: '-') ?></td>
                                                        <td><?= htmlspecialchars($historiaVision['ref_eje_od'] ?: '-') ?>°</td>
                                                        <td><?= htmlspecialchars($historiaVision['ref_adicion_od'] ?: '-') ?></td>
                                                        <td class="font-weight-bold text-primary"><?= htmlspecialchars($historiaVision['presion_intraocular_od'] ?: '-') ?> mmHg</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="font-weight-bold bg-light">Ojo Izquierdo (OI)</td>
                                                        <td><?= htmlspecialchars($historiaVision['av_lejos_oi'] ?: '-') ?></td>
                                                        <td><?= htmlspecialchars($historiaVision['av_cerca_oi'] ?: '-') ?></td>
                                                        <td><?= htmlspecialchars($historiaVision['ref_esfera_oi'] ?: '-') ?></td>
                                                        <td><?= htmlspecialchars($historiaVision['ref_cilindro_oi'] ?: '-') ?></td>
                                                        <td><?= htmlspecialchars($historiaVision['ref_eje_oi'] ?: '-') ?>°</td>
                                                        <td><?= htmlspecialchars($historiaVision['ref_adicion_oi'] ?: '-') ?></td>
                                                        <td class="font-weight-bold text-primary"><?= htmlspecialchars($historiaVision['presion_intraocular_oi'] ?: '-') ?> mmHg</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($adjuntos)): ?>
                                <div class="col-12 mt-2">
                                    <div class="border-top pt-2">
                                        <p class="mb-1 text-sm font-weight-bold"><i class="fas fa-paperclip text-muted"></i> Documentos o Imágenes Adjuntas:</p>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($adjuntos as $adj): ?>
                                                <a href="<?= htmlspecialchars($adj['ruta_archivo']) ?>" target="_blank" class="btn btn-xs btn-outline-secondary mr-2 mb-1 shadow-sm">
                                                    <i class="far fa-file-pdf text-danger"></i> <?= htmlspecialchars($adj['nombre_personalizado'] ?: 'Ver Adjunto') ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php
                }

            } catch (Exception $e) {
                echo mensajeErrorInterno($e) . '</div>';
            }
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no reconocida.']);
        break;
}