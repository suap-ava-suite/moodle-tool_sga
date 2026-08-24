Painel administrativo
=======================

O plugin inclui duas páginas simples para inspecionar o histórico de requisições de
:doc:`sincronizacao-envio` recebidas, fora da árvore padrão de administração do Moodle (não
aparecem em **Administração do site**; são acessadas diretamente pela URL).

Acesso
------

.. list-table::
   :header-rows: 1
   :widths: 40 60

   * - URL
     - Finalidade
   * - ``/admin/tool/sga/admin/index.php``
     - Lista paginada dos registros de ``sga_enrolment_to_sync``.
   * - ``/admin/tool/sga/admin/view.php?id=<id>``
     - Detalhe de um registro específico (JSON bruto recebido).

Ambas as páginas checam ``is_siteadmin()`` diretamente e exibem apenas a mensagem
"Fazes o quê aqui?" para quem não é administrador do site.

.. note::
   O plugin define a capability ``tool/sga:adminview`` (``db/access.php``, ``CAP_ALLOW`` para
   o arquétipo ``manager``), com string de idioma própria (``sga:adminview``, "Ver o admin do
   Integrador SGA"). No entanto, nenhuma das duas páginas chama ``require_capability()`` — o
   controle de acesso efetivo é só ``is_siteadmin()``, mais restritivo que a capability
   sugeriria (um usuário com arquétipo ``manager`` mas que não seja *site admin* não consegue
   acessar o painel, mesmo tendo a capability). A capability parece não estar conectada a
   nenhuma verificação de acesso no código atual.

Listagem (``index.php``)
----------------------------

* Parâmetros de query string: ``ordenacao`` (``ASC``/``DESC``, padrão ``ASC``) e ``pagina``
  (padrão ``1``), com 10 itens por página.
* Cada linha mostra ``id``, ``timecreated`` e o status traduzido de ``processed``: ``0`` →
  "Não processado", ``1`` → "Sucesso", ``2`` → "Falha". Como nenhum código do plugin atualiza
  ``processed`` após a inserção inicial (ver nota em :doc:`sincronizacao-envio`), todo
  registro aparece hoje como "Não processado".
* A lógica de paginação (blocos ``$primeirosCinco``/``$ultimosTres``/``$paginacaoVariada``)
  contém um bloco de depuração residual (``echo ("TO AQUI");``) dentro de um dos ramos
  condicionais — texto de depuração que aparentemente ficou no código e seria exibido para o
  usuário caso esse ramo específico seja alcançado (muitas páginas com o índice atual entre a
  quinta posição e a quarta a partir do fim).

Detalhe (``view.php``)
--------------------------

Recebe ``id`` via query string (``$_GET['id']``, sem *type casting* nem validação prévia),
busca o registro correspondente em ``sga_enrolment_to_sync`` e renderiza o mesmo JSON bruto que
o SGA enviou naquela requisição — útil para conferir exatamente o payload recebido ao
investigar uma sincronização com problema.

Templates
---------

Ambas as páginas renderizam via ``$OUTPUT->render_from_template()`` os templates Mustache
``tool_sga/index`` e ``tool_sga/view``. Nenhum arquivo ``.mustache`` foi encontrado em
``templates/`` neste repositório no momento desta revisão — as páginas provavelmente lançam um
erro de template não encontrado até que esses arquivos sejam adicionados.
