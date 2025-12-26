<?php
$config = require_once __DIR__ . '/config.php';

$token = $config['token'];
$base_url = $config['base_url'];

// Captura os filtros da URL (GET)
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$agent_id = $_GET['agent_id'] ?? 'all';
$queue_ids = $_GET['queue_ids'] ?? 'all';
$page = $_GET['page'] ?? 1;
$limit = $_GET['limit'] ?? 20;
$call_type = $_GET['call_type'] ?? 'both';
$csat = $_GET['csat'] ?? '';
$protocol = $_GET['protocol_number'] ?? '';

// Converter datas para o formato da API
$start_date_api = $start_date . 'T00:00:00.000Z';
$end_date_api = $end_date . 'T23:59:59.999Z';

// Construir query para API
$api_params = [
    'start_date' => $start_date_api,
    'end_date' => $end_date_api,
    'agent_id' => $agent_id,
    'queue_ids' => $queue_ids,
    'call_type' => $call_type,
    'csat' => $csat,
    'protocol_number' => $protocol,
    'page' => $page,
    'limit' => $limit,
];

// Remover parâmetros vazios
$api_params = array_filter($api_params, function($value) {
    return $value !== '' && $value !== 'all';
});

$query = http_build_query($api_params);
$url = "$base_url/api/v1/report/calls_history?$query";

// Debug: Mostrar URL da API (remover em produção)
// echo "<!-- URL da API: $url -->";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["token: $token"]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Para desenvolvimento
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Para desenvolvimento

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Debug: Verificar resposta
// echo "<!-- HTTP Code: $http_code -->";
// echo "<!-- Response: " . substr($response, 0, 500) . " -->";

if ($http_code === 200) {
    $data = json_decode($response, true);
    $calls = $data['data']['calls'] ?? [];
    $total = $data['pagination']['total'] ?? 0;
} else {
    $calls = [];
    $total = 0;
    echo "<!-- Erro na API: $http_code - $error -->";
}
?>

<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Histórico de Chamadas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3b82f6;
            --primary-dark: #2563eb;
            --primary-light: #60a5fa;
            --secondary-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #06b6d4;
            
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --bg-card: #1e293b;
            
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            
            --border-color: #475569;
            --border-light: #334155;
            
            --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3), 0 4px 6px -2px rgba(0, 0, 0, 0.2);
            --shadow-hover: 0 20px 25px -5px rgba(0, 0, 0, 0.4), 0 10px 10px -5px rgba(0, 0, 0, 0.3);
            --shadow-inner: inset 0 2px 4px 0 rgba(0, 0, 0, 0.2);
            
            --radius-lg: 12px;
            --radius-md: 8px;
            --radius-sm: 6px;
            
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-tertiary) 100%);
            border-radius: var(--radius-lg);
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, var(--primary-color) 0%, transparent 70%);
            opacity: 0.1;
            border-radius: 50%;
            transform: translate(50%, -50%);
        }

        .header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-color) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header p {
            color: var(--text-secondary);
            font-size: 16px;
            max-width: 600px;
        }

        /* Theme Toggle */
        .theme-toggle {
            position: absolute;
            top: 30px;
            right: 30px;
        }

        .theme-btn {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 10px 16px;
            border-radius: var(--radius-md);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: var(--transition);
        }

        .theme-btn:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-color: var(--primary-color);
        }

        /* Filter Section */
        .filter-section {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-primary);
        }

        .section-title i {
            color: var(--primary-color);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 10px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-group label i {
            color: var(--primary-color);
            width: 20px;
        }

        .filter-group input[type="text"],
        .filter-group input[type="date"],
        .filter-group input[type="number"],
        .filter-group select {
            padding: 14px 16px;
            background: var(--bg-primary);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 14px;
            transition: var(--transition);
        }

        .filter-group input::placeholder,
        .filter-group select option:first-child {
            color: var(--text-muted);
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        .filter-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 20px;
            padding-right: 50px;
        }

        .filter-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 15px;
            padding-top: 25px;
            border-top: 1px solid var(--border-light);
        }

        /* Buttons */
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: var(--shadow);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-color: var(--primary-color);
        }

        /* Results Summary */
        .results-summary {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 25px 30px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .summary-content {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .summary-card {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .summary-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }

        .summary-text h3 {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }

        .summary-text p {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .page-info {
            color: var(--text-secondary);
            font-size: 14px;
            background: var(--bg-tertiary);
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
        }

        /* Table */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        thead {
            background: linear-gradient(135deg, var(--bg-tertiary) 0%, var(--bg-secondary) 100%);
            border-bottom: 2px solid var(--border-color);
        }

        th {
            padding: 18px 20px;
            text-align: left;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        th i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        td {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-primary);
            font-size: 14px;
        }

        tbody tr {
            transition: var(--transition);
            border-left: 3px solid transparent;
        }

        tbody tr:hover {
            background: rgba(59, 130, 246, 0.05);
            border-left-color: var(--primary-color);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* Call Type Styles */
        .call-type {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
        }

        .call-type.incoming {
            background: rgba(16, 185, 129, 0.15);
            color: var(--secondary-color);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .call-type.outgoing {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-success {
            background: rgba(16, 185, 129, 0.15);
            color: var(--secondary-color);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-warning {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .status-danger {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* Download Link */
        .download-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(59, 130, 246, 0.1);
            border-radius: var(--radius-sm);
            border: 1px solid rgba(59, 130, 246, 0.3);
            transition: var(--transition);
        }

        .download-link:hover {
            background: rgba(59, 130, 246, 0.2);
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }

        /* No Results */
        .no-results {
            text-align: center;
            padding: 60px 20px;
        }

        .no-results-icon {
            font-size: 64px;
            color: var(--text-muted);
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .no-results h3 {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 10px;
        }

        .no-results p {
            color: var(--text-muted);
            max-width: 400px;
            margin: 0 auto;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 30px;
        }

        .page-link {
            padding: 10px 16px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            min-width: 44px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .page-link:hover {
            background: var(--bg-tertiary);
            border-color: var(--primary-color);
            color: var(--text-primary);
        }

        .page-link.active {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            border-color: var(--primary-color);
            box-shadow: var(--shadow);
        }

        .page-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Error Message */
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--danger-color);
            border-radius: var(--radius-md);
            padding: 15px 20px;
            margin-bottom: 20px;
            color: var(--danger-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Loading */
        .loading {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .container {
                padding: 15px;
            }
            
            .filter-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
            
            .summary-content {
                flex-wrap: wrap;
                gap: 15px;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .header {
                padding: 25px;
            }
            
            .theme-toggle {
                position: relative;
                top: 0;
                right: 0;
                margin-top: 20px;
            }
            
            .filter-section {
                padding: 25px;
            }
            
            .filter-actions {
                flex-direction: column;
                gap: 15px;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .results-summary {
                flex-direction: column;
                gap: 20px;
                align-items: flex-start;
            }
            
            .page-info {
                align-self: flex-end;
            }
            
            th, td {
                padding: 15px;
            }
        }

        @media (max-width: 480px) {
            .header h1 {
                font-size: 24px;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .summary-card {
                flex-direction: column;
                text-align: center;
            }
            
            .summary-icon {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
            
            .pagination {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="theme-toggle">
                <button class="theme-btn" onclick="toggleTheme()">
                    <i class="fas fa-moon"></i> Modo Escuro
                </button>
            </div>
            <h1><i class="fas fa-chart-line"></i> Relatório de Chamadas</h1>
            <p>Visualize, filtre e analise todas as chamadas do sistema com precisão</p>
        </div>

        <?php if ($http_code !== 200 && $http_code !== 0): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Erro na API:</strong> Código HTTP <?= $http_code ?>
                    <?php if ($error): ?>
                        <br><small><?= htmlspecialchars($error) ?></small>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="filter-section">
            <div class="section-title">
                <i class="fas fa-sliders-h"></i> Filtros Avançados
            </div>
            <form method="GET" action="">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="start_date"><i class="far fa-calendar-alt"></i> Data Inicial</label>
                        <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="end_date"><i class="far fa-calendar-alt"></i> Data Final</label>
                        <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="agent_id"><i class="fas fa-user-tie"></i> ID do Agente</label>
                        <input type="text" id="agent_id" name="agent_id" value="<?= htmlspecialchars($agent_id) ?>" placeholder="Todos os agentes">
                    </div>
                    
                    <div class="filter-group">
                        <label for="queue_ids"><i class="fas fa-list-ol"></i> ID da Fila</label>
                        <input type="text" id="queue_ids" name="queue_ids" value="<?= htmlspecialchars($queue_ids) ?>" placeholder="Todas as filas">
                    </div>
                    
                    <div class="filter-group">
                        <label for="csat"><i class="fas fa-star"></i> Avaliação CSAT</label>
                        <input type="text" id="csat" name="csat" value="<?= htmlspecialchars($csat) ?>" placeholder="Ex: 1-5">
                    </div>
                    
                    <div class="filter-group">
                        <label for="protocol_number"><i class="fas fa-file-contract"></i> Número do Protocolo</label>
                        <input type="text" id="protocol_number" name="protocol_number" value="<?= htmlspecialchars($protocol) ?>" placeholder="Digite o protocolo">
                    </div>
                    
                    <div class="filter-group">
                        <label for="limit"><i class="fas fa-list"></i> Itens por Página</label>
                        <input type="number" id="limit" name="limit" value="<?= htmlspecialchars($limit) ?>" min="1" max="100">
                    </div>
                    
                    <div class="filter-group">
                        <label for="call_type"><i class="fas fa-phone"></i> Tipo de Chamada</label>
                        <select id="call_type" name="call_type">
                            <option value="both" <?= $call_type == 'both' ? 'selected' : '' ?>>Todos os tipos</option>
                            <option value="incoming" <?= $call_type == 'incoming' ? 'selected' : '' ?>>Chamadas de Entrada</option>
                            <option value="outgoing" <?= $call_type == 'outgoing' ? 'selected' : '' ?>>Chamadas de Saída</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="button" class="btn btn-secondary" onclick="clearFilters()">
                        <i class="fas fa-broom"></i> Limpar Filtros
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Aplicar Filtros
                    </button>
                </div>
            </form>
        </div>

        <div class="results-summary">
            <div class="summary-content">
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-phone-alt"></i>
                    </div>
                    <div class="summary-text">
                        <h3>Total de Chamadas</h3>
                        <p><?= number_format($total, 0, ',', '.') ?></p>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon" style="background: linear-gradient(135deg, var(--secondary-color) 0%, #0d966c 100%);">
                        <i class="fas fa-list-ol"></i>
                    </div>
                    <div class="summary-text">
                        <h3>Exibindo</h3>
                        <p><?= min($limit, count($calls)) ?> de <?= number_format($total, 0, ',', '.') ?></p>
                    </div>
                </div>
            </div>
            <div class="page-info">
                Página <?= $page ?> de <?= max(1, ceil($total / $limit)) ?>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th><i class="far fa-calendar"></i> Data/Hora</th>
                        <th><i class="fas fa-exchange-alt"></i> Tipo</th>
                        <th><i class="fas fa-user-tie"></i> Agente</th>
                        <th><i class="fas fa-phone"></i> Número</th>
                        <th><i class="far fa-clock"></i> Duração</th>
                        <th><i class="fas fa-flag-checkered"></i> Finalização</th>
                        <th><i class="fas fa-file-audio"></i> Gravação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($calls)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="no-results">
                                    <div class="no-results-icon">
                                        <i class="fas fa-search"></i>
                                    </div>
                                    <h3>Nenhuma chamada encontrada</h3>
                                    <p>
                                        <?php if ($http_code !== 200 && $http_code !== 0): ?>
                                            Erro na conexão com a API. Verifique os logs.
                                        <?php else: ?>
                                            Não foram encontradas chamadas com os filtros aplicados.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($calls as $call): ?>
                            <?php 
                            // Determinar classe do tipo de chamada
                            $call_type_lower = strtolower($call['call_type_description'] ?? '');
                            $call_type_class = (strpos($call_type_lower, 'entrada') !== false || strpos($call_type_lower, 'incoming') !== false) ? 'incoming' : 'outgoing';
                            
                            // Determinar classe do status
                            $end_by = strtolower($call['end_by_description'] ?? '');
                            $status_class = 'status-warning';
                            if (strpos($end_by, 'cliente') !== false || strpos($end_by, 'customer') !== false) {
                                $status_class = 'status-success';
                            } elseif (strpos($end_by, 'atendente') !== false || strpos($end_by, 'agent') !== false || strpos($end_by, 'operador') !== false) {
                                $status_class = 'status-danger';
                            }
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: var(--text-primary);">
                                        <?= date('d/m/Y', strtotime($call['date_join'])) ?>
                                    </div>
                                    <div style="color: var(--text-secondary); font-size: 13px;">
                                        <?= date('H:i:s', strtotime($call['date_join'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="call-type <?= $call_type_class ?>">
                                        <?php if ($call_type_class == 'incoming'): ?>
                                            <i class="fas fa-phone-incoming"></i>
                                        <?php else: ?>
                                            <i class="fas fa-phone-outgoing"></i>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($call['call_type_description'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: var(--text-primary);">
                                        <?= htmlspecialchars($call['agent_name'] ?? 'N/A') ?>
                                    </div>
                                    <div style="color: var(--text-secondary); font-size: 13px;">
                                        ID: <?= htmlspecialchars($call['agent_id'] ?? 'N/A') ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: var(--text-primary);">
                                        <i class="fas fa-phone" style="color: var(--primary-light); margin-right: 8px;"></i>
                                        <?= htmlspecialchars($call['receiver_number'] ?? 'N/A') ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-success">
                                        <i class="far fa-clock" style="margin-right: 6px;"></i>
                                        <?= isset($call['call_duration']) ? gmdate("H:i:s", $call['call_duration']) : '00:00:00' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?= $status_class ?>">
                                        <?= htmlspecialchars($call['end_by_description'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($call['download_audio']) && filter_var($call['download_audio'], FILTER_VALIDATE_URL)): ?>
                                        <a href="<?= $call['download_audio'] ?>" target="_blank" class="download-link" title="Baixar gravação">
                                            <i class="fas fa-download"></i> Baixar
                                        </a>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-style: italic;">
                                            <i class="fas fa-ban"></i> Indisponível
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($calls) && $total > $limit): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-link" title="Página anterior">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php endif; ?>

            <?php
            $totalPages = max(1, ceil($total / $limit));
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            
            if ($startPage > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="page-link">1</a>
                <?php if ($startPage > 2): ?>
                    <span class="page-link disabled">...</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                   class="page-link <?= $i == $page ? 'active' : '' ?>"
                   title="Ir para página <?= $i ?>">
                   <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?>
                    <span class="page-link disabled">...</span>
                <?php endif; ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>" class="page-link" title="Última página">
                    <?= $totalPages ?>
                </a>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-link" title="Próxima página">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            const themeBtn = document.querySelector('.theme-btn');
            
            html.setAttribute('data-theme', newTheme);
            
            if (newTheme === 'light') {
                html.style.setProperty('--bg-primary', '#ffffff');
                html.style.setProperty('--bg-secondary', '#f8fafc');
                html.style.setProperty('--bg-tertiary', '#f1f5f9');
                html.style.setProperty('--bg-card', '#ffffff');
                html.style.setProperty('--text-primary', '#0f172a');
                html.style.setProperty('--text-secondary', '#475569');
                html.style.setProperty('--text-muted', '#64748b');
                html.style.setProperty('--border-color', '#e2e8f0');
                html.style.setProperty('--border-light', '#f1f5f9');
                themeBtn.innerHTML = '<i class="fas fa-sun"></i> Modo Claro';
            } else {
                html.style.setProperty('--bg-primary', '#0f172a');
                html.style.setProperty('--bg-secondary', '#1e293b');
                html.style.setProperty('--bg-tertiary', '#334155');
                html.style.setProperty('--bg-card', '#1e293b');
                html.style.setProperty('--text-primary', '#f1f5f9');
                html.style.setProperty('--text-secondary', '#94a3b8');
                html.style.setProperty('--text-muted', '#64748b');
                html.style.setProperty('--border-color', '#475569');
                html.style.setProperty('--border-light', '#334155');
                themeBtn.innerHTML = '<i class="fas fa-moon"></i> Modo Escuro';
            }
            
            // Salvar preferência
            localStorage.setItem('theme', newTheme);
        }

        function clearFilters() {
            window.location.href = window.location.pathname;
        }

        // Carregar tema salvo
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            if (savedTheme === 'light') {
                toggleTheme();
            }
            
            // Melhorar experiência do formulário
            document.querySelectorAll('input[type="date"]').forEach(input => {
                input.addEventListener('focus', function() {
                    this.showPicker && this.showPicker();
                });
            });
            
            // Adicionar máscara para número de telefone
            const phoneInputs = document.querySelectorAll('input[name="receiver_number"]');
            phoneInputs.forEach(input => {
                input.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 0) {
                        if (value.length <= 2) {
                            value = '(' + value;
                        } else if (value.length <= 7) {
                            value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
                        } else if (value.length <= 11) {
                            value = '(' + value.substring(0, 2) + ') ' + value.substring(2, 7) + '-' + value.substring(7);
                        } else {
                            value = '(' + value.substring(0, 2) + ') ' + value.substring(2, 7) + '-' + value.substring(7, 11);
                        }
                    }
                    e.target.value = value;
                });
            });
        });

        // Debug: Mostrar parâmetros atuais
        console.log('Parâmetros da URL:', new URLSearchParams(window.location.search).toString());
    </script>
</body>
</html><?php
$config = require_once __DIR__ . '/config.php';

$token = $config['token'];
$base_url = $config['base_url'];

// Captura os filtros da URL (GET)
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$agent_id = $_GET['agent_id'] ?? 'all';
$queue_ids = $_GET['queue_ids'] ?? 'all';
$page = $_GET['page'] ?? 1;
$limit = $_GET['limit'] ?? 20;
$call_type = $_GET['call_type'] ?? 'both';
$csat = $_GET['csat'] ?? '';
$protocol = $_GET['protocol_number'] ?? '';

// Converter datas para o formato da API
$start_date_api = $start_date . 'T00:00:00.000Z';
$end_date_api = $end_date . 'T23:59:59.999Z';

// Construir query para API
$api_params = [
    'start_date' => $start_date_api,
    'end_date' => $end_date_api,
    'agent_id' => $agent_id,
    'queue_ids' => $queue_ids,
    'call_type' => $call_type,
    'csat' => $csat,
    'protocol_number' => $protocol,
    'page' => $page,
    'limit' => $limit,
];

// Remover parâmetros vazios
$api_params = array_filter($api_params, function($value) {
    return $value !== '' && $value !== 'all';
});

$query = http_build_query($api_params);
$url = "$base_url/api/v1/report/calls_history?$query";

// Debug: Mostrar URL da API (remover em produção)
// echo "<!-- URL da API: $url -->";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["token: $token"]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Para desenvolvimento
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Para desenvolvimento

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Debug: Verificar resposta
// echo "<!-- HTTP Code: $http_code -->";
// echo "<!-- Response: " . substr($response, 0, 500) . " -->";

if ($http_code === 200) {
    $data = json_decode($response, true);
    $calls = $data['data']['calls'] ?? [];
    $total = $data['pagination']['total'] ?? 0;
} else {
    $calls = [];
    $total = 0;
    echo "<!-- Erro na API: $http_code - $error -->";
}
?>

<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Histórico de Chamadas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3b82f6;
            --primary-dark: #2563eb;
            --primary-light: #60a5fa;
            --secondary-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #06b6d4;
            
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --bg-card: #1e293b;
            
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            
            --border-color: #475569;
            --border-light: #334155;
            
            --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3), 0 4px 6px -2px rgba(0, 0, 0, 0.2);
            --shadow-hover: 0 20px 25px -5px rgba(0, 0, 0, 0.4), 0 10px 10px -5px rgba(0, 0, 0, 0.3);
            --shadow-inner: inset 0 2px 4px 0 rgba(0, 0, 0, 0.2);
            
            --radius-lg: 12px;
            --radius-md: 8px;
            --radius-sm: 6px;
            
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-tertiary) 100%);
            border-radius: var(--radius-lg);
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, var(--primary-color) 0%, transparent 70%);
            opacity: 0.1;
            border-radius: 50%;
            transform: translate(50%, -50%);
        }

        .header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-color) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header p {
            color: var(--text-secondary);
            font-size: 16px;
            max-width: 600px;
        }

        /* Theme Toggle */
        .theme-toggle {
            position: absolute;
            top: 30px;
            right: 30px;
        }

        .theme-btn {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 10px 16px;
            border-radius: var(--radius-md);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: var(--transition);
        }

        .theme-btn:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-color: var(--primary-color);
        }

        /* Filter Section */
        .filter-section {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-primary);
        }

        .section-title i {
            color: var(--primary-color);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 10px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-group label i {
            color: var(--primary-color);
            width: 20px;
        }

        .filter-group input[type="text"],
        .filter-group input[type="date"],
        .filter-group input[type="number"],
        .filter-group select {
            padding: 14px 16px;
            background: var(--bg-primary);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 14px;
            transition: var(--transition);
        }

        .filter-group input::placeholder,
        .filter-group select option:first-child {
            color: var(--text-muted);
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        .filter-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 20px;
            padding-right: 50px;
        }

        .filter-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 15px;
            padding-top: 25px;
            border-top: 1px solid var(--border-light);
        }

        /* Buttons */
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: var(--shadow);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-color: var(--primary-color);
        }

        /* Results Summary */
        .results-summary {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 25px 30px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .summary-content {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .summary-card {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .summary-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }

        .summary-text h3 {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }

        .summary-text p {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .page-info {
            color: var(--text-secondary);
            font-size: 14px;
            background: var(--bg-tertiary);
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
        }

        /* Table */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        thead {
            background: linear-gradient(135deg, var(--bg-tertiary) 0%, var(--bg-secondary) 100%);
            border-bottom: 2px solid var(--border-color);
        }

        th {
            padding: 18px 20px;
            text-align: left;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        th i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        td {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-primary);
            font-size: 14px;
        }

        tbody tr {
            transition: var(--transition);
            border-left: 3px solid transparent;
        }

        tbody tr:hover {
            background: rgba(59, 130, 246, 0.05);
            border-left-color: var(--primary-color);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* Call Type Styles */
        .call-type {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
        }

        .call-type.incoming {
            background: rgba(16, 185, 129, 0.15);
            color: var(--secondary-color);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .call-type.outgoing {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-success {
            background: rgba(16, 185, 129, 0.15);
            color: var(--secondary-color);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-warning {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .status-danger {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* Download Link */
        .download-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(59, 130, 246, 0.1);
            border-radius: var(--radius-sm);
            border: 1px solid rgba(59, 130, 246, 0.3);
            transition: var(--transition);
        }

        .download-link:hover {
            background: rgba(59, 130, 246, 0.2);
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }

        /* No Results */
        .no-results {
            text-align: center;
            padding: 60px 20px;
        }

        .no-results-icon {
            font-size: 64px;
            color: var(--text-muted);
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .no-results h3 {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 10px;
        }

        .no-results p {
            color: var(--text-muted);
            max-width: 400px;
            margin: 0 auto;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 30px;
        }

        .page-link {
            padding: 10px 16px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            min-width: 44px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .page-link:hover {
            background: var(--bg-tertiary);
            border-color: var(--primary-color);
            color: var(--text-primary);
        }

        .page-link.active {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            border-color: var(--primary-color);
            box-shadow: var(--shadow);
        }

        .page-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Error Message */
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--danger-color);
            border-radius: var(--radius-md);
            padding: 15px 20px;
            margin-bottom: 20px;
            color: var(--danger-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Loading */
        .loading {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .container {
                padding: 15px;
            }
            
            .filter-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
            
            .summary-content {
                flex-wrap: wrap;
                gap: 15px;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .header {
                padding: 25px;
            }
            
            .theme-toggle {
                position: relative;
                top: 0;
                right: 0;
                margin-top: 20px;
            }
            
            .filter-section {
                padding: 25px;
            }
            
            .filter-actions {
                flex-direction: column;
                gap: 15px;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .results-summary {
                flex-direction: column;
                gap: 20px;
                align-items: flex-start;
            }
            
            .page-info {
                align-self: flex-end;
            }
            
            th, td {
                padding: 15px;
            }
        }

        @media (max-width: 480px) {
            .header h1 {
                font-size: 24px;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .summary-card {
                flex-direction: column;
                text-align: center;
            }
            
            .summary-icon {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
            
            .pagination {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="theme-toggle">
                <button class="theme-btn" onclick="toggleTheme()">
                    <i class="fas fa-moon"></i> Modo Escuro
                </button>
            </div>
            <h1><i class="fas fa-chart-line"></i> Relatório de Chamadas</h1>
            <p>Visualize, filtre e analise todas as chamadas do sistema com precisão</p>
        </div>

        <?php if ($http_code !== 200 && $http_code !== 0): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Erro na API:</strong> Código HTTP <?= $http_code ?>
                    <?php if ($error): ?>
                        <br><small><?= htmlspecialchars($error) ?></small>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="filter-section">
            <div class="section-title">
                <i class="fas fa-sliders-h"></i> Filtros Avançados
            </div>
            <form method="GET" action="">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="start_date"><i class="far fa-calendar-alt"></i> Data Inicial</label>
                        <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="end_date"><i class="far fa-calendar-alt"></i> Data Final</label>
                        <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="agent_id"><i class="fas fa-user-tie"></i> ID do Agente</label>
                        <input type="text" id="agent_id" name="agent_id" value="<?= htmlspecialchars($agent_id) ?>" placeholder="Todos os agentes">
                    </div>
                    
                    <div class="filter-group">
                        <label for="queue_ids"><i class="fas fa-list-ol"></i> ID da Fila</label>
                        <input type="text" id="queue_ids" name="queue_ids" value="<?= htmlspecialchars($queue_ids) ?>" placeholder="Todas as filas">
                    </div>
                    
                    <div class="filter-group">
                        <label for="csat"><i class="fas fa-star"></i> Avaliação CSAT</label>
                        <input type="text" id="csat" name="csat" value="<?= htmlspecialchars($csat) ?>" placeholder="Ex: 1-5">
                    </div>
                    
                    <div class="filter-group">
                        <label for="protocol_number"><i class="fas fa-file-contract"></i> Número do Protocolo</label>
                        <input type="text" id="protocol_number" name="protocol_number" value="<?= htmlspecialchars($protocol) ?>" placeholder="Digite o protocolo">
                    </div>
                    
                    <div class="filter-group">
                        <label for="limit"><i class="fas fa-list"></i> Itens por Página</label>
                        <input type="number" id="limit" name="limit" value="<?= htmlspecialchars($limit) ?>" min="1" max="100">
                    </div>
                    
                    <div class="filter-group">
                        <label for="call_type"><i class="fas fa-phone"></i> Tipo de Chamada</label>
                        <select id="call_type" name="call_type">
                            <option value="both" <?= $call_type == 'both' ? 'selected' : '' ?>>Todos os tipos</option>
                            <option value="incoming" <?= $call_type == 'incoming' ? 'selected' : '' ?>>Chamadas de Entrada</option>
                            <option value="outgoing" <?= $call_type == 'outgoing' ? 'selected' : '' ?>>Chamadas de Saída</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="button" class="btn btn-secondary" onclick="clearFilters()">
                        <i class="fas fa-broom"></i> Limpar Filtros
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Aplicar Filtros
                    </button>
                </div>
            </form>
        </div>

        <div class="results-summary">
            <div class="summary-content">
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-phone-alt"></i>
                    </div>
                    <div class="summary-text">
                        <h3>Total de Chamadas</h3>
                        <p><?= number_format($total, 0, ',', '.') ?></p>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon" style="background: linear-gradient(135deg, var(--secondary-color) 0%, #0d966c 100%);">
                        <i class="fas fa-list-ol"></i>
                    </div>
                    <div class="summary-text">
                        <h3>Exibindo</h3>
                        <p><?= min($limit, count($calls)) ?> de <?= number_format($total, 0, ',', '.') ?></p>
                    </div>
                </div>
            </div>
            <div class="page-info">
                Página <?= $page ?> de <?= max(1, ceil($total / $limit)) ?>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th><i class="far fa-calendar"></i> Data/Hora</th>
                        <th><i class="fas fa-exchange-alt"></i> Tipo</th>
                        <th><i class="fas fa-user-tie"></i> Agente</th>
                        <th><i class="fas fa-phone"></i> Número</th>
                        <th><i class="far fa-clock"></i> Duração</th>
                        <th><i class="fas fa-flag-checkered"></i> Finalização</th>
                        <th><i class="fas fa-file-audio"></i> Gravação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($calls)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="no-results">
                                    <div class="no-results-icon">
                                        <i class="fas fa-search"></i>
                                    </div>
                                    <h3>Nenhuma chamada encontrada</h3>
                                    <p>
                                        <?php if ($http_code !== 200 && $http_code !== 0): ?>
                                            Erro na conexão com a API. Verifique os logs.
                                        <?php else: ?>
                                            Não foram encontradas chamadas com os filtros aplicados.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($calls as $call): ?>
                            <?php 
                            // Determinar classe do tipo de chamada
                            $call_type_lower = strtolower($call['call_type_description'] ?? '');
                            $call_type_class = (strpos($call_type_lower, 'entrada') !== false || strpos($call_type_lower, 'incoming') !== false) ? 'incoming' : 'outgoing';
                            
                            // Determinar classe do status
                            $end_by = strtolower($call['end_by_description'] ?? '');
                            $status_class = 'status-warning';
                            if (strpos($end_by, 'cliente') !== false || strpos($end_by, 'customer') !== false) {
                                $status_class = 'status-success';
                            } elseif (strpos($end_by, 'atendente') !== false || strpos($end_by, 'agent') !== false || strpos($end_by, 'operador') !== false) {
                                $status_class = 'status-danger';
                            }
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: var(--text-primary);">
                                        <?= date('d/m/Y', strtotime($call['date_join'])) ?>
                                    </div>
                                    <div style="color: var(--text-secondary); font-size: 13px;">
                                        <?= date('H:i:s', strtotime($call['date_join'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="call-type <?= $call_type_class ?>">
                                        <?php if ($call_type_class == 'incoming'): ?>
                                            <i class="fas fa-phone-incoming"></i>
                                        <?php else: ?>
                                            <i class="fas fa-phone-outgoing"></i>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($call['call_type_description'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: var(--text-primary);">
                                        <?= htmlspecialchars($call['agent_name'] ?? 'N/A') ?>
                                    </div>
                                    <div style="color: var(--text-secondary); font-size: 13px;">
                                        ID: <?= htmlspecialchars($call['agent_id'] ?? 'N/A') ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: var(--text-primary);">
                                        <i class="fas fa-phone" style="color: var(--primary-light); margin-right: 8px;"></i>
                                        <?= htmlspecialchars($call['receiver_number'] ?? 'N/A') ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-success">
                                        <i class="far fa-clock" style="margin-right: 6px;"></i>
                                        <?= isset($call['call_duration']) ? gmdate("H:i:s", $call['call_duration']) : '00:00:00' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?= $status_class ?>">
                                        <?= htmlspecialchars($call['end_by_description'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($call['download_audio']) && filter_var($call['download_audio'], FILTER_VALIDATE_URL)): ?>
                                        <a href="<?= $call['download_audio'] ?>" target="_blank" class="download-link" title="Baixar gravação">
                                            <i class="fas fa-download"></i> Baixar
                                        </a>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-style: italic;">
                                            <i class="fas fa-ban"></i> Indisponível
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($calls) && $total > $limit): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-link" title="Página anterior">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php endif; ?>

            <?php
            $totalPages = max(1, ceil($total / $limit));
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            
            if ($startPage > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="page-link">1</a>
                <?php if ($startPage > 2): ?>
                    <span class="page-link disabled">...</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                   class="page-link <?= $i == $page ? 'active' : '' ?>"
                   title="Ir para página <?= $i ?>">
                   <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?>
                    <span class="page-link disabled">...</span>
                <?php endif; ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>" class="page-link" title="Última página">
                    <?= $totalPages ?>
                </a>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-link" title="Próxima página">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            const themeBtn = document.querySelector('.theme-btn');
            
            html.setAttribute('data-theme', newTheme);
            
            if (newTheme === 'light') {
                html.style.setProperty('--bg-primary', '#ffffff');
                html.style.setProperty('--bg-secondary', '#f8fafc');
                html.style.setProperty('--bg-tertiary', '#f1f5f9');
                html.style.setProperty('--bg-card', '#ffffff');
                html.style.setProperty('--text-primary', '#0f172a');
                html.style.setProperty('--text-secondary', '#475569');
                html.style.setProperty('--text-muted', '#64748b');
                html.style.setProperty('--border-color', '#e2e8f0');
                html.style.setProperty('--border-light', '#f1f5f9');
                themeBtn.innerHTML = '<i class="fas fa-sun"></i> Modo Claro';
            } else {
                html.style.setProperty('--bg-primary', '#0f172a');
                html.style.setProperty('--bg-secondary', '#1e293b');
                html.style.setProperty('--bg-tertiary', '#334155');
                html.style.setProperty('--bg-card', '#1e293b');
                html.style.setProperty('--text-primary', '#f1f5f9');
                html.style.setProperty('--text-secondary', '#94a3b8');
                html.style.setProperty('--text-muted', '#64748b');
                html.style.setProperty('--border-color', '#475569');
                html.style.setProperty('--border-light', '#334155');
                themeBtn.innerHTML = '<i class="fas fa-moon"></i> Modo Escuro';
            }
            
            // Salvar preferência
            localStorage.setItem('theme', newTheme);
        }

        function clearFilters() {
            window.location.href = window.location.pathname;
        }

        // Carregar tema salvo
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            if (savedTheme === 'light') {
                toggleTheme();
            }
            
            // Melhorar experiência do formulário
            document.querySelectorAll('input[type="date"]').forEach(input => {
                input.addEventListener('focus', function() {
                    this.showPicker && this.showPicker();
                });
            });
            
            // Adicionar máscara para número de telefone
            const phoneInputs = document.querySelectorAll('input[name="receiver_number"]');
            phoneInputs.forEach(input => {
                input.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 0) {
                        if (value.length <= 2) {
                            value = '(' + value;
                        } else if (value.length <= 7) {
                            value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
                        } else if (value.length <= 11) {
                            value = '(' + value.substring(0, 2) + ') ' + value.substring(2, 7) + '-' + value.substring(7);
                        } else {
                            value = '(' + value.substring(0, 2) + ') ' + value.substring(2, 7) + '-' + value.substring(7, 11);
                        }
                    }
                    e.target.value = value;
                });
            });
        });

        // Debug: Mostrar parâmetros atuais
        console.log('Parâmetros da URL:', new URLSearchParams(window.location.search).toString());
    </script>
</body>
</html>