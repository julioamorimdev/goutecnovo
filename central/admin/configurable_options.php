<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

$page_title = 'Opções Configuráveis';
$active = 'configurable_options';
require_once __DIR__ . '/partials/layout_start.php';

$groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

if ($groupId === 0) {
    $_SESSION['error'] = 'Grupo não especificado.';
    header('Location: /admin/configurable_option_groups.php');
    exit;
}

// Buscar grupo
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = db()->prepare("SELECT * FROM configurable_option_groups WHERE id = ?");
    $stmt->execute([$groupId]);
    $group = $stmt->fetch();
    
    if (!$group) {
        $_SESSION['error'] = 'Grupo não encontrado.';
        header('Location: /admin/configurable_option_groups.php');
        exit;
    }
    
    // Buscar opções do grupo
    $stmt = db()->prepare("SELECT co.*, 
                                  COUNT(cov.id) as value_count
                           FROM configurable_options co
                           LEFT JOIN configurable_option_values cov ON co.id = cov.option_id
                           WHERE co.group_id = ?
                           GROUP BY co.id
                           ORDER BY co.sort_order ASC, co.name ASC");
    $stmt->execute([$groupId]);
    $options = $stmt->fetchAll();
} catch (Throwable $e) {
    $group = null;
    $options = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Opções Configuráveis - <?= h($group['name'] ?? '') ?></h1>
        <div>
            <a href="/admin/configurable_option_groups.php" class="btn btn-secondary me-2">
                <i class="las la-arrow-left me-1"></i> Voltar
            </a>
            <a href="/admin/configurable_option_edit.php?group_id=<?= $groupId ?>" class="btn btn-primary">
                <i class="las la-plus me-1"></i> Nova Opção
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (empty($options)): ?>
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="las la-list-ul text-muted" style="font-size: 4rem;"></i>
                <p class="text-muted mt-3 mb-0">Nenhuma opção cadastrada neste grupo.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Tipo</th>
                                <th>Valores</th>
                                <th>Obrigatório</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($options as $option): 
                                $typeLabels = [
                                    'dropdown' => 'Dropdown',
                                    'radio' => 'Radio',
                                    'checkbox' => 'Checkbox',
                                    'text' => 'Texto',
                                    'textarea' => 'Área de Texto',
                                    'quantity' => 'Quantidade'
                                ];
                            ?>
                                <tr>
                                    <td>
                                        <strong><?= h($option['name']) ?></strong>
                                        <?php if ($option['description']): ?>
                                            <br><small class="text-muted"><?= h($option['description']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $typeLabels[$option['option_type']] ?? ucfirst($option['option_type']) ?></td>
                                    <td>
                                        <span class="badge bg-info"><?= number_format((int)$option['value_count']) ?> valores</span>
                                    </td>
                                    <td>
                                        <?php if ($option['is_required']): ?>
                                            <span class="badge bg-warning">Sim</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Não</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="/admin/configurable_option_edit.php?id=<?= $option['id'] ?>&group_id=<?= $groupId ?>" class="btn btn-sm btn-primary">
                                            <i class="las la-edit"></i> Editar
                                        </a>
                                        <a href="/admin/configurable_option_values.php?option_id=<?= $option['id'] ?>" class="btn btn-sm btn-info">
                                            <i class="las la-list"></i> Valores
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

