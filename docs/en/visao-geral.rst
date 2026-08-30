Overview
========

What the plugin does
---------------------

``tool_sga`` is an ``admin/tool``-type plugin for Moodle. It does not change the login flow
or the end-user interface: it exists to expose an **HTTP API** consumed by the SGA (Academic
Management System) and to keep, inside Moodle, the data structure (categories, courses,
classes, users, enrolments) and the custom profile fields that the rest of the AVA/SUAP suite
(for example ``painel_ava``, ``integrador_ava``) expects to find.

In short, the plugin offers:

* **Upload synchronization** (SGA → Moodle): a single HTTP endpoint that receives a large JSON
  payload with categories, courses, users, cohorts, enrolment methods, enrolments and groups,
  and creates/updates each of these records in Moodle. See :doc:`sincronizacao-envio`.
* **Grade synchronization** (Moodle → SGA): an HTTP endpoint that returns, for a specific
  class (``diário``), the list of enrolled students and their grades recorded in Moodle. See
  :doc:`sincronizacao-notas`.
* **Custom fields**: on install/upgrade, creates dozens of custom course and user profile
  fields (campus, course, class, hub etc.), used by other components of the suite to
  display/filter institutional information. See :doc:`campos-customizados`.
* **Administrative panel**: two simple pages (outside Moodle's standard admin tree) to list
  and inspect the history of received upload synchronizations. See :doc:`painel-administrativo`.

API authentication
--------------------

All API endpoints (except those that rely on an administrator session, such as the panel)
require an HTTP header ``Authentication: Token <token>``, compared against the value
configured in **Integrador SGA auth token** (``integration_token``, see :doc:`instalacao`).
Requests without the header receive ``400``; with an incorrect token, ``401``.

Requirements
------------

.. list-table::
   :header-rows: 1
   :widths: 30 70

   * - Item
     - Value
   * - Moodle
     - ``$plugin->requires`` = ``2024100710`` in ``version.php``.
   * - PHP
     - The CI pipeline (``ci.yml``) tests PHP 7.4, 8.0 and 8.1.
   * - Database
     - PostgreSQL or MariaDB (both tested in CI).

.. note::
   The comment next to ``$plugin->requires`` in ``version.php`` says
   ``# 3.9.25, php >= 7.4``, but the numeric value (``2024100710``) corresponds to a Moodle
   version from October 2024, not to Moodle 3.9. Likewise, the CI matrix (``ci.yml``) tests
   against ``MOODLE_401_STABLE``, ``MOODLE_402_STABLE`` and ``MOODLE_403_STABLE`` — all earlier
   than the version required by ``$plugin->requires``. Both inconsistencies appear to be
   leftovers from a requirements update that was not replicated in the comment or in the CI
   matrix; this documentation does not attempt to resolve the discrepancy, it only records it.

Repository structure
----------------------

.. code-block:: text

   tool_sga/
   ├── adminlib.php                  # Settings screen (sga_admin_settingspage)
   ├── locallib.php                  # Generic database helpers (get_or_create, create_or_update...)
   ├── settings.php                  # Registers the settings screen in Moodle admin
   ├── version.php                   # Plugin version/release/maturity
   ├── admin/
   │   ├── index.php                 # Panel: lists the history of received synchronizations
   │   └── view.php                  # Panel: detail of a specific synchronization
   ├── api/
   │   ├── index.php                 # Query-string dispatcher (?health, among others)
   │   ├── servicelib.php            # Base `service` class + dispatch and authentication logic
   │   ├── health.php                # Diagnostic service (plugin/Moodle version)
   │   ├── sync/up/index.php         # Upload synchronization endpoint (SGA → Moodle)
   │   ├── sync/down/index.php       # Grade synchronization endpoint (Moodle → SGA)
   │   ├── schemas/                  # JSON Schema definitions (reference only, not used for active validation)
   │   └── examples/                 # Upload synchronization payload examples
   ├── classes/
   │   ├── observer.php              # tool_sga_observer: enrolment event handlers (empty)
   │   └── Jsv4/                     # Vendored JSON Schema validation library (Draft 4)
   ├── db/
   │   ├── access.php                # Capability tool/sga:adminview
   │   ├── events.php                # Observers for user_enrolment_created/updated/deleted
   │   ├── install.php               # Calls tool_sga_migrate(0) on install
   │   ├── install.xml               # Two tables (see note in campos-customizados)
   │   ├── migrate.php               # Shared install/upgrade logic (tables + fields)
   │   ├── tasks.php                 # List of scheduled tasks (currently empty/commented out)
   │   └── uninstall.php             # No-op
   ├── lang/{en,pt_br}/tool_sga.php  # Language strings
   ├── docs/                         # This documentation (Sphinx)
   └── .github/workflows/
       ├── ci.yml                    # moodle-plugin-ci (lint, PHPCS, PHPUnit, Behat)
       └── docs.yml                  # Publishes this documentation to GitHub Pages

Organization
------------

The repository lives in the `suap-ava-suite <https://github.com/suap-ava-suite>`_
organization as ``moodle-tool_sga``, alongside other components of the AVA/SUAP suite used by
IFRN — for example ``painel_ava`` and ``integrador_ava``, which consume part of the custom
fields described in :doc:`campos-customizados`.
