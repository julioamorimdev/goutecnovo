<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Dashboard';
$active = 'dashboard';
require_once __DIR__ . '/partials/layout_start.php';

// Processar ações de atalhos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shortcut_action'])) {
    csrf_verify($_POST['_csrf'] ?? null);
    
    $action = $_POST['shortcut_action'] ?? '';
    $id = isset($_POST['shortcut_id']) ? (int)$_POST['shortcut_id'] : 0;
    
    if ($action === 'add') {
        $label = trim((string)($_POST['label'] ?? ''));
        $url = trim((string)($_POST['url'] ?? ''));
        $icon_class = trim((string)($_POST['icon_class'] ?? ''));
        
        if ($label && $url) {
            $maxSort = (int)db()->query("SELECT COALESCE(MAX(sort_order), 0) AS m FROM dashboard_shortcuts")->fetch()['m'];
            db()->prepare("INSERT INTO dashboard_shortcuts (label, url, icon_class, sort_order) VALUES (?, ?, ?, ?)")
                ->execute([$label, $url, $icon_class, $maxSort + 10]);
        }
        header('Location: /admin/dashboard.php');
        exit;
    }
    
    if ($action === 'delete' && $id > 0) {
        db()->prepare("DELETE FROM dashboard_shortcuts WHERE id=?")->execute([$id]);
        header('Location: /admin/dashboard.php');
        exit;
    }
    
    if ($action === 'toggle' && $id > 0) {
        db()->prepare("UPDATE dashboard_shortcuts SET is_enabled = IF(is_enabled=1,0,1) WHERE id=?")->execute([$id]);
        header('Location: /admin/dashboard.php');
        exit;
    }
}

// Garantir UTF-8 na conexão
db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
db()->exec("SET CHARACTER SET utf8mb4");
db()->exec("SET character_set_connection=utf8mb4");

$counts = [
    'menu_total' => (int)db()->query("SELECT COUNT(*) AS c FROM menu_items")->fetch()['c'],
    'menu_enabled' => (int)db()->query("SELECT COUNT(*) AS c FROM menu_items WHERE is_enabled=1")->fetch()['c'],
    'admins' => (int)db()->query("SELECT COUNT(*) AS c FROM admin_users WHERE is_active=1")->fetch()['c'],
    'clients_total' => (int)db()->query("SELECT COUNT(*) AS c FROM clients")->fetch()['c'],
    'clients_active' => (int)db()->query("SELECT COUNT(*) AS c FROM clients WHERE status='active'")->fetch()['c'],
    'orders_total' => (int)db()->query("SELECT COUNT(*) AS c FROM orders")->fetch()['c'],
    'orders_pending' => (int)db()->query("SELECT COUNT(*) AS c FROM orders WHERE status='pending'")->fetch()['c'],
    'orders_active' => (int)db()->query("SELECT COUNT(*) AS c FROM orders WHERE status='active'")->fetch()['c'],
    'invoices_total' => (int)db()->query("SELECT COUNT(*) AS c FROM invoices")->fetch()['c'],
    'invoices_unpaid' => (int)db()->query("SELECT COUNT(*) AS c FROM invoices WHERE status='unpaid'")->fetch()['c'],
    'invoices_paid' => (int)db()->query("SELECT COUNT(*) AS c FROM invoices WHERE status='paid'")->fetch()['c'],
    'invoices_total_unpaid' => (float)db()->query("SELECT COALESCE(SUM(total), 0) AS total FROM invoices WHERE status='unpaid'")->fetch()['total'],
    'invoices_total_paid' => (float)db()->query("SELECT COALESCE(SUM(total), 0) AS total FROM invoices WHERE status='paid'")->fetch()['total'],
];

$shortcuts = db()->query("SELECT * FROM dashboard_shortcuts ORDER BY sort_order ASC, id ASC")->fetchAll();
?>

<div class="row g-4">
    <div class="col-md-3">
        <div class="card shadow-sm rounded-3 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-body-secondary small">Itens do menu</div>
                        <div class="h3 mb-0"><?= $counts['menu_total'] ?></div>
                    </div>
                    <div class="text-primary fs-2"><i class="las la-stream"></i></div>
                </div>
                <div class="mt-3 small text-body-secondary">
                    Ativos: <b><?= $counts['menu_enabled'] ?></b>
                </div>
                <div class="mt-3">
                    <a class="btn btn-sm btn-primary" href="/admin/menu.php">Gerenciar menu</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm rounded-3 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-body-secondary small">Clientes</div>
                        <div class="h3 mb-0"><?= $counts['clients_total'] ?></div>
                    </div>
                    <div class="text-success fs-2"><i class="las la-users"></i></div>
                </div>
                <div class="mt-3 small text-body-secondary">
                    Ativos: <b><?= $counts['clients_active'] ?></b>
                </div>
                <div class="mt-3">
                    <a class="btn btn-sm btn-success" href="/admin/clients.php">Gerenciar clientes</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm rounded-3 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-body-secondary small">Pedidos</div>
                        <div class="h3 mb-0"><?= $counts['orders_total'] ?></div>
                    </div>
                    <div class="text-info fs-2"><i class="las la-shopping-cart"></i></div>
                </div>
                <div class="mt-3 small text-body-secondary">
                    Pendentes: <b class="text-warning"><?= $counts['orders_pending'] ?></b> | Ativos: <b class="text-success"><?= $counts['orders_active'] ?></b>
                </div>
                <div class="mt-3">
                    <a class="btn btn-sm btn-info" href="/admin/orders.php">Gerenciar pedidos</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm rounded-3 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-body-secondary small">Faturas</div>
                        <div class="h3 mb-0"><?= $counts['invoices_total'] ?></div>
                    </div>
                    <div class="text-warning fs-2"><i class="las la-file-invoice"></i></div>
                </div>
                <div class="mt-3 small text-body-secondary">
                    Não pagas: <b class="text-danger"><?= $counts['invoices_unpaid'] ?></b> | Pagas: <b class="text-success"><?= $counts['invoices_paid'] ?></b>
                </div>
                <div class="mt-3">
                    <a class="btn btn-sm btn-warning" href="/admin/invoices.php">Gerenciar faturas</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-2">
    <div class="col-md-6">
        <div class="card shadow-sm rounded-3">
            <div class="card-body">
                <h5 class="card-title mb-3">Resumo Financeiro</h5>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="text-body-secondary small">Total Não Pago</div>
                        <div class="h4 mb-0 text-danger">R$ <?= number_format($counts['invoices_total_unpaid'], 2, ',', '.') ?></div>
                    </div>
                    <div class="text-danger fs-1"><i class="las la-exclamation-circle"></i></div>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-body-secondary small">Total Pago</div>
                        <div class="h4 mb-0 text-success">R$ <?= number_format($counts['invoices_total_paid'], 2, ',', '.') ?></div>
                    </div>
                    <div class="text-success fs-1"><i class="las la-check-circle"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm rounded-3 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-body-secondary small">Administradores ativos</div>
                        <div class="h3 mb-0"><?= $counts['admins'] ?></div>
                    </div>
                    <div class="text-primary fs-2"><i class="las la-user-shield"></i></div>
                </div>
                <div class="mt-3">
                    <a class="btn btn-sm btn-primary" href="/admin/admins.php">Gerenciar admins</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm rounded-3 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="text-body-secondary small">Atalhos</div>
                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#shortcutsManager" aria-expanded="false">
                        <i class="las la-cog me-1"></i>Gerenciar
                    </button>
                </div>
                <div class="d-grid gap-2 mt-3" id="shortcutsList">
                    <?php foreach ($shortcuts as $sc): ?>
                        <?php if ((int)$sc['is_enabled'] === 1): ?>
                            <a class="btn btn-outline-dark" href="<?= h($sc['url']) ?>">
                                <?php if ($sc['icon_class']): ?>
                                    <i class="<?= h($sc['icon_class']) ?> me-1"></i>
                                <?php endif; ?>
                                <?= h($sc['label']) ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                
                <!-- Gerenciador de atalhos (collapse) -->
                <div class="collapse mt-4" id="shortcutsManager">
                    <hr>
                    <h6 class="mb-3">Gerenciar atalhos</h6>
                    
                    <!-- Lista de atalhos existentes -->
                    <div class="mb-3">
                        <?php foreach ($shortcuts as $sc): ?>
                            <div class="d-flex align-items-center justify-content-between mb-2 p-2 border rounded">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if ($sc['icon_class']): ?>
                                            <i class="<?= h($sc['icon_class']) ?>"></i>
                                        <?php endif; ?>
                                        <span class="fw-semibold"><?= h($sc['label']) ?></span>
                                        <span class="text-body-secondary small"><?= h($sc['url']) ?></span>
                                        <?php if ((int)$sc['is_enabled'] === 0): ?>
                                            <span class="badge bg-secondary">Desativado</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="d-flex gap-1">
                                    <form method="post" class="m-0">
                                        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="shortcut_id" value="<?= (int)$sc['id'] ?>">
                                        <button class="btn btn-sm btn-outline-secondary" name="shortcut_action" value="toggle" type="submit" title="<?= (int)$sc['is_enabled'] === 1 ? 'Desativar' : 'Ativar' ?>">
                                            <i class="las <?= (int)$sc['is_enabled'] === 1 ? 'la-eye-slash' : 'la-eye' ?>"></i>
                                        </button>
                                    </form>
                                    <form method="post" class="m-0" onsubmit="return confirm('Excluir este atalho?')">
                                        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="shortcut_id" value="<?= (int)$sc['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" name="shortcut_action" value="delete" type="submit" title="Excluir">
                                            <i class="las la-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Formulário para adicionar novo atalho -->
                    <form method="post" class="border-top pt-3">
                        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="shortcut_action" value="add">
                        <div class="mb-2">
                            <label class="form-label small">Título</label>
                            <input class="form-control form-control-sm" name="label" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">URL</label>
                            <input class="form-control form-control-sm" name="url" placeholder="ex: /admin/menu.php" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Ícone (classe Line Awesome)</label>
                            <input class="form-control form-control-sm" name="icon_class" placeholder="ex: las la-plus">
                        </div>
                        <button class="btn btn-sm btn-primary w-100" type="submit">
                            <i class="las la-plus me-1"></i>Adicionar atalho
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>


