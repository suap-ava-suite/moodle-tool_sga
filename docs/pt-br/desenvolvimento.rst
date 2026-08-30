Desenvolvimento
================

Versionamento
-------------

Sempre que houver alteração em arquivos das pastas ``db/`` ou ``lang/``, ``version.php`` deve
ser incrementado:

* ``$plugin->version`` segue o padrão ``YYYY_MM_DD_XXX``, onde ``YYYY_MM_DD`` reflete a data
  da alteração.
* ``$plugin->release`` segue o padrão ``4.5.XXX``.
* ``XXX`` é o mesmo valor nos dois campos e deve ser incrementado em 1 a cada alteração nessas
  pastas.

Este é o critério verificado por ``moodle-plugin-ci savepoints`` no CI (etapa **Check upgrade
savepoints** em ``ci.yml``).

.. note::
   Este projeto **não** possui um arquivo ``AGENTS.md`` ou ``CLAUDE.md`` no momento desta
   revisão — a regra de versionamento acima segue a mesma convenção observada em outros
   plugins da suíte AVA/SUAP (por exemplo ``auth_suap``), não uma instrução própria deste
   repositório. Como esta tarefa só adiciona arquivos em ``docs/`` e no workflow de
   documentação (fora de ``db/`` e ``lang/``), ``version.php`` não foi alterado.

Tipos de commit
------------------

O ``README.md`` do plugin já documenta a convenção de prefixos de commit usada neste
repositório:

.. list-table::
   :header-rows: 1
   :widths: 20 80

   * - Prefixo
     - Uso
   * - ``feat:``
     - Novas funcionalidades.
   * - ``fix:``
     - Correção de bugs.
   * - ``refactor:``
     - Refatoração ou performance (sem impacto em lógica).
   * - ``style:``
     - Estilo ou formatação de código (sem impacto em lógica).
   * - ``test:``
     - Testes.
   * - ``doc:``
     - Documentação no código ou do repositório.
   * - ``env:``
     - CI/CD ou settings.
   * - ``build:``
     - Build ou dependências.

CI/CD
-----

``.github/workflows/ci.yml`` — **Moodle Plugin CI**
    Executa em todo ``push``/``pull_request`` para ``main``. Usa ``moodlehq/moodle-plugin-ci``
    contra três branches do Moodle (``MOODLE_401_STABLE``, ``MOODLE_402_STABLE``,
    ``MOODLE_403_STABLE``) × PHP (``7.4``, ``8.0``, ``8.1``) × banco (``pgsql``, ``mariadb``).
    Etapas: PHP Lint, PHP Copy/Paste Detector e PHP Mess Detector (não bloqueantes), Moodle
    Code Checker (PHPCS, 0 *warnings*), Moodle PHPDoc Checker (0 *warnings*), ``validate``,
    ``savepoints``, Mustache Lint, Grunt (não bloqueante), PHPUnit (``--fail-on-warning``) e
    Behat com Chrome.

    .. note::
       Como observado em :doc:`visao-geral`, as branches do Moodle testadas aqui
       (4.1 a 4.3) são anteriores à versão mínima declarada em ``$plugin->requires``
       (``2024100710``, ~Moodle 4.5). Não há, neste repositório, um workflow de
       ``release.yml`` equivalente ao de outros plugins da suíte (como ``auth_suap``) que
       empacote um ZIP instalável a cada tag.

``.github/workflows/docs.yml`` — **Build & Deploy Documentation**
    Publica esta documentação (Sphinx) no GitHub Pages a cada *push* em ``main`` que altere
    ``docs/**``. Veja :ref:`documentacao` abaixo.

.. _documentacao:

Documentação
------------

Esta documentação usa `Sphinx <https://www.sphinx-doc.org/>`_ com o tema
`moodle-docs-theme <https://pypi.org/project/moodle-docs-theme/>`_ e arquivos ``.rst`` em
``docs/``. Para gerar localmente:

.. code-block:: bash

   pip install sphinx moodle-docs-theme
   sphinx-build -W -b html docs docs/_build/html

O workflow ``docs.yml`` roda o mesmo comando em CI e publica o resultado via
``actions/deploy-pages``.

Observações consolidadas para quem for mexer no código
------------------------------------------------------------

Esta documentação, sendo apenas descritiva, registrou ao longo das páginas anteriores uma
série de pontos do código-fonte atual que parecem inconsistentes ou incompletos. Estão
reunidos aqui como referência rápida para quem for trabalhar no plugin (nenhum foi corrigido
como parte desta tarefa, que é só de documentação):

* :ref:`possivel-problema-dispatch` — ``api/servicelib.php`` roda um despacho global por
  query string só de ser incluído, o que pode interromper ``api/sync/up/index.php`` e
  ``api/sync/down/index.php`` antes do restante do arquivo executar.
* :doc:`sincronizacao-envio` declara o método ``sync_enrolments()`` duas vezes na mesma
  classe — erro fatal de compilação em PHP.
* :doc:`sincronizacao-notas` usa ``jsonb_object_agg`` (específico do PostgreSQL) apesar do CI
  testar contra MariaDB também, e seu bloco ``catch`` não qualificado provavelmente nunca
  captura a exceção real lançada pela camada de banco.
* :doc:`campos-customizados` — ``db/install.xml`` e ``db/migrate.php`` criam tabelas
  irmãs com nomes diferentes para o mesmo propósito (``tool_sga_relatorio_...`` /
  ``tool_sga_restricoes_...`` vs. ``sga_relatorio_...`` / ``sga_restricoes_...``).
* :doc:`painel-administrativo` referencia templates Mustache (``tool_sga/index``,
  ``tool_sga/view``) que não existem em ``templates/`` neste repositório, e usa uma
  capability (``tool/sga:adminview``) que não é checada em nenhum lugar do código.
* :doc:`instalacao` — o campo de configuração ``integration_callback`` não é lido por
  nenhum código do plugin, e ``default_user_preferences`` não é aplicado durante a criação de
  usuários em ``sync_users()``.
