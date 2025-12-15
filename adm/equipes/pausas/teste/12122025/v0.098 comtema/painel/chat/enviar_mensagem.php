<?php
header("Content-Type: application/json; charset=utf-8");

// Caminho correto para conexão
require_once "../../backend/conexao.php";

try {

    // Verificar se os dados necessários estão presentes no POST
    if (empty($_POST["mensagem"]) || empty($_POST["de_id"]) || empty($_POST["de_nome"]) || empty($_POST["equipe"])) {
        throw new Exception("Campos obrigatórios ausentes.");
    }

    // Pegando os dados do POST
    $de_id   = intval($_POST["de_id"]);
    $de_nome = htmlspecialchars($_POST["de_nome"]);
    $para    = htmlspecialchars($_POST["para"]);
    $equipe  = htmlspecialchars($_POST["equipe"]);
    $msg     = trim($_POST["mensagem"]);

    // Verifica se a mensagem não está vazia
    if (empty($msg)) {
        throw new Exception("A mensagem não pode ser vazia.");
    }

    // Se "para" for "todos", envia como mensagem pública
    $para_id = ($para === "todos" ? null : intval($para));

    // Prepara o SQL para inserir a mensagem no banco
    $sql = $pdo->prepare("
        INSERT INTO chat_mensagens (de_id, de_nome, para_id, equipe, mensagem, data_envio)
        VALUES (:de_id, :de_nome, :para_id, :equipe, :mensagem, NOW())
    ");

    // Executa a query com os parâmetros recebidos
    $sql->execute([
        ":de_id"   => $de_id,
        ":de_nome" => $de_nome,
        ":para_id" => $para_id,
        ":equipe"  => $equipe,
        ":mensagem"=> $msg
    ]);

    // Retorna uma resposta de sucesso
    echo json_encode(["success" => true]);

} catch (Exception $e) {
    // Em caso de erro, loga o erro e retorna a mensagem ao usuário
    error_log("[CHAT-ERRO] " . $e->getMessage());  // Log para depuração

    http_response_code(500);
    echo json_encode([
        "erro" => true,
        "mensagem" => $e->getMessage()
    ]);
}
?>
