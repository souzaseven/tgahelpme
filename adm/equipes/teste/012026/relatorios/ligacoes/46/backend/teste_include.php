<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
echo 'config OK<br>';

require_once __DIR__ . '/evolux_client.php';
echo 'client OK<br>';
