Sincronização de notas (Moodle → SGA)
=======================================

Diferente da sincronização de envio (que empurra dados do SGA para o Moodle), este fluxo é o
inverso: o SGA consulta o Moodle para obter as notas já lançadas em um diário (turma)
específico.

Endpoint
--------

``sync_down_grades_service::do_call()`` (``api/sync/down/index.php``) responde a uma
requisição identificando o diário pelo parâmetro de query string ``diario_id``:

.. code-block:: text

   GET /admin/tool/sga/api/sync/down/?diario_id=20231.1.15806.1E.TEC.1386

.. note::
   O comentário no topo do arquivo mostra esse formato de URL como exemplo, mas o mesmo aviso
   sobre inicialização do endpoint feito em :ref:`possivel-problema-dispatch` (na página
   :doc:`sincronizacao-envio`) também se aplica aqui, já que este arquivo também inclui
   ``api/servicelib.php``.

Consulta
--------

A consulta SQL localiza, via ``course.idnumber LIKE '%#' || :diario_id``, o curso cujo
``idnumber`` termina com ``#<diario_id>`` (convenção de nomenclatura usada pelos cursos
criados via :doc:`sincronizacao-envio`), lista todos os usuários matriculados com papel de
arquétipo ``student`` nesse curso e, para cada um, monta um objeto JSON (``jsonb_object_agg``)
mapeando o ``idnumber`` de cada ``grade_items`` — restrito à lista configurada em
**Notas a sincronizar** (``notes_to_sync``, ver :doc:`instalacao`) — para a nota final
lançada (``grade_grades.finalgrade``).

.. warning::
   A consulta usa ``jsonb_object_agg``, uma função específica do **PostgreSQL**. Como a
   esteira de CI (``ci.yml``) também testa contra MariaDB, este endpoint provavelmente falha
   em uma instalação Moodle rodando sobre MySQL/MariaDB — comportamento não verificado em
   ambiente real, apenas inferido da sintaxe SQL usada.

Resposta
--------

Um array JSON, um objeto por aluno matriculado, ordenado por nome completo:

.. code-block:: json

   [
     {
       "matricula": "20231015806001",
       "nome_completo": "Fulano de Tal",
       "notas": {"N1": 8.5, "N2": 9.0}
     }
   ]

``notas`` é ``null`` quando o aluno não tem nenhuma nota lançada nos itens configurados;
quando presente, é um objeto ``{idnumber_do_item: nota}`` apenas com os itens que o aluno
realmente possui em ``grade_grades``.

Tratamento de erro
-------------------

O bloco de tratamento é ``catch (Exception $ex)`` sem barra invertida inicial. Como o arquivo
declara ``namespace tool_sga;`` no topo, esse nome não qualificado resolve para
``\tool_sga\Exception`` — classe que não existe neste plugin — e não para a
``\Exception`` global do PHP. Na prática, isso significa que o ``catch`` provavelmente nunca
captura uma exceção real lançada pela camada de banco do Moodle (normalmente uma
``dml_exception`` ou similar, fora do namespace ``tool_sga``): o erro tende a propagar como não
capturado. Mesmo se o ``catch`` chegasse a capturar algo, o código dentro dele chama
``die("error")`` **antes** de qualquer outra linha — as linhas seguintes
(``http_response_code(500)`` e o ``echo json_encode(...)`` com a mensagem detalhada) ficam
inalcançáveis. Ou seja, o tratamento de erro detalhado que o código parece ter sido escrito
para produzir não é, de fato, alcançado por nenhum caminho de execução.
