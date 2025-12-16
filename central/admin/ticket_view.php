<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: /admin/tickets.php');
    exit;
}

$page_title = 'Visualizar Ticket';
$active = 'tickets';
require_once __DIR__ . '/partials/layout_start.php';

$error = null;
$success = null;

// Buscar ticket
try {
    // Garantir UTF-8 na conexão
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    db()->exec("SET CHARACTER SET utf8mb4");
    db()->exec("SET character_set_connection=utf8mb4");
    
    $stmt = db()->prepare("SELECT t.*, c.first_name, c.last_name, c.email as client_email, c.phone as client_phone 
                          FROM tickets t 
                          LEFT JOIN clients c ON t.client_id = c.id 
                          WHERE t.id = ?");
    $stmt->execute([$id]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) {
        http_response_code(404);
        exit('Ticket não encontrado.');
    }
} catch (Throwable $e) {
    http_response_code(404);
    exit('Erro ao buscar ticket.');
}

// Processar resposta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_action'])) {
    csrf_verify($_POST['_csrf'] ?? null);
    
    $message = trim((string)($_POST['message'] ?? ''));
    $isInternal = isset($_POST['is_internal']) ? 1 : 0;
    $newStatus = trim((string)($_POST['status'] ?? $ticket['status']));
    
    if ($message === '') {
        $error = 'A mensagem é obrigatória.';
    } else {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            db()->exec("SET CHARACTER SET utf8mb4");
            db()->exec("SET character_set_connection=utf8mb4");
            
            // Inserir resposta
            $adminUserId = (int)($_SESSION['admin_user_id'] ?? 0);
            $stmt = db()->prepare("INSERT INTO ticket_replies (ticket_id, user_id, user_type, message, is_internal) VALUES (?, ?, 'admin', ?, ?)");
            $stmt->execute([$id, $adminUserId > 0 ? $adminUserId : null, $message, $isInternal]);
            
            // Atualizar status e última resposta do ticket
            db()->prepare("UPDATE tickets SET status=?, last_reply_at=NOW() WHERE id=?")->execute([$newStatus, $id]);
            
            $success = 'Resposta enviada com sucesso.';
            // Recarregar ticket
            $stmt = db()->prepare("SELECT t.*, c.first_name, c.last_name, c.email as client_email, c.phone as client_phone 
                                  FROM tickets t 
                                  LEFT JOIN clients c ON t.client_id = c.id 
                                  WHERE t.id = ?");
            $stmt->execute([$id]);
            $ticket = $stmt->fetch();
        } catch (Throwable $e) {
            $error = 'Erro ao enviar resposta: ' . $e->getMessage();
        }
    }
}

// Buscar respostas
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    db()->exec("SET CHARACTER SET utf8mb4");
    db()->exec("SET character_set_connection=utf8mb4");
    $stmt = db()->prepare("SELECT tr.*, au.username as admin_username 
                          FROM ticket_replies tr 
                          LEFT JOIN admin_users au ON tr.user_id = au.id 
                          WHERE tr.ticket_id = ? 
                          ORDER BY tr.created_at ASC");
    $stmt->execute([$id]);
    $replies = $stmt->fetchAll();
} catch (Throwable $e) {
    $replies = [];
}

function getStatusBadge(string $status): string {
    switch ($status) {
        case 'open':
            return '<span class="badge bg-danger text-white">Aberto</span>';
        case 'answered':
            return '<span class="badge bg-success">Respondido</span>';
        case 'customer_reply':
            return '<span class="badge bg-warning text-dark">Aguardando Resposta</span>';
        case 'closed':
            return '<span class="badge bg-dark text-white">Fechado</span>';
        default:
            return '<span class="badge bg-secondary text-dark">' . h($status) . '</span>';
    }
}

function getPriorityBadge(string $priority): string {
    switch ($priority) {
        case 'urgent':
            return '<span class="badge bg-danger">Urgente</span>';
        case 'high':
            return '<span class="badge bg-warning text-dark">Alta</span>';
        case 'medium':
            return '<span class="badge bg-info text-white">Média</span>';
        case 'low':
            return '<span class="badge bg-dark text-white">Baixa</span>';
        default:
            return '<span class="badge bg-secondary text-dark">' . h($priority) . '</span>';
    }
}

function getDepartmentLabel(string $dept): string {
    $labels = [
        'support' => 'Suporte',
        'sales' => 'Vendas',
        'billing' => 'Faturamento',
        'technical' => 'Técnico',
    ];
    return $labels[$dept] ?? $dept;
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Ticket #<?= h($ticket['ticket_number']) ?></h1>
            <p class="text-muted mb-0"><?= h($ticket['subject']) ?></p>
        </div>
        <a href="/admin/tickets.php" class="btn btn-secondary">
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
        <div class="col-lg-9">
            <!-- Informações do Ticket -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Informações do Ticket</h5>
                    <div class="d-flex gap-2">
                        <?= getStatusBadge($ticket['status']) ?>
                        <?= getPriorityBadge($ticket['priority']) ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Cliente:</strong> <?= h($ticket['first_name'] . ' ' . $ticket['last_name']) ?><br>
                            <strong>Email:</strong> <a href="mailto:<?= h($ticket['client_email']) ?>"><?= h($ticket['client_email']) ?></a><br>
                            <?php if ($ticket['client_phone']): ?>
                                <strong>Telefone:</strong> <?= h($ticket['client_phone']) ?><br>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Departamento:</strong> <?= getDepartmentLabel($ticket['department']) ?><br>
                            <strong>Criado em:</strong> <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?><br>
                            <?php if ($ticket['last_reply_at']): ?>
                                <strong>Última resposta:</strong> <?= date('d/m/Y H:i', strtotime($ticket['last_reply_at'])) ?><br>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Histórico de Respostas -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Histórico de Conversa</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($replies)): ?>
                        <p class="text-muted mb-0">Nenhuma resposta ainda.</p>
                    <?php else: ?>
                        <?php foreach ($replies as $reply): ?>
                            <div class="mb-4 pb-4 border-bottom">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <strong>
                                            <?php if ($reply['user_type'] === 'admin'): ?>
                                                <?= h($reply['admin_username'] ?? 'Administrador') ?>
                                                <span class="badge bg-primary ms-2">Admin</span>
                                            <?php else: ?>
                                                <?= h($ticket['first_name'] . ' ' . $ticket['last_name']) ?>
                                                <span class="badge bg-info ms-2">Cliente</span>
                                            <?php endif; ?>
                                        </strong>
                                        <?php if ((int)$reply['is_internal'] === 1): ?>
                                            <span class="badge bg-warning text-dark ms-2">Nota Interna</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted"><?= date('d/m/Y H:i', strtotime($reply['created_at'])) ?></small>
                                </div>
                                <div class="text-break">
                                    <?= nl2br(h($reply['message'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Formulário de Resposta -->
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Responder Ticket</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="reply_action" value="reply">
                        
                        <div class="mb-3">
                            <label for="message" class="form-label">Mensagem <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="message" name="message" rows="6" required placeholder="Digite sua resposta..."></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="status" class="form-label">Alterar Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="<?= h($ticket['status']) ?>" selected>Manter atual (<?= h($ticket['status']) ?>)</option>
                                    <option value="open">Aberto</option>
                                    <option value="answered">Respondido</option>
                                    <option value="customer_reply">Aguardando Resposta</option>
                                    <option value="closed">Fechado</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_internal" name="is_internal">
                                    <label class="form-check-label" for="is_internal">
                                        Nota Interna (não visível para o cliente)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="las la-paper-plane me-1"></i> Enviar Resposta
                            </button>
                            <a href="/admin/tickets.php" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Ações Rápidas</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="mb-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$id ?>">
                        <label class="form-label small">Alterar Status</label>
                        <select class="form-select form-select-sm mb-2" name="action" onchange="if(confirm('Alterar status?')) this.form.submit();">
                            <option value="">Selecione...</option>
                            <option value="open" <?= $ticket['status'] === 'open' ? 'selected' : '' ?>>Aberto</option>
                            <option value="answered" <?= $ticket['status'] === 'answered' ? 'selected' : '' ?>>Respondido</option>
                            <option value="customer_reply" <?= $ticket['status'] === 'customer_reply' ? 'selected' : '' ?>>Aguardando Resposta</option>
                            <option value="closed" <?= $ticket['status'] === 'closed' ? 'selected' : '' ?>>Fechado</option>
                        </select>
                    </form>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$id ?>">
                        <label class="form-label small">Alterar Prioridade</label>
                        <select class="form-select form-select-sm" name="action" onchange="if(confirm('Alterar prioridade?')) this.form.submit();">
                            <option value="">Selecione...</option>
                            <option value="urgent" <?= $ticket['priority'] === 'urgent' ? 'selected' : '' ?>>Urgente</option>
                            <option value="high" <?= $ticket['priority'] === 'high' ? 'selected' : '' ?>>Alta</option>
                            <option value="medium" <?= $ticket['priority'] === 'medium' ? 'selected' : '' ?>>Média</option>
                            <option value="low" <?= $ticket['priority'] === 'low' ? 'selected' : '' ?>>Baixa</option>
                        </select>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>
