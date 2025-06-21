<?php
header('Content-Type: application/json');

// 🔒 CONFIGURAÇÕES
$email = "souza5661.7@gmail.com";
$password = "Soueu2025@";
$deviceToken = "9f5938b6-b2eb-4c4f-94f1-4fcbda0e66d8"; // ex: 73b76b21-1790-4936-ad9d-xxxxxxxxxxxx
$placa = $_GET['placa'] ?? '';

if (!$placa) {
  echo json_encode(['error' => 'Placa não informada']);
  exit;
}

// LOGIN PARA OBTER BEARER TOKEN
$loginUrl = "https://gateway.apibrasil.io/api/v2/auth/login";
$loginBody = json_encode([
  "email" => $email,
  "password" => $password
]);

$ch = curl_init($loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $loginBody);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  "Content-Type: application/json"
]);
$loginResponse = curl_exec($ch);
$loginCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($loginCode !== 200) {
  echo json_encode(["error" => "Erro ao autenticar: HTTP $loginCode"]);
  exit;
}

$loginData = json_decode($loginResponse, true);
$bearerToken = $loginData["token"] ?? null;

if (!$bearerToken) {
  echo json_encode(["error" => "Token de autenticação não retornado"]);
  exit;
}

// CONSULTA DE PLACA
$consultaUrl = "https://gateway.apibrasil.io/api/v2/vehicles/dados";
$consultaBody = json_encode([
  "placa" => $placa
]);

$ch = curl_init($consultaUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $consultaBody);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  "Content-Type: application/json",
  "Authorization: Bearer $bearerToken",
  "DeviceToken: $deviceToken"
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpCode);
file_put_contents("debug.log", $response);

echo $response;
