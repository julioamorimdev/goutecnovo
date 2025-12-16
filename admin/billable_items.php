<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

// Processar ações ANTES do layout_start para evitar erro de headers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    
    // Garantir UTF-8 na conexão
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    db()->exec("SET CHARACTER SET utf8mb4");
    db()->exec("SET character_set_connection=utf8mb4");
    
    if ($id > 0 && $action === 'toggle') {
        db()->prepare("UPDATE billable_items SET is_enabled = IF(is_enabled=1,0,1) WHERE id=?")->execute([$id]);
        header('Location: /admin/billable_items.php');
        exit;
    }
    
    if ($id > 0 && $action === 'delete') {
        db()->prepare("DELETE FROM billable_items WHERE id=?")->execute([$id]);
        $_SESSION['success'] = 'Item faturável excluído com sucesso.';
        header('Location: /admin/billable_items.php');
        exit;
    }
}

$page_title = 'Itens Faturáveis';
$active = 'billable_items';
require_once __DIR__ . '/partials/layout_start.php';

// Buscar itens faturáveis
try {
    // Garantir UTF-8 na conexão
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    db()->exec("SET CHARACTER SET utf8mb4");
    db()->exec("SET character_set_connection=utf8mb4");
    
    // Filtros
    $categoryFilter = $_GET['category'] ?? '';
    $statusFilter = $_GET['status'] ?? '';
    $search = trim($_GET['search'] ?? '');
    
    $where = [];
    $params = [];
    
    if ($categoryFilter && in_array($categoryFilter, ['service', 'product', 'license', 'other'], true)) {
        $where[] = "category = ?";
        $params[] = $categoryFilter;
    }
    
    if ($statusFilter === 'enabled') {
        $where[] = "is_enabled = 1";
    } elseif ($statusFilter === 'disabled') {
        $where[] = "is_enabled = 0";
    }
    
    if ($search !== '') {
        $where[] = "(code LIKE ? OR name LIKE ? OR description LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT * FROM billable_items {$whereClause} ORDER BY sort_order ASC, name ASC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();
} catch (Throwable $e) {
    $items = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Itens Faturáveis</h1>
        <a href="/admin/billable_item_edit.php" class="btn btn-primary">
            <i class="las la-plus me-1"></i> Novo Item
        </a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= h($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?= h($search) ?>" placeholder="Código, nome...">
                </div>
                <div class="col-md-3">
                    <label for="category" class="form-label">Categoria</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">Todas</option>
                        <option value="service" <?= $categoryFilter === 'service' ? 'selected' : '' ?>>Serviço</option>
                        <option value="product" <?= $categoryFilter === 'product' ? 'selected' : '' ?>>Produto</option>
                        <option value="license" <?= $categoryFilter === 'license' ? 'selected' : '' ?>>Licença</option>
                        <option value="other" <?= $categoryFilter === 'other' ? 'selected' : '' ?>>Outros</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="enabled" <?= $statusFilter === 'enabled' ? 'selected' : '' ?>>Ativos</option>
                        <option value="disabled" <?= $statusFilter === 'disabled' ? 'selected' : '' ?>>Inativos</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="las la-search me-1"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($items)): ?>
                <div class="text-center py-5">
                    <i class="las la-list text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">Nenhum item faturável cadastrado ainda.</p>
                    <a href="/admin/billable_item_edit.php" class="btn btn-primary">
                        <i class="las la-plus me-1"></i> Criar Primeiro Item
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nome</th>
                                <th>Categoria</th>
                                <th>Unidade</th>
                                <th>Preço</th>
                                <th>Imposto</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th style="width: 150px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <strong class="text-primary"><?= h($item['code']) ?></strong>
                                    </td>
                                    <td>
                                        <strong><?= h($item['name']) ?></strong>
                                        <?php if ($item['description']): ?>
                                            <br><small class="text-muted"><?= h(substr($item['description'], 0, 60)) ?><?= strlen($item['description']) > 60 ? '...' : '' ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $categoryLabels = [
                                            'service' => 'Serviço',
                                            'product' => 'Produto',
                                            'license' => 'Licença',
                                            'other' => 'Outros'
                                        ];
                                        ?>
                                        <span class="badge bg-secondary"><?= $categoryLabels[$item['category']] ?? ucfirst($item['category']) ?></span>
                                    </td>
                                    <td>
                                        <small><?= h($item['unit']) ?></small>
                                    </td>
                                    <td>
                                        <strong class="text-success">R$ <?= number_format((float)$item['price'], 2, ',', '.') ?></strong>
                                    </td>
                                    <td>
                                        <?php if ((float)$item['tax_rate'] > 0): ?>
                                            <span class="badge bg-info"><?= number_format((float)$item['tax_rate'], 2, ',', '.') ?>%</span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$item['is_recurring'] === 1): ?>
                                            <span class="badge bg-warning text-dark">Recorrente</span>
                                            <?php if ($item['billing_cycle']): ?>
                                                <br><small class="text-muted"><?= h($item['billing_cycle']) ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Único</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$item['is_enabled'] === 1): ?>
                                            <span class="badge bg-success">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="/admin/billable_item_edit.php?id=<?= (int)$item['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                                <i class="las la-edit"></i>
                                            </a>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-<?= (int)$item['is_enabled'] === 1 ? 'warning' : 'success' ?>" title="<?= (int)$item['is_enabled'] === 1 ? 'Desativar' : 'Ativar' ?>">
                                                    <i class="las la-<?= (int)$item['is_enabled'] === 1 ? 'eye-slash' : 'eye' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este item?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Excluir">
                                                    <i class="las la-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

