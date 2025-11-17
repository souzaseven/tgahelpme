<?php
header("Content-Type: application/json; charset=utf-8");

require_once "conexao.php";

// ---------------------------------------------------------------------
// RECEBIMENTO DOS DADOS
// ---------------------------------------------------------------------
$data = json_decode(file_get_contents("php://input"), true);

$nome     = $data["nome"]    ?? null;
$tipo     = $data["tipo"]    ?? null;   // pausa | espera
$inicioMs = $data["inicio"]  ?? null;   // timestamp em milissegundos
$fimMs    = $data["fim"]     ?? null;   // timestamp em milissegundos
$duracao  = $data["duracao"] ?? null;   // segundos totais

// ---------------------------------------------------------------------
// VALIDAÇÃO DE CAMPOS
// ---------------------------------------------------------------------
if (!$nome || !$tipo || !$inicioMs || !$fimMs || $duracao === null) {
    echo json_encode([
        "success" => false,
        "error"   => "Dados incompletos recebidos para registrar o tempo."
    ]);
    exit;
}

// Tipos aceitos
$tiposValidos = ["pausa", "espera"];
if (!in_array($tipo, $tiposValidos)) {
    echo json_encode([
        "success" => false,
        "error"   => "Tipo de registro inválido: '$tipo'"
    ]);
    exit;
}

// Converte ms → segundos
$inicioSeg = intval($inicioMs / 1000);
$fimSeg    = intval($fimMs / 1000);

// Segurança extra
if ($inicioSeg <= 0 || $fimSeg <= 0 || $fimSeg < $inicioSeg) {
    echo json_encode([
        "success" => false,
        "error"   => "Timestamps inválidos ou invertidos."
    ]);
    exit;
}

// ---------------------------------------------------------------------
// INSERÇÃO NO BANCO
// ---------------------------------------------------------------------
try {

    $stmt = $pdo->prepare("
        INSERT INTO controle_tempo
        (usuario, tipo, inicio, fim, duracao_segundos, status)
        VALUES (:usuario, :tipo, FROM_UNIXTIME(:inicio), FROM_UNIXTIME(:fim), :duracao, 'finalizado')
    ");

    $stmt->execute([
        ":usuario" => $nome,
        ":tipo"    => $tipo,
        ":inicio"  => $inicioSeg,
        ":fim"     => $fimSeg,
        ":duracao" => intval($duracao)
    ]);

    echo json_encode([
        "success" => true,
        "msg"     => "Tempo registrado com sucesso."
    ]);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "error"   => "Erro ao registrar tempo: " . $e->getMessage()
    ]);

}
?>
