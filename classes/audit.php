<?php
// This file is part of Moodle - https://moodle.org/
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
 * Ponte para a auditoria de usuários do local_suap.
 *
 * O tool_sga não exige o local_suap: sem ele, as funções abaixo apenas executam a escrita core.
 *
 * @package     tool_sga
 * @copyright   2026 IFRN
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_sga;

/**
 * Auditoria de alterações de usuário feitas pela API do SGA.
 */
class audit {
    /**
     * Auditoria disponível (local_suap instalado)?
     *
     * @return bool
     */
    public static function available(): bool {
        return class_exists('\local_suap\audit\writer');
    }

    /**
     * Define o ator da requisição atual.
     *
     * @param string $actorlabel Rótulo do ator.
     * @param string|null $requestref Referência da requisição.
     */
    public static function set_context(string $actorlabel, ?string $requestref = null): void {
        if (self::available()) {
            \local_suap\audit\context::set($actorlabel, $requestref);
        }
    }

    /**
     * Cria o usuário (user_create_user) registrando a criação.
     *
     * @param array|\stdClass $user Dados do usuário.
     * @param bool $updatepassword Atualizar senha.
     * @return int Id.
     */
    public static function create_user($user, bool $updatepassword = true): int {
        $user = (object) $user;
        if (self::available()) {
            return \local_suap\audit\writer::create_user($user, $updatepassword);
        }
        return \user_create_user($user, $updatepassword);
    }

    /**
     * Atualiza campos do usuário (user_update_user) registrando o diff.
     *
     * @param array|\stdClass $user Campos (com id).
     */
    public static function update_user($user): void {
        $user = (object) $user;
        if (self::available()) {
            \local_suap\audit\writer::update_user($user);
            return;
        }
        \user_update_user($user);
    }

    /**
     * Equivalente a profile_save_custom_fields() com diff.
     *
     * @param int $userid Usuário.
     * @param array $fields Mapa shortname => valor.
     */
    public static function save_profile_fields(int $userid, array $fields): void {
        if (self::available()) {
            \local_suap\audit\writer::save_profile_fields($userid, $fields);
            return;
        }
        \profile_save_custom_fields($userid, $fields);
    }

    /**
     * Equivalente a set_user_preference() com registro.
     *
     * @param string $name Nome.
     * @param mixed $value Valor.
     * @param \stdClass|int $user Usuário.
     */
    public static function set_preference(string $name, $value, $user): void {
        if (self::available()) {
            \local_suap\audit\writer::set_preference($name, $value, $user);
            return;
        }
        \set_user_preference($name, $value, $user);
    }

    /**
     * Grava {user}.picture registrando a alteração.
     *
     * @param int $userid Usuário.
     * @param mixed $picture Valor.
     */
    public static function set_picture(int $userid, $picture): void {
        global $DB;
        if (self::available()) {
            \local_suap\audit\writer::set_user_field($userid, 'picture', $picture);
            return;
        }
        $DB->set_field('user', 'picture', $picture, ['id' => $userid]);
    }
}
