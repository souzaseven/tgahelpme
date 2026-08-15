<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/StateStore.php';

use App\StateStore;

header('Content-Type: application/json; charset=utf-8');

$store = new StateStore(DATA_DIR);

echo json_encode(['error' => false, 'data' => $store->read()]);
