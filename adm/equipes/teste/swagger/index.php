<?php
include_once('../../verifica_acesso.php');

/**
 * Cache busting: anexa a data de modificação do arquivo como query string
 * (ex.: app.css?v=1755678900). Assim, sempre que um .css/.js for editado no
 * servidor, o navegador é obrigado a baixar a versão nova em vez de usar a
 * cópia em cache — sem precisar o usuário dar Ctrl+F5 a cada deploy.
 */
function tga_asset($relativePath) {
    $fullPath = __DIR__ . '/' . $relativePath;
    $version = file_exists($fullPath) ? filemtime($fullPath) : time();
    return $relativePath . '?v=' . $version;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="dark light">
<title>TGA API Explorer</title>
<script>
  // Aplica o tema ANTES do primeiro paint, para não piscar claro→escuro.
  // Padrão é escuro (definido direto no CSS); só precisamos agir aqui
  // quando o usuário escolheu explicitamente o tema claro antes.
  (function () {
    try {
      if (localStorage.getItem('tga_theme') === 'light') {
        document.documentElement.setAttribute('data-theme', 'light');
      }
    } catch (e) { /* sem localStorage: mantém o padrão escuro */ }
  })();
</script>
<link rel="stylesheet" href="<?php echo tga_asset('css/app.css'); ?>">
</head>
<body>

  <header class="topbar">
    <div class="topbar__brand">
      <span class="topbar__logo">TGA</span>
      <div>
        <h1>TGA API Explorer</h1>
        <p>Teste e explore as APIs da TGA Sistemas</p>
      </div>
    </div>
    <div class="topbar__actions">
      <span id="connStatus" class="badge badge--neutral">Verificando conexão…</span>
      <label class="switch">
        <input type="checkbox" id="guidedModeToggle">
        <span>Modo Guiado</span>
      </label>
      <button id="themeToggle" class="theme-toggle" type="button" aria-label="Alternar tema claro/escuro" title="Alternar tema">
        <span class="theme-toggle__icon" aria-hidden="true">🌙</span>
        <span class="theme-toggle__label">Escuro</span>
      </button>
    </div>
  </header>

  <div class="layout">

    <!-- ===================== SIDEBAR ===================== -->
    <aside class="sidebar">
      <nav class="sidebar__nav">
        <div class="sidebar__group">
          <h2>Autenticação</h2>
          <ul>
            <li class="sidebar__item" id="sidebarAuthItem">
              <span>Login</span> <span class="count">1</span>
            </li>
          </ul>
        </div>

        <!-- Um grupo por categoria (Misc, Configurações, ...), montado dinamicamente por js/explorer.js a partir de js/endpoints.js -->
        <div id="endpointGroups"></div>
      </nav>

      <div id="guidedPanel" class="guided-panel hidden">
        <h3>Modo Guiado</h3>
        <ol>
          <li>Faça login</li>
          <li>Receba o token</li>
          <li>Escolha um endpoint</li>
          <li>Informe os parâmetros</li>
          <li>Execute</li>
          <li>Analise o retorno</li>
        </ol>
      </div>

      <div class="sidebar__footer">
        <p class="muted small">Sessão vale apenas para esta aba. Nada de credencial fica salvo ao fechar o navegador.</p>
      </div>
    </aside>

    <!-- ===================== MAIN ===================== -->
    <main class="main">

      <!-- ---------- SESSÃO DA API ---------- -->
      <section class="panel panel--accent" id="sessionPanel">
        <div class="panel__header">
          <h2>Sessão da API</h2>
          <span id="sessionStatus" class="badge badge--warn">Não autenticado</span>
        </div>
        <div class="panel__body">
          <div class="session-row">
            <span class="session-row__label">Token</span>
            <code id="tokenDisplay" class="token-display">— nenhum token —</code>
          </div>
          <p id="tokenExpiry" class="muted small hidden"></p>
          <div class="session-row__actions">
            <button id="btnShowToken" class="btn btn--ghost btn--sm" disabled>Mostrar</button>
            <button id="btnCopyToken" class="btn btn--ghost btn--sm" disabled>Copiar</button>
            <button id="btnClearSession" class="btn btn--danger btn--sm" disabled>Limpar sessão</button>
            <button id="btnReauth" class="btn btn--primary btn--sm hidden">Autenticar novamente</button>
          </div>
        </div>
      </section>

      <!-- ===================== GRUPO: AUTENTICAÇÃO ===================== -->
      <h2 class="section-title">Autenticação</h2>

      <section class="panel" id="loginCard">
        <div class="panel__header">
          <div class="endpoint-title">
            <span class="method-badge method-badge--post">POST</span>
            <code>/v1/usuarios-api/login</code>
          </div>
        </div>
        <div class="panel__body">
          <h3>Autenticação do usuário da API</h3>
          <p class="muted">Autentica o usuário e retorna um token temporário. O token deverá ser enviado no header
            <code>Authorization</code> nas demais requisições. Não é necessário token para chamar esta rota.</p>

          <form id="loginForm" class="form" autocomplete="off">
            <div class="form-grid">
              <div class="form__field">
                <label for="username">Usuário</label>
                <input type="text" id="username" name="username" placeholder="Ex: TGA" autocomplete="username" required>
              </div>
              <div class="form__field">
                <label for="password">Senha</label>
                <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
              </div>
            </div>
            <div class="form__actions">
              <button type="submit" id="btnLogin" class="btn btn--primary">Autenticar</button>
              <span id="loginMeta" class="muted small"></span>
            </div>
          </form>

          <div id="loginFeedback" class="feedback hidden"></div>
        </div>
      </section>

      <!-- ===================== GRUPO: EXPLORADOR DE API ===================== -->
      <h2 class="section-title">Explorador de API</h2>
      <p class="section-subtitle muted small">
        Misc (tabelas auxiliares) e Configurações (TGA ERP). Escolha um endpoint na barra lateral.
      </p>

      <!-- Card montado dinamicamente por js/explorer.js a partir de js/endpoints.js -->
      <div id="endpointCardContainer"></div>

      <!-- ===================== GRUPO: CONSOLE (REQUEST / RESPONSE) ===================== -->
      <h2 class="section-title">Console</h2>

      <div class="viewer-grid">
        <!-- ---------- REQUEST VIEWER ---------- -->
        <section class="panel" id="requestPanel">
          <div class="panel__header">
            <h2>Request</h2>
          </div>
          <div class="panel__body">
            <div class="kv"><span class="kv__key">Método</span><span id="reqMethod" class="kv__value">—</span></div>
            <div class="kv"><span class="kv__key">URL</span><span id="reqUrl" class="kv__value">—</span></div>
            <div class="kv-block">
              <span class="kv__key">Headers</span>
              <pre id="reqHeaders" class="code-block">—</pre>
            </div>
            <div class="kv-block">
              <span class="kv__key">Body</span>
              <pre id="reqBody" class="code-block">—</pre>
            </div>
          </div>
        </section>

        <!-- ---------- RESPONSE VIEWER ---------- -->
        <section class="panel" id="responsePanel">
          <div class="panel__header">
            <h2>Response</h2>
            <div class="response-meta">
              <span id="resStatus" class="badge badge--neutral">—</span>
              <span id="resTime" class="muted small">—</span>
            </div>
          </div>
          <div class="panel__body">
            <div id="errorInterpretation" class="error-box hidden"></div>

            <!-- Tudo aqui dentro pode ser "teletransportado" pro modal de tela
                 cheia (js/app.js) quando o usuário clica em Expandir, e volta
                 pro lugar quando fecha. -->
            <div id="responseExpandTarget">
              <div class="response-toolbar">
                <div class="view-tabs" id="responseViewTabs" role="tablist">
                  <button type="button" class="view-tab view-tab--active" data-view="json">JSON</button>
                  <button type="button" class="view-tab" data-view="table">Tabela</button>
                  <button type="button" class="view-tab" data-view="ficha">Ficha</button>
                  <button type="button" class="view-tab" data-view="raw">Raw</button>
                </div>
                <div class="response-toolbar__actions">
                  <button id="btnExpandResponse" class="btn btn--ghost btn--sm" title="Ver em tela cheia">⛶ Expandir</button>
                  <button id="btnExportCsv" class="btn btn--ghost btn--sm" disabled>Exportar CSV</button>
                  <button id="btnCopyResponse" class="btn btn--ghost btn--sm" disabled>Copiar JSON</button>
                </div>
              </div>

              <input type="text" id="responseSearch" class="response-search hidden"
                placeholder="Pesquisar na resposta (código, nome, valor…)">

              <p id="tableScrollHint" class="table-scroll-hint hidden">↔ Esta tabela tem mais colunas do que cabem na tela — arraste para o lado (ou use Expandir) para ver o restante.</p>
              <pre id="resBody" class="code-block code-block--response">—</pre>
              <div id="resTable" class="table-wrap hidden"></div>
            </div>
          </div>
        </section>
      </div>

      <!-- ---------- GERADOR DE CÓDIGO (recolhido por padrão) ---------- -->
      <section class="panel" id="codegenPanel">
        <div class="panel__header">
          <h2>Gerador de código</h2>
          <button id="btnToggleCodegen" class="btn btn--ghost btn--sm" aria-expanded="false">Mostrar</button>
        </div>
        <div class="panel__body hidden" id="codegenBodyWrap">
          <div class="view-tabs" id="codegenTabs" role="tablist">
            <button type="button" class="view-tab view-tab--active" data-lang="javascript">JavaScript</button>
            <button type="button" class="view-tab" data-lang="php">PHP</button>
            <button type="button" class="view-tab" data-lang="curl">cURL</button>
          </div>
          <div class="codegen-toolbar">
            <label class="switch">
              <input type="checkbox" id="codegenIncludeToken">
              <span>Incluir token real neste exemplo (a senha nunca é incluída)</span>
            </label>
            <button id="btnCopyCodegen" class="btn btn--ghost btn--sm" disabled>Copiar código</button>
          </div>
          <pre id="codegenBody" class="code-block">Execute uma requisição para gerar o exemplo de código.</pre>
        </div>
      </section>

      <!-- ---------- HISTÓRICO ---------- -->
      <section class="panel" id="historyPanel">
        <div class="panel__header">
          <h2>Histórico de requisições</h2>
          <span class="muted small">Apenas durante esta sessão</span>
        </div>
        <div class="panel__body">
          <table class="history-table">
            <thead>
              <tr>
                <th>Hora</th>
                <th>Método</th>
                <th>Endpoint</th>
                <th>Status</th>
                <th>Tempo</th>
              </tr>
            </thead>
            <tbody id="historyBody">
              <tr class="history-empty"><td colspan="5">Nenhuma requisição realizada ainda.</td></tr>
            </tbody>
          </table>
        </div>
      </section>

    </main>
  </div>

  <footer class="footer">
    <p class="muted small">TGA API Explorer — ferramenta de testes. Nenhuma credencial é armazenada permanentemente.</p>
  </footer>

  <!-- Modal de tela cheia — recebe o conteúdo de #responseExpandTarget quando expandido -->
  <div id="expandModal" class="expand-modal hidden">
    <div class="expand-modal__backdrop"></div>
    <div class="expand-modal__panel">
      <div class="expand-modal__header">
        <h2>Response — visualização expandida</h2>
        <button id="btnCloseExpand" class="btn btn--ghost btn--sm">✕ Fechar</button>
      </div>
      <div class="expand-modal__body" id="expandModalBody"></div>
    </div>
  </div>

  <script src="<?php echo tga_asset('js/theme.js'); ?>"></script>
  <script src="<?php echo tga_asset('js/storage.js'); ?>"></script>
  <script src="<?php echo tga_asset('js/api.js'); ?>"></script>
  <script src="<?php echo tga_asset('js/jwt.js'); ?>"></script>
  <script src="<?php echo tga_asset('js/codegen.js'); ?>"></script>
  <script src="<?php echo tga_asset('js/ui.js'); ?>"></script>
  <script src="<?php echo tga_asset('js/auth.js'); ?>"></script>
  <script src="<?php echo tga_asset('js/endpoints.js'); ?>"></script>
  <script src="<?php echo tga_asset('js/explorer.js'); ?>"></script>
  <script src="<?php echo tga_asset('js/app.js'); ?>"></script>
</body>
</html>
