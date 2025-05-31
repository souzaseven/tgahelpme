<?php
// Conexão com o banco de dados
$host = '108.167.151.50';
$dbname = 'tgamea80_SUPORTE';
$user = 'tgamea80_tgamea80';
$password = 'anderson@2250';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (isset($_POST['id']) && isset($_POST['aprovado']) && isset($_POST['versao'])) {
        $id = $_POST['id'];
        $aprovado = $_POST['aprovado'];
        $versao = $_POST['versao'];

        // Atualiza a sugestão no banco
        $stmt = $pdo->prepare("UPDATE sugestoes SET aprovado = ?, versao = ? WHERE id = ?");
        $stmt->execute([$aprovado, $versao, $id]);

        // Exibe mensagem de sucesso
        echo "<p>Sugestão atualizada com sucesso!</p>";

        // Adiciona um redirecionamento para a página consultasugestao.php após 3 segundos
        echo "<script>
                setTimeout(function() {
                    window.location.href = './consultasugestao.php';
                }, 3000);
              </script>";

    } else {
        echo "<p>Dados incompletos.</p>";
    }
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>
