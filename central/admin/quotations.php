<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

// Processar ações ANTES do layout_start para evitar erro de headers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    
    // Garantir UTF-8 na conexão
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    db()->exec("SET CHARACTER SET utf8mb4");
    db()->exec("SET character_set_connection=utf8mb4");
    
    if ($id > 0 && $action === 'delete') {
        db()->prepare("DELETE FROM quotations WHERE id=?")->execute([$id]);
        $_SESSION['success'] = 'Orçamento excluído com sucesso.';
        header('Location: /admin/quotations.php');
        exit;
    }
    
    if ($id > 0 && $action === 'send') {
        db()->prepare("UPDATE quotations SET status='sent', sent_at=NOW() WHERE id=?")->execute([$id]);
        $_SESSION['success'] = 'Orçamento marcado como enviado.';
        header('Location: /admin/quotations.php');
        exit;
    }
    
    if ($id > 0 && $action === 'accept') {
        db()->prepare("UPDATE quotations SET status='accepted', accepted_at=NOW() WHERE id=?")->execute([$id]);
        $_SESSION['success'] = 'Orçamento marcado como aceito.';
        header('Location: /admin/quotations.php');
        exit;
    }
}

$page_title = 'Orçamentos';
$active = 'quotations';
require_once __DIR__ . '/partials/layout_start.php';

// Buscar orçamentos
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $statusFilter = $_GET['status'] ?? '';
    $search = trim($_GET['search'] ?? '');
    
    $where = [];
    $params = [];
    
    if ($statusFilter && in_array($statusFilter, ['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted'], true)) {
        $where[] = "q.status = ?";
        $params[] = $statusFilter;
    }
    
    if ($search !== '') {
        $where[] = "(q.quotation_number LIKE ? OR q.title LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT q.*, 
                   c.first_name, c.last_name, c.email as client_email, c.company_name,
                   a.username as created_by_name
            FROM quotations q
            LEFT JOIN clients c ON q.client_id = c.id
            LEFT JOIN admin_users a ON q.created_by = a.id
            {$whereClause}
            ORDER BY 
                CASE q.status 
                    WHEN 'draft' THEN 1 
                    WHEN 'sent' THEN 2 
                    WHEN 'accepted' THEN 3 
                    ELSE 4 
                END,
                q.created_at DESC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $quotations = $stmt->fetchAll();
} catch (Throwable $e) {
    $quotations = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Orçamentos</h1>
        <a href="/admin/quotation_edit.php" class="btn btn-primary">
            <i class="las la-plus me-1"></i> Novo Orçamento
        </a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= h($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <label for="search" class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?= h($search) ?>" placeholder="Número, título, cliente...">
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Rascunho</option>
                        <option value="sent" <?= $statusFilter === 'sent' ? 'selected' : '' ?>>Enviado</option>
                        <option value="accepted" <?= $statusFilter === 'accepted' ? 'selected' : '' ?>>Aceito</option>
                        <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejeitado</option>
                        <option value="expired" <?= $statusFilter === 'expired' ? 'selected' : '' ?>>Expirado</option>
                        <option value="converted" <?= $statusFilter === 'converted' ? 'selected' : '' ?>>Convertido</option>
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
            <?php if (empty($quotations)): ?>
                <div class="text-center py-5">
                    <i class="las la-file-invoice-dollar text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">Nenhum orçamento encontrado.</p>
                    <a href="/admin/quotation_edit.php" class="btn btn-primary">
                        <i class="las la-plus me-1"></i> Criar Primeiro Orçamento
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Cliente</th>
                                <th>Título</th>
                                <th>Total</th>
                                <th>Válido Até</th>
                                <th>Status</th>
                                <th style="width: 200px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quotations as $quote): 
                                $isExpired = $quote['valid_until'] && strtotime($quote['valid_until']) < time() && $quote['status'] !== 'converted';
                            ?>
                                <tr class="<?= $isExpired ? 'table-warning' : '' ?>">
                                    <td>
                                        <strong class="text-primary"><?= h($quote['quotation_number']) ?></strong>
                                    </td>
                                    <td>
                                        <strong><?= h($quote['first_name'] . ' ' . $quote['last_name']) ?></strong>
                                        <?php if ($quote['company_name']): ?>
                                            <br><small class="text-muted"><?= h($quote['company_name']) ?></small>
                                        <?php endif; ?>
                                        <br><small class="text-muted"><?= h($quote['client_email']) ?></small>
                                    </td>
                                    <td>
                                        <?= h($quote['title'] ?: 'Sem título') ?>
                                    </td>
                                    <td>
                                        <strong class="text-success">R$ <?= number_format((float)$quote['total'], 2, ',', '.') ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($quote['valid_until']): ?>
                                            <small class="<?= $isExpired ? 'text-danger' : '' ?>">
                                                <?= date('d/m/Y', strtotime($quote['valid_until'])) ?>
                                                <?php if ($isExpired): ?>
                                                    <br><span class="badge bg-danger">Expirado</span>
                                                <?php endif; ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusBadges = [
                                            'draft' => 'bg-secondary',
                                            'sent' => 'bg-info',
                                            'accepted' => 'bg-success',
                                            'rejected' => 'bg-danger',
                                            'expired' => 'bg-warning',
                                            'converted' => 'bg-primary'
                                        ];
                                        $statusLabels = [
                                            'draft' => 'Rascunho',
                                            'sent' => 'Enviado',
                                            'accepted' => 'Aceito',
                                            'rejected' => 'Rejeitado',
                                            'expired' => 'Expirado',
                                            'converted' => 'Convertido'
                                        ];
                                        $status = $quote['status'] ?? 'draft';
                                        ?>
                                        <span class="badge <?= $statusBadges[$status] ?? 'bg-secondary' ?>">
                                            <?= $statusLabels[$status] ?? ucfirst($status) ?>
                                        </span>
                                        <?php if ($quote['converted_to_order_id']): ?>
                                            <br><small class="text-muted">Pedido #<?= (int)$quote['converted_to_order_id'] ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="/admin/quotation_edit.php?id=<?= (int)$quote['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                                <i class="las la-edit"></i>
                                            </a>
                                            <?php if ($quote['status'] === 'draft'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Marcar como enviado?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="send">
                                                    <input type="hidden" name="id" value="<?= (int)$quote['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-info" title="Marcar como Enviado">
                                                        <i class="las la-paper-plane"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($quote['status'] === 'sent'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Marcar como aceito?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="accept">
                                                    <input type="hidden" name="id" value="<?= (int)$quote['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" title="Marcar como Aceito">
                                                        <i class="las la-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este orçamento?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$quote['id'] ?>">
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

