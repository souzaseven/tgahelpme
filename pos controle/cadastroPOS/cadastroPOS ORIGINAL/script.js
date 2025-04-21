// Mapeamento de modelos por adquirente
const modelosPorAdquirente = {
    'REDE': ['POSITIVO L400', 'GPOS700'],
    'CIELO': ['LIO 2', 'LIO ON'],
    'GETNET': ['P2', 'N910', 'A8', 'AXIUM DX8000'],
    'SICREDI': ['AXIUM DX8000'],
    'STONE': ['A8', 'P2', 'L400'],
    'SIPAG': ['P2'],
    'PAGSEGURO': ['P2', 'A930'],
    'VERO': ['L300', 'GPOS 700', 'P2'],
    'SAFRAPAY': ['P2', 'A8', 'L300'],
    'CAIXA': ['AXIUM DX800'],
    'BIN': ['AXIUM DX8000'],
    'PUNTO': ['N910'],
    'JUSTA': ['L300'],
    'ELGIN': ['ELGINPOS'],
    'ADQ': ['A920'],
    'REDEFLEX': ['AXIUM DX8000'],
    'CLOVER': ['FLEX 405'],
    'PICPAY': ['P2']
};

// VariÃ¡veis globais
let allTickets = [];
let darkMode = false;

// Carregar tickets
async function carregarTickets() {
    const errorMessage = document.getElementById('errorMessage');
    try {
        const response = await fetch(`./tickets.php?_=${Date.now()}`);
        if (!response.ok) {
            throw new Error(`Erro HTTP: ${response.status}`);
        }

        try {
            allTickets = await response.json();
        } catch {
            throw new Error("Resposta invÃ¡lida do servidor");
        }

        const ticketsList = document.getElementById('ticketsList');
        ticketsList.innerHTML = '';
        errorMessage.style.display = 'none';

        if (allTickets.error) {
            errorMessage.textContent = allTickets.error;
            errorMessage.style.display = 'block';
            return;
        }

        if (allTickets.length === 0) {
            ticketsList.innerHTML = '<tr><td colspan="14" style="text-align:center">Nenhum ticket encontrado.</td></tr>';
            return;
        }

        renderTickets(allTickets);
        setupScrollButton();

    } catch (error) {
        errorMessage.textContent = `Erro ao carregar os tickets: ${error.message}`;
        errorMessage.style.display = 'block';
        console.error('Erro ao carregar os tickets:', error);
    }
}

// Renderizar tickets na tabela
function renderTickets(tickets) {
    const ticketsList = document.getElementById('ticketsList');
    ticketsList.innerHTML = '';
    
    tickets.forEach(ticket => {
        const row = document.createElement('tr');
        row.dataset.id = ticket.id;
        
        // Status do ticket (finalizado)
        const statusText = ticket.finalizado == 1 ? 'Sim' : 'NÃ£o';
        const statusColor = ticket.finalizado == 1 ? 'color: green;' : 'color: red;';
        
        row.innerHTML = `
            <td>${ticket.id}</td>
            <td>${ticket.ticket}</td>
            <td>${ticket.empresa || '-'}</td>
            <td>${ticket.cnpj || '-'}</td>
            <td>${ticket.cod_cliente_tga || '-'}</td>
            <td>${ticket.quant_smart || '0'}</td>
            <td>${ticket.adquirente || '-'}</td>
            <td>${ticket.modelo || '-'}</td>
            <td>${ticket.solicitacao || '-'}</td>
            <td>${ticket.anotacao ? ticket.anotacao.substring(0, 30) + (ticket.anotacao.length > 30 ? '...' : '') : '-'}</td>
            <td>${ticket.monitor || '-'}</td>
          <td>
    <span class="status-badge ${ticket.finalizado == 1 ? 'status-finalizado' : 'status-pendente'}"
          onclick="toggleStatus(${ticket.id}, ${ticket.finalizado})">
        ${ticket.finalizado == 1 ? 'Sim' : 'Não'}
    </span>
</td>

            <td>${formatDate(ticket.data_criacao)}</td>
            <td>${formatDate(ticket.data_atualizacao)}</td>
            <td>
                <button class="edit-btn" onclick="openEditModal(${ticket.id})">
                    <i class="fas fa-edit"></i> Editar
                </button>
            </td>
        `;
        
        ticketsList.appendChild(row);
    });
}

// Alternar modo escuro
function toggleDarkMode() {
    darkMode = !darkMode;
    document.body.classList.toggle('dark-mode');
    localStorage.setItem('darkMode', darkMode);
    
    const darkModeBtn = document.getElementById('darkModeBtn');
    darkModeBtn.innerHTML = darkMode ? '<i class="fas fa-sun"></i> Modo Claro' : '<i class="fas fa-moon"></i> Modo Escuro';
}

// Exportar para Excel
function exportToExcel() {
    if (allTickets.length === 0) {
        alert('Nenhum dado para exportar!');
        return;
    }

    // Preparar dados para exportaÃ§Ã£o
    const data = [
        ['ID', 'Ticket', 'Empresa', 'CNPJ', 'CÃ³digo Cliente', 'Quantidade', 'Adquirente', 'Modelo', 'SolicitaÃ§Ã£o', 'AnotaÃ§Ãµes', 'Monitor', 'Finalizado', 'CriaÃ§Ã£o', 'AtualizaÃ§Ã£o']
    ];

    allTickets.forEach(ticket => {
        data.push([
            ticket.id,
            ticket.ticket,
            ticket.empresa || '',
            ticket.cnpj || '',
            ticket.cod_cliente_tga || '',
            ticket.quant_smart || 0,
            ticket.adquirente || '',
            ticket.modelo || '',
            ticket.solicitacao || '',
            ticket.anotacao || '',
            ticket.monitor || '',
            ticket.finalizado == 1 ? 'Sim' : 'NÃ£o',
            formatDate(ticket.data_criacao),
            formatDate(ticket.data_atualizacao)
        ]);
    });

    // Criar planilha
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(data);
    XLSX.utils.book_append_sheet(wb, ws, "Tickets");

    // Exportar arquivo
    XLSX.writeFile(wb, 'relatorio_tickets.xlsx');
}

// Formatar data
function formatDate(dateString) {
    if (!dateString) return '-';
    
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    
    return date.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Filtrar tickets
function filterTickets() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    
    if (!searchTerm) {
        renderTickets(allTickets);
        return;
    }
    
    const filteredTickets = allTickets.filter(ticket => {
        return Object.values(ticket).some(value => 
            String(value).toLowerCase().includes(searchTerm)
        );
    });
    
    renderTickets(filteredTickets);
}

// Configurar botÃ£o de scroll
function setupScrollButton() {
    window.onscroll = function() {
        const scrollBtn = document.getElementById('scrollToTopBtn');
        if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
            scrollBtn.style.display = "flex";
        } else {
            scrollBtn.style.display = "none";
        }
    };

    document.getElementById('scrollToTopBtn').addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
}

// Modal functions
function openModal() {
    document.getElementById('ticketModal').style.display = 'flex';
    document.getElementById('ticketForm').reset();
    document.getElementById('formMessage').textContent = '';
    document.getElementById('formMessage').style.display = 'none';
    
    // Resetar selects
    document.getElementById('adquirente').value = '';
    document.getElementById('modelo').innerHTML = '<option value="">Selecione a Adquirente primeiro</option>';
}

function closeModal() {
    document.getElementById('ticketModal').style.display = 'none';
}

// FunÃ§Ã£o para abrir modal de ediÃ§Ã£o
function openEditModal(ticketId) {
    const ticket = allTickets.find(t => t.id == ticketId);
    if (!ticket) return;

    document.getElementById('editId').value = ticket.id;
    document.getElementById('editTicket').value = ticket.ticket;
    document.getElementById('editEmpresa').value = ticket.empresa;
    document.getElementById('editCnpj').value = ticket.cnpj || '';
    document.getElementById('editCodCliente').value = ticket.cod_cliente_tga || '';
    document.getElementById('editQuant').value = ticket.quant_smart || 0;
    document.getElementById('editAdquirente').value = ticket.adquirente || '';
    document.getElementById('editSolicitacao').value = ticket.solicitacao;
    document.getElementById('editAnotacao').value = ticket.anotacao || '';
    document.getElementById('editMonitor').value = ticket.monitor || '';
   document.getElementById('editFinalizado').value = ticket.finalizado;


    // Atualiza modelos baseado na adquirente selecionada
    updateModelosSelect('editAdquirente', 'editModelo', ticket.adquirente, ticket.modelo);

    document.getElementById('editModal').style.display = 'flex';
    document.getElementById('editMessage').textContent = '';
    document.getElementById('editMessage').style.display = 'none';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Atualiza opÃ§Ãµes de modelo baseado na adquirente selecionada
function updateModelosSelect(adquirenteId, modeloId, adquirenteSelecionada = '', modeloSelecionado = '') {
    const adquirenteSelect = document.getElementById(adquirenteId);
    const modeloSelect = document.getElementById(modeloId);
    
    modeloSelect.innerHTML = '<option value="">Selecione</option>';
    
    if (adquirenteSelecionada && modelosPorAdquirente[adquirenteSelecionada]) {
        modelosPorAdquirente[adquirenteSelecionada].forEach(modelo => {
            const option = document.createElement('option');
            option.value = modelo;
            option.textContent = modelo;
            option.selected = (modelo === modeloSelecionado);
            modeloSelect.appendChild(option);
        });
    }
}

// Event listeners para adquirente
document.getElementById('adquirente').addEventListener('change', function() {
    updateModelosSelect('adquirente', 'modelo', this.value);
});

document.getElementById('editAdquirente').addEventListener('change', function() {
    updateModelosSelect('editAdquirente', 'editModelo', this.value);
});

// FunÃ§Ã£o para salvar novo ticket
document.getElementById('ticketForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = {
        ticket: document.getElementById('ticketNumber').value,
        empresa: document.getElementById('empresa').value,
        cnpj: document.getElementById('cnpj').value,
        cod_cliente_tga: document.getElementById('codCliente').value,
        quant_smart: document.getElementById('quantSmart').value,
        adquirente: document.getElementById('adquirente').value,
        modelo: document.getElementById('modelo').value,
        solicitacao: document.getElementById('solicitacao').value,
        anotacao: document.getElementById('anotacao').value,
        monitor: document.getElementById('monitor').value,
        finalizado: document.getElementById('finalizado').value
    };
    
    try {
        const response = await fetch('./salvar_ticket.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const result = await response.json();
        
        const formMessage = document.getElementById('formMessage');
        formMessage.style.display = 'block';
        
        if (result.success) {
            formMessage.style.color = 'green';
            formMessage.textContent = 'Ticket salvo com sucesso!';
            formMessage.style.backgroundColor = '#d4edda';
            
            // Atualiza a lista apÃ³s 1 segundo
            setTimeout(() => {
                closeModal();
                carregarTickets();
            }, 1000);
        } else {
            formMessage.style.color = '#721c24';
            formMessage.textContent = result.error || 'Erro ao salvar o ticket';
            formMessage.style.backgroundColor = '#f8d7da';
        }
    } catch (error) {
        const formMessage = document.getElementById('formMessage');
        formMessage.style.display = 'block';
        formMessage.style.color = '#721c24';
        formMessage.textContent = 'Erro ao conectar com o servidor: ' + error.message;
        formMessage.style.backgroundColor = '#f8d7da';
        console.error('Erro:', error);
    }
});

// FunÃ§Ã£o para salvar ediÃ§Ã£o
document.getElementById('editForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = {
        id: document.getElementById('editId').value,
        ticket: document.getElementById('editTicket').value,
        empresa: document.getElementById('editEmpresa').value,
        cnpj: document.getElementById('editCnpj').value,
        cod_cliente_tga: document.getElementById('editCodCliente').value,
        quant_smart: document.getElementById('editQuant').value,
        adquirente: document.getElementById('editAdquirente').value,
        modelo: document.getElementById('editModelo').value,
        solicitacao: document.getElementById('editSolicitacao').value,
        anotacao: document.getElementById('editAnotacao').value,
        monitor: document.getElementById('editMonitor').value,
       finalizado: document.getElementById('editFinalizado').value

    };
    
    try {
        const response = await fetch('./atualizar_ticket.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const result = await response.json();
        
        const editMessage = document.getElementById('editMessage');
        editMessage.style.display = 'block';
        
        if (result.success) {
            editMessage.style.color = 'green';
            editMessage.textContent = 'Ticket atualizado com sucesso!';
            editMessage.style.backgroundColor = '#d4edda';
            
            // Atualiza a lista apÃ³s 1 segundo
            setTimeout(() => {
                closeEditModal();
                carregarTickets();
            }, 1000);
        } else {
            editMessage.style.color = '#721c24';
            editMessage.textContent = result.error || 'Erro ao atualizar ticket';
            editMessage.style.backgroundColor = '#f8d7da';
        }
    } catch (error) {
        const editMessage = document.getElementById('editMessage');
        editMessage.style.display = 'block';
        editMessage.style.color = '#721c24';
        editMessage.textContent = 'Erro ao conectar com o servidor: ' + error.message;
        editMessage.style.backgroundColor = '#f8d7da';
        console.error('Erro:', error);
    }
});

// InicializaÃ§Ã£o
document.addEventListener('DOMContentLoaded', function() {
    // Verificar modo escuro no localStorage
    darkMode = localStorage.getItem('darkMode') === 'true';
    if (darkMode) {
        document.body.classList.add('dark-mode');
        document.getElementById('darkModeBtn').innerHTML = '<i class="fas fa-sun"></i> Modo Claro';
    }
    
    // Carregar tickets
    carregarTickets();
    
    // Configurar botÃ£o de modo escuro
    document.getElementById('darkModeBtn').addEventListener('click', toggleDarkMode);
    
    // Configurar botÃ£o de exportaÃ§Ã£o
    document.getElementById('exportExcelBtn').addEventListener('click', exportToExcel);
});

// Fechar modal ao clicar fora
window.addEventListener('click', function(event) {
    if (event.target === document.getElementById('ticketModal')) {
        closeModal();
    }
    if (event.target === document.getElementById('editModal')) {
        closeEditModal();
    }
});

async function toggleStatus(id, atual) {
    const novoStatus = atual == 1 ? 0 : 1;

    try {
        const response = await fetch('./atualizar_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, finalizado: novoStatus })
        });

        const result = await response.json();

        if (result.success) {
            // Pequeno delay para garantir tempo de resposta do backend
            setTimeout(() => {
                carregarTickets(); // Atualiza visual após confirmação
            }, 200);
        } else {
            alert(result.error || 'Erro ao atualizar status');
        }

    } catch (err) {
        console.error('Erro ao atualizar status:', err);
        alert('Erro de conexão com o servidor');
    }
}

tickets.forEach(ticket => {
    console.log(ticket); // ← veja o que vem do banco

    const row = document.createElement('tr');
  
});
