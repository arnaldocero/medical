<?php 
session_start();
if (!isset($_SESSION['id'])) { header("Location: login.php"); exit(); }

require_once 'config/db.php';
require_once 'config/security.php'; 

// 1. Validar permisos
requerirPermiso('usuarios.ver');

include 'layout/header.php'; 
include 'layout/nav.php'; 
include 'layout/sidebar.php'; 

// CONSULTA: Traemos los datos de la base de datos
$stmt = $pdo->prepare("
    SELECT u.id, u.nombre, u.usuario, u.email, u.estado, u.fecha_creacion, r.nombre_rol, u.rol_id,
           p.tipo_documento, p.documento_identidad, p.telefono, p.direccion, 
           p.cargo, p.fecha_nacimiento, p.genero
    FROM usuarios_clinicas u
    LEFT JOIN roles_clinicas r ON u.rol_id = r.id
    LEFT JOIN perfiles_usuarios p ON u.id = p.usuario_id
    WHERE u.clinica_id = ? AND u.rol_id NOT IN (2, 4, 5)
");
$stmt->execute([$_SESSION['clinica']]);
$usuarios = $stmt->fetchAll();
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1>Gestión de Personal Interno</h1></div>
                <div class="col-sm-6 text-right">
                    <?php if (tienePermiso('usuarios.crear')): ?>
                        <button class="btn btn-success" data-toggle="modal" data-target="#modalUsuario">
                            <i class="fas fa-plus"></i> Nuevo Usuario
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Tabla de datos -->
    <section class="content">
        <div class="card">
            <div class="card-body">
                <table id="tablaUsuarios" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Usuario</th>
                            <th>Cargo</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($usuarios as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['nombre']) ?></td>
                            <td><?= htmlspecialchars($u['usuario']) ?></td>
                            <td><?= !empty($u['cargo']) ? htmlspecialchars($u['cargo']) : '<span class="text-muted">No asignado</span>' ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= htmlspecialchars($u['nombre_rol']) ?></td>
                            <td>
                                <!-- Botón Asignar Sede -->
                                <?php if (tienePermiso('usuarios.asignar_sede')): ?>
                                    <button class="btn btn-info btn-sm btnAsignarSede" data-id="<?= $u['id'] ?>" data-nombre="<?= htmlspecialchars($u['nombre']) ?>"><i class="fas fa-hospital"></i></button>
                                <?php endif; ?>
                                
                                <!-- Botón Editar -->
                                <?php if (tienePermiso('usuarios.editar')): ?>
                                    <button class="btn btn-warning btn-sm btnEditarUsuario" 
                                            data-id="<?= $u['id'] ?>" 
                                            data-nombre="<?= htmlspecialchars($u['nombre']) ?>" 
                                            data-usuario="<?= htmlspecialchars($u['usuario']) ?>" 
                                            data-email="<?= htmlspecialchars($u['email']) ?>" 
                                            data-rol="<?= $u['rol_id'] ?>"
                                            data-cargo="<?= htmlspecialchars($u['cargo'] ?? '') ?>"
                                            data-fnac="<?= htmlspecialchars($u['fecha_nacimiento'] ?? '') ?>"
                                            data-genero="<?= htmlspecialchars($u['genero'] ?? '') ?>"
                                            data-tipodoc="<?= htmlspecialchars($u['tipo_documento'] ?? 'CC') ?>"
                                            data-doc="<?= htmlspecialchars($u['documento_identidad'] ?? '') ?>"
                                            data-tel="<?= htmlspecialchars($u['telefono'] ?? '') ?>"
                                            data-dir="<?= htmlspecialchars($u['direccion'] ?? '') ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                <?php endif; ?>

                                <!-- Botón Eliminar -->
                                <?php if (tienePermiso('usuarios.eliminar')): ?>
                                    <button class="btn btn-danger btn-sm btnEliminar" data-id="<?= $u['id'] ?>" data-nombre="<?= htmlspecialchars($u['nombre']) ?>"><i class="fas fa-trash"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<!-- ==========================================
     MODAL: REGISTRAR / EDITAR USUARIO
     ========================================== -->
<div class="modal fade" id="modalUsuario" tabindex="-1" role="dialog" aria-labelledby="modalUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalUsuarioLabel">Registrar Nuevo Personal/Usuario</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formNuevoUsuario">
                <div class="modal-body">
                    <div class="row">
                        <!-- Datos de cuenta -->
                        <div class="col-md-6 border-right">
                            <h5 class="text-primary mb-3"><i class="fas fa-user-lock"></i> Datos de Acceso</h5>
                            <div class="form-group">
                                <label>Nombre Completo *</label>
                                <input type="text" class="form-control" name="nombre" required>
                            </div>
                            <div class="form-group">
                                <label>Nombre de Usuario *</label>
                                <input type="text" class="form-control" name="usuario" required>
                            </div>
                            <div class="form-group">
                                <label>Correo Electrónico *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="form-group">
                                <label>Contraseña *</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                            <div class="form-group">
                                <label>Rol asignado *</label>
                                <select class="form-control" name="rol_id" required>
                                    <option value="">Seleccione un rol...</option>
                                    <?php 
                                    $roles = $pdo->query("SELECT id, nombre_rol FROM roles_clinicas WHERE id NOT IN (2, 4)")->fetchAll();
                                    foreach ($roles as $r): 
                                    ?>
                                        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre_rol']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Datos de perfil -->
                        <div class="col-md-6">
                            <h5 class="text-primary mb-3"><i class="fas fa-id-card"></i> Datos del Perfil Profesional</h5>
                            <div class="row">
                                <div class="col-sm-5">
                                    <div class="form-group">
                                        <label>Tipo Doc. *</label>
                                        <select class="form-control" name="tipo_doc" required>
                                            <option value="CC">C.C.</option>
                                            <option value="CE">C.E.</option>
                                            <option value="PP">Pasaporte</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-7">
                                    <div class="form-group">
                                        <label>Documento *</label>
                                        <input type="text" class="form-control" name="documento" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Cargo / Especialidad</label>
                                <input type="text" class="form-control" name="cargo">
                            </div>
                            <div class="form-group">
                                <label>Teléfono</label>
                                <input type="text" class="form-control" name="telefono">
                            </div>
                            <div class="form-group">
                                <label>Dirección de Residencia</label>
                                <input type="text" class="form-control" name="direccion">
                            </div>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>F. Nacimiento</label>
                                        <input type="date" class="form-control" name="fecha_nacimiento">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Género</label>
                                        <select class="form-control" name="genero">
                                            <option value="">Seleccione...</option>
                                            <option value="Masculino">Masculino</option>
                                            <option value="Femenino">Femenino</option>
                                            <option value="Otro">Otro</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Registro</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================
     MODAL: ASIGNAR SEDE (CENTRO MÉDICO)
     ========================================== -->
<div class="modal fade" id="modalAsignarSede" tabindex="-1" role="dialog" aria-labelledby="modalAsignarSedeLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="modalAsignarSedeLabel"><i class="fas fa-hospital"></i> Asignar Sedes / Centros</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formAsignarSede">
                <div class="modal-body">
                    <input type="hidden" id="idUsuarioAsignar" name="idUsuarioAsignar">
                    <p>Asignando centros médicos autorizados para:</p>
                    <h5 class="text-primary mb-3" id="nombreUsuarioAsignar">---</h5>
                    <div class="form-group">
                        <label>Selecciona las sedes permitidas:</label>
                        <div id="contenedorCentros" class="p-3 border rounded bg-light" style="max-height: 250px; overflow-y: auto;"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-info">Guardar Asignación</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'layout/footer.php'; ?>