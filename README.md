# tool_sga

> Reescrever

Documentação: publicada em https://suap-ava-suite.github.io/moodle-tool_sga/ (gerada
automaticamente a cada push em `docs/` via `.github/workflows/docs.yml`, usando o tema Sphinx
[moodle-docs-theme](https://pypi.org/project/moodle-docs-theme/)). Para gerar localmente:

```bash
pip install sphinx moodle-docs-theme
sphinx-build -W -b html docs docs/_build/html
```

Páginas: `docs/visao-geral.rst`, `docs/instalacao.rst`, `docs/sincronizacao-envio.rst`,
`docs/sincronizacao-notas.rst`, `docs/campos-customizados.rst`,
`docs/painel-administrativo.rst`, `docs/desenvolvimento.rst`.

## curl example

````bash
curl -X POST -H "Authentication: Token changeme" -d @admin/tool/sga/api/examples/sync.up.full.request.json http://moodle/admin/tool/sga/api/sync/up/

```

## Tipo de commits

- `feat:` novas funcionalidades.
- `fix:` correção de bugs.
- `refactor:` refatoração ou performances (sem impacto em lógica).
- `style:` estilo ou formatação de código (sem impacto em lógica).
- `test:` testes.
- `doc:` documentação no código ou do repositório.
- `env:` CI/CD ou settings.
- `build:` build ou dependências.
