<?php
// Configuração do banco de dados
$servidor = "108.167.151.50";
$banco = "tgamea80_MYSUITEATENDIMENTOS";
$usuario = "tgamea80";
$senha = "anderson@2249";

try {
    $conn = new PDO("mysql:host=$servidor;dbname=$banco", $usuario, $senha);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}
?>
