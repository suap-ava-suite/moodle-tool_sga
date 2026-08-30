# tool_sga

O `tool_sga` (Integrador SGA) é um plugin de administração (`admin/tool`) para o Moodle que
expõe uma API HTTP usada pelo SGA (Sistema de Gestão Acadêmica) para manter o Moodle
sincronizado: cursos, turmas, usuários, inscrições e notas.

`tool_sga` (SGA Integrator) is a Moodle admin tool plugin that exposes an HTTP API used by the
SGA (Academic Management System) to keep Moodle synchronized: courses, classes, users,
enrolments and grades.

## Documentação / Documentation

- 🇧🇷 **Português (pt-BR)**: [https://suap-ava-suite.github.io/moodle-tool_sga/pt-br/](https://suap-ava-suite.github.io/moodle-tool_sga/pt-br/)
- 🇺🇸 **English (en)**: [https://suap-ava-suite.github.io/moodle-tool_sga/en/](https://suap-ava-suite.github.io/moodle-tool_sga/en/)

## Exemplo de uso da API

```bash
curl -X POST -H "Authentication: Token changeme" \
     -d @admin/tool/sga/api/examples/sync.up.full.request.json \
     http://moodle/admin/tool/sga/api/sync/up/
```

Veja a documentação completa para detalhes de autenticação, formato do payload e o endpoint de
sincronização de notas.

## Convenção de commits

- `feat:` novas funcionalidades.
- `fix:` correção de bugs.
- `refactor:` refatoração ou performance (sem impacto em lógica).
- `style:` estilo ou formatação de código (sem impacto em lógica).
- `test:` testes.
- `doc:` documentação no código ou do repositório.
- `env:` CI/CD ou settings.
- `build:` build ou dependências.
