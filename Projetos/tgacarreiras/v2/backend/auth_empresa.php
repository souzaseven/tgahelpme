<?php
session_start();
require_once __DIR__ . '/conexao.php';

// ── Só aceita POST ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../empresa/login.php");
    exit;
}

$acao = $_POST['acao'] ?? '';

// ══════════════════════════════════════════════════════════
//  CADASTRO
// ══════════════════════════════════════════════════════════
if ($acao === 'cadastro') {

    // ── Capturar e sanitizar campos ─────────────────────
    $razao_social  = trim($_POST['razao_social']  ?? '');
    $nome_fantasia = trim($_POST['nome_fantasia']  ?? '');
    $cnpj          = preg_replace('/\D/', '', trim($_POST['cnpj'] ?? ''));
    $setor         = trim($_POST['setor']          ?? '');
    $porte         = trim($_POST['porte']          ?? '');
    $responsavel   = trim($_POST['responsavel']    ?? '');
    $email         = trim($_POST['email']          ?? '');
    $telefone      = trim($_POST['telefone']       ?? '');
    $website       = trim($_POST['website']        ?? '');
    $estado        = trim($_POST['estado']         ?? '');
    $cidade        = trim($_POST['cidade']         ?? '');
    $senha         = $_POST['senha'] ?? '';

    // ── Validações obrigatórias ─────────────────────────
    if (empty($razao_social) || empty($nome_fantasia) || empty($cnpj) ||
        empty($responsavel)  || empty($email) || empty($telefone) ||
        empty($senha)        || empty($setor) || empty($porte)) {

        header("Location: ../empresa/cadastro.php?erro=" . urlencode("Preencha todos os campos obrigatórios."));
        exit;
    }

    // Validar CNPJ (14 dígitos)
    if (strlen($cnpj) !== 14) {
        header("Location: ../empresa/cadastro.php?erro=" . urlencode("CNPJ inválido."));
        exit;
    }

    // Validar e-mail
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../empresa/cadastro.php?erro=" . urlencode("E-mail inválido."));
        exit;
    }

    // Validar senha (mín. 6 caracteres)
    if (strlen($senha) < 6) {
        header("Location: ../empresa/cadastro.php?erro=" . urlencode("A senha deve ter no mínimo 6 caracteres."));
        exit;
    }

    try {
        // ── Verificar se CNPJ já existe ─────────────────
        $stmt = $pdo->prepare("SELECT id FROM empresas_carreiras WHERE cnpj = ? LIMIT 1");
        $stmt->execute([$cnpj]);

        if ($stmt->fetch()) {
            header("Location: ../empresa/cadastro.php?erro=" . urlencode("Este CNPJ já está cadastrado."));
            exit;
        }

        // ── Verificar se e-mail já existe ───────────────
        $stmt = $pdo->prepare("SELECT id FROM empresas_carreiras WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            header("Location: ../empresa/cadastro.php?erro=" . urlencode("Este e-mail já está cadastrado."));
            exit;
        }

        // ── Hash da senha ───────────────────────────────
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

        // ── Inserir no banco ────────────────────────────
        $stmt = $pdo->prepare("
            INSERT INTO empresas_carreiras 
                (nome_empresa, razao_social, cnpj, setor, porte, responsavel, 
                 email, telefone, site, estado, cidade, senha, status, data_cadastro)
            VALUES 
                (?, ?, ?, ?, ?, ?, 
                 ?, ?, ?, ?, ?, ?, 'ativa', NOW())
        ");

        $stmt->execute([
            $nome_fantasia,   // nome_empresa
            $razao_social,    // razao_social
            $cnpj,            // cnpj
            $setor,           // setor
            $porte,           // porte
            $responsavel,     // responsavel
            $email,           // email
            $telefone,        // telefone
            $website,         // site
            $estado,          // estado
            $cidade,          // cidade
            $senhaHash        // senha
        ]);

        // ── Redirecionar para login com sucesso ─────────
        header("Location: ../empresa/login.php?success=1");
        exit;

    } catch (PDOException $e) {
        error_log('[auth_empresa][cadastro] Erro: ' . $e->getMessage());
        header("Location: ../empresa/cadastro.php?erro=" . urlencode("Erro interno ao cadastrar. Tente novamente."));
        exit;
    }
}

// ══════════════════════════════════════════════════════════
//  LOGIN
// ══════════════════════════════════════════════════════════
if ($acao === 'login') {

    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    if (empty($email) || empty($senha)) {
        header("Location: ../empresa/login.php?erro=" . urlencode("Preencha todos os campos."));
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, nome_empresa, senha
            FROM empresas_carreiras
            WHERE email = ?
            AND status = 'ativa'
            LIMIT 1
        ");

        $stmt->execute([$email]);
        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($empresa && password_verify($senha, $empresa['senha'])) {
            session_regenerate_id(true);
            $_SESSION['empresa_id']   = $empresa['id'];
            $_SESSION['empresa_nome'] = $empresa['nome_empresa'];

            header("Location: ../empresa/dashboard.php");
            exit;
        }

        header("Location: ../empresa/login.php?erro=" . urlencode("E-mail ou senha incorretos."));
        exit;

    } catch (PDOException $e) {
        error_log('[auth_empresa][login] Erro: ' . $e->getMessage());
        header("Location: ../empresa/login.php?erro=" . urlencode("Erro interno. Tente novamente."));
        exit;
    }
}

// ── Ação inválida ───────────────────────────────────────
header("Location: ../empresa/login.php");
exit;