// ===================================================
// agente_listar.js — Listagem, busca e seleção de agentes
// ===================================================

let agentesCache = [];

// ---------------------------------------------------
// INIT
// ---------------------------------------------------
document.addEventListener("DOMContentLoaded", () => {
    listarAgentes();

    const busca = document.getElementById("buscaAgente");
    if (busca) {
        busca.addEventListener("keyup", filtrarAgentes);
    }
});

// ---------------------------------------------------
// BUSCAR AGENTES NO BACKEND
// ---------------------------------------------------
async function listarAgentes() {
    const container = document.getElementById("lista-agentes");

    container.innerHTML = `<p>Carregando agentes...</p>`;

    try {
        const resp = await fetch("backend/listar_agentes.php", {
            cache: "no-store"
        });

        if (!resp.ok) {
            throw new Error("Erro HTTP");
        }

        const json = await resp.json();

        if (!json.success) {
            throw new Error(json.erro || "Erro ao buscar agentes");
        }

        agentesCache = json.agentes || [];
        renderizarTabela(agentesCache);

    } catch (e) {
        console.error("[AGENTE] Erro ao listar:", e);
        container.innerHTML = `<p>Erro ao carregar agentes.</p>`;
    }
}

// ---------------------------------------------------
// RENDERIZA TABELA
// ---------------------------------------------------
function renderizarTabela(dados) {
    const container = document.getElementById("lista-agentes");

    if (!dados.length) {
        container.innerHTML = `<p>Nenhum agente encontrado.</p>`;
        return;
    }

    let html = `
        <table class="tabela-operadores">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Login</th>
                    <th>Fila</th>
                    <th>Status</th>
                    <th>Ramal</th>
                    <th>Último login</th>
                    <th class="acoes">Ações</th>
                </tr>
            </thead>
            <tbody>
    `;

    dados.forEach(a => {

        const statusClass = {
            ativo: "ativo",
            em_pausa: "pausa",
            deslogado: "inativo",
            desativado: "bloqueado",
            arquivado: "arquivado"
        }[a.status] || "inativo";

        html += `
            <tr>
                <td>${a.nome}</td>
                <td>${a.login}</td>
                <td>${a.fila ?? "-"}</td>
                <td>
                    <span class="status ${statusClass}">
                        ${a.status}
                    </span>
                </td>
                <td>${a.extensao ?? "-"}</td>
                <td>${formatarData(a.ultimo_login)}</td>
                <td class="acoes">
                    <div class="acoes-linha">
                        <a href="editar.html?id=${a.id}" class="editar">✏️ Editar</a>
                        <a href="#" class="arquivar" onclick="arquivarAgente(${a.id}); return false;">
                            🗄️ Arquivar
                        </a>
                    </div>
                </td>
            </tr>
        `;
    });

    html += `
            </tbody>
        </table>
    `;

    container.innerHTML = html;
}

// ---------------------------------------------------
// FILTRO DE BUSCA
// ---------------------------------------------------
function filtrarAgentes() {
    const termo = document
        .getElementById("buscaAgente")
        .value
        .toLowerCase();

    const filtrados = agentesCache.filter(a =>
        Object.values(a).some(v =>
            String(v).toLowerCase().includes(termo)
        )
    );

    renderizarTabela(filtrados);
}

// ---------------------------------------------------
// AÇÃO: ARQUIVAR
// ---------------------------------------------------
async function arquivarAgente(id) {
    if (!confirm("Deseja realmente arquivar este agente?")) {
        return;
    }

    try {
        const resp = await fetch("backend/logoff.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `id=${id}`
        });

        const json = await resp.json();

        if (!json.success) {
            alert(json.erro || "Erro ao arquivar agente.");
            return;
        }

        listarAgentes();

    } catch (e) {
        console.error("[AGENTE] Erro ao arquivar:", e);
        alert("Falha ao comunicar com o servidor.");
    }
}

// ---------------------------------------------------
// UTIL
// ---------------------------------------------------
function formatarData(iso) {
    if (!iso) return "-";
    const d = new Date(iso);
    return d.toLocaleString("pt-BR");
}
