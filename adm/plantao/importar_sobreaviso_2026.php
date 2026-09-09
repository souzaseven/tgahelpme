<?php
/* =========================================================
   Importador (uso único) — Escala de SOBREAVISO
   Meses: Julho, Agosto e Setembro / 2026
   Base : suportes_plantao + plantoes_fim_semana

   COMO USAR
   1. Faça login no painel normalmente.
   2. Abra este arquivo pelo navegador:
        https://SEU-DOMINIO/importar_sobreaviso_2026.php
   3. Confira a prévia e clique em "Executar importação".
   4. APAGUE este arquivo do servidor depois de rodar.

   Segurança: exige sessão de admin (auth_guard) e token CSRF no POST.
   É idempotente — rodar de novo não duplica nada.
========================================================= */

require_once __DIR__ . '/auth_guard.php';      // redireciona para login.php se não for admin
require_once __DIR__ . '/backend/conexao.php'; // $pdo + bootstrap (.env, sessão, CSRF)

/* ---------------------------------------------------------
   ESCALA (fonte: coordenação — Julho/2026 na "versão A")
   "Flavio" normalizado para "Flávio".
--------------------------------------------------------- */
$COLABORADORES = ['Carlos', 'Diogo', 'Flávio', 'Rodrigo'];

$ESCALA = [
  // [sábado,       domingo,       colaborador, observação]
  ['2026-07-04', '2026-07-05', 'Diogo',   null],
  ['2026-07-11', '2026-07-12', 'Carlos',  null],
  ['2026-07-18', '2026-07-19', 'Flávio',  null],
  ['2026-07-25', '2026-07-26', 'Rodrigo', null],
  ['2026-08-01', '2026-08-02', 'Rodrigo', null],
  ['2026-08-08', '2026-08-09', 'Carlos',  null],
  ['2026-08-15', '2026-08-16', 'Diogo',   null],
  ['2026-08-22', '2026-08-23', 'Rodrigo', null],
  ['2026-08-29', '2026-08-30', 'Flávio',  null],
  ['2026-09-05', '2026-09-06', 'Diogo',   null],
  ['2026-09-12', '2026-09-13', 'Carlos',  null],
  ['2026-09-19', '2026-09-20', 'Flávio',  null],
  ['2026-09-26', '2026-09-27', 'Diogo',   null],
];

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$fmtBR = fn($ymd) => $ymd ? implode('/', array_reverse(explode('-', $ymd))) : '—';

$csrf = $_SESSION['csrf_token'] ?? '';
$isPost = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$csrfOk = $isPost && hash_equals($csrf, (string)($_POST['csrf'] ?? ''));

$resultado = null;
$erro = null;

if ($isPost) {
  if (!$csrfOk) {
    $erro = 'Token CSRF inválido. Recarregue a página e tente de novo.';
  } else {
    try {
      $pdo->beginTransaction();

      /* 1) Colaboradores — cria só se ainda não existir */
      $mapaId = [];
      $sel = $pdo->prepare("SELECT id FROM suportes_plantao WHERE nome = :n LIMIT 1");
      $ins = $pdo->prepare("INSERT INTO suportes_plantao (nome, ativo) VALUES (:n, 1)");
      $colabsCriados = [];

      foreach ($COLABORADORES as $nome) {
        $sel->execute([':n' => $nome]);
        $id = $sel->fetchColumn();
        if ($id === false) {
          $ins->execute([':n' => $nome]);
          $id = (int)$pdo->lastInsertId();
          $colabsCriados[] = $nome;
        }
        $mapaId[$nome] = (int)$id;
      }

      /* 2) Plantões — upsert por sábado (UNIQUE KEY uq_sabado) */
      $up = $pdo->prepare("
        INSERT INTO plantoes_fim_semana (sabado, domingo, suporte_id, suporte_nome, observacao)
        VALUES (:sab, :dom, :sid, :sname, :obs)
        ON DUPLICATE KEY UPDATE
          domingo      = VALUES(domingo),
          suporte_id   = VALUES(suporte_id),
          suporte_nome = VALUES(suporte_nome),
          observacao   = VALUES(observacao)
      ");

      $linhas = [];
      foreach ($ESCALA as [$sab, $dom, $nome, $obs]) {
        $up->execute([
          ':sab'   => $sab,
          ':dom'   => $dom,
          ':sid'   => $mapaId[$nome],
          ':sname' => $nome,
          ':obs'   => $obs,
        ]);
        // rowCount(): 1 = inseriu, 2 = atualizou, 0 = já estava igual
        $rc = $up->rowCount();
        $linhas[] = [
          'sabado' => $sab,
          'domingo' => $dom,
          'nome' => $nome,
          'acao' => $rc === 1 ? 'inserido' : ($rc === 2 ? 'atualizado' : 'sem mudança'),
        ];
      }

      $pdo->commit();

      $resultado = [
        'colabs_criados' => $colabsCriados,
        'linhas' => $linhas,
      ];
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      error_log('[importar_sobreaviso_2026] ' . $e->getMessage());
      $erro = 'Falha na importação: ' . $e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Importar Sobreaviso 2026</title>
  <style>
    :root { color-scheme: light dark; }
    body { font: 15px/1.5 system-ui, sans-serif; margin: 0; padding: 2rem 1rem; background: #f6f7f9; color: #1c1f24; }
    .box { max-width: 820px; margin: 0 auto; background: #fff; border: 1px solid #e2e5ea; border-radius: 12px; padding: 1.5rem; }
    h1 { font-size: 1.25rem; margin: 0 0 .25rem; }
    p.sub { margin: 0 0 1.25rem; color: #5b6270; }
    table { border-collapse: collapse; width: 100%; margin: 1rem 0; font-size: 14px; }
    th, td { border: 1px solid #e2e5ea; padding: .45rem .6rem; text-align: left; }
    th { background: #f0f2f5; }
    .btn { display: inline-block; border: 0; border-radius: 8px; padding: .7rem 1.2rem; font-size: 15px; font-weight: 600; cursor: pointer; background: #2563eb; color: #fff; }
    .btn.secondary { background: #e2e5ea; color: #1c1f24; text-decoration: none; }
    .alert { padding: .8rem 1rem; border-radius: 8px; margin: 1rem 0; }
    .alert.ok { background: #dcfce7; color: #14532d; }
    .alert.err { background: #fee2e2; color: #7f1d1d; }
    .alert.warn { background: #fef9c3; color: #713f12; }
    .tag { font-size: 12px; padding: .1rem .45rem; border-radius: 999px; background: #e2e5ea; }
    .tag.inserido { background: #dcfce7; color: #14532d; }
    .tag.atualizado { background: #dbeafe; color: #1e3a8a; }
    code { background: #f0f2f5; padding: .1rem .3rem; border-radius: 4px; }
  </style>
</head>
<body>
  <div class="box">
    <h1>Importar escala de Sobreaviso — Jul/Ago/Set 2026</h1>
    <p class="sub">Grava em <code>suportes_plantao</code> e <code>plantoes_fim_semana</code>. Idempotente: pode rodar de novo sem duplicar.</p>

    <?php if ($erro): ?>
      <div class="alert err"><?= $h($erro) ?></div>
    <?php endif; ?>

    <?php if ($resultado): ?>
      <div class="alert ok">Importação concluída.</div>

      <?php if ($resultado['colabs_criados']): ?>
        <p>Colaboradores criados: <strong><?= $h(implode(', ', $resultado['colabs_criados'])) ?></strong></p>
      <?php else: ?>
        <p>Nenhum colaborador novo (todos já existiam).</p>
      <?php endif; ?>

      <table>
        <thead><tr><th>Sábado</th><th>Domingo</th><th>Sobreaviso</th><th>Resultado</th></tr></thead>
        <tbody>
          <?php foreach ($resultado['linhas'] as $l): ?>
            <tr>
              <td><?= $h($fmtBR($l['sabado'])) ?></td>
              <td><?= $h($fmtBR($l['domingo'])) ?></td>
              <td><?= $h($l['nome']) ?></td>
              <td><span class="tag <?= $h($l['acao']) ?>"><?= $h($l['acao']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="alert warn"><strong>Agora apague este arquivo</strong> (<code>importar_sobreaviso_2026.php</code>) do servidor.</div>
      <p><a class="btn secondary" href="index.php">Voltar ao painel</a></p>

    <?php else: ?>
      <div class="alert warn">
        Um sábado que já exista no banco será <strong>sobrescrito</strong> com os dados abaixo.
        Julho/2026 usa a <strong>versão A</strong> (04 Diogo · 11 Carlos · 18 Flávio · 25 Rodrigo).
      </div>

      <table>
        <thead><tr><th>Sábado</th><th>Domingo</th><th>Sobreaviso</th></tr></thead>
        <tbody>
          <?php foreach ($ESCALA as [$sab, $dom, $nome]): ?>
            <tr>
              <td><?= $h($fmtBR($sab)) ?></td>
              <td><?= $h($fmtBR($dom)) ?></td>
              <td><?= $h($nome) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <button class="btn" type="submit">Executar importação</button>
        <a class="btn secondary" href="index.php">Cancelar</a>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
