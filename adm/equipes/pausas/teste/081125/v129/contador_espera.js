// contador_espera.js

class ContadorEspera {
    constructor() {
        this.contadores = new Map();
        this.intervalos = new Map();
        this.inicializado = false;
    }

    // Inicializar contadores para todas as pessoas na espera
    inicializar(estadoAtual) {
        if (this.inicializado) {
            this.pararTodos();
        }

        var pessoasNaEspera = estadoAtual.filter(function(p) {
            return p.status === 'espera';
        });
        
        pessoasNaEspera.forEach(function(pessoa) {
            this.iniciarContador(pessoa.nome, pessoa.inicio_espera);
        }.bind(this));

        this.inicializado = true;
        console.log('Contadores de espera inicializados:', this.contadores.size);
    }

    // Iniciar contador para uma pessoa com cálculo correto do tempo
    iniciarContador(nome, inicioEspera) {
        if (this.intervalos.has(nome)) {
            this.pararContador(nome);
        }

        var self = this;

        // Função para calcular e atualizar o tempo
        var atualizarTempo = function() {
            try {
                if (!inicioEspera) {
                    console.error('Timestamp vazio para:', nome);
                    return 0;
                }

                // Converter timestamp do servidor (UTC) para data JavaScript
                var inicio = new Date(inicioEspera + 'Z'); // Adiciona 'Z' para forçar UTC
                
                // Verificar se a data é válida
                if (isNaN(inicio.getTime())) {
                    console.error('Timestamp inválido para', nome + ':', inicioEspera);
                    return 0;
                }

                var agora = new Date();
                var tempoDecorrido = Math.floor((agora - inicio) / 1000);

                // Verificar se o tempo é negativo (problema de fuso horário)
                if (tempoDecorrido < 0) {
                    console.warn('Tempo negativo para', nome + ':', tempoDecorrido, 'segundos. Corrigindo para 0.');
                    tempoDecorrido = 0;
                }

                self.contadores.set(nome, tempoDecorrido);
                self.atualizarDisplay(nome, tempoDecorrido);
                
                return tempoDecorrido;
            } catch (error) {
                console.error('Erro ao calcular tempo para', nome + ':', error);
                return 0;
            }
        };

        // Calcular tempo inicial
        var tempoInicial = atualizarTempo();
        console.log('Contador iniciado para', nome, '- Timestamp:', inicioEspera, '- Tempo inicial:', tempoInicial, 'segundos');

        // Atualizar contador a cada segundo
        var intervalo = setInterval(atualizarTempo, 1000);

        this.intervalos.set(nome, intervalo);
    }

    // Parar contador para uma pessoa
    pararContador(nome) {
        if (this.intervalos.has(nome)) {
            clearInterval(this.intervalos.get(nome));
            this.intervalos.delete(nome);
        }
        this.contadores.delete(nome);
        this.removerDisplay(nome);
        console.log('Contador parado para:', nome);
    }

    // Parar todos os contadores
    pararTodos() {
        var self = this;
        this.intervalos.forEach(function(intervalo, nome) {
            clearInterval(intervalo);
        });
        this.intervalos.clear();
        this.contadores.clear();
        
        this.inicializado = false;
        console.log('Todos os contadores de espera parados');
    }

    // Atualizar display do contador
    atualizarDisplay(nome, segundos) {
        var tempoFormatado = this.formatarTempo(segundos);
        
        // Debug: log para verificar os tempos
        if (segundos % 30 === 0) { // Log a cada 30 segundos para não poluir o console
            console.log('Tempo atual para', nome + ':', tempoFormatado, '(', segundos, 'segundos)');
        }
        
        this.atualizarDisplayListaEspera(nome, tempoFormatado, segundos);
        this.atualizarDisplayParticipantes(nome, tempoFormatado, segundos);
    }

// Atualizar display na lista de espera
atualizarDisplayListaEspera(nome, tempoFormatado, segundos) {
    var itemEspera = this.encontrarItemEspera(nome);
    
    if (itemEspera) {
        var contadorElement = itemEspera.querySelector('.contador-espera');
        
        if (!contadorElement) {
            // Criar apenas se não existir
            contadorElement = document.createElement('div');
            contadorElement.className = 'contador-espera';
            contadorElement.setAttribute('data-nome', nome);
            contadorElement.innerHTML = '<div class="contador-tempo"><i class="fas fa-clock"></i><span class="tempo-decorrido">' + tempoFormatado + '</span></div>';
            
            var itemStatus = itemEspera.querySelector('.item-status');
            if (itemStatus) {
                // Inserir ANTES do primeiro elemento
                if (itemStatus.firstChild) {
                    itemStatus.insertBefore(contadorElement, itemStatus.firstChild);
                } else {
                    itemStatus.appendChild(contadorElement);
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
            self.atualizarDisplayListaEspera(nome, tempoFormatado, segundos);
        }, 100);
    }
}

    // Atualizar display na lista de participantes
    atualizarDisplayParticipantes(nome, tempoFormatado, segundos) {
        var participante = this.encontrarParticipante(nome);
        
        if (participante) {
            var contadorElement = participante.querySelector('.contador-espera');
            
            if (!contadorElement) {
                contadorElement = document.createElement('div');
                contadorElement.className = 'contador-espera';
                contadorElement.innerHTML = '<div class="contador-tempo"><i class="fas fa-clock"></i><span class="tempo-decorrido">' + tempoFormatado + '</span></div>';
                
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
        var tempoElement = contadorElement.querySelector('.contador-tempo');
        if (!tempoElement) return;

        tempoElement.classList.remove('longo', 'muito-longo');

        if (segundos > 300) {
            tempoElement.classList.add('longo');
        }
        if (segundos > 600) {
            tempoElement.classList.add('muito-longo');
        }
    }

    // Remover display do contador
    removerDisplay(nome) {
        var itemEspera = this.encontrarItemEspera(nome);
        if (itemEspera) {
            var contadorElement = itemEspera.querySelector('.contador-espera');
            if (contadorElement) {
                contadorElement.remove();
            }
        }

        var participante = this.encontrarParticipante(nome);
        if (participante) {
            var contadorElement = participante.querySelector('.contador-espera');
            if (contadorElement) {
                contadorElement.remove();
            }
        }
    }

    // Encontrar item na lista de espera pelo nome
    encontrarItemEspera(nome) {
        var itens = document.querySelectorAll('.item.espera');
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
            return '00:00'; // Fallback para tempo negativo
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

    // Atualizar estado quando houver mudanças
    atualizarEstado(novoEstado) {
        var pessoasNaEspera = novoEstado.filter(function(p) {
            return p.status === 'espera';
        });
        var pessoasAtuais = new Set(this.contadores.keys());
        var self = this;

        // Debug: log do estado atual
        console.log('Atualizando estado - Pessoas na espera:', pessoasNaEspera.map(function(p) { 
            return p.nome + ' (' + p.inicio_espera + ')'; 
        }));

        // Parar contadores de pessoas que saíram da espera
        pessoasAtuais.forEach(function(nome) {
            var aindaNaEspera = pessoasNaEspera.some(function(p) {
                return p.nome === nome;
            });
            if (!aindaNaEspera) {
                console.log('Parando contador para', nome, '- Saiu da espera');
                self.pararContador(nome);
            }
        });

        // Iniciar/atualizar contadores para pessoas na espera
        pessoasNaEspera.forEach(function(pessoa) {
            if (!self.contadores.has(pessoa.nome)) {
                console.log('Iniciando contador para:', pessoa.nome, 'Timestamp:', pessoa.inicio_espera);
                self.iniciarContador(pessoa.nome, pessoa.inicio_espera);
            }
        });
    }

    // Destruir/limpar tudo
    destruir() {
        this.pararTodos();
    }
}

// Inicializar o contador global
var contadorEspera = new ContadorEspera();