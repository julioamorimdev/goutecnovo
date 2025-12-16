<?php
declare(strict_types=1);
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Funções Administrativas';
$active = 'admin_roles';
require_once __DIR__ . '/partials/layout_start.php';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $action = $_POST['action'] ?? '';
        
        if ($action === 'save') {
            $id = $_POST['id'] ?? null;
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $permissions = [];
            
            // Coletar permissões do formulário
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'perm_') === 0) {
                    $permKey = str_replace('perm_', '', $key);
                    $permissions[$permKey] = true;
                }
            }
            
            // Se nenhuma permissão específica, usar array vazio
            if (empty($permissions)) {
                $permissions = [];
            }
            
            $permissionsJson = json_encode($permissions);
            
            if (empty($name)) {
                throw new Exception('Nome da função é obrigatório.');
            }
            
            if ($id) {
                // Verificar se é função do sistema
                $stmt = db()->prepare("SELECT is_system FROM admin_roles WHERE id = ?");
                $stmt->execute([$id]);
                $existing = $stmt->fetch();
                if ($existing && $existing['is_system']) {
                    throw new Exception('Não é possível editar funções do sistema.');
                }
                
                $stmt = db()->prepare("UPDATE admin_roles SET name = ?, description = ?, permissions = ? WHERE id = ?");
                $stmt->execute([$name, $description ?: null, $permissionsJson, $id]);
            } else {
                // Verificar se nome já existe
                $stmt = db()->prepare("SELECT id FROM admin_roles WHERE name = ?");
                $stmt->execute([$name]);
                if ($stmt->fetch()) {
                    throw new Exception('Já existe uma função com este nome.');
                }
                
                $stmt = db()->prepare("INSERT INTO admin_roles (name, description, permissions) VALUES (?, ?, ?)");
                $stmt->execute([$name, $description ?: null, $permissionsJson]);
            }
            
            $_SESSION['success'] = 'Função administrativa salva com sucesso.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            
            // Verificar se é função do sistema
            $stmt = db()->prepare("SELECT is_system FROM admin_roles WHERE id = ?");
            $stmt->execute([$id]);
            $role = $stmt->fetch();
            if ($role && $role['is_system']) {
                throw new Exception('Não é possível excluir funções do sistema.');
            }
            
            $stmt = db()->prepare("DELETE FROM admin_roles WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = 'Função excluída com sucesso.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Buscar funções
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = db()->query("SELECT * FROM admin_roles ORDER BY is_system DESC, name");
    $roles = $stmt->fetchAll();
} catch (Throwable $e) {
    $roles = [];
}

$editingRole = null;
if (isset($_GET['edit'])) {
    foreach ($roles as $role) {
        if ($role['id'] == $_GET['edit']) {
            $editingRole = $role;
            break;
        }
    }
}

// Definir categorias de permissões
$permissionCategories = [
    'Geral' => [
        'dashboard' => 'Dashboard',
        'settings' => 'Configurações',
    ],
    'Conteúdo' => [
        'menu' => 'Menu',
        'footer' => 'Footer',
        'blog' => 'Blog',
        'announcements' => 'Anúncios',
        'downloads' => 'Downloads',
    ],
    'Vendas' => [
        'plans' => 'Planos',
        'clients' => 'Clientes',
        'orders' => 'Pedidos',
        'invoices' => 'Faturas',
        'products' => 'Produtos',
    ],
    'Suporte' => [
        'tickets' => 'Tickets',
        'support_departments' => 'Departamentos',
        'canned_responses' => 'Respostas Predefinidas',
        'kb' => 'Base de Conhecimento',
    ],
    'Sistema' => [
        'admins' => 'Administradores',
        'admin_roles' => 'Funções Administrativas',
        'reports' => 'Relatórios',
        'security' => 'Segurança',
    ],
];

$editingPermissions = [];
if ($editingRole) {
    $editingPermissions = json_decode($editingRole['permissions'], true) ?? [];
    // Se tem permissão total
    if (isset($editingPermissions['*']) && $editingPermissions['*']) {
        $editingPermissions = ['*' => true];
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Funções Administrativas</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#roleModal">
            <i class="las la-plus me-1"></i> Nova Função
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
                            <th>Nome</th>
                            <th>Descrição</th>
                            <th>Tipo</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($roles)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">Nenhuma função cadastrada</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($roles as $role): ?>
                                <tr>
                                    <td><strong><?= h($role['name']) ?></strong></td>
                                    <td><?= h($role['description'] ?: '-') ?></td>
                                    <td>
                                        <?php if ($role['is_system']): ?>
                                            <span class="badge bg-primary">Sistema</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Personalizada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?edit=<?= $role['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="las la-edit"></i> Editar
                                        </a>
                                        <?php if (!$role['is_system']): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir esta função?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $role['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="las la-trash"></i> Excluir
                                                </button>
                                            </form>
                                        <?php endif; ?>
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

<!-- Modal para Nova/Editar Função -->
<div class="modal fade" id="roleModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $editingRole['id'] ?? '' ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title"><?= $editingRole ? 'Editar' : 'Nova' ?> Função Administrativa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nome da Função *</label>
                            <input type="text" class="form-control" name="name" required value="<?= h($editingRole['name'] ?? '') ?>" <?= ($editingRole['is_system'] ?? 0) ? 'readonly' : '' ?>>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Descrição</label>
                            <input type="text" class="form-control" name="description" value="<?= h($editingRole['description'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <?php if ($editingRole && ($editingRole['is_system'] ?? 0)): ?>
                        <div class="alert alert-info">
                            <i class="las la-info-circle me-1"></i>
                            Esta é uma função do sistema e não pode ser editada.
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <label class="form-label">Permissões</label>
                            <div class="border rounded p-3" style="max-height: 400px; overflow-y: auto;">
                                <?php foreach ($permissionCategories as $category => $perms): ?>
                                    <div class="mb-3">
                                        <h6 class="fw-semibold"><?= h($category) ?></h6>
                                        <div class="row">
                                            <?php foreach ($perms as $key => $label): ?>
                                                <div class="col-md-4 mb-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="perm_<?= h($key) ?>" id="perm_<?= h($key) ?>" 
                                                               <?= (isset($editingPermissions[$key]) || isset($editingPermissions['*'])) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="perm_<?= h($key) ?>">
                                                            <?= h($label) ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <?php if (!($editingRole['is_system'] ?? 0)): ?>
                        <button type="submit" class="btn btn-primary">Salvar Função</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editingRole): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = new bootstrap.Modal(document.getElementById('roleModal'));
            modal.show();
        });
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

