# Quallit Manager — reconstrução da interface

Reconstrução própria e limpa da interface do **Sistema de Gestão TGA**
(`tga.qloudme.com.br`, "Quallit Manager"), feita a partir da estrutura pública
da página de login e de **screenshots das telas internas fornecidos pelo
operador do sistema**. Espelha a stack real: **Flask + Jinja2 + renderização
server-side** (sem SPA).

| Fase | Escopo | Status |
|---|---|---|
| 1 | Tela de login | ✅ concluída |
| 2 | Shell autenticado (sidebar + layout) | ✅ concluída |
| 3 | Dashboard (KPIs + gráfico + atividades) | ✅ concluída |
| 4 | Clientes, Usuários AD, Serviços Windows, Limpeza de Perfil, Monitor de Sessões TSPlus | ✅ concluída |
| 5 | Acesso Servidor TGA (RDP), Datacenter Qloudme, Versionamento, Relatórios, Auditoria de Logins | ✅ concluída |
| 6 | CloudBackup, Manutenção Firebird, Segurança 2FA | ⏳ aguardando screenshots |

---

## Análise da referência (evidências públicas)

| Aspecto | Observado |
|---|---|
| Backend | **Flask (Python)** — sessão `itsdangerous`, redirect `/login?next=…`, flash "Por favor, faça login…" (padrão Flask-Login) |
| Renderização | Server-side com Jinja2, **sem SPA** |
| CSS | Bootstrap 5.3.0 (CDN jsDelivr) + `static/css/style.css` custom |
| JS | `bootstrap.bundle.min.js`; gráfico do dashboard é **Chart.js** |
| Marca | azul-petróleo `#1B9BA8`, laranja `#FD811F` |
| Fonte | stack de sistema `'Segoe UI', Tahoma, …` |
| Layout interno | **sidebar** teal fixa à esquerda + conteúdo em `#f0f4f5`; cards com header colorido, `card:hover { translateY(-2px) }`, tabelas `table-hover` |

### Mapa do dashboard

```
.app  (flex)
├── .sidebar (teal, fixa)
│   ├── marca "Quallit / Quallit Manager"
│   ├── usuário + cargo
│   ├── botão "Resetar Senhas"
│   └── nav: Dashboard · Clientes · Usuários AD · … · Sair
└── .content (#f0f4f5)
    ├── .page-head  → ícone + "Dashboard"
    ├── .kpi-grid   → 5 cards (label + número + ícone + footer colorido "Ver detalhes →")
    ├── .panel      → header "Usuários Novos vs Desativados (24 Meses)" + <canvas> Chart.js
    └── .panel      → header "Últimas Atividades" + lista rolável
```

---

## Stack e dependências

- **Flask** (backend, rotas, sessão, templates) — única dependência Python.
- **Chart.js 4.4.1** e **Bootstrap Icons 1.11.3** — via CDN jsDelivr, carregados
  **apenas nas telas autenticadas** (o mesmo CDN que o sistema original usa).
- **Sem Bootstrap CSS.** O layout (sidebar + grid de cards) é CSS próprio
  (~13 KB) com tokens em `:root`. A tela de login não carrega framework nenhum.

---

## O que mudou em relação ao original (sem clonar defeitos)

| Original | Nesta versão | Motivo |
|---|---|---|
| Bootstrap CSS+JS inteiro em todas as telas | CSS próprio; libs externas só no dashboard e só onde agregam (gráfico, ícones) | Performance |
| `?next` repassado sem validação | `_next_seguro()` aceita só caminho relativo local | Anti *open redirect* |
| — | menu lateral com item ativo (`aria-current`), foco visível, `Esc` fecha o menu mobile | Acessibilidade / UX |
| — | menu vira *off-canvas* com scrim abaixo de 900px | Responsividade real (não só a resolução do print) |
| Estilos soltos | tokens `--brand-*`, `--ctx-*`, `--radius-*` | Manutenibilidade |
| Dados sensíveis nas "Últimas Atividades" (nomes de colegas, IP e host internos) | substituídos por valores fictícios em `data/mock.json` | Higiene de dados — o projeto vive numa pasta de portfólio |

Preservado: layout da sidebar e dos cards, paleta, cores contextuais dos
footers, tipografia de sistema, formato das atividades e os números do print.

**Desvios conhecidos de fidelidade** (ajustáveis):
- Gráfico: eixo X faz *auto-skip* dos meses (mostra ~metade dos rótulos) para
  não sobrepor texto; o original mostra os 24. Trocar `autoSkip: false` em
  `static/js/dashboard.js` para igualar.
- Login: botão "Entrar" usa o azul da marca; no original é o azul padrão do
  Bootstrap (`#0d6efd`).

---

## Estrutura

```
clone_qualiti/
├── app.py                       # 12 rotas + /secao/<slug> (placeholder do resto)
├── requirements.txt             # Flask
├── data/
│   └── mock.json                # TODOS os dados de exibição — 100% fictícios
├── templates/
│   ├── base.html                # <head> + blocos head/scripts
│   ├── shell.html               # layout autenticado (sidebar + conteúdo)
│   ├── login.html
│   ├── dashboard.html
│   ├── clientes.html            usuarios_ad.html         servicos_windows.html
│   ├── limpeza_perfil.html      usuarios_conectados.html (Monitor Sessões TSPlus)
│   ├── rdp.html                 (Acesso Servidor TGA)
│   ├── datacenter.html          versionamento.html       relatorios.html
│   ├── auditoria_logins.html
│   ├── secao.html               # placeholder das 3 telas ainda não feitas
│   └── partials/_sidebar.html
└── static/
    ├── css/style.css            # tokens + todos os componentes
    ├── js/app.js                # toggle de senha, estado de envio, menu mobile
    ├── js/dashboard.js          # Chart.js — gráfico do dashboard
    ├── js/versionamento.js      # Chart.js — barra + rosca da tela Versionamento
    ├── js/tables.js             # filtro de tabela, ações-demo (toast), auto-refresh
    └── img/logo.svg             # marca placeholder
```

### Telas internas (Fases 4 e 5)

| Tela | Observação |
|---|---|
| Clientes, Usuários AD | tabela com busca; Usuários AD tem filtro por status |
| Serviços Windows, Limpeza de Perfil | layout 1:1 |
| Monitor de Sessões TSPlus | "Atualizar" recarrega; "Auto (30s)" recarrega a cada 30 s; barras escalam contra 40 sessões/servidor |
| Acesso Servidor TGA (RDP), Datacenter Qloudme | tabelas de servidores + aviso de segurança |
| Versionamento | chips de executável, 8 indicadores, gráfico de barras + rosca (Chart.js) |
| Relatórios | seletor de mês + estatísticas |
| Auditoria de Logins | período segmentado, busca, tabela paginável |

**Todas as ações destrutivas ou de efeito colateral** ("Parar", "Desativar",
"Ativar", "Limpar Perfil", "Resetar Senhas", "Conectar", "Testar", "Varrer
parque", "Gerar Excel", "Exportar PDF", filtros de período…) são
**demonstrativas** — exibem um aviso e não executam nada.

---

## Como executar

```bash
pip install -r requirements.txt
python app.py
```

Abra <http://localhost:5000>. **Usuário demo:** `admin` / `admin`
(defina `SECRET_KEY` e `DEMO_PASSWORD` no ambiente para alterar).

### O que testar

- Login válido → `/dashboard`; inválido → alerta vermelho; `/dashboard` deslogado → volta ao login
- Dashboard: 5 KPIs, gráfico de barras (teal *Novos* / laranja *Desativados*), lista de atividades rolável
- Menu lateral: item ativo por tela; itens sem tela abrem `secao.html`; "Sair" faz logout (POST)
- Clientes: digitar na busca filtra as linhas; Usuários AD: busca + dropdown de status
- Serviços Windows: "Parar" mostra o aviso de ação-demo
- Monitor de Sessões: "Atualizar" recarrega; marcar "Auto (30s)" recarrega a cada 30 s
- Responsivo: 1920 / 1440 / 1366 / tablet / **mobile** (sidebar vira menu ☰, cards empilham, tabelas rolam na horizontal, `Esc` fecha)
- Teclado: foco visível em todos os controles; console sem erros

- Versionamento: gráfico de barras + rosca renderizam; chips e indicadores conferem
- Auditoria: busca filtra a tabela; "Atualizar" recarrega

Verificado com Playwright (Edge headless) em 1440px e 390px, todas as telas.

---

## Próxima fase

Faltam **3 telas**: CloudBackup, Manutenção Firebird, Segurança 2FA — enviar um
screenshot de cada. Os componentes já existem (`.table-card`/`.grid`,
`.panel`/`.panel--dark`, `.stat-grid`/`.ministat-grid`, `.notice`, `.form-card`,
`.chip-list`, `.segmented`, cores contextuais) — a maioria é montagem desses blocos.
