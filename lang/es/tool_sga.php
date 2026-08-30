<?php
// This file is part of "Moodle SGA Integration"
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Cadenas en español para tool_sga.
 *
 * @package     tool_sga
 * @category    string
 * @copyright   2025 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Integrador SGA';
$string['sga:adminview'] = 'Ver el admin del Integrador SGA';


# Integrador SGA
$string['integration_token_header'] = 'Integrador SGA';
$string['integration_token_header_desc'] = 'Qué token utilizará el Integrador SGA para autenticarse en esta instalación de Moodle';
$string["integration_token"] = 'Token de autenticación del Integrador SGA';
$string["integration_token_desc"] = 'Qué token utilizará el Integrador SGA para autenticarse en esta instalación de Moodle';

# Enviar sincronización
$string["integration_callback"] = 'URL de callback del Integrador SGA';
$string["integration_callback_desc"] = 'Cuál es la URL de callback del Integrador SGA para esta instalación de Moodle';

# Descargar notas
$string["notes_to_sync_header"] = 'Notas a sincronizar';
$string["notes_to_sync_header_desc"] = 'Configuraciones para la sincronización de notas';
$string["notes_to_sync"] = 'Notas a sincronizar';
$string["notes_to_sync_desc"] = "Notas a sincronizar; para el SUAP, por ejemplo, suele ser: 'N1', 'N2', 'N3', 'N4', 'NAF'.";

# Nuevo usuario y nuevos valores predeterminados de inscripción
$string['user_and_enrolment_header'] = 'Nuevo usuario y nuevos valores predeterminados de inscripción';
$string['user_and_enrolment_header_desc'] = 'Configuraciones predeterminadas de la categoría principal';

$string["default_user_preferences"] = 'Preferencias predeterminadas del usuario';
$string["default_user_preferences_desc"] = 'Todo usuario nuevo (estudiante o profesor) tendrá estas preferencias. Use una línea por preferencia, como un archivo .ini.';

$string["sync_up_enrolments_task"] = 'Integrador SGA: Sincronizar las inscripciones en segundo plano';
