Custom fields
====================

On install (``db/install.php``) and on every upgrade (``db/upgrade.php``), the plugin calls
``tool_sga_migrate()`` (``db/migrate.php``), which creates auxiliary tables and reapplies the
same set of course and user custom field categories/fields — the same idempotent
``get_or_create()`` logic used by ``auth_suap`` for profile fields is reused here via
``locallib.php``.

Course fields
----------------

``sga_bulk_course_custom_field()`` creates, via ``core_course`` (``course`` area), the
following **course** custom field categories:

.. list-table::
   :header-rows: 1
   :widths: 25 75

   * - Category
     - Fields (shortname)
   * - Campus
     - ``campus_id``, ``campus_sigla``, ``campus_descricao``
   * - Course
     - ``curso_id``, ``curso_codigo``, ``curso_nome``, ``curso_descricao``,
       ``curso_descricao_historico``, ``curso_titulo_certificado_masculino``,
       ``curso_titulo_certificado_feminino``, ``curso_ch_total``, ``curso_ch_aula``,
       ``curso_autoinstrucional`` (checkbox), ``curso_programa``, ``curso_modalidade_id``,
       ``curso_modalidade_descricao``, ``curso_nivel_ensino_id``,
       ``curso_nivel_ensino_descricao``, ``curso_conteudo``, ``curso_sala_coordenacao``
   * - Subject/Curricular component
     - ``disciplina_id``, ``disciplina_tipo``, ``disciplina_sigla``, ``disciplina_descricao``,
       ``disciplina_descricao_historico``, ``disciplina_periodo``, ``disciplina_optativo``,
       ``disciplina_qtd_avaliacoes``, ``disciplina_is_seminario_estagio_docente`` (checkbox),
       ``disciplina_ch_presencial``, ``disciplina_ch_pratica``, ``disciplina_ch_extensao``,
       ``disciplina_ch_pcc``, ``disciplina_ch_visita_tecnica``, ``disciplina_ch_semanal_1s``,
       ``disciplina_ch_semanal_2s``
   * - Class
     - ``turma_id``, ``turma_codigo``, ``turma_ano_periodo``, ``turma_data_inicio``,
       ``turma_data_fim``, ``turma_gerar_matricula`` (checkbox), ``turma_nota_minima``,
       ``turma_completude_minima``, ``turma_modelo_padrao``
   * - Diário (register)
     - ``diario_id``, ``diario_tipo``, ``diario_situacao``, ``diario_descricao``,
       ``diario_descricao_historico``
   * - Open
     - ``carga_horaria``, ``tem_certificado`` (checkbox), ``linguagem_conteudo`` (select,
       populated with the languages installed in Moodle via
       ``get_string_manager()->get_list_of_translations()``)
   * - Integrador AVA
     - ``grupos_sincronizados``
   * - Painel AVA
     - ``sala_tipo``, ``turma_autoinscricao`` (checkbox), ``restricoes_de_autoinscricao``

User fields
--------------

``sga_bulk_user_custom_field()`` creates a single **user** profile field category, **SGA**,
with the fields:

.. list-table::
   :header-rows: 1
   :widths: 30 70

   * - Field (shortname)
     - Description
   * - ``email_google_classroom``
     - @escolar e-mail (Google Classroom)
   * - ``email_academico``
     - @academico e-mail (Microsoft)
   * - ``email_secundario``
     - Secondary e-mail (staff)
   * - ``campus_id`` / ``campus_descricao`` / ``campus_sigla``
     - Campus data
   * - ``curso_id`` / ``curso_codigo`` / ``curso_descricao``
     - Course data
   * - ``turma_id`` / ``turma_codigo``
     - Latest class data
   * - ``polo_id`` / ``polo_nome`` / ``polo_sigla``
     - Hub (``polo``) data
   * - ``ingresso_periodo``
     - Admission period
   * - ``nome_apresentacao`` / ``nome_completo`` / ``nome_social``
     - Variations of the user's name
   * - ``tipo_usuario``
     - User type
   * - ``programa_nome``
     - Program name
   * - ``last_login``
     - JSON of the last login (``textarea``, hidden field — ``visible = 0``)

.. note::
   This set of user fields partially overlaps with the fields created by ``auth_suap``
   (another plugin in the same suite, with its own profile field category). Since both plugins
   use ``get_or_create()`` by ``shortname`` (not by category), if both are installed on the
   same Moodle instance, whichever runs its install/upgrade first "wins" the field definition
   (category, type, visibility); the second one simply reuses the already-existing field with
   the same ``shortname``, without changing it. This is not necessarily a bug — it could be a
   deliberate convention of fields shared between plugins in the suite — but it is not
   explicitly documented in either plugin.

Data tables
--------------------

``tool_sga_migrate()`` also ensures the existence (via ``dbman->table_exists()``, without
recreating them if they already exist) of five tables:

.. list-table::
   :header-rows: 1
   :widths: 35 65

   * - Table
     - Use
   * - ``sga_enrolment_to_sync``
     - Raw log of every :doc:`sincronizacao-envio` request received (``json``,
       ``timecreated``, ``processed``). Queried by :doc:`painel-administrativo`.
   * - ``sga_learning_path`` / ``sga_learning_path_course``
     - Learning path structure (name, description, course, ordering). Created by the
       migration, but no code in this plugin (``api/``, ``admin/``, ``classes/``) reads or
       writes to them — possibly used by another component of the suite, or preparation for a
       feature not yet implemented here.
   * - ``sga_relatorio_cursos_autoinstrucionais``
     - Snapshot of metrics for self-paced mini-courses (enrolled, accesses, passed, failed,
       certificates etc.), grouped by course/campus/class type.
   * - ``sga_restricoes_autoinscricao``
     - Snapshot of self-enrolment restrictions per course.

.. danger::
   ``db/install.xml`` declares two tables with **different** names from the ones
   ``tool_sga_migrate()`` actually creates: ``tool_sga_relatorio_cursos_autoinstrucionais``
   (with the ``tool_`` prefix, ``timecreated`` field) and ``tool_sga_restricoes_autoinscricao``,
   instead of ``sga_relatorio_cursos_autoinstrucionais`` (without the ``tool_`` prefix,
   ``timegenerated`` field instead of ``timecreated``) and ``sga_restricoes_autoinscricao``,
   which are created by ``migrate.php``. Since Moodle processes ``install.xml`` automatically
   on install (before calling ``xmldb_tool_sga_install()``), the likely result is that **the
   two tables with the ``tool_`` prefix are created and never used** by any code in this
   plugin, while the tables without the prefix (actually used, although no reading code for
   them was found during this review) are created separately by ``migrate.php``. This results
   in four tables for two data needs. This could not be confirmed on a real installation; it is
   recorded here as the source code stands today, without modifying it.
