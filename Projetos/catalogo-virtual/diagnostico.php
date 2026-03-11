<?php
// ============================================
// DIAGNÓSTICO - diagnostico.php
// Coloque na raiz: catalogo-virtual/
// ============================================

$raiz = __DIR__;

// ============================================
// Mapa completo do projeto com dependências
// ============================================
$estrutura = [
    'config' => [
        'conexao.php' => [
            'descricao' => 'Conexão com banco de dados',
            'depende'   => []
        ]
    ],
    'banco' => [
        'estrutura.sql' => [
            'descricao' => 'Script SQL das tabelas',
            'depende'   => []
        ]
    ],
    'assets/css' => [
        'style.css' => [
            'descricao' => 'Estilos principais',
            'depende'   => []
        ]
    ],
    'assets/js' => [
        'main.js' => [
            'descricao' => 'Scripts do carrinho e catálogo',
            'depende'   => []
        ]
    ],
    'assets/img' => [
        '(pasta vazia)' => [
            'descricao' => 'Imagens do projeto',
            'depende'   => [],
            'opcional'  => true
        ]
    ],
    'includes' => [
        'header.php' => [
            'descricao' => 'Cabeçalho global',
            'depende'   => ['assets/css/style.css']
        ],
        'footer.php' => [
            'descricao' => 'Rodapé global',
            'depende'   => []
        ]
    ],
    'api' => [
        'produtos.php' => [
            'descricao' => 'API REST de produtos',
            'depende'   => ['config/conexao.php']
        ],
        'carrinho.php' => [
            'descricao' => 'API REST do carrinho',
            'depende'   => ['config/conexao.php']
        ],
        'pedidos.php' => [
            'descricao' => 'API REST de pedidos',
            'depende'   => ['config/conexao.php']
        ]
    ],
    'public' => [
        'index.php' => [
            'descricao' => 'Catálogo público',
            'depende'   => [
                'config/conexao.php',
                'includes/header.php',
                'includes/footer.php',
                'assets/js/main.js'
            ]
        ],
        'carrinho.php' => [
            'descricao' => 'Página do carrinho',
            'depende'   => [
                'config/conexao.php',
                'includes/header.php',
                'includes/footer.php',
                'assets/js/main.js'
            ]
        ],
        'pedido.php' => [
            'descricao' => 'Finalização de pedido',
            'depende'   => [
                'config/conexao.php',
                'includes/header.php',
                'includes/footer.php',
                'assets/js/main.js',
                'api/pedidos.php'
            ]
        ]
    ],
    'admin' => [
        'index.php' => [
            'descricao' => 'Dashboard administrativo',
            'depende'   => [
                'config/conexao.php',
                'includes/header.php',
                'includes/footer.php',
                'assets/js/main.js'
            ]
        ]
    ],
    'admin/produtos' => [
        'index.php' => [
            'descricao' => 'Listagem de produtos (admin)',
            'depende'   => [
                'config/conexao.php',
                'includes/header.php',
                'includes/footer.php',
                'assets/js/main.js',
                'api/produtos.php'
            ]
        ],
        'novo.php' => [
            'descricao' => 'Cadastrar produto',
            'depende'   => [
                'config/conexao.php',
                'includes/header.php',
                'includes/footer.php',
                'assets/js/main.js'
            ]
        ],
        'editar.php' => [
            'descricao' => 'Editar produto',
            'depende'   => [
                'config/conexao.php',
                'includes/header.php',
                'includes/footer.php',
                'assets/js/main.js',
                'api/produtos.php'
            ]
        ]
    ],
    'admin/pedidos' => [
        'index.php' => [
            'descricao' => 'Listagem de pedidos (admin)',
            'depende'   => [
                'config/conexao.php',
                'includes/header.php',
                'includes/footer.php',
                'assets/js/main.js',
                'api/pedidos.php'
            ]
        ],
        'detalhe.php' => [
            'descricao' => 'Detalhe do pedido',
            'depende'   => [
                'config/conexao.php',
                'includes/header.php',
                'includes/footer.php',
                'assets/js/main.js',
                'api/pedidos.php'
            ]
        ]
    ]
];

// ============================================
// Verificar existência dos arquivos
// ============================================
$total_ok    = 0;
$total_erro  = 0;
$resultados  = [];

foreach ($estrutura as $pasta => $arquivos) {
    foreach ($arquivos as $arquivo => $info) {
        if ($arquivo === '(pasta vazia)') {
            $caminho_real = $raiz . '/' . $pasta;
            $existe       = is_dir($caminho_real);
            $tipo         = 'pasta';
        } else {
            $caminho_real = $raiz . '/' . $pasta . '/' . $arquivo;
            $existe       = file_exists($caminho_real);
            $tipo         = 'arquivo';
        }

        // Verificar dependências
        $deps_ok   = [];
        $deps_erro = [];

        foreach ($info['depende'] as $dep) {
            $dep_path = $raiz . '/' . $dep;
            if (file_exists($dep_path)) {
                $deps_ok[] = $dep;
            } else {
                $deps_erro[] = $dep;
            }
        }

        $status = $existe ? 'ok' : 'erro';
        if ($existe && !empty($deps_erro)) $status = 'aviso';

        if ($status === 'ok')   $total_ok++;
        if ($status === 'erro') $total_erro++;

        $resultados[$pasta][$arquivo] = [
            'descricao' => $info['descricao'],
            'caminho'   => $pasta . '/' . $arquivo,
            'existe'    => $existe,
            'status'    => $status,
            'tipo'      => $tipo,
            'deps_ok'   => $deps_ok,
            'deps_erro' => $deps_erro,
            'opcional'  => $info['opcional'] ?? false
        ];
    }
}

// Testar conexão com banco
$conexao_ok  = false;
$conexao_msg = '';
try {
    require_once $raiz . '/config/conexao.php';
    $pdo->query("SELECT 1");
    $conexao_ok  = true;
    $conexao_msg = 'Conexão com banco de dados estabelecida com sucesso!';
} catch (Exception $e) {
    $conexao_msg = 'Falha na conexão: ' . $e->getMessage();
}

$total_arquivos = $total_ok + $total_erro;
$percentual     = $total_arquivos > 0 ? round(($total_ok / $total_arquivos) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico — Catálogo Virtual</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: #0f1117;
            color: #e2e8f0;
            min-height: 100vh;
            padding: 40px 20px;
        }

        .container { max-width: 1100px; margin: 0 auto; }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: -0.5px;
        }

        .header p {
            color: #64748b;
            margin-top: 6px;
            font-size: 0.9rem;
        }

        .header .raiz-path {
            display: inline-block;
            margin-top: 10px;
            background: #1e2130;
            border: 1px solid #2d3148;
            padding: 6px 14px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 0.82rem;
            color: #7dd3fc;
        }

        /* Cards de resumo */
        .resumo {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 36px;
        }

        .card-resumo {
            background: #1e2130;
            border: 1px solid #2d3148;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }

        .card-resumo .numero {
            font-size: 2.2rem;
            font-weight: 700;
            line-height: 1;
        }

        .card-resumo .rotulo {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card-resumo.verde  .numero { color: #4ade80; }
        .card-resumo.vermelho .numero { color: #f87171; }
        .card-resumo.azul   .numero { color: #60a5fa; }
        .card-resumo.amarelo .numero { color: #fbbf24; }

        /* Barra de progresso */
        .progresso-container {
            background: #1e2130;
            border: 1px solid #2d3148;
            border-radius: 10px;
            padding: 20px 24px;
            margin-bottom: 36px;
        }

        .progresso-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 0.85rem;
            color: #94a3b8;
        }

        .progresso-barra {
            height: 10px;
            background: #2d3148;
            border-radius: 10px;
            overflow: hidden;
        }

        .progresso-fill {
            height: 100%;
            border-radius: 10px;
            background: linear-gradient(90deg, #4ade80, #22d3ee);
            transition: width 1s ease;
        }

        /* Conexão banco */
        .card-conexao {
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 36px;
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .card-conexao.ok    { background: #052e16; border: 1px solid #166534; color: #4ade80; }
        .card-conexao.erro  { background: #2d0a0a; border: 1px solid #7f1d1d; color: #f87171; }

        .card-conexao .icone { font-size: 1.4rem; }

        /* Seções */
        .secao {
            margin-bottom: 28px;
        }

        .secao-titulo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid #2d3148;
        }

        .secao-titulo h2 {
            font-size: 0.95rem;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .pasta-badge {
            background: #1e2130;
            border: 1px solid #2d3148;
            border-radius: 4px;
            padding: 2px 8px;
            font-family: monospace;
            font-size: 0.78rem;
            color: #7dd3fc;
        }

        /* Linhas de arquivo */
        .arquivo-linha {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 16px;
            background: #1e2130;
            border: 1px solid #2d3148;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: border-color 0.2s;
        }

        .arquivo-linha:hover { border-color: #3d4568; }
        .arquivo-linha.ok    { border-left: 3px solid #4ade80; }
        .arquivo-linha.erro  { border-left: 3px solid #f87171; }
        .arquivo-linha.aviso { border-left: 3px solid #fbbf24; }

        .arquivo-icone { font-size: 1.1rem; padding-top: 1px; }

        .arquivo-info { flex: 1; }

        .arquivo-nome {
            font-family: monospace;
            font-size: 0.88rem;
            font-weight: 600;
            color: #e2e8f0;
        }

        .arquivo-desc {
            font-size: 0.78rem;
            color: #64748b;
            margin-top: 3px;
        }

        .arquivo-caminho {
            font-family: monospace;
            font-size: 0.72rem;
            color: #475569;
            margin-top: 2px;
        }

        /* Dependências */
        .deps {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .dep-tag {
            font-family: monospace;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .dep-tag.ok    { background: #052e16; color: #4ade80; border: 1px solid #166534; }
        .dep-tag.erro  { background: #2d0a0a; color: #f87171; border: 1px solid #7f1d1d; }

        /* Status badge */
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            white-space: nowrap;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.ok     { background: #052e16; color: #4ade80; border: 1px solid #166534; }
        .status-badge.erro   { background: #2d0a0a; color: #f87171; border: 1px solid #7f1d1d; }
        .status-badge.aviso  { background: #2d1a00; color: #fbbf24; border: 1px solid #92400e; }

        /* Links rápidos */
        .links-rapidos {
            background: #1e2130;
            border: 1px solid #2d3148;
            border-radius: 10px;
            padding: 20px 24px;
            margin-top: 36px;
        }

        .links-rapidos h3 {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            margin-bottom: 16px;
        }

        .links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 10px;
        }

        .link-card {
            background: #141720;
            border: 1px solid #2d3148;
            border-radius: 8px;
            padding: 12px 16px;
            text-decoration: none;
            color: #94a3b8;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
        }

        .link-card:hover {
            border-color: #4ade80;
            color: #4ade80;
            background: #0a1a0f;
        }

        .link-card .link-icone { font-size: 1.1rem; }

        /* Rodapé */
        .rodape {
            text-align: center;
            margin-top: 40px;
            color: #334155;
            font-size: 0.78rem;
        }

        @media (max-width: 640px) {
            .arquivo-linha { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="container">

    <!-- Header -->
    <div class="header">
        <h1>🔍 Diagnóstico do Projeto</h1>
        <p>Catálogo Virtual — Verificação de arquivos e dependências</p>
        <span class="raiz-path"><?= $raiz ?></span>
    </div>

    <!-- Cards de resumo -->
    <div class="resumo">
        <div class="card-resumo azul">
            <div class="numero"><?= $total_arquivos ?></div>
            <div class="rotulo">Total de Arquivos</div>
        </div>
        <div class="card-resumo verde">
            <div class="numero"><?= $total_ok ?></div>
            <div class="rotulo">Encontrados</div>
        </div>
        <div class="card-resumo vermelho">
            <div class="numero"><?= $total_erro ?></div>
            <div class="rotulo">Ausentes</div>
        </div>
        <div class="card-resumo amarelo">
            <div class="numero"><?= $percentual ?>%</div>
            <div class="rotulo">Completo</div>
        </div>
    </div>

    <!-- Barra de progresso -->
    <div class="progresso-container">
        <div class="progresso-label">
            <span>Progresso da estrutura</span>
            <span><?= $total_ok ?> de <?= $total_arquivos ?> arquivos</span>
        </div>
        <div class="progresso-barra">
            <div class="progresso-fill" style="width: <?= $percentual ?>%"></div>
        </div>
    </div>

    <!-- Status da conexão -->
    <div class="card-conexao <?= $conexao_ok ? 'ok' : 'erro' ?>">
        <span class="icone"><?= $conexao_ok ? '🟢' : '🔴' ?></span>
        <div>
            <strong>Banco de Dados</strong><br>
            <span style="font-weight:400;font-size:0.82rem;">
                <?= htmlspecialchars($conexao_msg) ?>
            </span>
        </div>
    </div>

    <!-- Listagem por pasta -->
    <?php foreach ($resultados as $pasta => $arquivos): ?>
        <div class="secao">
            <div class="secao-titulo">
                <h2>📁 Pasta</h2>
                <span class="pasta-badge">/<?= $pasta ?></span>
            </div>

            <?php foreach ($arquivos as $arquivo => $info): ?>
                <div class="arquivo-linha <?= $info['status'] ?>">

                    <!-- Ícone -->
                    <div class="arquivo-icone">
                        <?php if ($info['status'] === 'ok'):    echo '✅';
                        elseif ($info['status'] === 'aviso'):   echo '⚠️';
                        else:                                   echo '❌'; endif; ?>
                    </div>

                    <!-- Info -->
                    <div class="arquivo-info">
                        <div class="arquivo-nome">
                            <?= $arquivo === '(pasta vazia)' ? '📂 ' . $pasta : '📄 ' . $arquivo ?>
                        </div>
                        <div class="arquivo-desc"><?= $info['descricao'] ?></div>
                        <div class="arquivo-caminho">
                            <?= $raiz ?>/<?= $info['caminho'] ?>
                        </div>

                        <!-- Dependências -->
                        <?php if (!empty($info['deps_ok']) || !empty($info['deps_erro'])): ?>
                            <div class="deps">
                                <?php foreach ($info['deps_ok'] as $dep): ?>
                                    <span class="dep-tag ok">✓ <?= $dep ?></span>
                                <?php endforeach; ?>
                                <?php foreach ($info['deps_erro'] as $dep): ?>
                                    <span class="dep-tag erro">✗ <?= $dep ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Status badge -->
                    <div>
                        <span class="status-badge <?= $info['status'] ?>">
                            <?php if ($info['status'] === 'ok'):    echo 'OK';
                            elseif ($info['status'] === 'aviso'):   echo 'Aviso';
                            else:                                   echo 'Ausente'; endif; ?>
                        </span>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <!-- Links rápidos -->
    <div class="links-rapidos">
        <h3>⚡ Acessar páginas do projeto</h3>
        <div class="links-grid">
            <a href="public/index.php" class="link-card" target="_blank">
                <span class="link-icone">🌐</span> Catálogo Público
            </a>
            <a href="public/carrinho.php" class="link-card" target="_blank">
                <span class="link-icone">🛒</span> Carrinho
            </a>
            <a href="public/pedido.php" class="link-card" target="_blank">
                <span class="link-icone">📝</span> Finalizar Pedido
            </a>
            <a href="admin/index.php" class="link-card" target="_blank">
                <span class="link-icone">📊</span> Dashboard Admin
            </a>
            <a href="admin/produtos/index.php" class="link-card" target="_blank">
                <span class="link-icone">📦</span> Gerenciar Produtos
            </a>
            <a href="admin/produtos/novo.php" class="link-card" target="_blank">
                <span class="link-icone">➕</span> Novo Produto
            </a>
            <a href="admin/pedidos/index.php" class="link-card" target="_blank">
                <span class="link-icone">📋</span> Gerenciar Pedidos
            </a>
            <a href="api/produtos.php" class="link-card" target="_blank">
                <span class="link-icone">🔌</span> API Produtos
            </a>
        </div>
    </div>

    <div class="rodape">
        Diagnóstico gerado em <?= date('d/m/Y \à\s H:i:s') ?> —
        PHP <?= PHP_VERSION ?> —
        <?= PHP_OS ?>
    </div>

</div>
</body>
</html>