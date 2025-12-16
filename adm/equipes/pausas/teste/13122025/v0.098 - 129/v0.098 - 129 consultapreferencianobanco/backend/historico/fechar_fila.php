<?php
// ============================================================
// fechar_fila.php
// Fecha um período de fila e calcula tempo de espera
// ============================================================

if (!isset($operadorId) || !isset($equipe)) {
    // Segurança: não executa se chamado indevidamente
    return;
}

try {

    // --------------------------------------------------------
    // 1) BUSCAR ÚLTIMO REGISTRO DE FILA ABERTO
    // --------------------------------------------------------
    $sql = $pdo->prepare("
        SELECT id, inicio
        FROM log_fila
        WHERE operador_id = :op
          AND equipe = :equipe
          AND fim IS NULL
        ORDER BY inicio DESC
        LIMIT 1
    ");
    $sql->execute([
        ":op"     => $operadorId,
        ":equipe" => $equipe
    ]);

    $fila = $sql->fetch(PDO::FETCH_ASSOC);

    if (!$fila) {
        // Nenhum registro aberto — evita erro silenciosamente
        return;
    }

    $inicio = new DateTime($fila["inicio"]);
    $fim    = new DateTime();

    $tempoSegundos = $fim->getTimestamp() - $inicio->getTimestamp();
    $tempoMinutos  = (int) floor($tempoSegundos / 60);

    // --------------------------------------------------------
    // 2) ATUALIZAR REGISTRO DE FILA
    // --------------------------------------------------------
    $upd = $pdo->prepare("
        UPDATE log_fila
        SET 
            fim = NOW(),
            tempo_espera_segundos = :tempo
        WHERE id = :id
        LIMIT 1
    ");
    $upd->execute([
        ":tempo" => $tempoSegundos,
        ":id"    => $fila["id"]
    ]);

    // --------------------------------------------------------
    // 3) VERIFICAR EXCESSO DE TEMPO NA FILA
    // Regras (ajustáveis):
    // +10 min → nível 1
    // +20 min → nível 2
    // --------------------------------------------------------
    $limiteBase = 10; // minutos padrão aceitável
    $nivelExcesso = 0;

    if ($tempoMinutos > ($limiteBase + 10)) {
        $nivelExcesso = 2;
    } elseif ($tempoMinutos > $limiteBase) {
        $nivelExcesso = 1;
    }

    if ($nivelExcesso > 0) {

        $ins = $pdo->prepare("
            INSERT INTO log_excessos
            (
                operador_id,
                operador_nome,
                equipe,
                tipo,
                limite_min,
                tempo_real_min,
                nivel_excesso,
                data
            ) VALUES (
                :op,
                :nome,
                :equipe,
                'fila',
                :limite,
                :tempo,
                :nivel,
                CURDATE()
            )
        ");

        $ins->execute([
            ":op"      => $operadorId,
            ":nome"    => $operadorNome ?? '',
            ":equipe"  => $equipe,
            ":limite"  => $limiteBase,
            ":tempo"   => $tempoMinutos,
            ":nivel"   => $nivelExcesso
        ]);
    }

} catch (Exception $e) {
    // ⚠️ Nunca quebrar o fluxo principal
    // Se quiser, pode logar em arquivo futuramente
    // error_log("[FECHAR_FILA] " . $e->getMessage());
}
