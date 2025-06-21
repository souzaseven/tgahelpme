const API_URL = 'https://apibrasil.com.br/api/placa/v1';
const API_TOKEN = '9f5938b6-b2eb-4c4f-94f1-4fcbda0e66d8';
const MAX_HISTORICO = 10;

let streamAtual = null;
let usandoFrontal = false;
let historicoConsultas = JSON.parse(localStorage.getItem('historicoPlacas')) || [];

document.addEventListener('DOMContentLoaded', () => {
  atualizarHistoricoUI();
});

async function consultarPlaca() {
  const placaInput = document.getElementById('placaInput');
  let placa = placaInput.value.trim().toUpperCase().replace(/[^A-Z0-9]/g, '');

  if (!placa) return mostrarResultado('Por favor, digite uma placa válida', 'error');

  mostrarResultado(`Consultando placa ${placa}...`, 'loading');

  try {
const response = await fetch(`consulta.php?placa=${placa}`);



    if (!response.ok) throw new Error('Erro ao consultar placa');

    const dados = await response.json();
    adicionarAoHistorico(placa, dados);
    mostrarDadosPlaca(placa, dados);

  } catch (error) {
    console.error('Erro na consulta:', error);
    mostrarResultado('Erro ao consultar placa. Tente novamente.', 'error');
  }
}

async function ativarCamera() {
  pararCamera();
  const constraints = {
    video: { facingMode: usandoFrontal ? 'user' : { exact: 'environment' } },
    audio: false
  };

  try {
    const stream = await navigator.mediaDevices.getUserMedia(constraints);
    streamAtual = stream;
    const video = document.getElementById('camera');
    video.srcObject = stream;

    document.querySelector('.camera-container').style.display = 'block';
    video.style.display = 'block';
    document.getElementById('btnCapturar').style.display = 'block';
    video.play();

  } catch (error) {
    console.error("Erro ao ativar câmera:", error);
    mostrarResultado("Permissão de câmera negada ou dispositivo não disponível.", "error");
  }
}

function inverterCamera() {
  usandoFrontal = !usandoFrontal;
  ativarCamera();
}

function pararCamera() {
  if (streamAtual) {
    streamAtual.getTracks().forEach(track => track.stop());
    streamAtual = null;
  }
  document.querySelector('.camera-container').style.display = 'none';
  document.getElementById('camera').style.display = 'none';
  document.getElementById('btnCapturar').style.display = 'none';
}

function capturarFoto() {
  const video = document.getElementById('camera');
  const canvas = document.getElementById('canvas');
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  const ctx = canvas.getContext('2d');
  ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

  mostrarResultado("Processando imagem da placa...", "loading");

  Tesseract.recognize(canvas.toDataURL('image/jpeg'), 'eng', {
    logger: m => console.log(m)
  }).then(({ data: { text } }) => {
    const placa = text.match(/[A-Z]{3}[0-9][A-Z0-9][0-9]{2}/)?.[0];
    if (placa) {
      document.getElementById('placaInput').value = placa;
      mostrarResultado(`Placa detectada: ${placa}`, "success");
      consultarPlaca();
    } else {
      mostrarResultado("Não foi possível identificar uma placa válida na imagem.", "error");
    }
  });
}

function mostrarResultado(mensagem, tipo = 'info') {
  const div = document.getElementById('resultado');
  const msg = {
    loading: `<div class="loading">${mensagem}</div>`,
    error: `<div class="error">${mensagem}</div>`,
    success: `<div class="success">${mensagem}</div>`,
    info: `<div>${mensagem}</div>`
  };
  div.innerHTML = msg[tipo] || mensagem;
}

function mostrarDadosPlaca(placa, dados) {
  const html = `
    <div class="success"><i class="fas fa-check-circle"></i> Consulta realizada para placa: <strong>${placa}</strong></div>
    <div class="placa-info">
      <p><strong>Marca:</strong> ${dados.marca ?? 'Não encontrado'}</p>

      <p><strong>Modelo:</strong> ${dados.modelo || 'Não encontrado'}</p>
      <p><strong>Cor:</strong> ${dados.cor || 'IndNão encontrado'}</p>
      <p><strong>Ano:</strong> ${dados.anoModelo || 'Não encontrado'}</p>
      <p><strong>UF:</strong> ${dados.uf || 'Não encontrado'}</p>
      <p><strong>Município:</strong> ${dados.municipio || 'Não encontrado'}</p>
      <p><strong>Situação:</strong> ${dados.situacao || 'Não encontrado'}</p>
    </div>
  `;
  document.getElementById('resultado').innerHTML = html;
}

function adicionarAoHistorico(placa, dados) {
  historicoConsultas = historicoConsultas.filter(item => item.placa !== placa);
  historicoConsultas.unshift({
    placa,
    data: new Date().toLocaleString(),
    dados
  });
  if (historicoConsultas.length > MAX_HISTORICO)
    historicoConsultas = historicoConsultas.slice(0, MAX_HISTORICO);
  localStorage.setItem('historicoPlacas', JSON.stringify(historicoConsultas));
  atualizarHistoricoUI();
}

function atualizarHistoricoUI() {
  const lista = document.getElementById('historicoLista');
  lista.innerHTML = historicoConsultas.length === 0
    ? '<li>Nenhuma consulta recente</li>'
    : historicoConsultas.map(i => `
        <li>
          <span class="historico-placa">${i.placa}</span>
          <span class="historico-data">${i.data}</span>
        </li>
      `).join('');
}
