const form = document.getElementById('loginForm');
const msg  = document.getElementById('loginMsg');

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  msg.textContent = '';

  const usuario = document.getElementById('usuario')?.value.trim();
  const senha   = document.getElementById('senha')?.value;

  if (!usuario || !senha) {
    msg.textContent = 'Informe usuário e senha';
    return;
  }

  try {
    const res = await fetch('backend/login.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        nome: usuario,
        senha: senha
      })
    });

    const json = await res.json();

    if (!json.success) {
      msg.textContent = json.message || 'Usuário ou senha inválidos';
      return;
    }

    // login OK
    window.location.href = 'index.php';

  } catch (err) {
    console.error(err);
    msg.textContent = 'Erro de conexão com o servidor';
  }
});
