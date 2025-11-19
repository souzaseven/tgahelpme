/* ============================================================
   cronometro.js (v1.9 - CORREÇÃO RADICAL SEM PISCAR)
   Módulo independente de contagem de tempo para Pausa e Fila
   ============================================================ */

console.log("%c[cronometro.js v1.9] carregado", "color:#00ff88;font-weight:bold;");

const Cronometro = {
    ativos: {},     // lista de cronômetros ativos: { nome: {inicio, tipo, timer}}
    limitePausa: 20 * 60, // 20 min em segundos

    // ------------------------------------------------------------
    // Inicia o cronômetro (espera ou pausa)
    // ------------------------------------------------------------
    iniciar(nome, tipo, inicioTimestamp = null) {
        if (!nome || !tipo) return;

        // já existe → não recria
        if (this.ativos[nome]) {
            console.log(`[Cronometro] ${nome} já está ativo, ignorando...`);
            return;
        }

        let inicio;
        const agora = Date.now();
        
        if (inicioTimestamp) {
            // Converte string para timestamp
            if (typeof inicioTimestamp === 'string') {
                const data = new Date(inicioTimestamp);
                inicio = data.getTime();
                
                if (isNaN(inicio)) {
                    console.warn(`[Cronometro] Timestamp inválido: ${inicioTimestamp}, usando atual`);
                    inicio = agora;
                }
            } else if (typeof inicioTimestamp === 'number') {
                inicio = inicioTimestamp;
            } else {
                inicio = agora;
            }

            // CORREÇÃO: Se o timestamp está no futuro, ajusta para agora
            if (inicio > agora) {
                const diferenca = inicio - agora;
                const diferencaMinutos = Math.floor(diferenca / 1000 / 60);
                
                console.warn(`[Cronometro] TIMESTAMP NO FUTURO DETECTADO: ${diferencaMinutos} minutos à frente. Ajustando para agora.`);
                inicio = agora;
            }
        } else {
            inicio = agora;
        }

        console.log(`[Cronometro] Iniciando ${tipo} para ${nome} em ${new Date(inicio).toLocaleTimeString()}`);

        // REMOVE ELEMENTO ESTÁTICO ANTES DE INICIAR
        this.removerElementoEstatico(nome);

        this.ativos[nome] = {
            inicio,
            tipo,
            timer: setInterval(() => this.atualizar(nome), 1000)
        };

        // salva no localStorage
        localStorage.setItem(`cron_${nome}`, JSON.stringify({
            inicio,
            tipo
        }));

        this.atualizar(nome); // Atualiza imediatamente
    },

    // ------------------------------------------------------------
    // Atualiza contador do operador
    // ------------------------------------------------------------
    atualizar(nome) {
        const data = this.ativos[nome];
        if (!data) return;

        const agora = Date.now();
        const diff = Math.floor((agora - data.inicio) / 1000);
        
        // Sempre atualiza, mesmo que seja 0 (evita delay)
        const tempoFmt = this.formatar(Math.max(0, diff));
        this.exibir(nome, tempoFmt, data.tipo, diff);
    },

    // ------------------------------------------------------------
    // Para o cronômetro e envia ao backend
    // ------------------------------------------------------------
    parar(nome, enviarParaBanco = true) {
        const data = this.ativos[nome];
        if (!data) return;

        clearInterval(data.timer);

        const fim = Date.now();
        const duracao = Math.floor((fim - data.inicio) / 1000);

        console.log(`[Cronometro] Parando ${nome} após ${duracao}s`);

        if (enviarParaBanco) {
            fetch("./php/cronometro.php", {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify({
                    nome,
                    tipo: data.tipo,
                    inicio: data.inicio,
                    fim: fim,
                    duracao
                })
            }).catch(err => console.error("Erro ao salvar cronômetro:", err));
        }

        delete this.ativos[nome];
        localStorage.removeItem(`cron_${nome}`);

        // Remove completamente o elemento do cronômetro
        this.removerElementoCronometro(nome);
    },

    // ------------------------------------------------------------
    // REMOVE elemento estático .tempo da fila
    // ------------------------------------------------------------
    removerElementoEstatico(nome) {
        const itemFila = this.encontrarItemFila(nome);
        if (itemFila) {
            const tempoStatic = itemFila.querySelector('.tempo');
            if (tempoStatic) {
                console.log(`[Cronometro] Removendo elemento estático .tempo para: ${nome}`);
                tempoStatic.remove(); // REMOVE COMPLETAMENTE
            }
        }
    },

    // ------------------------------------------------------------
    // Exibe o cronômetro visualmente - VERSÃO SIMPLIFICADA
    // ------------------------------------------------------------
    exibir(nome, tempo, tipo, diff) {
        // PROCURA EM DOIS LOCAIS: lista de participantes E fila de espera
        let item = this.encontrarItemParticipante(nome);
        let isFila = false;
        
        if (!item) {
            item = this.encontrarItemFila(nome);
            isFila = true;
        }

        if (!item) {
            console.warn(`[Cronometro] Item não encontrado: ${nome}`);
            return;
        }

        let cronElement;
        
        if (isFila) {
            // PARA FILA: cria .cronometro-dinamico
            cronElement = item.querySelector('.cronometro-dinamico');
            if (!cronElement) {
                cronElement = document.createElement("span");
                cronElement.className = "cronometro-dinamico";
                
                // Adiciona no final do item-fila
                item.appendChild(cronElement);
            }
        } else {
            // PARA PARTICIPANTES: usa/create .cronometro
            cronElement = item.querySelector('.cronometro');
            if (!cronElement) {
                cronElement = document.createElement("div");
                cronElement.className = "cronometro";
                item.appendChild(cronElement);
            }
        }

        // ATUALIZA DIRETAMENTE - sem verificação de 00:00
        cronElement.textContent = tempo;
        cronElement.style.display = "inline";
        cronElement.style.opacity = "1";

        // Aplica estilos
        if (isFila) {
            this.aplicarEstilosFila(cronElement, tipo, diff);
        } else {
            this.aplicarEstilosParticipante(cronElement, item, tipo, diff);
        }
    },

    // ------------------------------------------------------------
    // Remove elemento do cronômetro ao parar
    // ------------------------------------------------------------
    removerElementoCronometro(nome) {
        // Procura em participantes
        let item = this.encontrarItemParticipante(nome);
        if (item) {
            const cronElement = item.querySelector('.cronometro');
            if (cronElement) {
                cronElement.remove();
            }
            item.classList.remove("expirado-limite");
            return;
        }

        // Procura na fila
        item = this.encontrarItemFila(nome);
        if (item) {
            // Remove elemento dinâmico
            const cronDinamico = item.querySelector('.cronometro-dinamico');
            if (cronDinamico) {
                cronDinamico.remove();
            }
        }
    },

    // ------------------------------------------------------------
    // Encontra item na lista de participantes
    // ------------------------------------------------------------
    encontrarItemParticipante(nome) {
        const todos = document.querySelectorAll(".op-item");
        let itemEncontrado = null;

        todos.forEach(el => {
            const strong = el.querySelector("strong");
            if (!strong) return;

            const texto = strong.textContent.trim().toLowerCase();
            if (texto === nome.trim().toLowerCase()) {
                itemEncontrado = el;
            }
        });

        return itemEncontrado;
    },

    // ------------------------------------------------------------
    // Encontra item na fila de espera
    // ------------------------------------------------------------
    encontrarItemFila(nome) {
        const todosFila = document.querySelectorAll(".item-fila");
        let itemEncontrado = null;

        todosFila.forEach(el => {
            const strong = el.querySelector("strong");
            if (!strong) return;

            const texto = strong.textContent.trim().toLowerCase();
            if (texto === nome.trim().toLowerCase()) {
                itemEncontrado = el;
            }
        });

        return itemEncontrado;
    },

    // ------------------------------------------------------------
    // Aplica estilos para participantes
    // ------------------------------------------------------------
    aplicarEstilosParticipante(cronElement, item, tipo, diff) {
        cronElement.className = "cronometro";

        if (tipo === "espera") {
            cronElement.classList.add("cron-espera");
        } else if (tipo === "pausa") {
            if (diff > this.limitePausa) {
                cronElement.classList.add("cron-excedido");
                item.classList.add("expirado-limite");
            } else {
                cronElement.classList.add("cron-pausa");
                item.classList.remove("expirado-limite");
            }
        }
    },

    // ------------------------------------------------------------
    // Aplica estilos para fila de espera
    // ------------------------------------------------------------
    aplicarEstilosFila(cronElement, tipo, diff) {
        cronElement.classList.remove("cron-espera", "cron-pausa", "cron-excedido");
        
        if (tipo === "espera") {
            cronElement.classList.add("cron-espera");
            cronElement.style.color = "#ffaa00";
            cronElement.style.fontWeight = "bold";
        } else if (tipo === "pausa") {
            if (diff > this.limitePausa) {
                cronElement.classList.add("cron-excedido");
                cronElement.style.color = "#ff4444";
                cronElement.style.fontWeight = "900";
            } else {
                cronElement.classList.add("cron-pausa");
                cronElement.style.color = "#22c55e";
                cronElement.style.fontWeight = "bold";
            }
        }

        // Estilos base para fila
        cronElement.style.marginLeft = "8px";
        cronElement.style.fontSize = "0.9rem";
        cronElement.style.fontFamily = "monospace";
    },

    // ------------------------------------------------------------
    // Inicia cronômetros automaticamente para a fila de espera
    // ------------------------------------------------------------
    iniciarCronometrosFila() {
        const itensFila = document.querySelectorAll('.item-fila');
        console.log(`[Cronometro] Encontrados ${itensFila.length} itens na fila`);
        
        itensFila.forEach((item, index) => {
            const strong = item.querySelector('strong');
            const tempoElement = item.querySelector('.tempo');
            
            if (!strong || !tempoElement) {
                console.warn(`[Cronometro] Item ${index} da fila sem strong ou tempo`);
                return;
            }
            
            const nome = strong.textContent.trim();
            const tinicio = tempoElement.getAttribute('data-tinicio');
            
            console.log(`[Cronometro] Processando fila: ${nome}, timestamp: ${tinicio}`);
            
            if (this.ativos[nome]) {
                console.log(`[Cronometro] ${nome} já tem cronômetro ativo`);
                return;
            }
            
            if (tinicio) {
                console.log(`[Cronometro] INICIANDO cronômetro de espera para: ${nome}`);
                this.iniciar(nome, 'espera', tinicio);
            } else {
                console.warn(`[Cronometro] ${nome} não tem data-tinicio`);
            }
        });
    },

    // Formata em mm:ss
    formatar(seg) {
        const m = String(Math.floor(seg / 60)).padStart(2, "0");
        const s = String(seg % 60).padStart(2, "0");
        return `${m}:${s}`;
    },

    // ------------------------------------------------------------
    // Retoma contadores após recarregar a página
    // ------------------------------------------------------------
    retomar() {
        console.log("[Cronometro] Retomando cronômetros do localStorage...");
        
        Object.keys(localStorage).forEach(key => {
            if (!key.startsWith("cron_")) return;
            
            try {
                const data = JSON.parse(localStorage.getItem(key));
                if (data.inicio && data.tipo) {
                    const nome = key.replace("cron_", "");
                    
                    const agora = Date.now();
                    const duasHoras = 2 * 60 * 60 * 1000;
                    
                    if (agora - data.inicio > duasHoras) {
                        console.log(`[Cronometro] Cronômetro muito antigo para ${nome}, removendo...`);
                        localStorage.removeItem(key);
                        return;
                    }
                    
                    console.log(`[Cronometro] Retomando ${nome} (${data.tipo})`);
                    this.iniciar(nome, data.tipo, data.inicio);
                }
            } catch (error) {
                console.error(`[Cronometro] Erro ao retomar ${key}:`, error);
                localStorage.removeItem(key);
            }
        });
    },

    // ------------------------------------------------------------
    // CORREÇÃO: Função para corrigir todos os timestamps futuros
    // ------------------------------------------------------------
    corrigirTimestampsFuturos() {
        console.log("[Cronometro] Verificando timestamps futuros...");
        const agora = Date.now();
        let corrigidos = 0;

        Object.keys(this.ativos).forEach(nome => {
            const data = this.ativos[nome];
            if (data.inicio > agora) {
                const diferencaMinutos = Math.floor((data.inicio - agora) / 1000 / 60);
                console.warn(`[Cronometro] Corrigindo timestamp futuro para ${nome}: +${diferencaMinutos}min`);
                
                data.inicio = agora;
                corrigidos++;
                
                localStorage.setItem(`cron_${nome}`, JSON.stringify({
                    inicio: agora,
                    tipo: data.tipo
                }));
            }
        });

        if (corrigidos > 0) {
            console.log(`[Cronometro] ${corrigidos} timestamps futuros corrigidos`);
        }
    },

    // ------------------------------------------------------------
    // DEBUG: Força iniciar cronômetro para teste
    // ------------------------------------------------------------
    debugIniciarFila() {
        console.log("[Cronometro] DEBUG: Forçando início de cronômetros da fila...");
        this.iniciarCronometrosFila();
    }
};

// Bootstrap melhorado
document.addEventListener("DOMContentLoaded", () => {
    console.log("[Cronometro] Inicializando...");
    
    setTimeout(() => {
        Cronometro.retomar();
        
        setTimeout(() => {
            Cronometro.corrigirTimestampsFuturos();
        }, 1000);
        
        // Inicia cronômetros da fila com mais delay para garantir que o DOM esteja pronto
        setTimeout(() => {
            console.log("[Cronometro] Iniciando cronômetros da fila...");
            Cronometro.iniciarCronometrosFila();
        }, 2000);
        
    }, 500);
});

// Export para uso global
window.Cronometro = Cronometro;