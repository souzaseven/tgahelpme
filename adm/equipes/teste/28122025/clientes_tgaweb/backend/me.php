<?php
// backend/me.php
declare(strict_types=1);

require __DIR__ . "/auth.php";

$logged = !empty($_SESSION["admin_logged"]);
json_out([
  "success" => true,
  "logged" => $logged,
  "csrf" => $logged ? csrf_token() : null
]);
