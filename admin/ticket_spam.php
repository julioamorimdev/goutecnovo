<?php
declare(strict_types=1);
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Controle de Spam de Ticket de Suporte';
$active = 'ticket_spam';
require_once __DIR__ . '/partials/layout_start.php';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $action = $_POST['action'] ?? '';
        
        if ($action === 'block') {
            $id = intval($_POST['id'] ?? 0);
            $stmt = db()->prepare("UPDATE ticket_spam_control SET is_blocked = 1 WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = 'IP/Email bloqueado com sucesso.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } elseif ($action === 'unblock') {
            $id = intval($_POST['id'] ?? 0);
            $stmt = db()->prepare("UPDATE ticket_spam_control SET is_blocked = 0 WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = 'IP/Email desbloqueado com sucesso.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            $stmt = db()->prepare("DELETE FROM ticket_spam_control WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = 'Registro excluído com sucesso.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } elseif ($action === 'reset') {
            $id = intval($_POST['id'] ?? 0);
            $stmt = db()->prepare("UPDATE ticket_spam_control SET ticket_count = 0, last_ticket_at = NULL WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = 'Contador resetado com sucesso.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Buscar registros de spam
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = db()->query("SELECT * FROM ticket_spam_control ORDER BY ticket_count DESC, created_at DESC");
    $spamRecords = $stmt->fetchAll();
} catch (Throwable $e) {
    $spamRecords = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Controle de Spam de Ticket de Suporte</h1>
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

    <div class="card mb-4">
        <div class="card-body">
            <p class="text-muted mb-0">
                <i class="las la-info-circle me-1"></i>
                O sistema monitora automaticamente IPs e emails que criam muitos tickets em pouco tempo. 
                Registros com alto número de tickets podem ser bloqueados para prevenir spam.
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>IP / Email</th>
                            <th>Contador de Tickets</th>
                            <th>Último Ticket</th>
                            <th>Status</th>
                            <th>Data de Criação</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($spamRecords)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">Nenhum registro encontrado</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($spamRecords as $record): ?>
                                <tr>
                                    <td>
                                        <?php if ($record['ip_address']): ?>
                                            <code><?= h($record['ip_address']) ?></code>
                                        <?php elseif ($record['email']): ?>
                                            <code><?= h($record['email']) ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $record['ticket_count'] > 10 ? 'bg-danger' : ($record['ticket_count'] > 5 ? 'bg-warning' : 'bg-info') ?>">
                                            <?= $record['ticket_count'] ?> tickets
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($record['last_ticket_at']): ?>
                                            <?= date('d/m/Y H:i', strtotime($record['last_ticket_at'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($record['is_blocked']): ?>
                                            <span class="badge bg-danger">Bloqueado</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Ativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($record['created_at'])) ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($record['is_blocked']): ?>
                                                <form method="POST" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="unblock">
                                                    <input type="hidden" name="id" value="<?= $record['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-success" title="Desbloquear">
                                                        <i class="las la-unlock"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="block">
                                                    <input type="hidden" name="id" value="<?= $record['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-warning" title="Bloquear">
                                                        <i class="las la-ban"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="reset">
                                                <input type="hidden" name="id" value="<?= $record['id'] ?>">
                                                <button type="submit" class="btn btn-outline-info" title="Resetar Contador" onclick="return confirm('Tem certeza que deseja resetar o contador?')">
                                                    <i class="las la-redo"></i>
                                                </button>
                                            </form>
                                            
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este registro?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $record['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="Excluir">
                                                    <i class="las la-trash"></i>
                                                </button>
                                            </form>
                                        </div>
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

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

