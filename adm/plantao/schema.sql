-- =====================================================================
-- Schema do Painel de Plantão
-- MySQL 5.7+ / MariaDB 10.2+
-- =====================================================================

CREATE TABLE IF NOT EXISTS suportes_plantao (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome           VARCHAR(120) NOT NULL,
  ativo          TINYINT(1)   NOT NULL DEFAULT 1,
  criado_em      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ativo_nome (ativo, nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plantoes_fim_semana (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sabado         DATE         NOT NULL,
  domingo        DATE         NOT NULL,
  suporte_id     INT UNSIGNED DEFAULT NULL,
  suporte_nome   VARCHAR(120) DEFAULT NULL,   -- desnormalizado de propósito: preserva o nome histórico
  observacao     VARCHAR(255) DEFAULT NULL,
  criado_em      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sabado (sabado),            -- garante 1 plantão por fim de semana (upsert atômico)
  KEY idx_periodo (sabado, domingo),
  CONSTRAINT fk_plantao_suporte FOREIGN KEY (suporte_id)
    REFERENCES suportes_plantao (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MIGRAÇÃO para um banco JÁ EXISTENTE (rodar uma única vez)
-- =====================================================================
--
-- 1) Remova eventuais duplicatas de `sabado` antes de criar o índice único:
--
--    DELETE p1 FROM plantoes_fim_semana p1
--    INNER JOIN plantoes_fim_semana p2
--      ON p1.sabado = p2.sabado AND p1.id < p2.id;
--
-- 2) Crie a constraint única (obrigatória para o ON DUPLICATE KEY UPDATE da API):
--
--    ALTER TABLE plantoes_fim_semana ADD UNIQUE KEY uq_sabado (sabado);
--
-- 3) (opcional) FK para o suporte, se ainda não existir e os dados estiverem limpos:
--
--    ALTER TABLE plantoes_fim_semana
--      ADD CONSTRAINT fk_plantao_suporte FOREIGN KEY (suporte_id)
--      REFERENCES suportes_plantao (id) ON DELETE SET NULL ON UPDATE CASCADE;
-- =====================================================================
