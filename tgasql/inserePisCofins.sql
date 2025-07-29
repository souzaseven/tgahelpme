TROCA DE REGIME - 
 
-- Bloco de inserção de tributos PIS e COFINS para produtos com CSTPIS '01' ou '05'
-- que ainda não possuem esses tributos registrados na tabela TTRBPRD para a empresa '1'
 
-- Insere o tributo PIS com alíquota de 1.65%
INSERT INTO ttrbprd (CODEMPRESA, CODTRB, ALIQUOTA, CODPRD)
  SELECT CODEMPRESA, 'PIS', 1.65, CODPRD 
  FROM TPRODUTO
  WHERE TPRODUTO.CSTPIS IN ('01','05')          -- Apenas produtos com CSTPIS 01 ou 05
    AND CODEMPRESA = '1'                        -- Da empresa de código 1
    AND TPRODUTO.CODPRD NOT IN (                -- Que ainda não possuem PIS cadastrado
      SELECT T.CODPRD 
      FROM TTRBPRD T
      WHERE T.CODPRD = TPRODUTO.CODPRD 
        AND T.CODTRB = 'PIS'
    );
 
-- Insere o tributo COFINS com alíquota de 7.60%
INSERT INTO ttrbprd (CODEMPRESA, CODTRB, ALIQUOTA, CODPRD)
  SELECT CODEMPRESA, 'COFINS', 7.60, CODPRD 
  FROM TPRODUTO
  WHERE TPRODUTO.CSTPIS IN ('01','05')          -- Utiliza CSTPIS também como referência para COFINS
    AND CODEMPRESA = '1'
    AND TPRODUTO.CODPRD NOT IN (                -- Que ainda não possuem COFINS cadastrado
      SELECT T.CODPRD 
      FROM TTRBPRD T
      WHERE T.CODPRD = TPRODUTO.CODPRD 
        AND T.CODTRB = 'COFINS'
    );
