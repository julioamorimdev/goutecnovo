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
    exit('Solicitação não encontrada.');
}

$page_title = 'Solicitação de Cancelamento';
$active = 'cancellation_requests';
require_once __DIR__ . '/partials/layout_start.php';

$error = null;
$success = null;

// Buscar solicitação
try {
    // Garantir UTF-8 na conexão
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    db()->exec("SET CHARACTER SET utf8mb4");
    db()->exec("SET character_set_connection=utf8mb4");
    
    $sql = "SELECT cr.*, 
                   c.first_name, c.last_name, c.email as client_email, c.company_name, c.phone,
                   o.order_number, o.amount as order_amount, o.status as order_status,
                   a.username as processed_by_name
            FROM cancellation_requests cr
            LEFT JOIN clients c ON cr.client_id = c.id
            LEFT JOIN orders o ON cr.order_id = o.id
            LEFT JOIN admin_users a ON cr.processed_by = a.id
            WHERE cr.id=?";
    
    $stmt = db()->prepare($sql);
    $stmt->execute([$id]);
    $request = $stmt->fetch();
    
    if (!$request) {
        http_response_code(404);
        exit('Solicitação não encontrada.');
    }
} catch (Throwable $e) {
    http_response_code(500);
    exit('Erro ao buscar solicitação.');
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    $action = $_POST['action'] ?? '';
    $adminId = (int)($_SESSION['admin_user_id'] ?? 0);
    $adminNotes = trim((string)($_POST['admin_notes'] ?? ''));
    
    if (in_array($action, ['approve', 'reject', 'complete'])) {
        db()->beginTransaction();
        try {
            if ($action === 'approve') {
                $effectiveDate = trim((string)($_POST['effective_date'] ?? ''));
                if ($effectiveDate === '') {
                    $effectiveDate = date('Y-m-d');
                }
                
                $stmt = db()->prepare("UPDATE cancellation_requests SET status='approved', effective_date=?, processed_by=?, processed_at=NOW(), admin_notes=? WHERE id=?");
                $stmt->execute([$effectiveDate, $adminId, $adminNotes !== '' ? $adminNotes : null, $id]);
                
                // Se houver pedido vinculado, cancelar o pedido
                if ($request['order_id']) {
                    db()->prepare("UPDATE orders SET status='cancelled' WHERE id=?")->execute([(int)$request['order_id']]);
                }
                
                $success = 'Solicitação de cancelamento aprovada com sucesso.';
            } elseif ($action === 'reject') {
                $stmt = db()->prepare("UPDATE cancellation_requests SET status='rejected', processed_by=?, processed_at=NOW(), admin_notes=? WHERE id=?");
                $stmt->execute([$adminId, $adminNotes !== '' ? $adminNotes : null, $id]);
                $success = 'Solicitação de cancelamento rejeitada.';
            } elseif ($action === 'complete') {
                $refundAmount = isset($_POST['refund_amount']) && $_POST['refund_amount'] !== '' ? (float)$_POST['refund_amount'] : null;
                
                $stmt = db()->prepare("UPDATE cancellation_requests SET status='completed', processed_by=?, processed_at=NOW(), admin_notes=? WHERE id=?");
                $stmt->execute([$adminId, $adminNotes !== '' ? $adminNotes : null, $id]);
                
                // Processar reembolso se fornecido
                if ($refundAmount !== null && $refundAmount > 0) {
                    $stmt = db()->prepare("UPDATE cancellation_requests SET refund_requested=1, refund_amount=?, refund_status='approved' WHERE id=?");
                    $stmt->execute([$refundAmount, $id]);
                }
                
                $success = 'Cancelamento concluído com sucesso.';
            }
            
            db()->commit();
            
            // Recarregar dados
            $stmt = db()->prepare($sql);
            $stmt->execute([$id]);
            $request = $stmt->fetch();
        } catch (Throwable $e) {
            db()->rollBack();
            $error = 'Erro ao processar solicitação: ' . $e->getMessage();
        }
    }
}

$isPending = $request['status'] === 'pending';
$isApproved = $request['status'] === 'approved';
$canProcess = $isPending || $isApproved;
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Solicitação de Cancelamento #<?= h($request['request_number']) ?></h1>
        <a href="/admin/cancellation_requests.php" class="btn btn-secondary">
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
            <!-- Informações da Solicitação -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Informações da Solicitação</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Número:</strong><br>
                            <span class="text-primary"><?= h($request['request_number']) ?></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Status:</strong><br>
                            <?php
                            $statusBadges = [
                                'pending' => 'bg-warning',
                                'approved' => 'bg-info',
                                'rejected' => 'bg-danger',
                                'cancelled' => 'bg-secondary',
                                'completed' => 'bg-success'
                            ];
                            $statusLabels = [
                                'pending' => 'Pendente',
                                'approved' => 'Aprovado',
                                'rejected' => 'Rejeitado',
                                'cancelled' => 'Cancelado',
                                'completed' => 'Concluído'
                            ];
                            $status = $request['status'] ?? 'pending';
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
                                'service' => 'Serviço',
                                'order' => 'Pedido',
                                'domain' => 'Domínio',
                                'subscription' => 'Assinatura'
                            ];
                            ?>
                            <span class="badge bg-secondary"><?= $typeLabels[$request['type']] ?? ucfirst($request['type']) ?></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Data Solicitada:</strong><br>
                            <?= date('d/m/Y', strtotime($request['requested_date'])) ?>
                        </div>
                    </div>

                    <?php if ($request['effective_date']): ?>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Data Efetiva:</strong><br>
                                <?= date('d/m/Y', strtotime($request['effective_date'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <strong>Motivo:</strong><br>
                        <span class="fw-semibold"><?= h($request['reason']) ?></span>
                    </div>

                    <?php if ($request['reason_details']): ?>
                        <div class="mb-3">
                            <strong>Detalhes do Motivo:</strong><br>
                            <p class="text-muted"><?= nl2br(h($request['reason_details'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($request['order_number']): ?>
                        <div class="mb-3">
                            <strong>Pedido Vinculado:</strong><br>
                            <a href="/admin/order_edit.php?id=<?= (int)$request['order_id'] ?>" class="text-decoration-none">
                                <?= h($request['order_number']) ?>
                            </a>
                            <?php if ($request['order_amount']): ?>
                                - R$ <?= number_format((float)$request['order_amount'], 2, ',', '.') ?>
                            <?php endif; ?>
                            <br><small class="text-muted">Status: <?= h($request['order_status']) ?></small>
                        </div>
                    <?php endif; ?>

                    <?php if ((int)$request['refund_requested'] === 1): ?>
                        <div class="alert alert-info">
                            <strong><i class="las la-money-bill"></i> Reembolso Solicitado</strong><br>
                            <?php if ($request['refund_amount']): ?>
                                Valor: R$ <?= number_format((float)$request['refund_amount'], 2, ',', '.') ?><br>
                            <?php endif; ?>
                            Status: <?= h($request['refund_status'] ?? 'pending') ?>
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
                            <?= h($request['first_name'] . ' ' . $request['last_name']) ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Email:</strong><br>
                            <a href="mailto:<?= h($request['client_email']) ?>"><?= h($request['client_email']) ?></a>
                        </div>
                    </div>
                    <?php if ($request['company_name']): ?>
                        <div class="mb-3">
                            <strong>Empresa:</strong><br>
                            <?= h($request['company_name']) ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($request['phone']): ?>
                        <div class="mb-3">
                            <strong>Telefone:</strong><br>
                            <?= h($request['phone']) ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <a href="/admin/client_edit.php?id=<?= (int)$request['client_id'] ?>" class="btn btn-sm btn-primary">
                            <i class="las la-user me-1"></i> Ver Cliente
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($request['admin_notes']): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Notas do Administrador</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-0"><?= nl2br(h($request['admin_notes'])) ?></p>
                        <?php if ($request['processed_by_name']): ?>
                            <small class="text-muted">
                                Processado por: <?= h($request['processed_by_name']) ?> 
                                em <?= $request['processed_at'] ? date('d/m/Y H:i', strtotime($request['processed_at'])) : '' ?>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <?php if ($canProcess): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-<?= $isPending ? 'warning' : 'info' ?> text-white">
                        <h5 class="mb-0">Processar Solicitação</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($isPending): ?>
                            <!-- Aprovar -->
                            <form method="POST" class="mb-3">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="approve">
                                <div class="mb-3">
                                    <label for="effective_date" class="form-label">Data Efetiva do Cancelamento</label>
                                    <input type="date" class="form-control" id="effective_date" name="effective_date" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="admin_notes_approve" class="form-label">Notas</label>
                                    <textarea class="form-control" id="admin_notes_approve" name="admin_notes" rows="3" placeholder="Observações sobre a aprovação..."></textarea>
                                </div>
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="las la-check me-1"></i> Aprovar Cancelamento
                                </button>
                            </form>

                            <!-- Rejeitar -->
                            <form method="POST" class="mb-3">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="reject">
                                <div class="mb-3">
                                    <label for="admin_notes_reject" class="form-label">Motivo da Rejeição</label>
                                    <textarea class="form-control" id="admin_notes_reject" name="admin_notes" rows="3" placeholder="Informe o motivo da rejeição..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Tem certeza que deseja rejeitar esta solicitação?');">
                                    <i class="las la-times me-1"></i> Rejeitar Solicitação
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if ($isApproved): ?>
                            <!-- Concluir -->
                            <form method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="complete">
                                <div class="mb-3">
                                    <label for="refund_amount" class="form-label">Valor do Reembolso (se aplicável)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">R$</span>
                                        <input type="number" class="form-control" id="refund_amount" name="refund_amount" step="0.01" min="0" placeholder="0.00">
                                    </div>
                                    <small class="text-muted">Deixe em branco se não houver reembolso</small>
                                </div>
                                <div class="mb-3">
                                    <label for="admin_notes_complete" class="form-label">Notas</label>
                                    <textarea class="form-control" id="admin_notes_complete" name="admin_notes" rows="3" placeholder="Observações sobre a conclusão..."></textarea>
                                </div>
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="las la-check-circle me-1"></i> Concluir Cancelamento
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Status</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-0">Esta solicitação já foi processada e não pode ser alterada.</p>
                        <?php if ($request['processed_by_name']): ?>
                            <hr>
                            <small class="text-muted">
                                <strong>Processado por:</strong> <?= h($request['processed_by_name']) ?><br>
                                <strong>Data:</strong> <?= $request['processed_at'] ? date('d/m/Y H:i', strtotime($request['processed_at'])) : '' ?>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Informações do Sistema</h5>
                </div>
                <div class="card-body">
                    <div class="small text-muted">
                        <div><strong>ID:</strong> #<?= (int)$id ?></div>
                        <div><strong>Criado em:</strong> <?= date('d/m/Y H:i', strtotime($request['created_at'])) ?></div>
                        <?php if ($request['updated_at']): ?>
                            <div><strong>Atualizado em:</strong> <?= date('d/m/Y H:i', strtotime($request['updated_at'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

