<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Status dos Pedidos';
$active = 'order_statuses';
require_once __DIR__ . '/partials/layout_start.php';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $name = strtolower(trim($_POST['name'] ?? ''));
        $label = trim($_POST['label'] ?? '');
        $color = $_POST['color'] ?? '#6c757d';
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $includePending = isset($_POST['include_in_pending']) ? 1 : 0;
        $includeActive = isset($_POST['include_in_active']) ? 1 : 0;
        $includeCancelled = isset($_POST['include_in_cancelled']) ? 1 : 0;
        
        if (empty($name) || empty($label)) {
            $_SESSION['error'] = 'Nome e rótulo são obrigatórios.';
        } else {
            try {
                db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
                
                if ($action === 'create') {
                    $stmt = db()->prepare("INSERT INTO order_statuses (name, label, color, sort_order, include_in_pending, include_in_active, include_in_cancelled) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $label, $color, $sortOrder, $includePending, $includeActive, $includeCancelled]);
                    $_SESSION['success'] = 'Status criado com sucesso.';
                } else {
                    // Verificar se é status padrão
                    $stmt = db()->prepare("SELECT is_default FROM order_statuses WHERE id = ?");
                    $stmt->execute([$id]);
                    $status = $stmt->fetch();
                    
                    if ($status && $status['is_default']) {
                        // Status padrão: só pode atualizar label, color e includes
                        $stmt = db()->prepare("UPDATE order_statuses SET label=?, color=?, sort_order=?, include_in_pending=?, include_in_active=?, include_in_cancelled=? WHERE id=?");
                        $stmt->execute([$label, $color, $sortOrder, $includePending, $includeActive, $includeCancelled, $id]);
                    } else {
                        $stmt = db()->prepare("UPDATE order_statuses SET name=?, label=?, color=?, sort_order=?, include_in_pending=?, include_in_active=?, include_in_cancelled=? WHERE id=?");
                        $stmt->execute([$name, $label, $color, $sortOrder, $includePending, $includeActive, $includeCancelled, $id]);
                    }
                    $_SESSION['success'] = 'Status atualizado com sucesso.';
                }
            } catch (Throwable $e) {
                $_SESSION['error'] = 'Erro ao salvar status: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Verificar se é status padrão
            $stmt = db()->prepare("SELECT is_default FROM order_statuses WHERE id = ?");
            $stmt->execute([$id]);
            $status = $stmt->fetch();
            
            if ($status && $status['is_default']) {
                $_SESSION['error'] = 'Status padrão não pode ser excluído.';
            } else {
                $stmt = db()->prepare("DELETE FROM order_statuses WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success'] = 'Status excluído com sucesso.';
            }
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Erro ao excluir status: ' . $e->getMessage();
        }
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Buscar status
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = db()->query("SELECT * FROM order_statuses ORDER BY sort_order ASC, id ASC");
    $statuses = $stmt->fetchAll();
} catch (Throwable $e) {
    $statuses = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Status dos Pedidos</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#statusModal" onclick="openStatusModal()">
            <i class="las la-plus me-1"></i> Novo Status
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

    <div class="alert alert-info">
        <i class="las la-info-circle me-2"></i>
        <strong>Nota:</strong> Os 4 status padrão (Pendente, Ativo, Fraude e Cancelado) não podem ser excluídos ou renomeados, mas podem ter suas cores e configurações de inclusão alteradas.
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Ordem</th>
                            <th>Nome</th>
                            <th>Rótulo</th>
                            <th>Cor</th>
                            <th>Incluir em Pendente</th>
                            <th>Incluir em Ativo</th>
                            <th>Incluir em Cancelado</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($statuses as $status): ?>
                            <tr>
                                <td><?= number_format((int)$status['sort_order']) ?></td>
                                <td>
                                    <strong><?= h($status['name']) ?></strong>
                                    <?php if ($status['is_default']): ?>
                                        <span class="badge bg-warning">Padrão</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= h($status['label']) ?></td>
                                <td>
                                    <span class="badge" style="background-color: <?= h($status['color']) ?>; color: <?= getContrastColor($status['color']) ?>;">
                                        <?= h($status['color']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($status['include_in_pending']): ?>
                                        <i class="las la-check text-success"></i>
                                    <?php else: ?>
                                        <i class="las la-times text-muted"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($status['include_in_active']): ?>
                                        <i class="las la-check text-success"></i>
                                    <?php else: ?>
                                        <i class="las la-times text-muted"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($status['include_in_cancelled']): ?>
                                        <i class="las la-check text-success"></i>
                                    <?php else: ?>
                                        <i class="las la-times text-muted"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="editStatus(<?= htmlspecialchars(json_encode($status)) ?>)">
                                        <i class="las la-edit"></i>
                                    </button>
                                    <?php if (!$status['is_default']): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Excluir este status?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $status['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="las la-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Status -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="statusForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="statusAction" value="create">
                <input type="hidden" name="id" id="statusId">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="statusModalTitle">Novo Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="statusName" class="form-label">Nome (interno) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="statusName" name="name" required 
                               pattern="[a-z0-9_]+" title="Apenas letras minúsculas, números e underscore">
                        <small class="text-muted">Usado internamente (ex: pending, active). Não pode ser alterado em status padrão.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="statusLabel" class="form-label">Rótulo (exibido) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="statusLabel" name="label" required>
                        <small class="text-muted">Texto exibido para o usuário.</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="statusColor" class="form-label">Cor</label>
                            <input type="color" class="form-control form-control-color" id="statusColor" name="color" value="#6c757d">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="statusSortOrder" class="form-label">Ordem</label>
                            <input type="number" class="form-control" id="statusSortOrder" name="sort_order" value="0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Incluir em:</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="includePending" name="include_in_pending">
                            <label class="form-check-label" for="includePending">
                                Pendente
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="includeActive" name="include_in_active">
                            <label class="form-check-label" for="includeActive">
                                Ativo
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="includeCancelled" name="include_in_cancelled">
                            <label class="form-check-label" for="includeCancelled">
                                Cancelado
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="statusSubmitBtn">Criar Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
function getContrastColor($hexColor) {
    $hexColor = ltrim($hexColor, '#');
    $r = hexdec(substr($hexColor, 0, 2));
    $g = hexdec(substr($hexColor, 2, 2));
    $b = hexdec(substr($hexColor, 4, 2));
    $brightness = ($r * 299 + $g * 587 + $b * 114) / 1000;
    return $brightness > 128 ? '#000000' : '#ffffff';
}
?>

<script>
function openStatusModal() {
    document.getElementById('statusForm').reset();
    document.getElementById('statusAction').value = 'create';
    document.getElementById('statusId').value = '';
    document.getElementById('statusModalTitle').textContent = 'Novo Status';
    document.getElementById('statusSubmitBtn').textContent = 'Criar Status';
    document.getElementById('statusColor').value = '#6c757d';
    document.getElementById('statusName').disabled = false;
}

function editStatus(status) {
    document.getElementById('statusAction').value = 'update';
    document.getElementById('statusId').value = status.id;
    document.getElementById('statusName').value = status.name;
    document.getElementById('statusLabel').value = status.label;
    document.getElementById('statusColor').value = status.color;
    document.getElementById('statusSortOrder').value = status.sort_order;
    document.getElementById('includePending').checked = status.include_in_pending == 1;
    document.getElementById('includeActive').checked = status.include_in_active == 1;
    document.getElementById('includeCancelled').checked = status.include_in_cancelled == 1;
    document.getElementById('statusModalTitle').textContent = 'Editar Status';
    document.getElementById('statusSubmitBtn').textContent = 'Atualizar Status';
    
    // Desabilitar nome se for status padrão
    if (status.is_default == 1) {
        document.getElementById('statusName').disabled = true;
    } else {
        document.getElementById('statusName').disabled = false;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

