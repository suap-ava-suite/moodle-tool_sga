Campos customizados
====================

Na instalação (``db/install.php``) e em toda atualização (``db/upgrade.php``), o plugin chama
``tool_sga_migrate()`` (``db/migrate.php``), que cria tabelas auxiliares e reaplica o mesmo
conjunto de categorias/campos customizados de curso e de usuário — a mesma lógica idempotente
de ``get_or_create()`` usada por ``auth_suap`` para campos de perfil é reaproveitada aqui via
``locallib.php``.

Campos de curso
----------------

``sga_bulk_course_custom_field()`` cria, via ``core_course`` (área ``course``), as seguintes
categorias de campo customizado de **curso**:

.. list-table::
   :header-rows: 1
   :widths: 25 75

   * - Categoria
     - Campos (shortname)
   * - Campus
     - ``campus_id``, ``campus_sigla``, ``campus_descricao``
   * - Curso
     - ``curso_id``, ``curso_codigo``, ``curso_nome``, ``curso_descricao``,
       ``curso_descricao_historico``, ``curso_titulo_certificado_masculino``,
       ``curso_titulo_certificado_feminino``, ``curso_ch_total``, ``curso_ch_aula``,
       ``curso_autoinstrucional`` (checkbox), ``curso_programa``, ``curso_modalidade_id``,
       ``curso_modalidade_descricao``, ``curso_nivel_ensino_id``,
       ``curso_nivel_ensino_descricao``, ``curso_conteudo``, ``curso_sala_coordenacao``
   * - Disciplina/Componente curricular
     - ``disciplina_id``, ``disciplina_tipo``, ``disciplina_sigla``, ``disciplina_descricao``,
       ``disciplina_descricao_historico``, ``disciplina_periodo``, ``disciplina_optativo``,
       ``disciplina_qtd_avaliacoes``, ``disciplina_is_seminario_estagio_docente`` (checkbox),
       ``disciplina_ch_presencial``, ``disciplina_ch_pratica``, ``disciplina_ch_extensao``,
       ``disciplina_ch_pcc``, ``disciplina_ch_visita_tecnica``, ``disciplina_ch_semanal_1s``,
       ``disciplina_ch_semanal_2s``
   * - Turma
     - ``turma_id``, ``turma_codigo``, ``turma_ano_periodo``, ``turma_data_inicio``,
       ``turma_data_fim``, ``turma_gerar_matricula`` (checkbox), ``turma_nota_minima``,
       ``turma_completude_minima``, ``turma_modelo_padrao``
   * - Diário
     - ``diario_id``, ``diario_tipo``, ``diario_situacao``, ``diario_descricao``,
       ``diario_descricao_historico``
   * - Aberto
     - ``carga_horaria``, ``tem_certificado`` (checkbox), ``linguagem_conteudo`` (select,
       populado com os idiomas instalados no Moodle via
       ``get_string_manager()->get_list_of_translations()``)
   * - Integrador AVA
     - ``grupos_sincronizados``
   * - Painel AVA
     - ``sala_tipo``, ``turma_autoinscricao`` (checkbox), ``restricoes_de_autoinscricao``

Campos de usuário
------------------

``sga_bulk_user_custom_field()`` cria uma única categoria de campo de perfil de **usuário**,
**SGA**, com os campos:

.. list-table::
   :header-rows: 1
   :widths: 30 70

   * - Campo (shortname)
     - Descrição
   * - ``email_google_classroom``
     - E-mail @escolar (Google Classroom)
   * - ``email_academico``
     - E-mail @academico (Microsoft)
   * - ``email_secundario``
     - Secundário (servidores)
   * - ``campus_id`` / ``campus_descricao`` / ``campus_sigla``
     - Dados do campus
   * - ``curso_id`` / ``curso_codigo`` / ``curso_descricao``
     - Dados do curso
   * - ``turma_id`` / ``turma_codigo``
     - Dados da última turma
   * - ``polo_id`` / ``polo_nome`` / ``polo_sigla``
     - Dados do polo
   * - ``ingresso_periodo``
     - Período de ingresso
   * - ``nome_apresentacao`` / ``nome_completo`` / ``nome_social``
     - Variações do nome do usuário
   * - ``tipo_usuario``
     - Tipo de usuário
   * - ``programa_nome``
     - Nome do programa
   * - ``last_login``
     - JSON do último login (``textarea``, campo oculto — ``visible = 0``)

.. note::
   Este conjunto de campos de usuário tem sobreposição parcial com os campos criados por
   ``auth_suap`` (outro plugin da mesma suíte, com sua própria categoria de campos de perfil).
   Como ambos os plugins usam ``get_or_create()`` por ``shortname`` (não por categoria), se
   ambos estiverem instalados no mesmo Moodle, o primeiro a rodar a instalação/upgrade "vence"
   a definição do campo (categoria, tipo, visibilidade); o segundo apenas reaproveita o campo
   já existente com o mesmo ``shortname``, sem alterá-lo. Isso não é necessariamente um bug —
   pode ser uma convenção deliberada de campos compartilhados entre plugins da suíte — mas não
   está documentado explicitamente em nenhum dos dois plugins.

Tabelas de dados
--------------------

``tool_sga_migrate()`` também garante a existência (via ``dbman->table_exists()``, sem
recriação se já existirem) de cinco tabelas:

.. list-table::
   :header-rows: 1
   :widths: 35 65

   * - Tabela
     - Uso
   * - ``sga_enrolment_to_sync``
     - Log bruto de cada requisição de :doc:`sincronizacao-envio` recebida (``json``,
       ``timecreated``, ``processed``). Consultada por :doc:`painel-administrativo`.
   * - ``sga_learning_path`` / ``sga_learning_path_course``
     - Estrutura de trilhas de aprendizagem (nome, descrição, curso, ordenação). Criadas pela
       migração, mas nenhum código deste plugin (``api/``, ``admin/``, ``classes/``) lê ou
       escreve nelas — possivelmente usadas por outro componente da suíte, ou preparação para
       uma funcionalidade ainda não implementada aqui.
   * - ``sga_relatorio_cursos_autoinstrucionais``
     - Snapshot de métricas de minicursos autoinstrucionais (matriculados, acessos, aprovados,
       reprovados, certificados etc.), agrupado por curso/campus/tipo de diário.
   * - ``sga_restricoes_autoinscricao``
     - Snapshot de restrições de autoinscrição por curso.

.. danger::
   ``db/install.xml`` declara duas tabelas com nomes **diferentes** dos que
   ``tool_sga_migrate()`` efetivamente cria: ``tool_sga_relatorio_cursos_autoinstrucionais``
   (com prefixo ``tool_``, campo ``timecreated``) e ``tool_sga_restricoes_autoinscricao``, em
   vez de ``sga_relatorio_cursos_autoinstrucionais`` (sem o prefixo ``tool_``, campo
   ``timegenerated`` em vez de ``timecreated``) e ``sga_restricoes_autoinscricao``, criadas por
   ``migrate.php``. Como o Moodle processa ``install.xml`` automaticamente na instalação
   (antes de chamar ``xmldb_tool_sga_install()``), o resultado provável é que **as duas
   tabelas com prefixo ``tool_`` são criadas e nunca usadas** por nenhum código deste plugin,
   enquanto as tabelas sem o prefixo (efetivamente usadas, embora nenhum código de leitura
   delas tenha sido encontrado nesta revisão) são criadas separadamente por ``migrate.php``.
   Isso resulta em quatro tabelas para duas necessidades de dados. Não foi possível confirmar
   isso em uma instalação real; registra-se aqui como fica no código-fonte hoje, sem alterá-lo.
