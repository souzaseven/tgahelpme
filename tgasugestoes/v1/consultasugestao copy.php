<?php
// Conexão com o banco de dados
$host = '108.167.151.50';
$dbname = 'tgamea80_SUPORTE';
$user = 'tgamea80_tgamea80';
$password = 'anderson@2250';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Busca todas as sugestões no banco
    $stmt = $pdo->query("SELECT * FROM sugestoes ORDER BY data_criacao DESC");
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

<style>

/* Estilo geral da página */
body {
    font-family: 'Arial', sans-serif;
    background-color: #f0f0f0;
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    color: #333;
}

/* Container principal */
.container {
    width: 90%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* Cabeçalho */
h1 {
    color: #2c3e50;
    font-size: 2.2rem;
    margin-bottom: 20px;
}

/* Subtítulo */
h3 {
    color: #7f8c8d;
    font-size: 1.5rem;
    margin-bottom: 20px;
}

/* Estilo da tabela */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

/* Cabeçalho da tabela */
th {
    background-color: #3498db;
    color: #fff;
    padding: 12px;
    text-align: left;
    font-size: 1rem;
}

/* Células da tabela */
td {
    padding: 12px;
    border-bottom: 1px solid #ecf0f1;
    font-size: 1rem;
}

/* Hover na linha da tabela */
tr:hover {
    background-color: #ecf0f1;
}

/* Estilo dos botões */
button {
    padding: 10px 15px;
    border: none;
    background-color: #3498db;
    color: white;
    font-size: 1.2rem;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

button:hover {
    background-color: #2980b9;
}

/* Estilo do link de editar */
a {
    color: #3498db;
    font-size: 1rem;
    text-decoration: none;
    display: inline-block;
    margin-top: 5px;
    transition: color 0.3s ease;
}

a:hover {
    color: #2980b9;
}

/* Estilo do botão de voltar */
.btn-voltar {
    display: inline-block;
    background-color: #16a085;
    color: white;
    font-size: 1.2rem;
    padding: 10px 20px;
    border-radius: 5px;
    text-decoration: none;
    margin-bottom: 20px;
    transition: background-color 0.3s ease;
}

.btn-voltar:hover {
    background-color: #1abc9c;
}

/* Esconder botões quando desnecessário */
#scroll-up-btn,
#scroll-down-btn {
    display: none;
    position: fixed;
    bottom: 30px;
    right: 30px;
    background-color: #3498db;
    color: white;
    border-radius: 50%;
    padding: 10px;
    font-size: 1.5rem;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

#scroll-up-btn:hover,
#scroll-down-btn:hover {
    background-color: #2980b9;
}

/* Estilo para a classe "sem dados" */
.no-data {
    text-align: center;
    color: #e74c3c;
    font-size: 1.2rem;
    margin-top: 20px;
}

/* Responsividade */
@media (max-width: 768px) {
    /* Ajustar os cabeçalhos */
    h1 {
        font-size: 1.8rem;
    }
    h3 {
        font-size: 1.3rem;
    }

    /* Ajustar a largura da tabela */
    table {
        font-size: 0.9rem;
    }

    /* Estilo dos botões */
    button,
    .btn-voltar {
        font-size: 1rem;
        padding: 8px 12px;
    }
}

/* Efeitos de transição */
table td, table th {
    transition: all 0.3s ease;
}

table td:hover, table th:hover {
    background-color: #ecf0f1;
}

</style>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <a href="https://tgameajuda.com/#sugestoes" class="btn-voltar">
        <i class="fas fa-arrow-left"></i> Voltar
    </a>


 <div class="container">
    <h1>Sugestões Enviadas</h1>
<!-- Botão para descer até o final -->
<button id="scroll-down-btn" onclick="scrollToBottom()">⬇️</button>
<!-- Botão para voltar ao topo -->
<button id="scroll-up-btn" onclick="scrollToTop()">⬆️</button>



    <?php if ($sugestoes): ?>
        <table id="suggestions-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Sugestão</th>
                    <th>Aprovado</th>
                    <th>Versão</th>
                    <th id="sort-date" style="cursor: pointer;">Data <i class="fas fa-sort"></i></th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sugestoes as $sugestao): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($sugestao['nome']); ?></td>
                        <td><?php echo htmlspecialchars($sugestao['sugestao']); ?></td>
                        <td>
                            <?php 
                                $aprovado = htmlspecialchars($sugestao['aprovado']);
                                echo $aprovado === 'sim' ? 'SIM' : ($aprovado === 'nao' ? 'Não' : 'Em Análise');
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


    <!-- Arquivo JS -->
    <script>
        // Função para alternar entre modo claro e escuro
      /*  const themeToggle = document.getElementById("theme-toggle");
        themeToggle.addEventListener("click", () => {
            document.body.classList.toggle("dark-mode");
            themeToggle.textContent = document.body.classList.contains("dark-mode") ? "🌞" : "🌙";
        });*/

        // Função para mostrar o Toast de sucesso
      /*  function showToast() {
            const toast = document.getElementById('toast');
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3000);
      //  }

        // Exemplo de chamada para mostrar o toast de sucesso
        // Isso seria chamado após uma ação, como uma edição bem-sucedida
        showToast();*/

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

// Função para rolar até o topo
function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Função para rolar até o final
function scrollToBottom() {
    window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
}

// Verifica a posição de rolagem
window.onscroll = function() {
    const scrollUpBtn = document.getElementById("scroll-up-btn");
    const scrollDownBtn = document.getElementById("scroll-down-btn");

    // Mostra o botão de "Voltar ao topo" ao rolar para baixo
    if (window.scrollY > 100) {
        scrollUpBtn.style.display = "block";
    } else {
        scrollUpBtn.style.display = "none";
    }

    // Esconde o botão de "Descer ao final" quando chega ao final da página
    if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 10) {
        scrollDownBtn.style.display = "none";
    } else {
        scrollDownBtn.style.display = "block";
    }
};

    </script>
</body>
</html>
