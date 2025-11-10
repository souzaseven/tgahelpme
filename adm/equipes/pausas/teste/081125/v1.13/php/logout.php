<?php
// =============================================================
// logout.php - Encerrar sess«ªo de forma segura
// =============================================================
session_start();
session_unset();
session_destroy();

echo json_encode(['success' => true, 'msg' => 'Sess«ªo encerrada com sucesso.']);
?>
