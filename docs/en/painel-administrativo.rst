Administrative panel
=======================

The plugin includes two simple pages to inspect the history of received
:doc:`sincronizacao-envio` requests, outside Moodle's standard admin tree (they do not appear
under **Site administration**; they are accessed directly by URL).

Access
------

.. list-table::
   :header-rows: 1
   :widths: 40 60

   * - URL
     - Purpose
   * - ``/admin/tool/sga/admin/index.php``
     - Paginated list of ``sga_enrolment_to_sync`` records.
   * - ``/admin/tool/sga/admin/view.php?id=<id>``
     - Detail of a specific record (raw JSON received).

Both pages check ``is_siteadmin()`` directly and only display the message "Fazes o quê aqui?"
to anyone who is not a site administrator.

.. note::
   The plugin defines the ``tool/sga:adminview`` capability (``db/access.php``,
   ``CAP_ALLOW`` for the ``manager`` archetype), with its own language string
   (``sga:adminview``, "Ver o admin do Integrador SGA"). However, neither page calls
   ``require_capability()`` — the effective access control is only ``is_siteadmin()``, which
   is more restrictive than the capability would suggest (a user with the ``manager``
   archetype who is not a *site admin* cannot access the panel, even with the capability). The
   capability appears not to be connected to any access check in the current code.

Listing (``index.php``)
----------------------------

* Query-string parameters: ``ordenacao`` (``ASC``/``DESC``, default ``ASC``) and ``pagina``
  (default ``1``), with 10 items per page.
* Each row shows ``id``, ``timecreated`` and the translated status of ``processed``: ``0`` →
  "Não processado", ``1`` → "Sucesso", ``2`` → "Falha". Since no code in the plugin updates
  ``processed`` after the initial insert (see note in :doc:`sincronizacao-envio`), every
  record currently shows as "Não processado".
* The pagination logic (``$primeirosCinco``/``$ultimosTres``/``$paginacaoVariada`` blocks)
  contains a leftover debug statement (``echo ("TO AQUI");``) inside one of the conditional
  branches — debug text that apparently remained in the code and would be shown to the user if
  that specific branch is reached (many pages with the current index between the fifth
  position and the fourth from the end).

Detail (``view.php``)
--------------------------

Receives ``id`` via the query string (``$_GET['id']``, with no type casting or prior
validation), fetches the matching record from ``sga_enrolment_to_sync`` and renders the same
raw JSON that the SGA sent in that request — useful for checking exactly what payload was
received when investigating a problematic synchronization.

Templates
---------

Both pages render, via ``$OUTPUT->render_from_template()``, the Mustache templates
``tool_sga/index`` and ``tool_sga/view``. No ``.mustache`` file was found under ``templates/``
in this repository at the time of this review — the pages likely throw a "template not found"
error until those files are added.
