<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Gerenciamento de Acessos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
        }
        .panel {
            background-color: #fff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            padding: 20px;
            margin: 20px auto;
            width: 90%;
            max-width: 1200px;
        }
        .panel h2, .panel h3 {
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #f4f4f4;
        }
        .stats {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
        }
        .stat-box {
            background-color: #fff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-box h3 {
            font-size: 2em;
            margin: 0;
        }
        .stat-box p {
            margin: 5px 0 0;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="panel">
        <h2>Painel de Gerenciamento de Acessos</h2>
        <div id="stats" class="stats"></div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Session ID</th>
                    <th>Última Atividade</th>
                    <th>Dispositivo</th>
                    <th>Endereço IP</th>
                    <th>Navegador</th>
                    <th>Tempo Ativo (min)</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="access-table">
                <!-- Dados dinâmicos serão carregados aqui -->
            </tbody>
        </table>
    </div>

    <script>
        function loadAccessData() {
            $.ajax({
                url: 'load_access_data.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    // Atualizar estatísticas
                    $('#stats').html(`
                        <div class="stat-box">
                            <h3>${data.stats.total_active}</h3>
                            <p>Acessos Ativos</p>
                        </div>
                        <div class="stat-box">
                            <h3>${data.stats.total_mobile}</h3>
                            <p>Mobile</p>
                        </div>
                        <div class="stat-box">
                            <h3>${data.stats.total_desktop}</h3>
                            <p>Desktop</p>
                        </div>
                        <div class="stat-box">
                            <h3>${data.stats.most_common_browser.browser || 'N/A'}</h3>
                            <p>Navegador Mais Usado</p>
                        </div>
                        <div class="stat-box">
                            <h3>${data.stats.most_common_ip.ip || 'N/A'}</h3>
                            <p>IP Mais Comum</p>
                        </div>
                    `);

                    // Atualizar tabela
                    let tableContent = '';
                    data.access.forEach(row => {
                        tableContent += `
                            <tr>
                                <td>${row.id}</td>
                                <td>${row.session_id}</td>
                                <td>${row.last_activity}</td>
                                <td>${row.device_type}</td>
                                <td>${row.ip_address}</td>
                                <td>${row.browser}</td>
                                <td>${row.time_active} min</td>
                                <td>
                                    <form method="POST" action="remove_connection.php">
                                        <input type="hidden" name="session_id" value="${row.session_id}">
                                        <button type="submit">Remover</button>
                                    </form>
                                </td>
                            </tr>
                        `;
                    });
                    $('#access-table').html(tableContent);
                },
                error: function() {
                    console.error('Erro ao carregar dados.');
                }
            });
        }

        // Atualizar dados a cada 5 segundos
        setInterval(loadAccessData, 5000);
        loadAccessData();
    </script>
</body>
</html>
