<?php
declare(strict_types=1);
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Dados da Licença';
$active = 'license_data';
require_once __DIR__ . '/partials/layout_start.php';

// Processar salvamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->beginTransaction();
        
        $license_key = trim($_POST['license_key'] ?? '');
        $license_type = $_POST['license_type'] ?? 'trial';
        $max_admins = !empty($_POST['max_admins']) ? intval($_POST['max_admins']) : null;
        $max_clients = !empty($_POST['max_clients']) ? intval($_POST['max_clients']) : null;
        $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        $status = $_POST['status'] ?? 'active';
        
        if (empty($license_key)) {
            throw new Exception('Chave da licença é obrigatória.');
        }
        
        // Verificar se já existe
        $stmt = db()->prepare("SELECT id FROM license_data LIMIT 1");
        $stmt->execute();
        $existing = $stmt->fetch();
        
        if ($existing) {
            $stmt = db()->prepare("UPDATE license_data SET license_key = ?, license_type = ?, max_admins = ?, max_clients = ?, expires_at = ?, status = ?, last_validation = NOW() WHERE id = ?");
            $stmt->execute([$license_key, $license_type, $max_admins, $max_clients, $expires_at, $status, $existing['id']]);
        } else {
            $stmt = db()->prepare("INSERT INTO license_data (license_key, license_type, max_admins, max_clients, expires_at, status, last_validation) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$license_key, $license_type, $max_admins, $max_clients, $expires_at, $status]);
        }
        
        db()->commit();
        $_SESSION['success'] = 'Dados da licença salvos com sucesso.';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } catch (Throwable $e) {
        db()->rollBack();
        $_SESSION['error'] = 'Erro ao salvar licença: ' . $e->getMessage();
    }
}

// Buscar dados da licença
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = db()->query("SELECT * FROM license_data LIMIT 1");
    $license = $stmt->fetch();
} catch (Throwable $e) {
    $license = null;
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Dados da Licença</h1>
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

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Informações da Licença</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrf_field() ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Chave da Licença *</label>
                            <input type="text" class="form-control" name="license_key" required value="<?= h($license['license_key'] ?? '') ?>">
                            <small class="text-muted">Digite a chave da licença fornecida.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo de Licença</label>
                            <select class="form-select" name="license_type">
                                <option value="trial" <?= ($license['license_type'] ?? 'trial') === 'trial' ? 'selected' : '' ?>>Trial</option>
                                <option value="standard" <?= ($license['license_type'] ?? '') === 'standard' ? 'selected' : '' ?>>Standard</option>
                                <option value="professional" <?= ($license['license_type'] ?? '') === 'professional' ? 'selected' : '' ?>>Professional</option>
                                <option value="enterprise" <?= ($license['license_type'] ?? '') === 'enterprise' ? 'selected' : '' ?>>Enterprise</option>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Máximo de Administradores</label>
                                <input type="number" class="form-control" name="max_admins" min="0" value="<?= h($license['max_admins'] ?? '') ?>">
                                <small class="text-muted">Deixe em branco para ilimitado.</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Máximo de Clientes</label>
                                <input type="number" class="form-control" name="max_clients" min="0" value="<?= h($license['max_clients'] ?? '') ?>">
                                <small class="text-muted">Deixe em branco para ilimitado.</small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Data de Expiração</label>
                                <input type="datetime-local" class="form-control" name="expires_at" value="<?= $license['expires_at'] ? date('Y-m-d\TH:i', strtotime($license['expires_at'])) : '' ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="active" <?= ($license['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Ativa</option>
                                    <option value="expired" <?= ($license['status'] ?? '') === 'expired' ? 'selected' : '' ?>>Expirada</option>
                                    <option value="suspended" <?= ($license['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspensa</option>
                                </select>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Salvar Licença</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Status da Licença</h5>
                </div>
                <div class="card-body">
                    <?php if ($license): ?>
                        <div class="mb-3">
                            <strong>Tipo:</strong><br>
                            <span class="badge bg-primary"><?= h(ucfirst($license['license_type'])) ?></span>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Status:</strong><br>
                            <?php
                            $statusBadges = [
                                'active' => 'bg-success',
                                'expired' => 'bg-danger',
                                'suspended' => 'bg-warning'
                            ];
                            $badgeClass = $statusBadges[$license['status']] ?? 'bg-secondary';
                            ?>
                            <span class="badge <?= $badgeClass ?>"><?= h(ucfirst($license['status'])) ?></span>
                        </div>
                        
                        <?php if ($license['expires_at']): ?>
                            <div class="mb-3">
                                <strong>Expira em:</strong><br>
                                <span class="text-muted"><?= date('d/m/Y H:i', strtotime($license['expires_at'])) ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($license['last_validation']): ?>
                            <div class="mb-3">
                                <strong>Última Validação:</strong><br>
                                <span class="text-muted"><?= date('d/m/Y H:i', strtotime($license['last_validation'])) ?></span>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">Nenhuma licença configurada.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

