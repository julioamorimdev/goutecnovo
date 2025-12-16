<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Configurações de Armazenamento';
$active = 'storage_settings';
require_once __DIR__ . '/partials/layout_start.php';

$activeTab = $_GET['tab'] ?? 'local';

// Processar salvamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->beginTransaction();
        
        foreach ($_POST as $key => $value) {
            if ($key === '_csrf' || $key === 'tab') continue;
            
            // Extrair storage_type e setting_key do nome do campo (formato: storage_type_setting_key)
            $parts = explode('_', $key, 2);
            if (count($parts) === 2) {
                $storageType = $parts[0];
                $settingKey = $parts[1];
                
                $stmt = db()->prepare("INSERT INTO storage_settings (storage_type, setting_key, setting_value) 
                                      VALUES (?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $stmt->execute([$storageType, $settingKey, $value]);
            }
        }
        
        db()->commit();
        $_SESSION['success'] = 'Configurações de armazenamento salvas com sucesso.';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=' . $activeTab);
        exit;
    } catch (Throwable $e) {
        db()->rollBack();
        $_SESSION['error'] = 'Erro ao salvar configurações: ' . $e->getMessage();
    }
}

// Buscar configurações
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = db()->query("SELECT storage_type, setting_key, setting_value FROM storage_settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['storage_type']][$row['setting_key']] = $row['setting_value'];
    }
} catch (Throwable $e) {
    $settings = [];
}

function getStorageSetting($type, $key, $default = '') {
    global $settings;
    return $settings[$type][$key] ?? $default;
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Configurações de Armazenamento</h1>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= h($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="tab" value="<?= h($activeTab) ?>">
        
        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'local' ? 'active' : '' ?>" 
                   href="?tab=local">Armazenamento Local</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 's3' ? 'active' : '' ?>" 
                   href="?tab=s3">Amazon S3</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'ftp' ? 'active' : '' ?>" 
                   href="?tab=ftp">FTP/SFTP</a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Armazenamento Local -->
            <?php if ($activeTab === 'local'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Armazenamento Local</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="local_base_path" class="form-label">Caminho Base</label>
                                <input type="text" class="form-control" id="local_base_path" name="local_base_path" 
                                       value="<?= h(getStorageSetting('local', 'base_path', '/var/www/goutecnovo/storage')) ?>">
                                <small class="text-muted">Caminho base para armazenamento de arquivos.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="local_max_file_size" class="form-label">Tamanho Máximo do Arquivo (bytes)</label>
                                <input type="number" class="form-control" id="local_max_file_size" name="local_max_file_size" 
                                       value="<?= h(getStorageSetting('local', 'max_file_size', '10485760')) ?>">
                                <small class="text-muted">10MB = 10485760 bytes</small>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="local_allowed_extensions" class="form-label">Extensões Permitidas</label>
                                <input type="text" class="form-control" id="local_allowed_extensions" name="local_allowed_extensions" 
                                       value="<?= h(getStorageSetting('local', 'allowed_extensions', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,zip,rar')) ?>">
                                <small class="text-muted">Separe por vírgula (ex: jpg,png,pdf)</small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Amazon S3 -->
            <?php if ($activeTab === 's3'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Amazon S3</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="s3_enabled" name="s3_enabled" 
                                           value="1" <?= getStorageSetting('s3', 'enabled') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="s3_enabled">
                                        Habilitar Armazenamento S3
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="s3_access_key" class="form-label">Access Key</label>
                                <input type="text" class="form-control" id="s3_access_key" name="s3_access_key" 
                                       value="<?= h(getStorageSetting('s3', 'access_key')) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="s3_secret_key" class="form-label">Secret Key</label>
                                <input type="password" class="form-control" id="s3_secret_key" name="s3_secret_key" 
                                       value="<?= h(getStorageSetting('s3', 'secret_key')) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="s3_bucket" class="form-label">Bucket</label>
                                <input type="text" class="form-control" id="s3_bucket" name="s3_bucket" 
                                       value="<?= h(getStorageSetting('s3', 'bucket')) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="s3_region" class="form-label">Região</label>
                                <input type="text" class="form-control" id="s3_region" name="s3_region" 
                                       value="<?= h(getStorageSetting('s3', 'region', 'us-east-1')) ?>">
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- FTP/SFTP -->
            <?php if ($activeTab === 'ftp'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">FTP/SFTP</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="ftp_enabled" name="ftp_enabled" 
                                           value="1" <?= getStorageSetting('ftp', 'enabled') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="ftp_enabled">
                                        Habilitar Armazenamento FTP/SFTP
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="ftp_host" class="form-label">Host</label>
                                <input type="text" class="form-control" id="ftp_host" name="ftp_host" 
                                       value="<?= h(getStorageSetting('ftp', 'host')) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="ftp_port" class="form-label">Porta</label>
                                <input type="number" class="form-control" id="ftp_port" name="ftp_port" 
                                       value="<?= h(getStorageSetting('ftp', 'port', '21')) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="ftp_username" class="form-label">Usuário</label>
                                <input type="text" class="form-control" id="ftp_username" name="ftp_username" 
                                       value="<?= h(getStorageSetting('ftp', 'username')) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="ftp_password" class="form-label">Senha</label>
                                <input type="password" class="form-control" id="ftp_password" name="ftp_password" 
                                       value="<?= h(getStorageSetting('ftp', 'password')) ?>">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="ftp_path" class="form-label">Caminho no Servidor</label>
                                <input type="text" class="form-control" id="ftp_path" name="ftp_path" 
                                       value="<?= h(getStorageSetting('ftp', 'path', '/')) ?>">
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="las la-save me-1"></i> Salvar Configurações
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

