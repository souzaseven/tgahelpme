<?php
require_once "conexao.php";

$nome = $_POST["nome"] ?? "";
$equipe = $_POST["equipe"] ?? "";

if(!$nome) {
    respostaJSON(["success"=>false,"erro"=>"Nome não enviado"]);
}

$sql = $pdo->prepare("
    UPDATE controle_pausa 
    SET status='online', inicio_pausa=NULL, inicio_espera=NULL, posicao_fila=NULL
    WHERE nome_usuario = :n AND equipe = :e
");

$sql->execute([
    ":n"=>$nome,
    ":e"=>$equipe
]);

respostaJSON(["success"=>true]);
