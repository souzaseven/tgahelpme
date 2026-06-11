<?php
require_once __DIR__ . '/conexao.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success'=>false,'message'=>'Método não permitido']);
  exit;
}

if (!validateCSRF()) {
  http_response_code(403);
  echo json_encode(['success'=>false,'message'=>'CSRF inválido']);
  exit;
}

/* ── Cria tabela se não existir ── */
$pdo->exec("
  CREATE TABLE IF NOT EXISTS clientes_web_servidores (
    id            INT          NOT NULL AUTO_INCREMENT,
    titulo        VARCHAR(150) NOT NULL,
    icone         VARCHAR(20)  NOT NULL DEFAULT '🖥️',
    descricao     VARCHAR(300) NOT NULL DEFAULT '',
    itens         JSON         NOT NULL,
    links         JSON         NOT NULL,
    layout        ENUM('list','grid') NOT NULL DEFAULT 'list',
    full_width    TINYINT(1)   NOT NULL DEFAULT 0,
    ordem         INT          NOT NULL DEFAULT 0,
    criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* ── Migração: adiciona full_width a tabelas já existentes ── */
try {
  $pdo->exec("ALTER TABLE clientes_web_servidores ADD COLUMN full_width TINYINT(1) NOT NULL DEFAULT 0");
  /* Na primeira migração, marca o Smart Client como largura completa automaticamente */
  $pdo->exec("UPDATE clientes_web_servidores SET full_width = 1 WHERE titulo LIKE '%Smart Client%'");
} catch (Exception $e) { /* coluna já existe — ignora */ }

/* ── Seed inicial (só na primeira vez que a tabela estiver vazia) ── */
if ((int)$pdo->query("SELECT COUNT(*) FROM clientes_web_servidores")->fetchColumn() === 0) {
  $tsplus = [];
  for ($i = 1; $i <= 9; $i++) {
    $tsplus[] = ['nome' => 'SRVTSPLUS' . str_pad($i, 2, '0', STR_PAD_LEFT), 'tag' => 'QUALLIT', 'desc' => ''];
  }
  $fv = [];
  for ($i = 1; $i <= 22; $i++) {
    $fv[] = [
      'nome' => 'SRVTGAFV' . str_pad($i, 2, '0', STR_PAD_LEFT),
      'tag'  => 'QUALLIT',
      'desc' => $i === 1 ? 'Comercial e Desenvolvimento' : ''
    ];
  }

  $seed = [
    [
      'titulo'    => 'Servidor Principal',
      'icone'     => '🖥️',
      'descricao' => 'Domínio / Infraestrutura base',
      'layout'    => 'list',
      'itens'     => [
        ['nome'=>'SRVAD','tag'=>'QUALLIT','desc'=>'Servidor principal do domínio — adicione 172.16.1.23 sweb.tgasistemas.com.br no arquivo hosts (C:\\Windows\\System32\\drivers\\etc\\hosts)']
      ],
      'links'     => [
        ['label'=>'🔐 Baixar VPN','url'=>'http://tgameajuda.com/adm/cliente_web/assets/files/openvpn-pfSense-UDP4-1111-install-2.6.7-I001-amd64.rar','download'=>true],
        ['label'=>'🖥️ Host VPN', 'url'=>'http://tgameajuda.com/adm/cliente_web/assets/files/hosts.rar','download'=>true]
      ],
      'ordem' => 1
    ],
    [
      'titulo'    => 'Servidor Revenda',
      'icone'     => '🗄️',
      'descricao' => 'Banco de dados Firebird',
      'layout'    => 'list',
      'itens'     => [['nome'=>'SRVFIREBIRD-REVRO','tag'=>'QUALLIT','desc'=>'Banco de dados Firebird para revendas']],
      'links'     => [],
      'ordem'     => 2
    ],
    [
      'titulo'    => 'Gateway',
      'icone'     => '🌐',
      'descricao' => 'Gateway – Liberação de IP para acesso à Farm TSPlus',
      'layout'    => 'list',
      'itens'     => [['nome'=>'SRVTSPLUSGW01','tag'=>'QUALLIT','desc'=>'Gateway – Liberação de IP']],
      'links'     => [],
      'ordem'     => 3
    ],
    [
      'titulo'    => 'Informações',
      'icone'     => '📌',
      'descricao' => 'Orientações e contatos rápidos para acesso aos servidores',
      'layout'    => 'list',
      'itens'     => [
        ['nome'=>'Conexão VPN','tag'=>'','desc'=>'Use a VPN para acessar os servidores internos quando estiver fora da rede QUALLIT.'],
        ['nome'=>'Hosts',      'tag'=>'','desc'=>'Edite o arquivo hosts local apenas quando necessário e siga as instruções de acesso.'],
        ['nome'=>'Suporte',    'tag'=>'','desc'=>'Abra ticket para alterações de firewall ou se o acesso não funcionar.']
      ],
      'links'     => [],
      'ordem'     => 4
    ],
    [
      'titulo'    => 'Farm TSPlus – QUALLIT',
      'icone'     => '🧩',
      'descricao' => 'Servidores de Terminal Service (acesso remoto)',
      'layout'    => 'grid',
      'itens'     => $tsplus,
      'links'     => [],
      'ordem'     => 5
    ],
    [
      'titulo'     => 'Força de Vendas – Smart Client',
      'icone'      => '📱',
      'descricao'  => 'SRVTGAFV01 até SRVTGAFV22',
      'layout'     => 'grid',
      'full_width' => 1,
      'itens'      => $fv,
      'links'      => [],
      'ordem'      => 6
    ],
    [
      'titulo'    => 'API – Força de Vendas',
      'icone'     => '⚙️',
      'descricao' => 'Servidores dedicados à API do sistema Força de Vendas',
      'layout'    => 'list',
      'itens'     => [
        ['nome'=>'SRVTGAFV23',    'tag'=>'QUALLIT','desc'=>''],
        ['nome'=>'SRVTGAFVAPI01', 'tag'=>'QUALLIT','desc'=>''],
        ['nome'=>'SRVTGAFVAPI02', 'tag'=>'QUALLIT','desc'=>''],
        ['nome'=>'SRVTGAFVAPI03', 'tag'=>'QUALLIT','desc'=>''],
        ['nome'=>'SRVTGAFVAPI04', 'tag'=>'QUALLIT','desc'=>'']
      ],
      'links'     => [],
      'ordem'     => 7
    ]
  ];

  $ins = $pdo->prepare("INSERT INTO clientes_web_servidores (titulo,icone,descricao,itens,links,layout,full_width,ordem) VALUES (?,?,?,?,?,?,?,?)");
  foreach ($seed as $s) {
    $ins->execute([
      $s['titulo'],
      $s['icone'],
      $s['descricao'],
      json_encode($s['itens'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      json_encode($s['links'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      $s['layout'],
      (int)($s['full_width'] ?? 0),
      $s['ordem']
    ]);
  }
}

/* ── Helpers ── */
$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $body['action'] ?? '';

function normSrv($v) { return trim((string)$v); }

function decodeSrv(array &$row): void {
  $row['itens'] = json_decode($row['itens'] ?? '[]', true) ?: [];
  $row['links'] = json_decode($row['links'] ?? '[]', true) ?: [];
}

function encodeSrvData(array $d): array {
  $titulo     = normSrv($d['titulo'] ?? '');
  if ($titulo === '') throw new Exception('Título obrigatório');
  $icone      = normSrv($d['icone'] ?? '') ?: '🖥️';
  $desc       = normSrv($d['descricao'] ?? '');
  $layout     = in_array($d['layout'] ?? '', ['list','grid']) ? $d['layout'] : 'list';
  $full_width = ($d['full_width'] ?? false) ? 1 : 0;
  $itens      = json_encode(is_array($d['itens'] ?? null) ? $d['itens'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  $links      = json_encode(is_array($d['links'] ?? null) ? $d['links'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  return compact('titulo','icone','desc','layout','full_width','itens','links');
}

try {

  /* ── list ── */
  if ($action === 'list') {
    $rows = $pdo->query("SELECT * FROM clientes_web_servidores ORDER BY ordem ASC, id ASC")->fetchAll();
    foreach ($rows as &$r) decodeSrv($r);
    unset($r);
    echo json_encode(['success'=>true, 'rows'=>$rows]);
    exit;
  }

  /* ── get ── */
  if ($action === 'get') {
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) throw new Exception('ID inválido');
    $stmt = $pdo->prepare("SELECT * FROM clientes_web_servidores WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) throw new Exception('Registro não encontrado');
    decodeSrv($row);
    echo json_encode(['success'=>true, 'row'=>$row]);
    exit;
  }

  /* ── create ── */
  if ($action === 'create') {
    $e = encodeSrvData($body['data'] ?? []);
    $maxOrd = (int)$pdo->query("SELECT COALESCE(MAX(ordem),0) FROM clientes_web_servidores")->fetchColumn();
    $pdo->prepare("INSERT INTO clientes_web_servidores (titulo,icone,descricao,itens,links,layout,full_width,ordem) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$e['titulo'],$e['icone'],$e['desc'],$e['itens'],$e['links'],$e['layout'],$e['full_width'],$maxOrd+1]);
    $newId = $pdo->lastInsertId();
    logAction($pdo,'CREATE','Servidores',$newId,null,['titulo'=>$e['titulo']],"Criou card \"{$e['titulo']}\"");
    echo json_encode(['success'=>true, 'id'=>$newId]);
    exit;
  }

  /* ── update ── */
  if ($action === 'update') {
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) throw new Exception('ID inválido');
    $e = encodeSrvData($body['data'] ?? []);

    $stmtA = $pdo->prepare("SELECT titulo,icone,descricao,layout FROM clientes_web_servidores WHERE id=? LIMIT 1");
    $stmtA->execute([$id]);
    $antes = $stmtA->fetch() ?: null;

    $pdo->prepare("UPDATE clientes_web_servidores SET titulo=?,icone=?,descricao=?,itens=?,links=?,layout=?,full_width=?,atualizado_em=NOW() WHERE id=? LIMIT 1")
        ->execute([$e['titulo'],$e['icone'],$e['desc'],$e['itens'],$e['links'],$e['layout'],$e['full_width'],$id]);

    $depois = ['titulo'=>$e['titulo'],'icone'=>$e['icone'],'descricao'=>$e['desc'],'layout'=>$e['layout'],'full_width'=>$e['full_width']];
    $resumo = $antes ? resumoAlteracoes($antes,$depois) : '';
    logAction($pdo,'UPDATE','Servidores',$id,$antes,$depois,"Atualizou card \"{$e['titulo']}\"".($resumo?" | {$resumo}":''));
    echo json_encode(['success'=>true]);
    exit;
  }

  /* ── delete ── */
  if ($action === 'delete') {
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) throw new Exception('ID inválido');
    $stmtA = $pdo->prepare("SELECT titulo FROM clientes_web_servidores WHERE id=? LIMIT 1");
    $stmtA->execute([$id]);
    $antes = $stmtA->fetch() ?: null;
    $nome  = $antes['titulo'] ?? "ID {$id}";
    $pdo->prepare("DELETE FROM clientes_web_servidores WHERE id=? LIMIT 1")->execute([$id]);
    logAction($pdo,'DELETE','Servidores',$id,$antes,null,"Excluiu card \"{$nome}\"");
    echo json_encode(['success'=>true]);
    exit;
  }

  /* ── reorder ── */
  if ($action === 'reorder') {
    $ids = $body['ids'] ?? [];
    if (!is_array($ids) || empty($ids)) throw new Exception('Lista de IDs inválida');
    $stmt = $pdo->prepare("UPDATE clientes_web_servidores SET ordem=? WHERE id=?");
    foreach ($ids as $i => $id) {
      $stmt->execute([$i + 1, (int)$id]);
    }
    echo json_encode(['success'=>true]);
    exit;
  }

  throw new Exception('Ação inválida');

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
