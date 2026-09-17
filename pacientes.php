<?php 
session_start();
if (!isset($_SESSION['id'])) { header("Location: login.php"); exit(); }

include 'layout/header.php'; 
include 'layout/nav.php'; 
include 'layout/sidebar.php'; 
require_once 'config/db.php';

$clinica_id_sesion = $_SESSION['clinica'] ?? null;

try {
    // 1. CONSULTA DE ROLES ASIGNABLES A PACIENTES
    $stmtRolesPacientes = $pdo->query("
        SELECT id, nombre_rol 
        FROM roles_clinicas 
        WHERE nombre_rol LIKE '%Paciente%' OR id = 5 
        ORDER BY id ASC
    ");
    $rolesPacientes = $stmtRolesPacientes->fetchAll(PDO::FETCH_ASSOC);

    // 2. CONSULTA DE PACIENTES CON ROL Y EPS ASOCIADA
    $stmt = $pdo->prepare("
        SELECT 
            p.id, p.usuario_id, p.fecha_nacimiento, p.genero, p.tipo_sangre, 
            p.documento_identidad, p.telefono_contacto, p.direccion, p.eps_id, 
            p.contacto_emergencia_nombre, p.contacto_emergencia_telefono,
            u.nombre, u.email, u.estado, u.usuario, u.rol_id,
            e.nombre AS nombre_eps,
            r.nombre_rol
        FROM pacientes_datos p
        INNER JOIN usuarios_clinicas u ON p.usuario_id = u.id 
        LEFT JOIN roles_clinicas r ON u.rol_id = r.id
        LEFT JOIN eps e ON p.eps_id = e.id
        WHERE u.clinica_id = ?
        ORDER BY p.id DESC
    ");
    $stmt->execute([$clinica_id_sesion]);
    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. CONSULTA DE EPS ACTIVAS PARA EL MODAL
    if (!$clinica_id_sesion) {
        $listaEps = [];
    } else {
        $stmtEps = $pdo->prepare("SELECT id, nombre FROM eps WHERE estado = 1 AND clinica_id = ? ORDER BY nombre ASC");
        $stmtEps->execute([$clinica_id_sesion]);
        $listaEps = $stmtEps->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("Error al cargar datos: " . $e->getMessage());
} 
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1>Gestión de Pacientes</h1></div>
                <div class="col-sm-6 text-right">
                    <button class="btn btn-success" id="btnNuevoPaciente">
                        <i class="fas fa-plus"></i> Nuevo Paciente
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="card">
            <div class="card-body">
                <table id="tablaPacientes" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Nombre Paciente</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>EPS</th>
                            <th>Tipo / Rol</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pacientes as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['documento_identidad']) ?></td>
                            <td><?= htmlspecialchars($p['nombre']) ?></td>
                            <td><?= htmlspecialchars($p['email']) ?></td>
                            <td><?= htmlspecialchars($p['telefono_contacto']) ?></td>
                            <td><?= htmlspecialchars($p['nombre_eps'] ?? 'Sin EPS') ?></td>
                            <td>
                                <span class="badge badge-info"><?= htmlspecialchars($p['nombre_rol'] ?? 'Paciente') ?></span>
                            </td>
                            <td>
                                <?= $p['estado'] == 1 
                                    ? '<span class="badge badge-success">Activo</span>' 
                                    : '<span class="badge badge-danger">Inactivo</span>' 
                                ?>
                            </td>
                            <td>
                                <button class="btn btn-info btn-sm btnHistorial shadow-sm" data-id="<?= $p['id'] ?>" data-nombre="<?= htmlspecialchars($p['nombre']) ?>" title="Ver Historial Clínico">
                                    <i class="fas fa-notes-medical"></i>
                                </button>

                                <a href="expediente_digital.php?paciente_id=<?= $p['usuario_id'] ?>" class="btn btn-sm bg-indigo shadow-sm" title="Ver Expediente Digital / Anexos">
                                    <i class="fas fa-folder-open"></i>
                                </a>

                                <button class="btn btn-warning btn-sm btnEditar shadow-sm" 
                                        data-id="<?= $p['id'] ?>" 
                                        data-uid="<?= $p['usuario_id'] ?>"
                                        data-nombre="<?= htmlspecialchars($p['nombre']) ?>" 
                                        data-email="<?= htmlspecialchars($p['email']) ?>" 
                                        data-user="<?= htmlspecialchars($p['usuario'] ?? '') ?>"
                                        data-rol="<?= $p['rol_id'] ?>"
                                        data-estado="<?= $p['estado'] ?>"
                                        data-doc="<?= htmlspecialchars($p['documento_identidad']) ?>"
                                        data-fnac="<?= htmlspecialchars($p['fecha_nacimiento']) ?>"
                                        data-genero="<?= htmlspecialchars($p['genero']) ?>"
                                        data-sangre="<?= htmlspecialchars($p['tipo_sangre']) ?>"
                                        data-tel="<?= htmlspecialchars($p['telefono_contacto']) ?>"
                                        data-dir="<?= htmlspecialchars($p['direccion']) ?>"
                                        data-eps="<?= $p['eps_id'] ?>"
                                        data-emernombre="<?= htmlspecialchars($p['contacto_emergencia_nombre'] ?? '') ?>"
                                        data-emertel="<?= htmlspecialchars($p['contacto_emergencia_telefono'] ?? '') ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm btnEliminar shadow-sm" 
                                        data-id="<?= $p['id'] ?>" 
                                        data-uid="<?= $p['usuario_id'] ?>"
                                        data-nombre="<?= htmlspecialchars($p['nombre']) ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<div class="modal fade" id="modalPaciente">
    <div class="modal-dialog modal-lg"> 
        <div class="modal-content">
            <form id="formPaciente">
                <div class="modal-header bg-primary text-white">
                    <h4 class="modal-title" id="modalTitle">Registrar Nuevo Paciente</h4>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="idPacienteEdit" name="idPaciente" value="">
                    <input type="hidden" id="idUsuarioEdit" name="idUsuario" value="">

                    <div class="row">
                        <div class="col-12"><h5>Información de Cuenta de Usuario</h5><hr></div>
                        <div class="col-md-6 mb-2">
                            <label>Nombre Completo *</label>
                            <input type="text" name="nombre" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label>Email *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label>Nombre de Usuario (Login) *</label>
                            <input type="text" name="usuario" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label id="labelPassword">Contraseña *</label>
                            <input type="password" name="password" id="inputPassword" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label>Tipo de Paciente (Rol) *</label>
                            <select name="rol_id" id="rol_id_paciente" class="form-control" required>
                                <?php foreach($rolesPacientes as $rp): ?>
                                    <option value="<?= $rp['id'] ?>"><?= htmlspecialchars($rp['nombre_rol']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label>Estado del Registro</label>
                            <select name="estado" id="estado_paciente" class="form-control" required>
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>

                        <div class="col-12 mt-3"><h5>Ficha de Datos del Paciente</h5><hr></div>
                        <div class="col-md-4 mb-2">
                            <label>Documento de Identidad *</label>
                            <input type="text" name="documento_identidad" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label>Fecha de Nacimiento *</label>
                            <input type="date" name="fecha_nacimiento" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label>Género *</label>
                            <select name="genero" class="form-control" required>
                                <option value="">Seleccione...</option>
                                <option value="Masculino">Masculino</option>
                                <option value="Femenino">Femenino</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label>Tipo de Sangre</label>
                            <select name="tipo_sangre" class="form-control">
                                <option value="">Seleccione...</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label>Teléfono Contacto *</label>
                            <input type="text" name="telefono_contacto" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label>EPS (Entidad de Salud) *</label>
                            <select name="eps_id" class="form-control" required>
                                <option value="">Seleccione una EPS...</option>
                                <?php foreach($listaEps as $eps): ?>
                                    <option value="<?= $eps['id'] ?>"><?= htmlspecialchars($eps['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12 mb-2">
                            <label>Dirección de Residencia *</label>
                            <input type="text" name="direccion" class="form-control" required>
                        </div>

                        <div class="col-12 mt-3"><h5>Contacto de Emergencia</h5><hr></div>
                        <div class="col-md-6 mb-2">
                            <label>Nombre del Contacto</label>
                            <input type="text" name="contacto_emergencia_nombre" class="form-control">
                        </div>
                        <div class="col-md-6 mb-2">
                            <label>Teléfono del Contacto</label>
                            <input type="text" name="contacto_emergencia_telefono" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardar">Guardar Registro</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalHistorialClinico">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-indigo text-white">
                <h4 class="modal-title"><i class="fas fa-history"></i> Historial Médico: <span id="lblNombrePacienteHistorial"></span></h4>
                <div class="modal-tools">
                    <button type="button" class="btn btn-success btn-sm mr-2 shadow-sm" id="btnImprimirHistorial">
                        <i class="fas fa-print"></i> Imprimir / PDF
                    </button>
                    <button type="button" class="close text-white" data-dismiss="modal" style="outline:none;">&times;</button>
                </div>
            </div>
            <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                <div id="contenedorHistorial"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar Historial</button>
            </div>
        </div>
    </div>
</div>

<?php include 'layout/footer.php'; ?>