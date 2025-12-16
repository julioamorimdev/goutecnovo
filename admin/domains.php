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
    
    if ($id > 0 && $action === 'toggle_auto_renew') {
        db()->prepare("UPDATE domain_registrations SET auto_renew = IF(auto_renew=1,0,1) WHERE id=?")->execute([$id]);
        header('Location: /admin/domains.php');
        exit;
    }
    
    if ($id > 0 && $action === 'toggle_privacy') {
        db()->prepare("UPDATE domain_registrations SET privacy_protection = IF(privacy_protection=1,0,1) WHERE id=?")->execute([$id]);
        header('Location: /admin/domains.php');
        exit;
    }
    
    if ($id > 0 && $action === 'delete') {
        db()->prepare("DELETE FROM domain_registrations WHERE id=?")->execute([$id]);
        $_SESSION['success'] = 'Registro de domínio excluído com sucesso.';
        header('Location: /admin/domains.php');
        exit;
    }
}

$page_title = 'Registros de Domínios';
$active = 'domains';
require_once __DIR__ . '/partials/layout_start.php';

// Buscar registros de domínios
try {
    // Garantir UTF-8 na conexão
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    db()->exec("SET CHARACTER SET utf8mb4");
    db()->exec("SET character_set_connection=utf8mb4");
    
    // Filtros
    $statusFilter = $_GET['status'] ?? '';
    $tldFilter = isset($_GET['tld_id']) ? (int)$_GET['tld_id'] : 0;
    $search = trim($_GET['search'] ?? '');
    
    $where = [];
    $params = [];
    
    if ($statusFilter && in_array($statusFilter, ['active', 'expired', 'suspended', 'cancelled', 'pending_transfer'], true)) {
        $where[] = "dr.status = ?";
        $params[] = $statusFilter;
    }
    
    if ($tldFilter > 0) {
        $where[] = "dr.tld_id = ?";
        $params[] = $tldFilter;
    }
    
    if ($search !== '') {
        $where[] = "(dr.domain_name LIKE ? OR dr.full_domain LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT dr.*, 
                   t.tld, t.name as tld_name,
                   c.first_name, c.last_name, c.email as client_email, c.company_name
            FROM domain_registrations dr
            LEFT JOIN tlds t ON dr.tld_id = t.id
            LEFT JOIN clients c ON dr.client_id = c.id
            {$whereClause}
            ORDER BY dr.expiration_date ASC, dr.created_at DESC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $domains = $stmt->fetchAll();
    
    // Buscar TLDs para filtro
    $tlds = db()->query("SELECT id, tld, name FROM tlds WHERE is_enabled=1 ORDER BY sort_order ASC")->fetchAll();
} catch (Throwable $e) {
    $domains = [];
    $tlds = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Registros de Domínios</h1>
        <a href="/admin/domain_edit.php" class="btn btn-primary">
            <i class="las la-plus me-1"></i> Novo Registro
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
                    <input type="text" class="form-control" id="search" name="search" value="<?= h($search) ?>" placeholder="Domínio, cliente, email...">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Ativo</option>
                        <option value="expired" <?= $statusFilter === 'expired' ? 'selected' : '' ?>>Expirado</option>
                        <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspenso</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
                        <option value="pending_transfer" <?= $statusFilter === 'pending_transfer' ? 'selected' : '' ?>>Transferência Pendente</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="tld_id" class="form-label">TLD</label>
                    <select class="form-select" id="tld_id" name="tld_id">
                        <option value="">Todos</option>
                        <?php foreach ($tlds as $tld): ?>
                            <option value="<?= (int)$tld['id'] ?>" <?= $tldFilter === (int)$tld['id'] ? 'selected' : '' ?>>
                                <?= h($tld['tld']) ?> - <?= h($tld['name']) ?>
                            </option>
                        <?php endforeach; ?>
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
            <?php if (empty($domains)): ?>
                <div class="text-center py-5">
                    <i class="las la-globe text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">Nenhum registro de domínio encontrado.</p>
                    <a href="/admin/domain_edit.php" class="btn btn-primary">
                        <i class="las la-plus me-1"></i> Criar Primeiro Registro
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Domínio</th>
                                <th>Cliente</th>
                                <th>TLD</th>
                                <th>Registro</th>
                                <th>Expiração</th>
                                <th>Anos</th>
                                <th>Status</th>
                                <th style="width: 150px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($domains as $domain): 
                                $expirationDate = new DateTime($domain['expiration_date']);
                                $now = new DateTime();
                                $isExpiringSoon = $expirationDate->diff($now)->days <= 30;
                                $isExpired = $expirationDate < $now;
                            ?>
                                <tr class="<?= $isExpired ? 'table-danger' : ($isExpiringSoon ? 'table-warning' : '') ?>">
                                    <td>
                                        <strong><?= h($domain['full_domain']) ?></strong>
                                        <?php if ((int)$domain['auto_renew'] === 1): ?>
                                            <br><small class="text-success"><i class="las la-sync"></i> Renovação automática</small>
                                        <?php endif; ?>
                                        <?php if ((int)$domain['privacy_protection'] === 1): ?>
                                            <br><small class="text-info"><i class="las la-shield-alt"></i> Proteção de privacidade</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= h($domain['first_name'] . ' ' . $domain['last_name']) ?></strong>
                                        <?php if ($domain['company_name']): ?>
                                            <br><small class="text-muted"><?= h($domain['company_name']) ?></small>
                                        <?php endif; ?>
                                        <br><small class="text-muted"><?= h($domain['client_email']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= h($domain['tld']) ?></span>
                                    </td>
                                    <td>
                                        <small><?= date('d/m/Y', strtotime($domain['registration_date'])) ?></small>
                                    </td>
                                    <td>
                                        <strong class="<?= $isExpired ? 'text-danger' : ($isExpiringSoon ? 'text-warning' : '') ?>">
                                            <?= date('d/m/Y', strtotime($domain['expiration_date'])) ?>
                                        </strong>
                                        <?php if ($isExpired): ?>
                                            <br><small class="text-danger">Expirado</small>
                                        <?php elseif ($isExpiringSoon): ?>
                                            <br><small class="text-warning">Expira em <?= $expirationDate->diff($now)->days ?> dias</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= (int)$domain['years'] ?> <?= (int)$domain['years'] === 1 ? 'ano' : 'anos' ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusBadges = [
                                            'active' => 'bg-success',
                                            'expired' => 'bg-danger',
                                            'suspended' => 'bg-warning',
                                            'cancelled' => 'bg-secondary',
                                            'pending_transfer' => 'bg-info'
                                        ];
                                        $statusLabels = [
                                            'active' => 'Ativo',
                                            'expired' => 'Expirado',
                                            'suspended' => 'Suspenso',
                                            'cancelled' => 'Cancelado',
                                            'pending_transfer' => 'Transferência Pendente'
                                        ];
                                        $status = $domain['status'] ?? 'active';
                                        ?>
                                        <span class="badge <?= $statusBadges[$status] ?? 'bg-secondary' ?>">
                                            <?= $statusLabels[$status] ?? ucfirst($status) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="/admin/domain_edit.php?id=<?= (int)$domain['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                                <i class="las la-edit"></i>
                                            </a>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle_auto_renew">
                                                <input type="hidden" name="id" value="<?= (int)$domain['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-<?= (int)$domain['auto_renew'] === 1 ? 'success' : 'secondary' ?>" title="<?= (int)$domain['auto_renew'] === 1 ? 'Desativar renovação automática' : 'Ativar renovação automática' ?>">
                                                    <i class="las la-<?= (int)$domain['auto_renew'] === 1 ? 'sync' : 'sync-alt' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este registro?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$domain['id'] ?>">
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

