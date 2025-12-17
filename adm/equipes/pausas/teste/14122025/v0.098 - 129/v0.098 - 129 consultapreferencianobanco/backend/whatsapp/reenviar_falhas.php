require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/zapi_client.php';

$stmt = $pdo->query("
    SELECT *
    FROM logs_whatsapp
    WHERE sucesso = 0
      AND tentativas < 3
");

$falhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($falhas as $log) {

    $res = enviarWhatsapp($log['telefone'], $log['mensagem']);

    $pdo->prepare("
        UPDATE logs_whatsapp
        SET 
            sucesso = ?,
            tentativas = tentativas + 1,
            ultimo_erro = ?
        WHERE id = ?
    ")->execute([
        $res['success'] ? 1 : 0,
        json_encode($res, JSON_UNESCAPED_UNICODE),
        $log['id']
    ]);
}
