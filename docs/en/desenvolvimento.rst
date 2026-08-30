Development
================

Versioning
-------------

Whenever files under ``db/`` or ``lang/`` change, ``version.php`` must be incremented:

* ``$plugin->version`` follows the ``YYYY_MM_DD_XXX`` pattern, where ``YYYY_MM_DD`` reflects
  the date of the change.
* ``$plugin->release`` follows the ``4.5.XXX`` pattern.
* ``XXX`` is the same value in both fields and must be incremented by 1 on every change to
  those folders.

This is the criterion checked by ``moodle-plugin-ci savepoints`` in CI (the **Check upgrade
savepoints** step in ``ci.yml``).

.. note::
   This project does **not** have an ``AGENTS.md`` or ``CLAUDE.md`` file at the time of this
   review — the versioning rule above follows the same convention observed in other plugins of
   the AVA/SUAP suite (for example ``auth_suap``), not an instruction specific to this
   repository. Since this task only adds files under ``docs/`` and in the documentation
   workflow (outside ``db/`` and ``lang/``), ``version.php`` was not changed.

Commit types
------------------

The plugin's ``README.md`` already documents the commit prefix convention used in this
repository:

.. list-table::
   :header-rows: 1
   :widths: 20 80

   * - Prefix
     - Use
   * - ``feat:``
     - New features.
   * - ``fix:``
     - Bug fixes.
   * - ``refactor:``
     - Refactoring or performance work (no logic impact).
   * - ``style:``
     - Code style or formatting (no logic impact).
   * - ``test:``
     - Tests.
   * - ``doc:``
     - Documentation, in code or in the repository.
   * - ``env:``
     - CI/CD or settings.
   * - ``build:``
     - Build or dependencies.

CI/CD
-----

``.github/workflows/ci.yml`` — **Moodle Plugin CI**
    Runs on every ``push``/``pull_request`` to ``main``. Uses ``moodlehq/moodle-plugin-ci``
    against three Moodle branches (``MOODLE_401_STABLE``, ``MOODLE_402_STABLE``,
    ``MOODLE_403_STABLE``) × PHP (``7.4``, ``8.0``, ``8.1``) × database (``pgsql``,
    ``mariadb``). Steps: PHP Lint, PHP Copy/Paste Detector and PHP Mess Detector
    (non-blocking), Moodle Code Checker (PHPCS, 0 warnings), Moodle PHPDoc Checker (0
    warnings), ``validate``, ``savepoints``, Mustache Lint, Grunt (non-blocking), PHPUnit
    (``--fail-on-warning``) and Behat with Chrome.

    .. note::
       As noted in :doc:`visao-geral`, the Moodle branches tested here (4.1 through 4.3)
       predate the minimum version declared in ``$plugin->requires`` (``2024100710``, ~Moodle
       4.5). There is no ``release.yml`` workflow in this repository equivalent to the one in
       other plugins of the suite (like ``auth_suap``) that packages an installable ZIP on
       every tag.

``.github/workflows/docs.yml`` — **Build & Deploy Documentation**
    Publishes this documentation (Sphinx) to GitHub Pages on every push to ``main`` that
    changes ``docs/**``. See :ref:`documentacao` below.

.. _documentacao:

Documentation
--------------

This documentation uses `Sphinx <https://www.sphinx-doc.org/>`_ with the
`moodle-docs-theme <https://pypi.org/project/moodle-docs-theme/>`_ theme and ``.rst`` files
under ``docs/``. To build it locally:

.. code-block:: bash

   pip install sphinx moodle-docs-theme
   sphinx-build -W -b html docs docs/_build/html

The ``docs.yml`` workflow runs the same command in CI and publishes the result via
``actions/deploy-pages``.

Consolidated notes for anyone working on the code
------------------------------------------------------------

Being purely descriptive, this documentation has recorded, across the previous pages, a
number of points in the current source code that appear inconsistent or incomplete. They are
gathered here as a quick reference for anyone working on the plugin (none of them were fixed
as part of this task, which is documentation only):

* :ref:`possivel-problema-dispatch` — ``api/servicelib.php`` runs a global query-string
  dispatch simply by being included, which can halt ``api/sync/up/index.php`` and
  ``api/sync/down/index.php`` before the rest of the file executes.
* :doc:`sincronizacao-envio` declares the ``sync_enrolments()`` method twice in the same
  class — a fatal compile-time error in PHP.
* :doc:`sincronizacao-notas` uses ``jsonb_object_agg`` (specific to PostgreSQL) even though CI
  also tests against MariaDB, and its unqualified ``catch`` block likely never catches the
  real exception thrown by the database layer.
* :doc:`campos-customizados` — ``db/install.xml`` and ``db/migrate.php`` create sibling
  tables with different names for the same purpose (``tool_sga_relatorio_...`` /
  ``tool_sga_restricoes_...`` vs. ``sga_relatorio_...`` / ``sga_restricoes_...``).
* :doc:`painel-administrativo` references Mustache templates (``tool_sga/index``,
  ``tool_sga/view``) that do not exist under ``templates/`` in this repository, and uses a
  capability (``tool/sga:adminview``) that is not checked anywhere in the code.
* :doc:`instalacao` — the ``integration_callback`` configuration field is not read by any
  code in the plugin, and ``default_user_preferences`` is not applied when users are created
  in ``sync_users()``.
