const devocionais = {
    // Renderizar tabela de devocionais
    renderTable(data, pagination = null) {
        let html = `
            <div class="toolbar">
                <div class="search-container">
                    <i>🔍</i>
                    <input type="text" id="searchInput" placeholder="Buscar em devocionais..." onkeyup="filterTable()">
                </div>
                <button class="btn btn-primary" onclick="devocionais.showInsertForm()">
                    ➕ Novo Devocional
                </button>
                <button class="btn btn-info" onclick="window.open('https://tgameajuda.com/devocional2/inicio.html', '_blank')">
                    🌐 Site Devocional
                </button>
            </div>
            <h2>📖 Devocionais</h2>
        `;
        
        if (data.length === 0) {
            html += `<div class="no-data">📭 Nenhum devocional encontrado.</div>`;
            document.getElementById("content").innerHTML = html;
            return;
        }

        html += `<div class="table-container"><table id="dataTable"><thead><tr>
            <th>🆔 ID</th>
            <th>📅 Data</th>
            <th>📝 Tema</th>
            <th>📄 Texto</th>
            <th>✍️ Ministrado Por</th>
            <th>📅 Criado em</th>
            <th width="200">⚡ Ações</th>
        </tr></thead><tbody>`;

        // Linhas
        data.forEach(row => {
            html += `
                <tr>
                    <td>${row.id}</td>
                    <td>${formatCellValue(row.data)}</td>
                    <td title="${row.tema}">${truncateText(row.tema, 30)}</td>
                    <td title="${row.texto}">${truncateText(row.texto || '', 25)}</td>
                    <td>${formatCellValue(row.ministrado_por)}</td>
                    <td>${formatCellValue(row.criado_em)}</td>
                    <td class="actions">
                        <button class="btn btn-success btn-icon" onclick="devocionais.editRow(${row.id})" title="Editar">
                            ✏️
                        </button>
                        <button class="btn btn-danger btn-icon" onclick="deleteRow('devocionais', ${row.id})" title="Excluir">
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
        
        html += `<div class="stats">📈 Total: ${pagination?.total || data.length} devocional(is) encontrado(s)</div>`;
        
        document.getElementById("content").innerHTML = html;
    },

    // Mostrar formulário de inserção
    showInsertForm() {
        const formFields = `
            <div class="form-group">
                <label for="data">
                    📅 Data
                    <span class="required">*</span>
                </label>
                <input type="date" id="data" class="form-control" 
                       value="${new Date().toISOString().split('T')[0]}" required>
            </div>
            <div class="form-group">
                <label for="tema">
                    📝 Tema
                    <span class="required">*</span>
                </label>
                <input type="text" id="tema" class="form-control" 
                       placeholder="Digite o tema do devocional" required>
            </div>
            <div class="form-group">
                <label for="texto">
                    📄 Texto
                </label>
                <textarea id="texto" class="form-control" 
                          placeholder="Escreva o texto do devocional aqui..."
                          rows="6"></textarea>
            </div>
            <div class="form-group">
                <label for="ministrado_por">
                    ✍️ Ministrado Por
                </label>
                <input type="text" id="ministrado_por" class="form-control" 
                       placeholder="Nome de quem ministrou">
            </div>
        `;

        document.getElementById("content").innerHTML = `
            <div class="form-container">
                <div class="form-header">
                    <h2>➕ Novo Devocional</h2>
                    <p>Preencha os dados abaixo para adicionar um novo devocional</p>
                </div>
                <form id="insertForm" onsubmit="return devocionais.insertRow(event)">
                    <div class="form-grid">
                        ${formFields}
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="loadData('devocionais')">
                            ↩️ Voltar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            💾 Salvar Devocional
                        </button>
                    </div>
                </form>
            </div>
        `;
    },

    // Inserir devocional
    async insertRow(e) {
        e.preventDefault();
        const submitButton = e.target.querySelector('button[type="submit"]');
        
        return await withLoading(submitButton, async () => {
            const formData = {
                data: document.getElementById('data').value,
                tema: document.getElementById('tema').value.trim(),
                texto: document.getElementById('texto').value.trim(),
                ministrado_por: document.getElementById('ministrado_por').value.trim()
            };
            
            // Validações
            if (!formData.data || !formData.tema) {
                throw new Error('Data e Tema são campos obrigatórios');
            }

            const data = await apiRequest('api.php?action=insert&table=devocionais', {
                method: "POST",
                body: JSON.stringify(formData)
            });
            
            showAlert('✅ ' + data.message, 'success');
            loadData('devocionais');
        });
    },

    // Editar devocional
    async editRow(id) {
        try {
            const data = await apiRequest(`api.php?action=get&table=devocionais&id=${id}`);
            const record = data.data;
            
            const formFields = `
                <div class="form-group">
                    <label for="data">
                        📅 Data
                        <span class="required">*</span>
                    </label>
                    <input type="date" id="data" class="form-control" 
                           value="${record.data || new Date().toISOString().split('T')[0]}" required>
                </div>
                <div class="form-group">
                    <label for="tema">
                        📝 Tema
                        <span class="required">*</span>
                    </label>
                    <input type="text" id="tema" class="form-control" 
                           placeholder="Digite o tema do devocional"
                           value="${escapeHtml(record.tema || '')}" required>
                </div>
                <div class="form-group">
                    <label for="texto">
                        📄 Texto
                    </label>
                    <textarea id="texto" class="form-control" 
                              placeholder="Escreva o texto do devocional aqui..."
                              rows="6">${escapeHtml(record.texto || '')}</textarea>
                </div>
                <div class="form-group">
                    <label for="ministrado_por">
                        ✍️ Ministrado Por
                    </label>
                    <input type="text" id="ministrado_por" class="form-control" 
                           placeholder="Nome de quem ministrou"
                           value="${escapeHtml(record.ministrado_por || '')}">
                </div>
            `;

            document.getElementById("content").innerHTML = `
                <div class="form-container">
                    <div class="form-header">
                        <h2>✏️ Editar Devocional #${id}</h2>
                        <p>Atualize os dados abaixo</p>
                    </div>
                    <form id="updateForm" onsubmit="return devocionais.updateRow(event, ${id})">
                        <div class="form-grid">
                            ${formFields}
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="loadData('devocionais')">
                                ↩️ Voltar
                            </button>
                            <button type="submit" class="btn btn-primary">
                                💾 Atualizar Devocional
                            </button>
                        </div>
                    </form>
                </div>
            `;
            
        } catch (error) {
            showAlert('❌ ' + error.message, 'error');
        }
    },

    // Atualizar devocional
    async updateRow(e, id) {
        e.preventDefault();
        const submitButton = e.target.querySelector('button[type="submit"]');
        
        return await withLoading(submitButton, async () => {
            const formData = {
                data: document.getElementById('data').value,
                tema: document.getElementById('tema').value.trim(),
                texto: document.getElementById('texto').value.trim(),
                ministrado_por: document.getElementById('ministrado_por').value.trim()
            };
            
            // Validações
            if (!formData.data || !formData.tema) {
                throw new Error('Data e Tema são campos obrigatórios');
            }

            const data = await apiRequest(`api.php?action=update&table=devocionais&id=${id}`, {
                method: "POST",
                body: JSON.stringify(formData)
            });
            
            showAlert('✅ ' + data.message, 'success');
            loadData('devocionais');
        });
    }
};