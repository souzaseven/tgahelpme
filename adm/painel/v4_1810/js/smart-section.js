// Smart Section JavaScript - Dark Mode
function loadSmartSection() {
    const content = `
        <div class="smart-section">
            <div class="smart-header">
                <h1>📱 Sessão Smart</h1>
                <p class="subtitle">Ferramentas e informações para configurações PDV.MOBI</p>
            </div>
            
            <div class="smart-grid-4">
                <!-- Cadastro e Configurações -->
                <div class="smart-card compact">
                    <h3>📋 Cadastro e Configurações</h3>
                    <div class="smart-buttons compact">
                        <button class="smart-btn compact-btn" onclick="openSmartModal('cadastro')">
                            <span class="smart-btn-icon compact-icon">⚙️</span>
                            Cadastro Smart
                        </button>
                        <button class="smart-btn compact-btn secondary" onclick="openSmartModal('senhas')">
                            <span class="smart-btn-icon compact-icon">🔑</span>
                            Senhas/Links
                        </button>
                        <button class="smart-btn compact-btn success" onclick="openSmartModal('procedimento')">
                            <span class="smart-btn-icon compact-icon">🔄</span>
                            Troca Maquininha
                        </button>
                    </div>
                </div>

                <!-- Painéis e Acesso -->
                <div class="smart-card compact">
                    <h3>📊 Painéis e Acesso</h3>
                    <div class="smart-buttons compact">
                        <button class="smart-btn compact-btn info" onclick="window.open('https://tga.pdv.mobi/POSAuth/#/monitor', '_blank')">
                            <span class="smart-btn-icon compact-icon">📱</span>
                            Painel Mobi V1
                        </button>
                        <button class="smart-btn compact-btn info" onclick="window.open('https://tga.v2.pdv.mobi/login', '_blank')">
                            <span class="smart-btn-icon compact-icon">🚀</span>
                            Painel Mobi V2
                        </button>
                        <button class="smart-btn compact-btn warning" onclick="window.open('https://tgameajuda.com/tgatef/configsmartpos.html#vincular-app', '_blank')">
                            <span class="smart-btn-icon compact-icon">🔗</span>
                            Config Smart POS
                        </button>
                    </div>
                </div>

                <!-- Solicitações -->
                <div class="smart-card compact">
                    <h3>📨 Solicitações</h3>
                    <div class="smart-buttons compact">
                        <button class="smart-btn compact-btn" onclick="openSmartModal('vincular-app')">
                            <span class="smart-btn-icon compact-icon">🔗</span>
                            Vincular APP
                        </button>
                        <button class="smart-btn compact-btn secondary" onclick="openSmartModal('chave-api')">
                            <span class="smart-btn-icon compact-icon">🔑</span>
                            Chave API
                        </button>
                        <button class="smart-btn compact-btn success" onclick="openFormulariosModal()">
                            <span class="smart-btn-icon compact-icon">📝</span>
                            Formulários
                        </button>
                    </div>
                </div>

                <!-- Status e Monitoramento -->
              <div class="smart-card compact">
                    <h3>📈 Status</h3>
                        <button class="smart-btn compact-btn warning" onclick="window.open('https://tga.pdv.mobi/POSAuth/#/monitor', '_blank')">
                            <span class="smart-btn-icon compact-icon">👁️</span>
                            Monitor Online
                        </button>
                        <button class="smart-btn compact-btn" onclick="openSmartModal('procedimento')">
                            <span class="smart-btn-icon compact-icon">📋</span>
                            Procedimentos
                        </button>
                    </div>
                </div>

                <!-- Informações por Operadora -->
                <div class="smart-card full-width compact">
                    <h3>🏦 Operadoras</h3>
                    <div class="smart-buttons-grid-4">
                        <button class="smart-btn compact-btn stone" onclick="openSmartModal('stone')">
                            STONE
                        </button>
                        <button class="smart-btn compact-btn sicredi" onclick="openSmartModal('sicredi')">
                            SICREDI
                        </button>
                        <button class="smart-btn compact-btn safra" onclick="openSmartModal('safra')">
                            SAFRA
                        </button>
                        <button class="smart-btn compact-btn rede" onclick="openSmartModal('rede')">
                            REDE
                        </button>
                        <button class="smart-btn compact-btn pagseguro" onclick="openSmartModal('pagseguro')">
                            PAGSEGURO
                        </button>
                        <button class="smart-btn compact-btn getnet" onclick="openSmartModal('getnet')">
                            GETNET
                        </button>
                        <button class="smart-btn compact-btn cielo" onclick="openSmartModal('cielo')">
                            CIELO
                        </button>
                        <button class="smart-btn compact-btn caixa" onclick="openSmartModal('caixa')">
                            CAIXA
                        </button>
                        <button class="smart-btn compact-btn sipag" onclick="openSmartModal('sipag')">
                            SIPAG
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('content').innerHTML = content;
}


// Modal Management
function openSmartModal(type) {
    let modalContent = '';
    const modalTitle = getModalTitle(type);
    
    switch(type) {
        case 'cadastro':
            modalContent = getCadastroContent();
            break;
        case 'senhas':
            modalContent = getSenhasContent();
            break;
        case 'procedimento':
            modalContent = getProcedimentoContent();
            break;
        case 'vincular-app':
            modalContent = getVincularAppContent();
            break;
        case 'chave-api':
            modalContent = getChaveApiContent();
            break;
        case 'stone':
            modalContent = getStoneContent();
            break;
        case 'sicredi':
            modalContent = getSicrediContent();
            break;
        case 'safra':
            modalContent = getSafraContent();
            break;
        case 'rede':
            modalContent = getRedeContent();
            break;
        case 'pagseguro':
            modalContent = getPagseguroContent();
            break;
        case 'getnet':
            modalContent = getGetnetContent();
            break;
        case 'cielo':
            modalContent = getCieloContent();
            break;
        case 'caixa':
            modalContent = getCaixaContent();
            break;
        case 'sipag':
            modalContent = getSipagContent();
            break;
        default:
            modalContent = '<div class="info-section"><p>Conteúdo não disponível.</p></div>';
    }
    
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

function closeSmartModal() {
    document.getElementById('smartModal').style.display = 'none';
}

function getModalTitle(type) {
    const titles = {
        'cadastro': '⚙️ Cadastro Smart Info',
        'senhas': '🔑 Senhas e Links Úteis',
        'procedimento': '🔄 Procedimento para Troca de Maquininha',
        'vincular-app': '🔗 Vincular APP - Revenda',
        'chave-api': '🔑 Solicitação - Chave API',
        'stone': '💎 STONE - Informações',
        'sicredi': '🔵 SICREDI - Informações',
        'safra': '🟡 SAFRA - Informações',
        'rede': '🔴 REDE - Informações',
        'pagseguro': '🟢 PAGSEGURO - Informações',
        'getnet': '🟣 GETNET - Informações',
        'cielo': '🔷 CIELO - Informações',
        'caixa': '🏦 CAIXA - Informações',
        'sipag': '⚪ SIPAG - Informações'
    };
    
    return titles[type] || 'Informações Smart';
}

// Modal Content Functions
function getCadastroContent() {
    return `
        <div class="info-section">
            <h3>📋 Cadastro Smart Info</h3>
            <p>Acesse o link abaixo para configurar o cadastro smart:</p>
            <div class="highlight">
                <strong>URL:</strong> <a href="https://tgameajuda.com/tgatef/POS/login.html" target="_blank">https://tgameajuda.com/tgatef/POS/login.html</a>
            </div>
        </div>
    `;
}

function getSenhasContent() {
    return `
        <div class="info-section">
            <h3>🔑 Senhas Importantes</h3>
            <div class="password-grid">
                <div class="password-item">
                    <strong>Senha master:</strong> #@dESjO3yW
                    <span class="password-desc">(usada para acessar o ambiente do cliente)</span>
                </div>
                <div class="password-item">
                    <strong>Senha do portal de revenda:</strong> 5yEm2aid
                    <span class="password-desc">(não é utilizada)</span>
                </div>
                <div class="password-item">
                    <strong>Senha de ativação na POS:</strong> 92548637
                    <span class="password-desc">(não é utilizada)</span>
                </div>
            </div>
        </div>
        
        <div class="info-section">
            <h3>🔗 Links Úteis</h3>
            <div class="links-grid">
                <div class="link-item">
                    <strong>Logar e cadastrar clientes:</strong>
                    <a href="https://tga.pdv.mobi/POSAuth/" target="_blank">https://tga.pdv.mobi/POSAuth/</a>
                </div>
                <div class="link-item">
                    <strong>Solicitação do app:</strong>
                    <a href="https://atendimento.poscontrole.com.br/form/5218" target="_blank">https://atendimento.poscontrole.com.br/form/5218</a>
                </div>
                <div class="link-item">
                    <strong>Solicitação de chaves:</strong>
                    <a href="https://atendimento.poscontrole.com.br/form/9464/" target="_blank">https://atendimento.poscontrole.com.br/form/9464/</a>
                </div>
            </div>
            <div class="highlight">
                <strong>CNPJ da Revenda:</strong> 06949316000100
            </div>
        </div>
        
        <div class="info-section">
            <h3>⚙️ Links de Configuração</h3>
            <div class="config-links">
                <div class="config-item">
                    <strong>Configuração Sicredi:</strong> Link disponível no portal
                </div>
                <div class="config-item">
                    <strong>Configuração Caixa:</strong> Link disponível no portal
                </div>
                <div class="config-item">
                    <strong>Configuração BIN:</strong> Link disponível no portal
                </div>
            </div>
            <div class="success-alert">
                <strong>💡 Dica:</strong> Para Sicredi e Sipag, o app já vem pré-instalado na maquininha. Você só precisará obter o código da loja do cliente junto ao Sicredi.
            </div>
        </div>
    `;
}

function getProcedimentoContent() {
    return `
        <div class="info-section">
            <h3>🔄 Procedimento para Troca de Maquininha</h3>
            <p>Sempre que o cliente entrar em contato com SUPORTE para fazer substituição/troca de maquininha:</p>
            <div class="procedure-steps">
                <div class="step">
                    <span class="step-number">1</span>
                    <span class="step-text">Abrir um ticket ou usar o mesmo do atendimento</span>
                </div>
                <div class="step">
                    <span class="step-number">2</span>
                    <span class="step-text">Registrar a informação que o cliente está trocando adquirente</span>
                </div>
                <div class="step">
                    <span class="step-number">3</span>
                    <span class="step-text">Direcionar o ticket para o responsável</span>
                </div>
                <div class="step">
                    <span class="step-number">4</span>
                    <span class="step-text">Não é necessário informar o número do ticket no TEAMS</span>
                </div>
            </div>
            <div class="highlight">
                <strong>📌 Importante:</strong> Sempre que for um ticket de implantação, deve ser retornado para o implantador responsável após a conclusão das configurações.
            </div>
        </div>
        
        <div class="info-section">
            <h3>📋 Informações Necessárias por Adquirente</h3>
            <div class="acquirer-grid">
                <div class="acquirer-item">
                    <strong>BIN:</strong> Apenas o código loja (processo FISERV)
                </div>
                <div class="acquirer-item">
                    <strong>Caixa:</strong> Apenas o código loja (processo FISERV)
                </div>
                <div class="acquirer-item">
                    <strong>Cielo DX800:</strong> Código EC (12 dígitos) + Loja (4 dígitos)
                </div>
                <div class="acquirer-item">
                    <strong>Cielo Lio 2 / Lio on:</strong> Código EC (16 dígitos) + Número Lógico
                </div>
                <div class="acquirer-item">
                    <strong>GetNet:</strong> Código do Estabelecimento (EC) + Número Serial (Físico)
                </div>
                <div class="acquirer-item">
                    <strong>PagSeguro:</strong> Número Serial
                </div>
                <div class="acquirer-item">
                    <strong>Rede SD/SR:</strong> Não é necessário fazer solicitação
                </div>
                <div class="acquirer-item">
                    <strong>Rede SB:</strong> Processo mais moroso - entrar em contato
                </div>
                <div class="acquirer-item">
                    <strong>RedeFlex:</strong> Apenas CNPJ
                </div>
                <div class="acquirer-item">
                    <strong>Safra:</strong> Não é necessário fazer solicitação
                </div>
                <div class="acquirer-item">
                    <strong>Sicredi:</strong> Apenas o código loja (processo FISERV)
                </div>
                <div class="acquirer-item">
                    <strong>Stone:</strong> Stone Code + CNPJ
                </div>
                <div class="acquirer-item">
                    <strong>Sipag/Sicoob:</strong> Não é necessário fazer solicitação
                </div>
            </div>
            <div class="highlight">
                <strong>📍 Localização das informações:</strong> Sempre na aba "sobre" da configuração das maquininhas
            </div>
        </div>
    `;
}

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

// Acquirer Content Functions
function getStoneContent() {
    return `
        <div class="info-section">
            <h3>💎 STONE</h3>
            <div class="acquirer-info-grid">
                <div class="info-item">
                    <strong>🔍 Como obter o stone code?</strong>
                    <p>No terminal: <strong>Menu → Ajustes → Sobre</strong></p>
                </div>
                <div class="info-item">
                    <strong>📅 Prazo para liberar:</strong>
                    <p>1 dia útil</p>
                </div>
                <div class="info-item">
                    <strong>📥 Como instalar o app após liberação?</strong>
                    <p>Reinicie o terminal conectado à internet e a instalação será feita automaticamente.</p>
                </div>
            </div>
        </div>
    `;
}

function getSicrediContent() {
    return `
        <div class="info-section">
            <h3>🔵 SICREDI</h3>
            
            <div class="acquirer-cards">
                <div class="acquirer-card">
                    <div class="card-header success">
                        <strong>🔷 Clientes cadastrados na Sicredi com CNPJ:</strong>
                    </div>
                    <div class="card-steps">
                        <div class="step">1. Gerar código de loja na maquininha (Manual)</div>
                        <div class="step">2. Configurar a integração de TEF no portal web</div>
                        <div class="step">3. Instalar o APP</div>
                    </div>
                </div>
                
                <div class="acquirer-card">
                    <div class="card-header warning">
                        <strong>🔶 Clientes cadastrados na Sicredi com CPF:</strong>
                    </div>
                    <div class="card-steps">
                        <div class="step">1. Solicitar código de loja (preencher formulário)</div>
                        <div class="step">2. Configurar a integração de TEF no portal web</div>
                        <div class="step">3. Instalar o APP</div>
                    </div>
                </div>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <strong>📅 Prazo para liberar o código de loja:</strong>
                    <ul>
                        <li><strong>Clientes com CNPJ:</strong> Diretamente na maquininha</li>
                        <li><strong>Clientes com CPF:</strong> Até 3 dias úteis</li>
                    </ul>
                </div>
                
                <div class="info-item">
                    <strong>📥 Como instalar o app?</strong>
                    <ul>
                        <li>Na tela inicial da máquina, deslize o dedo da direita para a esquerda</li>
                        <li>Selecione o aplicativo "Loja de Apps"</li>
                        <li>Após encontrar o App "PDV MOBI", baixe e instale</li>
                    </ul>
                </div>
            </div>
        </div>
    `;
}

function getSafraContent() {
    return `
        <div class="info-section">
            <h3>🟡 SAFRA</h3>
            <div class="alert">
                <strong>⚠️ ATENÇÃO!</strong> Não é necessário preencher solicitação, apenas seguir a instrução de instalação do APP.
            </div>
            
            <div class="info-item">
                <strong>📥 Como instalar o app?</strong>
                <div class="installation-steps">
                    <div class="step">1. Clique sobre o ícone "Safrapay Store", presente na tela inicial de apps</div>
                    <div class="step">2. Após abertura da Loja, clique na barra de busca</div>
                    <div class="step">3. Digite o nome do App desejado PDV MOBI ou COMANDA MOBI</div>
                    <div class="step">4. Após encontrar o App, basta clicar no botão "Instalar"</div>
                </div>
            </div>
        </div>
    `;
}

function getRedeContent() {
    return `
        <div class="info-section">
            <h3>🔴 REDE</h3>
            <div class="alert">
                <strong>⚠️ ATENÇÃO!</strong>
                <ul>
                    <li>Máquinas com etiquetas SR/SD → Não é necessário preencher solicitação</li>
                    <li>Máquinas com etiquetas SB → Necessário ter TEF cadastrado e OTP no ambiente</li>
                </ul>
            </div>
            
            <div class="info-item">
                <strong>🔍 Como identificar qual APP instalar?</strong>
                <p>Solicite ao cliente o print da etiqueta que fica no fundo da máquina:</p>
                <div class="rede-types">
                    <div class="type-item success">
                        <strong>Etiquetas SD:</strong> Conexão direta, único app na loja
                    </div>
                    <div class="type-item info">
                        <strong>Etiquetas SR:</strong> Conexão direta, versão app gertec-rede
                    </div>
                    <div class="type-item warning">
                        <strong>Etiquetas SB:</strong> Conexão TEF, versão app gertec-softwareExpress
                    </div>
                </div>
            </div>
            
            <div class="info-item">
                <strong>📥 Como instalar o app? (Modelo L400 - SD)</strong>
                <div class="installation-steps">
                    <div class="step">1. Clique sobre o ícone "Rede Store"</div>
                    <div class="step">2. Na Loja, clique na "Lupa"</div>
                    <div class="step">3. Digite PDV MOBI ou COMANDA MOBI</div>
                    <div class="step">4. Clique em "Instalar"</div>
                </div>
            </div>
        </div>
    `;
}

function getPagseguroContent() {
    return `
        <div class="info-section">
            <h3>🟢 PAGSEGURO</h3>
            <div class="info-grid">
                <div class="info-item">
                    <strong>🔍 Como obter o Número Serial (SN)?</strong>
                    <p>Está localizado atrás da bandeja de impressão do terminal</p>
                </div>
                <div class="info-item">
                    <strong>📅 Prazo para liberar:</strong>
                    <p>24hs úteis</p>
                </div>
                <div class="info-item">
                    <strong>📥 Como instalar o app após liberação?</strong>
                    <ul>
                        <li>Clique sobre o ícone "Loja de apps"</li>
                        <li>Digite na busca o nome do App desejado</li>
                        <li>Baixe e instale</li>
                    </ul>
                </div>
            </div>
        </div>
    `;
}

function getGetnetContent() {
    return `
        <div class="info-section">
            <h3>🟣 GETNET</h3>
            <div class="info-grid">
                <div class="info-item">
                    <strong>🔍 Como obter o EC?</strong>
                    <p>No terminal: <strong>App de pagamento → Configuração → Sobre</strong></p>
                </div>
                <div class="info-item">
                    <strong>📅 Prazo para liberar:</strong>
                    <p>48 horas</p>
                </div>
                <div class="info-item">
                    <strong>📥 Como instalar o app após liberação?</strong>
                    <p>Acesse a GetStore e baixe o app</p>
                </div>
            </div>
            <div class="alert">
                <strong>⚠️ ATENÇÃO:</strong> Insira o Código do Estabelecimento (EC) completo, incluindo os zeros iniciais
            </div>
        </div>
    `;
}

function getCieloContent() {
    return `
        <div class="info-section">
            <h3>🔷 CIELO</h3>
            <div class="info-grid">
                <div class="info-item">
                    <strong>🔍 Como obter o EC?</strong>
                    <p>No terminal: <strong>Ajuda → Minha Lio → Sobre</strong></p>
                </div>
                <div class="info-item">
                    <strong>📅 Prazo para liberar:</strong>
                    <p>1 dia útil</p>
                </div>
                <div class="info-item">
                    <strong>📥 Como instalar o app após liberação?</strong>
                    <p>No terminal: <strong>Ajuda → Minha Lio → Buscar atualizações</strong></p>
                </div>
            </div>
        </div>
    `;
}

function getCaixaContent() {
    return `
        <div class="info-section">
            <h3>🏦 CAIXA</h3>
            
            <div class="acquirer-cards">
                <div class="acquirer-card">
                    <div class="card-header success">
                        <strong>🔷 Clientes cadastrados na Caixa com CNPJ:</strong>
                    </div>
                    <div class="card-steps">
                        <div class="step">1. Gerar código de loja na maquininha (Manual)</div>
                        <div class="step">2. Configurar a integração de TEF no portal web</div>
                        <div class="step">3. Instalar o APP</div>
                    </div>
                </div>
                
                <div class="acquirer-card">
                    <div class="card-header warning">
                        <strong>🔶 Clientes cadastrados na Caixa com CPF:</strong>
                    </div>
                    <div class="card-steps">
                        <div class="step">1. Solicitar código de loja (preencher formulário)</div>
                        <div class="step">2. Configurar a integração de TEF no portal web</div>
                        <div class="step">3. Instalar o APP</div>
                    </div>
                </div>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <strong>📅 Prazo para liberar o código de loja:</strong>
                    <ul>
                        <li><strong>Clientes com CNPJ:</strong> Diretamente na maquininha</li>
                        <li><strong>Clientes com CPF:</strong> Até 3 dias úteis</li>
                    </ul>
                </div>
                
                <div class="info-item">
                    <strong>📥 Como instalar o app?</strong>
                    <ul>
                        <li>Na tela inicial da máquina, deslize o dedo da direita para a esquerda</li>
                        <li>Selecione o aplicativo "Loja de Apps"</li>
                        <li>Após encontrar o App "PDV MOBI", baixe e instale</li>
                    </ul>
                </div>
            </div>
        </div>
    `;
}

function getSipagContent() {
    return `
        <div class="info-section">
            <h3>⚪ SIPAG</h3>
            <div class="alert">
                <strong>⚠️ ATENÇÃO!</strong> Não é preciso solicitar o vínculo para SIPAG. O app já vem pré-instalado na maquininha.
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

// Funções dos formulários individuais (TODAS INCLUÍDAS)
function getBinFormulario() {
    return `
        <div class="formulario-detalhes">
            <h3>🏦 BIN - Formulário</h3>
            
            <div class="alert">
                <strong>⚠️ ALERTA ⚠️</strong>
                <p>Ao configurar a seção de Integrações no portal web, certifique-se que TODAS as configurações de servidor estejam CORRETAS e CORRESPONDAM à ADQUIRENTE do seu cliente, principalmente as configurações de TEF IP.</p>
                <p><strong>Exemplo: Adquirente: BIN → TEF IP correto: https://binpos.softwareexpress.com.br</strong></p>
            </div>
            
            <div class="highlight">
                <h4>📋 INFORMAÇÕES</h4>
                <p>Para utilizar o terminal Bin DX8000 é necessário seguir uma das etapas abaixo:</p>
                
                <div class="info-grid">
                    <div class="info-item">
                        <strong>🔷 Clientes cadastrados na Bin com CNPJ:</strong>
                        <ol>
                            <li>Gerar código de loja na maquininha (Manual)</li>
                            <li>Configurar a integração de TEF no portal web (Configuração das máquinas Bin)</li>
                            <li>Instalar o APP</li>
                        </ol>
                    </div>
                    
                    <div class="info-item">
                        <strong>🔶 Clientes cadastrados na Bin com CPF:</strong>
                        <ol>
                            <li>Solicitar código de loja (preencher este formulário)</li>
                            <li>Configurar a integração de TEF no portal web</li>
                            <li>Instalar o APP</li>
                        </ol>
                    </div>
                </div>
                
                <div class="alert">
                    <strong>ATENÇÃO:</strong> Só preencha o formulário caso seu cliente esteja CADASTRADO NA BIN com CPF e necessite SOLICITAR o CÓDIGO DE LOJA.
                </div>
                
                <div class="info-item">
                    <strong>📅 Prazo para liberar o código de loja:</strong>
                    <ul>
                        <li><strong>Clientes com CNPJ:</strong> Diretamente na maquininha</li>
                        <li><strong>Clientes com CPF:</strong> Até 3 dias úteis</li>
                    </ul>
                    <p><em>Obs.: Consultamos o código diretamente com a Software Express.</em></p>
                </div>
                
                <div class="info-item">
                    <strong>📥 Como instalar o app?</strong>
                    <ul>
                        <li>Na tela inicial da máquina, deslize o dedo da direita para a esquerda</li>
                        <li>Selecione o aplicativo "Loja de Apps"</li>
                        <li>Após encontrar o App "PDV MOBI", baixe e instale</li>
                    </ul>
                    <p><em>Obs.: Apenas o app PDV MOBI está disponível na adquirente BIN.</em></p>
                </div>
            </div>
            
            <div class="form-section">
                <h4>📝 FORMULÁRIO - BIN</h4>
                <div class="form-field">
                    <label>CPF do cliente:</label>
                    <input type="text" class="form-input" placeholder="Digite o CPF">
                </div>
                
                <div class="form-field">
                    <label>Já possui o número lógico? Caso sim, por favor insira aqui:</label>
                    <input type="text" class="form-input" placeholder="TFI">
                </div>
                
                <div class="alert">
                    <strong>💡 ATENÇÃO:</strong> Caso o cliente deseje TRANSACIONAR PIX é necessário no momento dessa solicitação acessar o APP GESTÃO e CADASTRAR a CHAVE PIX.
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
            
            <div class="alert">
                <strong>⚠️ ALERTA ⚠️</strong>
                <p>Ao configurar a seção de Integrações no portal web, certifique-se que TODAS as configurações de servidor estejam CORRETAS e CORRESPONDAM à ADQUIRENTE do seu cliente, principalmente as configurações de TEF IP.</p>
                <p><strong>Exemplo: Adquirente: CAIXA → TEF IP correto: https://caixapos.softwareexpress.com.br</strong></p>
            </div>
            
            <div class="highlight">
                <h4>📋 INFORMAÇÕES</h4>
                <p>Para utilizar o terminal Caixa DX8000 é necessário seguir uma das etapas abaixo:</p>
                
                <div class="info-grid">
                    <div class="info-item">
                        <strong>🔷 Clientes cadastrados na Caixa com CNPJ:</strong>
                        <ol>
                            <li>Gerar código de loja na maquininha (Manual)</li>
                            <li>Configurar a integração de TEF no portal web</li>
                            <li>Instalar o APP</li>
                        </ol>
                    </div>
                    
                    <div class="info-item">
                        <strong>🔶 Clientes cadastrados na Caixa com CPF:</strong>
                        <ol>
                            <li>Solicitar código de loja (preencher este formulário)</li>
                            <li>Configurar a integração de TEF no portal web</li>
                            <li>Instalar o APP</li>
                        </ol>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h4>📝 FORMULÁRIO - CAIXA</h4>
                <div class="form-field">
                    <label>CPF do cliente:</label>
                    <input type="text" class="form-input" placeholder="Digite o CPF">
                </div>
                
                <div class="form-field">
                    <label>Já possui o número lógico? Caso sim, por favor insira aqui:</label>
                    <input type="text" class="form-input" placeholder="TFI">
                </div>
                
                <div class="alert">
                    <strong>💡 ATENÇÃO:</strong> Caso o cliente deseje TRANSACIONAR PIX é necessário no momento dessa solicitação acessar o APP GESTÃO e CADASTRAR a CHAVE PIX.
                </div>
                
                <button class="submit-btn">Enviar Solicitação</button>
            </div>
        </div>
    `;
}

function getCieloDx8000Formulario() {
    return `
        <div class="formulario-detalhes">
            <h3>🔷 CIELO DX8000 - Formulário</h3>
            
            <div class="highlight">
                <h4>📋 INFORMAÇÕES</h4>
                
                <div class="info-item">
                    <strong>📅 Prazo para liberar:</strong>
                    <ul>
                        <li>1 dia útil</li>
                    </ul>
                </div>
                
                <div class="info-item">
                    <strong>🔍 Como obter o EC e loja?</strong>
                    <ul>
                        <li>No terminal vá em Configuração > Sobre</li>
                    </ul>
                </div>
                
                <div class="info-item">
                    <strong>📥 Como instalar o app após liberação?</strong>
                    <ul>
                        <li>No terminal vá em Configurações > Aplicativos Baixados > 3 pontinhos > Mostrar demais apps > Atualizar todos os aplicativos</li>
                    </ul>
                </div>
            </div>
            
            <div class="form-section">
                <h4>📝 FORMULÁRIO - CIELO DX8000</h4>
                <div class="form-field">
                    <label>Código EC (12 dígitos):</label>
                    <input type="text" class="form-input" placeholder="Digite o código EC">
                </div>
                
                <div class="form-field">
                    <label>Loja (4 dígitos):</label>
                    <input type="text" class="form-input" placeholder="Digite o código da loja">
                </div>
                
                <button class="submit-btn">Enviar Solicitação</button>
            </div>
        </div>
    `;
}

function getCieloLioFormulario() {
    return `
        <div class="formulario-detalhes">
            <h3>🔷 CIELO LIO2 / LIO ON - Formulário</h3>
            
            <div class="highlight">
                <h4>📋 INFORMAÇÕES</h4>
                
                <div class="info-item">
                    <strong>🔍 Como obter o EC?</strong>
                    <ul>
                        <li>No terminal vá em Ajuda > Minha Lio > Sobre</li>
                    </ul>
                </div>
                
                <div class="info-item">
                    <strong>📅 Prazo para liberar:</strong>
                    <ul>
                        <li>1 dia útil</li>
                    </ul>
                </div>
                
                <div class="info-item">
                    <strong>📥 Como instalar o app após liberação?</strong>
                    <ul>
                        <li>No terminal vá em Ajuda > Minha Lio > Buscar atualizações</li>
                    </ul>
                </div>
            </div>
            
            <div class="form-section">
                <h4>📝 FORMULÁRIO - CIELO LIO2/LIO ON</h4>
                <div class="form-field">
                    <label>Código EC (16 dígitos):</label>
                    <input type="text" class="form-input" placeholder="Digite o código EC">
                </div>
                
                <div class="form-field">
                    <label>Número Lógico:</label>
                    <input type="text" class="form-input" placeholder="Digite o número lógico">
                </div>
                
                <button class="submit-btn">Enviar Solicitação</button>
            </div>
        </div>
    `;
}

function getCloverFormulario() {
    return `
        <div class="formulario-detalhes">
            <h3>🍀 CLOVER - Formulário</h3>
            
            <div class="alert">
                <strong>ATENÇÃO!</strong>
                <p>Não é necessário preencher essa solicitação. Basta seguir as instruções de instalação do APP descritas abaixo.</p>
                <p>Se tiver qualquer dificuldade, entre em contato com nossa Central de Atendimento. Estamos aqui para ajudar! 😊</p>
            </div>
            
            <div class="highlight">
                <h4>📋 INFORMAÇÕES</h4>
                
                <div class="info-item">
                    <strong>📥 Como instalar o app:</strong>
                    <ol>
                        <li>Na tela inicial da maquininha abra o app "App Market"</li>
                        <li>Procure pelo app "PDV MOBI" ou "Comanda MOBI"</li>
                        <li>Cliquem em "Instalar"</li>
                        <li>Acesse a URL da sua revenda, realize login com CNPJ/CPF do cliente e SENHA MASTER da revenda</li>
                        <li>Vá ao menu Administração > Configuração - Integrações e na opção TEF Operadora defina como Software Express - Clover e clique em "Salvar"</li>
                        <li>Em seguida retorne para a maquininha e realize o SETUP inserindo CNPJ/CPF e SENHA de ativação ou escaneando o QR Code disponível no portal web do cliente</li>
                    </ol>
                </div>
            </div>
        </div>
    `;
}

function getElginM10Formulario() {
    return `
        <div class="formulario-detalhes">
            <h3>📱 ELGIN M10 - Formulário</h3>
            
            <div class="highlight">
                <h4>📋 INFORMAÇÕES</h4>
                <p>Para instalar o app no terminal Elgin M10 é necessário possuir um token.</p>
                
                <div class="alert">
                    <strong>ATENÇÃO:</strong> Só preencha o formulário caso JÁ POSSUA o TOKEN.
                    Após retornarmos a solicitação como APP VINCULADO, siga o passo "Como instalar o app após liberação?".
                    Caso NÃO POSSUA o TOKEN, siga o passo "Como obter o token?".
                </div>
                
                <div class="info-item">
                    <strong>🔑 Como obter o token?</strong>
                    <p>Será necessário gerar um token junto a Elgin mediante contato com o representante da Elgin.</p>
                    <ul>
                        <li>Felipe no WhatsApp 11 99956-0200</li>
                        <li>Marlon no WhatsApp 41 9672-7740</li>
                    </ul>
                </div>
                
                <div class="info-item">
                    <strong>📅 Prazo para liberar:</strong>
                    <ul>
                        <li>48 horas</li>
                    </ul>
                </div>
                
                <div class="info-item">
                    <strong>📥 Como instalar o app após liberação?</strong>
                    <ol>
                        <li>Atualizar o firmware do tablet (se disponível)</li>
                        <li>Cadastro do token no pos</li>
                    </ol>
                </div>
            </div>
            
            <div class="form-section">
                <h4>📝 FORMULÁRIO - ELGIN M10</h4>
                <div class="form-field">
                    <label>TOKEN:</label>
                    <input type="text" class="form-input" placeholder="Digite o token">
                </div>
                
                <button class="submit-btn">Enviar Solicitação</button>
            </div>
        </div>
    `;
}

function getElginPayFormulario() {
    return `
        <div class="formulario-detalhes">
            <h3>💳 ELGIN PAY - Formulário</h3>
            
            <div class="highlight">
                <h4>📋 INFORMAÇÕES</h4>
                <p>Para instalar o app no terminal Elgin PAY é necessário possuir um token.</p>
                
                <div class="alert">
                    <strong>ATENÇÃO:</strong> Só preencha o formulário caso JÁ POSSUA o TOKEN.
                    Após retornarmos a solicitação como APP VINCULADO, siga o passo "Como instalar o app após liberação?".
                    Caso NÃO POSSUA o TOKEN, siga o passo "Como obter o token?".
                </div>
                
                <div class="info-item">
                    <strong>🔑 Como obter o token?</strong>
                    <p>Será necessário gerar um token junto a Elgin mediante contato com o representante da Elgin.</p>
                    <ul>
                        <li>Felipe no WhatsApp 12 98268-9802</li>
                        <li>Samuel Moreira no WhatsApp 11 98673-4862</li>
                    </ul>
                </div>
                
                <div class="info-item">
                    <strong>📅 Prazo para liberar:</strong>
                    <ul>
                        <li>48 horas</li>
                    </ul>
                </div>
                
                <div class="info-item">
                    <strong>📥 Como instalar o app após liberação?</strong>
                    <ol>
                        <li>Clique sobre o ícone "Loja Elgin", presente na tela inicial de apps</li>
                        <li>Na tela que segue, clique no botão "Cadastrar Token"</li>
                        <li>Na tela seguinte, digite o Token</li>
                        <li>Confirme a operação clicando no botão "Cadastrar Token"</li>
                        <li>Será aberta a loja e os aplicativos estarão no ícone inferior com formato de CADEADO</li>
                        <li>Selecione o app e instale no pos</li>
                    </ol>
                </div>
            </div>
            
            <div class="form-section">
                <h4>📝 FORMULÁRIO - ELGIN PAY</h4>
                <div class="form-field">
                    <label>TOKEN:</label>
                    <input type="text" class="form-input" placeholder="Digite o token">
                </div>
                
                <button class="submit-btn">Enviar Solicitação</button>
            </div>
        </div>
    `;
}

function getGetnetFormulario() {
    return `
        <div class="formulario-detalhes">
            <h3>🟣 GETNET - Formulário</h3>
            
            <div class="highlight">
                <h4>📋 INFORMAÇÕES</h4>
                
                <div class="info-item">
                    <strong>🔍 Como obter o EC?</strong>
                    <ul>
                        <li>No terminal vá em App de pagamento > Configuração > Sobre</li>
                    </ul>
                </div>
                
                <div class="info-item">
                    <strong>📅 Prazo para liberar:</strong>
                    <ul>
                        <li>48 horas</li>
                    </ul>
                </div>
                
                <div class="info-item">
                    <strong>📥 Como instalar o app após liberação?</strong>
                    <ul>
                        <li>Acesse a GetStore e baixe o app</li>
                    </ul>
                </div>
            </div>
            
            <div class="form-section">
                <h4>📝 FORMULÁRIO - GETNET</h4>
                <div class="alert">
                    <strong>⚠️ ATENÇÃO!</strong> Insira o Código do Estabelecimento (EC) e o número do serial físico completo, incluindo os zeros iniciais.
                </div>
                
                <div class="form-field">
                    <label>Código do Estabelecimento (EC):</label>
                    <input type="text" class="form-input" placeholder="Digite o código EC completo">
                </div>
                
                <div class="form-field">
                    <label>Número Serial (Físico):</label>
                    <input type="text" class="form-input" placeholder="Digite o número serial">
                </div>
                
                <button class="submit-btn">Enviar Solicitação</button>
            </div>
        </div>
    `;
}

function getPagseguroFormulario() {
    return `
        <div class="formulario-detalhes">
            <h3>🟢 PAGSEGURO - Formulário</h3>
            
            <div class="highlight">
                <h4>📋 INFORMAÇÕES</h4>
                
                <div class="info-item">
                    <strong>🔍 Como obter o Número Serial (SN)?</strong>
                    <ul>
                        <li>O Número Serial (SN) está localizado atrás da bandeja de impressão do terminal</li>
                    </ul>
                </div>
                
                <div class="info-item">
                    <strong>📅 Prazo para liberar:</strong>
                    <ul>
                        <li>24hs úteis</li>
                    </ul>
                </div>
                
                <div class="info-item">
                    <strong>📥 Como instalar o app após liberação?</strong>
                    <ul>
                        <li>Clique sobre o ícone "Loja de apps", presente na tela inicial</li>
                        <li>Digite na busca o nome do App desejado</li>
                        <li>Após encontrar o App, baixe e instale</li>
                    </ul>
                </div>
            </div>
            
            <div class="form-section">
                <h4>📝 FORMULÁRIO - PAGSEGURO</h4>
                <div class="form-field">
                    <label>Número Serial:</label>
                    <input type="text" class="form-input" placeholder="Digite o número serial">
                </div>
                
                <button class="submit-btn">Enviar Solicitação</button>
            </div>
        </div>
    `;
}

function getRedeFormulario() {
    return `
        <div class="formulario-detalhes">
            <h3>🔴 REDE - Formulário</h3>
            
            <div class="alert">
                <strong>ATENÇÃO!</strong>
                <p>Máquinas com etiquetas SR/SD -> Não é necessário preencher essa solicitação, apenas seguir a instrução de instalação do APP abaixo.</p>
                <p>Máquinas com etiquetas SB -> Para utilizar terminais SB é necessário ter TEF cadastrado e OTP no ambiente.</p>
                <p>Se você não possui TEF solicite preenchendo o formulário disponível em <a href="https://atendimento.poscontrole.com.br/form/9239/" target="_blank">https://atendimento.poscontrole.com.br/form/9239/</a>.</p>
                <p>Importante somente realizar o SETUP nos terminais após cadastro e liberação do TEF.</p>
            </div>
            
            <div class="highlight">
                <h4>📋 INFORMAÇÕES</h4>
                
                <div class="info-item">
                    <strong>🔍 Como identificar qual APP devo instalar no terminal?</strong>
                    <p>Solicite ao cliente o print da etiqueta que fica localizada no fundo da máquina. Verifique o seguinte:</p>
                    <ul>
                        <li><strong>Etiquetas iniciadas com SD</strong> --> a máquina é conexão direta, único app na loja</li>
                        <li><strong>Etiquetas iniciadas com SR</strong> --> a máquina é conexão direta, usa a versão do app gertec-rede</li>
                        <li><strong>Etiquetas iniciadas com SB</strong> --> a máquina é conexão TEF, usa a versão do app gertec-softwareExpress</li>
                    </ul>
                </div>
            </div>
        </div>
    `;
}

function getRedeflexFormulario() {
    return `
        <div class="formulario-detalhes">
            <h3>🔄 REDEFLEX - Formulário</h3>
            
            <div class="highlight">
                <h4>📋 INFORMAÇÕES</h4>
                
                <div class="info-item">
                    <strong>📅 Prazo para liberar:</strong>
                    <ul>
                        <li>48 horas (úteis)</li>
                    </ul>
                </div>
                
                <div class="info-item">
                    <strong>📥 Como instalar o app após liberação?</strong>
                    <ol>
                        <li>Pressione o botão de liga/desliga, será exibido um menu na lateral direita da tela</li>
                        <li>Clique em "Services"</li>
                        <li>Cliquem em "TEM Call"</li>
                        <li>Em seguida clique em "Check for updates"</li>
                        <li>Aguarde o processo de instalação na tela</li>
                        <li>Ao finalizar cliquem em "Close"</li>
                        <li>Na parte inferior da tela, clique no ícone da "Casinha" e procure pelo app</li>
                        <li>Abra o app e clique em "Acessar Conta"</li>
                        <li>Faça o setup inserindo CNPJ e senha</li>
                    </ol>
                </div>
            </div>
            
            <div class="form-section">
                <h4>📝 FORMULÁRIO - REDEFLEX</h4>
                <div class="form-field">
                    <label>CNPJ:</label>
                    <input type="text" class="form-input" placeholder="Digite o CNPJ">
                </div>
                
                <div class="form-field">
                    <label>Quantidade de terminais:</label>
                    <input type="number" class="form-input" placeholder="Digite a quantidade">
                </div>
                
                <button class="submit-btn">Enviar Solicitação</button>
            </div>
        </div>
    `;
}

function getSafraFormulario() {
    return `
        <div class="formulario-detalhes">
            <h3>🟡 SAFRA - Formulário</h3>
            
            <div class="alert">
                <strong>ATENÇÃO!</strong>
                <p>Não é necessário preencher essa solicitação, apenas seguir a instrução de instalação do APP abaixo.</p>
            </div>
            
            <div class="highlight">
                <h4>📋 INFORMAÇÕES</h4>
                
                <div class="info-item">
                    <strong>📅 Prazo para liberar:</strong>
                    <ul>
                        <li>Imediato. A instalação do app é feita na loja</li>
                    </ul>
                </div>
                
                <div class="info-item">
                    <strong>📥 Como instalar o app após liberação?</strong>
                    <ol>
                        <li>Clique sobre o ícone "Safrapay Store", presente na tela inicial de apps</li>
                        <li>Após abertura da Loja, clique na barra de busca</li>
                        <li>Digite o nome do App desejado PDV MOBI ou COMANDA MOBI</li>
                        <li>Após encontrar o App, basta clicar no botão "Instalar"</li>
                    </ol>
                </div>
            </div>
        </div>
    `;
}

function getSicrediFormulario() {
    return `
        <div class="formulario-detalhes">
            <h3>🔵 SICREDI - Formulário</h3>
            
            <div class="alert">
                <strong>⚠️ ALERTA ⚠️</strong>
                <p>Ao configurar a seção de Integrações no portal web, certifique-se que TODAS as configurações de servidor estejam CORRETAS e CORRESPONDAM à ADQUIRENTE do seu cliente, principalmente as configurações de TEF IP.</p>
                <p><strong>Exemplo: Adquirente: SICREDI → TEF IP correto: https://sicredi.softwareexpress.com.br</strong></p>
            </div>
            
            <div class="highlight">
                <h4>📋 INFORMAÇÕES</h4>
                <p>Para utilizar o terminal Sicredi DX8000 é necessário seguir uma das etapas abaixo:</p>
                
                <div class="info-grid">
                    <div class="info-item">
                        <strong>🔷 Clientes cadastrados na Sicredi com CNPJ:</strong>
                        <ol>
                            <li>Gerar código de loja na maquininha (Manual)</li>
                            <li>Configurar a integração de TEF no portal web (Configuração das máquinas Sicredi)</li>
                            <li>Instalar o APP</li>
                        </ol>
                    </div>
                    
                    <div class="info-item">
                        <strong>🔶 Clientes cadastrados na Sicredi com CPF:</strong>
                        <ol>
                            <li>Solicitar código de loja (preencher este formulário)</li>
                            <li>Configurar a integração de TEF no portal web</li>
                            <li>Instalar o APP</li>
                        </ol>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h4>📝 FORMULÁRIO - SICREDI</h4>
                <div class="form-field">
                    <label>CPF do cliente:</label>
                    <input type="text" class="form-input" placeholder="Digite o CPF">
                </div>
                
                <div class="form-field">
                    <label>Já possui o número lógico? Caso sim, por favor insira aqui:</label>
                    <input type="text" class="form-input" placeholder="TFI">
                </div>
                
                <div class="alert">
                    <strong>💡 ATENÇÃO:</strong> Caso o cliente deseje TRANSACIONAR PIX é necessário no momento dessa solicitação acessar o APP GESTÃO e CADASTRAR a CHAVE PIX.
                </div>
                
                <button class="submit-btn">Enviar Solicitação</button>
            </div>
        </div>
    `;
}

function getStoneFormulario() {
    return `
        <div class="formulario-detalhes">
            <h3>💎 STONE - Formulário</h3>
            
            <div class="highlight">
                <h4>📋 INFORMAÇÕES</h4>
                
                <div class="info-item">
                    <strong>🔍 Como obter o stone code?</strong>
                    <ul>
                        <li>No terminal vá em Menu > Ajustes > Sobre</li>
                    </ul>
                </div>
                
                <div class="info-item">
                    <strong>📅 Prazo para liberar:</strong>
                    <ul>
                        <li>1 dia útil</li>
                    </ul>
                </div>
                
                <div class="info-item">
                    <strong>📥 Como instalar o app após liberação?</strong>
                    <ul>
                        <li>Reinicie o terminal conectado à internet e a instalação será feita automaticamente</li>
                    </ul>
                </div>
            </div>
            
            <div class="form-section">
                <h4>📝 FORMULÁRIO - STONE</h4>
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

function getVeroFormulario() {
    return `
        <div class="formulario-detalhes">
            <h3>⚡ VERO - Formulário</h3>
            
            <div class="highlight">
                <h4>📋 INFORMAÇÕES</h4>
                
                <div class="info-item">
                    <strong>📥 Como baixar e instalar o app?</strong>
                    <ol>
                        <li>No terminal acesse a loja de aplicativos "Vero Store"</li>
                        <li>Selecionar o app desejado (PDV MOBI / COMANDA MOBI)</li>
                        <li>Selecionar o botão "INSTALAR" para instalar o aplicativo no terminal</li>
                    </ol>
                </div>
            </div>
            
            <div class="alert">
                <strong>💡 ATENÇÃO!</strong>
                <p>Não é necessário nos enviar essa solicitação, basta seguir os passos acima e instalar o app.</p>
            </div>
        </div>
    `;
}

function getModelosFormulario() {
    return `
        <div class="formulario-detalhes">
            <h3>📸 Modelos de Terminais</h3>
            <div class="image-container">
                <img src="https://github.com/souzaseven/tgahelpme/blob/Desafios/tgatef/nova-tela-all-the-adquirentes-in-tef-clover-e-pic-pay-redeflex.png.png?raw=true" 
                     alt="Modelos de Terminais TEF" 
                     class="terminal-image"
                     onerror="this.style.display='none'">
                <div class="image-fallback">
                    <p>📱 Imagem com todos os modelos de terminais disponíveis</p>
                    <p><em>URL da imagem: https://github.com/souzaseven/tgahelpme/blob/Desafios/tgatef/nova-tela-all-the-adquirentes-in-tef-clover-e-pic-pay-redeflex.png.png?raw=true</em></p>
                </div>
            </div>
        </div>
    `;
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('smartModal');
    const formulariosModal = document.getElementById('formulariosModal');
    
    if (modal) {
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeSmartModal();
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
    
   // loadSmartSection();
});

// Fechar modal com ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeSmartModal();
        closeFormulariosModal();
    }
});