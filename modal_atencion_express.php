
<style>
    .select2-container--open,
    .select2-dropdown {
        z-index: 9999999 !important;
    }

    .select2-container {
        width: 100% !important;
    }

    .select2-container--bootstrap4 .select2-selection--single {
        height: calc(2.25rem + 2px) !important;
    }
</style>
<!-- Botón gatillo -->
<button type="button" class="btn btn-warning shadow-sm" data-toggle="modal" data-target="#modalAtencionExpress">
    <i class="fas fa-bolt mr-1"></i> Atención Inmediata (Sin Cita)
</button>

<!-- Modal -->
<div class="modal fade" id="modalAtencionExpress" tabindex="-1" role="dialog" aria-labelledby="modalExpressLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title font-weight-bold" id="modalExpressLabel">
                    <i class="fas fa-user-clock mr-2"></i> Nueva Atención Sin Cita Previa
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <!-- Cambiado a modal-body -->
            <div class="modal-body p-4">
                <div class="form-group">
                    <label>Buscar Paciente (Documento o Nombre):</label>
                    <select id="select_paciente_express" class="form-control" style="width: 100%;"></select>
                </div>
                <div id="info_paciente_seleccionado" class="alert alert-secondary d-none mt-3">
                    <strong>Paciente:</strong> <span id="lbl_nombre_paciente"></span><br>
                    <strong>Documento:</strong> <span id="lbl_doc_paciente"></span>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" id="btnIniciarAtencionExpress" class="btn btn-success shadow-sm" disabled>
                    <i class="fas fa-stethoscope"></i> Abrir Consulta Ocular
                </button>
            </div>
        </div>
    </div>
</div>