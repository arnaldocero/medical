<?php
/**
 * Clasificación de roles por módulo de gestión.
 *
 * - Médicos   → se crean y editan SOLO en "Gestión Médicos" (medicos.php)
 * - Pacientes → se crean y editan SOLO en "Gestión Pacientes" (pacientes.php)
 * - Personal del sistema (cualquier otro rol: Admin, Recepción, Contador, Enfermera...)
 *             → se crean y editan SOLO en "Gestión Usuarios" (usuarios.php)
 *
 * Si en "Gestión Roles" se crea un nuevo rol de médico o de paciente, agregue aquí su ID.
 * Los roles nuevos que no estén en estas listas se consideran personal del sistema.
 */

define('ROLES_MEDICOS', [2, 6, 7, 12]);   // Medico, Medico Especialista, Medico General, Odontólogo
define('ROLES_PACIENTES', [5, 8, 9]);     // Paciente, Paciente Beneficiario, Paciente Particular

/**
 * Lista de IDs para usar dentro de un IN (...) de SQL. Solo enteros, segura para concatenar.
 */
function listaSqlRoles(array $ids) {
    return implode(',', array_map('intval', $ids));
}

function esRolMedico($rolId) {
    return in_array(intval($rolId), ROLES_MEDICOS, true);
}

function esRolPaciente($rolId) {
    return in_array(intval($rolId), ROLES_PACIENTES, true);
}

function esRolPersonal($rolId) {
    return intval($rolId) > 0 && !esRolMedico($rolId) && !esRolPaciente($rolId);
}

/**
 * Roles del personal del sistema (excluye médicos y pacientes), para Gestión Usuarios.
 */
function obtenerRolesPersonal($pdo) {
    $excluir = listaSqlRoles(array_merge(ROLES_MEDICOS, ROLES_PACIENTES));
    return $pdo->query("SELECT id, nombre_rol FROM roles_clinicas WHERE id NOT IN ($excluir) ORDER BY nombre_rol ASC")
               ->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerRolesMedicos($pdo) {
    return $pdo->query("SELECT id, nombre_rol FROM roles_clinicas WHERE id IN (" . listaSqlRoles(ROLES_MEDICOS) . ") ORDER BY nombre_rol ASC")
               ->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerRolesPacientes($pdo) {
    return $pdo->query("SELECT id, nombre_rol FROM roles_clinicas WHERE id IN (" . listaSqlRoles(ROLES_PACIENTES) . ") ORDER BY id ASC")
               ->fetchAll(PDO::FETCH_ASSOC);
}
