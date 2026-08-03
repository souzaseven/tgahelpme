(function() {
  // ========== CRIAR MODAL ==========
  function criarModal() {
    if (document.getElementById('modalAnuncios')) return; // já existe um modal na página (ex.: estático no HTML)

    const modal = document.createElement('div');
    modal.id = 'modalAnuncios';
    modal.style.cssText = `
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.75);
      z-index: 99999;
      justify-content: center;
      align-items: center;
    `;
    modal.innerHTML = `
      <div style="background:#1a1a2e; border:1px solid #007ced; border-radius:16px;
                  padding:30px 40px; text-align:center; color:white; max-width:380px; width:90%;">
        <h3 style="margin:0 0 8px;">Você deseja ver anúncios?</h3>
        <p style="color:#aaa; font-size:14px; margin:0 0 20px;">Os anúncios ajudam a manter este serviço gratuito.</p>
        <div style="display:flex; gap:12px; justify-content:center;">
          <button onclick="window.definirAnuncios(true)"
                  style="background:#007ced; color:white; border:none; padding:10px 24px;
                         border-radius:8px; cursor:pointer; font-size:15px;">
            ✅ Sim
          </button>
          <button onclick="window.definirAnuncios(false)"
                  style="background:#444; color:white; border:none; padding:10px 24px;
                         border-radius:8px; cursor:pointer; font-size:15px;">
            ❌ Não
          </button>
        </div>
        <hr style="border:none; border-top:1px solid #444; margin:15px 0;">
        <button onclick="document.getElementById('modalAnuncios').style.display='none'"
                style="background:#666; color:white; border:none; padding:8px 16px;
                       border-radius:5px; cursor:pointer; font-size:12px;">
          Fechar
        </button>
      </div>
    `;
    document.body.appendChild(modal);
  }

  // ========== CRIAR BOTÃO COM X ==========
  function criarBotao() {
    const botaoAntigo = document.getElementById('btnAnuncios');
    if (botaoAntigo) {
      botaoAntigo.remove();
    }
    
    // Verificar se botão foi ocultado
    const botaoOculto = localStorage.getItem('btnAnuncioOculto');
    if (botaoOculto === 'true') {
      return;
    }
    
    const botao = document.createElement('button');
    botao.id = 'btnAnuncios';
    botao.type = 'button';
    botao.title = 'Clique para configurar anúncios';
    botao.setAttribute('aria-label', 'Configurar anúncios');
    
    botao.style.cssText = `
      position: fixed !important;
      top: 20px !important;
      right: 20px !important;
      z-index: 10001 !important;
      padding: 12px 16px !important;
      background: #007ced !important;
      color: white !important;
      border: none !important;
      border-radius: 8px !important;
      cursor: pointer !important;
      font-size: 14px !important;
      font-weight: bold !important;
      display: flex !important;
      align-items: center !important;
      gap: 8px !important;
      box-shadow: 0 4px 12px rgba(0, 124, 237, 0.3) !important;
    `;
    
    botao.innerHTML = `<span style="font-size:16px;">📢</span> <span id="textoAnuncio">Anúncios: ON</span>`;
    
    document.body.appendChild(botao);
    botao.addEventListener('click', window.abrirModalAnuncios);
    
    // ========== CRIAR X PARA OCULTAR ==========
    const xButton = document.createElement('button');
    xButton.type = 'button';
    xButton.title = 'Ocultar botão de anúncios';
    xButton.setAttribute('aria-label', 'Ocultar botão de anúncios');
    xButton.style.cssText = `
      position: fixed !important;
      top: 10px !important;
      right: 10px !important;
      z-index: 10002 !important;
      width: 24px !important;
      height: 24px !important;
      padding: 0 !important;
      background: #ff4444 !important;
      color: white !important;
      border: none !important;
      border-radius: 50% !important;
      cursor: pointer !important;
      font-size: 16px !important;
      font-weight: bold !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      box-shadow: 0 2px 8px rgba(255, 68, 68, 0.3) !important;
      opacity: 0 !important;
      transition: opacity 0.3s ease !important;
    `;
    
    xButton.innerHTML = '✕';
    
    xButton.addEventListener('click', function(e) {
      e.stopPropagation();
      localStorage.setItem('btnAnuncioOculto', 'true');
      botao.style.display = 'none';
      xButton.style.display = 'none';
      
      // Mostrar mensagem
      const msg = document.createElement('div');
      msg.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #333;
        color: white;
        padding: 12px 16px;
        border-radius: 8px;
        z-index: 10000;
        font-size: 14px;
      `;
      msg.innerHTML = 'Botão ocultado. Abra o console (F12) e execute: <br><code style="background:#222;padding:4px 8px;border-radius:4px;">recuperarBotaoAnuncios()</code> para recuperar.';
      document.body.appendChild(msg);
      
      setTimeout(() => msg.remove(), 5000);
    });
    
    document.body.appendChild(xButton);
    
    // Mostrar X ao passar o mouse
    botao.addEventListener('mouseenter', function() {
      xButton.style.opacity = '1';
    });
    
    botao.addEventListener('mouseleave', function() {
      xButton.style.opacity = '0';
    });
  }

  // ========== ABRIR MODAL ==========
  window.abrirModalAnuncios = function() {
    const modal = document.getElementById('modalAnuncios');
    if (modal) {
      modal.style.display = 'flex';
    }
  };

  // ========== APLICAR ANÚNCIOS ==========
  window.aplicarAnuncios = function() {
    const mostrar = localStorage.getItem('mostrarAnuncios');
    
    if (mostrar === null) {
      const modal = document.getElementById('modalAnuncios');
      if (modal) {
        modal.style.display = 'flex';
      }
      return;
    }

    const visivel = mostrar === 'true';
    
    document.querySelectorAll('ins.adsbygoogle').forEach(function(ad) {
      ad.style.display = visivel ? '' : 'none';
    });

    document.querySelectorAll('.ad-placeholder').forEach(function(ad) {
      ad.style.display = visivel ? '' : 'none';
    });

    const txt = document.getElementById('textoAnuncio');
    if (txt) txt.textContent = visivel ? 'Anúncios: ON' : 'Anúncios: OFF';
  };

  // ========== DEFINIR ANÚNCIOS ==========
  window.definirAnuncios = function(quer) {
    localStorage.setItem('mostrarAnuncios', quer ? 'true' : 'false');
    const modal = document.getElementById('modalAnuncios');
    if (modal) modal.style.display = 'none';
    window.aplicarAnuncios();
  };

  // ========== RECUPERAR BOTÃO ==========
  window.recuperarBotaoAnuncios = function() {
    localStorage.removeItem('btnAnuncioOculto');
    location.reload();
  };

  // ========== INICIALIZAR ==========
  function inicializar() {
    criarModal();
    criarBotao();
    window.aplicarAnuncios();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializar);
  } else {
    inicializar();
  }
})();