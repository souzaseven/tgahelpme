<?php
require_once __DIR__ . '/../conexao.php';

$stmt = $pdo->query("
  SELECT id, nome, fila, lider, evolux_agent_id
  FROM operadores
  ORDER BY lider, nome
");

echo json_encode([
  "success" => true,
  "operadores" => $stmt->fetchAll(PDO::FETCH_ASSOC)
], JSON_UNESCAPED_UNICODE);
