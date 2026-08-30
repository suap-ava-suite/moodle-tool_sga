tool_sga
========

.. image:: https://img.shields.io/badge/License-GPLv3-blue.svg
   :target: https://github.com/suap-ava-suite/moodle-tool_sga/blob/main/LICENSE
   :alt: License

.. image:: https://github.com/suap-ava-suite/moodle-tool_sga/actions/workflows/ci.yml/badge.svg
   :target: https://github.com/suap-ava-suite/moodle-tool_sga/actions/workflows/ci.yml
   :alt: Moodle Plugin CI

.. image:: https://img.shields.io/badge/Moodle-4.5.0%2B-orange.svg
   :target: https://github.com/suap-ava-suite/moodle-tool_sga/blob/main/version.php
   :alt: Moodle compatibility

.. image:: https://github.com/suap-ava-suite/moodle-tool_sga/actions/workflows/docs.yml/badge.svg
   :target: https://github.com/suap-ava-suite/moodle-tool_sga/actions/workflows/docs.yml
   :alt: Build & Deploy Documentation

``tool_sga`` (Integrador SGA) é um plugin de administração (``admin/tool``) para o Moodle que
expõe uma API HTTP usada pelo SGA (Sistema de Gestão Acadêmica) para manter o Moodle
sincronizado: cria e atualiza categorias, cursos, turmas, usuários, coortes, métodos de
inscrição e matrículas a partir de um payload JSON enviado pelo SGA, e devolve notas lançadas
no Moodle para o SGA sob demanda. O plugin também cria dezenas de campos de perfil
customizados (curso e usuário) usados por outros componentes da suíte AVA/SUAP do IFRN.

Conteúdo
--------

.. toctree::
   :maxdepth: 2

   visao-geral
   instalacao
   sincronizacao-envio
   sincronizacao-notas
   campos-customizados
   painel-administrativo
   desenvolvimento
