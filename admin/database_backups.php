<?php
declare(strict_types=1);
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Backups do Banco de Dados';
$active = 'database_backups';
require_once __DIR__ . '/partials/layout_start.php';

$adminId = $_SESSION['admin_user_id'] ?? null;
$backupDir = '/var/www/goutecnovo/storage/backups/';

// Criar diretório se não existir
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create_backup') {
            // Criar backup
            $filename = 'backup_' . date('Y-m-d_His') . '.sql';
            $filepath = $backupDir . $filename;
            
            // Obter configurações do banco
            $dbConfig = require __DIR__ . '/../app/config/database.php';
            $dbHost = $dbConfig['host'] ?? 'localhost';
            $dbName = $dbConfig['database'] ?? 'goutecnovo';
            $dbUser = $dbConfig['username'] ?? 'root';
            $dbPass = $dbConfig['password'] ?? '';
            
            // Comando mysqldump
            $command = sprintf(
                'mysqldump -h %s -u %s -p%s %s > %s 2>&1',
                escapeshellarg($dbHost),
                escapeshellarg($dbUser),
                escapeshellarg($dbPass),
                escapeshellarg($dbName),
                escapeshellarg($filepath)
            );
            
            exec($command, $output, $returnVar);
            
            if ($returnVar === 0 && file_exists($filepath)) {
                $fileSize = filesize($filepath);
                
                // Registrar no banco
                $stmt = db()->prepare("INSERT INTO database_backups (filename, file_path, file_size, backup_type, status, created_by) VALUES (?, ?, ?, 'manual', 'completed', ?)");
                $stmt->execute([$filename, '/storage/backups/' . $filename, $fileSize, $adminId]);
                
                $_SESSION['success'] = 'Backup criado com sucesso.';
            } else {
                throw new Exception('Erro ao criar backup: ' . implode("\n", $output));
            }
            
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            
            // Buscar backup
            $stmt = db()->prepare("SELECT file_path FROM database_backups WHERE id = ?");
            $stmt->execute([$id]);
            $backup = $stmt->fetch();
            
            if ($backup) {
                // Deletar arquivo
                $filepath = __DIR__ . '/..' . $backup['file_path'];
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
                
                // Deletar registro
                $stmt = db()->prepare("DELETE FROM database_backups WHERE id = ?");
                $stmt->execute([$id]);
                
                $_SESSION['success'] = 'Backup excluído com sucesso.';
            } else {
                throw new Exception('Backup não encontrado.');
            }
            
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } elseif ($action === 'restore') {
            $id = intval($_POST['id'] ?? 0);
            
            // Buscar backup
            $stmt = db()->prepare("SELECT file_path FROM database_backups WHERE id = ?");
            $stmt->execute([$id]);
            $backup = $stmt->fetch();
            
            if (!$backup) {
                throw new Exception('Backup não encontrado.');
            }
            
            $filepath = __DIR__ . '/..' . $backup['file_path'];
            if (!file_exists($filepath)) {
                throw new Exception('Arquivo de backup não encontrado.');
            }
            
            // Obter configurações do banco
            $dbConfig = require __DIR__ . '/../app/config/database.php';
            $dbHost = $dbConfig['host'] ?? 'localhost';
            $dbName = $dbConfig['database'] ?? 'goutecnovo';
            $dbUser = $dbConfig['username'] ?? 'root';
            $dbPass = $dbConfig['password'] ?? '';
            
            // Comando mysql para restaurar
            $command = sprintf(
                'mysql -h %s -u %s -p%s %s < %s 2>&1',
                escapeshellarg($dbHost),
                escapeshellarg($dbUser),
                escapeshellarg($dbPass),
                escapeshellarg($dbName),
                escapeshellarg($filepath)
            );
            
            exec($command, $output, $returnVar);
            
            if ($returnVar === 0) {
                $_SESSION['success'] = 'Backup restaurado com sucesso.';
            } else {
                throw new Exception('Erro ao restaurar backup: ' . implode("\n", $output));
            }
            
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Buscar backups
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = db()->query("SELECT db.*, au.username as created_by_name 
                        FROM database_backups db 
                        LEFT JOIN admin_users au ON db.created_by = au.id 
                        ORDER BY db.created_at DESC");
    $backups = $stmt->fetchAll();
} catch (Throwable $e) {
    $backups = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Backups do Banco de Dados</h1>
        <form method="POST" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_backup">
            <button type="submit" class="btn btn-primary" onclick="return confirm('Tem certeza que deseja criar um novo backup?')">
                <i class="las la-plus me-1"></i> Criar Backup
            </button>
        </form>
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
                            <th>Arquivo</th>
                            <th>Tamanho</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th>Criado por</th>
                            <th>Data de Criação</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($backups)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">Nenhum backup encontrado</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($backups as $backup): ?>
                                <tr>
                                    <td><code><?= h($backup['filename']) ?></code></td>
                                    <td><?= number_format($backup['file_size'] / 1024 / 1024, 2) ?> MB</td>
                                    <td>
                                        <?php
                                        $types = ['manual' => 'Manual', 'automatic' => 'Automático', 'scheduled' => 'Agendado'];
                                        echo h($types[$backup['backup_type']] ?? $backup['backup_type']);
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusBadges = [
                                            'completed' => 'bg-success',
                                            'failed' => 'bg-danger',
                                            'in_progress' => 'bg-warning'
                                        ];
                                        $badgeClass = $statusBadges[$backup['status']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= h(ucfirst($backup['status'])) ?></span>
                                    </td>
                                    <td><?= h($backup['created_by_name'] ?? 'Sistema') ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($backup['created_at'])) ?></td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="restore">
                                            <input type="hidden" name="id" value="<?= $backup['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-primary" onclick="return confirm('ATENÇÃO: Esta ação irá restaurar o banco de dados para o estado deste backup. Todos os dados atuais serão perdidos. Tem certeza?')">
                                                <i class="las la-undo"></i> Restaurar
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este backup?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $backup['id'] ?>">
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

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

