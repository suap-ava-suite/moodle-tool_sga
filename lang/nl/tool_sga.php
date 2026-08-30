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
 * Nederlandse taalstrings voor tool_sga.
 *
 * @package     tool_sga
 * @category    string
 * @copyright   2025 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'SGA-integrator';
$string['sga:adminview'] = 'Het beheer van de SGA-integrator bekijken';


// SGA-integrator
$string['integration_token_header'] = 'SGA-integrator';
$string['integration_token_header_desc'] = 'Welk token wordt door de SGA-integrator gebruikt om zich bij deze Moodle-installatie te authenticeren';
$string["integration_token"] = 'SGA-integrator authenticatietoken';
$string["integration_token_desc"] = 'Welk token wordt door de SGA-integrator gebruikt om zich bij deze Moodle-installatie te authenticeren';

// Synchronisatie verzenden
$string["integration_callback"] = 'Callback-URL van de SGA-integrator';
$string["integration_callback_desc"] = 'Wat is de callback-URL van de SGA-integrator voor deze Moodle-installatie';

// Cijfers ophalen
$string["notes_to_sync_header"] = 'Te synchroniseren cijfers';
$string["notes_to_sync_header_desc"] = 'Instellingen voor het synchroniseren van cijfers';
$string["notes_to_sync"] = 'Te synchroniseren cijfers';
$string["notes_to_sync_desc"] = "Te synchroniseren cijfers; voor het SUAP is dit bijvoorbeeld doorgaans: 'N1', 'N2', 'N3', 'N4', 'NAF'.";

// Standaardinstellingen voor nieuwe gebruiker en nieuwe inschrijving
$string['user_and_enrolment_header'] = 'Nieuwe gebruiker en nieuwe inschrijvingsstandaarden';
$string['user_and_enrolment_header_desc'] = 'Standaardinstellingen van de hoofdcategorie';

$string["default_user_preferences"] = 'Standaardvoorkeuren van de gebruiker';
$string["default_user_preferences_desc"] = 'Elke nieuwe gebruiker (student of docent) krijgt deze voorkeuren. Gebruik één regel per voorkeur, zoals in een .ini-bestand.';

$string["sync_up_enrolments_task"] = 'SGA-integrator: Inschrijvingen op de achtergrond synchroniseren';
