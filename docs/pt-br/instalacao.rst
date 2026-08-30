Instalação
==========

O plugin fica em ``admin/tool/sga`` no código-fonte do Moodle. Após instalá-lo (ou atualizar
uma versão existente), a instalação/upgrade do Moodle já cria automaticamente as tabelas e os
campos customizados — veja :doc:`campos-customizados`. O que resta é configurar o token de
autenticação da API e, opcionalmente, os demais parâmetros.

1. Configuração no Moodle
---------------------------

Acesse **Administração do site → Servidor → Integrador SGA** (registrado por ``settings.php``
em ``$ADMIN->add('server', ...)``) e preencha:

.. list-table::
   :header-rows: 1
   :widths: 25 15 60

   * - Campo
     - Nome interno
     - Descrição
   * - Integrador SGA auth token
     - ``integration_token``
     - Token comparado com o cabeçalho ``Authentication: Token <token>`` em toda chamada à
       API. **Valor padrão de instalação: ``changeme`` — troque antes de usar em produção.**
   * - URL de callback do Integrador SGA
     - ``integration_callback``
     - Campo de texto livre; não há nenhum código no plugin que leia ou chame essa URL —
       comportamento não implementado no código-fonte atual, apesar do campo existir na tela
       de configuração.
   * - Preferências padrão do usuário
     - ``default_user_preferences``
     - Lista de preferências (formato ``chave=valor``, uma por linha, como um arquivo
       ``.ini``) — ver observação abaixo sobre onde esse campo é (ou não) consumido.
   * - Notas a sincronizar
     - ``notes_to_sync``
     - Lista de identificadores de itens de nota (``idnumber`` dos ``grade_items``), separados
       por vírgula e entre aspas simples — ex.: ``'N1', 'N2', 'N3', 'N4', 'NAF'``. Usado
       diretamente em uma cláusula SQL ``IN (...)`` por :doc:`sincronizacao-notas`.

.. warning::
   ``notes_to_sync`` é interpolado diretamente na consulta SQL em
   ``api/sync/down/index.php`` (não é passado como parâmetro *bind*). Mantenha esse campo
   restrito a administradores de confiança — um valor malicioso permitiria injeção de SQL.

.. note::
   O campo **Preferências padrão do usuário** (``default_user_preferences``) é lido por
   ``adminlib.php`` (tela de configuração) e teria, pelo nome, um paralelo com o comportamento
   de ``local_suap`` em outros plugins da suíte (aplicar preferências apenas na criação da
   conta). No entanto, nenhum trecho do código deste plugin (``locallib.php``,
   ``api/sync/up/index.php``) efetivamente lê ``default_user_preferences`` para aplicá-la a um
   usuário novo — a criação de usuário em ``sync_users()`` só aplica
   ``$user->user_preferences`` vindas do próprio payload JSON do SGA, não a configuração do
   admin. Comportamento não claramente implementado no código-fonte atual apesar do campo
   existir na tela de configuração.

2. Testando o token
----------------------

O endpoint de diagnóstico não exige nenhuma configuração adicional além do token. Para
verificar rapidamente se o plugin está instalado e o token está correto:

.. code-block:: bash

   curl -H "Authentication: Token SEU_TOKEN_AQUI" \
        "http://moodle/admin/tool/sga/api/index.php?health"

Uma resposta ``200`` com ``{"status": "ok", ...}`` confirma que o token está correto e que o
plugin está ativo. Veja :doc:`sincronizacao-envio` para o endpoint de despacho por query
string usado aqui.

3. Enviando uma sincronização
---------------------------------

.. code-block:: bash

   curl -X POST -H "Authentication: Token SEU_TOKEN_AQUI" \
        -d @admin/tool/sga/api/examples/sync.up.full.request.json \
        http://moodle/admin/tool/sga/api/sync/up/

.. warning::
   Este é o exemplo de uso documentado no ``README.md`` original do plugin, mas há uma
   ressalva importante sobre esse endpoint — veja a nota em
   :ref:`possivel-problema-dispatch` (em :doc:`sincronizacao-envio`) antes de depender dele em
   produção sem testar em um ambiente real.
