Sincronização de envio (SGA → Moodle)
=======================================

Visão geral do fluxo
-----------------------

O SGA envia, em uma única requisição ``POST``, um JSON contendo listas de categorias, cursos,
usuários, coortes, métodos de inscrição (``enrols``), matrículas (``enrolments``) e grupos.
``sync_up_enrolments_service::do_call()`` (``api/sync/up/index.php``) processa essas listas
**na ordem fixa**:

1. ``sync_categories()``
2. ``sync_courses()``
3. ``import_template_courses_backup()`` — só roda no modo síncrono (ver abaixo)
4. ``sync_users()``
5. ``sync_cohorts()``
6. ``sync_cohorts_members()``
7. ``sync_enrols()``
8. ``sync_enrolments()``
9. ``sync_groups()``
10. ``sync_groups_members()``

Cada JSON bruto recebido é sempre persistido (independentemente de sucesso ou falha do
processamento) na tabela ``sga_enrolment_to_sync`` via ``insertSyncDB()`` — veja
:doc:`painel-administrativo` para como consultar esse histórico.

.. _possivel-problema-dispatch:

Possível problema de inicialização do endpoint
---------------------------------------------------

.. danger::
   ``api/sync/up/index.php`` (e o
   equivalente de notas, ``api/sync/down/index.php``) carregam ``api/servicelib.php`` via
   ``require_once``. Esse arquivo, além de definir a classe base ``service``, também contém
   — no seu escopo global, fora de qualquer função — um bloco ``try/catch`` que lê
   ``$_SERVER["QUERY_STRING"]``, valida contra uma lista branca (``sync_up_enrolments``,
   ``sync_down_grades``, ``health``) e chama ``die()`` com um erro ``404`` ("Serviço não
   existe") caso a query string não corresponda a nenhum desses nomes. Como uma chamada normal
   a ``/api/sync/up/`` (conforme o próprio exemplo de uso do plugin) não tem query string, esse
   bloco pode interromper a execução **antes** do restante de ``api/sync/up/index.php`` ser
   alcançado — incluindo a definição da classe ``sync_up_enrolments_service`` e a linha final
   ``(new sync_up_enrolments_service())->call();``. Isto não foi confirmado em um Moodle rodando
   de verdade (está fora do escopo desta tarefa alterar código), mas é uma leitura direta do
   fluxo de execução do PHP e merece verificação antes de depender deste endpoint em produção.
   Da mesma lista branca, apenas ``health`` tem um arquivo correspondente em ``api/`` (
   ``api/health.php``); não existem ``api/sync_up_enrolments.php`` nem
   ``api/sync_down_grades.php``, então o dispatcher de ``api/index.php`` (descrito abaixo)
   também não consegue servir esses dois serviços pelo nome.

Dois pontos de entrada distintos
-----------------------------------

O plugin expõe a mesma família de serviços por dois caminhos diferentes:

.. list-table::
   :header-rows: 1
   :widths: 40 60

   * - Caminho
     - Comportamento
   * - ``api/index.php?<nome_do_serviço>``
     - Dispatcher genérico: lê o primeiro parâmetro da query string, valida contra a lista
       branca (``sync_up_enrolments``, ``sync_down_grades``, ``health``), inclui
       ``<nome_do_serviço>.php`` (relativo a ``api/``) e instancia
       ``\tool_sga\<nome_do_serviço>_service``. Só funciona de fato para ``health``, pelo
       motivo explicado acima.
   * - ``api/sync/up/index.php`` e ``api/sync/down/index.php``
     - Scripts autocontidos: fazem seus próprios ``require_once`` de ``config.php`` e das libs
       do Moodle, definem a classe de serviço e a instanciam/chamam diretamente ao final do
       arquivo — sujeitos ao problema de inicialização descrito acima.

Formato do payload
----------------------

Cada seção do JSON (``categories``, ``courses``, ``users`` etc.) segue o mesmo formato geral,
processado por ``request_iterator()``:

.. code-block:: text

   {
     "<secao>": {
       "update_fields": ["campo1", "campo2", ...],   // opcional — quais campos atualizar em registros existentes
       "list": [ { ... }, { ... } ]                  // um objeto por registro
     }
   }

Cada objeto da lista é identificado por uma **chave natural** (``idnumber``, ``username``, ou
combinação de campos, dependendo da seção) usada para decidir se o registro já existe
(``UPD``) ou precisa ser criado (``ADD``). Um exemplo completo está em
``api/examples/sync.up.full.request.json``; um segundo exemplo (``sync_up.request-full.json``)
e os esquemas de referência em ``api/schemas/`` documentam o formato esperado — os esquemas
não são aplicados automaticamente (ver nota sobre validação abaixo).

.. list-table:: Seções do payload e campos obrigatórios
   :header-rows: 1
   :widths: 20 35 45

   * - Seção
     - Chave natural
     - Campos obrigatórios (``check_required_fields``)
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
   O código-fonte (``api/sync/up/index.php``) contém **duas** definições do método
   ``sync_enrolments()`` na classe ``sync_up_enrolments_service`` (uma logo após
   ``sync_enrols()``, outra logo após ``sync_groups()``). Em PHP, declarar o mesmo método duas
   vezes na mesma classe é um erro fatal de compilação (*Cannot redeclare method*) — o que
   sugere que este arquivo, no estado atual, não executa em PHP algum sem antes remover uma das
   duas declarações. Esta documentação apenas registra a observação; a correção está fora do
   escopo desta tarefa (documentação apenas).

Validação do JSON
----------------------

``validate_json()`` decodifica o JSON e garante que é um objeto válido, mas o bloco de
validação estrutural contra um esquema (usando a biblioteca vendorizada ``Jsv4\Validator`` em
``classes/Jsv4/``) está **comentado no código**. Ou seja, atualmente **nenhum campo é validado
contra os esquemas** em ``api/schemas/`` — eles servem apenas como documentação de referência
do formato esperado, não como validação ativa.

Restrições e tratamento de erro por item
--------------------------------------------

* ``check_banned_fields()`` rejeita, por seção, um conjunto de campos que nunca podem ser
  enviados (tipicamente colunas internas do Moodle como ``id``, ``timecreated``,
  ``sortorder``) — se presentes, a requisição inteira para aquele item falha.
* ``set_updatable_fields()`` nunca permite atualizar ``idnumber`` — lança exceção se
  ``update_fields`` incluir esse campo.
* Erros em um item **não interrompem os demais**: ``request_iterator()`` captura qualquer
  ``\Throwable`` por item, registra a mensagem em ``$this->errors[]`` no formato
  ``"Erro ao processar(ADD|UPD) o <seção>#<índice> '<chave>': `<mensagem>`."`` e segue para o
  próximo item da lista.
* A resposta final sempre inclui três chaves: ``urls`` (URLs de acesso no Moodle para cada
  registro processado com sucesso, por seção), ``erros`` e ``successes`` (usada apenas por
  ``import_template_courses_backup()``, ver abaixo).

Restauração de modelo de curso (``template_path``)
-------------------------------------------------------

Se um curso novo (``op == 'ADD'``) tiver o atributo ``template_path`` (uma lista ordenada de
``idnumber``s candidatos), ``import_template_courses_backup()`` localiza o primeiro curso
dessa lista que já existe no Moodle, faz um backup completo dele
(``backup::TYPE_1COURSE``) e restaura esse backup dentro do curso recém-criado — replicando
atividades, configurações e conteúdo do curso-modelo. Falhas aqui não interrompem o restante
da sincronização; são registradas em ``$this->errors[]``.

.. note::
   ``import_template_courses_backup()`` só é chamada quando ``process()`` é invocado em modo
   síncrono (``$assync = true`` em ``do_call()``); não há, no código atual, um caminho que
   chame ``process()`` em modo assíncrono (``$assync = false``) — o parâmetro existe na
   assinatura do método, mas apenas o valor ``true`` é usado.

Registro para suporte/auditoria
------------------------------------

Cada requisição de sincronização de envio — processada com sucesso ou não — é gravada em
``sga_enrolment_to_sync`` (``json``, ``timecreated``, ``processed``). O campo ``processed`` é
sempre inserido como ``0`` por ``insertSyncDB()``; nenhum código do plugin atualiza esse campo
posteriormente para ``1`` (sucesso) ou ``2`` (falha), apesar de ``admin/index.php`` e
``admin/view.php`` já traduzirem esses três valores para os rótulos "Não processado",
"Sucesso" e "Falha" — na prática, todo registro aparece hoje como "Não processado". Veja
:doc:`painel-administrativo`.
