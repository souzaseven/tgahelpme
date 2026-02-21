<?php 
include "verifica_login.php";
?>
<!--<?php 
session_start();
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: login.html');
    exit;
}

include "conexao.php";
?>-->

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - TGAMEAJUDA</title>
    <meta name="description" content="Sistema de gerenciamento TGAME - Usuários, Operadores e Devocionais">
  <!-- Favicon -->
    <link rel="icon" href="https://raw.githubusercontent.com/souzaseven/tgahelpme/Desafios/icon%20bot%20tga.ico" type="image/x-icon">

<!--souza system-->
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044"
     crossorigin="anonymous"></script>

<!--meajudatga-->
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-E7ZNTJSRYR"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-E7ZNTJSRYR');
</script>

<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-S8EC5C2WTG"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-S8EC5C2WTG');
</script>
    
<!-- CSS Base -->
<link rel="stylesheet" href="css/reset.css">
<link rel="stylesheet" href="css/layout.css">
<link rel="stylesheet" href="css/cards.css">
<link rel="stylesheet" href="css/table.css">

<!-- CSS do Tema Principal -->
<link rel="stylesheet" href="css/style.css">

<!-- CSS Utilitários e Modais-->
<link rel="stylesheet" href="css/utils.css">
<link rel="stylesheet" href="css/smart-section.css">
<link rel="stylesheet" href="css/modals.css">
<link rel="stylesheet" href="css/timeout.css">
<link rel="stylesheet" href="css/meajuda.css">
<link rel="stylesheet" href="css/equipes-semana.css">
</head>
<body>
    <header>
    <div class="header-content">
            <h1><img src="https://raw.githubusercontent.com/souzaseven/tgahelpme/Desafios/icon%20bot%20tga.ico" 
       alt="Ícone TGA"> Painel Administrativo <a href="https://tgameajuda.com/login.html">TGAMEAJUDA</a></h1>
                <button class="nav-btn" data-table="usuarios">👥 Usuários</button>
                <button class="nav-btn" data-table="operadores">⚡ Operadores</button>
                <button class="nav-btn" data-table="devocionais">📖 Devocionais</button>
                <button class="nav-btn" id="smartSectionBtn">📳 Sessão Smart</button>
<button class="nav-btn" id="meAjudaBtn">🛠️ TGA Me Ajuda</button>
            </nav>
        </div>
    </header>

    <main>
        <div id="content">
            <div class="text-center" style="padding: 4rem 2rem;">
                <h1 style="font-size: 2.5rem; margin-bottom: 1rem;">🚀 Painel Administrativo</h1>
                <p class="text-muted" style="font-size: 1.2rem;">
                    Bem-vindo, <?php echo $_SESSION['usuario_logado']; ?>!
                </p>
                <p class="text-muted" style="font-size: 1.2rem;">Selecione uma tabela no menu acima para começar</p>
            </div>
        </div>
    </main>

    <!-- Modais -->
    <div id="passwordModal" class="modal"></div>
    <div id="filaModal" class="modal"></div>
    <div id="linksLiderModal" class="modal"></div>
    <div id="smartModal" class="modal"></div>
<div id="formulariosModal" class="modal"></div>
<div id="meajudaModal" class="modal"></div>
<div id="formulariosModal" class="modal"></div>
 
</div>

    <!-- Scripts - ORDEM CORRETA -->
    <script src="js/core.js"></script>
    <script src="js/usuarios.js"></script>
    <script src="js/operadores.js"></script>
    <script src="js/devocionais.js"></script>
    <script src="js/modals.js"></script>
    <script src="js/smart-section.js"></script>
<script src="js/timeout.js"></script>
<script src="js/meajuda.js"></script>
<script src="js/auto-load.js"></script>
<script src="js/equipes-semana.js"></script>

    <script>
        // ✅ EVENT LISTENERS PARA OS BOTÕES - DEPOIS QUE TODOS OS SCRIPTS CARREGARAM
        document.addEventListener('DOMContentLoaded', function() {
            // Botões das tabelas
            document.querySelectorAll('.nav-btn[data-table]').forEach(button => {
                button.addEventListener('click', function() {
                    const table = this.getAttribute('data-table');
                    if (typeof loadData !== 'undefined') {
                        loadData(table);
                    } else {
                        console.error('loadData não está definido');
                    }
                });
            });

            // Botão Sessão Smart
            document.getElementById('smartSectionBtn').addEventListener('click', function() {
                if (typeof loadSmartSection !== 'undefined') {
                    loadSmartSection();
                } else {
                    console.error('loadSmartSection não está definido');
                    // Fallback básico
                    document.getElementById('content').innerHTML = `
                        <div class="smart-section">
                            <h1>🧠 Sessão Smart</h1>
                            <p>Funcionalidade em desenvolvimento</p>
                        </div>
                    `;
                }
            });

            console.log('✅ Event listeners configurados');
        });


    </script>
 <script>
    // CSRF Token para uso no JavaScript
    const CSRF_TOKEN = "<?php echo $_SESSION['csrf_token']; ?>";
    
    // ✅ DEBUG PARA VERIFICAR O TOKEN
    console.log('🔐 CSRF Token definido:', CSRF_TOKEN ? 'SIM' : 'NÃO');
    console.log('🔐 Valor do Token:', CSRF_TOKEN);
    console.log('🔐 Tamanho do Token:', CSRF_TOKEN ? CSRF_TOKEN.length : 0);
    
    // Também definir no escopo global para backup
    window.CSRF_TOKEN = CSRF_TOKEN;
    
    // Função para fazer logout
    function logout() {
        if (confirm('Deseja realmente sair do sistema?')) {
            window.location.href = 'logout.php';
        }
    }
</script>


  <div class="visitor-counter">
                        <img src="https://hits.sh/tgameajuda.com/admn/.html.svg?color=007ced&label=visitas&labelColor=FFFFFF&logo=https%3A%2F%2Fraw.githubusercontent.com%2Fsouzaseven%2Ftgahelpme%2FDesafios%2Ficon%2520bot%2520tga.ico" 
                             alt="Contador de visitas">
                    </div>
    </div>
    
    
    
<ins class="adsbygoogle" style="display:block" data-ad-client="ca-pub-8542251167876044" data-ad-slot="7622049777"
        data-ad-format="auto" data-full-width-responsive="true"></ins>
    <script>
        (adsbygoogle = window.adsbygoogle || []).push({});
    </script>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044"
        crossorigin="anonymous"></script>
    <ins class="adsbygoogle" style="display:block; text-align:center;" data-ad-layout="in-article"
        data-ad-format="fluid" data-ad-client="ca-pub-8542251167876044" data-ad-slot="3993704063"></ins>
    <script>
        (adsbygoogle = window.adsbygoogle || []).push({});
    </script>

    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044"
        crossorigin="anonymous"></script>
    <ins class="adsbygoogle" style="display:block" data-ad-format="fluid" data-ad-layout-key="-fb+5w+4e-db+86"
        data-ad-client="ca-pub-8542251167876044" data-ad-slot="1743677769"></ins>
    <script>
        (adsbygoogle = window.adsbygoogle || []).push({});
    </script>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044"
        crossorigin="anonymous"></script>
    <ins class="adsbygoogle" style="display:block" data-ad-format="autorelaxed" data-ad-client="ca-pub-8542251167876044"
        data-ad-slot="9430596092"></ins>
    <script>
        (adsbygoogle = window.adsbygoogle || []).push({});
    </script>
    
</body>
</html>