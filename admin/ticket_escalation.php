<?php
declare(strict_types=1);
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Escalonamento dos Tickets de Suporte';
$active = 'ticket_escalation';
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
            $priority = $_POST['priority'] ?? 'medium';
            $department = trim($_POST['department'] ?? '');
            $status = trim($_POST['status'] ?? '');
            $hours_without_reply = intval($_POST['hours_without_reply'] ?? 24);
            $action_type = $_POST['action_type'] ?? 'change_priority';
            $action_value = trim($_POST['action_value'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $sort_order = intval($_POST['sort_order'] ?? 0);
            
            if (empty($name)) {
                throw new Exception('Nome da regra é obrigatório.');
            }
            
            if ($id) {
                $stmt = db()->prepare("UPDATE ticket_escalation_rules SET name = ?, priority = ?, department = ?, status = ?, hours_without_reply = ?, action = ?, action_value = ?, is_active = ?, sort_order = ? WHERE id = ?");
                $stmt->execute([$name, $priority, $department ?: null, $status ?: null, $hours_without_reply, $action_type, $action_value ?: null, $is_active, $sort_order, $id]);
            } else {
                $stmt = db()->prepare("INSERT INTO ticket_escalation_rules (name, priority, department, status, hours_without_reply, action, action_value, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $priority, $department ?: null, $status ?: null, $hours_without_reply, $action_type, $action_value ?: null, $is_active, $sort_order]);
            }
            
            $_SESSION['success'] = 'Regra de escalonamento salva com sucesso.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            $stmt = db()->prepare("DELETE FROM ticket_escalation_rules WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = 'Regra excluída com sucesso.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Buscar regras
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = db()->query("SELECT * FROM ticket_escalation_rules ORDER BY sort_order, name");
    $rules = $stmt->fetchAll();
} catch (Throwable $e) {
    $rules = [];
}

// Buscar departamentos para select
try {
    $stmt = db()->query("SELECT id, name FROM support_departments WHERE is_active = 1 ORDER BY name");
    $departments = $stmt->fetchAll();
} catch (Throwable $e) {
    $departments = [];
}

// Buscar admins para select
try {
    $stmt = db()->query("SELECT id, username, name FROM admin_users WHERE is_active = 1 ORDER BY username");
    $admins = $stmt->fetchAll();
} catch (Throwable $e) {
    $admins = [];
}

$editingRule = null;
if (isset($_GET['edit'])) {
    foreach ($rules as $rule) {
        if ($rule['id'] == $_GET['edit']) {
            $editingRule = $rule;
            break;
        }
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Escalonamento dos Tickets de Suporte</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ruleModal">
            <i class="las la-plus me-1"></i> Nova Regra
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
                            <th>Prioridade</th>
                            <th>Departamento</th>
                            <th>Horas sem Resposta</th>
                            <th>Ação</th>
                            <th>Status</th>
                            <th>Ordem</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rules)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">Nenhuma regra cadastrada</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rules as $rule): ?>
                                <tr>
                                    <td><?= h($rule['name']) ?></td>
                                    <td>
                                        <?php
                                        $priorities = ['low' => 'Baixa', 'medium' => 'Média', 'high' => 'Alta', 'urgent' => 'Urgente'];
                                        $priorityBadges = ['low' => 'bg-info', 'medium' => 'bg-warning', 'high' => 'bg-danger', 'urgent' => 'bg-dark'];
                                        $badgeClass = $priorityBadges[$rule['priority']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= h($priorities[$rule['priority']] ?? $rule['priority']) ?></span>
                                    </td>
                                    <td><?= h($rule['department'] ?: '-') ?></td>
                                    <td><?= $rule['hours_without_reply'] ?> horas</td>
                                    <td>
                                        <?php
                                        $actions = [
                                            'change_priority' => 'Alterar Prioridade',
                                            'assign_admin' => 'Atribuir Admin',
                                            'close_ticket' => 'Fechar Ticket',
                                            'send_notification' => 'Enviar Notificação'
                                        ];
                                        echo h($actions[$rule['action']] ?? $rule['action']);
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($rule['is_active']): ?>
                                            <span class="badge bg-success">Ativa</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inativa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $rule['sort_order'] ?></td>
                                    <td>
                                        <a href="?edit=<?= $rule['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="las la-edit"></i> Editar
                                        </a>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir esta regra?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $rule['id'] ?>">
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

<!-- Modal para Nova/Editar Regra -->
<div class="modal fade" id="ruleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $editingRule['id'] ?? '' ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title"><?= $editingRule ? 'Editar' : 'Nova' ?> Regra de Escalonamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome da Regra *</label>
                        <input type="text" class="form-control" name="name" required value="<?= h($editingRule['name'] ?? '') ?>">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Prioridade</label>
                            <select class="form-select" name="priority">
                                <option value="low" <?= ($editingRule['priority'] ?? 'medium') === 'low' ? 'selected' : '' ?>>Baixa</option>
                                <option value="medium" <?= ($editingRule['priority'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Média</option>
                                <option value="high" <?= ($editingRule['priority'] ?? 'medium') === 'high' ? 'selected' : '' ?>>Alta</option>
                                <option value="urgent" <?= ($editingRule['priority'] ?? 'medium') === 'urgent' ? 'selected' : '' ?>>Urgente</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Departamento</label>
                            <select class="form-select" name="department">
                                <option value="">Todos</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>" <?= ($editingRule['department'] ?? '') == $dept['id'] ? 'selected' : '' ?>>
                                        <?= h($dept['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status do Ticket</label>
                            <select class="form-select" name="status">
                                <option value="">Todos</option>
                                <option value="open" <?= ($editingRule['status'] ?? '') === 'open' ? 'selected' : '' ?>>Aberto</option>
                                <option value="answered" <?= ($editingRule['status'] ?? '') === 'answered' ? 'selected' : '' ?>>Respondido</option>
                                <option value="closed" <?= ($editingRule['status'] ?? '') === 'closed' ? 'selected' : '' ?>>Fechado</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Horas sem Resposta *</label>
                            <input type="number" class="form-control" name="hours_without_reply" min="1" required value="<?= h($editingRule['hours_without_reply'] ?? '24') ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ação *</label>
                            <select class="form-select" name="action_type" id="actionType">
                                <option value="change_priority" <?= ($editingRule['action'] ?? 'change_priority') === 'change_priority' ? 'selected' : '' ?>>Alterar Prioridade</option>
                                <option value="assign_admin" <?= ($editingRule['action'] ?? '') === 'assign_admin' ? 'selected' : '' ?>>Atribuir Administrador</option>
                                <option value="close_ticket" <?= ($editingRule['action'] ?? '') === 'close_ticket' ? 'selected' : '' ?>>Fechar Ticket</option>
                                <option value="send_notification" <?= ($editingRule['action'] ?? '') === 'send_notification' ? 'selected' : '' ?>>Enviar Notificação</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3" id="actionValueContainer">
                            <label class="form-label" id="actionValueLabel">Valor da Ação</label>
                            <select class="form-select" name="action_value" id="actionValue">
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ordem de Execução</label>
                            <input type="number" class="form-control" name="sort_order" value="<?= h($editingRule['sort_order'] ?? '0') ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= ($editingRule['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label">Regra ativa</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Regra</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var admins = <?= json_encode($admins) ?>;
var actionType = document.getElementById('actionType');
var actionValue = document.getElementById('actionValue');
var actionValueLabel = document.getElementById('actionValueLabel');
var actionValueContainer = document.getElementById('actionValueContainer');

function updateActionValue() {
    var type = actionType.value;
    actionValue.innerHTML = '<option value="">Selecione...</option>';
    
    if (type === 'assign_admin') {
        actionValueLabel.textContent = 'Administrador';
        actionValueContainer.style.display = 'block';
        admins.forEach(function(admin) {
            var option = document.createElement('option');
            option.value = admin.id;
            option.textContent = (admin.name || admin.username);
            if ('<?= $editingRule['action_value'] ?? '' ?>' == admin.id) {
                option.selected = true;
            }
            actionValue.appendChild(option);
        });
    } else if (type === 'change_priority') {
        actionValueLabel.textContent = 'Nova Prioridade';
        actionValueContainer.style.display = 'block';
        var priorities = [
            {value: 'low', label: 'Baixa'},
            {value: 'medium', label: 'Média'},
            {value: 'high', label: 'Alta'},
            {value: 'urgent', label: 'Urgente'}
        ];
        priorities.forEach(function(p) {
            var option = document.createElement('option');
            option.value = p.value;
            option.textContent = p.label;
            if ('<?= $editingRule['action_value'] ?? '' ?>' === p.value) {
                option.selected = true;
            }
            actionValue.appendChild(option);
        });
    } else {
        actionValueContainer.style.display = 'none';
    }
}

actionType.addEventListener('change', updateActionValue);
updateActionValue();
</script>

<?php if ($editingRule): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = new bootstrap.Modal(document.getElementById('ruleModal'));
            modal.show();
        });
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

