<?php
// ============================================================
// upload_comprovante.php — Recebe a foto do comprovante (câmera ou
// galeria) usada em Despesas/Receitas. É um passo separado do
// salvamento do lançamento em si: o front sobe a imagem primeiro
// e recebe de volta o caminho salvo, que só depois vai junto no
// JSON de despesas.php/receitas.php como campo `comprovante_path`
// — assim o form principal continua sendo um POST JSON normal, sem
// precisar virar multipart/form-data.
//
// Nunca confia no nome/extensão que o navegador manda: valida o
// conteúdo de verdade com getimagesize() e gera um nome aleatório.
//
// POST multipart, campo "comprovante"
// ============================================================
require_once __DIR__ . '/../../backend/banco/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respostaJSON(['success' => false, 'erro' => 'Método não permitido.']);
}

if (empty($_FILES['comprovante']['tmp_name']) || $_FILES['comprovante']['error'] !== UPLOAD_ERR_OK) {
    respostaJSON(['success' => false, 'erro' => 'Nenhum arquivo enviado.']);
}

$tmpName = $_FILES['comprovante']['tmp_name'];

if ($_FILES['comprovante']['size'] > 5 * 1024 * 1024) {
    respostaJSON(['success' => false, 'erro' => 'Arquivo muito grande. Máximo permitido: 5 MB.']);
}

// Não confia em mime/extensão enviados pelo cliente — confirma que é
// uma imagem de verdade lendo o próprio conteúdo do arquivo.
$info = @getimagesize($tmpName);
if ($info === false) {
    respostaJSON(['success' => false, 'erro' => 'Arquivo não é uma imagem válida.']);
}

$extensoesPermitidas = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG  => 'png',
    IMAGETYPE_WEBP => 'webp',
    IMAGETYPE_GIF  => 'gif',
];
$tipo = $info[2];
if (!isset($extensoesPermitidas[$tipo])) {
    respostaJSON(['success' => false, 'erro' => 'Formato de imagem não suportado. Use JPG, PNG, WEBP ou GIF.']);
}

$ano = date('Y');
$mes = date('m');
$dirRelativo = "uploads/comprovantes/{$ano}/{$mes}";
$dirAbsoluto = __DIR__ . '/../../' . $dirRelativo;

if (!is_dir($dirAbsoluto) && !mkdir($dirAbsoluto, 0755, true) && !is_dir($dirAbsoluto)) {
    respostaJSON(['success' => false, 'erro' => 'Não foi possível preparar o diretório de upload.']);
}

$nomeArquivo = bin2hex(random_bytes(16)) . '.' . $extensoesPermitidas[$tipo];
$caminhoRelativo = "{$dirRelativo}/{$nomeArquivo}";
$caminhoAbsoluto = "{$dirAbsoluto}/{$nomeArquivo}";

if (!move_uploaded_file($tmpName, $caminhoAbsoluto)) {
    respostaJSON(['success' => false, 'erro' => 'Falha ao salvar o arquivo.']);
}

respostaJSON(['success' => true, 'path' => $caminhoRelativo]);
