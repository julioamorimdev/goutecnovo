<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(404);
    exit('Disputa não encontrada.');
}

$page_title = 'Disputa';
$active = 'disputes';
require_once __DIR__ . '/partials/layout_start.php';

$error = null;
$success = null;

// Buscar disputa
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $sql = "SELECT d.*, 
                   c.first_name, c.last_name, c.email as client_email, c.company_name, c.phone,
                   i.invoice_number, i.total as invoice_total,
                   o.order_number, o.amount as order_amount,
                   a.username as resolved_by_name
            FROM disputes d
            LEFT JOIN clients c ON d.client_id = c.id
            LEFT JOIN invoices i ON d.invoice_id = i.id
            LEFT JOIN orders o ON d.order_id = o.id
            LEFT JOIN admin_users a ON d.resolved_by = a.id
            WHERE d.id=?";
    
    $stmt = db()->prepare($sql);
    $stmt->execute([$id]);
    $dispute = $stmt->fetch();
    
    if (!$dispute) {
        http_response_code(404);
        exit('Disputa não encontrada.');
    }
} catch (Throwable $e) {
    http_response_code(500);
    exit('Erro ao buscar disputa.');
}

// Buscar comentários
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = db()->prepare("SELECT dc.*, a.username as admin_name FROM dispute_comments dc LEFT JOIN admin_users a ON dc.admin_id = a.id WHERE dc.dispute_id=? ORDER BY dc.created_at ASC");
    $stmt->execute([$id]);
    $comments = $stmt->fetchAll();
} catch (Throwable $e) {
    $comments = [];
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    $action = $_POST['action'] ?? '';
    $adminId = (int)($_SESSION['admin_user_id'] ?? 0);
    
    if ($action === 'update_status') {
        $newStatus = trim((string)($_POST['status'] ?? ''));
        $resolutionNotes = trim((string)($_POST['resolution_notes'] ?? ''));
        
        if (in_array($newStatus, ['open', 'under_review', 'resolved', 'won', 'lost', 'withdrawn'], true)) {
            db()->beginTransaction();
            try {
                if (in_array($newStatus, ['resolved', 'won', 'lost', 'withdrawn'], true)) {
                    $stmt = db()->prepare("UPDATE disputes SET status=?, resolved_by=?, resolved_at=NOW(), resolution_notes=? WHERE id=?");
                    $stmt->execute([$newStatus, $adminId, $resolutionNotes !== '' ? $resolutionNotes : null, $id]);
                } else {
                    $stmt = db()->prepare("UPDATE disputes SET status=?, resolved_by=NULL, resolved_at=NULL, resolution_notes=? WHERE id=?");
                    $stmt->execute([$newStatus, $resolutionNotes !== '' ? $resolutionNotes : null, $id]);
                }
                
                // Adicionar comentário automático
                $statusLabels = [
                    'open' => 'Aberta',
                    'under_review' => 'Em Análise',
                    'resolved' => 'Resolvida',
                    'won' => 'Ganha',
                    'lost' => 'Perdida',
                    'withdrawn' => 'Retirada'
                ];
                $comment = 'Status alterado para: ' . ($statusLabels[$newStatus] ?? $newStatus);
                if ($resolutionNotes) {
                    $comment .= "\n\n" . $resolutionNotes;
                }
                db()->prepare("INSERT INTO dispute_comments (dispute_id, admin_id, comment, is_internal) VALUES (?, ?, ?, 0)")->execute([$id, $adminId, $comment]);
                
                db()->commit();
                $success = 'Status da disputa atualizado com sucesso.';
                
                // Recarregar dados
                $stmt = db()->prepare($sql);
                $stmt->execute([$id]);
                $dispute = $stmt->fetch();
            } catch (Throwable $e) {
                db()->rollBack();
                $error = 'Erro ao atualizar status: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'update_priority') {
        $newPriority = trim((string)($_POST['priority'] ?? ''));
        if (in_array($newPriority, ['low', 'medium', 'high', 'urgent'], true)) {
            db()->prepare("UPDATE disputes SET priority=? WHERE id=?")->execute([$newPriority, $id]);
            $success = 'Prioridade atualizada com sucesso.';
            
            // Recarregar dados
            $stmt = db()->prepare($sql);
            $stmt->execute([$id]);
            $dispute = $stmt->fetch();
        }
    } elseif ($action === 'add_comment') {
        $comment = trim((string)($_POST['comment'] ?? ''));
        $isInternal = isset($_POST['is_internal']) ? 1 : 0;
        
        if ($comment !== '') {
            db()->prepare("INSERT INTO dispute_comments (dispute_id, admin_id, comment, is_internal) VALUES (?, ?, ?, ?)")->execute([$id, $adminId, $comment, $isInternal]);
            $success = 'Comentário adicionado com sucesso.';
            
            // Recarregar comentários
            $stmt = db()->prepare("SELECT dc.*, a.username as admin_name FROM dispute_comments dc LEFT JOIN admin_users a ON dc.admin_id = a.id WHERE dc.dispute_id=? ORDER BY dc.created_at ASC");
            $stmt->execute([$id]);
            $comments = $stmt->fetchAll();
        }
    } elseif ($action === 'update_dispute') {
        $deadlineDate = trim((string)($_POST['deadline_date'] ?? ''));
        $paymentProcessor = trim((string)($_POST['payment_processor'] ?? ''));
        $transactionId = trim((string)($_POST['transaction_id'] ?? ''));
        $chargebackReasonCode = trim((string)($_POST['chargeback_reason_code'] ?? ''));
        
        db()->prepare("UPDATE disputes SET deadline_date=?, payment_processor=?, transaction_id=?, chargeback_reason_code=? WHERE id=?")->execute([
            $deadlineDate !== '' ? $deadlineDate : null,
            $paymentProcessor !== '' ? $paymentProcessor : null,
            $transactionId !== '' ? $transactionId : null,
            $chargebackReasonCode !== '' ? $chargebackReasonCode : null,
            $id
        ]);
        
        $success = 'Disputa atualizada com sucesso.';
        
        // Recarregar dados
        $stmt = db()->prepare($sql);
        $stmt->execute([$id]);
        $dispute = $stmt->fetch();
    }
}

$isOpen = in_array($dispute['status'], ['open', 'under_review']);
$isUrgent = $dispute['deadline_date'] && strtotime($dispute['deadline_date']) <= strtotime('+3 days') && $isOpen;
$isOverdue = $dispute['deadline_date'] && strtotime($dispute['deadline_date']) < time() && $isOpen;
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Disputa #<?= h($dispute['dispute_number']) ?></h1>
        <a href="/admin/disputes.php" class="btn btn-secondary">
            <i class="las la-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= h($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <!-- Informações da Disputa -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Informações da Disputa</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Número:</strong><br>
                            <span class="text-primary"><?= h($dispute['dispute_number']) ?></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Status:</strong><br>
                            <?php
                            $statusBadges = [
                                'open' => 'bg-warning',
                                'under_review' => 'bg-info',
                                'resolved' => 'bg-success',
                                'won' => 'bg-success',
                                'lost' => 'bg-danger',
                                'withdrawn' => 'bg-secondary'
                            ];
                            $statusLabels = [
                                'open' => 'Aberta',
                                'under_review' => 'Em Análise',
                                'resolved' => 'Resolvida',
                                'won' => 'Ganha',
                                'lost' => 'Perdida',
                                'withdrawn' => 'Retirada'
                            ];
                            $status = $dispute['status'] ?? 'open';
                            ?>
                            <span class="badge <?= $statusBadges[$status] ?? 'bg-secondary' ?>">
                                <?= $statusLabels[$status] ?? ucfirst($status) ?>
                            </span>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Tipo:</strong><br>
                            <?php
                            $typeLabels = [
                                'chargeback' => 'Chargeback',
                                'refund_request' => 'Solicitação de Reembolso',
                                'billing_error' => 'Erro de Cobrança',
                                'service_issue' => 'Problema no Serviço',
                                'other' => 'Outros'
                            ];
                            ?>
                            <span class="badge bg-secondary"><?= $typeLabels[$dispute['type']] ?? ucfirst($dispute['type']) ?></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Prioridade:</strong><br>
                            <?php
                            $priorityBadges = [
                                'urgent' => 'bg-danger',
                                'high' => 'bg-warning',
                                'medium' => 'bg-info',
                                'low' => 'bg-secondary'
                            ];
                            $priorityLabels = [
                                'urgent' => 'Urgente',
                                'high' => 'Alta',
                                'medium' => 'Média',
                                'low' => 'Baixa'
                            ];
                            $priority = $dispute['priority'] ?? 'medium';
                            ?>
                            <span class="badge <?= $priorityBadges[$priority] ?? 'bg-secondary' ?>">
                                <?= $priorityLabels[$priority] ?? ucfirst($priority) ?>
                            </span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <strong>Motivo:</strong><br>
                        <span class="fw-semibold"><?= h($dispute['reason']) ?></span>
                    </div>

                    <div class="mb-3">
                        <strong>Descrição:</strong><br>
                        <p class="text-muted"><?= nl2br(h($dispute['description'])) ?></p>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Valor Disputado:</strong><br>
                            <span class="h5 text-danger">R$ <?= number_format((float)$dispute['amount'], 2, ',', '.') ?></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Data Limite:</strong><br>
                            <?php if ($dispute['deadline_date']): ?>
                                <span class="<?= $isOverdue ? 'text-danger fw-bold' : ($isUrgent ? 'text-warning' : '') ?>">
                                    <?= date('d/m/Y', strtotime($dispute['deadline_date'])) ?>
                                    <?php if ($isOverdue): ?>
                                        <span class="badge bg-danger">Atrasado</span>
                                    <?php elseif ($isUrgent): ?>
                                        <span class="badge bg-warning">Urgente</span>
                                    <?php endif; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Não definida</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($dispute['invoice_number']): ?>
                        <div class="mb-3">
                            <strong>Fatura Vinculada:</strong><br>
                            <a href="/admin/invoice_edit.php?id=<?= (int)$dispute['invoice_id'] ?>" class="text-decoration-none">
                                <?= h($dispute['invoice_number']) ?>
                            </a>
                            <?php if ($dispute['invoice_total']): ?>
                                - R$ <?= number_format((float)$dispute['invoice_total'], 2, ',', '.') ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($dispute['order_number']): ?>
                        <div class="mb-3">
                            <strong>Pedido Vinculado:</strong><br>
                            <a href="/admin/order_edit.php?id=<?= (int)$dispute['order_id'] ?>" class="text-decoration-none">
                                <?= h($dispute['order_number']) ?>
                            </a>
                            <?php if ($dispute['order_amount']): ?>
                                - R$ <?= number_format((float)$dispute['order_amount'], 2, ',', '.') ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($dispute['payment_processor'] || $dispute['transaction_id'] || $dispute['chargeback_reason_code']): ?>
                        <div class="alert alert-info">
                            <strong>Informações do Processador:</strong><br>
                            <?php if ($dispute['payment_processor']): ?>
                                <strong>Processador:</strong> <?= h($dispute['payment_processor']) ?><br>
                            <?php endif; ?>
                            <?php if ($dispute['transaction_id']): ?>
                                <strong>ID da Transação:</strong> <?= h($dispute['transaction_id']) ?><br>
                            <?php endif; ?>
                            <?php if ($dispute['chargeback_reason_code']): ?>
                                <strong>Código do Motivo:</strong> <?= h($dispute['chargeback_reason_code']) ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($dispute['resolution_notes']): ?>
                        <div class="alert alert-success">
                            <strong>Notas de Resolução:</strong><br>
                            <p class="mb-0"><?= nl2br(h($dispute['resolution_notes'])) ?></p>
                            <?php if ($dispute['resolved_by_name']): ?>
                                <small class="text-muted">
                                    Resolvido por: <?= h($dispute['resolved_by_name']) ?> 
                                    em <?= $dispute['resolved_at'] ? date('d/m/Y H:i', strtotime($dispute['resolved_at'])) : '' ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Informações do Cliente -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Informações do Cliente</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <strong>Nome:</strong><br>
                            <?= h($dispute['first_name'] . ' ' . $dispute['last_name']) ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Email:</strong><br>
                            <a href="mailto:<?= h($dispute['client_email']) ?>"><?= h($dispute['client_email']) ?></a>
                        </div>
                    </div>
                    <?php if ($dispute['company_name']): ?>
                        <div class="mb-3">
                            <strong>Empresa:</strong><br>
                            <?= h($dispute['company_name']) ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($dispute['phone']): ?>
                        <div class="mb-3">
                            <strong>Telefone:</strong><br>
                            <?= h($dispute['phone']) ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <a href="/admin/client_edit.php?id=<?= (int)$dispute['client_id'] ?>" class="btn btn-sm btn-primary">
                            <i class="las la-user me-1"></i> Ver Cliente
                        </a>
                    </div>
                </div>
            </div>

            <!-- Comentários -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Comentários</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add_comment">
                            <div class="mb-2">
                                <textarea class="form-control" name="comment" rows="3" placeholder="Adicionar comentário..." required></textarea>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="is_internal" name="is_internal" value="1">
                                <label class="form-check-label" for="is_internal">
                                    Comentário interno (não visível ao cliente)
                                </label>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="las la-comment me-1"></i> Adicionar Comentário
                            </button>
                        </form>
                    </div>
                    <hr>
                    <div id="commentsList">
                        <?php if (empty($comments)): ?>
                            <p class="text-muted text-center">Nenhum comentário ainda.</p>
                        <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                                <div class="mb-3 p-3 border rounded <?= (int)$comment['is_internal'] === 1 ? 'bg-light' : '' ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <strong><?= h($comment['admin_name'] ?? 'Sistema') ?></strong>
                                            <?php if ((int)$comment['is_internal'] === 1): ?>
                                                <span class="badge bg-secondary ms-2">Interno</span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted"><?= date('d/m/Y H:i', strtotime($comment['created_at'])) ?></small>
                                    </div>
                                    <p class="mb-0"><?= nl2br(h($comment['comment'])) ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <?php if ($isOpen): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-<?= $isOverdue ? 'danger' : ($isUrgent ? 'warning' : 'info') ?> text-white">
                        <h5 class="mb-0">Processar Disputa</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="mb-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_status">
                            <div class="mb-3">
                                <label for="status" class="form-label">Alterar Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="open" <?= $dispute['status'] === 'open' ? 'selected' : '' ?>>Aberta</option>
                                    <option value="under_review" <?= $dispute['status'] === 'under_review' ? 'selected' : '' ?>>Em Análise</option>
                                    <option value="resolved" <?= $dispute['status'] === 'resolved' ? 'selected' : '' ?>>Resolvida</option>
                                    <option value="won" <?= $dispute['status'] === 'won' ? 'selected' : '' ?>>Ganha</option>
                                    <option value="lost" <?= $dispute['status'] === 'lost' ? 'selected' : '' ?>>Perdida</option>
                                    <option value="withdrawn" <?= $dispute['status'] === 'withdrawn' ? 'selected' : '' ?>>Retirada</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="resolution_notes" class="form-label">Notas de Resolução</label>
                                <textarea class="form-control" id="resolution_notes" name="resolution_notes" rows="3" placeholder="Observações sobre a resolução..."><?= h($dispute['resolution_notes']) ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="las la-save me-1"></i> Atualizar Status
                            </button>
                        </form>

                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_priority">
                            <div class="mb-3">
                                <label for="priority" class="form-label">Alterar Prioridade</label>
                                <select class="form-select" id="priority" name="priority" required>
                                    <option value="low" <?= $dispute['priority'] === 'low' ? 'selected' : '' ?>>Baixa</option>
                                    <option value="medium" <?= $dispute['priority'] === 'medium' ? 'selected' : '' ?>>Média</option>
                                    <option value="high" <?= $dispute['priority'] === 'high' ? 'selected' : '' ?>>Alta</option>
                                    <option value="urgent" <?= $dispute['priority'] === 'urgent' ? 'selected' : '' ?>>Urgente</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="las la-exclamation-triangle me-1"></i> Atualizar Prioridade
                            </button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Status</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-0">Esta disputa já foi resolvida e não pode ser alterada.</p>
                        <?php if ($dispute['resolved_by_name']): ?>
                            <hr>
                            <small class="text-muted">
                                <strong>Resolvido por:</strong> <?= h($dispute['resolved_by_name']) ?><br>
                                <strong>Data:</strong> <?= $dispute['resolved_at'] ? date('d/m/Y H:i', strtotime($dispute['resolved_at'])) : '' ?>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Informações Adicionais</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_dispute">
                        <div class="mb-3">
                            <label for="deadline_date" class="form-label">Data Limite</label>
                            <input type="date" class="form-control" id="deadline_date" name="deadline_date" value="<?= $dispute['deadline_date'] ? date('Y-m-d', strtotime($dispute['deadline_date'])) : '' ?>">
                        </div>
                        <div class="mb-3">
                            <label for="payment_processor" class="form-label">Processador de Pagamento</label>
                            <input type="text" class="form-control" id="payment_processor" name="payment_processor" value="<?= h($dispute['payment_processor']) ?>" placeholder="Stripe, PayPal, etc.">
                        </div>
                        <div class="mb-3">
                            <label for="transaction_id" class="form-label">ID da Transação</label>
                            <input type="text" class="form-control" id="transaction_id" name="transaction_id" value="<?= h($dispute['transaction_id']) ?>" placeholder="ID da transação no processador">
                        </div>
                        <div class="mb-3">
                            <label for="chargeback_reason_code" class="form-label">Código do Motivo (Chargeback)</label>
                            <input type="text" class="form-control" id="chargeback_reason_code" name="chargeback_reason_code" value="<?= h($dispute['chargeback_reason_code']) ?>" placeholder="Código do motivo">
                        </div>
                        <button type="submit" class="btn btn-info w-100">
                            <i class="las la-save me-1"></i> Atualizar Informações
                        </button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Informações do Sistema</h5>
                </div>
                <div class="card-body">
                    <div class="small text-muted">
                        <div><strong>ID:</strong> #<?= (int)$id ?></div>
                        <div><strong>Criado em:</strong> <?= date('d/m/Y H:i', strtotime($dispute['created_at'])) ?></div>
                        <?php if ($dispute['updated_at']): ?>
                            <div><strong>Atualizado em:</strong> <?= date('d/m/Y H:i', strtotime($dispute['updated_at'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

