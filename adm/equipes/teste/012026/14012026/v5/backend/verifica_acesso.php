<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

/* =========================
   LOGIN EXISTE?
========================= */
if (
  empty($_SESSION['usuario_id']) ||
  empty($_SESSION['admweb'])
) {
  header('Location: /login.html');
  exit;
}

/* =========================
   PERMISSÃO WEB
========================= */
if ((int)$_SESSION['admweb'] !== 1) {
  http_response_code(403);
  exit('Acesso negado');
}

/* =========================
   TIMEOUT (1h)
========================= */
$timeout = 3600;

if (!isset($_SESSION['login_time'])) {
  session_destroy();
  header('Location: /login.html');
  exit;
}

if (time() - $_SESSION['login_time'] > $timeout) {
  session_destroy();
  header('Location: /login.html');
  exit;
}

$_SESSION['login_time'] = time();
