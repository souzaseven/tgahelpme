// ...existing code...
/* ── Robocopy Script Generator — script.js ── */



(function () {
    'use strict';

    // Funções utilitárias precisam estar disponíveis para todos os blocos
    function showToast(msg, type = 'info') {
        const toast = document.getElementById('toast');
        toast.textContent = msg;
        toast.className = 'toast show ' + type;
        toast.style.display = 'block';
        setTimeout(() => { toast.classList.remove('show'); toast.style.display = 'none'; }, 2200);
    }

    function saveState() {
        const origem = document.getElementById('origem').value;
        const destino = document.getElementById('destino').value;
        const codes = Array.from(document.querySelectorAll('.code-row textarea')).map(ta => ta.value);
        localStorage.setItem('rcg-origem', origem);
        localStorage.setItem('rcg-destino', destino);
        localStorage.setItem('rcg-codes', JSON.stringify(codes));
    }

    function restoreState() {
        const origem = localStorage.getItem('rcg-origem');
        const destino = localStorage.getItem('rcg-destino');
        const codes = JSON.parse(localStorage.getItem('rcg-codes') || '[]');
        if (origem) document.getElementById('origem').value = origem;
        if (destino) document.getElementById('destino').value = destino;
        if (codes.length) {
            codesList.innerHTML = '';
            codes.forEach(c => createBlock(c));
        }
    }

    // Modelos prontos de comandos
    const modelosCmd = document.getElementById('modelosCmd');
    // codesList precisa ser definido antes do uso
    const codesList = document.getElementById('codesList');
    if (modelosCmd) {
        modelosCmd.addEventListener('change', function () {
            let val = modelosCmd.value;
            let cmd = '';
            let desc = '';
            if (val === 'fdb') {
                desc = 'Listar pastas que têm $TGA.FDB';
                cmd = 'for /d %i in (S:\\TGA\\C*) do @if exist "%i\\DADOS\\$TGA.FDB" echo %i';
            } else if (val === 'contabil') {
                desc = 'Listar pastas que têm Contabil.exe';
                cmd = 'for /d %i in (S:\\TGA\\C*) do @if exist "%i\\TGA\\Contabil.exe" echo %i';
            } else if (val === 'folha') {
                desc = 'Listar pastas que têm Folha.exe';
                cmd = 'for /d %i in (S:\\TGA\\C*) do @if exist "%i\\TGA\\Folha.exe" echo %i';
            } else if (val === 'hotel') {
                desc = 'Listar pastas que têm Hotel.exe';
                cmd = 'for /d %i in (S:\\TGA\\C*) do @if exist "%i\\TGA\\Hotel.exe" echo %i';
            }
            if (cmd) {
                codesList.innerHTML = '';
                createBlock(cmd);
                document.getElementById('origem').value = '';
                document.getElementById('destino').value = '';
                render();
                saveState();
                showToast(desc + ' adicionado!');
            }
            modelosCmd.value = '';
        });
    }

    // ── Theme ───────────────────────────────────────
    const themeBtn  = document.getElementById('toggleTheme');
    const themeIcon = document.getElementById('themeIcon');

    function applyTheme(theme) {
        document.body.classList.toggle('light', theme === 'light');
        themeIcon.textContent = theme === 'light' ? '🌙' : '☀️';
    }

    themeBtn.addEventListener('click', function () {
        const isLight = document.body.classList.contains('light');
        const next = isLight ? 'dark' : 'light';
        applyTheme(next);
        localStorage.setItem('rcg-theme', next);
    });

    const saved = localStorage.getItem('rcg-theme');
    if (saved) applyTheme(saved);

    // ── Code blocks ─────────────────────────────────
    const btnAdd    = document.getElementById('btnAdd');

    function createBlock(value) {
        const row = document.createElement('div');
        row.className = 'code-row';

        const ta = document.createElement('textarea');
        ta.placeholder = 'Insira os códigos, um por linha';
        ta.value = value || '';
        ta.rows = 3;
        ta.setAttribute('aria-label', 'Bloco de códigos');
        ta.addEventListener('input', render);

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn-remove';
        btn.title = 'Remover bloco';
        btn.setAttribute('aria-label', 'Remover bloco');
        btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 4h10M6 4V2.5a.5.5 0 01.5-.5h3a.5.5 0 01.5.5V4M5 4l.5 9h5L11 4" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/></svg>';

        btn.addEventListener('click', function () {
            const all = document.querySelectorAll('.code-row');
            if (all.length > 1) {
                row.style.animation = 'none';
                row.style.opacity = '0';
                row.style.transform = 'translateY(-4px)';
                row.style.transition = 'opacity .15s, transform .15s';
                setTimeout(function () { row.remove(); render(); saveState(); }, 150);
            }
        });

        row.appendChild(ta);
        row.appendChild(btn);
        codesList.appendChild(row);
    }

    btnAdd.addEventListener('click', function () {
        createBlock();
        render();
        saveState();
        // Focus the new textarea
        const blocks = document.querySelectorAll('.code-row textarea');
        blocks[blocks.length - 1].focus();
    });

    // ── Render / generate script ─────────────────────
    const outputEl   = document.getElementById('output');
    const statCodes  = document.getElementById('statCodes');
    const statLines  = document.getElementById('statLines');

    document.getElementById('origem').addEventListener('input', function(){ render(); saveState(); });
    document.getElementById('destino').addEventListener('input', function(){ render(); saveState(); });

    function render() {
        const origem = document.getElementById('origem').value.trim();
        let   destino = document.getElementById('destino').value.trim();
        const textareas = document.querySelectorAll('.code-row textarea');

        let allCodes = [];
        textareas.forEach(function (ta) {
            ta.value.trim()
              .split(/\r?\n/)
              .map(function (l) { return l.trim(); })
              .filter(Boolean)
              .forEach(function (c) { allCodes.push(c); });
        });

        // Update stats
        statCodes.textContent = allCodes.length + (allCodes.length === 1 ? ' código' : ' códigos');

        // Feedback visual campos obrigatórios
        document.querySelectorAll('.field').forEach(f => f.classList.remove('invalid'));
        if (!origem) document.getElementById('origem').closest('.field').classList.add('invalid');
        if (!destino) document.getElementById('destino').closest('.field').classList.add('invalid');
        if (allCodes.length === 0) document.getElementById('codesList').classList.add('invalid');
        else document.getElementById('codesList').classList.remove('invalid');

        if (!origem || !destino || allCodes.length === 0) {
            const missing = [];
            if (!origem)            missing.push('origem');
            if (!destino)           missing.push('destino');
            if (allCodes.length === 0) missing.push('códigos');
            outputEl.value = ':: Preencha: ' + missing.join(', ');
            statLines.textContent = '— linhas';
            return;
        }
    // ...existing code...

    // Botão limpar tudo
    document.getElementById('btnClear').addEventListener('click', function () {
        document.getElementById('origem').value = '';
        document.getElementById('destino').value = '';
        codesList.innerHTML = '';
        createBlock();
        render();
        saveState();
        showToast('Campos limpos!');
    });

    // Botão exemplo
    document.getElementById('btnExample').addEventListener('click', function () {
        document.getElementById('origem').value = 'S:\\TGA\\TGA\\VERSOES\\26_05\\TGA';
        document.getElementById('destino').value = 'S:\\TGA\\CODIGOAQUI\\TGA';
        codesList.innerHTML = '';
        createBlock('C13060\nC14438\nC14243\nC11619');
        render();
        saveState();
        showToast('Exemplo preenchido!');
    });

        // Substitute placeholder
        let dest = destino;
        if (dest.includes('CODIGOAQUI')) {
            dest = dest.replace(/CODIGOAQUI/gi, '%i');
        } else {
            dest = dest.replace(/(\\[^\\]+)$/i, '\\%i$1');
        }

        let script = '@echo off\n';
        script += 'for %i in (\n';
        script += allCodes.map(function (c) { return '    ' + c; }).join('\n');
        script += '\n) do (\n';
        script += '    robocopy "' + origem + '" "' + dest + '" /E /IS /IT /R:1 /W:1\n';
        script += ')';

        outputEl.value = script;

        const lines = script.split('\n').length;
        statLines.textContent = lines + (lines === 1 ? ' linha' : ' linhas');

        // Salvar no histórico se mudou
        if (window._lastScript !== script && !outputEl.value.startsWith('::')) {
            addToHistory(script);
            window._lastScript = script;
        }
        renderHistory();
    }

    // Histórico de scripts
    function getHistory() {
        return JSON.parse(localStorage.getItem('rcg-history') || '[]');
    }
    function setHistory(arr) {
        localStorage.setItem('rcg-history', JSON.stringify(arr));
    }
    function addToHistory(script) {
        if (!script || script.startsWith('::')) return;
        let arr = getHistory();
        if (arr.length && arr[arr.length-1].script === script) return;
        arr.push({ script, date: new Date().toLocaleString() });
        if (arr.length > 10) arr = arr.slice(-10);
        setHistory(arr);
    }
    function renderHistory() {
        const list = document.getElementById('historyList');
        if (!list) return;
        const arr = getHistory();
        list.innerHTML = '';
        if (!arr.length) {
            const li = document.createElement('li');
            li.textContent = 'Nenhum script no histórico.';
            li.style.color = 'var(--text-muted)';
            list.appendChild(li);
            return;
        }
        arr.slice().reverse().forEach((item, idx) => {
            const li = document.createElement('li');
            const spanScript = document.createElement('span');
            spanScript.className = 'hist-script';
            spanScript.textContent = item.script.split('\n').slice(0,2).join(' ... ');
            const spanDate = document.createElement('span');
            spanDate.className = 'hist-date';
            spanDate.textContent = item.date;
            li.appendChild(spanScript);
            li.appendChild(spanDate);
            li.title = item.script;
            li.onclick = function() {
                outputEl.value = item.script;
                showToast('Script restaurado do histórico!');
            };
            list.appendChild(li);
        });
    }
    document.getElementById('btnClearHistory').addEventListener('click', function() {
        setHistory([]);
        renderHistory();
        showToast('Histórico limpo!');
    });

    // ── Copy ─────────────────────────────────────────
    const btnCopy   = document.getElementById('btnCopy');
    const copyIcon  = document.getElementById('copyIcon');
    const copyLabel = document.getElementById('copyLabel');

    btnCopy.addEventListener('click', function () {
        const val = outputEl.value;
        if (!val || val.startsWith('::')) return;

        navigator.clipboard.writeText(val).then(function () {
            btnCopy.classList.add('success');
            copyIcon.innerHTML = '<path d="M2 8l4 4 8-8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>';
            copyLabel.textContent = 'Copiado!';
            setTimeout(function () {
                btnCopy.classList.remove('success');
                copyIcon.innerHTML = '<rect x="5" y="5" width="9" height="9" rx="1.5" stroke="currentColor" stroke-width="1.25"/><path d="M3 11H2.5A1.5 1.5 0 011 9.5v-7A1.5 1.5 0 012.5 1h7A1.5 1.5 0 0111 2.5V3" stroke="currentColor" stroke-width="1.25"/>';
                copyLabel.textContent = 'Copiar script';
            }, 2000);
        });
    });

    // ── Download .bat ─────────────────────────────────
    document.getElementById('btnDownload').addEventListener('click', function () {
        const val = outputEl.value;
        if (!val || val.startsWith('::')) return;

        const blob = new Blob([val], { type: 'text/plain;charset=utf-8' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = 'robocopy_script.bat';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });

    // ── Init ─────────────────────────────────────────
    restoreState();
    if (!codesList.children.length) createBlock();
    render();

})();