<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

$page_title = 'Itens a Fazer';
$active = 'todos';
require_once __DIR__ . '/partials/layout_start.php';

$adminId = (int)($_SESSION['admin_user_id'] ?? 0);

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    $action = $_POST['action'] ?? '';
    
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        if ($action === 'create' || $action === 'update') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $priority = $_POST['priority'] ?? 'medium';
            $dueDate = $_POST['due_date'] ?? null;
            
            if (empty($title)) {
                $_SESSION['error'] = 'Título é obrigatório.';
            } else {
                if ($action === 'create') {
                    $stmt = db()->prepare("INSERT INTO todo_items (admin_id, title, description, priority, due_date) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$adminId, $title, $description ?: null, $priority, $dueDate ?: null]);
                    $_SESSION['success'] = 'Tarefa criada com sucesso.';
                } else {
                    $stmt = db()->prepare("UPDATE todo_items SET title=?, description=?, priority=?, due_date=? WHERE id=? AND admin_id=?");
                    $stmt->execute([$title, $description ?: null, $priority, $dueDate ?: null, $id, $adminId]);
                    $_SESSION['success'] = 'Tarefa atualizada com sucesso.';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $stmt = db()->prepare("DELETE FROM todo_items WHERE id=? AND admin_id=?");
            $stmt->execute([$id, $adminId]);
            $_SESSION['success'] = 'Tarefa excluída com sucesso.';
        } elseif ($action === 'update_status') {
            $id = (int)$_POST['id'];
            $status = $_POST['status'] ?? 'pending';
            $completedAt = ($status === 'completed') ? date('Y-m-d H:i:s') : null;
            
            $stmt = db()->prepare("UPDATE todo_items SET status=?, completed_at=? WHERE id=? AND admin_id=?");
            $stmt->execute([$status, $completedAt, $id, $adminId]);
            $_SESSION['success'] = 'Status da tarefa atualizado.';
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = 'Erro ao processar ação: ' . $e->getMessage();
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Filtros
$statusFilter = $_GET['status'] ?? 'all';
$priorityFilter = $_GET['priority'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'due_date';

// Buscar tarefas
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $where = ["admin_id = ?"];
    $params = [$adminId];
    
    if ($statusFilter !== 'all') {
        $where[] = "status = ?";
        $params[] = $statusFilter;
    }
    
    if ($priorityFilter !== 'all') {
        $where[] = "priority = ?";
        $params[] = $priorityFilter;
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $where);
    
    $orderBy = match($sortBy) {
        'priority' => 'priority DESC, due_date ASC',
        'created' => 'created_at DESC',
        'title' => 'title ASC',
        default => 'due_date ASC, priority DESC'
    };
    
    $stmt = db()->prepare("SELECT * FROM todo_items {$whereClause} ORDER BY {$orderBy}");
    $stmt->execute($params);
    $todos = $stmt->fetchAll();
    
    // Estatísticas
    $stats = [
        'total' => count($todos),
        'pending' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'overdue' => 0,
    ];
    
    foreach ($todos as $todo) {
        if ($todo['status'] === 'pending') $stats['pending']++;
        if ($todo['status'] === 'in_progress') $stats['in_progress']++;
        if ($todo['status'] === 'completed') $stats['completed']++;
        if ($todo['due_date'] && $todo['status'] !== 'completed' && strtotime($todo['due_date']) < time()) {
            $stats['overdue']++;
        }
    }
    
} catch (Throwable $e) {
    $todos = [];
    $stats = ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0, 'overdue' => 0];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Itens a Fazer</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#todoModal" onclick="openTodoModal()">
            <i class="las la-plus me-1"></i> Nova Tarefa
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

    <!-- Estatísticas -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-primary">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Total</h6>
                    <h3 class="mb-0 text-primary"><?= number_format($stats['total']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-warning">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Pendentes</h6>
                    <h3 class="mb-0 text-warning"><?= number_format($stats['pending']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Em Progresso</h6>
                    <h3 class="mb-0 text-info"><?= number_format($stats['in_progress']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-success">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Concluídas</h6>
                    <h3 class="mb-0 text-success"><?= number_format($stats['completed']) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status" onchange="this.form.submit()">
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Todos</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pendente</option>
                        <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>Em Progresso</option>
                        <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Concluída</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelada</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="priority" class="form-label">Prioridade</label>
                    <select class="form-select" id="priority" name="priority" onchange="this.form.submit()">
                        <option value="all" <?= $priorityFilter === 'all' ? 'selected' : '' ?>>Todas</option>
                        <option value="low" <?= $priorityFilter === 'low' ? 'selected' : '' ?>>Baixa</option>
                        <option value="medium" <?= $priorityFilter === 'medium' ? 'selected' : '' ?>>Média</option>
                        <option value="high" <?= $priorityFilter === 'high' ? 'selected' : '' ?>>Alta</option>
                        <option value="urgent" <?= $priorityFilter === 'urgent' ? 'selected' : '' ?>>Urgente</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="sort" class="form-label">Ordenar por</label>
                    <select class="form-select" id="sort" name="sort" onchange="this.form.submit()">
                        <option value="due_date" <?= $sortBy === 'due_date' ? 'selected' : '' ?>>Data de Vencimento</option>
                        <option value="priority" <?= $sortBy === 'priority' ? 'selected' : '' ?>>Prioridade</option>
                        <option value="created" <?= $sortBy === 'created' ? 'selected' : '' ?>>Data de Criação</option>
                        <option value="title" <?= $sortBy === 'title' ? 'selected' : '' ?>>Título</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <a href="/admin/todos.php" class="btn btn-secondary w-100">
                        <i class="las la-redo me-1"></i> Limpar Filtros
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Tarefas -->
    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($todos)): ?>
                <p class="text-muted text-center mb-0">Nenhuma tarefa encontrada.</p>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($todos as $todo): 
                        $priorityBadges = [
                            'low' => ['label' => 'Baixa', 'class' => 'secondary'],
                            'medium' => ['label' => 'Média', 'class' => 'info'],
                            'high' => ['label' => 'Alta', 'class' => 'warning'],
                            'urgent' => ['label' => 'Urgente', 'class' => 'danger']
                        ];
                        $statusBadges = [
                            'pending' => ['label' => 'Pendente', 'class' => 'warning'],
                            'in_progress' => ['label' => 'Em Progresso', 'class' => 'info'],
                            'completed' => ['label' => 'Concluída', 'class' => 'success'],
                            'cancelled' => ['label' => 'Cancelada', 'class' => 'secondary']
                        ];
                        $priorityInfo = $priorityBadges[$todo['priority']] ?? ['label' => ucfirst($todo['priority']), 'class' => 'secondary'];
                        $statusInfo = $statusBadges[$todo['status']] ?? ['label' => ucfirst($todo['status']), 'class' => 'secondary'];
                        $isOverdue = $todo['due_date'] && $todo['status'] !== 'completed' && strtotime($todo['due_date']) < time();
                    ?>
                        <div class="list-group-item <?= $todo['status'] === 'completed' ? 'bg-light' : '' ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <h6 class="mb-0 <?= $todo['status'] === 'completed' ? 'text-decoration-line-through text-muted' : '' ?>">
                                            <?= h($todo['title']) ?>
                                        </h6>
                                        <span class="badge bg-<?= $priorityInfo['class'] ?>"><?= $priorityInfo['label'] ?></span>
                                        <span class="badge bg-<?= $statusInfo['class'] ?>"><?= $statusInfo['label'] ?></span>
                                        <?php if ($isOverdue): ?>
                                            <span class="badge bg-danger">Atrasada</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($todo['description']): ?>
                                        <p class="text-muted small mb-2"><?= nl2br(h($todo['description'])) ?></p>
                                    <?php endif; ?>
                                    <div class="small text-muted">
                                        <?php if ($todo['due_date']): ?>
                                            <i class="las la-calendar me-1"></i>
                                            Vencimento: <strong class="<?= $isOverdue ? 'text-danger' : '' ?>">
                                                <?= date('d/m/Y', strtotime($todo['due_date'])) ?>
                                            </strong>
                                        <?php endif; ?>
                                        <?php if ($todo['completed_at']): ?>
                                            | <i class="las la-check-circle me-1"></i>
                                            Concluída em: <?= date('d/m/Y H:i', strtotime($todo['completed_at'])) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <?php if ($todo['status'] !== 'completed'): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Marcar como concluída?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="id" value="<?= $todo['id'] ?>">
                                            <input type="hidden" name="status" value="completed">
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <i class="las la-check"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="editTodo(<?= htmlspecialchars(json_encode($todo)) ?>)">
                                        <i class="las la-edit"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Excluir esta tarefa?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $todo['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="las la-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de Tarefa -->
<div class="modal fade" id="todoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="todoForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="todoAction" value="create">
                <input type="hidden" name="id" id="todoId">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="todoModalTitle">Nova Tarefa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="todoTitle" class="form-label">Título <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="todoTitle" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="todoDescription" class="form-label">Descrição</label>
                        <textarea class="form-control" id="todoDescription" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="todoPriority" class="form-label">Prioridade</label>
                            <select class="form-select" id="todoPriority" name="priority">
                                <option value="low">Baixa</option>
                                <option value="medium" selected>Média</option>
                                <option value="high">Alta</option>
                                <option value="urgent">Urgente</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="todoDueDate" class="form-label">Data de Vencimento</label>
                            <input type="date" class="form-control" id="todoDueDate" name="due_date">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="todoSubmitBtn">Criar Tarefa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openTodoModal() {
    document.getElementById('todoForm').reset();
    document.getElementById('todoAction').value = 'create';
    document.getElementById('todoId').value = '';
    document.getElementById('todoModalTitle').textContent = 'Nova Tarefa';
    document.getElementById('todoSubmitBtn').textContent = 'Criar Tarefa';
    document.getElementById('todoPriority').value = 'medium';
}

function editTodo(todo) {
    document.getElementById('todoAction').value = 'update';
    document.getElementById('todoId').value = todo.id;
    document.getElementById('todoTitle').value = todo.title;
    document.getElementById('todoDescription').value = todo.description || '';
    document.getElementById('todoPriority').value = todo.priority;
    document.getElementById('todoDueDate').value = todo.due_date || '';
    document.getElementById('todoModalTitle').textContent = 'Editar Tarefa';
    document.getElementById('todoSubmitBtn').textContent = 'Atualizar Tarefa';
    
    const modal = new bootstrap.Modal(document.getElementById('todoModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

