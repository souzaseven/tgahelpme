/* Quallit Manager — telas com tabela (Clientes, Usuários AD, Serviços, Sessões).
   - Filtro de linhas por texto e por status, no cliente.
   - Ações destrutivas (Parar, Desativar, Limpar Perfil...) são só demonstração:
     mostram um aviso e não fazem nada.
   - Monitor de Sessões: botão "Atualizar" e alternância "Auto (30s)". */

(function () {
    "use strict";

    /* ----------------------------- Filtros ----------------------------- */
    function aplicarFiltro(tabela) {
        var estado = tabela._filtro || {};
        var termo = (estado.texto || "").trim().toLowerCase();
        var status = estado.status || "";
        var linhas = tabela.tBodies[0] ? tabela.tBodies[0].rows : [];
        var visiveis = 0;

        for (var i = 0; i < linhas.length; i++) {
            var linha = linhas[i];
            var casaTexto = !termo || linha.textContent.toLowerCase().indexOf(termo) !== -1;
            var casaStatus = !status || (linha.dataset.status || "") === status;
            var mostra = casaTexto && casaStatus;
            linha.hidden = !mostra;
            if (mostra) {
                visiveis++;
            }
        }

        var card = tabela.closest(".table-card");
        var vazio = card && card.querySelector(".table-empty");
        if (vazio) {
            vazio.hidden = visiveis !== 0;
        }
    }

    document.querySelectorAll("[data-filter-input]").forEach(function (input) {
        var tabela = document.getElementById(input.getAttribute("data-filter-target"));
        if (!tabela) {
            return;
        }
        input.addEventListener("input", function () {
            tabela._filtro = tabela._filtro || {};
            tabela._filtro.texto = input.value;
            aplicarFiltro(tabela);
        });
    });

    document.querySelectorAll("[data-filter-select]").forEach(function (select) {
        var tabela = document.getElementById(select.getAttribute("data-filter-target"));
        if (!tabela) {
            return;
        }
        select.addEventListener("change", function () {
            tabela._filtro = tabela._filtro || {};
            tabela._filtro.status = select.value;
            aplicarFiltro(tabela);
        });
    });

    /* ----------------------- Ações de demonstração --------------------- */
    var aviso;
    function mostrarAviso() {
        if (!aviso) {
            aviso = document.createElement("div");
            aviso.className = "toast";
            aviso.setAttribute("role", "status");
            document.body.appendChild(aviso);
        }
        aviso.textContent = "Ação de demonstração — sem efeito neste clone.";
        aviso.classList.add("is-visible");
        clearTimeout(mostrarAviso._t);
        mostrarAviso._t = setTimeout(function () {
            aviso.classList.remove("is-visible");
        }, 2600);
    }

    document.querySelectorAll("[data-demo-action]").forEach(function (botao) {
        botao.addEventListener("click", function (e) {
            e.preventDefault();
            mostrarAviso();
        });
    });

    var formDemo = document.querySelector("[data-demo-form]");
    if (formDemo) {
        formDemo.addEventListener("submit", function (e) {
            e.preventDefault();
            mostrarAviso();
        });
    }

    /* --------------------- Monitor de Sessões TSPlus ------------------- */
    var btnRefresh = document.querySelector("[data-monitor-refresh]");
    if (btnRefresh) {
        btnRefresh.addEventListener("click", function () {
            location.reload();
        });
    }

    var chkAuto = document.querySelector("[data-monitor-auto]");
    if (chkAuto) {
        var timer = null;
        chkAuto.addEventListener("change", function () {
            if (chkAuto.checked) {
                timer = setInterval(function () {
                    location.reload();
                }, 30000);
            } else {
                clearInterval(timer);
                timer = null;
            }
        });
    }
})();
