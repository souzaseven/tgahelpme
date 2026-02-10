/* =========================================================
   Bridge — Empresa → Usuários Web (isolado)
========================================================= */
(function () {

  document.addEventListener('empresa:criada', async function (e) {
    const empresa = e.detail || {};
    if (!empresa.codigo_empresa) return;

    // 1️⃣ Mostra aviso inicial
    showToast(
      `🔎 Verificando usuários da empresa ${empresa.codigo_empresa}...`,
      'info',
      4000
    );

    let totalUsuarios = 0;

    try {
      const res = await apiFetch(
        'backend/api_verifica_usuarios_empresa.php',
        {
          body: {
            codigo_empresa: empresa.codigo_empresa
          }
        }
      );

      if (res.success) {
        totalUsuarios = Number(res.total || 0);
      }
    } catch (err) {
      console.warn('[Bridge] Falha ao verificar usuários', err);
    }

    // 2️⃣ Mostra resultado da verificação
    if (totalUsuarios === 0) {
      showToast(
        '⚠️ Nenhum usuário cadastrado para esta empresa. Vamos criar agora.',
        'warning',
        5000
      );
    } else {
      showToast(
        `ℹ️ Esta empresa já possui ${totalUsuarios} usuário(s). Você pode adicionar mais.`,
        'info',
        5000
      );
    }

    // 3️⃣ Abre aba Usuários Web
    const btnUsuarios = document.querySelector(
      '.menu-item[data-tab="usuarios-web"]'
    );
    if (btnUsuarios) btnUsuarios.click();

    // 4️⃣ Abre modal normalmente (como já estava)
    setTimeout(() => {
      const btnNovo = document.getElementById('btnNovoUsuarioWeb');
      if (btnNovo) btnNovo.click();

      // 5️⃣ Pré-preenche empresa no modal
      setTimeout(() => {
        const cod = document.querySelector(
          '#modalForm [name="codigo_empresa"]'
        );
        const nome = document.querySelector(
          '#modalForm [name="nome_empresa"]'
        );

        if (cod) cod.value = empresa.codigo_empresa;
        if (nome) nome.value = empresa.nome_empresa;
      }, 150);

    }, 600);

  });

})();
