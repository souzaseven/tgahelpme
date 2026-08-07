<?php
// ============================================================
// auth.php — Login/logout por senha única (Controle Financeiro v1)
// Este endpoint fica fora do gate de autenticação (ver conexao.php)
// porque é a própria porta de entrada.
//
// GET  ?acao=status  — sessão atual está autenticada?
// POST acao=login    — { senha }
// POST acao=logout
// ============================================================
require_once __DIR__ . '/../banco/conexao.php';

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    if (trim($_GET['acao'] ?? '') === 'status') {
        respostaJSON(['success' => true, 'autenticado' => !empty($_SESSION['autenticado'])]);
    }
    respostaJSON(['success' => false, 'erro' => 'Ação não reconhecida.']);
}

if ($metodo === 'POST') {
    $d    = json_decode(file_get_contents('php://input'), true) ?? [];
    $acao = trim($d['acao'] ?? '');

    if ($acao === 'logout') {
        $_SESSION = [];
        session_destroy();
        respostaJSON(['success' => true]);
    }

    if ($acao === 'login') {
        $maxTentativas = 5;
        $bloqueioSeg    = 300; // 5 minutos

        $bloqueadoAte = (int)($_SESSION['login_bloqueado_ate'] ?? 0);
        if ($bloqueadoAte > time()) {
            respostaJSON([
                'success' => false,
                'erro'    => 'Muitas tentativas. Tente novamente em ' . (int)ceil(($bloqueadoAte - time()) / 60) . ' min.',
            ]);
        }

        $senha = (string)($d['senha'] ?? '');
        $hash  = APP_PASSWORD_HASH;

        if ($hash === '' || !password_verify($senha, $hash)) {
            $_SESSION['login_tentativas'] = (int)($_SESSION['login_tentativas'] ?? 0) + 1;
            if ($_SESSION['login_tentativas'] >= $maxTentativas) {
                $_SESSION['login_bloqueado_ate'] = time() + $bloqueioSeg;
                $_SESSION['login_tentativas']    = 0;
            }
            respostaJSON(['success' => false, 'erro' => 'Senha incorreta.']);
        }

        // Login OK — renova o ID da sessão (evita fixação de sessão)
        session_regenerate_id(true);
        $_SESSION['autenticado']         = true;
        $_SESSION['login_tentativas']    = 0;
        $_SESSION['login_bloqueado_ate'] = 0;
        respostaJSON(['success' => true]);
    }

    respostaJSON(['success' => false, 'erro' => 'Ação não reconhecida.']);
}

respostaJSON(['success' => false, 'erro' => 'Método não suportado.']);
