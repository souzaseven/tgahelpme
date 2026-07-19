<?php
header('Content-Type: application/json; charset=utf-8');

require_once './conexao_page.php';

$ordem = [
  'Alex Sandro Braulio',
  'Daniel Feix',
  'Willian Pereira Reis'
];

function calcularRotacao($atual, $ordem) {
  $idx = array_search($atual, $ordem, true);
  if ($idx === false) $idx = 0;

  return [
    'anterior' => $ordem[($idx - 1 + count($ordem)) % count($ordem)],
    'atual'    => $ordem[$idx],
    'proxima'  => $ordem[($idx + 1) % count($ordem)]
  ];
}

$atualBanco = 'Daniel Feix';
$stmt = $pdo->query("SELECT atual_lider FROM rotacao_telefone WHERE id = 1");
if ($stmt && ($row = $stmt->fetch())) {
  $atualBanco = $row['atual_lider'];
}

echo json_encode(calcularRotacao($atualBanco, $ordem));
