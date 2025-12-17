<?php
// ============================================================
// salvar_preferencias.php — FASE 8.5 (FINAL / ESTÁVEL)
// Fonte da verdade: BANCO DE DADOS (controle_pausa)
// Compatibilidade com campos antigos (LEGADO)
// ============================================================

header("Content-Type: application/json; charset=utf-8");
require_once "conexao.php";

// ------------------------------------------------------------
// Função utilitária: normaliza boolean (0 ou 1)
// ------------------------------------------------------------
function b($v) {
    return (!empty($v) && $v !== "0") ? 1 : 0;
}

// ------------------------------------------------------------
// Captura ID do operador
// ------------------------------------------------------------
$id = intval($_POST["id"] ?? 0);

if ($id <= 0) {
    echo json_encode([
        "success" => false,
        "erro"    => "ID do operador inválido."
    ]);
    exit;
}

// ============================================================
// PREFERÊNCIAS VISUAIS / NOTIFICAÇÕES
// ============================================================

$pref_toast = b($_POST["pref_toast"] ?? 0);
$pref_notif = b($_POST["pref_notif"] ?? 0);

$pref_alerta_alvo = $_POST["pref_alerta_alvo"] ?? "todos";
$alvosValidos = ["todos", "meu", "nenhum"];
if (!in_array($pref_alerta_alvo, $alvosValidos, true)) {
    $pref_alerta_alvo = "todos";
}
// Tema (claro ou escuro)
$pref_tema = $_POST["pref_tema"] ?? "claro";
$pref_tema = in_array($pref_tema, ["claro", "escuro"], true) ? $pref_tema : "claro";

// ============================================================
// VOZ / ÁUDIO
// ============================================================

$pref_fala  = b($_POST["pref_fala"] ?? 0);
$pref_audio = b($_POST["pref_audio"] ?? 0);

$pref_voz = $_POST["pref_voz"] ?? "C";
$pref_voz = in_array($pref_voz, ["A", "B", "C"], true) ? $pref_voz : "C";

$pref_volume_fala = intval($_POST["pref_volume_fala"] ?? 70);
$pref_volume_fala = max(0, min(100, $pref_volume_fala));

// ============================================================
// WHATSAPP — PADRÃO OFICIAL DO SISTEMA
// ============================================================

$whatsapp_optin = b($_POST["whatsapp_habilitado"] ?? 0);

// Número do WhatsApp (somente números)
$whatsapp = $_POST["whatsapp_numero"] ?? null;
$whatsapp = $whatsapp ? preg_replace('/\D/', '', $whatsapp) : null;

// Se opt-in desativado → remove número
if ($whatsapp_optin === 0) {
    $whatsapp = null;
}



// ============================================================
// ATUALIZAÇÃO NO BANCO
// ============================================================

try {

    $stmt = $pdo->prepare("
        UPDATE controle_pausa
        SET
            -- Preferências visuais
            pref_toast           = :pref_toast,
            pref_notif           = :pref_notif,
            pref_alerta_alvo     = :pref_alerta_alvo,
pref_tema = :pref_tema,

            -- Voz / áudio
            pref_fala            = :pref_fala,
            pref_audio           = :pref_audio,
            pref_voz             = :pref_voz,
            pref_volume_fala     = :pref_volume_fala,

            -- WhatsApp (NOVO PADRÃO)
            whatsapp             = :whatsapp,
            whatsapp_optin       = :whatsapp_optin,

            -- WhatsApp (LEGADO)
            whatsapp_numero      = :whatsapp_legado,
            whatsapp_habilitado  = :whatsapp_optin_legado



        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ":id"                     => $id,

        // Preferências
        ":pref_toast"             => $pref_toast,
        ":pref_notif"             => $pref_notif,
        ":pref_alerta_alvo"       => $pref_alerta_alvo,
":pref_tema" => $pref_tema,

        // Voz / áudio
        ":pref_fala"              => $pref_fala,
        ":pref_audio"             => $pref_audio,
        ":pref_voz"               => $pref_voz,
        ":pref_volume_fala"       => $pref_volume_fala,

        // WhatsApp novo
        ":whatsapp"               => $whatsapp,
        ":whatsapp_optin"         => $whatsapp_optin,

        // WhatsApp legado
        ":whatsapp_legado"        => $whatsapp,
        ":whatsapp_optin_legado"  => $whatsapp_optin




    ]);

    echo json_encode([
        "success"          => true,
        "msg"              => "Preferências salvas com sucesso.",
        "id"               => $id,
        "whatsapp"         => $whatsapp,
        "whatsapp_optin"   => $whatsapp_optin,
        "pref_volume_fala" => $pref_volume_fala
    ]);

} catch (Throwable $e) {

    echo json_encode([
        "success" => false,
        "erro"    => "Erro ao salvar preferências.",
        "detalhe" => $e->getMessage()
    ]);
}
