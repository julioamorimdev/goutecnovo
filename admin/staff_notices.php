<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Quadro de Avisos da Equipe';
$active = 'staff_notices';
require_once __DIR__ . '/partials/layout_start.php';

$adminId = (int)($_SESSION['admin_user_id'] ?? 0);

// Filtros
$filter = $_GET['filter'] ?? 'all'; // all, active, expired, my_notices
$priorityFilter = $_GET['priority'] ?? 'all';

// Buscar avisos
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $where = [];
    $params = [];
    
    if ($filter === 'active') {
        $where[] = "(expires_at IS NULL OR expires_at > NOW())";
    } elseif ($filter === 'expired') {
        $where[] = "expires_at IS NOT NULL AND expires_at <= NOW()";
    } elseif ($filter === 'my_notices') {
        $where[] = "admin_id = ?";
        $params[] = $adminId;
    }
    
    if ($priorityFilter !== 'all') {
        $where[] = "priority = ?";
        $params[] = $priorityFilter;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "SELECT sn.*, 
                   a.username as author_username,
                   (SELECT COUNT(*) FROM staff_notice_views snv WHERE snv.notice_id = sn.id) as total_views,
                   (SELECT COUNT(*) FROM staff_notice_views snv WHERE snv.notice_id = sn.id AND snv.admin_id = ?) as viewed_by_me
            FROM staff_notices sn
            LEFT JOIN admin_users a ON sn.admin_id = a.id
            {$whereClause}
            ORDER BY sn.is_pinned DESC, sn.created_at DESC";
    
    $params[] = $adminId; // Para viewed_by_me
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $allNotices = $stmt->fetchAll();
    
    // Filtrar avisos: apenas públicos ou direcionados ao usuário atual
    $notices = [];
    foreach ($allNotices as $notice) {
        if ($notice['is_public']) {
            $notices[] = $notice;
        } else {
            $targetIds = json_decode($notice['target_admin_ids'] ?? '[]', true);
            if (is_array($targetIds) && in_array($adminId, $targetIds)) {
                $notices[] = $notice;
            }
        }
    }
    
    // Marcar avisos como visualizados
    foreach ($notices as $notice) {
        if (!$notice['viewed_by_me'] && ($filter === 'all' || $filter === 'active')) {
            try {
                $stmt = db()->prepare("INSERT IGNORE INTO staff_notice_views (notice_id, admin_id) VALUES (?, ?)");
                $stmt->execute([$notice['id'], $adminId]);
                
                // Atualizar contador
                db()->prepare("UPDATE staff_notices SET views_count = views_count + 1 WHERE id = ?")->execute([$notice['id']]);
            } catch (Throwable $e) {
                // Ignorar erros de visualização
            }
        }
    }
    
} catch (Throwable $e) {
    $notices = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Quadro de Avisos da Equipe</h1>
        <a href="/admin/staff_notice_edit.php" class="btn btn-primary">
            <i class="las la-plus me-1"></i> Novo Aviso
        </a>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="filter" class="form-label">Filtro</label>
                    <select class="form-select" id="filter" name="filter" onchange="this.form.submit()">
                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Todos os Avisos</option>
                        <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Avisos Ativos</option>
                        <option value="expired" <?= $filter === 'expired' ? 'selected' : '' ?>>Avisos Expirados</option>
                        <option value="my_notices" <?= $filter === 'my_notices' ? 'selected' : '' ?>>Meus Avisos</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="priority" class="form-label">Prioridade</label>
                    <select class="form-select" id="priority" name="priority" onchange="this.form.submit()">
                        <option value="all" <?= $priorityFilter === 'all' ? 'selected' : '' ?>>Todas</option>
                        <option value="low" <?= $priorityFilter === 'low' ? 'selected' : '' ?>>Baixa</option>
                        <option value="normal" <?= $priorityFilter === 'normal' ? 'selected' : '' ?>>Normal</option>
                        <option value="high" <?= $priorityFilter === 'high' ? 'selected' : '' ?>>Alta</option>
                        <option value="urgent" <?= $priorityFilter === 'urgent' ? 'selected' : '' ?>>Urgente</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <a href="/admin/staff_notices.php" class="btn btn-secondary w-100">
                        <i class="las la-redo me-1"></i> Limpar Filtros
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Avisos -->
    <?php if (empty($notices)): ?>
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="las la-clipboard-list text-muted" style="font-size: 4rem;"></i>
                <p class="text-muted mt-3 mb-0">Nenhum aviso encontrado.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($notices as $notice): 
                $priorityBadges = [
                    'low' => ['label' => 'Baixa', 'class' => 'secondary'],
                    'normal' => ['label' => 'Normal', 'class' => 'info'],
                    'high' => ['label' => 'Alta', 'class' => 'warning'],
                    'urgent' => ['label' => 'Urgente', 'class' => 'danger']
                ];
                $priorityInfo = $priorityBadges[$notice['priority']] ?? ['label' => ucfirst($notice['priority']), 'class' => 'secondary'];
                $isExpired = $notice['expires_at'] && strtotime($notice['expires_at']) < time();
                $isPinned = (bool)$notice['is_pinned'];
            ?>
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm h-100 <?= $isPinned ? 'border-warning' : '' ?> <?= $isExpired ? 'opacity-75' : '' ?>">
                        <?php if ($isPinned): ?>
                            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                                <span><i class="las la-thumbtack me-2"></i> <strong>FIXADO</strong></span>
                                <a href="/admin/staff_notice_edit.php?id=<?= $notice['id'] ?>" class="btn btn-sm btn-dark">
                                    <i class="las la-edit"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <span class="text-muted small">
                                    Por: <strong><?= h($notice['author_username']) ?></strong>
                                    em <?= date('d/m/Y H:i', strtotime($notice['created_at'])) ?>
                                </span>
                                <a href="/admin/staff_notice_edit.php?id=<?= $notice['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="las la-edit"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <h5 class="card-title mb-0 flex-grow-1"><?= h($notice['title']) ?></h5>
                                <span class="badge bg-<?= $priorityInfo['class'] ?>"><?= $priorityInfo['label'] ?></span>
                                <?php if ($isExpired): ?>
                                    <span class="badge bg-secondary">Expirado</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-text mb-3">
                                <?= nl2br(h($notice['content'])) ?>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="small text-muted">
                                    <i class="las la-eye me-1"></i> <?= number_format((int)$notice['total_views']) ?> visualizações
                                    <?php if ($notice['expires_at']): ?>
                                        | <i class="las la-clock me-1"></i> 
                                        Expira em: <?= date('d/m/Y H:i', strtotime($notice['expires_at'])) ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($notice['admin_id'] === $adminId): ?>
                                    <form method="POST" action="/admin/staff_notice_edit.php" class="d-inline" onsubmit="return confirm('Excluir este aviso?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $notice['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="las la-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

