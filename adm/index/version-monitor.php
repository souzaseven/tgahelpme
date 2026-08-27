<?php
// version-monitor.php - Sistema Centralizado de Monitoramento
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
class VersionMonitor {
    private $trackedFiles = [
        'cliente-tga-notificateste.html',
        'cliente-tga.html', 
        'index.html',
        'cliente-tga12112025.html'
    ];
    private $versionFile;
    private $baseDir;
    private $currentVersions = [];
    public function __construct() {
        // __DIR__ garante que os caminhos sejam sempre resolvidos a partir da pasta
        // deste script, independente de como/de onde o PHP foi chamado (evita ficar
        // sem achar version-history.json e reportar a mesma "mudança" pra sempre).
        $this->baseDir = __DIR__;
        $this->versionFile = $this->baseDir . '/version-history.json';
        $this->loadCurrentVersions();
    }
    private function loadCurrentVersions() {
        foreach ($this->trackedFiles as $file) {
            $path = $this->baseDir . '/' . $file;
            if (file_exists($path)) {
                $version = $this->extractVersionFromFile($path);
                if ($version) {
                    $this->currentVersions[$file] = $version;
                }
            }
        }
    }
    private function extractVersionFromFile($filename) {
        $content = file_get_contents($filename);
        // Padrões para encontrar versão
        $patterns = [
            '/<span[^>]*id=["\']page-version["\'][^>]*>([^<]+)<\/span>/i',
            '/<span[^>]*id=["\']version["\'][^>]*>([^<]+)<\/span>/i',
            '/v\d+\.\d+\.\d+/i'
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                return trim($matches[1] ?? $matches[0]);
            }
        }
        return null;
    }
    public function checkVersionChanges() {
        $history = $this->loadVersionHistory();
        $changes = [];
        foreach ($this->currentVersions as $file => $currentVersion) {
            $oldVersion = $history[$file]['version'] ?? null;
            if ($oldVersion && $oldVersion !== $currentVersion) {
                $changes[$file] = [
                    'old' => $oldVersion,
                    'new' => $currentVersion,
                    'file' => $file,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
            // Atualiza histórico
            $history[$file] = [
                'version' => $currentVersion,
                'last_checked' => date('Y-m-d H:i:s'),
                'file' => $file
            ];
        }
        $this->saveVersionHistory($history);
        return [
            'changes' => $changes,
            'current_versions' => $this->currentVersions,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    private function loadVersionHistory() {
        if (file_exists($this->versionFile)) {
            return json_decode(file_get_contents($this->versionFile), true) ?: [];
        }
        return [];
    }
    private function saveVersionHistory($history) {
        $json = json_encode($history, JSON_PRETTY_PRINT);
        // LOCK_EX evita corrupção quando várias abas checam ao mesmo tempo (a cada 30s cada uma).
        $ok = file_put_contents($this->versionFile, $json, LOCK_EX);
        if ($ok === false) {
            // Se isto falhar (permissão de escrita, por exemplo), o histórico nunca é
            // atualizado no disco e a mesma "mudança" volta a ser reportada pra sempre.
            error_log('version-monitor.php: falha ao salvar ' . $this->versionFile . ' (verifique permissão de escrita)');
        }
    }
    public function getCurrentStatus() {
        return [
            'current_versions' => $this->currentVersions,
            'tracked_files' => $this->trackedFiles,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    // NOVO MÉTODO: Obter última versão do arquivo principal
    public function getLatestVersion() {
        $mainFile = 'cliente-tga.html'; // Arquivo principal
        if (isset($this->currentVersions[$mainFile])) {
            return $this->currentVersions[$mainFile];
        }
        return null;
    }
}
// Processa requisições
$monitor = new VersionMonitor();
$action = $_GET['action'] ?? 'check';
try {
    switch ($action) {
        case 'check':
            $result = $monitor->checkVersionChanges();
            break;
        case 'status':
            $result = $monitor->getCurrentStatus();
            break;
        case 'latest': // NOVO ENDPOINT
            $latestVersion = $monitor->getLatestVersion();
            $result = [
                'latest_version' => $latestVersion,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            break;
        default:
            $result = ['error' => 'Ação não reconhecida'];
    }
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>