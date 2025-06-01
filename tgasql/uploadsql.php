<?php
header('Content-Type: application/json');

$diretorio = './uploads/';
if (!is_dir($diretorio)) {
    mkdir($diretorio, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['titulo']) && isset($_POST['dataHora'])) {
            $titulo = trim($_POST['titulo']);
            $texto = trim($_POST['texto'] ?? '');
            $dataHora = trim($_POST['dataHora']);

            if (empty($titulo)) {
                echo json_encode(['erro' => 'O título é obrigatório.']);
                exit;
            }

            $nomeArquivo = preg_replace('/[^a-zA-Z0-9_-]/', '_', $titulo) . '.txt';
            $caminhoArquivo = $diretorio . $nomeArquivo;

            if (!empty($texto)) {
                file_put_contents($caminhoArquivo, $texto);
            } elseif (isset($_FILES['file'])) {
                $arquivoTemp = $_FILES['file']['tmp_name'];
                move_uploaded_file($arquivoTemp, $caminhoArquivo);
            } else {
                echo json_encode(['erro' => 'É necessário enviar texto ou um arquivo.']);
                exit;
            }

            // Adicionando ao log.json
            $log = [
                'arquivo' => $nomeArquivo,
                'adicionadoPor' => $dataHora,
                'sequencial' => time() // Usado como identificador único
            ];

            $logArquivo = $diretorio . 'log.json';
            $logs = file_exists($logArquivo) ? json_decode(file_get_contents($logArquivo), true) : [];
            $logs[] = $log;
            file_put_contents($logArquivo, json_encode($logs, JSON_PRETTY_PRINT));

            echo json_encode(['sucesso' => 'Arquivo salvo com sucesso!']);
        } else {
            echo json_encode(['erro' => 'Dados incompletos.']);
        }
    } catch (Exception $e) {
        echo json_encode(['erro' => 'Erro no servidor: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['erro' => 'Método não permitido.']);
}
?>
