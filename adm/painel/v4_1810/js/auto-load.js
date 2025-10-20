// Auto Load - Carrega TGA Me Ajuda automaticamente
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Iniciando carregamento automático do TGA Me Ajuda...');
    
    // Aguarda um pouco para garantir que todos os scripts carregaram
    setTimeout(() => {
        if (typeof loadMeAjudaSection !== 'undefined') {
            loadMeAjudaSection();
            console.log('✅ TGA Me Ajuda carregado com sucesso!');
        } else {
            console.error('❌ loadMeAjudaSection não disponível');
        }
    }, 300);
});