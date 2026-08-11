-- =============================================================================
-- TGA Carreiras — SNAPSHOT REAL DO BANCO DE PRODUÇÃO
-- Capturado via SHOW CREATE TABLE em 2026-08-11, direto do banco
-- "tgamea80_SUPORTE" (host 108.167.151.50).
--
-- ATENÇÃO — LEIA ANTES DE USAR ESTE ARQUIVO:
--   1. Este arquivo documenta a estrutura REAL já existente em produção.
--      Não é (e não deve ser tratado como) um script de criação do zero.
--   2. Uma versão anterior deste arquivo continha uma estrutura especulativa,
--      inventada antes de termos acesso real ao banco. Aquela versão NÃO
--      refletia a realidade e foi substituída por esta.
--   3. O banco "tgamea80_SUPORTE" é COMPARTILHADO com dezenas de outras
--      tabelas de outros projetos (prefixos afiacao_, controle_planilhas_,
--      renascer_, chat_, quiz_, etc.). NUNCA criar, alterar ou remover
--      tabelas fora do escopo abaixo sem confirmação explícita.
--   4. As tabelas já contêm dados reais (não estão vazias). Qualquer ALTER
--      TABLE deve ser tratado como migração em produção: analisar impacto,
--      fazer backup e testar antes de aplicar.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Tabelas do TGA Carreiras confirmadas no banco (todas já existem e têm dados):
--   usuarios_carreiras       (candidatos + admins/moderadores)
--   empresas_carreiras       (contas empresariais)
--   vagas
--   candidaturas
--   sugestoes_tgacarreiras   (já em uso por múltiplos módulos, não só carreiras)
--   sugestoes_historico
--
-- Tabelas mencionadas no plano do produto que NÃO existem ainda neste banco
-- (confirmado via SHOW TABLES em 2026-08-11) — serão criadas somente quando a
-- fase correspondente for implementada:
--   rate_limit          -> FASE 3 (proteção contra brute force no login)
--   redefinicao_senha   -> FASE 3 (recuperação de senha)
--   logs_sistema        -> FASE 10 (auditoria de ações administrativas)
--
-- IMPORTANTE: o banco também contém tabelas "usuarios" e "empresas" (SEM o
-- sufixo "_carreiras") — essas pertencem a OUTRO sistema e não têm relação
-- com o TGA Carreiras. Não confundir nem referenciar.
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `usuarios_carreiras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `tipo` enum('candidato','empresa','admin') NOT NULL DEFAULT 'candidato',
  `ativo` tinyint(1) DEFAULT '1',
  `data_cadastro` datetime DEFAULT CURRENT_TIMESTAMP,
  `ultimo_login` datetime DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `telefone` varchar(20) DEFAULT NULL,
  `estado` varchar(2) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `linkedin` varchar(255) DEFAULT NULL,
  `portfolio` varchar(255) DEFAULT NULL,
  `cargo` varchar(150) DEFAULT NULL,
  `bio` text,
  `cpf` varchar(14) DEFAULT NULL,
  `area_interesse` varchar(150) DEFAULT NULL,
  `escolaridade` varchar(80) DEFAULT NULL,
  `experiencia` varchar(80) DEFAULT NULL,
  `salario_pretendido` varchar(80) DEFAULT NULL,
  `instagram` varchar(120) DEFAULT NULL,
  `facebook` varchar(120) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `curriculo` varchar(255) DEFAULT NULL,
  `habilidades` text,
  `exp_texto` text,
  `formacao_texto` text,
  `idiomas` text,
  `info_adicional` text,
  `pref_setores` varchar(500) DEFAULT NULL,
  `pref_estados` varchar(200) DEFAULT NULL,
  `pref_modelo` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `email_2` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Observação: existem DUAS UNIQUE KEY redundantes em "email" (email e email_2).
-- Não é urgente, mas é uma limpeza recomendada para o futuro (ver README).

CREATE TABLE IF NOT EXISTS `empresas_carreiras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome_empresa` varchar(150) COLLATE utf8_unicode_ci NOT NULL,
  `razao_social` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `cnpj` varchar(18) COLLATE utf8_unicode_ci NOT NULL,
  `setor` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `porte` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `responsavel` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8_unicode_ci NOT NULL,
  `telefone` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `estado` char(2) COLLATE utf8_unicode_ci DEFAULT NULL,
  `cidade` varchar(150) COLLATE utf8_unicode_ci DEFAULT NULL,
  `senha` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8_unicode_ci,
  `site` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `status` enum('ativa','inativa') COLLATE utf8_unicode_ci DEFAULT 'ativa',
  `data_cadastro` datetime DEFAULT CURRENT_TIMESTAMP,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `idx_cnpj` (`cnpj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
-- Observação: charset utf8 (não utf8mb4) — inconsistente com as demais
-- tabelas. Não crítico hoje (utf8 cobre acentuação normal), mas impede
-- emojis/alguns caracteres especiais em campos como "descricao". Migração
-- para utf8mb4 é uma melhoria RECOMENDADA, não urgente.

CREATE TABLE IF NOT EXISTS `vagas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(200) NOT NULL,
  `empresa` varchar(150) DEFAULT NULL,
  `descricao` text NOT NULL,
  `requisitos` text,
  `imagem` varchar(255) DEFAULT NULL,
  `salario` decimal(10,2) DEFAULT NULL,
  `setor` varchar(150) DEFAULT NULL,
  `localizacao` varchar(150) DEFAULT NULL,
  `data_publicacao` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` varchar(20) NOT NULL DEFAULT 'ativa',
  `empresa_id` int(11) DEFAULT NULL,
  `views` int(11) DEFAULT '0',
  `quantidade_vagas` int(11) DEFAULT '1',
  `disponibilidade` varchar(50) DEFAULT NULL,
  `escolaridade` varchar(100) DEFAULT NULL,
  `experiencia` varchar(100) DEFAULT NULL,
  `beneficios` text,
  `tipo_contrato` varchar(50) DEFAULT NULL,
  `modelo` varchar(50) DEFAULT NULL,
  `nivel` varchar(50) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` varchar(2) DEFAULT NULL,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  `data_cadastro` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Observações:
--  - "empresa_id" é NULLABLE e SEM foreign key para empresas_carreiras.
--    O plano do produto exige "cada vaga pertence obrigatoriamente a uma
--    empresa" — hoje isso não é garantido pelo banco. RECOMENDADO adicionar
--    FK (com plano de saneamento de dados órfãos antes).
--  - "salario" e "views"/"visualizacoes" e "data_publicacao"/"criado_em"/
--    "data_cadastro" têm nomes/redundância diferentes do que o plano do
--    produto descreve — o código de aplicação deve seguir os nomes REAIS
--    acima, não os nomes do documento de visão do produto.

CREATE TABLE IF NOT EXISTS `candidaturas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vaga_id` int(11) NOT NULL,
  `candidato_id` int(11) DEFAULT NULL,
  `nome` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `telefone` varchar(30) DEFAULT NULL,
  `mensagem` text,
  `curriculo` varchar(255) DEFAULT NULL,
  `curriculo_pdf` varchar(255) DEFAULT NULL,
  `data_candidatura` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` enum('novo','em_analise','aprovado','reprovado') DEFAULT 'novo',
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `observacao` text,
  `atualizado_em` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `nota` text,
  PRIMARY KEY (`id`),
  KEY `vaga_id` (`vaga_id`),
  CONSTRAINT `candidaturas_ibfk_1` FOREIGN KEY (`vaga_id`) REFERENCES `vagas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Observações:
--  - Existem DOIS campos de vínculo com o candidato: "candidato_id" e
--    "usuario_id", ambos nullable, sem FK. Isso é uma ambiguidade real que
--    precisa ser esclarecida antes de escrever qualquer código de
--    candidatura (qual campo o sistema atual realmente usa?).
--  - Existem DOIS campos de currículo: "curriculo" e "curriculo_pdf".
--    Mesma dúvida: qual é o campo em uso?
--  - NÃO existe UNIQUE KEY (vaga_id, email) — o plano do produto exige
--    impedir candidatura duplicada para a mesma vaga + e-mail, e hoje o
--    banco não impede isso. RECOMENDADO adicionar a constraint (checando
--    antes se já existem duplicatas nos dados atuais).
--  - Status usa valores em português com "novo" (não "pendente" como o
--    plano do produto descrevia) — seguir os valores REAIS do enum.

CREATE TABLE IF NOT EXISTS `sugestoes_tgacarreiras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('interno','cliente','empresa') COLLATE utf8_unicode_ci NOT NULL COMMENT 'Origem da sugestão',
  `titulo` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8_unicode_ci NOT NULL,
  `resumo` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `nome_solicitante` varchar(150) COLLATE utf8_unicode_ci DEFAULT NULL,
  `email_solicitante` varchar(150) COLLATE utf8_unicode_ci DEFAULT NULL,
  `empresa` varchar(150) COLLATE utf8_unicode_ci DEFAULT NULL,
  `prioridade` enum('baixa','media','alta','urgente') COLLATE utf8_unicode_ci DEFAULT 'media',
  `status` enum('aberto','em_analise','aprovado','em_desenvolvimento','concluido','rejeitado') COLLATE utf8_unicode_ci DEFAULT 'aberto',
  `modulo` varchar(150) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Ex: TGA Carreiras, Smart POS, ERP, Evolux',
  `criado_por` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `atribuido_para` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `data_criacao` datetime DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` datetime DEFAULT NULL,
  `data_atualizacao` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `data_conclusao` datetime DEFAULT NULL,
  `visualizacoes` int(11) DEFAULT '0',
  `votos` int(11) DEFAULT '0',
  `link_externo` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `anexos` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_prioridade` (`prioridade`),
  KEY `idx_modulo` (`modulo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
-- Observação: esta tabela é usada por MÚLTIPLOS módulos/produtos (ver coluna
-- "modulo": TGA Carreiras, Smart POS, ERP, Evolux), não é exclusiva do TGA
-- Carreiras. Qualquer código de aplicação deve sempre filtrar por
-- modulo = 'TGA Carreiras' (ou equivalente) antes de ler/gravar aqui.

CREATE TABLE IF NOT EXISTS `sugestoes_historico` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sugestao_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `status_anterior` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `status_novo` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `data_alteracao` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sugestao_id` (`sugestao_id`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
