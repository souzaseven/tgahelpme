<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((isset($_FILES['file']) || !empty($_POST['descricao'])) && isset($_POST['titulo']) && isset($_POST['dataHora'])) {
        $diretorio = './uploads/formulas/';
        $logArquivo = $diretorio . 'log.json';

        if (!is_dir($diretorio)) {
            mkdir($diretorio, 0777, true);
        }

        $titulo = preg_replace('/[^a-zA-Z0-9-_]/', '_', $_POST['titulo']); // Limpa nome do arquivo
        $dataHora = $_POST['dataHora'];

        $nomeArquivo = $titulo . '.txt';
        $caminhoArquivo = $diretorio . $nomeArquivo;

        // Cria o arquivo a partir da descrição ou move o enviado
        if (isset($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $extensao = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $nomeArquivo = $titulo . '.' . $extensao;
            $caminhoArquivo = $diretorio . $nomeArquivo;
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $caminhoArquivo)) {
                echo json_encode(['erro' => 'Falha ao mover o arquivo para o servidor']);
                exit;
            }
        } else {
            if (empty($_POST['descricao'])) {
                echo json_encode(['erro' => 'Você deve preencher a descrição ou enviar um arquivo.']);
                exit;
            }
            $descricao = $_POST['descricao'];

// Normaliza e remove acentos
$descricao = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $descricao);

// Garante que está em UTF-8
$descricao = mb_convert_encoding($descricao, 'UTF-8', 'auto');

file_put_contents($caminhoArquivo, $descricao);

        }

        // Carrega registros existentes
        $logs = file_exists($logArquivo) ? json_decode(file_get_contents($logArquivo), true) : [];
        $sequencial = count($logs) > 0 ? max(array_column($logs, 'sequencial')) + 1 : 1;
        $adicionadoPor = "Adicionado em $dataHora";

        $logs[] = [
            'arquivo' => $nomeArquivo,
            'adicionadoPor' => $adicionadoPor,
            'sequencial' => $sequencial
        ];

        file_put_contents($logArquivo, json_encode($logs, JSON_PRETTY_PRINT));

        echo json_encode(['sucesso' => 'Fórmula salva com sucesso']);
    } else {
        echo json_encode(['erro' => 'Campos obrigatórios ausentes']);
    }
} else {
    echo json_encode(['erro' => 'Método de requisição inválido']);
}
?>
