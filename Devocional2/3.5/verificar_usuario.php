<?php
// Endpoint chamado por handleConfirmarLogin (script.js) para autenticar
// o usuário antes de liberar a edição de um devocional
require 'conexao.php';
header('Content-Type: application/json');

// Dados recebidos via POST + informações da requisição para o log de acesso
$nome = $_POST['nome'] ?? '';
$senha = $_POST['senha'] ?? '';
$pagina = 'devocional'; // Ou use outra identificação da página se necessário
$ip = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';

// Validação básica: usuário e senha são obrigatórios
if (!$nome || !$senha) {
  echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
  exit;
}

try {
  // Verifica se existe usuário com esse nome/senha cadastrado
  $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE nome = ? AND senha = ?");
  $stmt->execute([$nome, $senha]);
  $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

  $sucesso = $usuario ? 1 : 0;

  // Registrar tentativa de login no logs_acesso (sucesso ou falha)
  $log = $pdo->prepare("INSERT INTO logs_acesso (usuario_tentado, senha_tentada, ip_origem, pagina, sucesso, data_tentativa)
                        VALUES (:usuario, :senha, :ip, :pagina, :sucesso, NOW())");
  $log->execute([
    ':usuario' => $nome,
    ':senha' => $senha,
    ':ip' => $ip,
    ':pagina' => $pagina,
    ':sucesso' => $sucesso
  ]);

  // Responde de acordo com o resultado da autenticação
  if ($sucesso) {
    echo json_encode(['success' => true]);
  } else {
    echo json_encode(['success' => false, 'message' => 'Usuário ou senha inválidos.']);
  }

} catch (Exception $e) {
  // Erro de banco/consulta
  echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
