-- =====================================================================
--  Migración 003: Límite de intentos de inicio de sesión
--  Motor: MariaDB 10.11 · Idempotente (se puede ejecutar más de una vez).
--
--  - usuarios_clinicas.intentos_fallidos / bloqueado_hasta:
--      3 intentos fallidos seguidos bloquean la cuenta 30 minutos
--  - login_intentos: registro de cada intento (auditoría y límite por IP)
-- =====================================================================

ALTER TABLE usuarios_clinicas ADD COLUMN IF NOT EXISTS intentos_fallidos INT(11) NOT NULL DEFAULT 0 AFTER expira_recuperacion;
ALTER TABLE usuarios_clinicas ADD COLUMN IF NOT EXISTS bloqueado_hasta DATETIME NULL AFTER intentos_fallidos;

CREATE TABLE IF NOT EXISTS login_intentos (
    id                 INT(11) NOT NULL AUTO_INCREMENT,
    usuario_id         INT(11) NULL,
    usuario_ingresado  VARCHAR(150) NOT NULL,
    ip                 VARCHAR(45) NOT NULL,
    exitoso            TINYINT(1) NOT NULL DEFAULT 0,
    motivo             VARCHAR(50) NULL,
    fecha              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ip_fecha (ip, fecha),
    KEY idx_usuario_fecha (usuario_id, fecha),
    CONSTRAINT fk_login_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_clinicas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
