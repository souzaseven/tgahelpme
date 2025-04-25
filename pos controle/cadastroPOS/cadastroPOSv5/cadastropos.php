<?php
session_start();

// Tempo máximo de inatividade
$tempo_maximo_inatividade = 300; // 5 minutos

if (!isset($_SESSION['usuario_logado']) || !isset($_SESSION['ultimo_acesso'])) {
    header('Location: login.html?erro=timeout');
    exit;
}

$tempo_inativo = time() - $_SESSION['ultimo_acesso'];

if ($tempo_inativo > $tempo_maximo_inatividade) {
    session_unset();
    session_destroy();
    header('Location: login.html?erro=timeout');
    exit;
}

$tempo_restante = $tempo_maximo_inatividade - $tempo_inativo;

// NÃO atualiza o tempo aqui, pois queremos forçar logout ao fim do contador
// Só se o usuário fizer uma ação nova (ex: requisição, clique etc)

// Enviar o tempo restante para o JS
echo "<script>var tempoRestanteSessao = $tempo_restante;</script>";
?>





<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">

    <title>Consulta de Tickets Smarts</title>
    <link rel="icon" href="./icon bot tga.ico" type="image/x-icon">

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">


<link rel="icon" href="https://github.com/souzaseven/tgahelpme/blob/Desafios/pos%20controle/cadastroPOS/tgaicosmart.png?raw=true" type="image/x-icon">
<!--icone maquina cartao-->

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
<!--souza system-->
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044"
     crossorigin="anonymous"></script>

<!--meajudatga-->

<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-S8EC5C2WTG"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-S8EC5C2WTG');
</script>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <link rel="stylesheet" href="styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head>
<body>
<div id="session-timer" style="text-align: right; margin: 10px 20px; font-weight: bold; color: #fff;"></div>
<div id="session-expired-msg" style="display: none; position: fixed; top: 10px; right: 10px; background: #e74c3c; color: white; padding: 10px 20px; border-radius: 6px; font-weight: bold; z-index: 9999;">
    Sessão expirada. Redirecionando para o login...
</div>



    <div class="container">
        <div class="search-container">
            <input id="searchInput" type="text" placeholder="Pesquisar em todos os campos..." oninput="filterTickets()">
  <button class="btn btn-primary" onclick="openModal()">
                <i class="fas fa-plus"></i> Novo Ticket
            </button>

<div class="custom-select-wrapper">
    <div class="custom-select-trigger" onclick="toggleStatusDropdown()">Status</div>
    <div class="custom-options" id="statusDropdown">
        <label><input type="checkbox" class="status-checkbox" value="todos" checked onchange="toggleAllStatuses()"> Todos</label>
        <label><input type="checkbox" class="status-checkbox" value="pendente" checked onchange="filterTickets()"> Pendente</label>
        <label><input type="checkbox" class="status-checkbox" value="em atendimento" checked onchange="filterTickets()"> Em Atendimento</label>
        <label><input type="checkbox" class="status-checkbox" value="cancelado" checked onchange="filterTickets()"> Cancelado</label>
        <label><input type="checkbox" class="status-checkbox" value="finalizado" checked onchange="filterTickets()"> Finalizado</label>
    </div>
</div>


          
            <button class="btn btn-export" onclick="exportToExcel()">
                <i class="fas fa-file-excel"></i> Exportar Relatório
            </button>
            <button id="darkModeBtn" class="btn">
                <i class="fas fa-moon"></i> Modo Escuro
            </button>

        </div>

        <div id="errorMessage" class="error-message"></div>

        <div class="table-container">
            <div class="scrollable-container">
                <table class="tickets-table" id="ticketsTable">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th style="width: 80px;">Ticket</th>
                            <th style="width: 120px;">Empresa</th>
                            <th style="width: 120px;">CNPJ</th>
                            <th style="width: 100px;">Código</th>
                            <th style="width: 60px;">Qtd.</th>
                            <th style="width: 100px;">Adquirente</th>
                            <th style="width: 100px;">Modelo</th>
                            <th style="width: 150px;">Solicitação</th>
                            <th style="width: 150px;">Anotações</th>
                            <th style="width: 100px;">Monitor</th>
                            <th style="width: 100px;">Status</th>
                            <th style="width: 120px;">Criação</th>
                            <th style="width: 120px;">Atualização</th>
                            <th style="width: 80px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="ticketsList"></tbody>
                </table>
            </div>
        </div>
    </div>

    <button id="scrollToTopBtn" class="scroll-to-top-button">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Modal para novo ticket -->
    <div id="ticketModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Novo Ticket</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="ticketForm">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="ticketNumber">Número do Ticket*</label>
                            <input type="text" id="ticketNumber" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="empresa">Empresa*</label>
                            <input type="text" id="empresa" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="cnpj">CNPJ</label>
                            <input type="text" id="cnpj" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="codCliente">Código Cliente</label>
                            <input type="text" id="codCliente" class="form-control">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="quantSmart">Quantidade</label>
                            <input type="number" id="quantSmart" class="form-control" min="0" value="0">
                        </div>
                        <div class="form-group">
                            <label for="adquirente">Adquirente*</label>
                         <select id="adquirente" class="form-control" required>
    <option value="">Selecione</option>
    <!-- Opções serão preenchidas via JavaScript -->
</select>
                        </div>
                        <div class="form-group">
                            <label for="modelo">Modelo*</label>
                         <select id="modelo" class="form-control" required>
    <option value="">Selecione a Adquirente primeiro</option>
</select>
                        </div>
                        <div class="form-group">
                            <label for="solicitacao">Solicitação*</label>
                            <input type="text" id="solicitacao" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="anotacao">Anotações</label>
                        <textarea id="anotacao" class="form-control"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="monitor">Monitor</label>
                        <input type="text" id="monitor" class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" class="form-control" required>
                            <option value="pendente">Pendente</option>
                            <option value="em atendimento">Em Atendimento</option>
                            <option value="cancelado">Cancelado</option>
                            <option value="finalizado">Finalizado</option>
                        </select>
                    </div>
<div class="form-group">
  <label for="imagemAnexo">Anexar Imagem (opcional)</label>
  <input type="file" id="imagemAnexo" name="imagemAnexo" class="form-control" accept="image/*" onchange="previewImagem(this, 'previewImagem')">
  <img id="previewImagem" style="max-width: 80px; margin-top: 10px; display: none; cursor: pointer;" onclick="expandirImagem(this)">
</div>



                    <div class="form-actions">
                        <button type="button" class="btn" onclick="closeModal()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar Ticket</button>
                    </div>
                </form>
                <div id="formMessage"></div>
            </div>
        </div>
    </div>

<!-- Modal para edição de ticket -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Editar Ticket</h2>
            <button class="close-btn" onclick="closeEditModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="editForm" enctype="multipart/form-data" method="post">
                <!-- Campo oculto com o ID do ticket -->
                <input type="hidden" id="editId" name="id">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="editTicket">Número do Ticket*</label>
                        <input type="text" id="editTicket" name="ticket" class="form-control" required>

                    </div>
                    <div class="form-group">
                        <label for="editEmpresa">Empresa*</label>
                        <input type="text" id="editEmpresa" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="editCnpj">CNPJ</label>
                        <input type="text" id="editCnpj" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="editCodCliente">Código Cliente</label>
                        <input type="text" id="editCodCliente" class="form-control">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="editQuant">Quantidade</label>
                        <input type="number" id="editQuant" class="form-control" min="0">
                    </div>
                    <div class="form-group">
                        <label for="editAdquirente">Adquirente*</label>
                        <select id="editAdquirente" class="form-control" required></select>
                    </div>
                    <div class="form-group">
                        <label for="editModelo">Modelo*</label>
                        <select id="editModelo" class="form-control" required>
                            <option value="">Selecione a Adquirente primeiro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editSolicitacao">Solicitação*</label>
                        <input type="text" id="editSolicitacao" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="editAnotacao">Anotações</label>
                    <textarea id="editAnotacao" class="form-control"></textarea>
                </div>

                <div class="form-group">
                    <label for="editMonitor">Monitor</label>
                    <input type="text" id="editMonitor" class="form-control">
                </div>

                <div class="form-group">
                    <label for="editStatus">Status</label>
                    <select id="editStatus" class="form-control" required>
                        <option value="pendente">Pendente</option>
                        <option value="em atendimento">Em Atendimento</option>
                        <option value="cancelado">Cancelado</option>
                        <option value="finalizado">Finalizado</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="editImagemAnexo">Anexar Imagem (opcional)</label>
                    <input type="file" id="editImagemAnexo" name="editImagemAnexo" class="form-control" accept="image/*" onchange="previewImagem(this, 'previewImagemEdit')">
                    <img id="previewImagemEdit" style="max-width: 80px; margin-top: 10px; display: none; cursor: pointer;" onclick="expandirImagem(this)">
                </div>

                <div class="form-actions">
                    <button type="button" class="btn" onclick="closeEditModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                </div>
            </form>
            <div id="editMessage"></div>
        </div>
    </div>
</div>





                    <div class="form-actions">
                        <button type="button" class="btn" onclick="closeEditModal()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                    </div>
                </form>
                <div id="editMessage"></div>
            </div>
        </div>
<button id="scrollToTopBtn" style="display: none;">↑ Topo</button>

    </div>

    <script src="./script.js"></script>
    <script src="https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js"></script>

<script>
let tempo = tempoRestanteSessao;
const timerDiv = document.getElementById('session-timer');
const expMsg = document.getElementById('session-expired-msg');

function formatarTempo(segundos) {
    const min = Math.floor(segundos / 60).toString().padStart(2, '0');
    const seg = (segundos % 60).toString().padStart(2, '0');
    return `${min}:${seg}`;
}

const intervalo = setInterval(() => {
    tempo--;
    if (tempo <= 0) {
        clearInterval(intervalo);
        timerDiv.textContent = 'Sessão expirada';
        expMsg.style.display = 'block';
        setTimeout(() => {
            window.location.href = 'login.html?erro=timeout';
        }, 3000); // Espera 3s para o usuário ver
    } else {
        timerDiv.textContent = `Sessão expira em: ${formatarTempo(tempo)}`;
    }
}, 1000);
</script>


<!-- Visualização em tela cheia -->
<div id="overlayImagem" style="display: none; position: fixed; top: 0; left: 0; 
    width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); 
    justify-content: center; align-items: center; z-index: 9999;">
  <img id="imagemExpandida" src="" style="max-width: 90%; max-height: 90%; border-radius: 10px; box-shadow: 0 0 20px black;">
</div>

<script>
function previewImagem(input, idPreview) {
    const file = input.files[0];
    const preview = document.getElementById(idPreview);

    if (file && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        preview.src = '';
        preview.style.display = 'none';
    }
}

function expandirImagem(img) {
    const overlay = document.getElementById('overlayImagem');
    const expandida = document.getElementById('imagemExpandida');
    expandida.src = img.src;
    overlay.style.display = 'flex';
}

// Fechar o overlay ao clicar nele
document.getElementById('overlayImagem').addEventListener('click', function () {
    this.style.display = 'none';
});
</script>

</body>
</html>