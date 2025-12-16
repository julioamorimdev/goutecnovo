<?php
declare(strict_types=1);
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Departamentos de Suporte';
$active = 'support_departments';
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
            $email = trim($_POST['email'] ?? '');
            $auto_respond = isset($_POST['auto_respond']) ? 1 : 0;
            $auto_respond_message = trim($_POST['auto_respond_message'] ?? '');
            $import_method = $_POST['import_method'] ?? 'none';
            $pop3_host = trim($_POST['pop3_host'] ?? '');
            $pop3_port = !empty($_POST['pop3_port']) ? intval($_POST['pop3_port']) : null;
            $pop3_username = trim($_POST['pop3_username'] ?? '');
            $pop3_password = trim($_POST['pop3_password'] ?? '');
            $pop3_ssl = isset($_POST['pop3_ssl']) ? 1 : 0;
            $imap_host = trim($_POST['imap_host'] ?? '');
            $imap_port = !empty($_POST['imap_port']) ? intval($_POST['imap_port']) : null;
            $imap_username = trim($_POST['imap_username'] ?? '');
            $imap_password = trim($_POST['imap_password'] ?? '');
            $imap_ssl = isset($_POST['imap_ssl']) ? 1 : 0;
            $import_frequency = intval($_POST['import_frequency'] ?? 5);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $sort_order = intval($_POST['sort_order'] ?? 0);
            
            if (empty($name) || empty($email)) {
                throw new Exception('Nome e email são obrigatórios.');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email inválido.');
            }
            
            if ($id) {
                $stmt = db()->prepare("UPDATE support_departments SET name = ?, description = ?, email = ?, auto_respond = ?, auto_respond_message = ?, import_method = ?, pop3_host = ?, pop3_port = ?, pop3_username = ?, pop3_password = ?, pop3_ssl = ?, imap_host = ?, imap_port = ?, imap_username = ?, imap_password = ?, imap_ssl = ?, import_frequency = ?, is_active = ?, sort_order = ? WHERE id = ?");
                $stmt->execute([$name, $description ?: null, $email, $auto_respond, $auto_respond_message ?: null, $import_method, $pop3_host ?: null, $pop3_port, $pop3_username ?: null, $pop3_password ?: null, $pop3_ssl, $imap_host ?: null, $imap_port, $imap_username ?: null, $imap_password ?: null, $imap_ssl, $import_frequency, $is_active, $sort_order, $id]);
            } else {
                // Verificar se email já existe
                $stmt = db()->prepare("SELECT id FROM support_departments WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    throw new Exception('Este email já está em uso por outro departamento.');
                }
                
                $stmt = db()->prepare("INSERT INTO support_departments (name, description, email, auto_respond, auto_respond_message, import_method, pop3_host, pop3_port, pop3_username, pop3_password, pop3_ssl, imap_host, imap_port, imap_username, imap_password, imap_ssl, import_frequency, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $description ?: null, $email, $auto_respond, $auto_respond_message ?: null, $import_method, $pop3_host ?: null, $pop3_port, $pop3_username ?: null, $pop3_password ?: null, $pop3_ssl, $imap_host ?: null, $imap_port, $imap_username ?: null, $imap_password ?: null, $imap_ssl, $import_frequency, $is_active, $sort_order]);
            }
            
            $_SESSION['success'] = 'Departamento salvo com sucesso.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            $stmt = db()->prepare("DELETE FROM support_departments WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = 'Departamento excluído com sucesso.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Buscar departamentos
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = db()->query("SELECT * FROM support_departments ORDER BY sort_order, name");
    $departments = $stmt->fetchAll();
} catch (Throwable $e) {
    $departments = [];
}

$editingDept = null;
if (isset($_GET['edit'])) {
    foreach ($departments as $dept) {
        if ($dept['id'] == $_GET['edit']) {
            $editingDept = $dept;
            break;
        }
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Departamentos de Suporte</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#deptModal">
            <i class="las la-plus me-1"></i> Novo Departamento
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
                            <th>Email</th>
                            <th>Método de Importação</th>
                            <th>Status</th>
                            <th>Ordem</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($departments)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">Nenhum departamento cadastrado</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td><?= h($dept['name']) ?></td>
                                    <td><?= h($dept['email']) ?></td>
                                    <td>
                                        <?php
                                        $methods = ['none' => 'Nenhum', 'forward' => 'Redirecionamento', 'pop3' => 'POP3', 'imap' => 'IMAP'];
                                        echo h($methods[$dept['import_method']] ?? $dept['import_method']);
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($dept['is_active']): ?>
                                            <span class="badge bg-success">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $dept['sort_order'] ?></td>
                                    <td>
                                        <a href="?edit=<?= $dept['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="las la-edit"></i> Editar
                                        </a>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este departamento?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $dept['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="las la-trash"></i> Excluir
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

<!-- Modal para Novo/Editar Departamento -->
<div class="modal fade" id="deptModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $editingDept['id'] ?? '' ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title"><?= $editingDept ? 'Editar' : 'Novo' ?> Departamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#generalTab" type="button">Geral</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#importTab" type="button">Importação</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="generalTab">
                            <div class="mb-3">
                                <label class="form-label">Nome *</label>
                                <input type="text" class="form-control" name="name" required value="<?= h($editingDept['name'] ?? '') ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required value="<?= h($editingDept['email'] ?? '') ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Descrição</label>
                                <textarea class="form-control" name="description" rows="3"><?= h($editingDept['description'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="auto_respond" value="1" <?= ($editingDept['auto_respond'] ?? 0) ? 'checked' : '' ?>>
                                    <label class="form-check-label">Resposta Automática</label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Mensagem de Resposta Automática</label>
                                <textarea class="form-control" name="auto_respond_message" rows="4"><?= h($editingDept['auto_respond_message'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Ordem de Exibição</label>
                                    <input type="number" class="form-control" name="sort_order" value="<?= h($editingDept['sort_order'] ?? '0') ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Status</label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= ($editingDept['is_active'] ?? 1) ? 'checked' : '' ?>>
                                        <label class="form-check-label">Departamento ativo</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="tab-pane fade" id="importTab">
                            <div class="mb-3">
                                <label class="form-label">Método de Importação</label>
                                <select class="form-select" name="import_method" id="importMethod">
                                    <option value="none" <?= ($editingDept['import_method'] ?? 'none') === 'none' ? 'selected' : '' ?>>Nenhum</option>
                                    <option value="forward" <?= ($editingDept['import_method'] ?? '') === 'forward' ? 'selected' : '' ?>>Redirecionamento de Email</option>
                                    <option value="pop3" <?= ($editingDept['import_method'] ?? '') === 'pop3' ? 'selected' : '' ?>>POP3</option>
                                    <option value="imap" <?= ($editingDept['import_method'] ?? '') === 'imap' ? 'selected' : '' ?>>IMAP</option>
                                </select>
                            </div>
                            
                            <div id="pop3Settings" style="display: none;">
                                <h6 class="mt-3 mb-3">Configurações POP3</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Host POP3</label>
                                        <input type="text" class="form-control" name="pop3_host" value="<?= h($editingDept['pop3_host'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Porta POP3</label>
                                        <input type="number" class="form-control" name="pop3_port" value="<?= h($editingDept['pop3_port'] ?? '110') ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Usuário POP3</label>
                                        <input type="text" class="form-control" name="pop3_username" value="<?= h($editingDept['pop3_username'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Senha POP3</label>
                                        <input type="password" class="form-control" name="pop3_password" value="<?= h($editingDept['pop3_password'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="pop3_ssl" value="1" <?= ($editingDept['pop3_ssl'] ?? 0) ? 'checked' : '' ?>>
                                        <label class="form-check-label">Usar SSL</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="imapSettings" style="display: none;">
                                <h6 class="mt-3 mb-3">Configurações IMAP</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Host IMAP</label>
                                        <input type="text" class="form-control" name="imap_host" value="<?= h($editingDept['imap_host'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Porta IMAP</label>
                                        <input type="number" class="form-control" name="imap_port" value="<?= h($editingDept['imap_port'] ?? '143') ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Usuário IMAP</label>
                                        <input type="text" class="form-control" name="imap_username" value="<?= h($editingDept['imap_username'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Senha IMAP</label>
                                        <input type="password" class="form-control" name="imap_password" value="<?= h($editingDept['imap_password'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="imap_ssl" value="1" <?= ($editingDept['imap_ssl'] ?? 0) ? 'checked' : '' ?>>
                                        <label class="form-check-label">Usar SSL</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Frequência de Importação (minutos)</label>
                                <input type="number" class="form-control" name="import_frequency" min="1" value="<?= h($editingDept['import_frequency'] ?? '5') ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Departamento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('importMethod').addEventListener('change', function() {
    var method = this.value;
    document.getElementById('pop3Settings').style.display = method === 'pop3' ? 'block' : 'none';
    document.getElementById('imapSettings').style.display = method === 'imap' ? 'block' : 'none';
});
// Trigger on load
document.getElementById('importMethod').dispatchEvent(new Event('change'));
</script>

<?php if ($editingDept): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = new bootstrap.Modal(document.getElementById('deptModal'));
            modal.show();
        });
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

