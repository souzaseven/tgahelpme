// TGA Me Ajuda JavaScript - VERSÃO FINAL
function loadMeAjudaSection() {
    const content = `
        <div class="meajuda-section">
            <div class="meajuda-header">
               <h1>
  <img src="https://raw.githubusercontent.com/souzaseven/tgahelpme/Desafios/icon%20bot%20tga.ico" 
       alt="Ícone TGA" 
       style="height: 24px; vertical-align: middle; margin-right: 8px;">
  TGA Me Ajuda
</h1>

                <p class="meajuda-subtitle">Ferramentas e recursos internos TGA Sistemas</p>
            </div>
            
            <div class="meajuda-grid-4">
                <!-- Linha 1 -->
                <!-- Acesso Cliente e Login -->
                <div class="meajuda-card compact">
                    <h3>👤 Acesso e Login</h3>
                    <div class="meajuda-buttons compact">
                        <button class="meajuda-btn compact-btn primary" onclick="window.open('https://tgameajuda.com/cliente-tga.html', '_blank')">
                            <span class="meajuda-btn-icon compact-icon">👥</span>
                            Sou Cliente
                        </button>
                        <button class="meajuda-btn compact-btn secondary" onclick="window.open('https://tgameajuda.com/login.html', '_blank')">
                            <span class="meajuda-btn-icon compact-icon">🔐</span>
                            Login TGA Me Ajuda
                        </button>
                        <button class="meajuda-btn compact-btn success" onclick="window.open('https://tgameajuda.com/devocional2/inicio.html', '_blank')">
                            <span class="meajuda-btn-icon compact-icon">🙏</span>
                            Devocional
                        </button>
                        <button class="meajuda-btn compact-btn info" onclick="window.open('https://chatgpt.com/g/g-683bc29b6e508191b0c94d367495dfec-bot-tga-me-ajuda', '_blank')">
                            <span class="meajuda-btn-icon compact-icon">🤖</span>
                            BOT TGAMEJUDA GPT
                        </button>
                        <button class="meajuda-btn compact-btn warning" onclick="window.open('https://chatgpt.com/gpts/mine', '_blank')">
                            <span class="meajuda-btn-icon compact-icon">🚀</span>
                            TGAMEJUDA GPT
                        </button>
                    </div>
                </div>

                <!-- Sistema e Versões -->
<div class="meajuda-card compact">
    <h3>📦 Sistema e Versões</h3>
    <div class="meajuda-buttons compact">
        <button class="meajuda-btn compact-btn primary" onclick="window.open('https://tgameajuda.com/NovidadesVersao/novidadeversao.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">🔄</span>
            Página de Versões
        </button>
        <button class="meajuda-btn compact-btn success" onclick="window.open('https://tgameajuda.com/tgasefaz/tgasefaz.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">⚡</span>
            TGA Sefaz
        </button>
        <button class="meajuda-btn compact-btn info" onclick="window.open('https://tgameajuda.com/tgadownloads/index.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">📥</span>
            TGA Downloads
        </button>
        <button class="meajuda-btn compact-btn warning" onclick="window.open('https://atendimento.tgasistemas.com.br/', '_blank')">
            <span class="meajuda-btn-icon compact-icon">🎧</span>
            TGA Movidesk
        </button>
        <button class="meajuda-btn compact-btn primary" onclick="window.open('https://mantis.tgasistemas.com.br/my_view_page.php', '_blank')">
            <span class="meajuda-btn-icon compact-icon">🐞</span>
            Painel de Mantis
        </button>

   <button class="meajuda-btn compact-btn primary" onclick="window.open('https://tgameajuda.com/NovidadesVersao/MOBILE/ForcadeVendas/index.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">📴</span>
            Mobile Info
        </button>
    </div>
</div>

              <!-- Telefonia Evolux -->
<div class="meajuda-card compact">
    <h3>📞 Telefonia Evolux</h3>
    <div class="meajuda-buttons compact">
        <button class="meajuda-btn compact-btn primary" onclick="window.open('https://tgameajuda.com/telefonia-evolux/telefonia.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">📚</span>
            Manuais Evolux
        </button>
        <button class="meajuda-btn compact-btn secondary" onclick="window.open('https://tgameajuda.com/telefonia-evolux/painel-operador/login.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">👨‍💼</span>
            Painel Operadores
        </button>
        <button class="meajuda-btn compact-btn info" onclick="window.open('https://tgameajuda.com/telefonia-evolux/painel-operador/index.php', '_blank')">
            <span class="meajuda-btn-icon compact-icon">🏠</span>
            Painel do Operador
        </button>
        <button class="meajuda-btn compact-btn success" onclick="window.open('https://tgasistemas.evolux.io/panel/queue?id=9&slug=suporte_matriz&type=queue#details', '_blank')">
            <span class="meajuda-btn-icon compact-icon">📊</span>
            Painel de Ligação
        </button>
        <button class="meajuda-btn compact-btn warning" onclick="window.open('https://tgasistemas.evolux.io/callcenter/agent', '_blank')">
            <span class="meajuda-btn-icon compact-icon">👥</span>
            Painel Operadores
        </button>
        <button class="meajuda-btn compact-btn danger" onclick="window.open('https://tgasistemas.evolux.io/callcenter/supervisor/agents_monitor', '_blank')">
            <span class="meajuda-btn-icon compact-icon">👁️</span>
            Monitor Operadores
        </button>
    </div>
</div>
<!-- TEF e Smart POS -->
<div class="meajuda-card compact">
    <h3>💳 TEF e Smart POS</h3>
    <div class="meajuda-buttons compact"> <!-- ✅ CORRIGIDO: meajuda-buttons -->
        <button class="meajuda-btn compact-btn success" onclick="window.open('https://tgameajuda.com/tgatef/ImagensSmartPos.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">🖼️</span>
            Imagens Smart POS
        </button>
        <button class="meajuda-btn compact-btn primary" onclick="window.open('https://tgameajuda.com/tgatef/POS/login.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">🔧</span>
            Painel Cadastro POS
        </button>
        <button class="meajuda-btn compact-btn info" onclick="window.open('https://tga.pdv.mobi/POSAuth/#/monitor', '_blank')">
            <span class="meajuda-btn-icon compact-icon">📱</span>
            Painel Mobi V1
        </button>
        <button class="meajuda-btn compact-btn warning" onclick="window.open('https://tga.v2.pdv.mobi/login', '_blank')">
            <span class="meajuda-btn-icon compact-icon">🚀</span>
            Painel Mobi V2
        </button>
        <button class="meajuda-btn compact-btn secondary" onclick="window.open('https://tgameajuda.com/tgatef/configsmartpos.html#vincular-app', '_blank')">
            <span class="meajuda-btn-icon compact-icon">🔗</span>
            Config Smart POS
        </button>
        <button class="meajuda-btn compact-btn" onclick="openMeAjudaModal('vincular-app')">
            <span class="meajuda-btn-icon compact-icon">🔗</span>
            Vincular APP
        </button>
        <button class="meajuda-btn compact-btn info" onclick="openMeAjudaModal('chave-api')">
            <span class="meajuda-btn-icon compact-icon">🔑</span>
            Chave API
        </button>
        <button class="meajuda-btn compact-btn success" onclick="openFormulariosModal()">
            <span class="meajuda-btn-icon compact-icon">📝</span>
            Formulários
        </button>
    </div>
</div>

                <!-- Linha 2 -->
                <!-- WhatsApp -->
                <div class="meajuda-card compact">
                    <h3>💬 WhatsApp</h3>
                    <div class="meajuda-buttons compact">
                        <button class="meajuda-btn compact-btn primary" onclick="window.open('https://tgameajuda.com/tgawhatsapp/mensagenstxt/mensagens.html', '_blank')">
                            <span class="meajuda-btn-icon compact-icon">💬</span>
                            Mensagens Exemplo
                        </button>
                        <button class="meajuda-btn compact-btn success" onclick="window.open('https://tgameajuda.com/tgawhatsapp/geraqrcodelink.html', '_blank')">
                            <span class="meajuda-btn-icon compact-icon">🔗</span>
                            Gera QRCode
                        </button>
                    </div>
                </div>

<!-- Consultas e Suporte -->
<div class="meajuda-card compact">
    <h3>🔍 Consultas</h3>
    <div style="display: flex; flex-direction: column; gap: 10px;">
        <button class="meajuda-btn primary" onclick="window.open('https://atendimento.tgasistemas.com.br/kb/pt-br', '_blank')">
            <span>📚</span>
            Base de conhecimento TGA
        </button>
        <button class="meajuda-btn warning" onclick="window.open('https://tgameajuda.com/tgaconsultaerro/Consulta-erro/consultaerro.html', '_blank')">
            <span>🔎</span>
            Consulta Atendimento
        </button>
        <button class="meajuda-btn info" onclick="window.open('https://tgameajuda.com/tgaconsultaerro/Busca-Mantis/buscamantis.html', '_blank')">
            <span>🐛</span>
            Consulta Mantis
        </button>
        <button class="meajuda-btn secondary" onclick="window.open('https://tgameajuda.com/tgaconsultaerro/Busca-Ticket/buscaticket.html', '_blank')">
            <span>🎫</span>
            Consulta Ticket
        </button>
        <button class="meajuda-btn success" onclick="window.open('https://tgameajuda.com/consultacnpj/index.html', '_blank')">
            <span>🏢</span>
            Consulta CNPJ
        </button>
    </div>
</div>

                <!-- SQL -->
                <div class="meajuda-card compact">
                    <h3>🗃️ SQL</h3>
                    <div class="meajuda-buttons compact">
                        <button class="meajuda-btn compact-btn primary" onclick="window.open('https://tgameajuda.com/tgasql/sql.html', '_blank')">
                            <span class="meajuda-btn-icon compact-icon">📊</span>
                            Arquivos SQL
                        </button>
                        <button class="meajuda-btn compact-btn success" onclick="window.open('https://tgameajuda.com/diversos/SQL/consultas-sqlv3.html', '_blank')">
                            <span class="meajuda-btn-icon compact-icon">💾</span>
                            Salva SQL
                        </button>
                    </div>
                </div>

                <!-- Report Específicos -->
<div class="meajuda-card compact">
    <h3>📄 Relatórios</h3>
    <div class="meajuda-buttons"> <!-- ⚠️ MUDAR: meajuda-buttons-grid-5 → meajuda-buttons -->
        <button class="meajuda-btn compact-btn primary" onclick="window.open('https://tgameajuda.com/tgareport/etiquetas.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">🏷️</span>
            Etiquetas
        </button>
        <button class="meajuda-btn compact-btn info" onclick="window.open('https://tgameajuda.com/tgareport/impressos.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">🖨️</span>
            Impressos
        </button>
        <button class="meajuda-btn compact-btn success" onclick="window.open('https://tgameajuda.com/tgareport/relatorios.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">📈</span>
            Relatórios
        </button>
        <button class="meajuda-btn compact-btn warning" onclick="window.open('https://tgameajuda.com/tgareport/workflow.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">🔄</span>
            Workflow
        </button>
        <button class="meajuda-btn compact-btn secondary" onclick="window.open('https://tgameajuda.com/tgareport/formulas.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">🧮</span>
            Fórmulas
        </button>
        <button class="meajuda-btn compact-btn" onclick="window.open('https://tgameajuda.com/tgareport/arquivosReport.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">📋</span>
            Todos Arquivos
        </button>
    </div>
</div>

            <!-- Configurações e Manuais -->
<div class="meajuda-card compact">
    <h3>⚙️ Configurações</h3>
    <div class="meajuda-buttons"> <!-- ⚠️ MUDAR: meajuda-buttons-grid-4 → meajuda-buttons -->
        <button class="meajuda-btn compact-btn primary" onclick="window.open('https://tgameajuda.com/tgaemail/Configura%C3%A7%C3%A3o%20E-mail%20no%20Sistema%20TGA.pdf', '_blank')">
            <span class="meajuda-btn-icon compact-icon">📧</span>
            Portas e Email
        </button>
        <button class="meajuda-btn compact-btn success" onclick="window.open('https://tgameajuda.com/tgafiscal/TREINAMENTO%20FISCAL.pdf', '_blank')">
            <span class="meajuda-btn-icon compact-icon">📚</span>
            Treinamento Fiscal
        </button>
        <button class="meajuda-btn compact-btn info" onclick="window.open('https://tgameajuda.com/tgamanuais/manuais.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">📖</span>
            Manuais Gerais
        </button>
        <button class="meajuda-btn compact-btn warning" onclick="window.open('https://tgameajuda.com/tgamanuais/perguntas-frequentes.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">❓</span>
            FAQ
        </button>
    </div>
</div>

              <!-- Ferramentas Diversas -->
<div class="meajuda-card compact">
    <h3>🛠️ Ferramentas</h3>
    <div class="meajuda-buttons"> <!-- ⚠️ MUDAR: meajuda-buttons-grid-5 → meajuda-buttons -->
        <button class="meajuda-btn compact-btn primary" onclick="window.open('https://tgameajuda.com/tgawallpaper/index.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">🖼️</span>
            Wallpapers
        </button>
        <button class="meajuda-btn compact-btn success" onclick="window.open('https://tgameajuda.com/tgasugestoes/consultasugestao.php', '_blank')">
            <span class="meajuda-btn-icon compact-icon">💡</span>
            Sugestões
        </button>
        <button class="meajuda-btn compact-btn info" onclick="window.open('https://tgameajuda.com/diversos/Qrcode/qrcode.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">🔲</span>
            Gera QRCode
        </button>
        <button class="meajuda-btn compact-btn warning" onclick="window.open('https://tgameajuda.com/diversos/Conta%20texto/contatexto-auto.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">📝</span>
            Conta Texto
        </button>
    </div>
</div>

                <!-- Validações e Testes -->
<div class="meajuda-card compact">
    <h3>✅ Validações</h3>
    <div class="meajuda-buttons"> <!-- ⚠️ MUDAR: meajuda-buttons-grid-4 → meajuda-buttons -->
        <button class="meajuda-btn compact-btn primary" onclick="window.open('https://tgameajuda.com/diversos/Valida%20chave/validachave.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">🔑</span>
            Valida Chave
        </button>
        <button class="meajuda-btn compact-btn success" onclick="window.open('https://tgameajuda.com/diversos/Valida%20boleto/validaboletoauto.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">💰</span>
            Valida Boleto
        </button>
        <button class="meajuda-btn compact-btn warning" onclick="window.open('https://tgameajuda.com/diversos/Provas/provas.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">🧠</span>
            Teste Conhecimento
        </button>
        <button class="meajuda-btn compact-btn info" onclick="window.open('https://tgameajuda.com/diversos/Provas/RegimeFiscal.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">📊</span>
            Prova Fiscal
        </button>
    </div>
</div>

               <!-- Tickets e Tempo -->
<div class="meajuda-card compact">
    <h3>⏰ Tickets</h3>
    <div class="meajuda-buttons"> <!-- ⚠️ MUDAR: meajuda-buttons-grid-4 → meajuda-buttons -->
        <button class="meajuda-btn compact-btn primary" onclick="window.open('https://tgameajuda.com/diversos/tickets/intervalotickets.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">🎫</span>
            Vários Tickets
        </button>
        <button class="meajuda-btn compact-btn info" onclick="window.open('https://tgameajuda.com/diversos/Intervalo%20de%20tempo/index.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">⏱️</span>
            Intervalo Tempo
        </button>
        <button class="meajuda-btn compact-btn success" onclick="window.open('https://tgameajuda.com/diversos/Hora%20trabalhada/index.html', '_blank')">
            <span class="meajuda-btn-icon compact-icon">👷</span>
            Hora Trabalhada
        </button>
    </div>
</div>
            </div>
        </div>
    `;
    
    document.getElementById('content').innerHTML = content;
}

// Modal Management for MeAjuda Section
function openMeAjudaModal(type) {
    let modalContent = '';
    const modalTitle = getMeAjudaModalTitle(type);
    
    switch(type) {
        case 'vincular-app':
            modalContent = getVincularAppContent();
            break;
        case 'chave-api':
            modalContent = getChaveApiContent();
            break;
        default:
            modalContent = '<div class="info-section"><p>Conteúdo não disponível.</p></div>';
    }
    
    const modalHTML = `
        <div class="meajuda-modal-content">
            <div class="meajuda-modal-header">
                <h2>${modalTitle}</h2>
                <button class="close-meajuda-modal" onclick="closeMeAjudaModal()">&times;</button>
            </div>
            <div class="meajuda-info-grid">
                ${modalContent}
            </div>
        </div>
    `;
    
    document.getElementById('meajudaModal').innerHTML = modalHTML;
    document.getElementById('meajudaModal').style.display = 'block';
}

function closeMeAjudaModal() {
    document.getElementById('meajudaModal').style.display = 'none';
}

function getMeAjudaModalTitle(type) {
    const titles = {
        'vincular-app': '🔗 Vincular APP - Revenda',
        'chave-api': '🔑 Solicitação - Chave API'
    };
    
    return titles[type] || 'Informações';
}

// Modal Content Functions
function getVincularAppContent() {
    return `
        <div class="info-section">
            <h3>🔗 Vincular APP - Revenda</h3>
            <div class="form-link">
                <strong>URL:</strong> 
                <a href="https://atendimento.poscontrole.com.br/form/5218" target="_blank">
                    https://atendimento.poscontrole.com.br/form/5218
                </a>
            </div>
            
            <h4>📝 Campos do Formulário:</h4>
            <div class="form-fields-grid">
                <div class="form-field-item">
                    <strong>Nome:</strong> TGA SISTEMAS
                </div>
                <div class="form-field-item">
                    <strong>Email:</strong> pos.controle@tgasistemas.com.br
                </div>
                <div class="form-field-item">
                    <strong>Serviço:</strong> Selecionar de acordo com o adquirente
                </div>
                <div class="form-field-item">
                    <strong>CNPJ do cliente:</strong> Informar sem pontuação
                </div>
                <div class="form-field-item">
                    <strong>Aplicativo:</strong> PDV.MOBI
                </div>
            </div>
            
            <div class="alert">
                <strong>⚠️ Observações:</strong>
                <ul>
                    <li>Descrições podem mudar de acordo com cada adquirente</li>
                    <li>Pode pedir CODE, Código serial da maquininha, etc.</li>
                    <li>O CNPJ sempre será do cliente</li>
                    <li>Para maquininhas da REDE (modelos SR e SD): apenas enviar a solicitação</li>
                </ul>
            </div>
        </div>
    `;
}

function getChaveApiContent() {
    return `
        <div class="info-section">
            <h3>🔑 Solicitação - Chave API</h3>
            <div class="form-link">
                <strong>URL:</strong> 
                <a href="https://atendimento.poscontrole.com.br/form/9464/" target="_blank">
                    https://atendimento.poscontrole.com.br/form/9464/
                </a>
            </div>
            
            <h4>📝 Campos do Formulário:</h4>
            <div class="form-fields-grid">
                <div class="form-field-item">
                    <strong>Nome:</strong> TGA SISTEMAS
                </div>
                <div class="form-field-item">
                    <strong>Email:</strong> pos.controle@tgasistemas.com.br
                </div>
                <div class="form-field-item">
                    <strong>Serviço:</strong> Solicitação Chave API
                </div>
                <div class="form-field-item">
                    <strong>Formulário - API:</strong> Selecionar a opção desejada
                </div>
                <div class="form-field-item">
                    <strong>Revenda - CNPJ:</strong> 06949316000100
                </div>
                <div class="form-field-item">
                    <strong>Cliente:</strong> Informar Razão Social e CNPJ (sem pontuação)
                </div>
            </div>
            
            <div class="highlight">
                <strong>ℹ️ Informações importantes:</strong>
                <ul>
                    <li><strong>Prazo para liberação:</strong> até 48h úteis</li>
                    <li><strong>Múltiplas solicitações:</strong> listar em sequência na seção "Cliente"</li>
                    <li><strong>CNPJ:</strong> sempre sem pontuação (apenas números)</li>
                </ul>
            </div>
        </div>
    `;
}

// Formulários Modal Functions
function openFormulariosModal() {
    const modalHTML = `
        <div class="modal-content large-modal">
            <div class="modal-header">
                <h2>📝 Formulários Smart - Todas as Operadoras</h2>
                <button class="close-modal" onclick="closeFormulariosModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="formularios-container">
                    <div class="formularios-grid-5">
                        <button class="formulario-card" onclick="showFormulario('bin')">
                            <span class="formulario-icon">🏦</span>
                            <span class="formulario-title">BIN</span>
                        </button>
                        <button class="formulario-card" onclick="showFormulario('caixa')">
                            <span class="formulario-icon">🏦</span>
                            <span class="formulario-title">CAIXA</span>
                        </button>
                        <button class="formulario-card" onclick="showFormulario('cielo-dx8000')">
                            <span class="formulario-icon">🔷</span>
                            <span class="formulario-title">CIELO DX8000</span>
                        </button>
                        <button class="formulario-card" onclick="showFormulario('cielo-lio')">
                            <span class="formulario-icon">🔷</span>
                            <span class="formulario-title">CIELO LIO</span>
                        </button>
                        <button class="formulario-card" onclick="showFormulario('clover')">
                            <span class="formulario-icon">🍀</span>
                            <span class="formulario-title">CLOVER</span>
                        </button>
                        <button class="formulario-card" onclick="showFormulario('elgin-m10')">
                            <span class="formulario-icon">📱</span>
                            <span class="formulario-title">ELGIN M10</span>
                        </button>
                        <button class="formulario-card" onclick="showFormulario('elgin-pay')">
                            <span class="formulario-icon">💳</span>
                            <span class="formulario-title">ELGIN PAY</span>
                        </button>
                        <button class="formulario-card" onclick="showFormulario('getnet')">
                            <span class="formulario-icon">🟣</span>
                            <span class="formulario-title">GETNET</span>
                        </button>
                        <button class="formulario-card" onclick="showFormulario('pagseguro')">
                            <span class="formulario-icon">🟢</span>
                            <span class="formulario-title">PAGSEGURO</span>
                        </button>
                        <button class="formulario-card" onclick="showFormulario('rede')">
                            <span class="formulario-icon">🔴</span>
                            <span class="formulario-title">REDE</span>
                        </button>
                        <button class="formulario-card" onclick="showFormulario('redeflex')">
                            <span class="formulario-icon">🔄</span>
                            <span class="formulario-title">REDEFLEX</span>
                        </button>
                        <button class="formulario-card" onclick="showFormulario('safra')">
                            <span class="formulario-icon">🟡</span>
                            <span class="formulario-title">SAFRA</span>
                        </button>
                        <button class="formulario-card" onclick="showFormulario('sicredi')">
                            <span class="formulario-icon">🔵</span>
                            <span class="formulario-title">SICREDI</span>
                        </button>
                        <button class="formulario-card" onclick="showFormulario('stone')">
                            <span class="formulario-icon">💎</span>
                            <span class="formulario-title">STONE</span>
                        </button>
                        <button class="formulario-card" onclick="showFormulario('vero')">
                            <span class="formulario-icon">⚡</span>
                            <span class="formulario-title">VERO</span>
                        </button>
                        <button class="formulario-card" onclick="showFormulario('modelos')">
                            <span class="formulario-icon">📸</span>
                            <span class="formulario-title">MODELOS</span>
                        </button>
                    </div>
                    
                    <div id="formulario-detalhes-content" class="formulario-detalhes-content">
                        <div class="placeholder-message">
                            <span class="placeholder-icon">📋</span>
                            <p>Selecione uma operadora para visualizar o formulário</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('formulariosModal').innerHTML = modalHTML;
    document.getElementById('formulariosModal').style.display = 'block';
}

function closeFormulariosModal() {
    document.getElementById('formulariosModal').style.display = 'none';
}

function showFormulario(tipo) {
    let content = '';
    
    switch(tipo) {
        case 'bin':
            content = getBinFormulario();
            break;
        case 'caixa':
            content = getCaixaFormulario();
            break;
        case 'cielo-dx8000':
            content = getCieloDx8000Formulario();
            break;
        case 'cielo-lio':
            content = getCieloLioFormulario();
            break;
        case 'clover':
            content = getCloverFormulario();
            break;
        case 'elgin-m10':
            content = getElginM10Formulario();
            break;
        case 'elgin-pay':
            content = getElginPayFormulario();
            break;
        case 'getnet':
            content = getGetnetFormulario();
            break;
        case 'pagseguro':
            content = getPagseguroFormulario();
            break;
        case 'rede':
            content = getRedeFormulario();
            break;
        case 'redeflex':
            content = getRedeflexFormulario();
            break;
        case 'safra':
            content = getSafraFormulario();
            break;
        case 'sicredi':
            content = getSicrediFormulario();
            break;
        case 'stone':
            content = getStoneFormulario();
            break;
        case 'vero':
            content = getVeroFormulario();
            break;
        case 'modelos':
            content = getModelosFormulario();
            break;
        default:
            content = '<div class="info-section"><p>Formulário não disponível.</p></div>';
    }
    
    document.getElementById('formulario-detalhes-content').innerHTML = content;
    document.getElementById('formulario-detalhes-content').scrollIntoView({ behavior: 'smooth' });
}

// Funções dos formulários individuais (versões simplificadas)
function getBinFormulario() {
    return `
        <div class="formulario-detalhes">
            <h3>🏦 BIN - Formulário</h3>
            <div class="alert">
                <strong>⚠️ ALERTA ⚠️</strong>
                <p>Ao configurar a seção de Integrações no portal web, certifique-se que TODAS as configurações de servidor estejam CORRETAS.</p>
            </div>
            <div class="form-section">
                <div class="form-field">
                    <label>CPF do cliente:</label>
                    <input type="text" class="form-input" placeholder="Digite o CPF">
                </div>
                <button class="submit-btn">Enviar Solicitação</button>
            </div>
        </div>
    `;
}

function getCaixaFormulario() {
    return `
        <div class="formulario-detalhes">
            <h3>🏦 CAIXA - Formulário</h3>
            <div class="form-section">
                <div class="form-field">
                    <label>CPF do cliente:</label>
                    <input type="text" class="form-input" placeholder="Digite o CPF">
                </div>
                <button class="submit-btn">Enviar Solicitação</button>
            </div>
        </div>
    `;
}

// ... (adicione as outras funções get...Formulario() seguindo o mesmo padrão)

function getStoneFormulario() {
    return `
        <div class="formulario-detalhes">
            <h3>💎 STONE - Formulário</h3>
            <div class="form-section">
                <div class="form-field">
                    <label>Stone Code:</label>
                    <input type="text" class="form-input" placeholder="Digite o stone code">
                </div>
                <div class="form-field">
                    <label>CNPJ:</label>
                    <input type="text" class="form-input" placeholder="Digite o CNPJ">
                </div>
                <button class="submit-btn">Enviar Solicitação</button>
            </div>
        </div>
    `;
}

// ... (continue com as outras funções de formulário)

// Event Listeners para o Me Ajuda
document.addEventListener('DOMContentLoaded', function() {
    const meajudaModal = document.getElementById('meajudaModal');
    const formulariosModal = document.getElementById('formulariosModal');
    
    if (meajudaModal) {
        meajudaModal.addEventListener('click', function(event) {
            if (event.target === meajudaModal) {
                closeMeAjudaModal();
            }
        });
    }
    
    if (formulariosModal) {
        formulariosModal.addEventListener('click', function(event) {
            if (event.target === formulariosModal) {
                closeFormulariosModal();
            }
        });
    }
});

// Fechar modais com ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeMeAjudaModal();
        closeFormulariosModal();
    }
});

document.getElementById('meAjudaBtn').addEventListener('click', function() {
    loadMeAjudaSection();
});