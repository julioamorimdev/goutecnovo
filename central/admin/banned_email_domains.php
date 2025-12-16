<?php
declare(strict_types=1);
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

$page_title = 'Domínios de Email Banidos';
$active = 'banned_email_domains';
require_once __DIR__ . '/partials/layout_start.php';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            $domain = trim($_POST['domain'] ?? '');
            $reason = trim($_POST['reason'] ?? '');
            $banned_by = $_SESSION['admin_user_id'] ?? null;
            
            if (empty($domain)) {
                throw new Exception('Domínio é obrigatório.');
            }
            
            // Remover @ se presente
            $domain = str_replace('@', '', $domain);
            
            // Validar formato básico
            if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/', $domain)) {
                throw new Exception('Formato de domínio inválido.');
            }
            
            $stmt = db()->prepare("INSERT INTO banned_email_domains (domain, reason, banned_by) VALUES (?, ?, ?)");
            $stmt->execute([$domain, $reason ?: null, $banned_by]);
            
            $_SESSION['success'] = 'Domínio banido com sucesso.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            $stmt = db()->prepare("DELETE FROM banned_email_domains WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = 'Domínio removido da lista de banidos.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Buscar domínios banidos
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = db()->query("SELECT bed.*, au.username as banned_by_name 
                        FROM banned_email_domains bed 
                        LEFT JOIN admin_users au ON bed.banned_by = au.id 
                        ORDER BY bed.created_at DESC");
    $bannedDomains = $stmt->fetchAll();
} catch (Throwable $e) {
    $bannedDomains = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Domínios de Email Banidos</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#banModal">
            <i class="las la-plus me-1"></i> Banir Domínio
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
                            <th>Domínio</th>
                            <th>Motivo</th>
                            <th>Banido por</th>
                            <th>Data do Banimento</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bannedDomains)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">Nenhum domínio banido</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bannedDomains as $domain): ?>
                                <tr>
                                    <td><code><?= h($domain['domain']) ?></code></td>
                                    <td><?= h($domain['reason'] ?: '-') ?></td>
                                    <td><?= h($domain['banned_by_name'] ?? 'Sistema') ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($domain['created_at'])) ?></td>
                                    <td>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja remover este domínio da lista de banidos?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $domain['id'] ?>">
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

<!-- Modal para Banir Domínio -->
<div class="modal fade" id="banModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                
                <div class="modal-header">
                    <h5 class="modal-title">Banir Domínio de Email</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Domínio *</label>
                        <input type="text" class="form-control" name="domain" required placeholder="exemplo.com">
                        <small class="text-muted">Digite o domínio sem @ (ex: exemplo.com)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Motivo</label>
                        <textarea class="form-control" name="reason" rows="3" placeholder="Motivo do banimento..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Banir Domínio</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

