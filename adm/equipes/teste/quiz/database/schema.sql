-- =====================================================================
-- TGA ACADEMY - Plataforma de treinamento corporativo para o ERP
-- Schema do banco de dados (MySQL 8+ / InnoDB / utf8mb4)
-- =====================================================================

-- CREATE DATABASE IF NOT EXISTS tga_academy
--   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE tga_academy;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
-- 1. USUÁRIOS E AUTENTICAÇÃO
-- =====================================================================

CREATE TABLE usuarios (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome              VARCHAR(150) NOT NULL,
    email             VARCHAR(150) NOT NULL,
    senha_hash        VARCHAR(255) NOT NULL,
    tipo              ENUM('admin', 'instrutor', 'aluno') NOT NULL DEFAULT 'aluno',
    avatar_url        VARCHAR(255) NULL,
    cargo             VARCHAR(100) NULL,          -- função do colaborador no ERP
    empresa           VARCHAR(150) NULL,          -- cliente/parceiro externo
    ativo             TINYINT(1) NOT NULL DEFAULT 1,
    xp_total          INT UNSIGNED NOT NULL DEFAULT 0,
    nivel             INT UNSIGNED NOT NULL DEFAULT 1,
    streak_dias       INT UNSIGNED NOT NULL DEFAULT 0,
    ultimo_acesso     DATETIME NULL,
    criado_em         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuarios_email (email),
    KEY idx_usuarios_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 2. TAXONOMIA (categorias e tags)
-- =====================================================================

CREATE TABLE categorias (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome              VARCHAR(100) NOT NULL,
    slug              VARCHAR(100) NOT NULL,
    descricao         TEXT NULL,
    icone             VARCHAR(50) NULL,
    cor               VARCHAR(7) NULL,
    categoria_pai_id  INT UNSIGNED NULL,
    ordem             INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_categorias_slug (slug),
    KEY idx_categorias_pai (categoria_pai_id),
    CONSTRAINT fk_categorias_pai FOREIGN KEY (categoria_pai_id)
        REFERENCES categorias (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tags (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome              VARCHAR(60) NOT NULL,
    slug              VARCHAR(60) NOT NULL,
    UNIQUE KEY uq_tags_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 3. HIERARQUIA DE CONTEÚDO: Cursos > Módulos > Capítulos > Conteúdos
-- =====================================================================

CREATE TABLE cursos (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo            VARCHAR(200) NOT NULL,
    slug              VARCHAR(200) NOT NULL,
    descricao         TEXT NULL,
    categoria_id      INT UNSIGNED NULL,
    carga_horaria_min INT UNSIGNED NOT NULL DEFAULT 0,  -- usado no certificado
    capa_url          VARCHAR(255) NULL,
    status            ENUM('rascunho', 'publicado', 'arquivado') NOT NULL DEFAULT 'rascunho',
    criado_por        INT UNSIGNED NULL,
    criado_em         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cursos_slug (slug),
    KEY idx_cursos_categoria (categoria_id),
    KEY idx_cursos_status (status),
    CONSTRAINT fk_cursos_categoria FOREIGN KEY (categoria_id)
        REFERENCES categorias (id) ON DELETE SET NULL,
    CONSTRAINT fk_cursos_criador FOREIGN KEY (criado_por)
        REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE modulos (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_id          INT UNSIGNED NOT NULL,
    titulo            VARCHAR(200) NOT NULL,
    descricao         TEXT NULL,
    ordem             INT NOT NULL DEFAULT 0,
    criado_em         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_modulos_curso (curso_id),
    CONSTRAINT fk_modulos_curso FOREIGN KEY (curso_id)
        REFERENCES cursos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE capitulos (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    modulo_id         INT UNSIGNED NOT NULL,
    titulo            VARCHAR(200) NOT NULL,
    descricao         TEXT NULL,
    ordem             INT NOT NULL DEFAULT 0,
    criado_em         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_capitulos_modulo (modulo_id),
    CONSTRAINT fk_capitulos_modulo FOREIGN KEY (modulo_id)
        REFERENCES modulos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 4. IMPORTAÇÃO DE ARQUIVOS (fila de processamento para gerar conteúdo/IA)
-- =====================================================================

CREATE TABLE arquivos_importados (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome_original       VARCHAR(255) NOT NULL,
    caminho_armazenado  VARCHAR(500) NOT NULL,
    tipo_mime           VARCHAR(100) NOT NULL,
    tamanho_bytes       INT UNSIGNED NOT NULL,
    status_processamento ENUM('pendente', 'processando', 'concluido', 'erro') NOT NULL DEFAULT 'pendente',
    mensagem_erro       TEXT NULL,
    texto_extraido      LONGTEXT NULL,
    enviado_por         INT UNSIGNED NULL,
    criado_em           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_arquivos_status (status_processamento),
    CONSTRAINT fk_arquivos_usuario FOREIGN KEY (enviado_por)
        REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE conteudos (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    capitulo_id         INT UNSIGNED NOT NULL,
    titulo              VARCHAR(200) NOT NULL,
    tipo                ENUM('texto', 'video', 'arquivo', 'link') NOT NULL DEFAULT 'texto',
    corpo               LONGTEXT NULL,          -- HTML do editor
    url_externa         VARCHAR(500) NULL,      -- vídeo/link
    arquivo_origem_id   INT UNSIGNED NULL,      -- rastreabilidade: veio de qual upload
    ordem               INT NOT NULL DEFAULT 0,
    tempo_estimado_min  INT UNSIGNED NULL,
    criado_em           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_conteudos_capitulo (capitulo_id),
    CONSTRAINT fk_conteudos_capitulo FOREIGN KEY (capitulo_id)
        REFERENCES capitulos (id) ON DELETE CASCADE,
    CONSTRAINT fk_conteudos_arquivo FOREIGN KEY (arquivo_origem_id)
        REFERENCES arquivos_importados (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 5. BANCO DE QUESTÕES
-- =====================================================================

CREATE TABLE questoes (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    enunciado           TEXT NOT NULL,
    tipo                ENUM(
                            'multipla_escolha',
                            'verdadeiro_falso',
                            'multipla_selecao',
                            'complete_frase',
                            'ordenacao',
                            'associacao',
                            'estudo_caso'
                        ) NOT NULL,
    dificuldade         ENUM('facil', 'media', 'dificil', 'especialista') NOT NULL DEFAULT 'media',
    categoria_id        INT UNSIGNED NULL,
    modulo_id           INT UNSIGNED NULL,
    capitulo_id         INT UNSIGNED NULL,
    conteudo_origem_id  INT UNSIGNED NULL,      -- referência ao conteúdo que originou a questão
    resposta_texto      VARCHAR(500) NULL,      -- usado apenas em complete_frase dissertativo
    explicacao          TEXT NULL,
    gerada_por_ia       TINYINT(1) NOT NULL DEFAULT 0,
    status              ENUM('rascunho', 'revisao', 'aprovada', 'arquivada') NOT NULL DEFAULT 'rascunho',
    criado_por          INT UNSIGNED NULL,
    criado_em           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_questoes_categoria (categoria_id),
    KEY idx_questoes_modulo (modulo_id),
    KEY idx_questoes_dificuldade (dificuldade),
    KEY idx_questoes_status (status),
    FULLTEXT KEY ft_questoes_enunciado (enunciado),
    CONSTRAINT fk_questoes_categoria FOREIGN KEY (categoria_id)
        REFERENCES categorias (id) ON DELETE SET NULL,
    CONSTRAINT fk_questoes_modulo FOREIGN KEY (modulo_id)
        REFERENCES modulos (id) ON DELETE SET NULL,
    CONSTRAINT fk_questoes_capitulo FOREIGN KEY (capitulo_id)
        REFERENCES capitulos (id) ON DELETE SET NULL,
    CONSTRAINT fk_questoes_conteudo FOREIGN KEY (conteudo_origem_id)
        REFERENCES conteudos (id) ON DELETE SET NULL,
    CONSTRAINT fk_questoes_criador FOREIGN KEY (criado_por)
        REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- multipla_escolha / verdadeiro_falso / multipla_selecao usam "correta".
-- ordenacao usa "ordem_correta" (a ordem em que o item deveria aparecer).
CREATE TABLE questao_alternativas (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    questao_id          INT UNSIGNED NOT NULL,
    texto               TEXT NOT NULL,
    correta             TINYINT(1) NOT NULL DEFAULT 0,
    ordem_correta       INT UNSIGNED NULL,
    ordem               INT NOT NULL DEFAULT 0,
    KEY idx_alternativas_questao (questao_id),
    CONSTRAINT fk_alternativas_questao FOREIGN KEY (questao_id)
        REFERENCES questoes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exclusiva para questões do tipo associação (campo <-> descrição)
CREATE TABLE questao_pares (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    questao_id          INT UNSIGNED NOT NULL,
    item_esquerda       VARCHAR(255) NOT NULL,
    item_direita        VARCHAR(255) NOT NULL,
    ordem               INT NOT NULL DEFAULT 0,
    KEY idx_pares_questao (questao_id),
    CONSTRAINT fk_pares_questao FOREIGN KEY (questao_id)
        REFERENCES questoes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE questao_tags (
    questao_id          INT UNSIGNED NOT NULL,
    tag_id              INT UNSIGNED NOT NULL,
    PRIMARY KEY (questao_id, tag_id),
    CONSTRAINT fk_questaotags_questao FOREIGN KEY (questao_id)
        REFERENCES questoes (id) ON DELETE CASCADE,
    CONSTRAINT fk_questaotags_tag FOREIGN KEY (tag_id)
        REFERENCES tags (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 6. AVALIAÇÕES / PROVAS
-- =====================================================================

CREATE TABLE avaliacoes (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo                  VARCHAR(200) NOT NULL,
    tipo                    ENUM('simulado', 'prova_certificacao', 'quiz_rapido', 'diagnostico')
                                NOT NULL DEFAULT 'simulado',
    curso_id                INT UNSIGNED NULL,
    modulo_id               INT UNSIGNED NULL,
    categoria_id            INT UNSIGNED NULL,
    qtd_questoes            INT UNSIGNED NOT NULL DEFAULT 10,
    tempo_limite_min        INT UNSIGNED NULL,
    nota_minima_aprovacao   DECIMAL(5,2) NOT NULL DEFAULT 70.00,
    embaralhar_questoes     TINYINT(1) NOT NULL DEFAULT 1,
    embaralhar_alternativas TINYINT(1) NOT NULL DEFAULT 1,
    criterio_selecao        JSON NULL,  -- regras extras: dificuldade, tags, mix de categorias
    criado_por              INT UNSIGNED NULL,
    ativo                   TINYINT(1) NOT NULL DEFAULT 1,
    criado_em               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_avaliacoes_curso (curso_id),
    CONSTRAINT fk_avaliacoes_curso FOREIGN KEY (curso_id)
        REFERENCES cursos (id) ON DELETE CASCADE,
    CONSTRAINT fk_avaliacoes_modulo FOREIGN KEY (modulo_id)
        REFERENCES modulos (id) ON DELETE SET NULL,
    CONSTRAINT fk_avaliacoes_categoria FOREIGN KEY (categoria_id)
        REFERENCES categorias (id) ON DELETE SET NULL,
    CONSTRAINT fk_avaliacoes_criador FOREIGN KEY (criado_por)
        REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usada apenas quando a prova é fixa (não 100% dinâmica por critério)
CREATE TABLE avaliacao_questoes (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    avaliacao_id        INT UNSIGNED NOT NULL,
    questao_id          INT UNSIGNED NOT NULL,
    ordem               INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_avaliacao_questao (avaliacao_id, questao_id),
    CONSTRAINT fk_avquestoes_avaliacao FOREIGN KEY (avaliacao_id)
        REFERENCES avaliacoes (id) ON DELETE CASCADE,
    CONSTRAINT fk_avquestoes_questao FOREIGN KEY (questao_id)
        REFERENCES questoes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 7. TENTATIVAS E RESPOSTAS (histórico de execução das provas)
-- =====================================================================

CREATE TABLE tentativas (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    avaliacao_id        INT UNSIGNED NOT NULL,
    usuario_id          INT UNSIGNED NOT NULL,
    iniciado_em         DATETIME NOT NULL,
    finalizado_em       DATETIME NULL,
    tempo_gasto_seg     INT UNSIGNED NULL,
    nota                DECIMAL(5,2) NULL,
    total_questoes      INT UNSIGNED NOT NULL,
    acertos             INT UNSIGNED NOT NULL DEFAULT 0,
    erros               INT UNSIGNED NOT NULL DEFAULT 0,
    status              ENUM('em_andamento', 'finalizada', 'abandonada') NOT NULL DEFAULT 'em_andamento',
    aprovado            TINYINT(1) NULL,
    ip_address          VARCHAR(45) NULL,
    dispositivo         VARCHAR(255) NULL,
    KEY idx_tentativas_avaliacao (avaliacao_id),
    KEY idx_tentativas_usuario (usuario_id),
    CONSTRAINT fk_tentativas_avaliacao FOREIGN KEY (avaliacao_id)
        REFERENCES avaliacoes (id) ON DELETE CASCADE,
    CONSTRAINT fk_tentativas_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tentativa_respostas (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tentativa_id        INT UNSIGNED NOT NULL,
    questao_id          INT UNSIGNED NOT NULL,
    alternativa_id      INT UNSIGNED NULL,      -- resposta de escolha única
    resposta_texto      TEXT NULL,              -- complete_frase dissertativo
    resposta_json       JSON NULL,              -- multipla_selecao / ordenacao / associacao
    correta             TINYINT(1) NULL,
    tempo_resposta_seg  INT UNSIGNED NULL,
    ordem               INT NOT NULL DEFAULT 0,
    KEY idx_respostas_tentativa (tentativa_id),
    KEY idx_respostas_questao (questao_id),
    CONSTRAINT fk_respostas_tentativa FOREIGN KEY (tentativa_id)
        REFERENCES tentativas (id) ON DELETE CASCADE,
    CONSTRAINT fk_respostas_questao FOREIGN KEY (questao_id)
        REFERENCES questoes (id) ON DELETE CASCADE,
    CONSTRAINT fk_respostas_alternativa FOREIGN KEY (alternativa_id)
        REFERENCES questao_alternativas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 8. MATRÍCULAS E PROGRESSO
-- =====================================================================

CREATE TABLE matriculas (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id              INT UNSIGNED NOT NULL,
    curso_id                INT UNSIGNED NOT NULL,
    status                  ENUM('nao_iniciado', 'em_andamento', 'concluido') NOT NULL DEFAULT 'nao_iniciado',
    progresso_percentual    DECIMAL(5,2) NOT NULL DEFAULT 0,
    iniciado_em             DATETIME NULL,
    concluido_em            DATETIME NULL,
    UNIQUE KEY uq_matricula_usuario_curso (usuario_id, curso_id),
    CONSTRAINT fk_matriculas_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_matriculas_curso FOREIGN KEY (curso_id)
        REFERENCES cursos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE progresso_conteudos (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id          INT UNSIGNED NOT NULL,
    conteudo_id         INT UNSIGNED NOT NULL,
    concluido           TINYINT(1) NOT NULL DEFAULT 0,
    concluido_em        DATETIME NULL,
    UNIQUE KEY uq_progresso_usuario_conteudo (usuario_id, conteudo_id),
    CONSTRAINT fk_progresso_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_progresso_conteudo FOREIGN KEY (conteudo_id)
        REFERENCES conteudos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 9. CERTIFICADOS
-- =====================================================================

CREATE TABLE certificados (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id          INT UNSIGNED NOT NULL,
    curso_id            INT UNSIGNED NOT NULL,
    tentativa_id        INT UNSIGNED NULL,      -- prova que aprovou o aluno
    codigo_validacao    VARCHAR(40) NOT NULL,
    carga_horaria_min   INT UNSIGNED NOT NULL,
    nota_final          DECIMAL(5,2) NOT NULL,
    emitido_em          DATETIME NOT NULL,
    qr_code_url         VARCHAR(255) NULL,
    arquivo_pdf         VARCHAR(255) NULL,
    UNIQUE KEY uq_certificados_codigo (codigo_validacao),
    KEY idx_certificados_usuario (usuario_id),
    CONSTRAINT fk_certificados_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_certificados_curso FOREIGN KEY (curso_id)
        REFERENCES cursos (id) ON DELETE CASCADE,
    CONSTRAINT fk_certificados_tentativa FOREIGN KEY (tentativa_id)
        REFERENCES tentativas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 10. GAMIFICAÇÃO
-- =====================================================================

CREATE TABLE gamificacao_conquistas (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome                VARCHAR(100) NOT NULL,
    descricao           TEXT NULL,
    icone               VARCHAR(100) NULL,
    criterio_tipo       ENUM('cursos_concluidos', 'sequencia_dias', 'pontuacao', 'provas_aprovadas')
                            NOT NULL,
    criterio_valor      INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuario_conquistas (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id          INT UNSIGNED NOT NULL,
    conquista_id        INT UNSIGNED NOT NULL,
    conquistado_em      DATETIME NOT NULL,
    UNIQUE KEY uq_usuario_conquista (usuario_id, conquista_id),
    CONSTRAINT fk_usuconquistas_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_usuconquistas_conquista FOREIGN KEY (conquista_id)
        REFERENCES gamificacao_conquistas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE xp_historico (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id          INT UNSIGNED NOT NULL,
    origem_tipo         ENUM('conteudo_concluido', 'avaliacao_aprovada', 'streak', 'conquista') NOT NULL,
    origem_id           INT UNSIGNED NULL,
    pontos              INT NOT NULL,
    criado_em           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_xphistorico_usuario (usuario_id),
    CONSTRAINT fk_xphistorico_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
