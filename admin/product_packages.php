<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Pacotes de Produtos';
$active = 'product_packages';
require_once __DIR__ . '/partials/layout_start.php';

// Processar exclusão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_package'])) {
    csrf_verify($_POST['_csrf'] ?? null);
    $id = (int)$_POST['id'];
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->prepare("DELETE FROM product_packages WHERE id = ?")->execute([$id]);
        $_SESSION['success'] = 'Pacote excluído com sucesso.';
    } catch (Throwable $e) {
        $_SESSION['error'] = 'Erro ao excluir pacote.';
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Buscar pacotes
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $stmt = db()->query("SELECT pp.*, 
                                COUNT(ppi.id) as item_count,
                                GROUP_CONCAT(p.name SEPARATOR ', ') as plan_names
                         FROM product_packages pp
                         LEFT JOIN product_package_items ppi ON pp.id = ppi.package_id
                         LEFT JOIN plans p ON ppi.plan_id = p.id
                         GROUP BY pp.id
                         ORDER BY pp.sort_order ASC, pp.created_at DESC");
    $packages = $stmt->fetchAll();
} catch (Throwable $e) {
    $packages = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Pacotes de Produtos</h1>
        <a href="/admin/product_package_edit.php" class="btn btn-primary">
            <i class="las la-plus me-1"></i> Novo Pacote
        </a>
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

    <div class="alert alert-info">
        <i class="las la-info-circle me-2"></i>
        <strong>Pacotes de Produtos</strong> permitem criar ofertas especiais que envolvem 2 ou mais produtos. 
        Quando o usuário compra todos os itens juntos, ele recebe um desconto. 
        Você também pode gerar um link que adiciona automaticamente todos os itens ao carrinho.
    </div>

    <?php if (empty($packages)): ?>
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="las la-box text-muted" style="font-size: 4rem;"></i>
                <p class="text-muted mt-3 mb-0">Nenhum pacote cadastrado.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($packages as $package): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card shadow-sm h-100 <?= !$package['is_active'] ? 'opacity-75' : '' ?>">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><?= h($package['name']) ?></h6>
                            <?php if (!$package['is_active']): ?>
                                <span class="badge bg-secondary">Inativo</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if ($package['description']): ?>
                                <p class="card-text small"><?= nl2br(h($package['description'])) ?></p>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <strong>Desconto:</strong>
                                <?php if ($package['discount_type'] === 'percentage'): ?>
                                    <span class="badge bg-success"><?= number_format((float)$package['discount_value'], 0) ?>%</span>
                                <?php else: ?>
                                    <span class="badge bg-success">R$ <?= number_format((float)$package['discount_value'], 2, ',', '.') ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Preço Total:</strong> 
                                <span class="text-success">R$ <?= number_format((float)$package['total_price'], 2, ',', '.') ?></span>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Itens no Pacote:</strong> <?= number_format((int)$package['item_count']) ?>
                                <?php if ($package['plan_names']): ?>
                                    <br><small class="text-muted"><?= h($package['plan_names']) ?></small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Link do Pacote:</strong>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" 
                                           value="<?= h($_SERVER['HTTP_HOST'] . '/package/' . $package['slug']) ?>" 
                                           readonly id="link-<?= $package['id'] ?>">
                                    <button class="btn btn-outline-secondary" type="button" 
                                            onclick="copyLink(<?= $package['id'] ?>)">
                                        <i class="las la-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-light d-flex justify-content-between">
                            <a href="/admin/product_package_edit.php?id=<?= $package['id'] ?>" class="btn btn-sm btn-primary">
                                <i class="las la-edit"></i> Editar
                            </a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Excluir este pacote?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="delete_package" value="1">
                                <input type="hidden" name="id" value="<?= $package['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="las la-trash"></i> Excluir
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function copyLink(id) {
    const input = document.getElementById('link-' + id);
    input.select();
    document.execCommand('copy');
    alert('Link copiado para a área de transferência!');
}
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

