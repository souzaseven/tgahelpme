const newDevocionalBtn = document.getElementById('newDevocionalBtn');
const toggleDarkMode = document.getElementById('toggleDarkMode');
const modal = document.getElementById('modal');
const cancelarBtn = document.getElementById('cancelarBtn');
const salvarBtn = document.getElementById('salvarBtn');
const inputData = document.getElementById('inputData');
const inputTema = document.getElementById('inputTema');
const inputTexto = document.getElementById('inputTexto');
const infoDevocional = document.getElementById('infoDevocional');
const searchInput = document.getElementById('searchInput');
const historicoContainer = document.querySelector('.historico-container');
const inputMinistradoPor = document.getElementById('inputMinistradoPor');


// Verificação de elementos essenciais
if (!historicoContainer) {
  console.error('Elemento .historico-container não encontrado no DOM');
  // Pode adicionar criação dinâmica do elemento se necessário
}

let historicoDevocionais = [];
let devocionalEditando = null;

document.addEventListener('DOMContentLoaded', () => {
  document.body.classList.add('dark');
  localStorage.setItem('darkMode', 'enabled');
  carregarDevocionais();
document.querySelectorAll('input[name="ministradoPor"]').forEach(radio => {
  radio.addEventListener('change', () => {
    const outroSelecionado = document.getElementById('radioOutro').checked;
    const inputOutro = document.getElementById('inputMinistradoPor');
    inputOutro.disabled = !outroSelecionado;
    if (!outroSelecionado) {
      inputOutro.value = radio.value;
    } else {
      inputOutro.value = '';
      inputOutro.focus();
    }
  });
});

});

  
  // Verificar preferência de tema do usuário//
 /* if (localStorage.getItem('darkMode') === 'enabled') {
    document.body.classList.add('dark');
  }
});*/
document.body.classList.add('dark');
localStorage.setItem('darkMode', 'enabled');


// Event Listeners
newDevocionalBtn.addEventListener('click', () => {
  devocionalEditando = null;
  abrirModal();
});

cancelarBtn.addEventListener('click', fecharModal);

salvarBtn.addEventListener('click', salvarDevocional);

toggleDarkMode.addEventListener('click', alternarTema);

searchInput.addEventListener('input', atualizarHistorico);

// Funções principais
async function salvarDevocional() {
  const data = inputData.value;
  const tema = inputTema.value.trim();
  const texto = inputTexto.value.trim();
const ministradoPor = inputMinistradoPor.value.trim();
if (!data || !tema || !texto || !ministradoPor) {
  mostrarNotificacao('Preencha todos os campos.', 'warning');
  return;
}


  const formData = new FormData();
  formData.append('data', data);
  formData.append('tema', tema);
  formData.append('texto', texto);
formData.append('ministrado_por', ministradoPor);


  if (devocionalEditando) {
    formData.append('id', devocionalEditando.id);
  }

  try {
    const response = await fetch('salvar_devocional.php', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (result.success) {
      mostrarNotificacao('Devocional salvo com sucesso!', 'success');
      fecharModal();
      limparInputs();
      carregarDevocionais();
    } else {
      mostrarNotificacao(result.message, 'error');
    }
  } catch (error) {
    mostrarNotificacao('Erro ao salvar devocional.', 'error');
    console.error(error);
  }
}

function alternarTema() {
  document.body.classList.toggle('dark');
  localStorage.setItem('darkMode', 
    document.body.classList.contains('dark') ? 'enabled' : 'disabled');
}

// Funções de UI
function abrirModal() {
  modal.classList.remove('hidden');
  document.querySelector('.modal-content h2').textContent = 
    devocionalEditando ? 'Editar Devocional' : 'Novo Devocional';
  
  if (devocionalEditando) {
    inputTema.focus();
  } else {
    inputData.focus();
  }
}

function fecharModal() {
  modal.classList.add('hidden');
  limparInputs();
}

function limparInputs() {
  if (!devocionalEditando) {
    inputData.value = '';
inputMinistradoPor.value = '';

  }
  inputTema.value = '';
  inputTexto.value = '';
}

// Funções de dados
async function carregarDevocionais() {
  try {
    const response = await fetch('listar_devocionais.php');
    
    if (!response.ok) {
      throw new Error(`Erro HTTP: ${response.status}`);
    }
    
    const result = await response.json();

    if (result.success) {
      historicoDevocionais = Array.isArray(result.data) ? result.data : [];
      atualizarUltimoDevocional();
      atualizarHistorico();
    } else {
      mostrarNotificacao(result.message || 'Erro ao carregar devocionais', 'error');
    }
  } catch (error) {
    mostrarNotificacao('Erro ao carregar devocionais: ' + error.message, 'error');
    console.error('Erro:', error);
  }
}

function atualizarUltimoDevocional() {
  if (!infoDevocional) return;

  if (historicoDevocionais.length > 0) {
    const ultimo = historicoDevocionais[0];
    infoDevocional.innerHTML = `
      <h3>${escapeHTML(ultimo.tema)}</h3>
      <p><strong>${formatarData(ultimo.data)}</strong></p>

    <!-- <p>${escapeHTML(ultimo.texto)}</p> -->
      <p><em>Ministrado por: ${escapeHTML(ultimo.ministrado_por || '')}</em></p>
    `;
  } else {
    infoDevocional.textContent = 'Nenhum devocional cadastrado ainda.';
  }
}


function atualizarHistorico() {
  if (!historicoContainer) return;
  
  const filtro = searchInput ? searchInput.value.toLowerCase() : '';
  historicoContainer.innerHTML = '';

  if (!historicoDevocionais || historicoDevocionais.length === 0) {
    mostrarMensagemVazia('Nenhum devocional encontrado.');
    return;
  }

  const devocionaisAgrupados = agruparPorMes(historicoDevocionais, filtro);

  if (devocionaisAgrupados.length === 0) {
    mostrarMensagemVazia('Nenhum devocional encontrado para esta busca.');
    return;
  }

  devocionaisAgrupados.forEach(criarSecaoMes);
  configurarBotoesAcao();
}

// Funções auxiliares
function mostrarMensagemVazia(mensagem) {
  const emptyMessage = document.createElement('p');
  emptyMessage.textContent = mensagem;
  emptyMessage.className = 'mensagem-vazia';
  historicoContainer.appendChild(emptyMessage);
}

function criarSecaoMes(grupo) {
  const monthSection = document.createElement('div');
  monthSection.className = 'month-section';

  monthSection.innerHTML = `
    <div class="month-header">
      <span>${escapeHTML(grupo.mesAno)}</span>
      <span>${grupo.devocionais.length} devocional${grupo.devocionais.length !== 1 ? 's' : ''}</span>
    </div>
    <div class="month-content">
      <ul class="month-list">
        ${grupo.devocionais.map(criarItemDevocional).join('')}
      </ul>
    </div>
  `;

  historicoContainer.appendChild(monthSection);
}
/*
function criarItemDevocional(devocional) {
  const previewText = devocional.texto.length > 100 
    ? escapeHTML(devocional.texto.substring(0, 100)) + '...' 
    : escapeHTML(devocional.texto);
  
  return `
    <li>
      <div class="devocional-info">
        <div class="devocional-date">${formatarData(devocional.data, true)}</div>
        <div class="devocional-tema">${escapeHTML(devocional.tema)}</div>
        <div class="devocional-texto-preview">${previewText}</div>
      </div>
      <div class="devocional-actions">
        <button class="edit-btn" data-id="${devocional.id}">
          <span class="material-icons">edit</span>
        </button>
        <button class="delete-btn" data-id="${devocional.id}">
          <span class="material-icons">delete</span>
        </button>
      </div>
    </li>
  `;
}*/
/*
function criarItemDevocional(devocional) {
  return `
    <li>
      <div class="devocional-info">
        <div class="devocional-date">${formatarData(devocional.data, true)}</div>
        <div class="devocional-tema">${escapeHTML(devocional.tema)}</div>
        <div class="devocional-texto-preview">${escapeHTML(devocional.ministrado_por || 'Desconhecido')}</div>
        <button class="ver-texto-btn" data-texto="${escapeHTML(devocional.texto)}">Ver Texto</button>
      </div>
      <div class="devocional-actions">
        <button class="edit-btn" data-id="${devocional.id}">
          <span class="material-icons">edit</span>
        </button>
        <button class="delete-btn" data-id="${devocional.id}">
          <span class="material-icons">delete</span>
        </button>
      </div>
    </li>
  `;
}

function criarItemDevocional(devocional) {
  return `
    <li>
      <div class="devocional-info">
        <div class="devocional-date">${formatarData(devocional.data, true)}</div>
        <div class="devocional-tema">${escapeHTML(devocional.tema)}</div>
        <div class="devocional-texto-preview">
          ${escapeHTML(devocional.ministrado_por || 'Desconhecido')}
         <button class="ver-texto-btn" data-texto="${escapeHTML(devocional.texto)}" title="Ver texto completo">
  <span class="material-icons">visibility</span>
</button>

        </div>
      </div>
      <div class="devocional-actions">
        <button class="edit-btn" data-id="${devocional.id}">
          <span class="material-icons">edit</span>
        </button>
        <button class="delete-btn" data-id="${devocional.id}">
          <span class="material-icons">delete</span>
        </button>
      </div>
    </li>
  `;
}
*/
function criarItemDevocional(devocional) {
  return `
    <li>
      <div class="devocional-info">
        <div class="devocional-date">${formatarData(devocional.data, true)}</div>
        <div class="devocional-tema">${escapeHTML(devocional.tema)}</div>
      <div class="devocional-preview-container">
  <div class="ministrado-por">
    ${escapeHTML(devocional.ministrado_por || 'Desconhecido')}
  </div>
  <button class="ver-texto-btn" data-texto="${escapeHTML(devocional.texto)}" title="Ver texto completo">
    Ver texto
  </button>
</div>

      </div>
      <div class="devocional-actions">
        <button class="edit-btn" data-id="${devocional.id}">
          <span class="material-icons">edit</span>
        </button>
        <button class="delete-btn" data-id="${devocional.id}">
          <span class="material-icons">delete</span>
        </button>
      </div>
    </li>
  `;
}


function configurarBotoesAcao() {
  document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const id = e.currentTarget.getAttribute('data-id');
      editarDevocional(id);
    });
  });

  document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const id = e.currentTarget.getAttribute('data-id');
      confirmarExclusao(id);
    });
  });

document.querySelectorAll('.ver-texto-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const texto = btn.getAttribute('data-texto');
    document.getElementById('modalTextoConteudo').textContent = texto || 'Nenhum texto disponível.';
    document.getElementById('modalTexto').classList.remove('hidden');
  });
});

function fecharModalTexto() {
  document.getElementById('modalTexto').classList.add('hidden');
}
window.fecharModalTexto = fecharModalTexto;

}

function agruparPorMes(devocionais, filtro = '') {
  const meses = {};
  
  const devocionaisFiltrados = filtro 
    ? devocionais.filter(devocional => {
        const tema = devocional.tema.toLowerCase();
        const texto = devocional.texto.toLowerCase();
        const dataFormatada = formatarData(devocional.data, true).toLowerCase();
        return tema.includes(filtro) || texto.includes(filtro) || dataFormatada.includes(filtro);
      })
    : [...devocionais];
  
  devocionaisFiltrados.forEach(devocional => {
    const data = new Date(devocional.data);
    const mesAno = new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(data);

    const mesAnoFormatado = mesAno.charAt(0).toUpperCase() + mesAno.slice(1);
    
    if (!meses[mesAnoFormatado]) {
      meses[mesAnoFormatado] = [];
    }
    meses[mesAnoFormatado].push(devocional);
  });
  
  return Object.entries(meses)
    .map(([mesAno, devocionais]) => ({
      mesAno,
      devocionais: devocionais.sort((a, b) => new Date(b.data) - new Date(a.data))
    }))
    .sort((a, b) => new Date(b.devocionais[0].data) - new Date(a.devocionais[0].data));
}

function editarDevocional(id) {
  const devocional = historicoDevocionais.find(d => d.id == id);
  if (devocional) {
    devocionalEditando = devocional;
    inputData.value = devocional.data;
    inputTema.value = devocional.tema;
    inputTexto.value = devocional.texto;
inputMinistradoPor.value = devocional.ministrado_por || '';

    abrirModal();
  }
}

async function confirmarExclusao(id) {
  if (!confirm('Tem certeza que deseja excluir este devocional?')) return;

  try {
    const response = await fetch('excluir_devocional.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `id=${id}`
    });
    
    const result = await response.json();
    
    if (result.success) {
      mostrarNotificacao('Devocional excluído com sucesso!', 'success');
      carregarDevocionais();
    } else {
      mostrarNotificacao(result.message, 'error');
    }
  } catch (error) {
    mostrarNotificacao('Erro ao excluir devocional.', 'error');
    console.error(error);
  }
}

// Funções utilitárias
function formatarData(dataStr, short = false) {
  if (!dataStr) return '';
  
  let data;
  if (dataStr.includes('-')) {
    const partes = dataStr.split('-');
    data = new Date(partes[0], partes[1] - 1, partes[2]);
  } else {
    data = new Date(dataStr);
  }
  
  if (isNaN(data.getTime())) return dataStr;
  
  const options = short 
    ? { day: '2-digit', month: '2-digit', year: 'numeric' }
    : { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
  
  return data.toLocaleDateString('pt-BR', options);
}

function escapeHTML(str) {
  return str.replace(/[&<>'"]/g, 
    tag => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      "'": '&#39;',
      '"': '&quot;'
    }[tag]));
}

function mostrarNotificacao(mensagem, tipo = 'info') {
  const notificacao = document.createElement('div');
  notificacao.className = `notificacao notificacao-${tipo}`;
  notificacao.textContent = mensagem;
  
  document.body.appendChild(notificacao);
  
  setTimeout(() => notificacao.classList.add('show'), 10);
  setTimeout(() => {
    notificacao.classList.remove('show');
    setTimeout(() => document.body.removeChild(notificacao), 300);
  }, 3000);
}

// Inicialização
const estiloNotificacao = document.createElement('style');
estiloNotificacao.textContent = `
.notificacao {
  position: fixed;
  bottom: 20px;
  right: 20px;
  padding: 15px 25px;
  border-radius: 8px;
  color: white;
  font-weight: 500;
  transform: translateY(100px);
  opacity: 0;
  transition: all 0.3s ease;
  z-index: 10000;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.notificacao.show { transform: translateY(0); opacity: 1; }
.notificacao-success { background: var(--success-color); }
.notificacao-error { background: var(--danger-color); }
.notificacao-warning { background: var(--accent-color); }
.notificacao-info { background: var(--primary-color); }
.mensagem-vazia {
  text-align: center;
  padding: 20px;
  color: #666;
}
body.dark .mensagem-vazia { color: #aaa; }
`;
document.head.appendChild(estiloNotificacao);

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
    fecharModal();
  }
});

inputData.addEventListener('focus', () => {
  if (!inputData.value) {
    const hoje = new Date();
    inputData.value = hoje.toISOString().split('T')[0];
  }
});