Installation
============

The plugin lives at ``admin/tool/sga`` in the Moodle source tree. After installing it (or
upgrading an existing version), Moodle's install/upgrade process already creates the tables
and custom fields automatically — see :doc:`campos-customizados`. What remains is to
configure the API authentication token and, optionally, the other parameters.

1. Configuration in Moodle
----------------------------

Go to **Site administration → Server → Integrador SGA** (registered by ``settings.php`` via
``$ADMIN->add('server', ...)``) and fill in:

.. list-table::
   :header-rows: 1
   :widths: 25 15 60

   * - Field
     - Internal name
     - Description
   * - Integrador SGA auth token
     - ``integration_token``
     - Token compared against the ``Authentication: Token <token>`` header on every API call.
       **Default install value: ``changeme`` — change it before using in production.**
   * - Integrador SGA callback URL
     - ``integration_callback``
     - Free-text field; there is no code in the plugin that reads or calls this URL —
       behaviour not implemented in the current source code, despite the field existing on the
       configuration screen.
   * - Default user preferences
     - ``default_user_preferences``
     - List of preferences (``key=value`` format, one per line, like an ``.ini`` file) — see
       the note below about where this field is (or is not) consumed.
   * - Notes to sync
     - ``notes_to_sync``
     - List of grade item identifiers (``idnumber`` of the ``grade_items``), comma-separated
       and wrapped in single quotes — e.g. ``'N1', 'N2', 'N3', 'N4', 'NAF'``. Used directly in
       a SQL ``IN (...)`` clause by :doc:`sincronizacao-notas`.

.. warning::
   ``notes_to_sync`` is interpolated directly into the SQL query in
   ``api/sync/down/index.php`` (it is not passed as a bind parameter). Keep this field
   restricted to trusted administrators — a malicious value would allow SQL injection.

.. note::
   The **Default user preferences** field (``default_user_preferences``) is read by
   ``adminlib.php`` (settings screen) and, by its name, would appear to parallel the behaviour
   of ``local_suap`` in other plugins of the suite (applying preferences only when the account
   is created). However, no part of this plugin's code (``locallib.php``,
   ``api/sync/up/index.php``) actually reads ``default_user_preferences`` to apply it to a new
   user — user creation in ``sync_users()`` only applies ``$user->user_preferences`` coming
   from the SGA's own JSON payload, not the admin setting. Behaviour not clearly implemented
   in the current source code despite the field existing on the configuration screen.

2. Testing the token
------------------------

The diagnostic endpoint requires no additional configuration beyond the token. To quickly
check that the plugin is installed and the token is correct:

.. code-block:: bash

   curl -H "Authentication: Token SEU_TOKEN_AQUI" \
        "http://moodle/admin/tool/sga/api/index.php?health"

A ``200`` response with ``{"status": "ok", ...}`` confirms that the token is correct and that
the plugin is active. See :doc:`sincronizacao-envio` for the query-string dispatch endpoint
used here.

3. Sending a synchronization
--------------------------------

.. code-block:: bash

   curl -X POST -H "Authentication: Token SEU_TOKEN_AQUI" \
        -d @admin/tool/sga/api/examples/sync.up.full.request.json \
        http://moodle/admin/tool/sga/api/sync/up/

.. warning::
   This is the usage example documented in the plugin's original ``README.md``, but there is
   an important caveat about this endpoint — see the note at
   :ref:`possivel-problema-dispatch` (in :doc:`sincronizacao-envio`) before relying on it in
   production without testing it in a real environment.
