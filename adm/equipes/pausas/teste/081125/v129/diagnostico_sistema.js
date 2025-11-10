// diagnostico_sistema.js - Diagnóstico completo do sistema
console.log('🔍 === INICIANDO DIAGNÓSTICO DO SISTEMA ===');

// Aguardar o DOM carregar
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        executarDiagnosticoCompleto();
    }, 2000);
});

function executarDiagnosticoCompleto() {
    console.log('🔍 === DIAGNÓSTICO COMPLETO DO SISTEMA ===');
    
    // 1. Verificar sistemas carregados
    console.log('📋 1. SISTEMAS CARREGADOS:');
    console.log('- sistemaAutenticacao:', typeof sistemaAutenticacao !== 'undefined' ? '✅' : '❌');
    console.log('- controle:', typeof controle !== 'undefined' ? '✅' : '❌');
    console.log('- notificacoesGlobal:', typeof notificacoesGlobal !== 'undefined' ? '✅' : '❌');
    console.log('- notificacoesTempoReal:', typeof notificacoesTempoReal !== 'undefined' ? '✅' : '❌');
    console.log('- sistemaVoz:', typeof sistemaVoz !== 'undefined' ? '✅' : '❌');
    console.log('- sonsNotificacoes:', typeof sonsNotificacoes !== 'undefined' ? '✅' : '❌');
    
    // 2. Verificar status do sistema de voz
    console.log('🔊 2. SISTEMA DE VOZ:');
    if (typeof sistemaVoz !== 'undefined') {
        const statusVoz = sistemaVoz.getStatus();
        console.log('- Status:', statusVoz);
        console.log('- Vozes disponíveis:', sistemaVoz.vozesDisponiveis.length);
        sistemaVoz.vozesDisponiveis.forEach((voz, index) => {
            console.log(`  ${index + 1}. ${voz.name} (${voz.lang})`);
        });
    } else {
        console.log('❌ Sistema de voz não carregado');
    }
    
    // 3. Verificar permissões
    console.log('🔔 3. PERMISSÕES:');
    if ("Notification" in window) {
        console.log('- Notificações:', Notification.permission);
    } else {
        console.log('❌ Navegador não suporta notificações');
    }
    
    // 4. Testar funcionalidades
    console.log('🎯 4. TESTES MANUAIS:');
    
    // Testar voz
    console.log('🗣️ Testando voz...');
    if (typeof sistemaVoz !== 'undefined') {
        sistemaVoz.falarNotificacao('Teste de voz do sistema de diagnóstico');
    }
    
    // Testar notificação Windows
    console.log('🪟 Testando notificação Windows...');
    if (typeof sistemaVoz !== 'undefined') {
        sistemaVoz.mostrarNotificacaoWindows("Teste Diagnóstico", "Esta é uma notificação de teste do sistema");
    }
    
    // Testar notificação global
    console.log('🌐 Testando notificação global...');
    if (typeof notificacoesTempoReal !== 'undefined') {
        notificacoesTempoReal.enviarNotificacaoGlobal(
            '🔔 TESTE: Notificação de diagnóstico do sistema', 
            'info', 
            5000
        );
    }
    
    // 5. Verificar se há controles na tela
    console.log('🎛️ 5. CONTROLES NA TELA:');
    const controles = document.getElementById('controles-voz');
    if (controles) {
        console.log('✅ Controles de voz encontrados na tela');
    } else {
        console.log('❌ Controles de voz NÃO encontrados na tela');
    }
    
    console.log('🔍 === FIM DO DIAGNÓSTICO ===');
    
    // Criar botão de teste na interface
    criarBotaoTeste();
}

function criarBotaoTeste() {
    const testButton = document.createElement('button');
    testButton.textContent = '🧪 Testar Sistema';
    testButton.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #ff6b00;
        color: white;
        border: none;
        padding: 10px 15px;
        border-radius: 5px;
        cursor: pointer;
        z-index: 10000;
        font-size: 14px;
        font-weight: bold;
    `;
    testButton.onclick = function() {
        console.log('🧪 === TESTE MANUAL INICIADO ===');
        
        // Teste 1: Voz
        if (sistemaVoz) {
            console.log('🗣️ Testando voz...');
            sistemaVoz.falarNotificacao('Teste manual do sistema de voz');
        }
        
        // Teste 2: Notificação Windows
        if (sistemaVoz) {
            console.log('🪟 Testando notificação Windows...');
            sistemaVoz.mostrarNotificacaoWindows("Teste Manual", "Notificação de teste manual do sistema");
        }
        
        // Teste 3: Notificação Global
        if (notificacoesTempoReal) {
            console.log('🌐 Testando notificação global...');
            notificacoesTempoReal.enviarNotificacaoGlobal(
                '🧪 TESTE MANUAL: Notificação de teste', 
                'success', 
                5000
            );
        }
        
        console.log('🧪 === TESTE MANUAL FINALIZADO ===');
    };
    
    document.body.appendChild(testButton);
}

// Teste inicial rápido
console.log('🔍 Verificação inicial dos sistemas:');
console.log('- sistemaVoz:', typeof sistemaVoz);
console.log('- notificacoesTempoReal:', typeof notificacoesTempoReal);