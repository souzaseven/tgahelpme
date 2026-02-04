 // Elementos DOM
  const modal = document.getElementById('modalLogins');
  const btnRelatorioLogins = document.getElementById('btnRelatorioLogins');
  const formFiltros = document.getElementById('formFiltros');
  
  // Função para abrir modal
  function abrirModal() {
    modal.classList.add('ativo');
    document.body.style.overflow = 'hidden'; // Previne scroll da página
    
    // Foco no primeiro campo
    setTimeout(() => {
      const primeiroCampo = document.querySelector('#formFiltros input, #formFiltros select');
      if (primeiroCampo) primeiroCampo.focus();
    }, 100);
  }
  
  // Função para fechar modal
  function fecharModal() {
    modal.classList.remove('ativo');
    
    // Limpa os estilos inline após a transição
    setTimeout(() => {
      document.body.style.overflow = '';
    }, 300);
  }
  
  // Event Listeners
  if (btnRelatorioLogins) {
    btnRelatorioLogins.onclick = abrirModal;
  }
  
  // Fechar modal com ESC
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.classList.contains('ativo')) {
      fecharModal();
    }
  });
  
  // Fechar modal clicando fora
  modal.addEventListener('click', (e) => {
    if (e.target === modal || e.target.classList.contains('modal-backdrop')) {
      fecharModal();
    }
  });
  formFiltros.onsubmit = async function (e) {
  e.preventDefault();

  const btnSubmit = this.querySelector('button[type="submit"]');
  const btnOriginalText = btnSubmit.innerHTML;

  try {
    btnSubmit.classList.add('loading');
    btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
    btnSubmit.disabled = true;

    const dados = new FormData(this);

    // Defaults de segurança
    if (!dados.get('ordenar_por')) dados.set('ordenar_por', 'criado_em');
    if (!dados.get('ordenar_dir')) dados.set('ordenar_dir', 'DESC');
    if (!dados.get('formato')) dados.set('formato', 'pdf');

    const params = new URLSearchParams(dados).toString();

    console.log("Enviando filtros para:", params);

    window.open(`backend/relatorio_logins.php?${params}`, '_blank');

    fecharModal();

  } catch (error) {
    console.error("Erro ao enviar filtros:", error);
    alert("Erro ao processar filtros. Tente novamente.");
  } finally {
    btnSubmit.classList.remove('loading');
    btnSubmit.innerHTML = btnOriginalText;
    btnSubmit.disabled = false;
  }
};

  // Validação em tempo real
  const inputs = formFiltros.querySelectorAll('input, select');
  inputs.forEach(input => {
    input.addEventListener('blur', function() {
      if (this.value.trim() === '' && this.hasAttribute('required')) {
        this.classList.add('campo-invalido');
      } else if (this.value.trim() !== '') {
        this.classList.remove('campo-invalido');
        this.classList.add('campo-valido');
      }
    });
    
    input.addEventListener('input', function() {
      this.classList.remove('campo-invalido', 'campo-valido');
    });
  });
