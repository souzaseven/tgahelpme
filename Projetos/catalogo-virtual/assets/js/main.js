// ============================================
// BASE URL — detecta automaticamente
// ============================================
const BASE_URL = (function() {
    const path = window.location.pathname;
    const marcadores = ['public', 'admin', 'api'];

    for (const marcador of marcadores) {
        const idx = path.indexOf('/' + marcador + '/');
        if (idx !== -1) return path.substring(0, idx);
        const idx2 = path.lastIndexOf('/' + marcador);
        if (idx2 !== -1) return path.substring(0, idx2);
    }

    return '';
})();

// ============================================
// CARRINHO - Funções principais
// ============================================

function adicionarAoCarrinho(produtoId, nomeProduto, preco) {
    fetch(BASE_URL + '/api/carrinho.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            acao: 'adicionar',
            produto_id: produtoId,
            quantidade: 1
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            mostrarNotificacao(`✅ "${nomeProduto}" adicionado ao carrinho!`, 'sucesso');
            atualizarContadorCarrinho();
        } else {
            mostrarNotificacao(`❌ Erro: ${data.message}`, 'erro');
        }
    })
    .catch(() => {
        mostrarNotificacao('❌ Erro ao conectar com o servidor.', 'erro');
    });
}

function removerDoCarrinho(itemId) {
    if (!confirm('Deseja remover este item do carrinho?')) return;

    fetch(BASE_URL + '/api/carrinho.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            acao: 'remover',
            item_id: itemId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            mostrarNotificacao(`❌ Erro: ${data.message}`, 'erro');
        }
    })
    .catch(() => {
        mostrarNotificacao('❌ Erro ao conectar com o servidor.', 'erro');
    });
}

function atualizarQuantidade(itemId, novaQuantidade) {
    if (novaQuantidade < 1) return;

    fetch(BASE_URL + '/api/carrinho.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            acao: 'atualizar',
            item_id: itemId,
            quantidade: novaQuantidade
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            mostrarNotificacao(`❌ Erro: ${data.message}`, 'erro');
        }
    });
}

function atualizarContadorCarrinho() {
    fetch(BASE_URL + '/api/carrinho.php?acao=contar')
        .then(res => res.json())
        .then(data => {
            const contador = document.getElementById('contador-carrinho');
            if (contador) {
                contador.textContent = data.total ?? 0;
            }
        });
}

// ============================================
// NOTIFICAÇÕES - Toast na tela
// ============================================

function mostrarNotificacao(mensagem, tipo = 'sucesso') {
    const anterior = document.getElementById('notificacao-toast');
    if (anterior) anterior.remove();

    const toast = document.createElement('div');
    toast.id = 'notificacao-toast';
    toast.textContent = mensagem;
    toast.style.cssText = `
        position: fixed;
        bottom: 30px;
        right: 30px;
        padding: 14px 20px;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 600;
        color: white;
        z-index: 9999;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        animation: fadeIn 0.3s ease;
        background-color: ${tipo === 'sucesso' ? '#27ae60' : '#e74c3c'};
    `;

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.4s';
        setTimeout(() => toast.remove(), 400);
    }, 3000);
}

// ============================================
// BUSCA - Filtro de produtos no catálogo
// ============================================

function iniciarBuscaProdutos() {
    const campoBusca = document.getElementById('busca-produto');
    if (!campoBusca) return;

    campoBusca.addEventListener('input', function () {
        const termo = this.value.toLowerCase();
        const cards = document.querySelectorAll('.card-produto');

        cards.forEach(card => {
            const nome      = card.querySelector('.nome-produto')?.textContent.toLowerCase()      || '';
            const codigo    = card.querySelector('.codigo-produto')?.textContent.toLowerCase()    || '';
            const descricao = card.querySelector('.descricao-produto')?.textContent.toLowerCase() || '';

            const encontrou = nome.includes(termo) || codigo.includes(termo) || descricao.includes(termo);
            card.style.display = encontrou ? 'flex' : 'none';
        });
    });
}

// ============================================
// INICIALIZAÇÃO
// ============================================

document.addEventListener('DOMContentLoaded', function () {
    atualizarContadorCarrinho();
    iniciarBuscaProdutos();
});