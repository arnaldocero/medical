-- =====================================================================
--  Migración 001: Separación de la información por sede (centro de atención)
--  Motor: MariaDB 10.11 · Se puede ejecutar más de una vez (idempotente).
--  Las columnas nuevas admiten NULL: el código anterior sigue funcionando.
--  Ejecutar en phpMyAdmin (pestaña SQL) sobre la base medical6_HMS.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. Asignación usuario <-> sede (personal, médicos y pacientes)
-- ---------------------------------------------------------------------
-- Evitar asignaciones duplicadas
ALTER TABLE asignacion_centros
    ADD UNIQUE INDEX IF NOT EXISTS uq_usuario_centro (usuario_id, centro_id);

-- Todos los usuarios existentes quedan asignados a la sede más antigua de su
-- clínica (donde está hoy toda la información), para que nadie pierda acceso.
INSERT IGNORE INTO asignacion_centros (clinica_id, usuario_id, centro_id)
SELECT u.clinica_id, u.id,
       (SELECT MIN(c.id) FROM centros_medicos c WHERE c.clinica_id = u.clinica_id)
FROM usuarios_clinicas u
WHERE (SELECT MIN(c.id) FROM centros_medicos c WHERE c.clinica_id = u.clinica_id) IS NOT NULL;

-- ---------------------------------------------------------------------
-- 2. Columna centro_id en las tablas que se separan por sede
-- ---------------------------------------------------------------------
ALTER TABLE dian_resoluciones    ADD COLUMN IF NOT EXISTS centro_id INT(11) NULL AFTER clinica_id;
ALTER TABLE facturas             ADD COLUMN IF NOT EXISTS centro_id INT(11) NULL AFTER clinica_id;
ALTER TABLE formulas_medicas     ADD COLUMN IF NOT EXISTS centro_id INT(11) NULL AFTER clinica_id;
ALTER TABLE medicamentos         ADD COLUMN IF NOT EXISTS centro_id INT(11) NULL AFTER clinica_id;
ALTER TABLE articulos_inventario ADD COLUMN IF NOT EXISTS centro_id INT(11) NULL AFTER clinica_id;
ALTER TABLE inventario_movimientos ADD COLUMN IF NOT EXISTS centro_id INT(11) NULL AFTER clinica_id;

-- Índices para los filtros por sede
ALTER TABLE dian_resoluciones      ADD INDEX IF NOT EXISTS idx_centro (centro_id);
ALTER TABLE facturas               ADD INDEX IF NOT EXISTS idx_centro (centro_id);
ALTER TABLE formulas_medicas       ADD INDEX IF NOT EXISTS idx_centro (centro_id);
ALTER TABLE medicamentos           ADD INDEX IF NOT EXISTS idx_centro (centro_id);
ALTER TABLE articulos_inventario   ADD INDEX IF NOT EXISTS idx_centro (centro_id);
ALTER TABLE inventario_movimientos ADD INDEX IF NOT EXISTS idx_centro (centro_id);

-- ---------------------------------------------------------------------
-- 3. Asignar la sede más antigua de la clínica a los registros existentes
-- ---------------------------------------------------------------------
UPDATE dian_resoluciones t
SET t.centro_id = (SELECT MIN(c.id) FROM centros_medicos c WHERE c.clinica_id = t.clinica_id)
WHERE t.centro_id IS NULL;

UPDATE facturas t
SET t.centro_id = (SELECT MIN(c.id) FROM centros_medicos c WHERE c.clinica_id = t.clinica_id)
WHERE t.centro_id IS NULL;

UPDATE formulas_medicas t
SET t.centro_id = (SELECT MIN(c.id) FROM centros_medicos c WHERE c.clinica_id = t.clinica_id)
WHERE t.centro_id IS NULL;

UPDATE medicamentos t
SET t.centro_id = (SELECT MIN(c.id) FROM centros_medicos c WHERE c.clinica_id = t.clinica_id)
WHERE t.centro_id IS NULL;

UPDATE articulos_inventario t
SET t.centro_id = (SELECT MIN(c.id) FROM centros_medicos c WHERE c.clinica_id = t.clinica_id)
WHERE t.centro_id IS NULL;

UPDATE inventario_movimientos t
SET t.centro_id = (SELECT MIN(c.id) FROM centros_medicos c WHERE c.clinica_id = t.clinica_id)
WHERE t.centro_id IS NULL;


-- ---------------------------------------------------------------------
-- 4. Llaves foráneas
-- ---------------------------------------------------------------------
ALTER TABLE dian_resoluciones      ADD FOREIGN KEY IF NOT EXISTS fk_dian_centro (centro_id) REFERENCES centros_medicos (id);
ALTER TABLE facturas               ADD FOREIGN KEY IF NOT EXISTS fk_fact_centro (centro_id) REFERENCES centros_medicos (id);
ALTER TABLE formulas_medicas       ADD FOREIGN KEY IF NOT EXISTS fk_form_centro (centro_id) REFERENCES centros_medicos (id);
ALTER TABLE medicamentos           ADD FOREIGN KEY IF NOT EXISTS fk_medi_centro (centro_id) REFERENCES centros_medicos (id);
ALTER TABLE articulos_inventario   ADD FOREIGN KEY IF NOT EXISTS fk_arti_centro (centro_id) REFERENCES centros_medicos (id);
ALTER TABLE inventario_movimientos ADD FOREIGN KEY IF NOT EXISTS fk_movi_centro (centro_id) REFERENCES centros_medicos (id);
