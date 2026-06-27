<?php
require_once __DIR__ . '/../../backend/banco/conexao.php';

$p    = TABLE_PREFIX;
$acao = trim($_GET['acao'] ?? '');

// ── PARSEAR arquivo ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acao === 'parsear') {
    if (empty($_FILES['arquivo']['tmp_name'])) {
        respostaJSON(['success' => false, 'erro' => 'Nenhum arquivo enviado.']);
    }

    if (($_FILES['arquivo']['size'] ?? 0) > 5 * 1024 * 1024) {
        respostaJSON(['success' => false, 'erro' => 'Arquivo muito grande. Máximo permitido: 5 MB.']);
    }

    $conteudo = file_get_contents($_FILES['arquivo']['tmp_name']);
    if ($conteudo === false) {
        respostaJSON(['success' => false, 'erro' => 'Erro ao ler arquivo.']);
    }

    $ext = strtolower(pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION));
    $transacoes = [];

    if (in_array($ext, ['ofx', 'ofc'])) {
        $transacoes = parsearOFX($conteudo);
    } elseif ($ext === 'csv' || $ext === 'txt') {
        $transacoes = parsearCSV($conteudo);
    } else {
        // Tenta detectar pelo conteúdo
        if (stripos($conteudo, '<OFX>') !== false || stripos($conteudo, 'OFXHEADER') !== false) {
            $transacoes = parsearOFX($conteudo);
        } else {
            $transacoes = parsearCSV($conteudo);
        }
    }

    respostaJSON(['success' => true, 'transacoes' => $transacoes, 'total' => count($transacoes)]);
}

// ── SALVAR transações importadas ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acao === 'salvar') {
    $d    = json_decode(file_get_contents('php://input'), true) ?? [];
    $lista = $d['transacoes'] ?? [];

    if (empty($lista)) {
        respostaJSON(['success' => false, 'erro' => 'Nenhuma transação para importar.']);
    }

    $importadas = 0;
    $stmt = $pdo->prepare(
        "INSERT INTO `{$p}transacoes`
         (tipo, descricao, valor, data, categoria_id, conta_id, status, observacao)
         VALUES (?,?,?,?,?,?,?,?)"
    );

    $pdo->beginTransaction();
    try {
        foreach ($lista as $tx) {
            $tipo     = in_array($tx['tipo'] ?? '', ['receita','despesa']) ? $tx['tipo'] : 'despesa';
            $descricao = substr(trim($tx['descricao'] ?? 'Importado'), 0, 200) ?: 'Importado';
            $valor    = round(abs((float)($tx['valor'] ?? 0)), 2);
            $data     = $tx['data'] ?? date('Y-m-d');
            $catId    = (int)($tx['categoria_id'] ?? 0) ?: null;
            $contaId  = (int)($tx['conta_id'] ?? 0) ?: null;
            $status   = in_array($tx['status'] ?? '', ['pago','pendente']) ? $tx['status'] : 'pago';

            if (!$valor) continue;

            $stmt->execute([$tipo, $descricao, $valor, $data, $catId, $contaId, $status, 'Importado via OFX/CSV']);

            // Atualiza saldo da conta se pago
            if ($contaId && $status === 'pago') {
                $sinal = $tipo === 'receita' ? '+' : '-';
                $pdo->prepare("UPDATE `{$p}contas` SET saldo_atual = saldo_atual {$sinal} ? WHERE id=?")
                    ->execute([$valor, $contaId]);
            }
            $importadas++;
        }
        $pdo->commit();
        respostaJSON(['success' => true, 'importadas' => $importadas]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        erroInterno($e);
    }
}

// ── Funções de parse ──────────────────────────────────────────
function parsearOFX(string $conteudo): array {
    $transacoes = [];

    // Tenta XML primeiro (OFX moderno)
    $conteudoXml = preg_replace('/^[^<]*/s', '', $conteudo);
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($conteudoXml);

    if ($xml !== false) {
        // Percorre buscando STMTTRN em qualquer nível
        $blocos = $xml->xpath('//STMTTRN');
        foreach ((array)$blocos as $bl) {
            $tx = extrairCamposOFX((array)$bl);
            if ($tx) $transacoes[] = $tx;
        }
    } else {
        // SGML (OFX legado) — parse por regex
        preg_match_all('/<STMTTRN>(.*?)(?=<STMTTRN>|<\/BANKTRANLIST>|$)/si', $conteudo, $m);
        foreach ($m[1] as $bloco) {
            $campos = [];
            foreach (['TRNTYPE','DTPOSTED','TRNAMT','MEMO','FITID'] as $tag) {
                if (preg_match("/<{$tag}>([^\r\n<]+)/i", $bloco, $mm)) {
                    $campos[$tag] = trim($mm[1]);
                }
            }
            $tx = extrairCamposOFX($campos);
            if ($tx) $transacoes[] = $tx;
        }
    }

    return $transacoes;
}

function extrairCamposOFX(array $campos): ?array {
    $dtRaw = (string)($campos['DTPOSTED'] ?? '');
    if (!preg_match('/(\d{4})(\d{2})(\d{2})/', $dtRaw, $dm)) return null;
    $data  = "{$dm[1]}-{$dm[2]}-{$dm[3]}";

    $valRaw = str_replace(',', '.', (string)($campos['TRNAMT'] ?? '0'));
    $valor  = abs((float)$valRaw);
    if ($valor <= 0) return null;

    $tipo = (float)$valRaw < 0 ? 'despesa' : 'receita';
    // CREDIT = receita, DEBIT = despesa
    $trntype = strtoupper((string)($campos['TRNTYPE'] ?? ''));
    if ($trntype === 'CREDIT') $tipo = 'receita';
    if ($trntype === 'DEBIT')  $tipo = 'despesa';

    $descricao = (string)($campos['MEMO'] ?? $campos['NAME'] ?? 'Sem descrição');
    $descricao = trim(preg_replace('/\s+/', ' ', $descricao));

    return [
        'data'       => $data,
        'data_fmt'   => date('d/m/Y', strtotime($data)),
        'valor'      => $valor,
        'tipo'       => $tipo,
        'descricao'  => $descricao,
        'fitid'      => (string)($campos['FITID'] ?? ''),
    ];
}

function parsearCSV(string $conteudo): array {
    $transacoes = [];
    $linhas     = preg_split('/\r\n|\r|\n/', trim($conteudo));
    if (count($linhas) < 2) return [];

    // Detecta separador
    $cabecalho = $linhas[0];
    $sep = substr_count($cabecalho, ';') > substr_count($cabecalho, ',') ? ';' : ',';

    $cols = str_getcsv($cabecalho, $sep);
    $cols = array_map(fn($c) => strtolower(trim(str_replace(['"', "'"], '', $c))), $cols);

    // Mapeamento flexível de colunas
    $mapData  = encontrarColuna($cols, ['data','date','dt','dt. lançamento','data lançamento']);
    $mapDesc  = encontrarColuna($cols, ['descrição','descricao','memo','histórico','historico','description']);
    $mapValor = encontrarColuna($cols, ['valor','value','amount','vlr']);
    $mapTipo  = encontrarColuna($cols, ['tipo','type','natureza']);

    if ($mapData === null || $mapValor === null) return [];

    for ($i = 1; $i < count($linhas); $i++) {
        $linha = str_getcsv($linhas[$i], $sep);
        if (count($linha) < 2) continue;

        $dataRaw  = trim($linha[$mapData] ?? '');
        $valorRaw = trim($linha[$mapValor] ?? '');
        $desc     = trim($linha[$mapDesc ?? 0] ?? 'Importado');
        $tipoRaw  = $mapTipo !== null ? strtolower(trim($linha[$mapTipo] ?? '')) : '';

        // Normaliza data BR (dd/mm/aaaa) ou ISO
        $data = null;
        if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $dataRaw, $dm)) {
            $data = "{$dm[3]}-{$dm[2]}-{$dm[1]}";
        } elseif (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $dataRaw, $dm)) {
            $data = $dataRaw;
        }
        if (!$data) continue;

        // Normaliza valor
        $valorRaw = preg_replace('/[^\d.,-]/', '', $valorRaw);
        $valorRaw = str_replace(',', '.', $valorRaw);
        $valor    = abs((float)$valorRaw);
        if ($valor <= 0) continue;

        $tipo = (float)$valorRaw < 0 ? 'despesa' : 'receita';
        if (str_contains($tipoRaw, 'créd') || str_contains($tipoRaw, 'recei')) $tipo = 'receita';
        if (str_contains($tipoRaw, 'déb')  || str_contains($tipoRaw, 'despe')) $tipo = 'despesa';

        $transacoes[] = [
            'data'       => $data,
            'data_fmt'   => date('d/m/Y', strtotime($data)),
            'valor'      => $valor,
            'tipo'       => $tipo,
            'descricao'  => $desc ?: 'Importado',
            'fitid'      => '',
        ];
    }

    return $transacoes;
}

function encontrarColuna(array $cols, array $candidatos): ?int {
    foreach ($candidatos as $c) {
        $idx = array_search($c, $cols);
        if ($idx !== false) return (int)$idx;
    }
    // Busca parcial
    foreach ($cols as $i => $col) {
        foreach ($candidatos as $c) {
            if (str_contains($col, $c) || str_contains($c, $col)) return $i;
        }
    }
    return null;
}

respostaJSON(['success' => false, 'erro' => 'Ação inválida.']);
