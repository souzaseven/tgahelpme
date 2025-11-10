<?php
// controle_pausa.php - Sistema completo de controle de pausas com notificações

include 'conexao.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

class ControlePausa {
    private $pdo;
    private $maxPausa = 2;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->criarTabelaNotificacoes();
    }

    public function criarTabelaNotificacoes() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS notificacoes_globais (
                id VARCHAR(50) PRIMARY KEY,
                mensagem TEXT NOT NULL,
                tipo VARCHAR(20) DEFAULT 'info',
                duracao INT DEFAULT 8000,
                criada_em DATETIME NOT NULL,
                INDEX idx_notificacoes_tempo (criada_em)
            )";
            $this->pdo->exec($sql);
        } catch (Exception $e) {
            error_log("Erro ao criar tabela de notificações: " . $e->getMessage());
        }
    }

    public function inicializarParticipantes() {
        $participantes = [
            "Anderson de Souza", "Antônio Carlos", "Carlos Eduardo", "Daniel Feix",
            "Heitor Simon", "Igor Gabriel", "Jesse Kalebe", "Jessica Bergue",
            "Lucas Eduardo", "Moisés Vinicius", "Pablo de Freitas", 
            "Suzana Ferreira", "Uanderson Almeida"
        ];

        foreach ($participantes as $nome) {
            $stmt = $this->pdo->prepare("SELECT id FROM controle_pausa WHERE nome = ?");
            $stmt->execute([$nome]);
            
            if (!$stmt->fetch()) {
                $stmt = $this->pdo->prepare("INSERT INTO controle_pausa (nome, status) VALUES (?, 'disponivel')");
                $stmt->execute([$nome]);
            }
        }
    }

    public function enviarNotificacaoGlobal($mensagem, $tipo = 'info', $duracao = 10000) {
        try {
            if (!isset($mensagem) || strlen(trim($mensagem)) === 0) {
                error_log("❌ ERRO: Mensagem vazia");
                return ['success' => false, 'error' => 'Mensagem vazia'];
            }

            $this->criarTabelaNotificacoes();
            
            $idNotificacao = uniqid('notif_');
            $timestamp = date('Y-m-d H:i:s');
            
            // Limpar notificações antigas (mais de 1 minuto)
            $this->limparNotificacoesAntigas();
            
            // Inserir notificação
            $stmt = $this->pdo->prepare("INSERT INTO notificacoes_globais (id, mensagem, tipo, duracao, criada_em) VALUES (?, ?, ?, ?, ?)");
            $result = $stmt->execute([$idNotificacao, trim($mensagem), $tipo, $duracao, $timestamp]);
            
            if ($result) {
                error_log("✅ NOTIFICAÇÃO ENVIADA: " . substr($mensagem, 0, 100));
                return ['success' => true, 'id' => $idNotificacao, 'timestamp' => $timestamp];
            } else {
                error_log("❌ ERRO AO INSERIR NOTIFICAÇÃO NO BANCO");
                return ['success' => false, 'error' => 'Erro ao inserir no banco'];
            }
            
        } catch (Exception $e) {
            error_log("❌ ERRO AO ENVIAR NOTIFICAÇÃO GLOBAL: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getNotificacoes($ultimaNotificacao = null) {
        try {
            $this->criarTabelaNotificacoes();
            
            $sql = "SELECT id, mensagem, tipo, duracao, criada_em 
                    FROM notificacoes_globais 
                    WHERE criada_em >= DATE_SUB(NOW(), INTERVAL 30 SECOND)";
            
            $params = [];
            
            if ($ultimaNotificacao) {
                $sql .= " AND id != ?";
                $params[] = $ultimaNotificacao;
            }
            
            $sql .= " ORDER BY criada_em DESC LIMIT 10";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $notificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Limpar notificações antigas periodicamente
            if (rand(1, 10) === 1) { // Aproximadamente 10% das vezes
                $this->limparNotificacoesAntigas();
            }
            
            return ['success' => true, 'notificacoes' => $notificacoes];
        } catch (Exception $e) {
            error_log("❌ ERRO EM GET_NOTIFICACOES: " . $e->getMessage());
            return ['success' => false, 'notificacoes' => [], 'error' => $e->getMessage()];
        }
    }

    private function limparNotificacoesAntigas() {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM notificacoes_globais WHERE criada_em < DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Erro ao limpar notificações antigas: " . $e->getMessage());
        }
    }

    public function getTempoPausaExcedido($nome) {
        try {
            $tempoLimite = 20 * 60; // 20 minutos em segundos
            $stmt = $this->pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, inicio_pausa, NOW()) as tempo_decorrido FROM controle_pausa WHERE nome = ? AND status = 'pausa'");
            $stmt->execute([$nome]);
            $result = $stmt->fetch();
            
            if ($result) {
                $tempo = $result['tempo_decorrido'];
                return [
                    'tempo' => $tempo,
                    'excedido' => $tempo > $tempoLimite,
                    'tempo_excedido' => max(0, $tempo - $tempoLimite)
                ];
            }
            return ['tempo' => 0, 'excedido' => false, 'tempo_excedido' => 0];
        } catch (Exception $e) {
            error_log("Erro em getTempoPausaExcedido: " . $e->getMessage());
            return ['tempo' => 0, 'excedido' => false, 'tempo_excedido' => 0];
        }
    }

    public function getEstadoAtual() {
        try {
            $stmt = $this->pdo->query("SELECT *, inicio_espera FROM controle_pausa ORDER BY nome");
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $resultado;
        } catch (Exception $e) {
            error_log("Erro ao carregar estado: " . $e->getMessage());
            return [];
        }
    }

    public function entrarNaEspera($nome) {
        try {
            error_log("Tentando entrar na espera: " . $nome);
            
            $stmt = $this->pdo->prepare("SELECT status FROM controle_pausa WHERE nome = ?");
            $stmt->execute([$nome]);
            $atual = $stmt->fetch();
            
            error_log("Status atual de {$nome}: " . ($atual ? $atual['status'] : 'não encontrado'));
            
            if ($atual && $atual['status'] == 'espera') {
                error_log("{$nome} já está na lista de espera. Não pode entrar novamente.");
                return ['success' => false, 'error' => 'ja_na_espera', 'message' => 'Você já está na lista de espera'];
            }
            
            if ($atual && $atual['status'] == 'pausa') {
                error_log("{$nome} já está na pausa");
                return ['success' => false, 'error' => 'ja_na_pausa', 'message' => 'Você já está em pausa'];
            }

            $timestamp = gmdate('Y-m-d H:i:s');
            $stmt = $this->pdo->prepare("UPDATE controle_pausa SET status = 'espera', inicio_espera = ? WHERE nome = ?");
            $result = $stmt->execute([$timestamp, $nome]);
            
            if ($result) {
                error_log("SUCESSO: {$nome} entrou na lista de espera às {$timestamp} (UTC)");
                
                // Enviar notificação
                $this->enviarNotificacaoGlobal(
                    "📋 <strong>" . explode(' ', $nome)[0] . "</strong> entrou na fila de espera",
                    'info',
                    5000
                );
                
                return ['success' => true, 'status' => 'espera', 'inicio_espera' => $timestamp];
            } else {
                error_log("ERRO: Falha ao colocar {$nome} na espera");
                return ['success' => false, 'error' => 'erro_banco'];
            }
        } catch (Exception $e) {
            error_log("ERRO em entrarNaEspera para {$nome}: " . $e->getMessage());
            return ['success' => false, 'error' => 'excecao', 'message' => $e->getMessage()];
        }
    }

    public function entrarNaPausaAgora($nome) {
        try {
            error_log("Tentando colocar {$nome} na pausa agora");
            
            $stmt = $this->pdo->prepare("SELECT status FROM controle_pausa WHERE nome = ?");
            $stmt->execute([$nome]);
            $atual = $stmt->fetch();
            
            if (!$atual || $atual['status'] != 'espera') {
                return ['success' => false, 'error' => 'nao_na_espera', 'message' => 'Você não está na lista de espera'];
            }

            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM controle_pausa WHERE status = 'pausa'");
            $stmt->execute();
            $count = $stmt->fetch()['count'];

            if ($count >= $this->maxPausa) {
                return ['success' => false, 'error' => 'sem_vagas', 'message' => 'Não há vagas disponíveis'];
            }

            $timestamp = date('Y-m-d H:i:s');
            $stmt = $this->pdo->prepare("UPDATE controle_pausa SET status = 'pausa', inicio_pausa = ?, inicio_espera = NULL WHERE nome = ?");
            $result = $stmt->execute([$timestamp, $nome]);
            
            if ($result) {
                error_log("SUCESSO: {$nome} movido da espera para pausa");
                
                // Enviar notificação
                $this->enviarNotificacaoGlobal(
                    "✅ <strong>" . explode(' ', $nome)[0] . "</strong> entrou na pausa",
                    'success',
                    6000
                );
                
                return ['success' => true, 'status' => 'pausa'];
            } else {
                error_log("ERRO: Falha ao mover {$nome} para pausa");
                return ['success' => false, 'error' => 'erro_banco'];
            }
        } catch (Exception $e) {
            error_log("ERRO em entrarNaPausaAgora para {$nome}: " . $e->getMessage());
            return ['success' => false, 'error' => 'excecao', 'message' => $e->getMessage()];
        }
    }

    public function ficarSegundo($nome) {
        try {
            error_log("Tentando colocar {$nome} como segundo da fila");
            
            $stmt = $this->pdo->prepare("SELECT status FROM controle_pausa WHERE nome = ?");
            $stmt->execute([$nome]);
            $atual = $stmt->fetch();
            
            if (!$atual || $atual['status'] != 'espera') {
                return ['success' => false, 'error' => 'nao_na_espera', 'message' => 'Você não está na lista de espera'];
            }

            $stmt = $this->pdo->prepare("SELECT nome FROM controle_pausa WHERE status = 'espera' ORDER BY inicio_espera ASC");
            $stmt->execute();
            $fila = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $posicaoAtual = array_search($nome, $fila);
            
            if ($posicaoAtual === false) {
                return ['success' => false, 'error' => 'nao_encontrado', 'message' => 'Pessoa não encontrada na fila'];
            }

            if ($posicaoAtual !== 0) {
                return ['success' => false, 'error' => 'nao_primeiro', 'message' => 'Apenas o primeiro da fila pode escolher ficar como segundo'];
            }

            if (count($fila) < 2) {
                return ['success' => false, 'error' => 'fila_curta', 'message' => 'Não há pessoas suficientes na fila para esta operação'];
            }

            $segundo = $fila[1];
            
            $stmt = $this->pdo->prepare("SELECT inicio_espera FROM controle_pausa WHERE nome = ?");
            $stmt->execute([$nome]);
            $timestampPrimeiro = $stmt->fetch()['inicio_espera'];
            
            $stmt = $this->pdo->prepare("SELECT inicio_espera FROM controle_pausa WHERE nome = ?");
            $stmt->execute([$segundo]);
            $timestampSegundo = $stmt->fetch()['inicio_espera'];
            
            $stmt = $this->pdo->prepare("UPDATE controle_pausa SET inicio_espera = ? WHERE nome = ?");
            $stmt->execute([$timestampSegundo, $nome]);
            
            $stmt = $this->pdo->prepare("UPDATE controle_pausa SET inicio_espera = ? WHERE nome = ?");
            $stmt->execute([$timestampPrimeiro, $segundo]);
            
            error_log("SUCESSO: {$nome} (primeiro) trocou com {$segundo} (segundo)");
            
            // Enviar notificação
            $this->enviarNotificacaoGlobal(
                "🔄 <strong>" . explode(' ', $nome)[0] . "</strong> ficou como segundo da fila",
                'info',
                5000
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log("ERRO em ficarSegundo para {$nome}: " . $e->getMessage());
            return ['success' => false, 'error' => 'excecao', 'message' => $e->getMessage()];
        }
    }

    public function moverParaUltimaPosicao($nome) {
        try {
            $stmt = $this->pdo->prepare("SELECT status FROM controle_pausa WHERE nome = ?");
            $stmt->execute([$nome]);
            $atual = $stmt->fetch();
            
            if (!$atual || $atual['status'] != 'espera') {
                return ['success' => false, 'error' => 'nao_na_espera', 'message' => 'Você não está na lista de espera'];
            }

            $stmt = $this->pdo->prepare("SELECT nome, inicio_espera FROM controle_pausa WHERE status = 'espera' ORDER BY inicio_espera ASC");
            $stmt->execute();
            $fila = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $posicaoAtual = -1;
            foreach ($fila as $index => $pessoa) {
                if ($pessoa['nome'] === $nome) {
                    $posicaoAtual = $index;
                    break;
                }
            }
            
            if ($posicaoAtual === -1) {
                return ['success' => false, 'error' => 'nao_encontrado', 'message' => 'Pessoa não encontrada na fila'];
            }

            if ($posicaoAtual === count($fila) - 1) {
                return ['success' => false, 'error' => 'ja_ultimo', 'message' => 'Você já é o último da fila'];
            }

            $ultimo = end($fila);
            $novoTimestamp = date('Y-m-d H:i:s', strtotime($ultimo['inicio_espera'] . ' +1 second'));
            
            $stmt = $this->pdo->prepare("UPDATE controle_pausa SET inicio_espera = ? WHERE nome = ?");
            $result = $stmt->execute([$novoTimestamp, $nome]);
            
            if ($result) {
                error_log($nome . " movido para última posição - Novo timestamp: " . $novoTimestamp);
                
                // Enviar notificação
                $this->enviarNotificacaoGlobal(
                    "🔽 <strong>" . explode(' ', $nome)[0] . "</strong> foi para o final da fila",
                    'info',
                    5000
                );
                
                return ['success' => true];
            }
            
            return ['success' => false, 'error' => 'erro_banco'];
            
        } catch (Exception $e) {
            error_log("Erro em moverParaUltimaPosicao: " . $e->getMessage());
            return ['success' => false, 'error' => 'excecao', 'message' => $e->getMessage()];
        }
    }

    public function sairDaPausa($nome) {
        try {
            $stmt = $this->pdo->prepare("SELECT status, inicio_pausa FROM controle_pausa WHERE nome = ?");
            $stmt->execute([$nome]);
            $atual = $stmt->fetch();

            if ($atual && $atual['status'] == 'pausa') {
                $stmt = $this->pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, inicio_pausa, NOW()) as tempo_decorrido FROM controle_pausa WHERE nome = ?");
                $stmt->execute([$nome]);
                $tempo = $stmt->fetch();
                $tempoPausa = $tempo['tempo_decorrido'];
                
                $stmt = $this->pdo->prepare("UPDATE controle_pausa SET tempo_total_pausa = tempo_total_pausa + ?, status = 'disponivel', inicio_pausa = NULL WHERE nome = ?");
                $stmt->execute([$tempoPausa, $nome]);

                error_log($nome . " saiu da pausa (sem mover próximo automaticamente)");
                
                // Enviar notificação
                $this->enviarNotificacaoGlobal(
                    "👋 <strong>" . explode(' ', $nome)[0] . "</strong> saiu da pausa",
                    'info',
                    5000
                );
                
                return ['success' => true];
            }
            return ['success' => false, 'error' => 'nao_na_pausa'];
        } catch (Exception $e) {
            error_log("Erro em sairDaPausa: " . $e->getMessage());
            return ['success' => false, 'error' => 'excecao', 'message' => $e->getMessage()];
        }
    }

    public function sairDaEspera($nome) {
        try {
            $stmt = $this->pdo->prepare("SELECT status, inicio_espera FROM controle_pausa WHERE nome = ?");
            $stmt->execute([$nome]);
            $atual = $stmt->fetch();

            if ($atual && $atual['status'] == 'espera') {
                $stmt = $this->pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, inicio_espera, NOW()) as tempo_decorrido FROM controle_pausa WHERE nome = ?");
                $stmt->execute([$nome]);
                $tempo = $stmt->fetch();
                $tempoEspera = $tempo['tempo_decorrido'];
                
                $stmt = $this->pdo->prepare("UPDATE controle_pausa SET tempo_total_espera = tempo_total_espera + ?, status = 'disponivel', inicio_espera = NULL WHERE nome = ?");
                $result = $stmt->execute([$tempoEspera, $nome]);
                
                if ($result) {
                    error_log($nome . " saiu da espera");
                    
                    // Enviar notificação
                    $this->enviarNotificacaoGlobal(
                        "🚪 <strong>" . explode(' ', $nome)[0] . "</strong> saiu da fila de espera",
                        'info',
                        5000
                    );
                }
                
                return ['success' => $result];
            }
            return ['success' => false, 'error' => 'nao_na_espera'];
        } catch (Exception $e) {
            error_log("Erro em sairDaEspera: " . $e->getMessage());
            return ['success' => false, 'error' => 'excecao', 'message' => $e->getMessage()];
        }
    }

    public function verificarTempoExpirado() {
        try {
            $tempoLimite = 20 * 60;
            
            $stmt = $this->pdo->prepare("SELECT nome FROM controle_pausa WHERE status = 'pausa' AND TIMESTAMPDIFF(SECOND, inicio_pausa, NOW()) > ?");
            $stmt->execute([$tempoLimite]);
            
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Erro em verificarTempoExpirado: " . $e->getMessage());
            return [];
        }
    }

    public function getTempoPausa($nome) {
        try {
            $stmt = $this->pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, inicio_pausa, NOW()) as tempo_decorrido FROM controle_pausa WHERE nome = ? AND status = 'pausa'");
            $stmt->execute([$nome]);
            $result = $stmt->fetch();
            return $result ? $result['tempo_decorrido'] : 0;
        } catch (Exception $e) {
            error_log("Erro em getTempoPausa: " . $e->getMessage());
            return 0;
        }
    }
}

// Processar requisições
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $controle = new ControlePausa($pdo);
        $data = json_decode(file_get_contents('php://input'), true);
        
        $acao = $data['acao'] ?? '';
        $nome = $data['nome'] ?? '';
        
        error_log("=== REQUISIÇÃO: " . $acao . " para " . $nome . " ===");
        
        switch ($acao) {
            case 'inicializar':
                $controle->inicializarParticipantes();
                echo json_encode(['success' => true]);
                break;
                
            case 'entrar_espera':
                $result = $controle->entrarNaEspera($nome);
                echo json_encode($result);
                break;
                
            case 'entrar_na_pausa_agora':
                $result = $controle->entrarNaPausaAgora($nome);
                echo json_encode($result);
                break;
                
            case 'ficar_segundo':
                $result = $controle->ficarSegundo($nome);
                echo json_encode($result);
                break;
                
            case 'mover_ultima_posicao':
                $result = $controle->moverParaUltimaPosicao($nome);
                echo json_encode($result);
                break;
                
            case 'sair_pausa':
                $result = $controle->sairDaPausa($nome);
                echo json_encode($result);
                break;
                
            case 'sair_espera':
                $result = $controle->sairDaEspera($nome);
                echo json_encode($result);
                break;
                
            case 'get_estado':
                $estado = $controle->getEstadoAtual();
                $expirados = $controle->verificarTempoExpirado();
                echo json_encode(['success' => true, 'estado' => $estado, 'expirados' => $expirados]);
                break;
                
            case 'get_tempo_pausa':
                $tempo = $controle->getTempoPausa($nome);
                echo json_encode(['tempo' => $tempo]);
                break;

            case 'enviar_notificacao':
                $mensagem = $data['mensagem'] ?? '';
                $tipo = $data['tipo'] ?? 'info';
                $duracao = $data['duracao'] ?? 10000;
                
                $result = $controle->enviarNotificacaoGlobal($mensagem, $tipo, $duracao);
                echo json_encode($result);
                break;

            case 'get_notificacoes':
                $ultimaNotificacao = $data['ultima_notificacao'] ?? null;
                $result = $controle->getNotificacoes($ultimaNotificacao);
                echo json_encode($result);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Ação inválida: ' . $acao]);
        }
        
        error_log("=== RESPOSTA ENVIADA ===");
        
    } catch (Exception $e) {
        error_log("ERRO GERAL: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Erro interno do servidor']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
}
?>