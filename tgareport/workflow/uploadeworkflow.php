<?php
// Mostrar erros durante o desenvolvimento
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cabeçalho JSON
header('Content-Type: application/json');

try {
    // Valida método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['erro' => 'Método de requisição inválido']);
        exit;
    }

    // Valida parâmetros
    if (!isset($_FILES['file'], $_POST['fileName'], $_POST['dataHora'])) {
        echo json_encode(['erro' => 'Parâmetros ausentes']);
        exit;
    }

    // Diretórios e caminhos
    $diretorio = './uploads/workflow/';
    $logArquivo = $diretorio . 'log.json';

    if (!is_dir($diretorio)) {
        mkdir($diretorio, 0777, true);
    }

    $arquivoTemp = $_FILES['file']['tmp_name'];
    $nomeArquivo = $_POST['fileName'] . '.TGAWF';
    $caminhoArquivo = $diretorio . $nomeArquivo;
    $dataHora = $_POST['dataHora'];

    // Carrega registros do log de forma segura
    $logs = [];
    if (file_exists($logArquivo)) {
        $conteudo = file_get_contents($logArquivo);
        $dados = json_decode($conteudo, true);
        if (is_array($dados)) {
            $logs = $dados;
        }
    }

    // Define número sequencial
    $sequencial = count($logs) > 0 ? max(array_column($logs, 'sequencial')) + 1 : 1;
    $adicionadoPor = "Adicionado em $dataHora";

    // Move o arquivo e atualiza o log
    if (move_uploaded_file($arquivoTemp, $caminhoArquivo)) {
        $logs[] = [
            'arquivo' => $nomeArquivo,
            'adicionadoPor' => $adicionadoPor,
            'sequencial' => $sequencial
        ];

        file_put_contents($logArquivo, json_encode($logs, JSON_PRETTY_PRINT));
        echo json_encode(['sucesso' => 'Arquivo enviado com sucesso']);
        exit;
    } else {
        echo json_encode(['erro' => 'Falha ao mover o arquivo para o servidor']);
        exit;
    }
} catch (Throwable $e) {
    echo json_encode(['erro' => 'Erro interno: ' . $e->getMessage()]);
    exit;
}
