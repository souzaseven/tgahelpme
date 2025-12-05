<?php
header("Content-Type: application/json; charset=utf-8");
require_once "conexao.php";

/* ============================================================
   CAPTURA DE DADOS (ACEITA POST OU GET)
============================================================ */
$nome   = trim($_POST["nome"]   ?? $_GET["nome"]   ?? "");
$equipe = trim($_POST["equipe"] ?? $_GET["equipe"] ?? "");

/* ============================================================
   VALIDAÇÃO
============================================================ */
if ($nome === "" || $equipe === "") {
    echo json_encode([
        "success" => false,
        "erro"    => "Nome ou equipe não enviados",
        "debug"   => [
            "POST" => $_POST,
            "GET"  => $_GET
        ]
    ]);
    exit;
}

/* ============================================================
   REGISTRO DE DEBUG
============================================================ */
file_put_contents("debug_login.txt",
    "NOVO LOGIN → Nome: {$nome} | Equipe: {$equipe} | Data: " . date("Y-m-d H:i:s") . "\n",
    FILE_APPEND
);

try {

    /* ============================================================
       ATUALIZAÇÃO USANDO LOWER(TRIM())  
       → Evita erros de acento, espaços, letras maiúsculas/minúsculas
    ============================================================= */
    $sql = $pdo->prepare("
        UPDATE controle_pausa
        SET 
            status = 'ativo',
            inicio_pausa = NULL,
            inicio_espera = NULL,
            posicao_fila = NULL,
            notificacao_enviada = 0
        WHERE LOWER(TRIM(nome_usuario)) = LOWER(TRIM(:nome))
          AND LOWER(TRIM(equipe))       = LOWER(TRIM(:equipe))
        LIMIT 1
    ");

    $sql->execute([
        ":nome"   => $nome,
        ":equipe" => $equipe
    ]);

    if ($sql->rowCount() === 0) {
        echo json_encode([
            "success" => false,
            "erro"    => "Nenhum operador encontrado para atualizar.",
            "recebido" => [
                "nome" => $nome,
                "equipe" => $equipe
            ]
        ]);
        exit;
    }

    echo json_encode([
        "success" => true,
        "mensagem" => "Operador marcado como ATIVO com sucesso",
        "atualizado" => [
            "nome" => $nome,
            "equipe" => $equipe
        ]
    ]);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "erro"    => $e->getMessage(),
    ]);
}
