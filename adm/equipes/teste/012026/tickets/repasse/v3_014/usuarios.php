<?php
require_once __DIR__ . '/includes/session.php';

if (empty($_SESSION['usuario_id'])) { header('Location: login.php'); exit; }
if (empty($_SESSION['is_admin']))   { header('Location: index.php');  exit; }

$nomeUsuario = htmlspecialchars($_SESSION['nome']);
$sessaoId    = (int) $_SESSION['usuario_id'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários — Repasse</title>
    <link rel="icon" href="https://tgameajuda.com/img/principal/bot-tga.webp" type="image/x-icon">
    <link rel="stylesheet" href="assets/style.css">
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-E7ZNTJSRYR"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-E7ZNTJSRYR');
    </script>
    <style>
        /* ── Tabela de usuários ── */
        .users-table { width: 100%; border-collapse: collapse; min-width: 820px; }
        .users-table th {
            background: var(--bg); color: var(--muted);
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .6px; padding: 10px 14px;
            text-align: left; border-bottom: 2px solid var(--border);
            white-space: nowrap;
        }
        .users-table td {
            padding: 10px 14px; border-bottom: 1px solid var(--border);
            font-size: 13px; vertical-align: middle;
        }
        .users-table tbody tr:nth-child(even) { background: rgba(148, 163, 184, .06); }
        .users-table tbody tr:hover { background: var(--bg); }

        .perm-badges { display: flex; flex-wrap: wrap; gap: 4px; }
        .perm-badge  { padding: 2px 8px; border-radius: 999px; font-size: 10px; font-weight: 700; white-space: nowrap; }
        .perm-acesso { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .perm-admin  { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .perm-outro  { background: var(--tef-bg); color: var(--tef-color); border: 1px solid #93c5fd; }
        .perm-none   { color: var(--muted); font-size: 12px; }
        body.dark .perm-acesso { background: #052e16; color: #86efac; border-color: #166534; }
        body.dark .perm-admin  { background: #2d1a00; color: #fcd34d; border-color: #92400e; }

        /* ── Status (badge) ── */
        .status-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 3px 10px; border-radius: 999px;
            font-size: 11px; font-weight: 700; white-space: nowrap;
        }
        .status-liberado  { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .status-bloqueado { background: var(--bg); color: var(--muted); border: 1px solid var(--border); }
        body.dark .status-liberado  { background: #052e16; color: #86efac; border-color: #166534; }
        body.dark .status-bloqueado { background: #0f172a; color: var(--muted); border-color: var(--border); }

        /* ── Barra de estatísticas ── */
        .users-stats-bar {
            display: flex; flex-wrap: wrap; gap: 12px;
            padding: 0 18px 16px;
        }
        .mini-card {
            background: var(--bg); border: 1px solid var(--border);
            border-radius: 8px; padding: 8px 16px; min-width: 110px;
        }
        .mini-card-label { font-size: 11px; color: var(--muted); margin-bottom: 2px; }
        .mini-card-value { font-size: 18px; font-weight: 700; color: var(--text); }

        /* ── Avatar ── */
        .user-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            display: inline-flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 700; font-size: 14px; flex-shrink: 0;
        }
        .user-info { display: flex; align-items: center; gap: 10px; }
        .user-info-text strong { display: block; font-size: 13px; }
        .user-info-text span   { font-size: 11px; color: var(--muted); }

        .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
        .dot-ativo   { background: #16a34a; }
        .dot-inativo { background: #94a3b8; }

        /* ── Modal ── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(2, 6, 23, .65);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 200;
        }
        .modal-overlay[hidden] { display: none !important; }

        .modal-box {
            width: min(100%, 720px);
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        .modal-lg { max-width: 620px; }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
        }

        .modal-header h2 { font-size: 17px; }

        .modal-close {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--muted);
            cursor: pointer;
            font-size: 14px;
        }

        .modal-close:hover { border-color: var(--primary); color: var(--primary); }

        .modal-body {
            padding: 16px 18px 18px;
            max-height: min(78vh, 760px);
            overflow-y: auto;
        }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        @media (max-width: 520px) { .form-row { grid-template-columns: 1fr; } }

        .form-label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 5px; text-transform: uppercase; letter-spacing: .4px; }
        .form-hint  { font-size: 11px; color: var(--muted); margin-top: 4px; }

        select.form-control option { background: var(--surface); color: var(--text); }

        .section-divider {
            border: none; border-top: 1px solid var(--border);
            margin: 20px 0;
        }
        .section-title {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .6px; color: var(--muted); margin-bottom: 12px;
        }

        /* ── Toggles de permissão ── */
        .perm-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        @media (max-width: 480px) { .perm-grid { grid-template-columns: 1fr; } }

        .perm-toggle {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border);
            cursor: pointer; transition: border-color .15s, background .15s; gap: 10px;
        }
        .perm-toggle:hover { background: var(--bg); }
        .perm-toggle.ativo { border-color: #2563eb; background: #eff6ff; }
        body.dark .perm-toggle.ativo { background: #1e3a5f; border-color: #3b82f6; }
        .perm-toggle.destaque.ativo  { border-color: #16a34a; background: #f0fdf4; }
        body.dark .perm-toggle.destaque.ativo { background: #052e16; border-color: #16a34a; }

        .ptl { flex: 1; }
        .ptl strong { font-size: 13px; display: block; color: var(--text); }
        .ptl span   { font-size: 11px; color: var(--muted); }

        .toggle-sw {
            width: 36px; height: 20px; background: #cbd5e1; border-radius: 999px;
            position: relative; transition: background .2s; flex-shrink: 0;
        }
        .toggle-sw::after {
            content: ''; position: absolute; top: 3px; left: 3px;
            width: 14px; height: 14px; background: #fff; border-radius: 50%;
            transition: transform .2s;
        }
        .toggle-sw.on         { background: #2563eb; }
        .toggle-sw.on::after  { transform: translateX(16px); }
        .perm-toggle.destaque .toggle-sw.on { background: #16a34a; }
        body.dark .toggle-sw { background: #475569; }

        /* ── Rodapé modal ── */
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border); }

        .action-col { white-space: nowrap; }
        .btn-danger-outline {
            background: transparent;
            color: #dc2626;
            border: 1px solid #fca5a5;
        }
        .btn-danger-outline:hover {
            background: #fee2e2;
            border-color: #dc2626;
            color: #991b1b;
        }
        body.dark .btn-danger-outline {
            color: #fca5a5;
            border-color: #7f1d1d;
        }
        body.dark .btn-danger-outline:hover {
            background: #450a0a;
            border-color: #dc2626;
            color: #fecaca;
        }
    </style>
</head>
<body>
<script src="assets/common.js?v=1"></script>

<!-- HEADER -->
<header class="topbar">
    <div class="topbar-left">
        <span class="topbar-icon">👥</span>
        <h1 class="topbar-title">Gerenciar Usuários</h1>
    </div>
    <div class="topbar-right">
        <span class="badge-admin">Admin</span>
        <span class="topbar-user">👤 <?= $nomeUsuario ?></span>
        <a href="historico.php" class="btn btn-outline btn-sm">📋 Histórico</a>
        <a href="index.php"     class="btn btn-outline btn-sm">← Painel</a>
        <button id="btn-tema" class="btn btn-outline btn-sm">🌙</button>
        <a href="logout.php" class="btn btn-outline btn-sm">Sair</a>
    </div>
</header>

<!-- CONTEÚDO -->
<main class="main-content" style="padding-bottom: 40px;">
    <div class="team-card">
        <div class="team-header">
            <div class="team-header-info">
                <h2>Usuários do Sistema</h2>
                <p>Cadastre e gerencie permissões de acesso</p>
            </div>
            <div class="team-header-actions" style="display:flex;gap:10px;align-items:center;">
                <input type="text" id="busca-usuario" class="form-control" placeholder="🔍 Buscar por nome ou e-mail..." style="width:220px;">
                <button class="btn btn-success btn-sm" id="btn-novo">+ Novo Usuário</button>
            </div>
        </div>
        <div class="users-stats-bar">
            <div class="mini-card">
                <div class="mini-card-label">Total</div>
                <div class="mini-card-value" id="stat-total">0</div>
            </div>
            <div class="mini-card">
                <div class="mini-card-label">Liberados</div>
                <div class="mini-card-value" id="stat-liberados">0</div>
            </div>
            <div class="mini-card">
                <div class="mini-card-label">Bloqueados</div>
                <div class="mini-card-value" id="stat-bloqueados">0</div>
            </div>
        </div>
        <div class="table-wrap">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Acesso Repasse</th>
                        <th>Permissões</th>
                        <th>Cadastro</th>
                        <th style="width:100px;"></th>
                    </tr>
                </thead>
                <tbody id="tbody-usuarios">
                    <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--muted);">
                        <div class="spinner" style="margin:0 auto 12px;"></div>Carregando...
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- MODAL CRIAR / EDITAR -->
<div id="modal-usuario" class="modal-overlay" hidden>
    <div class="modal-box modal-lg">
        <div class="modal-header">
            <h2 id="modal-titulo">Novo Usuário</h2>
            <button class="modal-close" id="modal-fechar">✕</button>
        </div>
        <div class="modal-body">

            <!-- Dados pessoais -->
            <p class="section-title">Dados pessoais</p>
            <div class="form-row" style="margin-bottom:14px;">
                <div>
                    <label class="form-label" for="f-nome">Nome <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="f-nome" class="form-control" placeholder="Nome completo ou apelido">
                </div>
                <div>
                    <label class="form-label" for="f-email">E-mail</label>
                    <input type="email" id="f-email" class="form-control" placeholder="email@exemplo.com">
                </div>
            </div>
            <div class="form-row" style="margin-bottom:14px;">
                <div>
                    <label class="form-label" for="f-telefone">Telefone</label>
                    <input type="text" id="f-telefone" class="form-control" placeholder="(00) 00000-0000">
                </div>
                <div>
                    <label class="form-label" for="f-sexo">Sexo</label>
                    <select id="f-sexo" class="form-control">
                        <option value="">— Não informado —</option>
                        <option value="M">Masculino</option>
                        <option value="F">Feminino</option>
                    </select>
                </div>
            </div>

            <hr class="section-divider">

            <!-- Senha -->
            <p class="section-title" id="label-senha">Senha <span style="color:var(--danger)" id="senha-obrig">*</span></p>
            <div class="form-row">
                <div>
                    <input type="password" id="f-senha" class="form-control" placeholder="Nova senha" autocomplete="new-password">
                    <p class="form-hint" id="hint-senha">Mínimo 4 caracteres. Salva com bcrypt.</p>
                </div>
                <div>
                    <input type="password" id="f-senha2" class="form-control" placeholder="Confirmar senha" autocomplete="new-password">
                </div>
            </div>

            <hr class="section-divider">

            <!-- Permissões -->
            <p class="section-title">Permissões</p>
            <div class="perm-grid" id="perm-grid"></div>

            <div class="modal-footer">
                <button class="btn btn-outline-dark" id="modal-cancelar">Cancelar</button>
                <button class="btn btn-success" id="modal-salvar">💾 Salvar</button>
            </div>
        </div>
    </div>
</div>

<!-- TOAST -->
<div id="toast" class="toast" hidden></div>

<script>
'use strict';

const SESSAO_ID = <?= json_encode($sessaoId) ?>;

// ── Permissões disponíveis ────────────────────────────────────────────────────
const PERMS = [
    { key: 'editaticket',          label: 'Acesso Repasse',   desc: 'Pode logar no sistema',        destaque: true },
    { key: 'is_admin',             label: 'Administrador',    desc: 'Acesso total ao sistema'                      },
    { key: 'admweb',               label: 'Admin Web',        desc: 'Nível alternativo de admin'                   },
    { key: 'editafila',            label: 'Editar Fila',      desc: 'Gerencia fila de atendimento'                 },
    { key: 'edita_fila_telefone',  label: 'Editar Fila Tel.', desc: 'Edita filas de telefone'                      },
    { key: 'ligacao_evolux',       label: 'Ligação Evolux',   desc: 'Acesso a ligações Evolux'                     },
];

// ── Estado ────────────────────────────────────────────────────────────────────
let usuarios  = [];
let modoModal = 'criar'; // 'criar' | 'editar'
let editandoId = null;
let permState  = {};
let filtroBusca = '';

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    carregarUsuarios();

    document.getElementById('busca-usuario').addEventListener('input', e => {
        filtroBusca = e.target.value.trim().toLowerCase();
        renderTabela();
    });

    document.getElementById('btn-novo').addEventListener('click', abrirModalNovo);
    document.getElementById('modal-fechar').addEventListener('click', fecharModal);
    document.getElementById('modal-cancelar').addEventListener('click', fecharModal);
    document.getElementById('modal-usuario').addEventListener('click', e => {
        if (e.target === e.currentTarget) fecharModal();
    });
    document.getElementById('modal-salvar').addEventListener('click', salvarUsuario);
});

// ── Carregar tabela ───────────────────────────────────────────────────────────
async function carregarUsuarios() {
    try {
        const res = await fetch('api/usuarios.php?acao=listar');
        usuarios  = await res.json();
        renderTabela();
    } catch (e) {
        mostrarToast('Erro ao carregar usuários.', 'error');
    }
}

function renderTabela() {
    const tbody = document.getElementById('tbody-usuarios');

    atualizarStats();

    if (!Array.isArray(usuarios) || !usuarios.length) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:32px;color:var(--muted);">Nenhum usuário encontrado.</td></tr>';
        return;
    }

    const filtrados = filtroBusca
        ? usuarios.filter(u =>
            (u.nome || '').toLowerCase().includes(filtroBusca) ||
            (u.email || '').toLowerCase().includes(filtroBusca))
        : usuarios;

    if (!filtrados.length) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:32px;color:var(--muted);">Nenhum usuário encontrado para "${esc(filtroBusca)}".</td></tr>`;
        return;
    }

    tbody.innerHTML = filtrados.map(u => {
        const ini    = (u.nome || '?')[0].toUpperCase();
        const acesso = u.editaticket == 1;
        const data   = u.data_cadastro ? u.data_cadastro.split(' ')[0].split('-').reverse().join('/') : '—';

        return `<tr>
            <td>
                <div class="user-info">
                    <div class="user-avatar">${esc(ini)}</div>
                    <div class="user-info-text">
                        <strong>${esc(u.nome)}</strong>
                        <span>${esc(u.email || '—')}</span>
                    </div>
                </div>
            </td>
            <td>
                <span class="status-badge ${acesso ? 'status-liberado' : 'status-bloqueado'}">
                    <span class="status-dot ${acesso ? 'dot-ativo' : 'dot-inativo'}"></span>
                    ${acesso ? 'Liberado' : 'Bloqueado'}
                </span>
            </td>
            <td><div class="perm-badges">${badgesPermissoes(u)}</div></td>
            <td style="color:var(--muted);font-size:12px;">${data}</td>
            <td class="action-col">
                <button class="btn btn-outline-dark btn-sm" onclick="abrirModalEditar(${u.id})">✏️ Editar</button>
                ${botaoExcluir(u)}
            </td>
        </tr>`;
    }).join('');
}

function atualizarStats() {
    const total = Array.isArray(usuarios) ? usuarios.length : 0;
    const liberados = Array.isArray(usuarios) ? usuarios.filter(u => u.editaticket == 1).length : 0;
    document.getElementById('stat-total').textContent      = total;
    document.getElementById('stat-liberados').textContent  = liberados;
    document.getElementById('stat-bloqueados').textContent = total - liberados;
}

function botaoExcluir(u) {
    const adminUser = Number(u.is_admin) === 1 || Number(u.admweb) === 1;
    if (adminUser) {
        return `<button class="btn btn-danger-outline btn-sm" disabled title="Usuário administrador não pode ser excluído.">🗑 Excluir</button>`;
    }

    if (Number(u.id) === Number(SESSAO_ID)) {
        return `<button class="btn btn-danger-outline btn-sm" disabled title="Você não pode excluir seu próprio usuário.">🗑 Excluir</button>`;
    }

    return `<button class="btn btn-danger-outline btn-sm" onclick="excluirUsuario(${u.id}, '${esc(u.nome)}')">🗑 Excluir</button>`;
}

function badgesPermissoes(u) {
    const b = [];
    if (u.editaticket == 1)        b.push('<span class="perm-badge perm-acesso">Repasse</span>');
    if (u.is_admin    == 1)        b.push('<span class="perm-badge perm-admin">Admin</span>');
    if (u.admweb      == 1)        b.push('<span class="perm-badge perm-admin">AdmWeb</span>');
    if (u.editafila   == 1)        b.push('<span class="perm-badge perm-outro">Fila</span>');
    if (u.edita_fila_telefone == 1)b.push('<span class="perm-badge perm-outro">Fila Tel.</span>');
    if (u.ligacao_evolux == 1)     b.push('<span class="perm-badge perm-outro">Evolux</span>');
    return b.length ? b.join('') : '<span class="perm-none">Sem permissões</span>';
}

// ── Modal: Novo ───────────────────────────────────────────────────────────────
function abrirModalNovo() {
    modoModal  = 'criar';
    editandoId = null;
    permState  = {};
    PERMS.forEach(p => { permState[p.key] = false; });

    document.getElementById('modal-titulo').textContent = 'Novo Usuário';
    document.getElementById('senha-obrig').hidden        = false;
    document.getElementById('hint-senha').textContent    = 'Obrigatória. Salva com bcrypt.';

    preencherCampos({ nome:'', email:'', telefone:'', sexo:'' });
    document.getElementById('f-senha').value  = '';
    document.getElementById('f-senha2').value = '';

    renderPerms();
    document.getElementById('modal-usuario').hidden = false;
    document.getElementById('f-nome').focus();
}

// ── Modal: Editar ─────────────────────────────────────────────────────────────
function abrirModalEditar(id) {
    const u = usuarios.find(x => x.id == id);
    if (!u) return;

    modoModal  = 'editar';
    editandoId = id;
    permState  = {};
    PERMS.forEach(p => { permState[p.key] = u[p.key] == 1; });

    document.getElementById('modal-titulo').textContent = `Editar — ${u.nome}`;
    document.getElementById('senha-obrig').hidden       = true;
    document.getElementById('hint-senha').textContent   = 'Deixe em branco para não alterar.';

    preencherCampos(u);
    document.getElementById('f-senha').value  = '';
    document.getElementById('f-senha2').value = '';

    renderPerms();
    document.getElementById('modal-usuario').hidden = false;
    document.getElementById('f-nome').focus();
}

function preencherCampos(u) {
    document.getElementById('f-nome').value     = u.nome     || '';
    document.getElementById('f-email').value    = u.email    || '';
    document.getElementById('f-telefone').value = u.telefone || '';
    document.getElementById('f-sexo').value     = u.sexo     || '';
}

// ── Render toggles de permissão ───────────────────────────────────────────────
function renderPerms() {
    const grid = document.getElementById('perm-grid');
    grid.innerHTML = PERMS.map(p => {
        const on  = permState[p.key];
        const cls = `perm-toggle${p.destaque ? ' destaque' : ''}${on ? ' ativo' : ''}`;
        return `
        <label class="${cls}" id="pt-${p.key}">
            <div class="ptl">
                <strong>${p.label}</strong>
                <span>${p.desc}</span>
            </div>
            <div class="toggle-sw${on ? ' on' : ''}" id="sw-${p.key}"></div>
            <input type="checkbox" hidden ${on ? 'checked' : ''}
                onchange="togglePerm('${p.key}', this.checked)">
        </label>`;
    }).join('');
}

function togglePerm(key, val) {
    permState[key] = val;
    document.getElementById(`pt-${key}`).classList.toggle('ativo', val);
    document.getElementById(`sw-${key}`).classList.toggle('on', val);
}

function fecharModal() {
    document.getElementById('modal-usuario').hidden = true;
}

// ── Salvar ────────────────────────────────────────────────────────────────────
async function salvarUsuario() {
    const nome   = document.getElementById('f-nome').value.trim();
    const senha  = document.getElementById('f-senha').value;
    const senha2 = document.getElementById('f-senha2').value;

    if (!nome) { mostrarToast('O campo Nome é obrigatório.', 'error'); return; }
    if (modoModal === 'criar' && !senha) { mostrarToast('A senha é obrigatória para novos usuários.', 'error'); return; }
    if (senha && senha !== senha2)        { mostrarToast('As senhas não coincidem.', 'error'); return; }
    if (senha && senha.length < 4)        { mostrarToast('A senha deve ter ao menos 4 caracteres.', 'error'); return; }

    const btn       = document.getElementById('modal-salvar');
    btn.disabled    = true;
    btn.textContent = '⏳ Salvando...';

    const payload = {
        nome,
        email:     document.getElementById('f-email').value.trim(),
        telefone:  document.getElementById('f-telefone').value.trim(),
        sexo:      document.getElementById('f-sexo').value,
        nova_senha: senha,
        ...permState,
    };

    const acao = modoModal === 'criar' ? 'criar' : 'salvar';
    if (modoModal === 'editar') payload.id = editandoId;
    if (modoModal === 'criar')  payload.senha = senha;

    try {
        const res  = await fetch(`api/usuarios.php?acao=${acao}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const json = await res.json();

        if (json.success) {
            mostrarToast(json.mensagem, 'success');
            fecharModal();
            await carregarUsuarios();
        } else {
            mostrarToast(json.erro || 'Erro ao salvar.', 'error');
        }
    } catch (e) {
        mostrarToast('Falha na comunicação com o servidor.', 'error');
    } finally {
        btn.disabled    = false;
        btn.textContent = '💾 Salvar';
    }
}

async function excluirUsuario(id, nome) {
    if (!confirm(`Deseja realmente excluir o usuário "${nome}"?`)) return;

    try {
        const res = await fetch('api/usuarios.php?acao=excluir', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        });
        const json = await res.json();

        if (json.success) {
            mostrarToast(json.mensagem, 'success');
            await carregarUsuarios();
        } else {
            mostrarToast(json.erro || 'Erro ao excluir usuário.', 'error');
        }
    } catch (e) {
        mostrarToast('Falha na comunicação com o servidor.', 'error');
    }
}

// ── Utilitários ───────────────────────────────────────────────────────────────
function esc(s) {
    return String(s ?? '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

</script>
</body>
</html>
