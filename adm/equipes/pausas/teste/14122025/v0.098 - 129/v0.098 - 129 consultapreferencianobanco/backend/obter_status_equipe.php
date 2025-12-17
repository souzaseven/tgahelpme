<?php
// ============================================================
// obter_status_equipe.php — FASE 6 + FASE 7 + FASE 9 (ATUALIZADO)
// ------------------------------------------------------------
// Retorna listas separadas: pausa, fila, equipe completa
// - Usa posicao_fila gravada no banco
// - Calcula tempo real de espera/pausa
// - Inclui dados de WhatsApp (manual + cron)
// - TOTALMENTE compatível com painel.js atual
// ============================================================

header("Content-Type: application/json; charset=utf-8");
require_once "conexao.php";

// ============================================================
// Função de resposta
// ============================================================
if (!function_exists("respostaJSON")) {
    function respostaJSON($arr) {
        echo json_encode($arr, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ============================================================
// CAPTURA DA EQUIPE (POST — painel.js usa POST)
// ============================================================
$equipe = trim($_POST["equipe"] ?? "");

if ($equipe === "") {
    respostaJSON([
        "success" => false,
        "erro"    => "Equipe não informada."
    ]);
}

// ============================================================
// CONSULTA OPERADORES DA EQUIPE
// ============================================================
try {

    $sql = $pdo->prepare("
        SELECT 
            id,
            nome_usuario AS nome,
            status,
            posicao_fila,
            inicio_espera,
            inicio_pausa,
            elider,

            -- WHATSAPP (FONTE DA VERDADE)
            whatsapp,
            whatsapp_optin,
            whatsapp_limite_enviado

        FROM controle_pausa
        WHERE equipe = :equipe
    ");

    $sql->execute([":equipe" => $equipe]);
    $db = $sql->fetchAll(PDO::FETCH_ASSOC);

    if (!$db) {
        respostaJSON([
            "success"         => true,
            "equipe"          => $equipe,
            "pausa"           => [],
            "fila"            => [],
            "equipe_completa" => []
        ]);
    }

    $agora          = new DateTimeImmutable("now");
    $listaPausa     = [];
    $listaFila      = [];
    $equipeCompleta = [];

    foreach ($db as $op) {

        $status = strtolower(trim($op["status"] ?? ""));
        if ($status === "") {
            $status = "ativo";
        }

        // ====================================================
        // REGISTRO BASE (USADO EM TODAS AS LISTAS)
        // ====================================================
        $registroBase = [
            "id"     => (int) $op["id"],
            "nome"   => $op["nome"],
            "status" => $status,
            "elider" => isset($op["elider"]) ? (int) $op["elider"] : 0,

            // WHATSAPP — frontend + cron
            "whatsapp"                => $op["whatsapp"] ?? null,
            "whatsapp_optin"          => isset($op["whatsapp_optin"]) ? (int)$op["whatsapp_optin"] : 0,
            "whatsapp_limite_enviado" => isset($op["whatsapp_limite_enviado"]) ? (int)$op["whatsapp_limite_enviado"] : 0
        ];

        // ---------------------------
        // EQUIPE COMPLETA
        // ---------------------------
        $equipeCompleta[] = $registroBase;

        // ---------------------------
        // LISTA DE PAUSA
        // ---------------------------
        if ($status === "pausa") {

            $tempoSeg = 0;
            if (!empty($op["inicio_pausa"])) {
                try {
                    $inicio   = new DateTimeImmutable($op["inicio_pausa"]);
                    $tempoSeg = max(0, $agora->getTimestamp() - $inicio->getTimestamp());
                } catch (Exception $e) {
                    $tempoSeg = 0;
                }
            }

            $registroPausa = $registroBase;
            $registroPausa["tempo_pausa_seg"] = $tempoSeg;

            $listaPausa[] = $registroPausa;
        }

        // ---------------------------
        // LISTA DE FILA (ESPERA)
        // ---------------------------
        if ($status === "espera") {

            $tempoSeg = 0;
            if (!empty($op["inicio_espera"])) {
                try {
                    $inicio   = new DateTimeImmutable($op["inicio_espera"]);
                    $tempoSeg = max(0, $agora->getTimestamp() - $inicio->getTimestamp());
                } catch (Exception $e) {
                    $tempoSeg = 0;
                }
            }

            $registroFila = $registroBase;
            $registroFila["posicao_fila"]     = isset($op["posicao_fila"]) ? (int)$op["posicao_fila"] : null;
            $registroFila["tempo_espera_seg"] = $tempoSeg;

            $listaFila[] = $registroFila;
        }
    }

    // ========================================================
    // ORDENAÇÕES
    // ========================================================

    // Pausa: quem está há mais tempo primeiro
    usort($listaPausa, function ($a, $b) {
        return ($a["tempo_pausa_seg"] ?? 0) <=> ($b["tempo_pausa_seg"] ?? 0);
    });

    // Fila: por posição
    usort($listaFila, function ($a, $b) {
        $pa = $a["posicao_fila"] ?? 999999;
        $pb = $b["posicao_fila"] ?? 999999;
        if ($pa === $pb) {
            return ($a["id"] ?? 0) <=> ($b["id"] ?? 0);
        }
        return $pa <=> $pb;
    });

    // ========================================================
    // RETORNO FINAL
    // ========================================================
    respostaJSON([
        "success"         => true,
        "equipe"          => $equipe,
        "pausa"           => $listaPausa,
        "fila"            => $listaFila,
        "equipe_completa" => $equipeCompleta
    ]);

} catch (Throwable $e) {

    respostaJSON([
        "success" => false,
        "erro"    => "Erro ao obter status da equipe.",
        "detalhe" => $e->getMessage()
    ]);
}
