<?php
/**
 * candidatar.php — OBSOLETO
 *
 * Este arquivo não é mais necessário.
 * O processamento da candidatura foi movido para candidato/vaga.php.
 *
 * Qualquer requisição aqui recebe redirect seguro de volta para a vaga.
 */

$vaga_id = null;

// Tentar recuperar o ID de onde vier
if (!empty($_POST['vaga_id'])) {
    $vaga_id = (int) $_POST['vaga_id'];
} elseif (!empty($_GET['id'])) {
    $vaga_id = (int) $_GET['id'];
}

if ($vaga_id) {
    header("Location: vaga.php?id=" . $vaga_id);
} else {
    header("Location: index.php");
}
exit;