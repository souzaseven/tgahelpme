<?php

declare(strict_types=1);

/**
 * Arquivo de compatibilidade para includes antigos (require 'backend/conexao.php').
 * O ponto central de conexão é backend/database/conexao.php — mantenha
 * qualquer alteração de lógica de conexão apenas lá.
 */
require_once __DIR__ . '/database/conexao.php';
