<?php
session_start();
// Seguridad: sesión activa (o "Recordarme") + permiso de la pantalla
require_once 'config/auth.php';
protegerPagina('formatos.administrar');
require_once 'config/formatos.php';

include 'layout/header.php';
include 'layout/nav.php';
include 'layout/sidebar.php';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-7">
                    <h1><i class="fas fa-file-signature text-success"></i> Formatos de Historia Clínica</h1>
                    <small class="text-muted">Cree formatos (general, odontología, examen de la vista...) y asígnelos a los médicos.</small>
                </div>
                <div class="col-sm-5 text-right">
                    <button class="btn btn-success shadow-sm" id="btnNuevoFormato">
                        <i class="fas fa-plus"></i> Nuevo Formato
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-outline card-success">
                <div class="card-body">
                    <table id="tablaFormatos" class="table table-bordered table-striped table-hover w-100">
                        <thead>
                            <tr>
                                <th>Formato</th>
                                <th>Secciones</th>
                                <th class="text-center">Campos propios</th>
                                <th class="text-center">Médicos</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center" style="width: 150px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- ==========================================
     MODAL: CREAR / EDITAR FORMATO
     ========================================== -->
<div class="modal fade" id="modalFormato" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <form id="formFormato">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="tituloModalFormato"><i class="fas fa-file-signature"></i> Nuevo Formato</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="formato_id">

                    <div class="row">
                        <div class="col-md-5 form-group">
                            <label>Nombre del formato <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" id="formato_nombre" class="form-control" maxlength="150" required placeholder="Ej: Historia Clínica de Odontología">
                        </div>
                        <div class="col-md-5 form-group">
                            <label>Descripción</label>
                            <input type="text" name="descripcion" id="formato_descripcion" class="form-control" maxlength="255" placeholder="Para qué se usa este formato">
                        </div>
                        <div class="col-md-2 form-group d-flex align-items-end">
                            <div class="custom-control custom-switch mb-2" title="Se usa para los médicos que no tienen formatos asignados">
                                <input type="checkbox" class="custom-control-input" name="es_predeterminado" id="formato_predeterminado" value="1">
                                <label class="custom-control-label" for="formato_predeterminado">Predeterminado</label>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-success font-weight-bold mt-2"><i class="fas fa-layer-group"></i> Secciones del formato</h6>
                    <p class="text-muted small mb-2">
                        <i class="fas fa-info-circle"></i> "Motivo de consulta" y "Diagnóstico CIE-10" se incluyen siempre (son obligatorios para el RIPS).
                    </p>
                    <div class="row mb-3">
                        <?php foreach (SECCIONES_FORMATO as $clave => [$nombre, $icono]): ?>
                            <div class="col-md-4 col-sm-6">
                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox" class="custom-control-input chk-seccion" name="secciones[]" id="sec_<?= $clave ?>" value="<?= $clave ?>">
                                    <label class="custom-control-label" for="sec_<?= $clave ?>"><i class="fas <?= $icono ?> text-muted"></i> <?= htmlspecialchars($nombre) ?></label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <h6 class="text-success font-weight-bold"><i class="fas fa-list-ul"></i> Campos personalizados</h6>
                    <p class="text-muted small mb-2">Preguntas propias del formato. Aparecen en una pestaña con el nombre del formato. Use las flechas para ordenarlas.</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-2">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 32%;">Etiqueta / pregunta</th>
                                    <th style="width: 18%;">Tipo</th>
                                    <th>Opciones <small class="text-muted">(solo listas, una por línea)</small></th>
                                    <th class="text-center" style="width: 90px;">Obligatorio</th>
                                    <th class="text-center" style="width: 110px;"></th>
                                </tr>
                            </thead>
                            <tbody id="cuerpoCampos"></tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-outline-success btn-sm" id="btnAgregarCampo">
                        <i class="fas fa-plus"></i> Agregar campo
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btnGuardarFormato"><i class="fas fa-save"></i> Guardar Formato</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Plantilla de una fila de campo personalizado -->
<template id="plantillaCampo">
    <tr class="fila-campo">
        <td>
            <input type="hidden" class="campo-id">
            <input type="text" class="form-control form-control-sm campo-etiqueta" maxlength="150" placeholder="Ej: Antecedentes familiares">
        </td>
        <td>
            <select class="form-control form-control-sm campo-tipo">
                <?php foreach (TIPOS_CAMPO_FORMATO as $clave => $nombre): ?>
                    <option value="<?= $clave ?>"><?= htmlspecialchars($nombre) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><textarea class="form-control form-control-sm campo-opciones" rows="1" placeholder="Opción 1&#10;Opción 2" disabled></textarea></td>
        <td class="text-center align-middle"><input type="checkbox" class="campo-obligatorio"></td>
        <td class="text-center align-middle text-nowrap">
            <button type="button" class="btn btn-xs btn-light btnSubirCampo" title="Subir"><i class="fas fa-arrow-up"></i></button>
            <button type="button" class="btn btn-xs btn-light btnBajarCampo" title="Bajar"><i class="fas fa-arrow-down"></i></button>
            <button type="button" class="btn btn-xs btn-danger btnQuitarCampo" title="Quitar"><i class="fas fa-times"></i></button>
        </td>
    </tr>
</template>

<!-- ==========================================
     MODAL: ASIGNAR MÉDICOS
     ========================================== -->
<div class="modal fade" id="modalMedicosFormato" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="formMedicosFormato">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-user-md"></i> Médicos que usan el formato</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="medicos_formato_id">
                    <h5 class="text-info mb-3" id="medicos_formato_nombre">---</h5>
                    <div id="contenedorMedicosFormato" class="p-3 border rounded bg-light" style="max-height: 320px; overflow-y: auto;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-info"><i class="fas fa-save"></i> Guardar Asignación</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'layout/footer.php'; ?>
