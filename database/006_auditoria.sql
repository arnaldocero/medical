-- =====================================================================
--  Migración 006: Auditoría de cambios
--  Motor: MariaDB 10.4+ · Se puede ejecutar más de una vez.
--  En phpMyAdmin se ejecuta tal cual (los triggers son de una sola sentencia, no requieren DELIMITER).
--
--  Registra quién, cuándo, desde qué IP/sede y qué cambió (antes / después) en las acciones
--  sensibles: historias clínicas, fórmulas, despachos, facturas, usuarios, médicos, pacientes,
--  roles y permisos, sedes, citas, resoluciones DIAN, formatos, medicamentos y adjuntos.
--  Los registros NO se pueden modificar ni borrar (triggers).
-- =====================================================================

CREATE TABLE IF NOT EXISTS auditoria (
    id              BIGINT NOT NULL AUTO_INCREMENT,
    clinica_id      INT(11) NULL,
    centro_id       INT(11) NULL,
    usuario_id      INT(11) NULL,
    usuario_nombre  VARCHAR(150) NULL,
    modulo          VARCHAR(40) NOT NULL,
    accion          VARCHAR(40) NOT NULL,
    registro_id     VARCHAR(40) NULL,
    descripcion     VARCHAR(500) NOT NULL,
    datos_antes     LONGTEXT NULL,
    datos_despues   LONGTEXT NULL,
    ip              VARCHAR(45) NULL,
    fecha           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_clinica_fecha (clinica_id, fecha),
    KEY idx_modulo (modulo, accion),
    KEY idx_usuario (usuario_id),
    KEY idx_registro (modulo, registro_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registros inalterables: se rechaza cualquier UPDATE o DELETE sobre la auditoría
CREATE TRIGGER IF NOT EXISTS trg_auditoria_no_update BEFORE UPDATE ON auditoria
    FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La auditoria no se puede modificar';

CREATE TRIGGER IF NOT EXISTS trg_auditoria_no_delete BEFORE DELETE ON auditoria
    FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La auditoria no se puede borrar';

-- Permiso para consultar la auditoría (el Admin ya tiene acceso a todo)
INSERT INTO permisos (slug, descripcion, categoria)
SELECT 'auditoria.ver', 'Consultar la auditoría de cambios del sistema', 'Personal'
WHERE NOT EXISTS (SELECT 1 FROM permisos WHERE slug = 'auditoria.ver');
