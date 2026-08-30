Visão geral
===========

O que o plugin faz
-------------------

``tool_sga`` é um plugin do tipo ``admin/tool`` para o Moodle. Ele não altera o fluxo de
login nem a interface do usuário final: existe para expor uma **API HTTP** consumida pelo SGA
(Sistema de Gestão Acadêmica) e para manter, no Moodle, a estrutura de dados (categorias,
cursos, turmas, usuários, inscrições) e os campos de perfil customizados que o restante da
suíte AVA/SUAP (por exemplo ``painel_ava``, ``integrador_ava``) espera encontrar.

Em resumo, o plugin oferece:

* **Sincronização de envio** (SGA → Moodle): um único endpoint HTTP que recebe um JSON grande
  com categorias, cursos, usuários, coortes, métodos de inscrição, matrículas e grupos, e
  cria/atualiza cada um desses registros no Moodle. Veja :doc:`sincronizacao-envio`.
* **Sincronização de notas** (Moodle → SGA): um endpoint HTTP que devolve, para um diário
  (turma) específico, a lista de alunos matriculados e suas notas lançadas no Moodle. Veja
  :doc:`sincronizacao-notas`.
* **Campos customizados**: na instalação/atualização, cria dezenas de campos de perfil
  customizados de curso e de usuário (campus, curso, turma, polo etc.), usados por outros
  componentes da suíte para exibir/filtrar informações institucionais. Veja
  :doc:`campos-customizados`.
* **Painel administrativo**: duas páginas simples (fora do admin tree padrão do Moodle) para
  listar e inspecionar o histórico de sincronizações de envio recebidas. Veja
  :doc:`painel-administrativo`.

Autenticação da API
--------------------

Todos os endpoints da API (exceto os que dependem de sessão de administrador, como o painel)
exigem um cabeçalho HTTP ``Authentication: Token <token>``, comparado com o valor configurado
em **Integrador SGA auth token** (``integration_token``, ver :doc:`instalacao`). Requisições
sem o cabeçalho recebem ``400``; com token incorreto, ``401``.

Requisitos
----------

.. list-table::
   :header-rows: 1
   :widths: 30 70

   * - Item
     - Valor
   * - Moodle
     - ``$plugin->requires`` = ``2024100710`` em ``version.php``.
   * - PHP
     - A esteira de CI (``ci.yml``) testa PHP 7.4, 8.0 e 8.1.
   * - Banco de dados
     - PostgreSQL ou MariaDB (ambos testados em CI).

.. note::
   O comentário ao lado de ``$plugin->requires`` em ``version.php`` diz
   ``# 3.9.25, php >= 7.4``, mas o valor numérico (``2024100710``) corresponde a uma versão do
   Moodle de outubro/2024, não ao Moodle 3.9. Da mesma forma, a matriz de CI
   (``ci.yml``) testa contra ``MOODLE_401_STABLE``, ``MOODLE_402_STABLE`` e
   ``MOODLE_403_STABLE`` — todas anteriores à versão exigida por ``$plugin->requires``. Ambas
   as inconsistências parecem resquícios de uma atualização de requisitos que não foi replicada
   no comentário nem na matriz de CI; esta documentação não tenta resolver a divergência,
   apenas a registra.

Estrutura do repositório
-------------------------

.. code-block:: text

   tool_sga/
   ├── adminlib.php                  # Tela de configuração (sga_admin_settingspage)
   ├── locallib.php                  # Helpers genéricos de banco (get_or_create, create_or_update...)
   ├── settings.php                  # Registra a tela de configuração no admin do Moodle
   ├── version.php                   # Versão/release/maturidade do plugin
   ├── admin/
   │   ├── index.php                 # Painel: lista o histórico de sincronizações recebidas
   │   └── view.php                  # Painel: detalhe de uma sincronização específica
   ├── api/
   │   ├── index.php                 # Dispatcher por query string (?health, entre outros)
   │   ├── servicelib.php            # Classe base `service` + lógica de despacho e autenticação
   │   ├── health.php                # Serviço de diagnóstico (versão do plugin/Moodle)
   │   ├── sync/up/index.php         # Endpoint de sincronização de envio (SGA → Moodle)
   │   ├── sync/down/index.php       # Endpoint de sincronização de notas (Moodle → SGA)
   │   ├── schemas/                  # Esquemas JSON Schema (referência, não usados na validação ativa)
   │   └── examples/                 # Exemplos de payload de sincronização de envio
   ├── classes/
   │   ├── observer.php              # tool_sga_observer: handlers de eventos de inscrição (vazios)
   │   └── Jsv4/                     # Biblioteca vendorizada de validação JSON Schema (Draft 4)
   ├── db/
   │   ├── access.php                # Capability tool/sga:adminview
   │   ├── events.php                # Observers de user_enrolment_created/updated/deleted
   │   ├── install.php               # Chama tool_sga_migrate(0) na instalação
   │   ├── install.xml               # Duas tabelas (ver nota em campos-customizados)
   │   ├── migrate.php               # Lógica compartilhada de instalação/upgrade (tabelas + campos)
   │   ├── tasks.php                 # Lista de tarefas agendadas (atualmente vazia/comentada)
   │   └── uninstall.php             # No-op
   ├── lang/{en,pt_br}/tool_sga.php  # Strings de idioma
   ├── docs/                         # Esta documentação (Sphinx)
   └── .github/workflows/
       ├── ci.yml                    # moodle-plugin-ci (lint, PHPCS, PHPUnit, Behat)
       └── docs.yml                  # Publica esta documentação no GitHub Pages

Organização
-----------

O repositório vive na organização `suap-ava-suite <https://github.com/suap-ava-suite>`_ como
``moodle-tool_sga``, ao lado de outros componentes da suíte AVA/SUAP usados pelo IFRN — por
exemplo ``painel_ava`` e ``integrador_ava``, que consomem parte dos campos customizados
descritos em :doc:`campos-customizados`.
