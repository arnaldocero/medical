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
