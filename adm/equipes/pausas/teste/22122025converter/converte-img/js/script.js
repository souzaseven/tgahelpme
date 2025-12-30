// =====================================================
// REFERÊNCIAS AOS ELEMENTOS DA INTERFACE (DOM)
// =====================================================

// Input de upload (aceita múltiplas imagens)
const upload = document.getElementById("upload");

// Campos de largura e altura (resize em pixels)
const widthInput = document.getElementById("width");
const heightInput = document.getElementById("height");

// Checkbox para manter proporção
const lock = document.getElementById("lock");

// Blocos informativos
const info = document.getElementById("info");
const originalSize = document.getElementById("originalSize");

// Área de preview antes/depois
const previewBox = document.getElementById("previewBox");
const previewBefore = document.getElementById("previewBefore");
const previewAfter = document.getElementById("previewAfter");

// Container onde os links de download serão gerados
const downloads = document.getElementById("downloads");

// Canvas usado para redimensionar e converter imagens
const canvas = document.getElementById("canvas");
const ctx = canvas.getContext("2d");


// =====================================================
// VARIÁVEIS DE CONTROLE DE ESTADO
// =====================================================

// Proporção original da imagem (largura / altura)
let ratio = 1;

// Dimensões originais da imagem (usadas para reset)
let originalW = 0;
let originalH = 0;

// Lista de arquivos selecionados (para conversão em lote)
let files = [];


// =====================================================
// EVENTO: SELEÇÃO DE IMAGENS
// =====================================================

upload.addEventListener("change", () => {

  // Converte FileList em array para facilitar iteração
  files = Array.from(upload.files);
  if (!files.length) return;

  // Usa a primeira imagem como referência visual
  const first = files[0];
  const url = URL.createObjectURL(first);

  // Preview da imagem original
  previewBefore.src = url;
  previewAfter.src = "";
  previewBox.style.display = "block";

  // Carrega a imagem para leitura das dimensões reais
  const img = new Image();
  img.src = url;

  img.onload = () => {

    // Armazena dimensões originais
    originalW = img.width;
    originalH = img.height;

    // Calcula proporção para manter escala correta
    ratio = img.width / img.height;

    // Preenche campos de resize
    widthInput.value = img.width;
    heightInput.value = img.height;

    // Exibe informações ao usuário
    originalSize.textContent =
      `Dimensão original: ${img.width} × ${img.height}px`;

    info.style.display = "block";
  };
});


// =====================================================
// EVENTO: ALTERAÇÃO DE LARGURA (mantendo proporção)
// =====================================================

widthInput.addEventListener("input", () => {
  if (lock.checked && widthInput.value) {
    heightInput.value = Math.round(widthInput.value / ratio);
  }
});


// =====================================================
// EVENTO: ALTERAÇÃO DE ALTURA (mantendo proporção)
// =====================================================

heightInput.addEventListener("input", () => {
  if (lock.checked && heightInput.value) {
    widthInput.value = Math.round(heightInput.value * ratio);
  }
});


// =====================================================
// FUNÇÃO: RESETAR DIMENSÕES ORIGINAIS
// =====================================================

function resetarDimensoes() {
  widthInput.value = originalW;
  heightInput.value = originalH;
}


// =====================================================
// FUNÇÃO: CONVERSÃO EM LOTE
// - Aplica resize + formato selecionado
// - Mantém nome original dos arquivos
// - Gera downloads individuais
// =====================================================

function converterLote() {

  // Validação básica
  if (!files.length) {
    alert("Selecione ao menos uma imagem");
    return;
  }

  // Limpa resultados anteriores
  downloads.innerHTML = "";

  // Formato de saída escolhido
  const format = document.getElementById("format").value;
  const ext = format.split("/")[1];

  // Processa cada imagem selecionada
  files.forEach(file => {

    const img = new Image();
    const url = URL.createObjectURL(file);
    img.src = url;

    img.onload = () => {

      // Dimensões finais definidas pelo usuário
      const w = parseInt(widthInput.value);
      const h = parseInt(heightInput.value);

      // Ajusta canvas para novo tamanho
      canvas.width = w;
      canvas.height = h;

      // Limpa e redesenha imagem redimensionada
      ctx.clearRect(0, 0, w, h);
      ctx.drawImage(img, 0, 0, w, h);

      // Gera preview do resultado (apenas da primeira imagem)
      if (!previewAfter.src) {
        previewAfter.src = canvas.toDataURL(format, 0.9);
      }

      // Converte canvas em blob para download
      canvas.toBlob(blob => {

        // Mantém o nome original do arquivo
        const nomeBase = file.name.replace(/\.[^/.]+$/, "");

        // Cria link de download dinamicamente
        const a = document.createElement("a");
        a.href = URL.createObjectURL(blob);
        a.download = `${nomeBase}.${ext}`;
        a.textContent = `⬇ ${nomeBase}.${ext}`;

        // Adiciona à lista de downloads
        downloads.appendChild(a);
        downloads.appendChild(document.createElement("br"));

      }, format, 0.9);
    };
  });
}
