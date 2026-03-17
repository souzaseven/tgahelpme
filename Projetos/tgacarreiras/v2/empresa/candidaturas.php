<?php
require_once __DIR__ . '/../backend/conexao.php';
session_start();

if (!isset($_SESSION['empresa_id'])) {
    header("Location: login.php");
    exit;
}

$empresa_id = $_SESSION['empresa_id'];

$stmt = $pdo->prepare("
SELECT c.*, v.titulo
FROM candidaturas c
JOIN vagas v ON c.vaga_id = v.id
WHERE v.empresa_id=?
ORDER BY c.data_candidatura DESC
");
$stmt->execute([$empresa_id]);
$candidaturas = $stmt->fetchAll();
?>

<h2>Candidaturas Recebidas</h2>

<?php foreach ($candidaturas as $c): ?>
<p>
<strong><?= htmlspecialchars($c['nome']) ?></strong>
- <?= htmlspecialchars($c['titulo']) ?>
- <?= ucfirst($c['status']) ?>
</p>
<?php endforeach; ?>
