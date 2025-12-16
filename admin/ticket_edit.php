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

$page_title = $id ? 'Editar Ticket' : 'Novo Ticket';
$active = 'tickets';
require_once __DIR__ . '/partials/layout_start.php';

$error = null;

$item = [
    'client_id' => null,
    'ticket_number' => '',
    'subject' => '',
    'department' => 'support',
    'priority' => 'medium',
    'status' => 'open',
    'message' => '', // Mensagem inicial
];

// Gerar número do ticket se for novo
if ($id === 0) {
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->exec("SET CHARACTER SET utf8mb4");
        db()->exec("SET character_set_connection=utf8mb4");
        $stmt = db()->query("SELECT COUNT(*) as cnt FROM tickets WHERE ticket_number LIKE 'TKT-%'");
        $count = (int)$stmt->fetch()['cnt'];
        $item['ticket_number'] = 'TKT-' . date('Y') . '-' . str_pad((string)($count + 1), 3, '0', STR_PAD_LEFT);
    } catch (Throwable $e) {
        $item['ticket_number'] = 'TKT-' . date('Y') . '-001';
    }
}

if ($id > 0) {
    try {
        // Garantir UTF-8 na conexão
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->exec("SET CHARACTER SET utf8mb4");
        db()->exec("SET character_set_connection=utf8mb4");
        
        $stmt = db()->prepare("SELECT * FROM tickets WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            exit('Ticket não encontrado.');
        }
        $item = array_merge($item, $row);
        
        // Buscar primeira mensagem (do cliente)
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->exec("SET CHARACTER SET utf8mb4");
        db()->exec("SET character_set_connection=utf8mb4");
        $stmt = db()->prepare("SELECT message FROM ticket_replies WHERE ticket_id=? AND user_type='client' ORDER BY created_at ASC LIMIT 1");
        $stmt->execute([$id]);
        $firstReply = $stmt->fetch();
        if ($firstReply) {
            $item['message'] = $firstReply['message'];
        }
    } catch (Throwable $e) {
        http_response_code(404);
        exit('Erro ao buscar ticket.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $clientId = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
    $ticketNumber = trim((string)($_POST['ticket_number'] ?? ''));
    $subject = trim((string)($_POST['subject'] ?? ''));
    $department = trim((string)($_POST['department'] ?? 'support'));
    $priority = trim((string)($_POST['priority'] ?? 'medium'));
    $status = trim((string)($_POST['status'] ?? 'open'));
    $message = trim((string)($_POST['message'] ?? ''));
    
    if ($clientId <= 0) $error = 'O cliente é obrigatório.';
    if ($ticketNumber === '') $error = 'O número do ticket é obrigatório.';
    if ($subject === '') $error = 'O assunto é obrigatório.';
    if ($id === 0 && $message === '') $error = 'A mensagem inicial é obrigatória.';
    
    if (!in_array($status, ['open', 'answered', 'customer_reply', 'closed'], true)) {
        $status = 'open';
    }
    if (!in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
        $priority = 'medium';
    }
    if (!in_array($department, ['support', 'sales', 'billing', 'technical'], true)) {
        $department = 'support';
    }
    
    // Verificar se o número do ticket já existe (exceto para o próprio ticket)
    if (!$error) {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            db()->exec("SET CHARACTER SET utf8mb4");
            db()->exec("SET character_set_connection=utf8mb4");
            $stmt = db()->prepare("SELECT id FROM tickets WHERE ticket_number=? AND id != ?");
            $stmt->execute([$ticketNumber, $id]);
            if ($stmt->fetch()) {
                $error = 'Este número de ticket já está em uso.';
            }
        } catch (Throwable $e) {
            // Ignorar erro na verificação
        }
    }

    $data = [
        'client_id' => $clientId,
        'ticket_number' => $ticketNumber,
        'subject' => $subject,
        'department' => $department,
        'priority' => $priority,
        'status' => $status,
    ];

    if (!$error) {
        try {
            // Garantir UTF-8 na conexão
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            db()->exec("SET CHARACTER SET utf8mb4");
            db()->exec("SET character_set_connection=utf8mb4");
            
            if ($id > 0) {
                $stmt = db()->prepare("UPDATE tickets SET client_id=:client_id, ticket_number=:ticket_number, subject=:subject, department=:department, priority=:priority, status=:status WHERE id=:id");
                $data['id'] = $id;
                $stmt->execute($data);
                $_SESSION['success'] = 'Ticket atualizado com sucesso.';
                header('Location: /admin/ticket_view.php?id=' . $id);
                exit;
            } else {
                $stmt = db()->prepare("INSERT INTO tickets (client_id, ticket_number, subject, department, priority, status) VALUES (:client_id, :ticket_number, :subject, :department, :priority, :status)");
                $stmt->execute($data);
                $newTicketId = (int)db()->lastInsertId();
                
                // Inserir mensagem inicial
                if ($message !== '') {
                    $stmt = db()->prepare("INSERT INTO ticket_replies (ticket_id, user_type, message, is_internal) VALUES (?, 'client', ?, 0)");
                    $stmt->execute([$newTicketId, $message]);
                }
                
                $_SESSION['success'] = 'Ticket criado com sucesso.';
                header('Location: /admin/ticket_view.php?id=' . $newTicketId);
                exit;
            }
        } catch (Throwable $e) {
            $error = 'Erro ao salvar ticket: ' . $e->getMessage();
        }
    }
    $item = array_merge($item, $data);
}

// Buscar clientes para o select
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    db()->exec("SET CHARACTER SET utf8mb4");
    db()->exec("SET character_set_connection=utf8mb4");
    $clients = db()->query("SELECT id, first_name, last_name, email FROM clients ORDER BY first_name, last_name")->fetchAll();
} catch (Throwable $e) {
    $clients = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= $id ? 'Editar Ticket' : 'Novo Ticket' ?></h1>
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

    <form method="POST">
        <?= csrf_field() ?>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Informações do Ticket</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="ticket_number" class="form-label">Número do Ticket <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ticket_number" name="ticket_number" value="<?= h($item['ticket_number']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="client_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                            <select class="form-select" id="client_id" name="client_id" required>
                                <option value="">Selecione um cliente</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= (int)$client['id'] ?>" <?= (int)$item['client_id'] === (int)$client['id'] ? 'selected' : '' ?>>
                                        <?= h($client['first_name'] . ' ' . $client['last_name']) ?> (<?= h($client['email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="subject" class="form-label">Assunto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="subject" name="subject" value="<?= h($item['subject']) ?>" required>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="department" class="form-label">Departamento</label>
                                <select class="form-select" id="department" name="department">
                                    <option value="support" <?= $item['department'] === 'support' ? 'selected' : '' ?>>Suporte</option>
                                    <option value="sales" <?= $item['department'] === 'sales' ? 'selected' : '' ?>>Vendas</option>
                                    <option value="billing" <?= $item['department'] === 'billing' ? 'selected' : '' ?>>Faturamento</option>
                                    <option value="technical" <?= $item['department'] === 'technical' ? 'selected' : '' ?>>Técnico</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="priority" class="form-label">Prioridade</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="low" <?= $item['priority'] === 'low' ? 'selected' : '' ?>>Baixa</option>
                                    <option value="medium" <?= $item['priority'] === 'medium' ? 'selected' : '' ?>>Média</option>
                                    <option value="high" <?= $item['priority'] === 'high' ? 'selected' : '' ?>>Alta</option>
                                    <option value="urgent" <?= $item['priority'] === 'urgent' ? 'selected' : '' ?>>Urgente</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="open" <?= $item['status'] === 'open' ? 'selected' : '' ?>>Aberto</option>
                                    <option value="answered" <?= $item['status'] === 'answered' ? 'selected' : '' ?>>Respondido</option>
                                    <option value="customer_reply" <?= $item['status'] === 'customer_reply' ? 'selected' : '' ?>>Aguardando Resposta</option>
                                    <option value="closed" <?= $item['status'] === 'closed' ? 'selected' : '' ?>>Fechado</option>
                                </select>
                            </div>
                        </div>

                        <?php if ($id === 0): ?>
                            <div class="mb-3">
                                <label for="message" class="form-label">Mensagem Inicial <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="message" name="message" rows="6" required placeholder="Digite a mensagem inicial do ticket..."><?= h($item['message']) ?></textarea>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Informações do Sistema</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($id > 0): ?>
                            <div class="mb-3">
                                <label class="form-label">ID do Ticket</label>
                                <div class="form-control-plaintext">#<?= (int)$id ?></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Criado em</label>
                                <div class="form-control-plaintext"><?= date('d/m/Y H:i', strtotime($item['created_at'] ?? 'now')) ?></div>
                            </div>
                            <?php if ($item['updated_at'] ?? ''): ?>
                                <div class="mb-3">
                                    <label class="form-label">Última atualização</label>
                                    <div class="form-control-plaintext"><?= date('d/m/Y H:i', strtotime($item['updated_at'])) ?></div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-info mb-0">
                                <small>O ticket será criado com as informações fornecidas.</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="las la-save me-1"></i> Salvar
            </button>
            <a href="/admin/tickets.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>
