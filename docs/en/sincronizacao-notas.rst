Grade synchronization (Moodle → SGA)
=======================================

Unlike upload synchronization (which pushes data from the SGA into Moodle), this flow is the
reverse: the SGA queries Moodle to obtain the grades already recorded for a specific class
(``diário``).

Endpoint
--------

``sync_down_grades_service::do_call()`` (``api/sync/down/index.php``) responds to a request
identifying the class through the ``diario_id`` query-string parameter:

.. code-block:: text

   GET /admin/tool/sga/api/sync/down/?diario_id=20231.1.15806.1E.TEC.1386

.. note::
   The comment at the top of the file shows this URL format as an example, but the same
   warning about endpoint initialization made at :ref:`possivel-problema-dispatch` (on the
   :doc:`sincronizacao-envio` page) also applies here, since this file also includes
   ``api/servicelib.php``.

Query
-----

The SQL query locates, via ``course.idnumber LIKE '%#' || :diario_id``, the course whose
``idnumber`` ends with ``#<diario_id>`` (the naming convention used by courses created through
:doc:`sincronizacao-envio`), lists all users enrolled with the ``student`` archetype role in
that course and, for each one, builds a JSON object (``jsonb_object_agg``) mapping the
``idnumber`` of each ``grade_items`` — restricted to the list configured in **Notes to sync**
(``notes_to_sync``, see :doc:`instalacao`) — to the recorded final grade
(``grade_grades.finalgrade``).

.. warning::
   The query uses ``jsonb_object_agg``, a function specific to **PostgreSQL**. Since the CI
   pipeline (``ci.yml``) also tests against MariaDB, this endpoint likely fails on a Moodle
   installation running on MySQL/MariaDB — behaviour not verified in a real environment, only
   inferred from the SQL syntax used.

Response
--------

A JSON array, one object per enrolled student, ordered by full name:

.. code-block:: json

   [
     {
       "matricula": "20231015806001",
       "nome_completo": "Fulano de Tal",
       "notas": {"N1": 8.5, "N2": 9.0}
     }
   ]

``notas`` is ``null`` when the student has no grade recorded in any of the configured items;
when present, it is an object ``{item_idnumber: grade}`` containing only the items the student
actually has in ``grade_grades``.

Error handling
-------------------

The error handling block is ``catch (Exception $ex)`` with no leading backslash. Since the
file declares ``namespace tool_sga;`` at the top, this unqualified name resolves to
``\tool_sga\Exception`` — a class that does not exist in this plugin — rather than to PHP's
global ``\Exception``. In practice, this means the ``catch`` block likely never catches a real
exception thrown by Moodle's database layer (typically a ``dml_exception`` or similar, outside
the ``tool_sga`` namespace): the error tends to propagate uncaught. Even if the ``catch`` block
did catch something, the code inside it calls ``die("error")`` **before** any other line — the
following lines (``http_response_code(500)`` and the ``echo json_encode(...)`` with the
detailed message) are unreachable. In other words, the detailed error handling the code
appears to have been written to produce is not, in fact, reached by any execution path.
