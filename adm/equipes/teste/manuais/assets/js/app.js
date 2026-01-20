const menu = document.getElementById('menuManuais');
const buscaInput = document.getElementById('buscaManual');

const modal = document.getElementById('modalTxt');
const modalTexto = document.getElementById('modalTexto');
const modalTitulo = document.getElementById('modalTitulo');
const breadcrumbEl = document.getElementById('breadcrumb');
const resultadoInfo = document.getElementById('resultadoInfo');

const btnCopiar = document.getElementById('btnCopiarTxt');
const btnBaixar = document.getElementById('btnBaixarTxt');
const btnFechar = document.getElementById('btnFechar');
const listaRecentes = document.getElementById('listaRecentes');

let arvoreManuais = [];
let termoAtual = '';
let debounceBusca = null;

/* CARREGAR MENU */
fetch('api/listar_manuais.php')
  .then(r => r.json())
  .then(d => {
    arvoreManuais = d;
    renderMenu(d);
    renderRecentes();
  });

function renderMenu(lista) {
  menu.innerHTML = '';
  lista.forEach(i => menu.appendChild(criarItem(i)));
}

function criarItem(item) {
  const div = document.createElement('div');
  div.classList.add('menu-item');

  if (item.tipo === 'pasta') {
    const t = document.createElement('div');
    t.classList.add('menu-title');
    t.textContent = '📁 ' + item.nome;

    const filhos = document.createElement('div');
    filhos.style.marginLeft = '15px';
    filhos.style.display = 'none';

    t.onclick = () => filhos.style.display = filhos.style.display === 'none' ? 'block' : 'none';

    (item.filhos || []).forEach(f => filhos.appendChild(criarItem(f)));

    div.appendChild(t);
    div.appendChild(filhos);
  }

  if (item.tipo === 'arquivo') {
    const a = document.createElement('div');
    a.classList.add('menu-title');
    a.textContent = '📄 ' + item.nome;
    a.onclick = () => abrirManual(item);
    div.appendChild(a);
  }

  return div;
}

function abrirManual(item) {
  fetch(`api/ler_manual.php?arquivo=${encodeURIComponent(item.arquivo)}`)
    .then(r => r.json())
    .then(d => {
      modalTitulo.textContent = item.nome;
      breadcrumbEl.textContent = item.breadcrumb
        ? '📍 ' + item.breadcrumb.replace(/\//g, ' > ')
        : '';
      modalTexto.innerHTML = destacar(d.conteudo, termoAtual);
      salvarRecente(item);
      renderRecentes();
      modal.classList.remove('hidden');
    });
}

function destacar(texto, termo) {
  if (!termo) return texto;
  return texto.replace(new RegExp(`(${termo})`, 'gi'), '<mark>$1</mark>');
}

btnFechar.onclick = () => modal.classList.add('hidden');

btnCopiar.onclick = () => navigator.clipboard.writeText(modalTexto.innerText);
btnBaixar.onclick = () => {
  const blob = new Blob([modalTexto.innerText], { type: 'text/plain' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = modalTitulo.textContent + '.txt';
  a.click();
};

buscaInput.addEventListener('input', e => {
  termoAtual = e.target.value.trim();
  clearTimeout(debounceBusca);

  if (!termoAtual) {
    renderMenu(arvoreManuais);
    resultadoInfo.textContent = '';
    return;
  }

  debounceBusca = setTimeout(() => {
    fetch(`api/buscar_manuais.php?q=${encodeURIComponent(termoAtual)}`)
      .then(r => r.json())
      .then(d => {
        resultadoInfo.textContent = `🔍 ${d.length} resultado(s) encontrado(s)`;
        renderMenu(d);
      });
  }, 300);
});

/* ÚLTIMOS */
function salvarRecente(item) {
  let r = JSON.parse(localStorage.getItem('recentes')) || [];
  r = r.filter(i => i.arquivo !== item.arquivo);
  r.unshift(item);
  if (r.length > 5) r.pop();
  localStorage.setItem('recentes', JSON.stringify(r));
}

function renderRecentes() {
  listaRecentes.innerHTML = '';
  (JSON.parse(localStorage.getItem('recentes')) || []).forEach(i => {
    const d = document.createElement('div');
    d.textContent = i.nome;
    d.onclick = () => abrirManual(i);
    listaRecentes.appendChild(d);
  });
}
