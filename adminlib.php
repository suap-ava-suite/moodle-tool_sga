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
 * SGA Integration
 *
 * This module provides extensive analytics on a platform of choice
 * Currently support Google Analytics and Piwik
 *
 * @package     tool_sga
 * @category    upgrade
 * @copyright   2025 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Admin settings page for the tool_sga plugin.
 *
 * @package     tool_sga
 * @copyright   2025 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sga_admin_settingspage extends admin_settingpage
{
    /**
     * Constructs the settings page and sets up its fields.
     *
     * @param bool $adminmode whether the settings fields should be added.
     */
    public function __construct($adminmode) {
        $pluginname = 'tool_sga';
        parent::__construct($pluginname, get_string('pluginname', $pluginname), 'moodle/site:config', false, null);
        $this->setup($adminmode);
    }

    /**
     * Shortcut to get_string() scoped to this plugin.
     *
     * @param string $str the string identifier.
     * @param mixed $args arguments passed to get_string().
     * @param bool $lazyload whether to lazy-load the string.
     * @return string the translated string.
     */
    public function _($str, $args = null, $lazyload = false) {
        return get_string($str, $this->name);
    }

    /**
     * Adds a settings heading.
     *
     * @param string $name the heading string identifier.
     * @return void
     */
    public function add_heading($name) {
        $this->add(new admin_setting_heading("{$this->name}/$name", $this->_($name), $this->_("{$name}_desc")));
    }

    /**
     * Adds a text configuration field.
     *
     * @param string $name the field string identifier.
     * @param string $default the default value.
     * @return void
     */
    public function add_configtext($name, $default = '') {
        $this->add(new admin_setting_configtext("{$this->name}/$name", $this->_($name), $this->_("{$name}_desc"), $default));
    }

    /**
     * Adds a textarea configuration field.
     *
     * @param string $name the field string identifier.
     * @param string $default the default value.
     * @return void
     */
    public function add_configtextarea($name, $default = '') {
        $this->add(new admin_setting_configtextarea("{$this->name}/$name", $this->_($name), $this->_("{$name}_desc"), $default));
    }

    /**
     * Adds a checkbox configuration field.
     *
     * @param string $name the field string identifier.
     * @param int $default the default value.
     * @return void
     */
    public function add_configcheckbox($name, $default = 0) {
        $this->add(new admin_setting_configcheckbox("{$this->name}/$name", $this->_($name), $this->_("{$name}_desc"), $default));
    }

    /**
     * Adds the plugin's settings fields when running in admin mode.
     *
     * @param bool $adminmode whether the settings fields should be added.
     * @return void
     */
    public function setup($adminmode) {
        global $CFG;
        if ($adminmode) {
            $defaultenrol = is_dir(dirname(__FILE__) . '/../../enrol/suap/') ? 'suap' : 'manual';
            $this->add_heading('integration_token_header');
            $this->add_configtext("integration_token", 'changeme');
            $this->add_configtext("integration_callback", '');

            $this->add_heading('user_and_enrolment_header');
            $defaultuserpreferences = "auth_forcepasswordchange=0\nhtmleditor=0\nemail_bounce_count=1\n"
                . "email_send_count=1\nvisual_preference=1";
            $this->add_configtextarea("default_user_preferences", $defaultuserpreferences);

            $this->add_heading('notes_to_sync_header');
            $this->add_configtext("notes_to_sync", "'N1', 'N2', 'N3' , 'N4', 'NAF'");
        }
    }
}
