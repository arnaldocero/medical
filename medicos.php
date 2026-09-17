<?php 
session_start();
if (!isset($_SESSION['id'])) { header("Location: login.php"); exit(); }

include 'layout/header.php'; 
include 'layout/nav.php'; 
include 'layout/sidebar.php'; 
require_once 'config/db.php'; 

try {
    $stmtEsp = $pdo->query("SELECT id, nombre FROM especialidades WHERE estado = 1 ORDER BY nombre ASC");
    $especialidades = $stmtEsp->fetchAll(PDO::FETCH_ASSOC);
    // Consulta de roles médicos disponibles (Roles creados para personal facultativo)
    $stmtRolesMedicos = $pdo->query("
    SELECT id, nombre_rol 
    FROM roles_clinicas 
    WHERE nombre_rol LIKE '%Medico%' OR id = 2 
    ORDER BY nombre_rol ASC
    ");
    $rolesMedicos = $stmtRolesMedicos->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $especialidades = [];
}
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-user-md"></i> Gestión de Personal Médico</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <button class="btn btn-success" data-toggle="modal" data-target="#modalMedico" id="btnNuevoMedico">
                        <i class="fas fa-plus"></i> Registrar Médico
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaMedicos" class="table table-bordered table-striped w-100">
                        <thead>
                            <tr>
                                <th>Nombre Médico</th>
                                <th>Correo Electrónico</th>
                                <th>Usuario</th>
                                <th>Licencia Médica</th>
                                <th>Especialidad</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="modal fade" id="modalMedico" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg"> 
        <div class="modal-content">
            <form id="formMedico">
                <input type="hidden" name="action" id="action" value="registrar">
                <input type="hidden" name="usuario_id" id="usuario_id" value="">

                <div class="modal-header bg-primary text-white">
                    <h4 class="modal-title" id="modalTitle"><i class="fas fa-user-md"></i> Registrar Médico</h4>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                
                <div class="modal-body">
                    <h5 class="text-primary font-weight-bold">Datos de Credenciales y Acceso</h5>
                    <hr class="mt-1">
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label>Nombre Completo *</label>
                            <input type="text" name="nombre" id="nombre" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label>Correo Electrónico *</label>
                            <input type="email" name="email" id="email" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label>Usuario de Sistema *</label>
                            <input type="text" name="usuario" id="usuario" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label>Contraseña *</label>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Escriba la contraseña" required>
                            <small id="passHelp" class="form-text text-muted d-none">Deje en blanco si no desea cambiarla.</small>
                        </div>
                        <div class="col-md-4 mb-2">
                        <label>Rol Interno *</label>
                        <select name="rol_id" id="rol_id" class="form-control" required>
                            <option value="">Seleccione Rol...</option>
                            <?php foreach($rolesMedicos as $rm): ?>
                                <option value="<?= $rm['id'] ?>"><?= htmlspecialchars($rm['nombre_rol']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    </div>

                    <div class="col-12 mt-3 px-0">
                        <h5 class="text-primary font-weight-bold">Información Profesional e Institucional</h5>
                        <hr class="mt-1">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <label>N° Licencia Médica / Registro *</label>
                            <input type="text" name="licencia_medica" id="licencia_medica" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label>Especialidad Principal *</label>
                            <input type="hidden" name="clinica_id" value="<?= $_SESSION['clinica'] ?? '' ?>">
                            <select name="especialidad_id" id="especialidad_id" class="form-control" required>
                                <option value="">Seleccione una especialidad...</option>
                                <?php foreach($especialidades as $esp): ?>
                                    <option value="<?= $esp['id'] ?>"><?= htmlspecialchars($esp['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label>Años de Experiencia</label>
                            <input type="number" name="anos_experiencia" id="anos_experiencia" class="form-control" min="0" value="0">
                        </div>
                        <div class="col-md-12 mb-2">
                            <label>Universidad de Egreso</label>
                            <input type="text" name="universidad_egreso" id="universidad_egreso" class="form-control">
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardar">Guardar Profesional</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'layout/footer.php'; ?>