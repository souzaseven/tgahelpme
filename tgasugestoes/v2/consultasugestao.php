<?php
// Conexão com o banco de dados
$host = '108.167.151.50';
$dbname = 'tgamea80_SUPORTE';
$user = 'tgamea80_tgamea80';
$password = 'anderson@2250';
/*
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Busca todas as sugestões no banco
    $stmt = $pdo->query("SELECT * FROM sugestoes ORDER BY data_criacao DESC");
    $sugestoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
    exit();
}*/

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

   $filtroAprovado = $_GET['aprovado'] ?? '';

if ($filtroAprovado === 'sim' || $filtroAprovado === 'nao' || $filtroAprovado === 'em analise') {
    $stmt = $pdo->prepare("SELECT * FROM sugestoes WHERE aprovado = :aprovado ORDER BY data_criacao DESC");
    $stmt->execute([':aprovado' => $filtroAprovado]);
} else {
    $stmt = $pdo->query("SELECT * 
        FROM sugestoes 
        ORDER BY 
            CASE 
                WHEN aprovado = 'em analise' THEN 1
                ELSE 2
            END, 
            data_criacao DESC");
}


    $sugestoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
    exit();
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Sugestões</title>
<link rel="icon" href="./icon bot tga.ico" type="image/x-icon">

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
    


<style>
/* Resetando alguns estilos padrões */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* Corpo da página */
body {
    font-family: 'Arial', sans-serif;
    background-color: #007ced;  /* Fundo azul */
    color: black;                /* Texto branco para contraste */
    padding: 20px;              /* Espaçamento interno do body */
    /* Removido display: flex, align-items e justify-content 
       para permitir que o conteúdo cresça verticalmente */
    /* Removido height: 100vh para que a página possa expandir de acordo com o conteúdo */
    overflow-y: auto;           /* Ativa barra de rolagem caso o conteúdo exceda a tela */
}

/* Contêiner principal */
/*.container {
    width: 90%;                 /* Largura flexível do contêiner */
 /*   max-width: 800px;           /* Limita a largura máxima para boa legibilidade */
/*    margin: 40px auto;          /* Centraliza horizontalmente e cria espaço no topo/embaixo */
/*}

/* Estilização específica para o container */
.container {
    /* Tamanho flexível e centrado */
    width: 90%;               /* Ocupa 90% da tela em desktops maiores */
    max-width: 1200px;        /* Limite de largura para boa legibilidade */
    margin: 40px auto;        /* Centraliza horizontalmente e dá espaçamento vertical */
    
    /* Visual moderno e escuro */
    background: #222;         /* Fundo mais escuro para o container */
    color: #fff;              /* Texto em branco */
    border-radius: 12px;      /* Bordas levemente arredondadas */
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2); /* Sombra suave */
    padding: 30px;            /* Espaçamento interno */
    
    /* Animação sutil ao passar o mouse */
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.container:hover {
    /* Elevação leve ao passar o mouse */
    transform: translateY(-4px);
    box-shadow: 0 12px 20px rgba(0, 0, 0, 0.3);
}

/* Responsividade */
@media (max-width: 992px) {
    .container {
        width: 90%;
        margin: 30px auto;
        padding: 25px;
    }
}

@media (max-width: 768px) {
    .container {
        width: 95%;
        margin: 20px auto;
        padding: 20px;
    }
}

@media (max-width: 480px) {
    .container {
        width: 95%;
        margin: 15px auto;
        padding: 15px;
    }
}


/* Título principal */
h1 {
    font-size: 2.5rem;
    margin-bottom: 20px;
    color: white;                /* Cor do título em branco */
    text-align: center;
}

/* Formulário */
form {
    background-color: #ffffff;  /* Fundo branco para o formulário */
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    width: 100%;                /* Ocupa toda a largura do contêiner */
    transition: transform 0.3s ease; /* Animação ao enviar com sucesso */
}

/* Quando o formulário é enviado com sucesso */
form.success {
    transform: scale(1.02);
}

/* Labels do formulário */
label {
    font-size: 1rem;
    color: #333;
    display: block;
    margin-bottom: 10px;
}

/* Campos de entrada */
input[type="text"],
input[type="email"],   /* Adiciona o campo de e-mail */
textarea,
select {
    width: 100%;
    padding: 12px;
    margin-bottom: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
    transition: border 0.3s ease;
}

/* Efeito de foco nos campos de entrada */
input[type="text"]:focus,
input[type="email"]:focus, /* Foco no campo de e-mail */
textarea:focus,
select:focus {
    border-color: #007ced; /* Foco com a cor do fundo azul */
    outline: none;
}

/* Botão de envio */
button {
    background-color: #007ced;  /* Cor do botão azul */
    color: #fff;
    border: none;
    padding: 12px 20px;
    font-size: 1rem;
    cursor: pointer;
    border-radius: 4px;
    transition: background-color 0.3s ease;
}

/* Efeito de hover no botão */
button:hover {
    background-color: #005bb5;  /* Cor do botão no hover */
}

/* Estilo da tabela de sugestões */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 30px;
}

table th,
table td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

table th {
    background-color: #007ced;  /* Fundo da tabela azul */
    color: white;
}

table td a {
    color: #007ced;             /* Cor do link azul */
    text-decoration: none;
    font-weight: bold;
}

/* Efeito de hover no link */
table td a:hover {
    text-decoration: underline;
    color: #005bb5;             /* Cor do link no hover */
}

/* Estilo de mensagens de status */
p {
    font-size: 1.1rem;
    color: #007ced;
    margin-bottom: 20px;
    font-weight: bold;
}

p.error {
    color: red;
}

/* Estilização do botão de voltar */
.btn-voltar {
    position: fixed;
    top: 20px;      /* Distância do topo da tela */
    left: 1px;      /* Distância da lateral esquerda */
    background-color: #007ced; /* Cor de fundo */
    color: #fff;    /* Cor do texto */
    padding: 10px 20px;
    border-radius: 11px;
    text-decoration: none;     /* Remove o sublinhado do link */
    font-size: 16px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Sombra para dar destaque */
    z-index: 1000;  /* Garante que o botão fique acima de outros elementos */
}

.btn-voltar:hover {
    opacity: 0.8;   /* Efeito de hover */
}

/* -----------------------------
   Botões de Scroll (descer/subir)
   ----------------------------- */

/* Botão para descer até o final da página */
#scroll-down-btn {
    position: fixed;
    top: 20px;         /* Distância do topo */
    right: 20px;       /* Distância da lateral direita */
    background-color: #007ced;
    color: #fff;
    padding: 10px 20px;
    border-radius: 11px;
    font-size: 16px;
    cursor: pointer;
    display: none;     /* Inicialmente oculto, controle via JS */
    z-index: 1000;     /* Fica acima dos demais elementos */
    text-decoration: none;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

/* Botão para voltar ao topo */
#scroll-up-btn {
    position: fixed;
    bottom: 20px;      /* Distância do rodapé */
    right: 20px;       /* Distância da lateral direita */
    background-color: #007ced;
    color: #fff;
    padding: 10px 20px;
    border-radius: 11px;
    font-size: 16px;
    cursor: pointer;
    display: none;     /* Inicialmente oculto, controle via JS */
    z-index: 1000;
    text-decoration: none;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

/* Efeito de hover para ambos os botões */
#scroll-down-btn:hover,
#scroll-up-btn:hover {
    background-color: #005bb5;  /* Cor ao passar o mouse */
}

/* Ajustar alinhamento do cabeçalho "Aprovado" */
table th {
    text-align: center; /* Centraliza o texto nos cabeçalhos */
    vertical-align: middle; /* Alinha verticalmente ao centro */
    padding: 10px;
}

/* Ajustar alinhamento específico para o cabeçalho "Aprovado" */
table th:nth-child(4) { /* 4ª coluna: "Aprovado" */
    text-align: center; /* Alinha ao centro horizontalmente */
}

/* Aumentar o espaço para a coluna "Sugestão" */
table td:nth-child(3),
table th:nth-child(3) { /* 3ª coluna: "Sugestão" */
    max-width: 400px; /* Aumenta a largura máxima */
    min-width: 200px; /* Define uma largura mínima */
    overflow-wrap: break-word; /* Permite quebra de palavras longas */
    white-space: normal; /* Permite texto multilinhas */
}

/* Responsividade em telas menores */
@media (max-width: 768px) {
    table td:nth-child(3),
    table th:nth-child(3) {
        max-width: 100%; /* Ocupa toda a largura disponível em telas pequenas */
    }
}

.btn-novo {
    position: fixed;
    top: 20px;
    right: 70px;
    z-index: 1000;
    background-color: #007BFF;
    color: #fff !important;
    padding: 10px 15px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: bold;
    transition: background-color 0.3s;
}
.btn-novo:hover {
    background-color: #0056b3;
}


</style>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <a href="https://tgameajuda.com/#sugestoes" class="btn-voltar">
        <i class="fas fa-arrow-left"></i> Voltar
    </a>
  <a href="https://tgameajuda.com/tgasugestoes/sugestoes.html" class="btn-novo">
    <i class="fas fa-plus"></i> Novo
</a>

</div>





    <div class="container">
        <h1>Sugestões Enviadas</h1>
<form method="GET" style="margin-bottom: 20px; background-color: #222;">
    <select name="aprovado" id="aprovado" onchange="this.form.submit()" style="
        padding: 6px 14px;
        border-radius: 20px;
        border: none;
        font-size: 13px;
      background-color: #111;
  color: #fff;
        cursor: pointer;
        appearance: none;
        outline: none;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
        transition: all 0.3s ease;
    ">
        <option value="">Todas</option>
 <option value="nao" <?= (isset($_GET['aprovado']) && $_GET['aprovado'] === 'nao') ? 'selected' : '' ?>>Em Análise</option>
  <option value="sim" <?= (isset($_GET['aprovado']) && $_GET['aprovado'] === 'sim') ? 'selected' : '' ?>>Aprovado</option>
        <option value="em analise" <?= (isset($_GET['aprovado']) && $_GET['aprovado'] === 'em analise') ? 'selected' : '' ?>>Não Aprovado</option>
    </select>
</form>






        <?php if ($sugestoes): ?>
            <table id="suggestions-table">
                <thead>
                    <tr>
<th>ID</th> <!-- Adicionando ID na tabela -->

                        <th>Nome</th>
                        <th>Sugestão</th>
                        <th id="sort-aprovado" style="cursor: pointer;">Aprovado</th>
                        <th>Versão</th>
                        <th id="sort-date" style="cursor: pointer;">Data <i class="fas fa-sort"></i></th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sugestoes as $sugestao): ?> 
                        <tr>
<td><?php echo htmlspecialchars($sugestao['id']); ?></td> <!-- Exibindo ID -->
                            <td><?php echo htmlspecialchars($sugestao['nome']); ?></td>
                           <td><?php echo htmlspecialchars($sugestao['resumo']); ?></td>

                            <td>
                                <?php 
                                    // Verifica o valor da coluna "aprovado" e exibe "Aprovado", "Não Aprovado" ou "Em Análise"
                                    $aprovado = htmlspecialchars($sugestao['aprovado']);
                                    if ($aprovado == 'sim') {
                                        echo 'SIM';
                                    } elseif ($aprovado == 'nao') {
                                        echo 'Não';
                                    } else {
                                        echo 'Em Análise'; // Exibe "Em Análise" caso o valor não seja definido ou esteja vazio
                                    }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($sugestao['versao']); ?></td>
                            <td><?php echo htmlspecialchars($sugestao['data_criacao']); ?></td>
                            <td><a href="login.php?id=<?php echo $sugestao['id']; ?>"><i class="fas fa-edit"></i> Editar</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-data">Não há sugestões para mostrar.</p>
        <?php endif; ?>


    </div>
<!-- Botão para descer até o final -->
<button id="scroll-down-btn" onclick="scrollToBottom()">⬇️</button>
<!-- Botão para voltar ao topo -->
<button id="scroll-up-btn" onclick="scrollToTop()">⬆️</button>


    <!-- Arquivo JS -->
    <script>
 
        // Ordenar a tabela por data ao clicar no cabeçalho "Data"
        let sortDirection = false; // false para DESC, true para ASC
        document.getElementById("sort-date").addEventListener("click", function() {
            let rows = Array.from(document.querySelectorAll("table tbody tr"));
            rows.sort((a, b) => {
                const dateA = new Date(a.cells[4].textContent);
                const dateB = new Date(b.cells[4].textContent);
                return sortDirection ? dateA - dateB : dateB - dateA;
            });

            // Remover as linhas antigas e adicionar as ordenadas
            const tbody = document.querySelector("table tbody");
            tbody.innerHTML = "";
            rows.forEach(row => tbody.appendChild(row));

            // Alternar a direção da ordenação
            sortDirection = !sortDirection;

            // Alterar o ícone para refletir a ordem
            const icon = document.querySelector("#sort-date i");
            icon.className = sortDirection ? "fas fa-sort-up" : "fas fa-sort-down";
        });

// Ordenar a tabela por "Aprovado" ao clicar no cabeçalho
let aprovadoSortDirection = false; // false para "Em Análise" primeiro, depois "SIM", depois "NÃO"
document.getElementById("sort-aprovado").addEventListener("click", function() {
    let rows = Array.from(document.querySelectorAll("table tbody tr"));
    rows.sort((a, b) => {
        const aprovadoA = a.cells[2].textContent.trim(); // Ajuste conforme a coluna de "Aprovado"
        const aprovadoB = b.cells[2].textContent.trim();
        if (aprovadoA === "Em Análise" && aprovadoB !== "Em Análise") {
            return -1;
        } else if (aprovadoA === "SIM" && aprovadoB !== "Em Análise" && aprovadoB !== "SIM") {
            return -1;
        } else if (aprovadoA === "NÃO") {
            return 1;
        }
        return aprovadoSortDirection ? 1 : -1;
    });

    const tbody = document.querySelector("table tbody");
    tbody.innerHTML = "";
    rows.forEach(row => tbody.appendChild(row));

    aprovadoSortDirection = !aprovadoSortDirection; // Alterna a direção da ordenação
});


// Função para rolar até o topo
function scrollToTop() {
    // Garante que a rolagem vá até o topo da página
    document.documentElement.scrollTop = 0;
    document.body.scrollTop = 0; // Para compatibilidade com o Safari
}

// Função para rolar até o final
function scrollToBottom() {
    document.documentElement.scrollTop = document.body.scrollHeight; 
    document.body.scrollTop = document.body.scrollHeight;
}

// Mostrar o botão de voltar ao topo quando rolar para baixo
window.onscroll = function() {
    const scrollUpBtn = document.getElementById("scroll-up-btn");
    const scrollDownBtn = document.getElementById("scroll-down-btn");

    // Exibe o botão de voltar ao topo quando o usuário descer da página
    if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) {
        scrollUpBtn.style.display = "block"; // Mostra o botão de "Voltar ao topo"
    } else {
        scrollUpBtn.style.display = "none"; // Esconde o botão de "Voltar ao topo"
    }

    // Exibe o botão de descer até o final da página
    if (document.body.scrollHeight - window.innerHeight === window.scrollY) {
        scrollDownBtn.style.display = "none"; // Esconde o botão "descer" quando estiver no final
    } else {
        scrollDownBtn.style.display = "block"; // Mostra o botão de "descer"
    }
};

  document.getElementById('mostrar_aprovadas').addEventListener('change', function () {
    const aprovado = this.checked ? 'sim' : '';
    window.location.href = 'sugestoes.html?aprovado=' + aprovado;
  });

/*atualiza a pagina a cada 1min*/
  setInterval(function() {
    location.reload();
  }, 60000); // 60.000 milissegundos = 1 minuto


    </script>
<!--
<img src="https://profile-counter.glitch.me/tgahelpme-consultasugestaophp/count.svg" alt="Contador de Visitantes" style="border: 2px solid; border-radius: 8px; background: transparent; padding: 5px;">
-->
<div style="display: flex; justify-content: center; margin: 10px 0;">
  <img alt="visitas" src="https://hits.sh/tgameajuda.com/consultasugestaophp.html.svg?color=007ced&label=visitas&labelColor=FFFFFF&logo=https%3A%2F%2Fraw.githubusercontent.com%2Fsouzaseven%2Ftgahelpme%2FDesafios%2Ficon%2520bot%2520tga.ico"/>
</div>
</body>
</html>