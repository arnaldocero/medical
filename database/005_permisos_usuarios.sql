-- =====================================================================
--  Migración 005: Permisos de Gestión Usuarios + limpieza de un médico sin ficha
--  Se puede ejecutar más de una vez.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Permisos finos de Gestión Usuarios (el código ya los usaba, pero no existían en la tabla).
--    "usuarios.administrar" sigue equivaliendo a tenerlos todos.
--    Nota: aunque un rol tenga estos permisos, solo el Administrador puede crear, editar,
--    desactivar o cambiar sedes de usuarios con rol Admin.
-- ---------------------------------------------------------------------
INSERT INTO permisos (slug, descripcion, categoria)
SELECT * FROM (
    SELECT 'usuarios.ver'          AS slug, 'Ver la lista de usuarios del sistema'      AS descripcion, 'Personal' AS categoria UNION ALL
    SELECT 'usuarios.crear',         'Crear usuarios del sistema',                    'Personal' UNION ALL
    SELECT 'usuarios.editar',        'Editar usuarios del sistema',                   'Personal' UNION ALL
    SELECT 'usuarios.eliminar',      'Desactivar usuarios del sistema',               'Personal' UNION ALL
    SELECT 'usuarios.asignar_sede',  'Asignar sedes a los usuarios del sistema',      'Personal'
) nuevos
WHERE NOT EXISTS (SELECT 1 FROM permisos p WHERE p.slug = nuevos.slug);

-- Los roles que ya administraban usuarios reciben también los permisos finos
INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
SELECT rp.rol_id, p.id
FROM rol_permisos rp
INNER JOIN permisos pa ON pa.id = rp.permiso_id AND pa.slug = 'usuarios.administrar'
INNER JOIN permisos p ON p.slug IN ('usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.eliminar', 'usuarios.asignar_sede');

-- ---------------------------------------------------------------------
-- 2. Desactivar el usuario de prueba "medico02" (rol médico SIN ficha, sin citas ni historias).
--    Las condiciones garantizan que no se toque a ningún otro usuario.
-- ---------------------------------------------------------------------
UPDATE usuarios_clinicas u
SET u.estado = 0, u.remember_token = NULL
WHERE u.usuario = 'medico02'
  AND u.rol_id IN (2, 6, 7, 12)
  AND NOT EXISTS (SELECT 1 FROM datos_medicos d      WHERE d.usuario_id = u.id)
  AND NOT EXISTS (SELECT 1 FROM citas_medicas c      WHERE c.medico_id  = u.id)
  AND NOT EXISTS (SELECT 1 FROM historias_clinicas h WHERE h.medico_id  = u.id);
