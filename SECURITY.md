# Política de Segurança

## Versões com Suporte

Apenas a versão mais recente do plugin recebe correções de segurança.

| Versão | Com suporte       |
|--------|-------------------|
| 4.5.x  | ✅ Sim (atual)    |
| < 4.5  | ❌ Não            |

## Relatando uma Vulnerabilidade

Se você descobriu uma vulnerabilidade de segurança neste plugin, **não abra uma issue pública**. Siga as etapas abaixo:

1. **Envie um e-mail** para a equipe de desenvolvimento do IFRN descrevendo o problema:
   - Assunto: `[SECURITY] moodle-tool_sga – <resumo breve>`
   - Descrição detalhada da vulnerabilidade
   - Passos para reprodução
   - Impacto potencial
   - Versão do plugin e do Moodle afetadas
   - (Opcional) Sugestão de correção ou prova de conceito

2. **Aguarde a confirmação.** Você receberá um retorno em até **5 dias úteis** confirmando o recebimento e indicando os próximos passos.

3. **Processo de correção.** Após a confirmação, trabalharemos em conjunto para validar, corrigir e divulgar a vulnerabilidade de forma responsável. O prazo-alvo para disponibilizar uma correção é de **30 dias** após a confirmação.

4. **Divulgação coordenada.** A vulnerabilidade será divulgada publicamente somente após a publicação de uma versão corrigida, salvo acordo diferente com o pesquisador.

## Escopo

Este projeto é um plugin de administração (`admin/tool`) para o Moodle que expõe uma **API HTTP** consumida pelo SGA (Sistema de Gestão Acadêmica) para sincronizar categorias, cursos, turmas, usuários, coortes, matrículas e notas, além de criar dezenas de campos de perfil customizados. As vulnerabilidades de interesse incluem, mas não se limitam a:

- Bypass ou falsificação da autenticação por token (`Authentication: Token <token>`, comparado com `integration_token`) nos endpoints `api/index.php`, `api/sync/up/` e `api/sync/down/`
- Injeção de SQL, especialmente através de campos configuráveis por administrador que são interpolados diretamente em consultas (por exemplo, `notes_to_sync` na sincronização de notas)
- Falhas no processamento do payload de sincronização de envio (`api/sync/up/index.php`) que permitam criação/atualização indevida de categorias, cursos, usuários, coortes, métodos de inscrição, matrículas ou grupos
- Exposição indevida de notas, dados de matrícula ou dados pessoais de alunos e professores através do endpoint de sincronização de notas (`api/sync/down/`)
- Escalada de privilégios ou burla da capacidade `tool/sga:adminview` no painel administrativo (`admin/index.php`, `admin/view.php`)
- Exposição de dados sensíveis no histórico bruto de sincronizações armazenado em `sga_enrolment_to_sync`
- Falhas na criação/emissão de certificados e demais campos customizados de curso e usuário sincronizados com o SUAP
- Cross-Site Scripting (XSS) ou Cross-Site Request Forgery (CSRF) introduzidos pelo plugin

Vulnerabilidades no **Moodle core** ou em outros plugins da suíte AVA/SUAP devem ser reportadas diretamente ao [programa de segurança do Moodle](https://moodle.org/security/) ou ao repositório do plugin correspondente.

## Boas Práticas para Quem Usa o Plugin

- Mantenha o plugin sempre atualizado para a versão mais recente.
- Troque o **Integrador SGA auth token** (`integration_token`) do valor padrão de instalação (`changeme`) antes de usar em produção.
- Restrinja o acesso à configuração de **Notas a sincronizar** (`notes_to_sync`) a administradores de confiança — o valor é usado diretamente em uma cláusula SQL.
- Restrinja o acesso de rede aos endpoints `api/sync/up/` e `api/sync/down/` a integrações confiáveis (SGA), preferencialmente via firewall ou lista de IPs permitidos, além da autenticação por token.
- Audite periodicamente o histórico em `sga_enrolment_to_sync` para identificar tentativas de sincronização mal-intencionadas ou malformadas.
- Mantenha o Moodle e as dependências de banco de dados (PostgreSQL/MariaDB) atualizados com os patches de segurança oficiais.

## Créditos

Agradecemos a todos que contribuem para a segurança deste projeto de forma responsável.

---

© 2026 Kelson da Costa Medeiros – Licença [GNU GPL v3 ou superior](http://www.gnu.org/copyleft/gpl.html)
