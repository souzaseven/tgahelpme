// contador_pausa.js - Sistema de contador para pausas com limite de 20 minutos

class ContadorPausa {
    constructor() {
        this.limitePausa = 20 * 60; // 20 minutos em segundos
        this.contadores = new Map();
        this.intervalos = new Map();
        this.alertaAtivo = new Set();
        this.piscando = new Set();
        this.inicializado = false;
    }

    // Inicializar contadores para todas as pessoas na pausa
    inicializar(estadoAtual) {
        if (this.inicializado) {
            this.pararTodos();
        }

        var pessoasNaPausa = estadoAtual.filter(function(p) {
            return p.status === 'pausa';
        });
        
        pessoasNaPausa.forEach(function(pessoa) {
            this.iniciarContador(pessoa.nome, pessoa.inicio_pausa);
        }.bind(this));

        this.inicializado = true;
        console.log('Contadores de pausa inicializados:', this.contadores.size);
    }

    // Iniciar contador para uma pessoa com cálculo correto do tempo
    iniciarContador(nome, inicioPausa) {
        if (this.intervalos.has(nome)) {
            this.pararContador(nome);
        }

        var self = this;

        // Função para calcular e atualizar o tempo
        var atualizarTempo = function() {
            try {
                if (!inicioPausa) {
                    console.error('Timestamp vazio para:', nome);
                    return 0;
                }

                // DEBUG: Começar com -3 horas para testar
                var inicio = new Date(inicioPausa + 'Z');
                var agora = new Date();
                
                // TESTE: Subtrair 3 horas do tempo calculado
                var tempoDecorrido = Math.floor((agora - inicio) / 1000) - (3 * 3600); // -3 horas
                
                console.log('DEBUG - Timestamp:', inicioPausa);
                console.log('DEBUG - Inicio (UTC):', inicio);
                console.log('DEBUG - Agora (local):', agora);
                console.log('DEBUG - Diferença bruta:', Math.floor((agora - inicio) / 1000), 'segundos');
                console.log('DEBUG - Tempo com correção:', tempoDecorrido, 'segundos');

                // Garantir que o tempo não seja negativo após correção
                if (tempoDecorrido < 0) {
                    console.log('Tempo corrigido é negativo, usando 0');
                    tempoDecorrido = 0;
                }

                self.contadores.set(nome, tempoDecorrido);
                self.atualizarDisplay(nome, tempoDecorrido);
                
                // Verificar se excedeu o limite
                if (tempoDecorrido > self.limitePausa) {
                    self.ativarAlerta(nome, tempoDecorrido);
                } else {
                    self.desativarAlerta(nome);
                }
                
                return tempoDecorrido;
            } catch (error) {
                console.error('Erro ao calcular tempo para', nome + ':', error);
                return 0;
            }
        };

        // Calcular tempo inicial
        var tempoInicial = atualizarTempo();
        console.log('Contador de pausa iniciado para', nome, '- Timestamp:', inicioPausa, '- Tempo inicial CORRIGIDO:', tempoInicial, 'segundos', '- Formatado:', this.formatarTempo(tempoInicial));

        // Atualizar contador a cada segundo
        var intervalo = setInterval(atualizarTempo, 1000);

        this.intervalos.set(nome, intervalo);
    }

    // Ativar alerta quando exceder o limite
    ativarAlerta(nome, segundos) {
        if (!this.alertaAtivo.has(nome)) {
            console.warn('🚨 ALERTA: ' + nome + ' excedeu o tempo de pausa! Tempo: ' + this.formatarTempo(segundos));
            this.alertaAtivo.add(nome);
            
            // Mostrar notificação apenas no console
            this.mostrarNotificacaoAlerta(nome, segundos);
        }

        // Ativar efeito piscante
        this.ativarPiscante(nome);
    }

    // Desativar alerta
    desativarAlerta(nome) {
        if (this.alertaAtivo.has(nome)) {
            console.log('Alerta desativado para:', nome);
            this.alertaAtivo.delete(nome);
            this.desativarPiscante(nome);
        }
    }

    // Ativar efeito piscante
    ativarPiscante(nome) {
        if (!this.piscando.has(nome)) {
            this.piscando.add(nome);
            this.aplicarEfeitoPiscante(nome, true);
        }
    }

    // Desativar efeito piscante
    desativarPiscante(nome) {
        if (this.piscando.has(nome)) {
            this.piscando.delete(nome);
            this.aplicarEfeitoPiscante(nome, false);
        }
    }

    // Aplicar/remover efeito piscante na interface
    aplicarEfeitoPiscante(nome, ativar) {
        var itemPausa = this.encontrarItemPausa(nome);
        var participante = this.encontrarParticipante(nome);
        
        if (ativar) {
            if (itemPausa) itemPausa.classList.add('piscante-alerta');
            if (participante) participante.classList.add('piscante-alerta');
        } else {
            if (itemPausa) itemPausa.classList.remove('piscante-alerta');
            if (participante) participante.classList.remove('piscante-alerta');
        }
    }

    // Mostrar notificação de alerta
    mostrarNotificacaoAlerta(nome, segundos) {
        var tempoExcedido = segundos - this.limitePausa;
        var mensagem = '🚨 ' + nome + ' excedeu o tempo de pausa! ' + 
                      'Tempo: ' + this.formatarTempo(segundos) + ' (' + 
                      this.formatarTempo(tempoExcedido) + ' excedido)';
        
        // Apenas log no console (sistema de notificações foi removido)
        console.warn(mensagem);
    }

    // Parar contador para uma pessoa
    pararContador(nome) {
        this.desativarAlerta(nome);
        
        if (this.intervalos.has(nome)) {
            clearInterval(this.intervalos.get(nome));
            this.intervalos.delete(nome);
        }
        this.contadores.delete(nome);
        this.removerDisplay(nome);
        console.log('Contador de pausa parado para:', nome);
    }

    // Parar todos os contadores
    pararTodos() {
        var self = this;
        this.intervalos.forEach(function(intervalo, nome) {
            clearInterval(intervalo);
            self.desativarAlerta(nome);
        });
        this.intervalos.clear();
        this.contadores.clear();
        this.alertaAtivo.clear();
        this.piscando.clear();
        
        this.inicializado = false;
        console.log('Todos os contadores de pausa parados');
    }

    // Atualizar display do contador
    atualizarDisplay(nome, segundos) {
        var tempoFormatado = this.formatarTempo(segundos);
        
        // Debug: log para verificar os tempos
        if (segundos % 60 === 0) { // Log a cada minuto
            console.log('Tempo de pausa para', nome + ':', tempoFormatado, '(', segundos, 'segundos)');
        }
        
        this.atualizarDisplayListaPausa(nome, tempoFormatado, segundos);
        this.atualizarDisplayParticipantes(nome, tempoFormatado, segundos);
    }

    // Atualizar display na lista de pausa
    atualizarDisplayListaPausa(nome, tempoFormatado, segundos) {
        var itemPausa = this.encontrarItemPausa(nome);
        
        if (itemPausa) {
            var contadorElement = itemPausa.querySelector('.contador-pausa');
            
            if (!contadorElement) {
                // Criar apenas se não existir
                contadorElement = document.createElement('div');
                contadorElement.className = 'contador-pausa';
                contadorElement.setAttribute('data-nome', nome);
                contadorElement.innerHTML = '<div class="contador-tempo-pausa"><i class="fas fa-hourglass-half"></i><span class="tempo-decorrido">' + tempoFormatado + '</span></div>';
                
                var itemInfo = itemPausa.querySelector('.item-info');
                if (itemInfo) {
                    // Inserir após o nome
                    var itemStatus = itemPausa.querySelector('.item-status');
                    if (itemStatus) {
                        itemInfo.insertBefore(contadorElement, itemStatus);
                    } else {
                        itemInfo.appendChild(contadorElement);
                    }
                }
            } else {
                // Apenas atualizar o texto
                var tempoElement = contadorElement.querySelector('.tempo-decorrido');
                if (tempoElement) {
                    tempoElement.textContent = tempoFormatado;
                }
            }

            this.aplicarDestaqueTempo(contadorElement, segundos);
        } else {
            // Tentar novamente após um delay
            var self = this;
            setTimeout(function() {
                self.atualizarDisplayListaPausa(nome, tempoFormatado, segundos);
            }, 100);
        }
    }

    // Atualizar display na lista de participantes
    atualizarDisplayParticipantes(nome, tempoFormatado, segundos) {
        var participante = this.encontrarParticipante(nome);
        
        if (participante) {
            var contadorElement = participante.querySelector('.contador-pausa');
            
            if (!contadorElement) {
                contadorElement = document.createElement('div');
                contadorElement.className = 'contador-pausa';
                contadorElement.innerHTML = '<div class="contador-tempo-pausa"><i class="fas fa-hourglass-half"></i><span class="tempo-decorrido">' + tempoFormatado + '</span></div>';
                
                var participanteHeader = participante.querySelector('.participante-header');
                if (participanteHeader) {
                    participanteHeader.appendChild(contadorElement);
                }
            } else {
                var tempoElement = contadorElement.querySelector('.tempo-decorrido');
                if (tempoElement) {
                    tempoElement.textContent = tempoFormatado;
                }
            }

            this.aplicarDestaqueTempo(contadorElement, segundos);
        }
    }

    // Aplicar classes CSS baseadas no tempo decorrido
    aplicarDestaqueTempo(contadorElement, segundos) {
        var tempoElement = contadorElement.querySelector('.contador-tempo-pausa');
        if (!tempoElement) return;

        // Remover todas as classes de tempo
        tempoElement.classList.remove('tempo-normal', 'tempo-alerta', 'tempo-excedido');

        if (segundos <= this.limitePausa) {
            tempoElement.classList.add('tempo-normal');
        } else if (segundos <= this.limitePausa + 300) { // 5 minutos após o limite
            tempoElement.classList.add('tempo-alerta');
        } else {
            tempoElement.classList.add('tempo-excedido');
        }
    }

    // Remover display do contador
    removerDisplay(nome) {
        var itemPausa = this.encontrarItemPausa(nome);
        if (itemPausa) {
            var contadorElement = itemPausa.querySelector('.contador-pausa');
            if (contadorElement) {
                contadorElement.remove();
            }
        }

        var participante = this.encontrarParticipante(nome);
        if (participante) {
            var contadorElement = participante.querySelector('.contador-pausa');
            if (contadorElement) {
                contadorElement.remove();
            }
        }
    }

    // Encontrar item na lista de pausa pelo nome
    encontrarItemPausa(nome) {
        var itens = document.querySelectorAll('#pausa-lista .item');
        for (var i = 0; i < itens.length; i++) {
            var item = itens[i];
            var nomeElement = item.querySelector('.item-nome');
            if (nomeElement && nomeElement.textContent.trim() === nome) {
                return item;
            }
        }
        return null;
    }

    // Encontrar participante pelo nome
    encontrarParticipante(nome) {
        var participantes = document.querySelectorAll('.participante');
        for (var i = 0; i < participantes.length; i++) {
            var participante = participantes[i];
            var nomeElement = participante.querySelector('.participante-nome');
            if (nomeElement && nomeElement.textContent.trim() === nome) {
                return participante;
            }
        }
        return null;
    }

    // Formatar tempo em segundos para HH:MM:SS ou MM:SS
    formatarTempo(segundos) {
        if (segundos < 0) {
            return '00:00';
        }
        
        var horas = Math.floor(segundos / 3600);
        var minutos = Math.floor((segundos % 3600) / 60);
        var segs = segundos % 60;
        
        if (horas > 0) {
            return horas.toString().padStart(2, '0') + ':' + 
                   minutos.toString().padStart(2, '0') + ':' + 
                   segs.toString().padStart(2, '0');
        } else {
            return minutos.toString().padStart(2, '0') + ':' + 
                   segs.toString().padStart(2, '0');
        }
    }

    // Obter tempo atual de uma pessoa
    obterTempo(nome) {
        return this.contadores.get(nome) || 0;
    }

    // Verificar se está em alerta
    estaEmAlerta(nome) {
        return this.alertaAtivo.has(nome);
    }

    // Atualizar estado quando houver mudanças
    atualizarEstado(novoEstado) {
        var pessoasNaPausa = novoEstado.filter(function(p) {
            return p.status === 'pausa';
        });
        var pessoasAtuais = new Set(this.contadores.keys());
        var self = this;

        // Debug: log do estado atual
        console.log('Atualizando estado pausa - Pessoas na pausa:', pessoasNaPausa.map(function(p) { 
            return p.nome + ' (' + p.inicio_pausa + ')'; 
        }));

        // Parar contadores de pessoas que saíram da pausa
        pessoasAtuais.forEach(function(nome) {
            var aindaNaPausa = pessoasNaPausa.some(function(p) {
                return p.nome === nome;
            });
            if (!aindaNaPausa) {
                console.log('Parando contador de pausa para', nome, '- Saiu da pausa');
                self.pararContador(nome);
            }
        });

        // Iniciar/atualizar contadores para pessoas na pausa
        pessoasNaPausa.forEach(function(pessoa) {
            if (!self.contadores.has(pessoa.nome)) {
                console.log('Iniciando contador de pausa para:', pessoa.nome, 'Timestamp:', pessoa.inicio_pausa);
                self.iniciarContador(pessoa.nome, pessoa.inicio_pausa);
            }
        });
    }

    // Destruir/limpar tudo
    destruir() {
        this.pararTodos();
    }
}

// Inicializar o contador global de pausa
var contadorPausa = new ContadorPausa();