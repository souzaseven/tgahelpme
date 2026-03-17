<?php
// /backend/auth_admin.php
session_start();
require_once __DIR__ . '/conexao.php';

function redirectLogin(string $msg, string $tipo = 'error'): void {
    $msg  = urlencode($msg);
    $tipo = urlencode($tipo);
    header("Location: ../adm/login.php?msg={$msg}&tipo={$tipo}");
    exit;
}

function tableExists(PDO $pdo, string $table): bool {
    try {
        // CORREÇÃO: Usando quote() para evitar problemas com prepared statements
        $table = str_replace('`', '', $table); // Remove backticks se houver
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erro ao verificar tabela: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectLogin("Método inválido.");
}

$acao = $_POST['acao'] ?? '';
if ($acao !== 'login_admin') {
    redirectLogin("Ação inválida.");
}

$email = trim((string)($_POST['email'] ?? ''));
$senha = (string)($_POST['senha'] ?? '');

if ($email === '' || $senha === '') {
    redirectLogin("Informe email e senha.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirectLogin("Email inválido.");
}

try {
    $user = null;

    // ======================================================
    // VERIFICA TABELA usuarios_carreiras
    // Campos esperados: id, nome, email, senha, tipo, status
    // tipo: admin, user, etc
    // status: ativo, inativo, pendente
    // ======================================================
    if (tableExists($pdo, 'usuarios_carreiras')) {
        // Primeiro, vamos verificar a estrutura da tabela para saber quais campos existem
        $stmt = $pdo->query("DESCRIBE usuarios_carreiras");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Mapeamento de campos possíveis
        $fieldMap = [
            'id' => in_array('id', $columns) ? 'id' : (in_array('usuario_id', $columns) ? 'usuario_id' : null),
            'nome' => in_array('nome', $columns) ? 'nome' : (in_array('name', $columns) ? 'name' : 'nome'),
            'email' => in_array('email', $columns) ? 'email' : null,
            'senha' => in_array('senha', $columns) ? 'senha' : (in_array('password', $columns) ? 'password' : (in_array('senha_hash', $columns) ? 'senha_hash' : null)),
            'tipo' => in_array('tipo', $columns) ? 'tipo' : (in_array('permissao', $columns) ? 'permissao' : (in_array('role', $columns) ? 'role' : null)),
            'status' => in_array('status', $columns) ? 'status' : (in_array('ativo', $columns) ? 'ativo' : null)
        ];
        
        // Verifica se os campos obrigatórios existem
        if (!$fieldMap['email'] || !$fieldMap['senha']) {
            redirectLogin("Estrutura da tabela não compatível.");
        }
        
        // Monta a query dinamicamente
        $selectFields = [];
        foreach ($fieldMap as $key => $field) {
            if ($field) {
                $selectFields[] = "`$field` as `$key`";
            }
        }
        
        $sql = "SELECT " . implode(', ', $selectFields) . " 
                FROM usuarios_carreiras 
                WHERE `{$fieldMap['email']}` = :email 
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            redirectLogin("Usuário não encontrado.");
        }

        // Verifica status se o campo existir
        if (isset($user['status'])) {
            $status = strtolower(trim($user['status']));
            if ($status !== 'ativo' && $status !== 'active' && $status !== '1' && $status !== 1) {
                redirectLogin("Usuário inativo ou pendente.");
            }
        }

        // Verifica senha
       if (empty($user['senha']) || $senha !== $user['senha']) {
            redirectLogin("Senha inválida.");
        }

        // Verifica se é admin
        $tipo = strtolower(trim((string)($user['tipo'] ?? '')));
        $isAdmin = in_array($tipo, ['admin', 'administrador', 'master', 'root'], true);
        
        // Também verifica se há campo específico de admin
        if (!$isAdmin && in_array('admin', $columns)) {
            // Verifica se existe campo booleano 'admin'
            $stmt = $pdo->prepare("SELECT admin FROM usuarios_carreiras WHERE `{$fieldMap['email']}` = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $adminFlag = $stmt->fetchColumn();
            $isAdmin = ($adminFlag == 1);
        }

        if (!$isAdmin) {
            redirectLogin("Sem permissão para o painel ADM.");
        }

        // Define se é desenvolvedor (nível superior)
        $isDev = in_array($tipo, ['dev', 'desenvolvedor', 'root', 'master'], true);

        // Garante que temos um nome
        if (empty($user['nome'])) {
            $user['nome'] = explode('@', $email)[0];
        }
    }

    if (!$user) {
        redirectLogin("Tabela de usuários não encontrada.");
    }

    // ======================================================
    // ✅ SESSÃO PADRÃO DO SEU ADMIN
    // ======================================================
    session_regenerate_id(true);

    $_SESSION['admin_id']    = (int)$user['id'];
    $_SESSION['admin_nome']  = (string)$user['nome'];
    $_SESSION['admin_email'] = (string)$user['email'];

    $_SESSION['admin']   = 1;
    $_SESSION['adm_dev'] = !empty($isDev) ? 1 : 0;

    $_SESSION['login_time'] = time();

    header("Location: ../adm/index.php?msg=" . urlencode("Login realizado com sucesso!") . "&tipo=success");
    exit;

} catch (Throwable $e) {
    // Log do erro para debug
    error_log("Erro no login: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    redirectLogin("Erro interno no login: " . $e->getMessage());
}
?>