/* Quallit Manager — comportamento base (login + shell autenticado).
   Sem dependências. Progressive enhancement: se o JS falhar, os formulários
   continuam funcionando com envio e validação nativos. */

(function () {
    "use strict";

    /* ------------------------------------------------------------------ *
     * Login: mostrar / ocultar senha
     * ------------------------------------------------------------------ */
    var toggle = document.querySelector("[data-toggle-password]");
    if (toggle) {
        var campo = document.getElementById(toggle.getAttribute("aria-controls"));
        var rotulo = toggle.querySelector("[data-toggle-label]");

        toggle.addEventListener("click", function () {
            var mostrar = campo.type === "password";
            campo.type = mostrar ? "text" : "password";
            toggle.setAttribute("aria-pressed", String(mostrar));
            if (rotulo) {
                rotulo.textContent = mostrar ? "Ocultar" : "Mostrar";
            }
            campo.focus({ preventScroll: true });
        });
    }

    /* ------------------------------------------------------------------ *
     * Login: estado de envio (evita duplo clique, dá feedback visual)
     * ------------------------------------------------------------------ */
    var form = document.querySelector(".login-form");
    if (form) {
        form.addEventListener("submit", function () {
            if (typeof form.checkValidity === "function" && !form.checkValidity()) {
                return; // navegador exibe as mensagens nativas
            }

            var botao = form.querySelector("[data-submit]");
            if (!botao || botao.dataset.busy === "1") {
                return;
            }
            botao.dataset.busy = "1";
            botao.disabled = true;

            var spinner = botao.querySelector(".spinner");
            var rotuloBotao = botao.querySelector("[data-submit-label]");
            if (spinner) {
                spinner.hidden = false;
            }
            if (rotuloBotao) {
                rotuloBotao.textContent = "Entrando…";
            }
        });
    }

    /* ------------------------------------------------------------------ *
     * Shell: abrir/fechar o menu lateral no mobile
     * ------------------------------------------------------------------ */
    var app = document.getElementById("app");
    var abrir = document.querySelector("[data-nav-toggle]");
    var fechar = document.querySelector("[data-nav-close]");

    function setNav(aberto) {
        if (!app) {
            return;
        }
        app.classList.toggle("nav-open", aberto);
        if (abrir) {
            abrir.setAttribute("aria-expanded", String(aberto));
        }
        if (fechar) {
            fechar.hidden = !aberto;
        }
    }

    if (abrir) {
        abrir.addEventListener("click", function () {
            setNav(!app.classList.contains("nav-open"));
        });
    }
    if (fechar) {
        fechar.addEventListener("click", function () {
            setNav(false);
        });
    }
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            setNav(false);
        }
    });
})();
