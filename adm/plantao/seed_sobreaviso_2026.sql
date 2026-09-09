-- =====================================================================
-- Seed: Escala de SOBREAVISO (plantão de fim de semana)
-- Meses: Julho, Agosto e Setembro / 2026
-- Alvo : suportes_plantao + plantoes_fim_semana  (ver schema.sql)
-- MySQL 5.7+ / MariaDB 10.2+
--
-- Idempotente: pode rodar de novo sem duplicar.
--  - Colaboradores: inseridos só se ainda não existirem (não há UNIQUE
--    em `nome` no schema, então a guarda é feita com NOT EXISTS).
--  - Plantões: upsert por `sabado` (UNIQUE KEY uq_sabado), no mesmo
--    formato usado pela API em backend/plantoes_api.php.
--
-- ATENÇÃO: um `sabado` que já exista será SOBRESCRITO com os dados
-- abaixo (inclusive observação). É esse o objetivo da carga.
--
-- NOTAS sobre a fonte:
--  - Julho/2026 foi enviado em duas versões. Aqui está a VERSÃO A:
--      04/07 Diogo · 11/07 Carlos · 18/07 Flávio · 25/07 Rodrigo
--    A outra versão (04 Carlos · 11 Rodrigo · 18 Flávio · 25 Diogo) foi descartada.
--  - "Flavio" foi normalizado para "Flávio".
--  - Férias mencionadas na origem (não gravadas em `observacao`; ajuste
--    pelo painel se quiser exibir):
--      Carlos  : 16/07 a 30/07/2026  (a origem também trazia "16/06", provável erro de digitação)
--      Rodrigo : 20/07 a 29/07/2026  -> sobrepõe o plantão de 25/07 (Rodrigo). Revisar.
--  - Jul e Ago/2026 são datas passadas: só entram por SQL — a API
--    (plantao_save) bloqueia cadastro em data passada.
-- =====================================================================

SET NAMES utf8mb4;

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1) Colaboradores do sobreaviso
-- ---------------------------------------------------------------------
INSERT INTO suportes_plantao (nome, ativo)
SELECT 'Carlos', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM suportes_plantao WHERE nome = 'Carlos');

INSERT INTO suportes_plantao (nome, ativo)
SELECT 'Diogo', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM suportes_plantao WHERE nome = 'Diogo');

INSERT INTO suportes_plantao (nome, ativo)
SELECT 'Flávio', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM suportes_plantao WHERE nome = 'Flávio');

INSERT INTO suportes_plantao (nome, ativo)
SELECT 'Rodrigo', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM suportes_plantao WHERE nome = 'Rodrigo');

-- ---------------------------------------------------------------------
-- 2) Plantões por fim de semana (sábado + domingo)
--    A escala vira uma tabela derivada e casa com o colaborador pelo nome.
-- ---------------------------------------------------------------------
INSERT INTO plantoes_fim_semana (sabado, domingo, suporte_id, suporte_nome, observacao)
SELECT e.sabado, e.domingo, s.id, s.nome, e.observacao
FROM (
            SELECT '2026-07-04' AS sabado, '2026-07-05' AS domingo, 'Diogo'   AS nome, NULL AS observacao
  UNION ALL SELECT '2026-07-11',           '2026-07-12',            'Carlos',            NULL
  UNION ALL SELECT '2026-07-18',           '2026-07-19',            'Flávio',            NULL
  UNION ALL SELECT '2026-07-25',           '2026-07-26',            'Rodrigo',           NULL
  UNION ALL SELECT '2026-08-01',           '2026-08-02',            'Rodrigo',           NULL
  UNION ALL SELECT '2026-08-08',           '2026-08-09',            'Carlos',            NULL
  UNION ALL SELECT '2026-08-15',           '2026-08-16',            'Diogo',             NULL
  UNION ALL SELECT '2026-08-22',           '2026-08-23',            'Rodrigo',           NULL
  UNION ALL SELECT '2026-08-29',           '2026-08-30',            'Flávio',            NULL
  UNION ALL SELECT '2026-09-05',           '2026-09-06',            'Diogo',             NULL
  UNION ALL SELECT '2026-09-12',           '2026-09-13',            'Carlos',            NULL
  UNION ALL SELECT '2026-09-19',           '2026-09-20',            'Flávio',            NULL
  UNION ALL SELECT '2026-09-26',           '2026-09-27',            'Diogo',             NULL
) AS e
JOIN suportes_plantao s ON s.nome = e.nome
ON DUPLICATE KEY UPDATE
  domingo      = VALUES(domingo),
  suporte_id   = VALUES(suporte_id),
  suporte_nome = VALUES(suporte_nome),
  observacao   = VALUES(observacao);

COMMIT;

-- ---------------------------------------------------------------------
-- 3) Conferência (opcional)
-- ---------------------------------------------------------------------
-- SELECT p.sabado, p.domingo, p.suporte_nome, p.observacao
-- FROM plantoes_fim_semana p
-- WHERE p.sabado BETWEEN '2026-07-01' AND '2026-09-30'
-- ORDER BY p.sabado;
