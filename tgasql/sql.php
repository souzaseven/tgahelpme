<?php
header('Content-Type: application/json');

$uploadDirectory = './uploads/';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!is_dir($uploadDirectory)) {
        echo json_encode(['erro' => 'Diretório de uploads não encontrado.']);
        exit;
    }

    $arquivos = array_diff(scandir($uploadDirectory, SCANDIR_SORT_DESCENDING), ['.', '..']);

    if (empty($arquivos)) {
        echo json_encode(['erro' => 'Nenhum arquivo encontrado.']);
        exit;
    }

    // Ordenar os arquivos pela data de modificação, mais recentes primeiro
    $arquivosOrdenados = [];
    foreach ($arquivos as $arquivo) {
        $caminhoArquivo = $uploadDirectory . $arquivo;
        if (is_file($caminhoArquivo) && pathinfo($arquivo, PATHINFO_EXTENSION) === 'sql') {
            $arquivosOrdenados[] = [
                'nome_arquivo' => pathinfo($arquivo, PATHINFO_FILENAME),
                'extensao' => 'sql',
                'caminho' => $caminhoArquivo,
                'data_modificacao' => filemtime($caminhoArquivo)
            ];
        }
    }

    if (empty($arquivosOrdenados)) {
        echo json_encode(['erro' => 'Nenhum arquivo .sql encontrado.']);
        exit;
    }

    // Ordena os arquivos pela data de modificação
    usort($arquivosOrdenados, function($a, $b) {
        return $b['data_modificacao'] - $a['data_modificacao'];
    });

    // Atribui um número sequencial aos arquivos
    foreach ($arquivosOrdenados as $index => &$arquivo) {
        $arquivo['sequencial'] = $index + 1;
    }

    echo json_encode($arquivosOrdenados);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'conexao.php';

    $usuario = $_POST['usuario'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $arquivo = $_POST['arquivo'] ?? '';

    if (!$usuario || !$senha || !$arquivo) {
        echo json_encode(['erro' => 'Dados incompletos.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE nome = ?");
        $stmt->execute([$usuario]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resultado || $senha !== $resultado['senha']) {

            echo json_encode(['erro' => 'Usuário ou senha inválidos.']);
            exit;
        }

        if ($usuario !== 'anderson') {
            echo json_encode(['erro' => 'Apenas o usuário anderson pode excluir arquivos.']);
            exit;
        }

        if (file_exists($arquivo)) {
            unlink($arquivo);
            echo json_encode(['sucesso' => true]);
        } else {
            echo json_encode(['erro' => 'Arquivo não encontrado.']);
        }
    } catch (Exception $e) {
        echo json_encode(['erro' => 'Erro ao excluir: ' . $e->getMessage()]);
    }
}
?>
