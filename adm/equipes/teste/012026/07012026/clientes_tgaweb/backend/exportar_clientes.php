<?php
require __DIR__ . "/conexao.php";

/*
 * EXPORTAÇÃO CSV - EXCEL WINDOWS (ANSI / CP1252)
 */

header("Content-Type: text/csv; charset=windows-1252");
header("Content-Disposition: attachment; filename=clientes_tga_web.csv");
header("Pragma: no-cache");
header("Expires: 0");

// Limpa qualquer saída anterior
if (ob_get_length()) {
  ob_clean();
}

$out = fopen("php://output", "w");

/**
 * Converte UTF-8 → Windows-1252
 */
function enc($str) {
  return iconv("UTF-8", "Windows-1252//TRANSLIT", $str);
}

// Cabeçalho (SEM BOM, já em ANSI)
fputcsv($out, [
  enc("Código TGA"),
  enc("Empresa"),
  enc("CNPJ"),
  enc("Versão"),
  enc("Firebird"),
  enc("Informação"),
  enc("Qtd Usuários"),
  enc("Senha")
], ";");

// Consulta
$sql = "SELECT
          codigotga,
          nome_empresa,
          cnpj,
          versao,
          firebird,
          info_adicional,
          qntusuarios,
          senhapadrao
        FROM clientes_tga_web
        ORDER BY nome_empresa";

$stmt = $pdo->query($sql);

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  fputcsv($out, [
    enc($r['codigotga']),
    enc($r['nome_empresa']),
    enc($r['cnpj']),
    enc($r['versao']),
    enc($r['firebird']),
    enc($r['info_adicional']),
    enc((string)$r['qntusuarios']),
    enc($r['senhapadrao'])
  ], ";");
}

fclose($out);
exit;
