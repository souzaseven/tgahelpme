<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['file']) && isset($_POST['fileName']) && isset($_POST['dataHora'])) {
        $diretorio = './uploads/relatorios/';
        $logArquivo = $diretorio . 'log.json';

        if (!is_dir($diretorio)) {
            mkdir($diretorio, 0777, true);
        }

        $arquivoTemp = $_FILES['file']['tmp_name'];
        $nomeArquivo = $_POST['fileName'] . '.rtm';
        $caminhoArquivo = $diretorio . $nomeArquivo;
        $dataHora = $_POST['dataHora'];

        // Carrega os registros existentes no log.json
        $logs = file_exists($logArquivo) ? json_decode(file_get_contents($logArquivo), true) : [];

        // Define o próximo número sequencial
        $sequencial = count($logs) > 0 ? max(array_column($logs, 'sequencial')) + 1 : 1;

        $adicionadoPor = "Adicionado em $dataHora";

        if (move_uploaded_file($arquivoTemp, $caminhoArquivo)) {
            $logs[] = [
                'arquivo' => $nomeArquivo,
                'adicionadoPor' => $adicionadoPor,
                'sequencial' => $sequencial
            ];

            file_put_contents($logArquivo, json_encode($logs, JSON_PRETTY_PRINT));

            echo json_encode(['sucesso' => 'Arquivo enviado com sucesso']);
        } else {
            echo json_encode(['erro' => 'Falha ao mover o arquivo para o servidor']);
        }
    } else {
        echo json_encode(['erro' => 'Erro ao enviar o arquivo']);
    }
} else {
    echo json_encode(['erro' => 'Método de requisição inválido']);
}
?>
