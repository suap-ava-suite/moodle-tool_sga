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

``tool_sga`` (SGA Integrator) is an ``admin/tool``-type plugin for Moodle that exposes an HTTP
API consumed by the SGA (Academic Management System) used to keep Moodle synchronized: it
creates and updates categories, courses, classes, users, cohorts, enrolment methods and
enrolments from a JSON payload sent by the SGA, and returns grades recorded in Moodle to the
SGA on demand. The plugin also creates dozens of custom profile fields (course and user) used
by other components of the IFRN AVA/SUAP suite.

Contents
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
