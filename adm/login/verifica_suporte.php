<?php
require_once 'conexao.php';
session_start();

$senha = $_POST['senha'] ?? '';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

try {
    $stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE senha = ?");
    $stmt->execute([$senha]);
    $usuario = $stmt->fetch();

    if ($usuario) {
        session_regenerate_id(true);
        $_SESSION['suporte_autenticado'] = true;

        // Recupera a URL de origem (se existir)
        $url_origem = $_SESSION['url_origem'] ?? '/';
        unset($_SESSION['url_origem']);

        echo json_encode([
            "sucesso" => true,
            "redirecionar_para" => $url_origem
        ]);
    } else {
        echo json_encode(["sucesso" => false]);
    }
} catch (Exception $e) {
    echo json_encode(["sucesso" => false, "erro" => $e->getMessage()]);
}
