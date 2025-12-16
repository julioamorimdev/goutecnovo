<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

$page_title = 'Grupos de Clientes';
$active = 'client_groups';
require_once __DIR__ . '/partials/layout_start.php';

// Processar exclusão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_group'])) {
    csrf_verify($_POST['_csrf'] ?? null);
    $id = (int)$_POST['id'];
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // Verificar se é o grupo padrão
        $stmt = db()->prepare("SELECT is_default FROM client_groups WHERE id = ?");
        $stmt->execute([$id]);
        $group = $stmt->fetch();
        
        if ($group && (int)$group['is_default']) {
            $_SESSION['error'] = 'Não é possível excluir o grupo padrão.';
        } else {
            // Verificar se há clientes usando este grupo
            $stmt = db()->prepare("SELECT COUNT(*) as count FROM clients WHERE group_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            
            if ($result && (int)$result['count'] > 0) {
                $_SESSION['error'] = 'Não é possível excluir o grupo. Existem clientes associados a ele.';
            } else {
                db()->prepare("DELETE FROM client_groups WHERE id = ?")->execute([$id]);
                $_SESSION['success'] = 'Grupo excluído com sucesso.';
            }
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = 'Erro ao excluir grupo: ' . $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Processar toggle de grupo padrão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_default'])) {
    csrf_verify($_POST['_csrf'] ?? null);
    $id = (int)$_POST['id'];
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // Remover padrão de todos os grupos
        db()->prepare("UPDATE client_groups SET is_default = 0")->execute();
        
        // Definir este como padrão
        db()->prepare("UPDATE client_groups SET is_default = 1 WHERE id = ?")->execute([$id]);
        
        $_SESSION['success'] = 'Grupo padrão atualizado com sucesso.';
    } catch (Throwable $e) {
        $_SESSION['error'] = 'Erro ao atualizar grupo padrão.';
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Buscar grupos
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $stmt = db()->query("SELECT cg.*, 
                                COUNT(c.id) as client_count
                         FROM client_groups cg
                         LEFT JOIN clients c ON cg.id = c.group_id
                         GROUP BY cg.id
                         ORDER BY cg.sort_order ASC, cg.name ASC");
    $groups = $stmt->fetchAll();
} catch (Throwable $e) {
    $groups = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Grupos de Clientes</h2>
            <p class="text-body-secondary mb-0">Gerencie os grupos de clientes e seus descontos</p>
        </div>
        <a href="/admin/client_group_edit.php" class="btn btn-primary">
            <i class="las la-plus me-1"></i> Novo Grupo
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

    <div class="card">
        <div class="card-body">
            <?php if (empty($groups)): ?>
                <div class="text-center py-5">
                    <i class="las la-users-cog fs-1 text-muted mb-3 d-block"></i>
                    <p class="text-muted">Nenhum grupo de clientes encontrado.</p>
                    <a href="/admin/client_group_edit.php" class="btn btn-primary">
                        <i class="las la-plus me-1"></i> Criar Primeiro Grupo
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th style="width: 50px;">Cor</th>
                                <th>Nome</th>
                                <th>Descrição</th>
                                <th class="text-center">Desconto</th>
                                <th class="text-center">Clientes</th>
                                <th class="text-center">Padrão</th>
                                <th class="text-center">Ordem</th>
                                <th class="text-end" style="width: 150px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groups as $group): ?>
                                <tr>
                                    <td>
                                        <span class="badge" style="background-color: <?= h($group['color']) ?>; width: 30px; height: 30px; display: inline-block; border-radius: 4px;" title="<?= h($group['color']) ?>"></span>
                                    </td>
                                    <td>
                                        <strong><?= h($group['name']) ?></strong>
                                    </td>
                                    <td>
                                        <span class="text-body-secondary"><?= h($group['description'] ?: '-') ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ((float)$group['discount_percentage'] > 0): ?>
                                            <span class="badge bg-success"><?= number_format((float)$group['discount_percentage'], 2, ',', '.') ?>%</span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary"><?= (int)$group['client_count'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ((int)$group['is_default']): ?>
                                            <span class="badge bg-primary">Padrão</span>
                                        <?php else: ?>
                                            <form method="post" style="display: inline;" onsubmit="return confirm('Definir este grupo como padrão?');">
                                                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                                <input type="hidden" name="id" value="<?= $group['id'] ?>">
                                                <button type="submit" name="set_default" class="btn btn-sm btn-outline-primary" title="Definir como padrão">
                                                    <i class="las la-star"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-muted"><?= (int)$group['sort_order'] ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="/admin/client_group_edit.php?id=<?= $group['id'] ?>" class="btn btn-outline-primary" title="Editar">
                                                <i class="las la-edit"></i>
                                            </a>
                                            <?php if (!(int)$group['is_default'] && (int)$group['client_count'] === 0): ?>
                                                <form method="post" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este grupo?');">
                                                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                                    <input type="hidden" name="id" value="<?= $group['id'] ?>">
                                                    <button type="submit" name="delete_group" class="btn btn-outline-danger" title="Excluir">
                                                        <i class="las la-trash"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-outline-danger" disabled title="Não pode ser excluído">
                                                    <i class="las la-trash"></i>
                                                </button>
                                            <?php endif; ?>
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

    <div class="mt-4">
        <div class="card bg-light">
            <div class="card-body">
                <h5 class="card-title"><i class="las la-info-circle me-2"></i> Sobre Grupos de Clientes</h5>
                <p class="card-text mb-2">
                    Os grupos de clientes permitem organizar seus clientes e aplicar descontos automáticos. 
                    Você pode definir um grupo padrão que será atribuído automaticamente a novos clientes.
                </p>
                <ul class="mb-0">
                    <li><strong>Desconto:</strong> Aplique um desconto percentual automático para todos os clientes do grupo</li>
                    <li><strong>Grupo Padrão:</strong> Novos clientes serão automaticamente adicionados ao grupo padrão</li>
                    <li><strong>Cor:</strong> Use cores para identificar visualmente os grupos</li>
                    <li><strong>Ordem:</strong> Defina a ordem de exibição dos grupos</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

