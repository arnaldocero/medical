<?php 
session_start();
if (!isset($_SESSION['id'])) { 
    header("Location: login.php"); 
    exit(); 
}

require_once 'config/db.php';
require_once 'config/security.php';

// Verificación estricta: Solo Administrador del sistema o permiso explícito
if ((!isset($_SESSION['rol']) || $_SESSION['rol'] != 1) && !tienePermiso('roles.administrar')) {
    header("Location: index.php");
    exit();
}

include 'layout/header.php'; 
include 'layout/nav.php'; 
include 'layout/sidebar.php'; 

// Consultar el catálogo de permisos agrupados por categoría
try {
    $stmtP = $pdo->query("SELECT id, slug, descripcion, categoria FROM permisos ORDER BY categoria ASC, id ASC");
    $permisosAgrupados = [];
    while ($row = $stmtP->fetch(PDO::FETCH_ASSOC)) {
        $permisosAgrupados[$row['categoria']][] = $row;
    }
} catch (PDOException $e) {
    $permisosAgrupados = [];
}
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-user-tag text-primary"></i> Gestión de Roles y Permisos</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <button class="btn btn-success" id="btnNuevoRol">
                        <i class="fas fa-plus"></i> Nuevo Rol
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="card card-outline card-primary shadow">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaRoles" class="table table-bordered table-striped w-100">
                        <thead>
                            <tr>
                                <th style="width: 5%;">ID</th>
                                <th style="width: 20%;">Nombre del Rol</th>
                                <th style="width: 35%;">Descripción</th>
                                <th style="width: 15%;" class="text-center">Usuarios Asignados</th>
                                <th style="width: 15%;" class="text-center">Total Permisos</th>
                                <th style="width: 10%;" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- MODAL PARA CREAR Y EDITAR ROL CON MATRIZ DE PERMISOS -->
<div class="modal fade" id="modalRol" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document"> 
        <div class="modal-content">
            <form id="formRol">
                <input type="hidden" name="idRol" id="idRol" value="">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalRolTitle"><i class="fas fa-shield-alt"></i> Configuración de Rol</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Nombre del Rol *</label>
                            <input type="text" name="nombre_rol" id="nombre_rol" class="form-control" placeholder="Ej: Auditor Médico, Facturador..." required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Descripción</label>
                            <input type="text" name="descripcion" id="descripcion" class="form-control" placeholder="Alcance o propósito del rol">
                        </div>
                    </div>

                    <hr class="mt-2 mb-3">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="text-primary font-weight-bold m-0"><i class="fas fa-key"></i> Asignación de Permisos del Sistema</h5>
                        <div>
                            <button type="button" class="btn btn-xs btn-outline-primary" id="btnMarcarTodos">Marcar Todos</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary" id="btnDesmarcarTodos">Desmarcar Todos</button>
                        </div>
                    </div>

                    <div class="row">
                        <?php foreach ($permisosAgrupados as $categoria => $permisos): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card card-outline card-secondary h-100 shadow-sm">
                                <div class="card-header py-1 px-3 bg-light d-flex justify-content-between align-items-center">
                                    <strong class="text-dark small text-uppercase"><?= htmlspecialchars($categoria) ?></strong>
                                    <button type="button" class="btn btn-link btn-xs text-primary btn-check-grupo" data-target="cat_<?= md5($categoria) ?>">Alternar</button>
                                </div>
                                <div class="card-body p-2">
                                    <?php foreach ($permisos as $p): ?>
                                    <div class="custom-control custom-checkbox mb-1">
                                        <input type="checkbox" class="custom-control-input chk-permiso cat_<?= md5($categoria) ?>" id="perm_<?= $p['id'] ?>" name="permisos[]" value="<?= $p['id'] ?>">
                                        <label class="custom-control-label font-weight-normal text-sm" for="perm_<?= $p['id'] ?>">
                                            <strong><?= htmlspecialchars($p['slug']) ?></strong>: 
                                            <span class="text-muted"><?= htmlspecialchars($p['descripcion']) ?></span>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarRol">Guardar Rol</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'layout/footer.php'; ?>
