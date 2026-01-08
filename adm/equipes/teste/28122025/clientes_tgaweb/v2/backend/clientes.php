<?php
// backend/clientes.php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_login(); // respeita DEV_SEM_LOGIN

// $pdo vem do conexao.php
$method = $_SERVER["REQUEST_METHOD"] ?? "GET";

function clean_str($v): string {
    return trim((string)$v);
}

/* ============================================================
   GET — LISTAGEM + FILTROS + PAGINAÇÃO (SEM ERRO 500)
============================================================ */
if ($method === "GET") {

    $q        = clean_str($_GET["q"] ?? "");
    $versao   = clean_str($_GET["versao"] ?? "");
    $firebird = clean_str($_GET["firebird"] ?? "");

    $page    = max(1, (int)($_GET["page"] ?? 1));
    $perPage = min(100, max(5, (int)($_GET["perPage"] ?? 20)));
    $offset  = ($page - 1) * $perPage;

    $where  = [];
    $params = [];

    if ($q !== "") {
        $where[] = "(codigotga LIKE :q OR nome_empresa LIKE :q OR cnpj LIKE :q OR info_adicional LIKE :q)";
        $params[":q"] = "%{$q}%";
    }
    if ($versao !== "") {
        $where[] = "versao LIKE :versao";
        $params[":versao"] = "%{$versao}%";
    }
    if ($firebird !== "") {
        $where[] = "firebird LIKE :firebird";
        $params[":firebird"] = "%{$firebird}%";
    }

    $sqlWhere = $where ? "WHERE " . implode(" AND ", $where) : "";

    // TOTAL
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) total FROM clientes_tga_web {$sqlWhere}");
    $stmtTotal->execute($params);
    $total = (int)($stmtTotal->fetch()["total"] ?? 0);

    // 🔥 LIMIT/OFFSET SEM BIND (CORREÇÃO DO ERRO 500)
    $limit  = (int)$perPage;
    $offset = (int)$offset;

    $stmt = $pdo->prepare("
        SELECT
            id, codigotga, nome_empresa, cnpj,
            versao, firebird, info_adicional,
            qntusuarios, senhapadrao,
            created_at, updated_at
        FROM clientes_tga_web
        {$sqlWhere}
        ORDER BY updated_at DESC, id DESC
        LIMIT {$limit} OFFSET {$offset}
    ");

    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }

    $stmt->execute();

    respostaJSON([
        "success" => true,
        "page"    => $page,
        "perPage" => $perPage,
        "total"   => $total,
        "items"   => $stmt->fetchAll()
    ]);
}

/* ============================================================
   POST — CRIAR
============================================================ */
if ($method === "POST") {

    require_csrf();

    $body = json_decode(file_get_contents("php://input"), true);
    if (!is_array($body)) {
        respostaJSON(["success" => false, "error" => "JSON inválido."], 400);
    }

    $codigotga      = clean_str($body["codigotga"] ?? "");
    $nome_empresa   = clean_str($body["nome_empresa"] ?? "");
    $cnpj           = clean_str($body["cnpj"] ?? "");
    $versao         = clean_str($body["versao"] ?? "");
    $firebird       = clean_str($body["firebird"] ?? "");
    $info_adicional = clean_str($body["info_adicional"] ?? "");
    $qntusuarios    = (int)($body["qntusuarios"] ?? 0);
    $senhapadrao    = clean_str($body["senhapadrao"] ?? "");

    if (!$codigotga || !$nome_empresa || !$cnpj) {
        respostaJSON([
            "success" => false,
            "error"   => "Código TGA, Empresa e CNPJ são obrigatórios."
        ], 422);
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO clientes_tga_web
            (codigotga, nome_empresa, cnpj, versao, firebird, info_adicional, qntusuarios, senhapadrao)
            VALUES
            (:codigotga, :nome_empresa, :cnpj, :versao, :firebird, :info_adicional, :qntusuarios, :senhapadrao)
        ");

        $stmt->execute([
            ":codigotga"      => $codigotga,
            ":nome_empresa"   => $nome_empresa,
            ":cnpj"           => $cnpj,
            ":versao"         => $versao ?: null,
            ":firebird"       => $firebird ?: null,
            ":info_adicional" => $info_adicional ?: null,
            ":qntusuarios"    => $qntusuarios,
            ":senhapadrao"    => $senhapadrao ?: null,
        ]);

        respostaJSON([
            "success" => true,
            "id"      => (int)$pdo->lastInsertId()
        ]);
    } catch (Throwable $e) {
        respostaJSON([
            "success" => false,
            "error"   => "Erro ao inserir (Código TGA duplicado?)"
        ], 400);
    }
}

/* ============================================================
   PUT / DELETE — permanecem IGUAIS (já funcionando)
============================================================ */

respostaJSON([
    "success" => false,
    "error"   => "Método não suportado."
], 405);
