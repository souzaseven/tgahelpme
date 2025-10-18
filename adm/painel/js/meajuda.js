// TGA Me Ajuda JavaScript - Dark Mode
function loadMeAjudaSection() {
    const content = `
        <div class="smart-section">
            <div class="smart-header">
                <h1>🚀 TGA Me Ajuda</h1>
                <p class="subtitle">Ferramentas e recursos internos TGA Sistemas</p>
            </div>
            
            <div class="smart-grid-4">
                <!-- Acesso Cliente e Login -->
                <div class="smart-card compact">
                    <h3>👤 Acesso e Login</h3>
                    <div class="smart-buttons compact">
                        <button class="smart-btn compact-btn primary" onclick="window.open('https://tgameajuda.com/cliente-tga.html', '_blank')">
                            <span class="smart-btn-icon compact-icon">👥</span>
                            Sou Cliente
                        </button>
                        <button class="smart-btn compact-btn secondary" onclick="window.open('https://tgameajuda.com/login.html', '_blank')">
                            <span class="smart-btn-icon compact-icon">🔐</span>
                            Login TGA Me Ajuda
                        </button>
                        <button class="smart-btn compact-btn success" onclick="window.open('https://tgameajuda.com/devocional2/inicio.html', '_blank')">
                            <span class="smart-btn-icon compact-icon">🙏</span>
                            Devocional
                        </button>
                    </div>
                </div>

                <!-- Sistema e Versões -->
                <div class="smart-card compact">
                    <h3>📦 Sistema e Versões</h3>
                    <div class="smart-buttons compact">
                        <button class="smart-btn compact-btn info" onclick="window.open('https://tgameajuda.com/NovidadesVersao/novidadeversao.html', '_blank')">
                            <span class="smart-btn-icon compact-icon">🔄</span>
                            Página de Versões
                        </button>
                        <button class="smart-btn compact-btn warning" onclick="window.open('https://tgameajuda.com/tgasefaz/tgasefaz.html', '_blank')">
                            <span class="smart-btn-icon compact-icon">⚡</span>
                            TGA Se Faz
                        </button>
                        <button class="smart-btn compact-btn" onclick="window.open('https://tgameajuda.com/tgadownloads/index.html', '_blank')">
                            <span class="smart-btn-icon compact-icon">📥</span>
                            TGA Downloads
                        </button>
                    </div>
                </div>

                <!-- Telefonia Evolux -->
                <div class="smart-card compact">
                    <h3>📞 Telefonia Evolux</h3>
                    <div class="smart-buttons compact">
                        <button class="smart-btn compact-btn" onclick="window.open('https://tgameajuda.com/telefonia-evolux/telefonia.html', '_blank')">
                            <span class="smart-btn-icon compact-icon">📚</span>
                            Manuais Evolux
                        </button>
                        <button class="smart-btn compact-btn secondary" onclick="window.open('https://tgameajuda.com/telefonia-evolux/painel-operador/login.html', '_blank')">
                            <span class="smart-btn-icon compact-icon">👨‍💼</span>
                            Painel Operadores
                        </button>
                    </div>
                </div>

                <!-- TEF e Smart POS -->
                <div class="smart-card compact">
                    <h3>💳 TEF e Smart POS</h3>
                    <div class="smart-buttons compact">
                        <button class="smart-btn compact-btn success" onclick="window.open('https://tgameajuda.com/tgatef/ImagensSmartPos.html', '_blank')">
                            <span class="smart-btn-icon compact-icon">🖼️</span>
                            Imagens Smart POS
                        </button>
                        <button class="smart-btn compact-btn info" onclick="window.open('https://tgameajuda.com/tgatef/POS/login.html', '_blank')">
                            <span class="smart-btn-icon compact-icon">🔧</span>
                            Painel Cadastro POS
                        </button>
                    </div>
                </div>

                <!-- WhatsApp -->
                <div class="smart-card compact">
                    <h3>💬 WhatsApp</h3>
                    <div class="smart-buttons compact">
                        <button class="smart-btn compact-btn" onclick="window.open('https://tgameajuda.com/tgawhatsapp/mensagenstxt/mensagens.html', '_blank')">
                            <span class="smart-btn-icon compact-icon">💬</span>
                            Mensagens Exemplo
                        </button>
                        <button class="smart-btn compact-btn secondary" onclick="window.open('https://tgameajuda.com/tgawhatsapp/geraqrcodelink.html', '_blank')">
                            <span class="smart-btn-icon compact-icon">🔗</span>
                            Gera QRCode WhatsApp
                        </button>
                    </div>
                </div>

                <!-- Consultas e Suporte -->
                <div class="smart-card compact">
                    <h3>🔍 Consultas</h3>
                    <div class="smart-buttons compact">
                        <button class="smart-btn compact-btn warning" onclick="window.open('https://tgameajuda.com/tgaconsultaerro/Consulta-erro/consultaerro.html', '_blank')">
                            <span class="smart-btn-icon compact-icon">🔎</span>
                            Consulta Atendimento
                        </button>
                        <button class="smart-btn compact-btn" onclick="window.open('https://tgameajuda.com/tgaconsultaerro/Busca-Mantis/buscamantis.html', '_blank')">
                            <span class="smart-btn-icon compact-icon">🐛</span>
                            Consulta Mantis
                        </button>
                        <button class="smart-btn compact-btn secondary" onclick="window.open('https://tgameajuda.com/tgaconsultaerro/Busca-Ticket/buscaticket.html', '_blank')">
                            <span class="smart-btn-icon compact-icon">🎫</span>
                            Consulta Ticket
                        </button>
                    </div>
                </div>

                <!-- SQL e Relatórios -->
                <div class="smart-card compact">
                    <h3>🗃️ SQL e Relatórios</h3>
                    <div class="smart-buttons compact">
                        <button class="smart-btn compact-btn success" onclick="window.open('https://tgameajuda.com/tgasql/sql.html', '_blank')">
                            <span class="smart-btn-icon compact-icon">📊</span>
                            Arquivos SQL
                        </button>
                        <button class="smart-btn compact-btn info" onclick="window.open('https://tgameajuda.com/tgareport/arquivosReport.html', '_blank')">
                            <span class="smart-btn-icon compact-icon">📋</span>
                            Todos Arquivos Report
                        </button>
                    </div>
                </div>

                <!-- Report Específicos -->
                <div class="smart-card full-width compact">
                    <h3>📄 Relatórios Específicos</h3>
                    <div class="smart-buttons-grid-5">
                        <button class="smart-btn compact-btn" onclick="window.open('https://tgameajuda.com/tgareport/etiquetas.html', '_blank')">
                            🏷️ Etiquetas
                        </button>
                        <button class="smart-btn compact-btn secondary" onclick="window.open('https://tgameajuda.com/tgareport/impressos.html', '_blank')">
                            🖨️ Impressos
                        </button>
                        <button class="smart-btn compact-btn success" onclick="window.open('https://tgameajuda.com/tgareport/relatorios.html', '_blank')">
                            📈 Relatórios
                        </button>
                        <button class="smart-btn compact-btn info" onclick="window.open('https://tgameajuda.com/tgareport/workflow.html', '_blank')">
                            🔄 Workflow
                        </button>
                        <button class="smart-btn compact-btn warning" onclick="window.open('https://tgameajuda.com/tgareport/formulas.html', '_blank')">
                            🧮 Fórmulas
                        </button>
                    </div>
                </div>

                <!-- Configurações e Manuais -->
                <div class="smart-card full-width compact">
                    <h3>⚙️ Configurações e Manuais</h3>
                    <div class="smart-buttons-grid-4">
                        <button class="smart-btn compact-btn" onclick="window.open('https://tgameajuda.com/tgaemail/Configura%C3%A7%C3%A3o%20E-mail%20no%20Sistema%20TGA.pdf', '_blank')">
                            📧 Portas e Email
                        </button>
                        <button class="smart-btn compact-btn secondary" onclick="window.open('https://tgameajuda.com/tgafiscal/TREINAMENTO%20FISCAL.pdf', '_blank')">
                            📚 Treinamento Fiscal
                        </button>
                        <button class="smart-btn compact-btn success" onclick="window.open('https://tgameajuda.com/tgamanuais/manuais.html', '_blank')">
                            📖 Manuais Gerais
                        </button>
                        <button class="smart-btn compact-btn info" onclick="window.open('https://tgameajuda.com/tgamanuais/perguntas-frequentes.html', '_blank')">
                            ❓ FAQ
                        </button>
                    </div>
                </div>

                <!-- Ferramentas Diversas -->
                <div class="smart-card full-width compact">
                    <h3>🛠️ Ferramentas Diversas</h3>
                    <div class="smart-buttons-grid-5">
                        <button class="smart-btn compact-btn" onclick="window.open('https://tgameajuda.com/tgawallpaper/index.html', '_blank')">
                            🖼️ Wallpapers
                        </button>
                        <button class="smart-btn compact-btn secondary" onclick="window.open('https://tgameajuda.com/tgasugestoes/consultasugestao.php', '_blank')">
                            💡 Sugestões
                        </button>
                        <button class="smart-btn compact-btn success" onclick="window.open('https://tgameajuda.com/consultacnpj/index.html', '_blank')">
                            🏢 Consulta CNPJ
                        </button>
                        <button class="smart-btn compact-btn info" onclick="window.open('https://tgameajuda.com/diversos/Qrcode/qrcode.html', '_blank')">
                            🔲 Gera QRCode
                        </button>
                        <button class="smart-btn compact-btn warning" onclick="window.open('https://tgameajuda.com/diversos/Conta%20texto/contatexto-auto.html', '_blank')">
                            📝 Conta Texto
                        </button>
                    </div>
                </div>

                <!-- Validações e Testes -->
                <div class="smart-card full-width compact">
                    <h3>✅ Validações e Testes</h3>
                    <div class="smart-buttons-grid-4">
                        <button class="smart-btn compact-btn" onclick="window.open('https://tgameajuda.com/diversos/Valida%20chave/validachave.html', '_blank')">
                            🔑 Valida Chave
                        </button>
                        <button class="smart-btn compact-btn secondary" onclick="window.open('https://tgameajuda.com/diversos/Valida%20boleto/validaboletoauto.html', '_blank')">
                            💰 Valida Boleto
                        </button>
                        <button class="smart-btn compact-btn success" onclick="window.open('https://tgameajuda.com/diversos/Provas/provas.html', '_blank')">
                            🧠 Teste Conhecimento
                        </button>
                        <button class="smart-btn compact-btn info" onclick="window.open('https://tgameajuda.com/diversos/Provas/RegimeFiscal.html', '_blank')">
                            📊 Prova Fiscal
                        </button>
                    </div>
                </div>

                <!-- Tickets e Tempo -->
                <div class="smart-card full-width compact">
                    <h3>⏰ Tickets e Tempo</h3>
                    <div class="smart-buttons-grid-4">
                        <button class="smart-btn compact-btn" onclick="window.open('https://tgameajuda.com/diversos/tickets/intervalotickets.html', '_blank')">
                            🎫 Vários Tickets
                        </button>
                        <button class="smart-btn compact-btn secondary" onclick="window.open('https://tgameajuda.com/diversos/Intervalo%20de%20tempo/index.html', '_blank')">
                            ⏱️ Intervalo Tempo
                        </button>
                        <button class="smart-btn compact-btn success" onclick="window.open('https://tgameajuda.com/diversos/Hora%20trabalhada/index.html', '_blank')">
                            👷 Hora Trabalhada
                        </button>
                        <button class="smart-btn compact-btn info" onclick="window.open('https://tgameajuda.com/diversos/SQL/consultas-sqlv3.html', '_blank')">
                            💾 Salva SQL
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('content').innerHTML = content;
}

// Modal para informações detalhadas (se necessário futuramente)
function openMeAjudaModal(type) {
    let modalContent = '';
    const modalTitle = getMeAjudaModalTitle(type);
    
    // Pode ser expandido futuramente para modais específicos
    modalContent = `
        <div class="info-section">
            <h3>${modalTitle}</h3>
            <p>Esta funcionalidade redireciona para a página específica do TGA Me Ajuda.</p>
            <p>Clique no botão para acessar diretamente.</p>
        </div>
    `;
    
    const modalHTML = `
        <div class="smart-modal-content">
            <div class="smart-modal-header">
                <h2>${modalTitle}</h2>
                <button class="close-smart-modal" onclick="closeSmartModal()">&times;</button>
            </div>
            <div class="smart-info-grid">
                ${modalContent}
            </div>
        </div>
    `;
    
    document.getElementById('smartModal').innerHTML = modalHTML;
    document.getElementById('smartModal').style.display = 'block';
}

function getMeAjudaModalTitle(type) {
    const titles = {
        'cliente': '👥 Área do Cliente',
        'login': '🔐 Login TGA Me Ajuda',
        'devocional': '🙏 Devocional',
        'versoes': '🔄 Página de Versões',
        'tgasefaz': '⚡ TGA Se Faz',
        'downloads': '📥 TGA Downloads'
    };
    
    return titles[type] || 'TGA Me Ajuda';
}

// Event Listeners para o Me Ajuda
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('smartModal');
    
    if (modal) {
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeSmartModal();
            }
        });
    }
});

// Fechar modal com ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeSmartModal();
    }
});

document.getElementById('meAjudaBtn').addEventListener('click', function() {
    loadMeAjudaSection();
});
