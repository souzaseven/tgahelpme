require_once __DIR__ . '/../conexao.php';

$stmt = $pdo->query("
    SELECT 
        l.id,
        l.evento,
        l.telefone,
        l.sucesso,
        l.tentativas,
        l.criado_em,
        o.nome AS operador
    FROM logs_whatsapp l
    LEFT JOIN operadores o ON o.id = l.operador_id
    ORDER BY l.criado_em DESC
    LIMIT 200
");

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
