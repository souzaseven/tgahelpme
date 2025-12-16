<?php
header("Content-Type: application/json; charset=utf-8");

echo json_encode([
    "status" => "PHP EXECUTOU",
    "dir"    => __DIR__,
    "files"  => scandir(__DIR__)
]);
exit;
