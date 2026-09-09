# Painel de Plantão — Fim de Semana

Controle interno das escalas de plantão de sábado e domingo, com uma página
pública somente-leitura para consulta.

## Stack

- PHP 8+ (sem framework) + PDO/MySQL
- JavaScript vanilla (ES modules), sem build
- Sem dependências externas (Composer/npm)

## Estrutura

```
bootstrap.php            Inicialização compartilhada: .env, sessão endurecida, timezone, CSRF
auth_guard.php           Barra o acesso ao painel sem login
login.php / logout.php   Autenticação da equipe interna
index.php                Painel administrativo
assets/                  CSS e JS do painel
backend/
  conexao.php            Conexão PDO
  plantoes_api.php       API JSON (leitura pública + escrita autenticada)
publico/                 Página pública de consulta (somente leitura)
schema.sql               Estrutura do banco + migração
```

## Setup

1. Copie `.env.example` para `.env` e preencha as credenciais.
2. Gere o hash da senha do painel:
   ```
   php -r "echo password_hash('SUA_SENHA', PASSWORD_DEFAULT), PHP_EOL;"
   ```
   e cole em `ADMIN_PASS`.
3. Rode `schema.sql` no banco. **Em banco já existente**, siga o bloco
   "MIGRAÇÃO" do arquivo — o índice `UNIQUE (sabado)` é obrigatório para o
   upsert atômico de plantões.
4. Aponte o virtual host para a pasta do projeto.

## Segurança — o que já está aplicado

- Sessão com cookies `HttpOnly` + `SameSite=Lax` (+ `Secure` sob HTTPS),
  timeout de inatividade e rotação periódica do ID de sessão. O painel usa a
  **mesma sessão do site** (`verifica_acesso.php` da raiz) — não definir
  `session_name` no `bootstrap.php`, senão o login entra em loop.
- Acesso em duas camadas: `verifica_acesso.php` (site) + login próprio do
  painel (`auth_guard.php`). `index.php` e `publico/index.html` incluem o
  `verifica_acesso.php` da raiz via `$_SERVER['DOCUMENT_ROOT']`.
- Login com proteção contra força bruta (bloqueio de 5 min após 5 falhas),
  CSRF no formulário e senha via `password_hash`/`password_verify`.
- **API:** toda ação de escrita (`plantao_save`, `plantao_delete`,
  `support_save`, `support_delete`) e as estatísticas exigem sessão de admin,
  além do token CSRF em todo `POST`.
- Página pública enxerga apenas `summary`, `period` e colaboradores ativos.
- Headers `Cache-Control: no-store` na área logada e nas respostas da API.

## Segurança — pendências do operador

- [ ] **Rotacionar as credenciais do banco e a senha do painel** que estavam
      em texto puro no `.env` (assuma que vazaram).
- [ ] Servir tudo sob HTTPS.
- [ ] Conferir se `display_errors=0` está no `php.ini` de produção.
