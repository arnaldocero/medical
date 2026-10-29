-- =====================================================================
--  Migración 004: Unificar y reparar estados de las citas
--  Se puede ejecutar más de una vez.
--
--  La columna citas_medicas.estado es un ENUM:
--      'Asignada','En Sala','En Consulta','Atendida','Cancelada'
--  Atención Express guardaba 'En Atencion' y la historia clínica 'Atendido', que NO existen
--  en el ENUM, así que MariaDB los guardaba como cadena vacía (''). El código ya está
--  corregido; aquí se reparan las citas que quedaron con estado vacío:
--    - con historia clínica registrada  -> 'Atendida'
--    - sin historia (Atención Express)  -> 'En Consulta'
-- =====================================================================

UPDATE citas_medicas c
SET c.estado = 'Atendida'
WHERE (c.estado = '' OR c.estado IS NULL)
  AND EXISTS (SELECT 1 FROM historias_clinicas h WHERE h.cita_id = c.id);

UPDATE citas_medicas c
SET c.estado = 'En Consulta'
WHERE (c.estado = '' OR c.estado IS NULL);
