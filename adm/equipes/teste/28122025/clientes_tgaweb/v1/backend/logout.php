<?php
// backend/logout.php
declare(strict_types=1);

require __DIR__ . "/auth.php";
session_destroy();
json_out(["success" => true]);
