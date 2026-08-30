Upload synchronization (SGA → Moodle)
=======================================

Flow overview
-----------------

The SGA sends, in a single ``POST`` request, a JSON payload containing lists of categories,
courses, users, cohorts, enrolment methods (``enrols``), enrolments (``enrolments``) and
groups. ``sync_up_enrolments_service::do_call()`` (``api/sync/up/index.php``) processes these
lists **in a fixed order**:

1. ``sync_categories()``
2. ``sync_courses()``
3. ``import_template_courses_backup()`` — only runs in synchronous mode (see below)
4. ``sync_users()``
5. ``sync_cohorts()``
6. ``sync_cohorts_members()``
7. ``sync_enrols()``
8. ``sync_enrolments()``
9. ``sync_groups()``
10. ``sync_groups_members()``

Every raw JSON payload received is always persisted (regardless of processing success or
failure) into the ``sga_enrolment_to_sync`` table via ``insertSyncDB()`` — see
:doc:`painel-administrativo` for how to query this history.

.. _possivel-problema-dispatch:

Possible endpoint initialization issue
---------------------------------------------------

.. danger::
   ``api/sync/up/index.php`` (and the equivalent grade endpoint,
   ``api/sync/down/index.php``) load ``api/servicelib.php`` via ``require_once``. Besides
   defining the ``service`` base class, this file also contains — in its global scope, outside
   any function — a ``try/catch`` block that reads ``$_SERVER["QUERY_STRING"]``, validates it
   against a whitelist (``sync_up_enrolments``, ``sync_down_grades``, ``health``) and calls
   ``die()`` with a ``404`` error ("Serviço não existe") if the query string does not match any
   of those names. Since a normal call to ``/api/sync/up/`` (as shown in the plugin's own usage
   example) has no query string, this block can halt execution **before** the rest of
   ``api/sync/up/index.php`` is reached — including the definition of the
   ``sync_up_enrolments_service`` class and the final line
   ``(new sync_up_enrolments_service())->call();``. This has not been confirmed on a real
   running Moodle instance (changing code is out of scope for this task), but it is a direct
   reading of PHP's execution flow and deserves verification before relying on this endpoint in
   production. Of that same whitelist, only ``health`` has a corresponding file in ``api/``
   (``api/health.php``); there is no ``api/sync_up_enrolments.php`` nor
   ``api/sync_down_grades.php``, so the generic dispatcher in ``api/index.php`` (described
   below) cannot serve those two services by name either.

Two distinct entry points
-----------------------------

The plugin exposes the same family of services through two different paths:

.. list-table::
   :header-rows: 1
   :widths: 40 60

   * - Path
     - Behaviour
   * - ``api/index.php?<service_name>``
     - Generic dispatcher: reads the first query-string parameter, validates it against the
       whitelist (``sync_up_enrolments``, ``sync_down_grades``, ``health``), includes
       ``<service_name>.php`` (relative to ``api/``) and instantiates
       ``\tool_sga\<service_name>_service``. Only actually works for ``health``, for the reason
       explained above.
   * - ``api/sync/up/index.php`` and ``api/sync/down/index.php``
     - Self-contained scripts: they do their own ``require_once`` of ``config.php`` and the
       Moodle libraries, define the service class and instantiate/call it directly at the end
       of the file — subject to the initialization issue described above.

Payload format
------------------

Each section of the JSON (``categories``, ``courses``, ``users`` etc.) follows the same
general format, processed by ``request_iterator()``:

.. code-block:: text

   {
     "<secao>": {
       "update_fields": ["campo1", "campo2", ...],   // optional — which fields to update on existing records
       "list": [ { ... }, { ... } ]                  // one object per record
     }
   }

Each object in the list is identified by a **natural key** (``idnumber``, ``username``, or a
combination of fields, depending on the section) used to decide whether the record already
exists (``UPD``) or needs to be created (``ADD``). A complete example is available in
``api/examples/sync.up.full.request.json``; a second example (``sync_up.request-full.json``)
and the reference schemas in ``api/schemas/`` document the expected format — the schemas are
not applied automatically (see the note about validation below).

.. list-table:: Payload sections and required fields
   :header-rows: 1
   :widths: 20 35 45

   * - Section
     - Natural key
     - Required fields (``check_required_fields``)
   * - ``categories``
     - ``idnumber``
     - ``idnumber``, ``name``, ``visible``
   * - ``courses``
     - ``idnumber``
     - ``category_idnumber``, ``fullname``, ``shortname``, ``idnumber``
   * - ``users``
     - ``username``
     - ``username``, ``auth``, ``firstname``, ``lastname``, ``email``, ``password``, ``active``
   * - ``cohorts``
     - ``idnumber``
     - ``idnumber``, ``contextid``, ``visible``
   * - ``cohorts_members``
     - ``cohort_idnumber`` + ``user_username``
     - ``cohort_idnumber``, ``user_username``
   * - ``enrols``
     - ``enrol`` + ``role_shortname`` + ``course_idnumber``
     - ``enrol``, ``role_shortname``, ``course_idnumber``, ``name``
   * - ``enrolments``
     - ``username`` + ``course_idnumber`` + ``enrol`` + ``role_shortname``
     - ``course_idnumber``, ``enrol``, ``username``, ``role_shortname``, ``status``
   * - ``groups``
     - ``idnumber`` + ``course_idnumber``
     - ``course_idnumber``, ``idnumber``, ``name``
   * - ``groups_members``
     - ``group_idnumber`` + ``course_idnumber`` + ``username``
     - ``course_idnumber``, ``group_idnumber``, ``username``

.. note::
   The source code (``api/sync/up/index.php``) contains **two** definitions of the
   ``sync_enrolments()`` method in the ``sync_up_enrolments_service`` class (one right after
   ``sync_enrols()``, another right after ``sync_groups()``). In PHP, declaring the same method
   twice in the same class is a fatal compile-time error (*Cannot redeclare method*) — which
   suggests that this file, as it stands, does not run under any PHP version without first
   removing one of the two declarations. This documentation only records the observation; the
   fix is out of scope for this task (documentation only).

JSON validation
------------------

``validate_json()`` decodes the JSON and ensures it is a valid object, but the structural
validation block against a schema (using the vendored ``Jsv4\Validator`` library in
``classes/Jsv4/``) is **commented out in the code**. In other words, currently **no field is
validated** against the schemas in ``api/schemas/`` — they only serve as reference
documentation of the expected format, not as active validation.

Per-item restrictions and error handling
--------------------------------------------

* ``check_banned_fields()`` rejects, per section, a set of fields that may never be sent
  (typically internal Moodle columns such as ``id``, ``timecreated``, ``sortorder``) — if
  present, the entire request for that item fails.
* ``set_updatable_fields()`` never allows ``idnumber`` to be updated — it throws an exception
  if ``update_fields`` includes that field.
* Errors in one item **do not stop the others**: ``request_iterator()`` catches any
  ``\Throwable`` per item, records the message in ``$this->errors[]`` in the format
  ``"Erro ao processar(ADD|UPD) o <seção>#<índice> '<chave>': `<mensagem>`."`` and moves on to
  the next item in the list.
* The final response always includes three keys: ``urls`` (Moodle access URLs for each
  successfully processed record, per section), ``erros`` and ``successes`` (used only by
  ``import_template_courses_backup()``, see below).

Course template restore (``template_path``)
-------------------------------------------------------

If a new course (``op == 'ADD'``) has the ``template_path`` attribute (an ordered list of
candidate ``idnumber`` values), ``import_template_courses_backup()`` locates the first course
in that list that already exists in Moodle, makes a full backup of it
(``backup::TYPE_1COURSE``) and restores that backup inside the newly created course —
replicating activities, settings and content from the template course. Failures here do not
stop the rest of the synchronization; they are recorded in ``$this->errors[]``.

.. note::
   ``import_template_courses_backup()`` is only called when ``process()`` is invoked in
   synchronous mode (``$assync = true`` in ``do_call()``); there is no code path in the current
   codebase that calls ``process()`` in asynchronous mode (``$assync = false``) — the parameter
   exists in the method signature, but only the value ``true`` is ever used.

Logging for support/audit
------------------------------------

Every upload synchronization request — whether processed successfully or not — is recorded in
``sga_enrolment_to_sync`` (``json``, ``timecreated``, ``processed``). The ``processed`` field
is always inserted as ``0`` by ``insertSyncDB()``; no code in the plugin later updates this
field to ``1`` (success) or ``2`` (failure), even though ``admin/index.php`` and
``admin/view.php`` already translate these three values into the labels "Não processado",
"Sucesso" and "Falha" — in practice, every record currently shows as "Não processado". See
:doc:`painel-administrativo`.
