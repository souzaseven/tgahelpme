<?php
// Buscar dados em tempo real
$realtimeStatus = $api->getRealtimeStatus();
$realtimeCanais = $api->getRealtimeCanais();
$agentes = $api->getAgentes();
$filas = $api->getFilas();
$chamadas = $api->getChamadas(['limit' => 10]);
$callcenterDash = $api->getCallcenterDashboard();
?>

<div class="dashboard-grid">
    <!-- Cards de Estatísticas -->
    <div class="card stats-card">
        <div class="stats-label">Canais Ativos</div>
        <div class="stats-value"><?= $realtimeCanais['data']['total'] ?? 0 ?></div>
        <div class="stats-trend up">↑ Em tempo real</div>
    </div>

    <div class="card stats-card">
        <div class="stats-label">Agentes Online</div>
        <div class="stats-value"><?= count($agentes['data'] ?? []) ?></div>
        <div class="stats-trend">Total de agentes</div>
    </div>

    <div class="card stats-card">
        <div class="stats-label">Filas Ativas</div>
        <div class="stats-value"><?= count($filas['data'] ?? []) ?></div>
        <div class="stats-trend">Total de filas</div>
    </div>

    <div class="card stats-card">
        <div class="stats-label">Chamadas Ativas</div>
        <div class="stats-value"><?= count($chamadas['data'] ?? []) ?></div>
        <div class="stats-trend up">↑ Agora</div>
    </div>
</div>

<!-- Últimas Chamadas -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">📞 Últimas Chamadas</h2>
        <a href="?page=chamadas" class="btn btn-sm btn-primary">Ver Todas</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Origem</th>
                        <th>Destino</th>
                        <th>Duração</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($chamadas['data'])): ?>
                        <?php foreach (array_slice($chamadas['data'], 0, 10) as $chamada): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($chamada['data'] ?? 'now')) ?></td>
                                <td><?= htmlspecialchars($chamada['origem'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($chamada['destino'] ?? '-') ?></td>
                                <td><?= gmdate("i:s", $chamada['duracao'] ?? 0) ?></td>
                                <td>
                                    <span class="badge badge-<?= ($chamada['status'] ?? '') === 'ANSWERED' ? 'success' : 'danger' ?>">
                                        <?= htmlspecialchars($chamada['status'] ?? 'N/A') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">Nenhuma chamada encontrada</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Status do Sistema -->
<div class="card mt-3">
    <div class="card-header">
        <h2 class="card-title">⚙️ Status do Sistema</h2>
    </div>
    <div class="card-body">
        <?php if ($realtimeStatus['success'] ?? false): ?>
            <div class="alert alert-success">
                ✅ Sistema operacional e funcionando normalmente
            </div>
            <div class="form-row">
                <div>
                    <strong>Status:</strong> <?= htmlspecialchars($realtimeStatus['data']['status'] ?? 'Operacional') ?>
                </div>
                <div>
                    <strong>Canais:</strong> <?= htmlspecialchars($realtimeCanais['data']['total'] ?? '0') ?>
                </div>
                <div>
                    <strong>Última Atualização:</strong> <?= date('d/m/Y H:i:s') ?>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                ⚠️ Não foi possível obter status do sistema
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Auto-refresh do dashboard a cada 30 segundos
setTimeout(() => location.reload(), 30000);
</script>
