<?php
// backend/login.php
declare(strict_types=1);

require __DIR__ . "/auth.php";
$config = require __DIR__ . "/config.php";

$raw = file_get_contents("php://input");
$body = json_decode($raw, true);

$user = trim((string)($body["user"] ?? ""));
$pass = (string)($body["pass"] ?? "");

if ($user === $config["auth"]["user"] && $pass === $config["auth"]["pass"]) {
  $_SESSION["admin_logged"] = true;
  $csrf = csrf_token();
  json_out(["success" => true, "csrf" => $csrf]);
}

json_out(["success" => false, "error" => "Usuário ou senha inválidos."], 401);
