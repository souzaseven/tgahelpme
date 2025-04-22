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

let allTickets = [];
let darkMode = false;

function populateAdquirenteSelect() {
    const adquirenteSelect = document.getElementById('adquirente');
    const editAdquirenteSelect = document.getElementById('editAdquirente');
    
    // Limpa os selects
    adquirenteSelect.innerHTML = '<option value="">Selecione</option>';
    editAdquirenteSelect.innerHTML = '<option value="">Selecione</option>';
    
    // Adiciona todas as opções de adquirentes
    Object.keys(modelosPorAdquirente).forEach(adquirente => {
        const option = document.createElement('option');
        option.value = adquirente;
        option.textContent = adquirente;
        adquirenteSelect.appendChild(option.cloneNode(true));
        editAdquirenteSelect.appendChild(option);
    });
}

async function carregarTickets() {
    const errorMessage = document.getElementById('errorMessage');
    try {
        const response = await fetch(`./tickets.php?_=${Date.now()}`);
        if (!response.ok) throw new Error(`Erro HTTP: ${response.status}`);

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

function initializeModeloSelects() {
    const modeloSelect = document.getElementById('modelo');
    const editModeloSelect = document.getElementById('editModelo');
    
    modeloSelect.innerHTML = '<option value="">Selecione a Adquirente primeiro</option>';
    editModeloSelect.innerHTML = '<option value="">Selecione a Adquirente primeiro</option>';
}

function renderTickets(tickets) {
    const ticketsList = document.getElementById('ticketsList');
    ticketsList.innerHTML = '';

    tickets.forEach(ticket => {
        const row = document.createElement('tr');
        row.dataset.id = ticket.id;

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
            <td><span class="status-badge ${getStatusClass(ticket.status)}">${ticket.status}</span></td>
            <td>${formatDate(ticket.data_criacao)}</td>
            <td>${formatDate(ticket.data_atualizacao)}</td>
            <td><button class="edit-btn" onclick="openEditModal(${ticket.id})"><i class="fas fa-edit"></i> Editar</button></td>
        `;

        ticketsList.appendChild(row);
    });
}

function getStatusClass(status) {
    return 'status-' + status.toLowerCase().replace(/\s/g, '-');
}

function toggleDarkMode() {
    darkMode = !darkMode;
    document.body.classList.toggle('dark-mode');
    localStorage.setItem('darkMode', darkMode);
    const darkModeBtn = document.getElementById('darkModeBtn');
    darkModeBtn.innerHTML = darkMode ? '<i class="fas fa-sun"></i> Modo Claro' : '<i class="fas fa-moon"></i> Modo Escuro';
}
/*
function exportToExcel() {
    const rows = document.querySelectorAll('#ticketsList tr');
    if (rows.length === 0) {
        alert('Nenhum dado para exportar!');
        return;
    }

    const data = [
        ['ID', 'Ticket', 'Empresa', 'CNPJ', 'Código Cliente', 'Quantidade', 'Adquirente', 'Modelo', 'Solicitação', 'Anotações', 'Monitor', 'Status', 'Criação', 'Atualização']
    ];

    rows.forEach(row => {
        // Verifica se a linha está visível
        if (row.style.display === 'none') return;

        const cells = row.querySelectorAll('td');
        data.push([
            cells[0].textContent, // ID
            cells[1].textContent, // Ticket
            cells[2].textContent, // Empresa
            cells[3].textContent, // CNPJ
            cells[4].textContent, // Código Cliente
            cells[5].textContent, // Quantidade
            cells[6].textContent, // Adquirente
            cells[7].textContent, // Modelo
            cells[8].textContent, // Solicitação
            cells[9].textContent, // Anotações
            cells[10].textContent, // Monitor
            cells[11].textContent, // Status
            cells[12].textContent, // Criação
            cells[13].textContent  // Atualização
        ]);
    });

    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(data);
    XLSX.utils.book_append_sheet(wb, ws, "Tickets");
    XLSX.writeFile(wb, 'relatorio_tickets.xlsx');
}
*/
function exportToExcel() {
    const rows = document.querySelectorAll('#ticketsList tr');
    if (rows.length === 0) {
        alert('Nenhum dado para exportar!');
        return;
    }

    const data = [
        ['ID', 'Ticket', 'Empresa', 'CNPJ', 'Código Cliente', 'Quantidade', 'Adquirente', 'Modelo', 'Solicitação', 'Anotações', 'Monitor', 'Status', 'Criação', 'Atualização']
    ];

    rows.forEach(row => {
        if (row.style.display === 'none') return;

        const cells = row.querySelectorAll('td');
        data.push([
            cells[0].textContent,
            cells[1].textContent,
            cells[2].textContent,
            cells[3].textContent,
            cells[4].textContent,
            cells[5].textContent,
            cells[6].textContent,
            cells[7].textContent,
            cells[8].textContent,
            cells[9].textContent,
            cells[10].textContent,
            cells[11].textContent,
            cells[12].textContent,
            cells[13].textContent
        ]);
    });

    // Obter os status selecionados
    const selectedStatuses = Array.from(document.querySelectorAll('.status-checkbox:not([value="todos"])'))
        .filter(cb => cb.checked)
        .map(cb => cb.value.charAt(0).toUpperCase() + cb.value.slice(1));

    let statusLabel = 'Todos';
    if (selectedStatuses.length > 0 && selectedStatuses.length < 4) {
        statusLabel = selectedStatuses.join(', ');
    }

    const fileName = `relatorio_tickets (${statusLabel}).xlsx`;

    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(data);
    XLSX.utils.book_append_sheet(wb, ws, "Tickets");
    XLSX.writeFile(wb, fileName);
}


function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    return date.toLocaleString('pt-BR', {
        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
    });
}

function createStatusFilterCheckboxes() {
    const searchContainer = document.querySelector('.search-container');

    const container = document.createElement('div');
    container.id = 'statusFilterContainer';
    container.className = 'form-control';
    container.style.display = 'flex';
    container.style.flexWrap = 'wrap';
    container.style.gap = '10px';
    container.style.alignItems = 'center';
    container.style.padding = '10px';

    const statusList = ['pendente', 'em atendimento', 'cancelado', 'finalizado'];

    statusList.forEach(status => {
        const label = document.createElement('label');
        label.style.display = 'flex';
        label.style.alignItems = 'center';
        label.style.gap = '5px';

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.value = status;
        checkbox.name = 'statusFilter';
        checkbox.addEventListener('change', filterTickets);

        label.appendChild(checkbox);
        label.appendChild(document.createTextNode(status.charAt(0).toUpperCase() + status.slice(1)));

        container.appendChild(label);
    });

    searchContainer.insertBefore(container, document.getElementById('searchInput'));
}


function filterTickets() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const checkedStatus = Array.from(document.querySelectorAll('input[name="statusFilter"]:checked'))
        .map(cb => cb.value.toLowerCase());

    const filtered = allTickets.filter(ticket => {
        const matchesSearch = Object.values(ticket).some(value => {
            if (value === null || value === undefined) return false;
            return String(value).toLowerCase().includes(searchTerm);
        });

        const matchesStatus = checkedStatus.length === 0 || (ticket.status && checkedStatus.includes(ticket.status.toLowerCase()));
        return matchesSearch && matchesStatus;
    });

    renderTickets(filtered);
}



function setupScrollButton() {
    window.onscroll = function () {
        const scrollBtn = document.getElementById('scrollToTopBtn');
        scrollBtn.style.display = (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) ? "flex" : "none";
    };

    document.getElementById('scrollToTopBtn').addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

function openModal() {
    document.getElementById('ticketModal').style.display = 'flex';
    document.getElementById('ticketForm').reset();
    document.getElementById('formMessage').textContent = '';
    document.getElementById('formMessage').style.display = 'none';
    document.getElementById('adquirente').value = '';
    document.getElementById('modelo').innerHTML = '<option value="">Selecione a Adquirente primeiro</option>';
}

function closeModal() {
    document.getElementById('ticketModal').style.display = 'none';
}

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
    document.getElementById('editStatus').value = ticket.status;

    updateModelosSelect('editAdquirente', 'editModelo', ticket.adquirente, ticket.modelo);

    // Miniatura da imagem salva
    const previewEdit = document.getElementById('previewImagemEdit');
    if (ticket.imagem) {
        previewEdit.src = './uploads/' + ticket.imagem;
        previewEdit.style.display = 'block';
        previewEdit.onclick = function () {
            expandirImagem(previewEdit);
        };
    } else {
        previewEdit.style.display = 'none';
    }

    document.getElementById('editModal').style.display = 'flex';
    document.getElementById('editMessage').textContent = '';
    document.getElementById('editMessage').style.display = 'none';
}



function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function updateModelosSelect(adquirenteId, modeloId, adquirenteSelecionada = '', modeloSelecionado = '') {
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
    } else {
        modeloSelect.innerHTML = '<option value="">Nenhum modelo disponível</option>';
    }
}

document.getElementById('adquirente').addEventListener('change', function() {
    updateModelosSelect('adquirente', 'modelo', this.value);
});

document.getElementById('editAdquirente').addEventListener('change', function() {
    updateModelosSelect('editAdquirente', 'editModelo', this.value);
});
/*
document.getElementById('ticketForm').addEventListener('submit', async function (e) {
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
        status: document.getElementById('status').value
    };

    try {
        const response = await fetch('./salvar_ticket.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });

        const result = await response.json();
        const formMessage = document.getElementById('formMessage');
        formMessage.style.display = 'block';

        if (result.success) {
            formMessage.style.color = 'green';
            formMessage.textContent = 'Ticket salvo com sucesso!';
            formMessage.style.backgroundColor = '#d4edda';
            setTimeout(() => { closeModal(); carregarTickets(); }, 1000);
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
});*/
document.getElementById('ticketForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const formData = new FormData();
    formData.append('ticket', document.getElementById('ticketNumber').value);
    formData.append('empresa', document.getElementById('empresa').value);
    formData.append('cnpj', document.getElementById('cnpj').value);
    formData.append('cod_cliente_tga', document.getElementById('codCliente').value);
    formData.append('quant_smart', document.getElementById('quantSmart').value);
    formData.append('adquirente', document.getElementById('adquirente').value);
    formData.append('modelo', document.getElementById('modelo').value);
    formData.append('solicitacao', document.getElementById('solicitacao').value);
    formData.append('anotacao', document.getElementById('anotacao').value);
    formData.append('monitor', document.getElementById('monitor').value);
    formData.append('status', document.getElementById('status').value);

    const imagem = document.getElementById('imagemAnexo').files[0];
    if (imagem) {
        formData.append('imagemAnexo', imagem);
    }

    try {
        const response = await fetch('./salvar_ticket.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        const formMessage = document.getElementById('formMessage');
        formMessage.style.display = 'block';

        if (result.success) {
            formMessage.style.color = 'green';
            formMessage.textContent = 'Ticket salvo com sucesso!';
            formMessage.style.backgroundColor = '#d4edda';
            setTimeout(() => { closeModal(); carregarTickets(); }, 1000);
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

document.getElementById('editForm').addEventListener('submit', async function (e) {
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
        status: document.getElementById('editStatus').value
    };

    try {
        const response = await fetch('./atualizar_ticket.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });

        const result = await response.json();
        const editMessage = document.getElementById('editMessage');
        editMessage.style.display = 'block';

        if (result.success) {
            editMessage.style.color = 'green';
            editMessage.textContent = 'Ticket atualizado com sucesso!';
            editMessage.style.backgroundColor = '#d4edda';
            setTimeout(() => { closeEditModal(); carregarTickets(); }, 1000);
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

document.addEventListener('DOMContentLoaded', function () {
    darkMode = localStorage.getItem('darkMode') === 'true';
    if (darkMode) {
        document.body.classList.add('dark-mode');
        document.getElementById('darkModeBtn').innerHTML = '<i class="fas fa-sun"></i> Modo Claro';
    }
    
    populateAdquirenteSelect();
    initializeModeloSelects(); // Adicionar esta linha
    carregarTickets();
    document.getElementById('darkModeBtn').addEventListener('click', toggleDarkMode);
    document.getElementById('exportExcelBtn').addEventListener('click', exportToExcel);
    createStatusFilterCheckboxes();
});

window.addEventListener('click', function (event) {
    if (event.target === document.getElementById('ticketModal')) closeModal();
    if (event.target === document.getElementById('editModal')) closeEditModal();
});

function setupScrollButton() {
    const scrollBtn = document.getElementById('scrollToTopBtn');
    if (!scrollBtn) return; // <-- evita erro se o botão não existir

    window.onscroll = function () {
        scrollBtn.style.display = (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) ? "flex" : "none";
    };

    scrollBtn.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

function toggleStatusDropdown() {
    const dropdown = document.getElementById('statusDropdown');
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}

function toggleAllStatuses() {
    const allCheckbox = document.querySelector('.status-checkbox[value="todos"]');
    const statusCheckboxes = document.querySelectorAll('.status-checkbox:not([value="todos"])');

    statusCheckboxes.forEach(cb => cb.checked = allCheckbox.checked);
    filterTickets();
}

function filterTickets() {
    const searchText = document.getElementById('searchInput').value.toLowerCase();
    const checkboxes = document.querySelectorAll('.status-checkbox:not([value="todos"])');
    const selectedStatuses = Array.from(checkboxes)
        .filter(cb => cb.checked)
        .map(cb => cb.value);

    // Se todos estiverem selecionados, marca a opção "Todos"
    const allCheckbox = document.querySelector('.status-checkbox[value="todos"]');
    allCheckbox.checked = checkboxes.length === selectedStatuses.length;

    const rows = document.querySelectorAll('#ticketsList tr');

    rows.forEach(row => {
        const rowText = row.textContent.toLowerCase();
        const status = row.querySelector('td:nth-child(12)')?.textContent.toLowerCase();

        const matchesSearch = rowText.includes(searchText);
        const matchesStatus = selectedStatuses.includes(status);

        row.style.display = matchesSearch && matchesStatus ? '' : 'none';
    });
}

function toggleStatusDropdown() {
    const dropdown = document.getElementById('statusDropdown');
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}

// Fecha o dropdown ao clicar fora
document.addEventListener('click', function (event) {
    const wrapper = document.querySelector('.custom-select-wrapper');
    if (!wrapper.contains(event.target)) {
        document.getElementById('statusDropdown').style.display = 'none';
    }
});

function toggleAllStatuses() {
    const allCheckbox = document.querySelector('.status-checkbox[value="todos"]');
    const statusCheckboxes = document.querySelectorAll('.status-checkbox:not([value="todos"])');

    statusCheckboxes.forEach(cb => cb.checked = allCheckbox.checked);
    filterTickets();
}

function filterTickets() {
    const searchText = document.getElementById('searchInput').value.toLowerCase();
    const checkboxes = document.querySelectorAll('.status-checkbox:not([value="todos"])');
    const selectedStatuses = Array.from(checkboxes)
        .filter(cb => cb.checked)
        .map(cb => cb.value);

    const allCheckbox = document.querySelector('.status-checkbox[value="todos"]');
    allCheckbox.checked = checkboxes.length === selectedStatuses.length;

    const rows = document.querySelectorAll('#ticketsList tr');

    rows.forEach(row => {
        const rowText = row.textContent.toLowerCase();
        const status = row.querySelector('td:nth-child(12)')?.textContent.toLowerCase();

        const matchesSearch = rowText.includes(searchText);
        const matchesStatus = selectedStatuses.includes(status);

        row.style.display = matchesSearch && matchesStatus ? '' : 'none';
    });
}
