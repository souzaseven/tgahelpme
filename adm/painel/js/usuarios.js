const usuarios = {
    // Renderizar tabela de usuários
    renderTable(data, pagination = null) {
        let html = `
            <div class="toolbar">
                <div class="search-container">
                    <i>🔍</i>
                    <input type="text" id="searchInput" placeholder="Buscar em usuários..." onkeyup="filterTable()">
                </div>
                <button class="btn btn-primary" onclick="usuarios.showInsertForm()">
                    ➕ Novo Usuário
                </button>
                <button class="btn btn-warning" onclick="showPasswordModal()">
                    🔑 Senha SUPORTE
                </button>
            </div>
            <h2>👥 Usuários do Sistema</h2>
        `;
        
        if (data.length === 0) {
            html += `<div class="no-data">📭 Nenhum usuário encontrado.</div>`;
            document.getElementById("content").innerHTML = html;
            return;
        }

        html += `<div class="table-container"><table id="dataTable"><thead><tr>
            <th>🆔 ID</th>
            <th>👤 Nome</th>
            <th>📧 E-mail</th>
            <th>📞 Telefone</th>
            <th>🔒 Senha</th>
            <th>⚧️ Sexo</th>
            <th>📅 Data Cadastro</th>
            <th width="250">⚡ Ações</th>
        </tr></thead><tbody>`;

        // Linhas
        data.forEach(row => {
            html += `
                <tr>
                    <td>${row.id}</td>
                    <td title="${row.nome}">${truncateText(row.nome, 30)}</td>
                    <td title="${row.email}">${truncateText(row.email, 25)}</td>
                    <td>${row.telefone || '<em style="color: var(--text-muted)">VAZIO</em>'}</td>
                    <td>
                        <span id="senha-${row.id}" class="senha-oculta">•••••••</span>
                        <span id="senha-texto-${row.id}" class="senha-texto" style="display: none;">${row.senha}</span>
                    </td>
                    <td>${row.sexo}</td>
                    <td>${formatCellValue(row.data_cadastro)}</td>
                    <td class="actions">
                        <button class="btn btn-info btn-icon" onclick="usuarios.toggleSenha(${row.id})" title="Mostrar/Ocultar Senha">
                            👁️
                        </button>
                        <button class="btn btn-success btn-icon" onclick="usuarios.editRow(${row.id})" title="Editar">
                            ✏️
                        </button>
                        <button class="btn btn-danger btn-icon" onclick="deleteRow('usuarios', ${row.id})" title="Excluir">
                            🗑️
                        </button>
                    </td>
                </tr>
            `;
        });

        html += "</tbody></table></div>";
        
        // Paginação
        if (pagination) {
            currentTotal = pagination.total;
            html += renderPagination(pagination);
        }
        
        html += `<div class="stats">📈 Total: ${pagination?.total || data.length} usuário(s) encontrado(s)</div>`;
        
        document.getElementById("content").innerHTML = html;
    },

    // Mostrar/ocultar senha
    toggleSenha(id) {
        const senhaOculta = document.getElementById(`senha-${id}`);
        const senhaTexto = document.getElementById(`senha-texto-${id}`);
        
        if (senhaOculta.style.display !== 'none') {
            // Mostrar senha
            senhaOculta.style.display = 'none';
            senhaTexto.style.display = 'inline';
            
            // Alterar temporariamente o texto para ocultar após 5 segundos
            setTimeout(() => {
                if (senhaTexto.style.display !== 'none') {
                    senhaTexto.style.display = 'none';
                    senhaOculta.style.display = 'inline';
                }
            }, 5000);
        } else {
            // Ocultar senha
            senhaTexto.style.display = 'none';
            senhaOculta.style.display = 'inline';
        }
    },

    // Mostrar formulário de inserção
    showInsertForm() {
        const formFields = `
            <div class="form-group">
                <label for="nome">
                    👤 Nome Completo
                    <span class="required">*</span>
                </label>
                <input type="text" id="nome" class="form-control" 
                       placeholder="Digite o nome completo" required>
            </div>
            <div class="form-group">
                <label for="email">
                    📧 E-mail
                    <span class="required">*</span>
                </label>
                <input type="email" id="email" class="form-control" 
                       placeholder="exemplo@email.com" required>
            </div>
            <div class="form-group">
                <label for="telefone">
                    📞 Telefone
                </label>
                <input type="text" id="telefone" class="form-control" 
                       placeholder="11999999999 (apenas números)">
            </div>
            <div class="form-group">
                <label for="senha">
                    🔒 Senha
                    <span class="required">*</span>
                </label>
                <div style="position: relative;">
                    <input type="password" id="senha" class="form-control" 
                           placeholder="Digite uma senha segura" required
                           style="padding-right: 40px;">
                    <button type="button" class="btn-senha-toggle" 
                            onclick="usuarios.toggleSenhaInput('senha')"
                            style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer;">
                        👁️
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label for="sexo">
                    ⚧️ Sexo
                    <span class="required">*</span>
                </label>
                <select id="sexo" class="form-control" required>
                    <option value="">Selecione...</option>
                    <option value="Masculino">Masculino</option>
                    <option value="Feminino">Feminino</option>
                    <option value="Outro">Outro</option>
                </select>
            </div>
        `;

        document.getElementById("content").innerHTML = `
            <div class="form-container">
                <div class="form-header">
                    <h2>➕ Novo Usuário</h2>
                    <p>Preencha os dados abaixo para adicionar um novo usuário</p>
                </div>
                <form id="insertForm" onsubmit="return usuarios.insertRow(event)">
                    <div class="form-grid">
                        ${formFields}
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="loadData('usuarios')">
                            ↩️ Voltar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            💾 Salvar Usuário
                        </button>
                    </div>
                </form>
            </div>
        `;
    },

    // Alternar entre mostrar/ocultar senha no input
    toggleSenhaInput(fieldId) {
        const input = document.getElementById(fieldId);
        const button = input.parentNode.querySelector('.btn-senha-toggle');
        
        if (input.type === 'password') {
            input.type = 'text';
            button.innerHTML = '🙈';
            button.title = 'Ocultar senha';
        } else {
            input.type = 'password';
            button.innerHTML = '👁️';
            button.title = 'Mostrar senha';
        }
    },

    // Inserir usuário
    async insertRow(e) {
        e.preventDefault();
        const submitButton = e.target.querySelector('button[type="submit"]');
        
        return await withLoading(submitButton, async () => {
            const formData = {
                nome: document.getElementById('nome').value.trim(),
                email: document.getElementById('email').value.trim(),
                telefone: document.getElementById('telefone').value.trim(),
                senha: document.getElementById('senha').value.trim(),
                sexo: document.getElementById('sexo').value
            };
            
            // Validações
            if (!formData.nome || !formData.email || !formData.senha || !formData.sexo) {
                throw new Error('Todos os campos obrigatórios devem ser preenchidos');
            }

            if (!isValidEmail(formData.email)) {
                throw new Error('Por favor, insira um e-mail válido.');
            }

            const data = await apiRequest('api.php?action=insert&table=usuarios', {
                method: "POST",
                body: JSON.stringify(formData)
            });
            
            showAlert('✅ ' + data.message, 'success');
            loadData('usuarios');
        });
    },

    // Editar usuário
    async editRow(id) {
        try {
            const data = await apiRequest(`api.php?action=get&table=usuarios&id=${id}`);
            const record = data.data;
            
            const formFields = `
                <div class="form-group">
                    <label for="nome">
                        👤 Nome Completo
                        <span class="required">*</span>
                    </label>
                    <input type="text" id="nome" class="form-control" 
                           placeholder="Digite o nome completo" 
                           value="${escapeHtml(record.nome || '')}" required>
                </div>
                <div class="form-group">
                    <label for="email">
                        📧 E-mail
                        <span class="required">*</span>
                    </label>
                    <input type="email" id="email" class="form-control" 
                           placeholder="exemplo@email.com" 
                           value="${escapeHtml(record.email || '')}" required>
                </div>
                <div class="form-group">
                    <label for="telefone">
                        📞 Telefone
                    </label>
                    <input type="text" id="telefone" class="form-control" 
                           placeholder="11999999999 (apenas números)"
                           value="${escapeHtml(record.telefone || '')}">
                </div>
                <div class="form-group">
                    <label for="senha">
                        🔒 Senha
                        <span class="required">*</span>
                    </label>
                    <div style="position: relative;">
                        <input type="password" id="senha" class="form-control" 
                               placeholder="Digite uma senha segura" required
                               value="${escapeHtml(record.senha || '')}"
                               style="padding-right: 40px;">
                        <button type="button" class="btn-senha-toggle" 
                                onclick="usuarios.toggleSenhaInput('senha')"
                                style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer;">
                            👁️
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="sexo">
                        ⚧️ Sexo
                        <span class="required">*</span>
                    </label>
                    <select id="sexo" class="form-control" required>
                        <option value="">Selecione...</option>
                        <option value="Masculino" ${record.sexo === 'Masculino' ? 'selected' : ''}>Masculino</option>
                        <option value="Feminino" ${record.sexo === 'Feminino' ? 'selected' : ''}>Feminino</option>
                        <option value="Outro" ${record.sexo === 'Outro' ? 'selected' : ''}>Outro</option>
                    </select>
                </div>
            `;

            document.getElementById("content").innerHTML = `
                <div class="form-container">
                    <div class="form-header">
                        <h2>✏️ Editar Usuário #${id}</h2>
                        <p>Atualize os dados abaixo</p>
                    </div>
                    <form id="updateForm" onsubmit="return usuarios.updateRow(event, ${id})">
                        <div class="form-grid">
                            ${formFields}
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="loadData('usuarios')">
                                ↩️ Voltar
                            </button>
                            <button type="submit" class="btn btn-primary">
                                💾 Atualizar Usuário
                            </button>
                        </div>
                    </form>
                </div>
            `;
            
        } catch (error) {
            showAlert('❌ ' + error.message, 'error');
        }
    },

    // Atualizar usuário
    async updateRow(e, id) {
        e.preventDefault();
        const submitButton = e.target.querySelector('button[type="submit"]');
        
        return await withLoading(submitButton, async () => {
            const formData = {
                nome: document.getElementById('nome').value.trim(),
                email: document.getElementById('email').value.trim(),
                telefone: document.getElementById('telefone').value.trim(),
                senha: document.getElementById('senha').value.trim(),
                sexo: document.getElementById('sexo').value
            };
            
            // Validações
            if (!formData.nome || !formData.email || !formData.senha || !formData.sexo) {
                throw new Error('Todos os campos obrigatórios devem ser preenchidos');
            }

            if (!isValidEmail(formData.email)) {
                throw new Error('Por favor, insira um e-mail válido.');
            }

            const data = await apiRequest(`api.php?action=update&table=usuarios&id=${id}`, {
                method: "POST",
                body: JSON.stringify(formData)
            });
            
            showAlert('✅ ' + data.message, 'success');
            loadData('usuarios');
        });
    }
};