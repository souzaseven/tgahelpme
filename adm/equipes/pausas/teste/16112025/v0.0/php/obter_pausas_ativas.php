<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Simulação - substitua pela sua lógica de banco de dados
$pausasAtivas = [
    // Exemplo de estrutura esperada
    // ['nome' => 'João Silva', 'inicio' => '2024-01-15 10:30:00'],
    // ['nome' => 'Maria Santos', 'inicio' => '2024-01-15 10:35:00']
];

echo json_encode($pausasAtivas);
?>