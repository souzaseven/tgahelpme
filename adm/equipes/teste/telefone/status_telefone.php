<?php
header('Content-Type: application/json; charset=utf-8');

require_once './conexao_page.php';

$arquivoModo = __DIR__ . '/semana_modo.json';

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

function carregarModoSemana($arquivoModo) {
  $padrao = [
    'modo' => 'automatico',
    'manual' => [
      'subtitulo' => '',
      'aviso_resumo' => '',
      'telefone' => [],
      'chat' => [],
      'folga' => [],
      'compensacao' => []
    ]
  ];

  if (!file_exists($arquivoModo)) {
    return $padrao;
  }

  $conteudo = @file_get_contents($arquivoModo);
  if ($conteudo === false || trim($conteudo) === '') {
    return $padrao;
  }

  $json = json_decode($conteudo, true);
  if (!is_array($json)) {
    return $padrao;
  }

  $modo = isset($json['modo']) && $json['modo'] === 'manual' ? 'manual' : 'automatico';
  $manual = isset($json['manual']) && is_array($json['manual']) ? $json['manual'] : [];

  return [
    'modo' => $modo,
    'manual' => [
      'subtitulo' => isset($manual['subtitulo']) && is_string($manual['subtitulo']) ? trim($manual['subtitulo']) : '',
      'aviso_resumo' => isset($manual['aviso_resumo']) && is_string($manual['aviso_resumo']) ? trim($manual['aviso_resumo']) : '',
      'telefone' => isset($manual['telefone']) && is_array($manual['telefone']) ? array_values($manual['telefone']) : [],
      'chat' => isset($manual['chat']) && is_array($manual['chat']) ? array_values($manual['chat']) : [],
      'folga' => isset($manual['folga']) && is_array($manual['folga']) ? array_values($manual['folga']) : [],
      'compensacao' => isset($manual['compensacao']) && is_array($manual['compensacao']) ? array_values($manual['compensacao']) : []
    ]
  ];
}

function juntarLista($lista) {
  if (!is_array($lista) || empty($lista)) {
    return '—';
  }

  return implode(', ', array_values(array_filter(array_map('trim', $lista))));
}

$configModo = carregarModoSemana($arquivoModo);

if (($configModo['modo'] ?? 'automatico') === 'manual') {
  echo json_encode([
    'subtitulo' => $configModo['manual']['subtitulo'] ?? '',
    'aviso_resumo' => $configModo['manual']['aviso_resumo'] ?? '',
    'modo' => 'manual',
    'manual' => $configModo['manual'],
    'telefone' => juntarLista($configModo['manual']['telefone'] ?? []),
    'chat' => juntarLista($configModo['manual']['chat'] ?? []),
    'folga' => juntarLista($configModo['manual']['folga'] ?? []),
    'compensacao' => juntarLista($configModo['manual']['compensacao'] ?? [])
  ]);
  exit;
}

$atualBanco = 'Daniel Feix';
$stmt = $pdo->query("SELECT atual_lider FROM rotacao_telefone WHERE id = 1");
if ($stmt && ($row = $stmt->fetch())) {
  $atualBanco = $row['atual_lider'];
}

$rotacao = calcularRotacao($atualBanco, $ordem);
$rotacao['modo'] = 'automatico';

echo json_encode($rotacao);
