# TGA Carreiras

Plataforma de empregos conectando candidatos, empresas e administração.
Stack: PHP 8.2+, MySQL 8+ (PDO), HTML5/CSS3/JS vanilla, Apache + mod_rewrite.

## Status do projeto

**FASE 1 — Fundação: concluída.** **FASE 2 — Design System: concluída.**

### FASE 2 — Design System

CSS puro (sem framework), organizado em camadas — nenhuma tela deve escrever
CSS próprio por página; sempre reaproveitar estas classes:

| Arquivo | Conteúdo |
|---|---|
| `public/assets/css/tokens.css` | Cores, tipografia, espaçamento, raio, sombra, z-index — única fonte de verdade |
| `public/assets/css/base.css` | Reset, tipografia base, foco acessível |
| `public/assets/css/components.css` | Botões, badges, alertas, cards, formulários, tabelas, modal, dropdown, paginação, skeleton, empty state |
| `public/assets/css/layout.css` | Container, navbar pública, dashboard shell (sidebar + conteúdo) |
| `public/assets/js/modal.js` | Abrir/fechar modal via `data-modal-open`/`data-modal-close`, Esc, clique fora, devolve foco |
| `public/assets/js/toast.js` | `mostrarToast(mensagem, tipo)` — notificações temporárias |
| `public/assets/js/ui.js` | Dropdown (`data-dropdown-toggle`) e toggle de sidebar mobile (`data-sidebar-toggle`) |
| `public/design-system.html` | Guia vivo — visualiza todos os componentes acima (não indexado, uso interno) |

Acesse `/design-system.html` (ou o link na página inicial) para ver tudo
renderizado. Identidade visual: azul (`--color-primary-*`, confiança/
profissionalismo) + âmbar (`--color-accent-*`, energia/oportunidade),
tipografia do sistema operacional (sem fonte externa — carrega mais rápido e
não depende de CDN).

O projeto está sendo construído em etapas (Básico → Intermediário → Avançado
→ Profissional → Enterprise). Nenhuma funcionalidade de negócio (login,
vagas, candidaturas etc.) foi implementada ainda — o objetivo desta fase era
apenas ter uma base sólida antes de qualquer módulo complexo começar.

### ⚠️ Correção de rota (auditoria do banco real)

O banco de dados **já existia** em produção (`tgamea80_SUPORTE`, host
`108.167.151.50`) desde antes deste projeto, com dados reais — não estava
vazio como a pasta de código local. A primeira versão de `database/schema.sql`
foi escrita de forma especulativa (baseada só na descrição do plano de
produto) antes de termos acesso a esse banco, e **não batia com a estrutura
real**. Isso contraria o princípio "não recriar tabelas / não inventar
estrutura" do próprio guia deste projeto.

Assim que a conexão real foi testada, auditei a estrutura de verdade via
`SHOW CREATE TABLE` e reescrevi `database/schema.sql` para refletir
exatamente o que existe hoje em produção — ele agora é um **snapshot de
documentação**, não mais um script de criação especulativo. Detalhes,
divergências encontradas e recomendações estão comentados dentro do próprio
arquivo.

**Ponto de atenção de segurança:** as credenciais reais do banco (incluindo
senha) foram compartilhadas em texto puro nesta conversa. Elas foram
colocadas no `.env` local (que é ignorado pelo git) e em nenhum código-fonte.
Ainda assim, **recomendo fortemente trocar essa senha do MySQL** assim que
possível, já que ela ficou registrada em um histórico de chat — isso não é
opcional, é uma boa prática básica de segurança (a senha ficou exposta fora
do seu controle direto).

### Banco compartilhado — regra importante

O banco `tgamea80_SUPORTE` é usado por **vários outros projetos seus**
(prefixos `afiacao_`, `controle_planilhas_`, `renascer_`, `chat_`, `quiz_`,
etc. — dezenas de tabelas). As únicas tabelas do TGA Carreiras são:
`usuarios_carreiras`, `empresas_carreiras`, `vagas`, `candidaturas`,
`sugestoes_tgacarreiras`, `sugestoes_historico`. Existem tabelas `usuarios`
e `empresas` (sem o sufixo `_carreiras`) que **pertencem a outro sistema** —
nunca devem ser tocadas ou referenciadas pelo código do TGA Carreiras.

### O que existe agora

| Item | Arquivo | Descrição |
|---|---|---|
| Configuração/env | `backend/config/config.php` | Carrega `.env`, define timezone, controla exibição/log de erros, captura erros e exceções globalmente |
| Logger | `backend/helpers/logger.php` | Log de erros técnicos em arquivo (`backend/storage/logs/`) |
| Conexão com banco | `backend/database/conexao.php` | PDO singleton, utf8mb4, prepares reais, exceptions |
| Compatibilidade | `backend/conexao.php` | Shim para includes antigos, aponta para o arquivo acima |
| Sessão segura | `backend/helpers/session.php` | Cookie httponly/secure/samesite, regeneração de ID, logout |
| CSRF | `backend/helpers/csrf.php` | Geração e validação de token CSRF via sessão |
| Respostas JSON | `backend/helpers/response.php` | Helpers padronizados de resposta (sucesso/erro) |
| Front controller | `public/index.php` | Roteador mínimo (`/` e `/api/health`) |
| Rewrite | `public/.htaccess`, `.htaccess` (raiz) | Redireciona tudo para `index.php`; raiz cobre hospedagens sem DocumentRoot em `/public` |
| Schema | `database/schema.sql` | Snapshot real (documentação) de `usuarios_carreiras`, `empresas_carreiras`, `vagas`, `candidaturas`, `sugestoes_tgacarreiras`, `sugestoes_historico` |
| Diagnóstico CLI | `bin/verificar-banco.php` | Testa a conexão com o banco fora do navegador |

### O que a auditoria do banco real encontrou (resumo — detalhes em `database/schema.sql`)

- `candidaturas` tem dois campos redundantes para o candidato (`candidato_id`
  e `usuario_id`, ambos nullable, sem FK) e dois para currículo (`curriculo`
  e `curriculo_pdf`) — precisa ser esclarecido qual está realmente em uso
  antes de escrever qualquer tela de candidatura.
- Não existe constraint impedindo candidatura duplicada (vaga + e-mail),
  como o plano do produto exige. **RECOMENDADO**, não aplicado ainda.
- `vagas.empresa_id` é nullable e sem foreign key — hoje o banco não garante
  "toda vaga pertence a uma empresa". **RECOMENDADO** revisar dados órfãos
  antes de adicionar a FK.
- `empresas_carreiras` está em `utf8` (não `utf8mb4`) — inconsistente com as
  demais tabelas. **RECOMENDADO**, não urgente.
- `usuarios_carreiras` tem duas UNIQUE KEY redundantes no e-mail. Limpeza
  **RECOMENDADA**, não urgente.
- Nomes de campo reais divergem do plano do produto em vários pontos
  (`views` em vez de `visualizacoes`, `salario` como `decimal` em vez de
  texto livre, status de candidatura em `novo/em_analise/aprovado/reprovado`
  em vez de `pendente`, etc.) — o código deve sempre seguir os nomes reais.

Nenhuma dessas melhorias foi aplicada — são tabelas em produção com dados
reais, então qualquer `ALTER TABLE` exige análise de impacto e confirmação
explícita antes, conforme o princípio de mudança de banco deste projeto.

### Decisão de escopo desta fase

As tabelas auxiliares citadas no plano do produto — `rate_limit`,
`redefinicao_senha`, `logs_sistema` — foram confirmadas **ausentes** do
banco real (`SHOW TABLES` em 2026-08-11). Serão criadas exatamente quando a
fase que delas depende for implementada:

- `rate_limit` e `redefinicao_senha` → FASE 3 (Autenticação)
- `logs_sistema` → FASE 10 (Auditoria)

`sugestoes_tgacarreiras` e `sugestoes_historico` já existem e já têm dados —
mas note que `sugestoes_tgacarreiras` é compartilhada com outros módulos
(campo `modulo`), então qualquer código de aplicação deve filtrar por esse
campo.

## Como configurar o ambiente

O banco real de produção (`tgamea80_SUPORTE`) já existe e é o único banco
usado até agora — não há um banco local separado. O `.env` (nunca commitado)
aponta diretamente para ele:

```
APP_ENV=development
APP_DEBUG=true
DB_HOST=108.167.151.50
DB_PORT=3306
DB_NAME=tgamea80_SUPORTE
DB_USER=tgamea80_tgamea80
DB_PASS=********
```

1. Copie `.env.example` para `.env` e preencha com as credenciais reais
   (peça-as separadamente se este arquivo não existir na sua cópia local).
2. Teste a conexão pela linha de comando:
   ```bash
   php bin/verificar-banco.php
   ```
3. Suba um servidor local apontando para `public/` e acesse `/api/health`:
   ```bash
   php -S 127.0.0.1:8000 -t public public/index.php
   ```
   `http://127.0.0.1:8000/` deve exibir a página inicial e
   `http://127.0.0.1:8000/api/health` deve retornar `"banco": "conectado"`.

⚠️ Como estamos conectando direto no banco de produção (não há banco de
desenvolvimento isolado ainda), qualquer teste que grave dados durante o
desenvolvimento grava em cima dos dados reais. Se isso virar um problema
recorrente, a solução correta é criar um banco de desenvolvimento separado
(mesmo schema, dados de teste) — ainda não fizemos isso porque não foi
pedido, mas é uma recomendação para quando começarmos a escrever fluxos que
alteram dados (cadastro, candidatura, etc.).

## Deploy em servidor Apache

Aponte o **DocumentRoot para a pasta `public/`**. Isso garante que
`backend/`, `database/` e `bin/` nunca fiquem acessíveis publicamente,
mesmo sem depender do `.htaccess` de bloqueio.

Se a hospedagem não permitir alterar o DocumentRoot (comum em cPanel
apontando para a raiz do domínio), o `.htaccess` da raiz do projeto já
redireciona todo o tráfego para `public/` e bloqueia acesso direto às
pastas internas — mas o ideal continua sendo configurar o DocumentRoot
corretamente quando possível.

## Próxima fase

**FASE 3 — Autenticação**: cadastro/login de candidato, cadastro/login de
empresa, login admin, logout, recuperação de senha, rate limiting. É nesta
fase que as tabelas `rate_limit` e `redefinicao_senha` serão criadas — e
onde o esclarecimento sobre `candidaturas.usuario_id` vs `candidato_id`
(ver seção de auditoria acima) vai ficar mais relevante, já que login vai
definir qual campo passa a ser preenchido de fato.
