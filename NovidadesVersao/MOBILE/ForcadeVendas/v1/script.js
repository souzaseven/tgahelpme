// =================== EFEITOS DE BOTÃO ===================
document.querySelectorAll(".btn").forEach(btn => {
  btn.addEventListener("click", () => {
    btn.style.transform = "scale(0.95)";
    setTimeout(() => btn.style.transform = "scale(1)", 150);
  });
});

// =================== ANIMAÇÃO DE ENTRADA (Scroll Reveal) ===================
document.addEventListener('DOMContentLoaded', () => {
  const fadeElements = document.querySelectorAll('.fade-in');

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.style.opacity = 1;
        entry.target.style.transform = 'translateY(0)';
      }
    });
  }, { threshold: 0.1 });

  fadeElements.forEach(el => {
    el.style.opacity = 0;
    el.style.transform = 'translateY(20px)';
    el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    observer.observe(el);
  });
});


/*desce até o titulo*/
document.addEventListener("DOMContentLoaded", () => {
  const scrollTopBtn = document.getElementById("scrollTopBtn");
  const mainContainer = document.querySelector("main.container");

  // Mostrar botão ao rolar
  window.addEventListener("scroll", () => {
    if (window.scrollY > 400) {
      scrollTopBtn.style.display = "block";
    } else {
      scrollTopBtn.style.display = "none";
    }
  });

  // Scroll para main ao clicar no botão
  scrollTopBtn.addEventListener("click", () => {
    if (mainContainer) {
      mainContainer.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  });

  // Scroll automaticamente ao carregar
  if (mainContainer) {
    mainContainer.scrollIntoView({ behavior: "smooth", block: "start" });
  }
});
