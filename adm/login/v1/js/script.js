// Detecta Enter dentro do campo de senha
  document.getElementById("senhaSuporte").addEventListener("keydown", function(event) {
    if (event.key === "Enter") {
      event.preventDefault(); // evita comportamento padrão (form submit, etc)
      validarSuporte();       // chama a função que o botão "Entrar" usa
    }
  });

    lucide.createIcons();
function acessarComoCliente() {
    localStorage.setItem("acesso", "cliente");
    location.href = "cliente-tga.html"; // redireciona para página do cliente
  }

  function mostrarLoginSuporte() {
    document.getElementById("formSuporte").style.display = "block";
  }

  function validarSuporte() {
    const senha = document.getElementById("senhaSuporte").value;
    fetch("verifica_suporte.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "senha=" + encodeURIComponent(senha)
    })
    .then(r => r.json())
    .then(d => {
      if (d.sucesso) {
        localStorage.setItem("acesso", "suporte");
        location.href = "index.html";
      } else {
        document.getElementById("erroSuporte").textContent = "Senha incorreta.";
      }
    });
  }