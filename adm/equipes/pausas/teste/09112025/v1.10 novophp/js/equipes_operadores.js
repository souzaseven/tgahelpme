// ============================================================
// equipes_operadores.js (v3.2 - Souza System)
// ------------------------------------------------------------
// ✅ Mostra banner fixo com a equipe do operador logado
// ✅ Compatível com campo verlider (fallback para lider)
// ✅ Alterna entre “minha equipe” e “todas”
// ✅ Atualização suave, sem piscadas
// ✅ Totalmente responsivo e leve
// ============================================================

console.log("%c[Equipes] Módulo v3.2 inicializado...", "color:#00c6ff;font-weight:bold;");

let ultimoHash = "";
let debounceTimer = null;
let liderDoOperador = null;
let filtroAtivo = true;

// =============================================
// Elementos visuais e notificações
// =============================================
function criarElemento(id, estilo) {
  const el = document.createElement("div");
  el.id = id;
  Object.assign(el.style, estilo);
  document.body.appendChild(el);
  return el;
}

// 🔹 Indicador de atualização
const indicador = criarElemento("indicadorAtualizacao", {
  position: "fixed",
  bottom: "10px",
  right: "15px",
  background: "rgba(0,0,0,0.8)",
  color: "#00ff88",
  padding: "6px 12px",
  borderRadius: "8px",
  fontSize: "0.85rem",
  fontFamily: "Poppins, sans-serif",
  zIndex: "9999",
  opacity: "0",
  transition: "opacity 0.5s ease"
});

// 🔹 Toast (mensagem temporária)
const toast = criarElemento("toastEquipes", {
  position: "fixed",
  bottom: "15px",
  left: "15px",
  background: "rgba(0,0,0,0.85)",
  color: "#fff",
  padding: "10px 16px",
  borderRadius: "10px",
  fontSize: "0.9rem",
  fontFamily: "Poppins, sans-serif",
  zIndex: "9999",
  opacity: "0",
  transform: "translateY(20px)",
  transition: "all 0.5s ease"
});

// 🔹 Banner fixo de equipe (permanece visível sempre)
const banner = criarElemento("bannerEquipe", {
  position: "fixed",
  top: "0",
  left: "0",
  width: "100%",
  background: "linear-gradient(90deg, #007ced, #00c6ff)",
  color: "#fff",
  textAlign: "center",
  padding: "10px 0",
  fontFamily: "Poppins, sans-serif",
  fontSize: "1rem",
  fontWeight: "600",
  letterSpacing: "0.5px",
  boxShadow: "0 2px 10px rgba(0,0,0,0.3)",
  zIndex: "9998",
  opacity: "0",
  transition: "opacity 0.6s ease"
});

function atualizarBannerEquipe(lider) {
  if (!lider) return;
  banner.textContent = `👑 Você pertence à equipe ${lider}`;
  banner.style.opacity = "1";
  // Ajusta padding-top do body para não sobrepor o conteúdo
  document.body.style.paddingTop = "50px";
}

// =============================================
// Indicadores
// =============================================
function atualizarIndicador(msg = "") {
  indicador.textContent = msg || `✔️ Atualizado às ${new Date().toLocaleTimeString("pt-BR", { hour12: false })}`;
  indicador.style.opacity = "1";
  clearTimeout(indicador.timer);
  indicador.timer = setTimeout(() => (indicador.style.opacity = "0"), 4000);
}

function mostrarToast(texto, cor = "#00ff88") {
  toast.textContent = texto;
  toast.style.border = `1px solid ${cor}`;
  toast.style.opacity = "1";
  toast.style.transform = "translateY(0)";
  clearTimeout(toast.timer);
  toast.timer = setTimeout(() => {
    toast.style.opacity = "0";
    toast.style.transform = "translateY(20px)";
  }, 3000);
}

// =============================================
// 🔄 Carrega e exibe as equipes
// =============================================
async function carregarEquipes() {
  const container = document.getElementById("listaParticipantes");
  if (!container) return;

  try {
    const resp = await fetch("php/listar_operadores.php", { cache: "no-store" });
    const data = await resp.json();
    if (!data.success) throw new Error(data.error || "Erro ao carregar equipes.");

    const jsonStr = JSON.stringify(data.equipes);
    const hash = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(jsonStr));
    const hashHex = Array.from(new Uint8Array(hash)).map(b => b.toString(16).padStart(2, "0")).join("");

    let operadorLogado = (
      window.usuarioLogado ||
      localStorage.getItem("operador_nome") ||
      ""
    ).trim().toLowerCase();

    // 👑 Detecta equipe do operador logado
    if (!liderDoOperador && operadorLogado) {
      data.equipes.forEach(eq => {
        (eq.operadores || []).forEach(op => {
          if ((op.nome || "").trim().toLowerCase() === operadorLogado) {
            liderDoOperador = (op.verlider || eq.verlider || eq.lider || "").trim();
            localStorage.setItem("equipe_filtro", liderDoOperador.toLowerCase());
            atualizarBannerEquipe(liderDoOperador);
            mostrarToast(`👑 Equipe detectada: ${liderDoOperador}`, "#00c6ff");
          }
        });
      });
    }

    // 🔁 Evita redesenhar se não houve alteração
    if (hashHex === ultimoHash) {
      aplicarFiltroEquipes();
      return;
    }
    ultimoHash = hashHex;

    const fragment = document.createDocumentFragment();
    data.equipes.forEach(equipe => {
      const nomeLider = (equipe.verlider || equipe.lider || "Desconhecido").trim();
      const bloco = document.createElement("div");
      bloco.className = "equipe-bloco";
      bloco.dataset.lider = nomeLider.toLowerCase();

      bloco.innerHTML = `
        <h3><i class="fas fa-users"></i> Equipe <b>${nomeLider}</b></h3>
        <div class="equipe-operadores">
          ${equipe.operadores
            .map(op => {
              const status = (op.status || "disponivel").toLowerCase();
              let cor = "#00ff88", texto = "🟢 Disponível";
              if (status === "pausa") { cor = "#007ced"; texto = "☕ Em Pausa"; }
              else if (status === "espera") { cor = "#ffaa00"; texto = "⏳ Em Espera"; }
              else if (status === "expirado") { cor = "#ff4444"; texto = "🔴 Inativo"; }
              return `<div class="op-item"><strong>${op.nome}</strong><div style="color:${cor};font-weight:600;">${texto}</div></div>`;
            })
            .join("")}
        </div>`;
      fragment.appendChild(bloco);
    });

    container.innerHTML = "";
    container.appendChild(fragment);
    aplicarFiltroEquipes();
    atualizarIndicador();
  } catch (e) {
    console.error("❌ [Equipes] Erro:", e);
    container.innerHTML = `<div class="lista-vazia"><i class="fas fa-exclamation-triangle"></i> ${e.message}</div>`;
  }
}

// =============================================
// Filtro de exibição
// =============================================
function aplicarFiltroEquipes() {
  const filtro = localStorage.getItem("equipe_filtro") || "todas";
  document.querySelectorAll(".equipe-bloco").forEach(b => {
    const lider = b.dataset.lider?.toLowerCase();
    const mostrar = !filtroAtivo || filtro === "todas" || filtro === lider;
    b.style.display = mostrar ? "block" : "none";
  });
}

// =============================================
// Alternância entre modos de exibição
// =============================================
function atualizarBotaoToggle() {
  const btn = document.getElementById("btnToggleEquipes");
  if (!btn) return;
  btn.innerHTML = filtroAtivo
    ? `<i class="fas fa-eye-slash"></i> Ver todas as equipes`
    : `<i class="fas fa-eye"></i> Ver somente minha equipe`;
}

// =============================================
// Inicialização
// =============================================
document.addEventListener("DOMContentLoaded", () => {
  const btn = document.getElementById("btnToggleEquipes");
  if (btn) {
    btn.addEventListener("click", () => {
      if (!liderDoOperador) {
        alert("Aguarde o carregamento da sua equipe...");
        return;
      }
      filtroAtivo = !filtroAtivo;
      localStorage.setItem("equipe_filtro", filtroAtivo ? liderDoOperador.toLowerCase() : "todas");
      aplicarFiltroEquipes();
      atualizarBotaoToggle();
      mostrarToast(
        filtroAtivo
          ? `👥 Mostrando apenas equipe ${liderDoOperador}`
          : "🌍 Mostrando todas as equipes",
        filtroAtivo ? "#00c6ff" : "#ffaa00"
      );
    });
  }

  carregarEquipes();
  setInterval(() => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(carregarEquipes, 250);
  }, 5000);
});

console.log("%c✅ Souza System - Equipes v3.2 ativo com banner fixo de equipe!", "color:#00ff88;font-weight:bold;");
