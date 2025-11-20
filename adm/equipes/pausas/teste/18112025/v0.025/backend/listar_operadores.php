<?php
// ============================================================
// listar_operadores.php - Lista operadores de uma equipe
// Usado na tela de login (seleção de operador)
// ============================================================

require_once "conexao.php";

try {
    // Validar ID da equipe
    $idEquipe = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$idEquipe) {
        respostaJSON([
            "success" => false,
            "erro"    => "ID de equipe inválido ou não informado."
        ]);
    }

    // ========================================================
    // Ajuste aqui conforme o seu banco:
    //  - Tabela: operadores
    //  - Colunas: id, nome, equipe_id, ativo
    // ========================================================

    $sql = "SELECT id, nome 
            FROM operadores 
            WHERE equipe_id = :idEquipe
              AND (ativo = 1 OR ativo IS NULL)
            ORDER BY nome ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(":idEquipe", $idEquipe, PDO::PARAM_INT);
    $stmt->execute();

    $operadores = $stmt->fetchAll();

    respostaJSON([
        "success"    => true,
        "equipe_id"  => $idEquipe,
        "operadores" => $operadores
    ]);

} catch (Exception $e) {

    respostaJSON([
        "success" => false,
        "erro"    => "Erro ao carregar operadores.",
        "detalhe" => $e->getMessage() // em produção podemos ocultar se quiser
    ]);
}
