<?php
// ============================================================
// salvar_preferencias.php - FASE 8.3
// Atualiza preferências individuais do operador
// ============================================================

header("Content-Type: application/json; charset=utf-8");
require_once "conexao.php";

// ------------------------------------------------------------
// Captura ID
// ------------------------------------------------------------
$id = intval($_POST["id"] ?? 0);

if ($id <= 0) {
    echo json_encode([
        "success" => false,
        "erro"    => "ID do operador inválido."
    ]);
    exit;
}

// ------------------------------------------------------------
// Função para normalizar valores (0 ou 1)
// ------------------------------------------------------------
function b($v) {
    return (!empty($v) && $v != "0") ? 1 : 0;
}

$pref_toast  = b($_POST["pref_toast"]  ?? 0);
$pref_fala   = b($_POST["pref_fala"]   ?? 0);
$pref_audio  = b($_POST["pref_audio"]  ?? 0);
$pref_notif  = b($_POST["pref_notif"]  ?? 0);
$pref_voz    = $_POST["pref_voz"]      ?? "C";
$pref_alerta_alvo = $_POST["pref_alerta_alvo"] ?? "todos";

// ------------------------------------------------------------
// Volume da fala (0 a 100)
// ------------------------------------------------------------
$pref_volume_fala = isset($_POST["pref_volume_fala"])
    ? intval($_POST["pref_volume_fala"])
    : 70;

// garante limite
if ($pref_volume_fala < 0)   $pref_volume_fala = 0;
if ($pref_volume_fala > 100) $pref_volume_fala = 100;




// sanitiza pref_voz
$pref_voz = in_array($pref_voz, ["A","B","C"]) ? $pref_voz : "C";

// sanitiza alcance: 'todos' | 'meu' | 'nenhum'
$validAlvos = ["todos","meu","nenhum"];
if (!in_array($pref_alerta_alvo, $validAlvos, true)) {
    $pref_alerta_alvo = "todos";
}

// ------------------------------------------------------------
// Nome da tabela (ajuste se usar outra)
// ------------------------------------------------------------
$tabela = "controle_pausa";

try {
    $sql = $pdo->prepare("
        UPDATE {$tabela}
        SET 
            pref_toast       = :toast,
            pref_fala        = :fala,
            pref_audio       = :audio,
            pref_notif       = :notif,
            pref_voz         = :voz,
            pref_alerta_alvo = :alvo,
pref_volume_fala  = :volume
        WHERE id = :id
        LIMIT 1
    ");

    $sql->execute([
    ":id"     => $id,
":toast"  => $pref_toast,
    ":fala"   => $pref_fala,
    ":audio"  => $pref_audio,
    ":notif"  => $pref_notif,
    ":voz"    => $pref_voz,
    ":alvo"   => $pref_alerta_alvo,
    ":volume" => $pref_volume_fala

    ]);

    if ($sql->rowCount() === 0) {
        echo json_encode([
            "success" => false,
            "erro"    => "Nenhum registro atualizado (ID inexistente?)."
        ]);
        exit;
    }

    echo json_encode([
        "success"          => true,
        "msg"              => "Preferências atualizadas.",
        "id"               => $id,
        "pref_toast"       => $pref_toast,
        "pref_fala"        => $pref_fala,
        "pref_audio"       => $pref_audio,
        "pref_notif"       => $pref_notif,
        "pref_voz"         => $pref_voz,
        "pref_alerta_alvo" => $pref_alerta_alvo,
"pref_volume_fala" => $pref_volume_fala

    ]);

} catch (Throwable $e) {
    echo json_encode([
        "success" => false,
        "erro"    => "Erro ao salvar preferências.",
        "detalhe" => $e->getMessage(),
        "arquivo" => $e->getFile(),
        "linha"   => $e->getLine()
    ]);
}
