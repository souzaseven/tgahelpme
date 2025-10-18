// =================== EFEITOS DE BOTÃO ===================
document.querySelectorAll(".btn").forEach(btn => {
  btn.addEventListener("click", () => {
    btn.style.transform = "scale(0.95)";
    setTimeout(() => btn.style.transform = "scale(1)", 150);
  });
});

// =================== ANIMAÇÃO DE ENTRADA (Scroll Reveal) ===================
document.addEventListener("DOMContentLoaded", function () {
  const backToTopBtn = document.getElementById("backToTop");

  if (backToTopBtn) {
    window.addEventListener("scroll", () => {
      if (window.scrollY > 300) {
        backToTopBtn.style.display = "block";
      } else {
        backToTopBtn.style.display = "none";
      }
    });

    backToTopBtn.addEventListener("click", () => {
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  }
});


/*Função de copiar a informação*/
function copiar(id) {
    const texto = document.getElementById(id).innerText;
    navigator.clipboard.writeText(texto).then(() => {
      alert(`Copiado: ${texto}`);
    }).catch(err => {
      console.error('Erro ao copiar', err);
    });
  }


// Botão de voltar ao topo
document.addEventListener("DOMContentLoaded", () => {
  const backToTopBtn = document.getElementById("backToTop");

  if (!backToTopBtn) return; // Proteção contra erro

  // Mostrar ou esconder botão ao rolar
  window.addEventListener("scroll", () => {
    const scrollY = window.scrollY || document.documentElement.scrollTop;
    const height = document.documentElement.scrollHeight - window.innerHeight;
    backToTopBtn.style.display = scrollY > height * 0.6 ? "block" : "none";
  });

  // Ao clicar, descer até o main.container
  backToTopBtn.addEventListener("click", () => {
    const mainContainer = document.querySelector("main.container");
    if (mainContainer) {
      mainContainer.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  });

  // Scroll automático ao carregar
  const mainContainer = document.querySelector("main.container");
  if (mainContainer) {
    mainContainer.scrollIntoView({ behavior: "smooth", block: "start" });
  }
});
