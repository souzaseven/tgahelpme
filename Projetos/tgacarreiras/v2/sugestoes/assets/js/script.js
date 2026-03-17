/**
 * TGA — Painel de Sugestões | script.js
 * Funcionalidades: validação, char counter, file preview,
 * drag-and-drop, priority indicator, toast, steps progress
 */

"use strict";

/* ── Seletores ──────────────────────────────────────────── */
const form        = document.getElementById("formSugestao");
const btnSubmit   = document.getElementById("btnSubmit");
const toast       = document.getElementById("toast");
const tituloInput = document.getElementById("titulo");
const descInput   = document.getElementById("descricao");
const tituloCount = document.getElementById("titulo-count");
const descCount   = document.getElementById("desc-count");
const prioSelect  = document.getElementById("prioridade");
const fileInput   = document.getElementById("anexos");
const fileDrop    = document.getElementById("fileDrop");
const filePreview = document.getElementById("filePreview");
const fileLabel   = document.getElementById("fileLabel");

let uploadedFiles = [];

/* ── Character Counter ──────────────────────────────────── */
function updateCounter(input, display, max) {
    const len = input.value.length;
    display.textContent = `${len} / ${max}`;
    const ratio = len / max;
    display.style.color = ratio > 0.9
        ? "var(--danger)"
        : ratio > 0.75
            ? "var(--warn)"
            : "var(--muted)";
}

tituloInput?.addEventListener("input", () =>
    updateCounter(tituloInput, tituloCount, 100));

descInput?.addEventListener("input", () =>
    updateCounter(descInput, descCount, 2000));

/* ── Priority color indicator ───────────────────────────── */
function applyPrioClass() {
    if (!prioSelect) return;
    prioSelect.className = prioSelect.className
        .replace(/\bprio-\S+/g, "")
        .trim();
    const val = prioSelect.value;
    if (val) prioSelect.classList.add(`prio-${val}`);
}

prioSelect?.addEventListener("change", applyPrioClass);
applyPrioClass(); // init

/* ── File upload & drag-and-drop ────────────────────────── */
function handleFiles(files) {
    const remaining = 10 - uploadedFiles.length;
    const toAdd = Array.from(files).slice(0, remaining);

    toAdd.forEach(file => {
        if (!file.type.startsWith("image/")) return;
        uploadedFiles.push(file);

        const reader = new FileReader();
        reader.onload = (e) => {
            const thumb = document.createElement("div");
            thumb.className = "file-thumb";
            thumb.dataset.index = uploadedFiles.length - 1;

            const img = document.createElement("img");
            img.src = e.target.result;
            img.alt = file.name;

            const removeBtn = document.createElement("button");
            removeBtn.type = "button";
            removeBtn.className = "file-thumb-remove";
            removeBtn.textContent = "✕";
            removeBtn.title = "Remover";
            removeBtn.addEventListener("click", () => {
                const idx = parseInt(thumb.dataset.index);
                uploadedFiles.splice(idx, 1);
                thumb.remove();
                updateFileLabel();
                filePreview.querySelectorAll(".file-thumb").forEach((t, i) => {
                    t.dataset.index = i;
                });
            });

            thumb.appendChild(img);
            thumb.appendChild(removeBtn);
            filePreview.appendChild(thumb);
        };
        reader.readAsDataURL(file);
    });

    updateFileLabel();
}

function updateFileLabel() {
    if (!fileLabel) return;
    fileLabel.textContent = uploadedFiles.length > 0
        ? `${uploadedFiles.length} arquivo(s) selecionado(s)`
        : "Arraste ou clique para selecionar";
}

fileInput?.addEventListener("change", () => handleFiles(fileInput.files));

fileDrop?.addEventListener("dragover", (e) => {
    e.preventDefault();
    fileDrop.classList.add("dragover");
});

fileDrop?.addEventListener("dragleave", () =>
    fileDrop.classList.remove("dragover"));

fileDrop?.addEventListener("drop", (e) => {
    e.preventDefault();
    fileDrop.classList.remove("dragover");
    handleFiles(e.dataTransfer.files);
});

/* ── Toast ──────────────────────────────────────────────── */
function showToast(message, type = "success", duration = 4000) {
    if (!toast) return;
    toast.className = `toast ${type} show`;
    toast.innerHTML = `
        <span class="toast-icon">${type === "success" ? "✅" : "❌"}</span>
        <span>${message}</span>
    `;
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => {
        toast.classList.remove("show");
    }, duration);
}

/* ── Steps progress ─────────────────────────────────────── */
function updateSteps(step) {
    document.querySelectorAll(".step").forEach((el, i) => {
        el.classList.remove("active", "done");
        if (i < step) el.classList.add("done");
        else if (i === step) el.classList.add("active");
    });
}

let maxStep = 0;

document.querySelectorAll("fieldset").forEach((fs, i) => {
    fs.addEventListener("focusin", () => {
        if (i > maxStep) maxStep = i;
        updateSteps(maxStep);
    });
});

/* ── Form validation ────────────────────────────────────── */
function validateForm() {
    const errors = [];
    if (!document.getElementById("tipo").value)
        errors.push("Selecione o tipo da sugestão.");
    if (!document.getElementById("titulo").value.trim())
        errors.push("O título é obrigatório.");
    if (!document.getElementById("descricao").value.trim())
        errors.push("A descrição é obrigatória.");
    return errors;
}

function markFieldError(el, hasError) {
    el.style.borderColor = hasError ? "var(--danger)" : "";
    el.style.boxShadow   = hasError ? "0 0 0 3px var(--danger-dim)" : "";
}

function resetFieldErrors() {
    ["tipo", "titulo", "descricao"].forEach(id => {
        const el = document.getElementById(id);
        if (el) markFieldError(el, false);
    });
}

/* ── Form submit ────────────────────────────────────────── */
form?.addEventListener("submit", async function (e) {
    e.preventDefault();

    resetFieldErrors();

    const errors = validateForm();

    if (errors.length > 0) {
        if (!document.getElementById("tipo").value)
            markFieldError(document.getElementById("tipo"), true);
        if (!document.getElementById("titulo").value.trim())
            markFieldError(document.getElementById("titulo"), true);
        if (!document.getElementById("descricao").value.trim())
            markFieldError(document.getElementById("descricao"), true);

        showToast(errors[0], "error");
        return;
    }

    // Estado de loading
    btnSubmit.classList.add("loading");
    btnSubmit.querySelector("span").textContent = "Enviando";
    btnSubmit.disabled = true;

    const formData = new FormData(form);
    uploadedFiles.forEach(f => formData.append("anexos[]", f));

    try {
        const res  = await fetch("./backend/salvar.php", { method: "POST", body: formData });
        const json = await res.json();

        if (!res.ok || !json.success) {
            throw new Error(json.message || "Erro ao salvar.");
        }

        showToast("Sugestão criada com sucesso! 🎉", "success");

        // Reset
        form.reset();
        uploadedFiles = [];
        if (filePreview) filePreview.innerHTML = "";
        updateFileLabel();
        applyPrioClass();
        updateSteps(0);
        maxStep = 0;
        if (tituloCount) tituloCount.textContent = "0 / 100";
        if (descCount)   descCount.textContent   = "0 / 2000";

    } catch (err) {
        showToast(err.message || "Erro ao enviar sugestão.", "error");
    } finally {
        // Sempre restaura o botão, mesmo em caso de erro
        btnSubmit.classList.remove("loading");
        btnSubmit.querySelector("span").textContent = "Enviar Sugestão";
        btnSubmit.disabled = false;
    }
});

/* ── Rascunho ───────────────────────────────────────────── */
document.querySelector(".btn-ghost")?.addEventListener("click", () => {
    const titulo = document.getElementById("titulo").value.trim();
    if (!titulo) {
        showToast("Adicione um título antes de salvar o rascunho.", "error");
        return;
    }
    const draft = {
        tipo:       document.getElementById("tipo").value,
        prioridade: document.getElementById("prioridade").value,
        modulo:     document.getElementById("modulo").value,
        titulo,
        descricao:  document.getElementById("descricao").value,
        nome:       document.getElementById("nome_solicitante").value,
        savedAt:    new Date().toISOString(),
    };
    localStorage.setItem("tga_draft", JSON.stringify(draft));
    showToast("Rascunho salvo localmente.", "success");
});

/* ── Restaurar rascunho ─────────────────────────────────── */
(function restoreDraft() {
    try {
        const raw = localStorage.getItem("tga_draft");
        if (!raw) return;
        const d = JSON.parse(raw);
        const set = (id, val) => {
            const el = document.getElementById(id);
            if (el && val) { el.value = val; el.dispatchEvent(new Event("input")); }
        };
        set("tipo",             d.tipo);
        set("prioridade",       d.prioridade);
        set("modulo",           d.modulo);
        set("titulo",           d.titulo);
        set("descricao",        d.descricao);
        set("nome_solicitante", d.nome);
        applyPrioClass();
        if (d.titulo) showToast("Rascunho restaurado automaticamente.", "success");
    } catch (_) { /* ignore */ }
})();