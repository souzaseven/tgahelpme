<?php
// ============================================================
// fechar_pausa.php
// Fecha a pausa aberta, calcula duração e registra excessos
// ============================================================

if (!isset($operadorId) || !$operadorId) {
    return; // segurança: não quebra o fluxo
}

try {

    // --------------------------------------------------------
    // 1) Buscar pausa aberta do operador
    // --------------------------------------------------------
    $sql = $pdo->prepare("
        SELECT *
        FROM log_pausas
        WHERE operador_id = :id
          AND fim IS NULL
        ORDER BY inicio DESC
        LIMIT 1
    ");
    $sql->execute([":id" => $operadorId]);
    $pausa = $sql->fetch(PDO::FETCH_ASSOC);

    if (!$pausa) {
        return; // não há pausa aberta
    }

    // --------------------------------------------------------
    // 2) Calcular duração
    // --------------------------------------------------------
    $inicio = new DateTime($pausa['inicio']);
    $fim    = new DateTime();

    $duracaoSeg = $fim->getTimestamp() - $inicio->getTimestamp();
    $duracaoMin = floor($duracaoSeg / 60);

    // --------------------------------------------------------
    // 3) Definir excesso
    // --------------------------------------------------------
    $limiteMin = 10;
    $excedeu = 0;
    $nivelExcesso = 0;

    if ($duracaoMin > 10 && $duracaoMin <= 20) {
        $excedeu = 1;
        $nivelExcesso = 1;
    }

    if ($duracaoMin > 20) {
        $excedeu = 1;
        $nivelExcesso = 2;
    }

    // --------------------------------------------------------
    // 4) Atualizar log_pausas
    // --------------------------------------------------------
    $upd = $pdo->prepare("
        UPDATE log_pausas
        SET
            fim = NOW(),
            duracao_segundos = :duracao,
            excedeu = :excedeu,
            excedeu_nivel = :nivel
        WHERE id = :id
    ");
    $upd->execute([
        ":duracao" => $duracaoSeg,
        ":excedeu" => $excedeu,
        ":nivel"   => $nivelExcesso,
        ":id"      => $pausa['id']
    ]);

    // --------------------------------------------------------
    // 5) Registrar excesso (se houver)
    // --------------------------------------------------------
    if ($excedeu) {
        $ins = $pdo->prepare("
            INSERT INTO log_excessos (
                operador_id,
                operador_nome,
                equipe,
                tipo,
                limite_min,
                tempo_real_min,
                nivel_excesso,
                data
            ) VALUES (
                :op_id,
                :nome,
                :equipe,
                'pausa',
                :limite,
                :real,
                :nivel,
                CURDATE()
            )
        ");
        $ins->execute([
            ":op_id"  => $pausa['operador_id'],
            ":nome"   => $pausa['operador_nome'],
            ":equipe" => $pausa['equipe'],
            ":limite" => $limiteMin,
            ":real"   => $duracaoMin,
            ":nivel"  => $nivelExcesso
        ]);
    }

} catch (Exception $e) {
    // opcional: gravar em log_erro
    // error_log($e->getMessage());
}
