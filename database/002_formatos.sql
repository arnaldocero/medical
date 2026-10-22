-- =====================================================================
--  Migración 002: Formatos de historia clínica configurables
--  Motor: MariaDB 10.11 · Idempotente (se puede ejecutar más de una vez).
--  Ejecutar en phpMyAdmin (pestaña SQL) sobre la base medical6_HMS.
--
--  - formatos_historia      : formatos por clínica y sus secciones activas
--  - formato_campos         : campos personalizados de cada formato
--  - formato_medicos        : formatos asignados a cada médico
--  - historia_campos_valores: respuestas de los campos personalizados
--  - historias_clinicas.formato_id: formato usado en la atención
-- =====================================================================

-- Secciones disponibles (columna "secciones", lista JSON):
--   signos_vitales, examen_fisico, plan_formula, anexos, odontograma, vision
-- "Motivo / enfermedad actual" y "Diagnóstico CIE-10" siempre se incluyen (los exige el RIPS).

CREATE TABLE IF NOT EXISTS formatos_historia (
    id                INT(11) NOT NULL AUTO_INCREMENT,
    clinica_id        INT(11) NOT NULL,
    nombre            VARCHAR(150) NOT NULL,
    descripcion       VARCHAR(255) NULL,
    secciones         TEXT NOT NULL,
    es_predeterminado TINYINT(1) NOT NULL DEFAULT 0,
    estado            TINYINT(1) NOT NULL DEFAULT 1,
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_clinica (clinica_id),
    CONSTRAINT fk_formato_clinica FOREIGN KEY (clinica_id) REFERENCES clientes_clinicas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS formato_campos (
    id          INT(11) NOT NULL AUTO_INCREMENT,
    formato_id  INT(11) NOT NULL,
    etiqueta    VARCHAR(150) NOT NULL,
    tipo        ENUM('texto','parrafo','numero','fecha','si_no','lista') NOT NULL DEFAULT 'texto',
    opciones    TEXT NULL,
    obligatorio TINYINT(1) NOT NULL DEFAULT 0,
    orden       INT(11) NOT NULL DEFAULT 0,
    estado      TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_formato (formato_id),
    CONSTRAINT fk_campo_formato FOREIGN KEY (formato_id) REFERENCES formatos_historia (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS formato_medicos (
    formato_id INT(11) NOT NULL,
    usuario_id INT(11) NOT NULL,
    PRIMARY KEY (formato_id, usuario_id),
    KEY idx_usuario (usuario_id),
    CONSTRAINT fk_fm_formato FOREIGN KEY (formato_id) REFERENCES formatos_historia (id) ON DELETE CASCADE,
    CONSTRAINT fk_fm_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_clinicas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS historia_campos_valores (
    id          INT(11) NOT NULL AUTO_INCREMENT,
    historia_id INT(11) NOT NULL,
    campo_id    INT(11) NULL,
    etiqueta    VARCHAR(150) NOT NULL,
    valor       TEXT NULL,
    PRIMARY KEY (id),
    KEY idx_historia (historia_id),
    CONSTRAINT fk_hcv_historia FOREIGN KEY (historia_id) REFERENCES historias_clinicas (id) ON DELETE CASCADE,
    CONSTRAINT fk_hcv_campo FOREIGN KEY (campo_id) REFERENCES formato_campos (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE historias_clinicas ADD COLUMN IF NOT EXISTS formato_id INT(11) NULL AFTER medico_id;
ALTER TABLE historias_clinicas ADD INDEX IF NOT EXISTS idx_formato (formato_id);
ALTER TABLE historias_clinicas ADD FOREIGN KEY IF NOT EXISTS fk_hc_formato (formato_id) REFERENCES formatos_historia (id);

-- ---------------------------------------------------------------------
-- Formatos iniciales en cada clínica (equivalentes a lo que existía antes)
-- ---------------------------------------------------------------------
INSERT INTO formatos_historia (clinica_id, nombre, descripcion, secciones, es_predeterminado)
SELECT c.id, 'Historia Clínica General', 'Consulta médica general',
       '["signos_vitales","examen_fisico","plan_formula","anexos"]', 1
FROM clientes_clinicas c
WHERE NOT EXISTS (SELECT 1 FROM formatos_historia f WHERE f.clinica_id = c.id AND f.nombre = 'Historia Clínica General');

INSERT INTO formatos_historia (clinica_id, nombre, descripcion, secciones, es_predeterminado)
SELECT c.id, 'Historia Clínica de Odontología', 'Consulta odontológica con odontograma',
       '["signos_vitales","examen_fisico","plan_formula","anexos","odontograma"]', 0
FROM clientes_clinicas c
WHERE NOT EXISTS (SELECT 1 FROM formatos_historia f WHERE f.clinica_id = c.id AND f.nombre = 'Historia Clínica de Odontología');

INSERT INTO formatos_historia (clinica_id, nombre, descripcion, secciones, es_predeterminado)
SELECT c.id, 'Examen de la Vista', 'Optometría / Oftalmología: agudeza visual, refracción y PIO',
       '["signos_vitales","examen_fisico","plan_formula","anexos","vision"]', 0
FROM clientes_clinicas c
WHERE NOT EXISTS (SELECT 1 FROM formatos_historia f WHERE f.clinica_id = c.id AND f.nombre = 'Examen de la Vista');

-- Asignar a cada médico el formato que corresponde a su especialidad (igual que el comportamiento anterior)
INSERT IGNORE INTO formato_medicos (formato_id, usuario_id)
SELECT f.id, u.id
FROM usuarios_clinicas u
INNER JOIN datos_medicos dm ON dm.usuario_id = u.id
LEFT JOIN especialidades e ON e.id = dm.especialidad
INNER JOIN formatos_historia f ON f.clinica_id = u.clinica_id AND f.nombre =
    CASE
        WHEN e.nombre LIKE '%odontolog%' THEN 'Historia Clínica de Odontología'
        WHEN e.nombre LIKE '%oftalmolog%' OR e.nombre LIKE '%optometr%' THEN 'Examen de la Vista'
        ELSE 'Historia Clínica General'
    END;

-- Permiso para administrar los formatos (el Admin ya tiene acceso a todo)
INSERT INTO permisos (slug, descripcion, categoria)
SELECT 'formatos.administrar', 'Gestionar formatos de historia clínica y asignarlos a médicos', 'Atención Médica'
WHERE NOT EXISTS (SELECT 1 FROM permisos WHERE slug = 'formatos.administrar');
