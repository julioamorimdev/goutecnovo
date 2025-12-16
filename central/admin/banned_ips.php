<?php
declare(strict_types=1);
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

$page_title = 'IPs Banidos';
$active = 'banned_ips';
require_once __DIR__ . '/partials/layout_start.php';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            $ip_address = trim($_POST['ip_address'] ?? '');
            $reason = trim($_POST['reason'] ?? '');
            $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
            $banned_by = $_SESSION['admin_user_id'] ?? null;
            
            if (empty($ip_address) || !filter_var($ip_address, FILTER_VALIDATE_IP)) {
                throw new Exception('Endereço IP inválido.');
            }
            
            $stmt = db()->prepare("INSERT INTO banned_ips (ip_address, reason, expires_at, banned_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$ip_address, $reason ?: null, $expires_at, $banned_by]);
            
            $_SESSION['success'] = 'IP banido com sucesso.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            $stmt = db()->prepare("DELETE FROM banned_ips WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = 'IP removido da lista de banidos.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Buscar IPs banidos
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = db()->query("SELECT bi.*, au.username as banned_by_name 
                        FROM banned_ips bi 
                        LEFT JOIN admin_users au ON bi.banned_by = au.id 
                        ORDER BY bi.created_at DESC");
    $bannedIps = $stmt->fetchAll();
} catch (Throwable $e) {
    $bannedIps = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">IPs Banidos</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#banModal">
            <i class="las la-plus me-1"></i> Banir IP
        </button>
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

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Endereço IP</th>
                            <th>Motivo</th>
                            <th>Banido por</th>
                            <th>Data de Expiração</th>
                            <th>Data do Banimento</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bannedIps)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">Nenhum IP banido</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bannedIps as $ip): ?>
                                <tr>
                                    <td><code><?= h($ip['ip_address']) ?></code></td>
                                    <td><?= h($ip['reason'] ?: '-') ?></td>
                                    <td><?= h($ip['banned_by_name'] ?? 'Sistema') ?></td>
                                    <td>
                                        <?php if ($ip['expires_at']): ?>
                                            <?= date('d/m/Y H:i', strtotime($ip['expires_at'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Permanente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($ip['created_at'])) ?></td>
                                    <td>
                                        <?php
                                        if ($ip['expires_at'] && strtotime($ip['expires_at']) < time()) {
                                            echo '<span class="badge bg-secondary">Expirado</span>';
                                        } else {
                                            echo '<span class="badge bg-danger">Banido</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja remover este IP da lista de banidos?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $ip['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="las la-trash"></i> Remover
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Banir IP -->
<div class="modal fade" id="banModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                
                <div class="modal-header">
                    <h5 class="modal-title">Banir Endereço IP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Endereço IP *</label>
                        <input type="text" class="form-control" name="ip_address" required placeholder="192.168.1.1">
                        <small class="text-muted">Digite o endereço IP a ser banido.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Motivo</label>
                        <textarea class="form-control" name="reason" rows="3" placeholder="Motivo do banimento..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Data de Expiração</label>
                        <input type="datetime-local" class="form-control" name="expires_at">
                        <small class="text-muted">Deixe em branco para banimento permanente.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Banir IP</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

