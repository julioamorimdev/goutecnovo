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

$page_title = $id ? 'Editar Falha na Rede' : 'Nova Falha na Rede';
$active = 'network_incidents';
require_once __DIR__ . '/partials/layout_start.php';

$error = null;

$item = [
    'incident_number' => '',
    'title' => '',
    'description' => '',
    'type' => 'network',
    'severity' => 'medium',
    'status' => 'investigating',
    'affected_services' => '',
    'affected_servers' => '',
    'impact_description' => '',
    'root_cause' => '',
    'resolution' => '',
    'started_at' => date('Y-m-d\TH:i'),
    'resolved_at' => '',
    'estimated_resolution' => '',
    'is_public' => 1,
    'notify_clients' => 0,
];

// Gerar número do incidente se for novo
if ($id === 0) {
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt = db()->query("SELECT COUNT(*) as cnt FROM network_incidents WHERE incident_number LIKE 'INC-%'");
        $count = (int)$stmt->fetch()['cnt'];
        $item['incident_number'] = 'INC-' . date('Y') . '-' . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);
    } catch (Throwable $e) {
        $item['incident_number'] = 'INC-' . date('Y') . '-0001';
    }
}

if ($id > 0) {
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt = db()->prepare("SELECT * FROM network_incidents WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            exit('Falha não encontrada.');
        }
        $item = array_merge($item, $row);
        if ($item['started_at']) {
            $item['started_at'] = date('Y-m-d\TH:i', strtotime($item['started_at']));
        }
        if ($item['resolved_at']) {
            $item['resolved_at'] = date('Y-m-d\TH:i', strtotime($item['resolved_at']));
        }
        if ($item['estimated_resolution']) {
            $item['estimated_resolution'] = date('Y-m-d\TH:i', strtotime($item['estimated_resolution']));
        }
        
        // Buscar atualizações
        $stmt = db()->prepare("SELECT u.*, a.username as author_name FROM network_incident_updates u LEFT JOIN admin_users a ON u.created_by = a.id WHERE u.incident_id=? ORDER BY u.created_at ASC");
        $stmt->execute([$id]);
        $updates = $stmt->fetchAll();
    } catch (Throwable $e) {
        http_response_code(404);
        exit('Erro ao buscar falha.');
    }
} else {
    $updates = [];
}

// Processar adição de atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_update'])) {
    csrf_verify($_POST['_csrf'] ?? null);
    
    if ($id > 0) {
        $updateStatus = trim((string)($_POST['update_status'] ?? ''));
        $updateMessage = trim((string)($_POST['update_message'] ?? ''));
        $updatePublic = isset($_POST['update_public']) ? 1 : 0;
        
        if ($updateMessage !== '') {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            db()->prepare("INSERT INTO network_incident_updates (incident_id, status, message, is_public, created_by) VALUES (?, ?, ?, ?, ?)")->execute([
                $id,
                $updateStatus !== '' ? $updateStatus : $item['status'],
                $updateMessage,
                $updatePublic,
                (int)($_SESSION['admin_user_id'] ?? 0)
            ]);
            
            // Atualizar status do incidente se fornecido
            if ($updateStatus !== '' && $updateStatus !== $item['status']) {
                db()->prepare("UPDATE network_incidents SET status=? WHERE id=?")->execute([$updateStatus, $id]);
            }
            
            $_SESSION['success'] = 'Atualização adicionada com sucesso.';
            header('Location: /admin/network_incident_edit.php?id=' . $id);
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['add_update'])) {
    csrf_verify($_POST['_csrf'] ?? null);

    $incidentNumber = trim((string)($_POST['incident_number'] ?? ''));
    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $type = trim((string)($_POST['type'] ?? 'network'));
    $severity = trim((string)($_POST['severity'] ?? 'medium'));
    $status = trim((string)($_POST['status'] ?? 'investigating'));
    $affectedServices = trim((string)($_POST['affected_services'] ?? ''));
    $affectedServers = trim((string)($_POST['affected_servers'] ?? ''));
    $impactDescription = trim((string)($_POST['impact_description'] ?? ''));
    $rootCause = trim((string)($_POST['root_cause'] ?? ''));
    $resolution = trim((string)($_POST['resolution'] ?? ''));
    $startedAt = trim((string)($_POST['started_at'] ?? ''));
    $resolvedAt = trim((string)($_POST['resolved_at'] ?? ''));
    $estimatedResolution = trim((string)($_POST['estimated_resolution'] ?? ''));
    $isPublic = isset($_POST['is_public']) ? 1 : 0;
    $notifyClients = isset($_POST['notify_clients']) ? 1 : 0;
    
    if ($incidentNumber === '') $error = 'O número do incidente é obrigatório.';
    if ($title === '') $error = 'O título é obrigatório.';
    if ($description === '') $error = 'A descrição é obrigatória.';
    
    if (!in_array($type, ['network', 'server', 'service', 'database', 'other'], true)) {
        $type = 'network';
    }
    
    if (!in_array($severity, ['low', 'medium', 'high', 'critical'], true)) {
        $severity = 'medium';
    }
    
    if (!in_array($status, ['investigating', 'identified', 'monitoring', 'resolved', 'false_alarm'], true)) {
        $status = 'investigating';
    }
    
    // Se status for resolved e não tiver data de resolução, usar agora
    if ($status === 'resolved' && $resolvedAt === '') {
        $resolvedAt = date('Y-m-d H:i:s');
    } elseif ($status !== 'resolved') {
        $resolvedAt = null;
    } else {
        $resolvedAt = date('Y-m-d H:i:s', strtotime($resolvedAt));
    }
    
    // Verificar se o número já existe
    if (!$error) {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $stmt = db()->prepare("SELECT id FROM network_incidents WHERE incident_number=? AND id != ?");
            $stmt->execute([$incidentNumber, $id]);
            if ($stmt->fetch()) {
                $error = 'Este número de incidente já está em uso.';
            }
        } catch (Throwable $e) {
            // Ignorar erro na verificação
        }
    }

    $data = [
        'incident_number' => $incidentNumber,
        'title' => $title,
        'description' => $description,
        'type' => $type,
        'severity' => $severity,
        'status' => $status,
        'affected_services' => $affectedServices !== '' ? $affectedServices : null,
        'affected_servers' => $affectedServers !== '' ? $affectedServers : null,
        'impact_description' => $impactDescription !== '' ? $impactDescription : null,
        'root_cause' => $rootCause !== '' ? $rootCause : null,
        'resolution' => $resolution !== '' ? $resolution : null,
        'started_at' => date('Y-m-d H:i:s', strtotime($startedAt)),
        'resolved_at' => $resolvedAt,
        'estimated_resolution' => $estimatedResolution !== '' ? date('Y-m-d H:i:s', strtotime($estimatedResolution)) : null,
        'is_public' => $isPublic,
        'notify_clients' => $notifyClients,
        'created_by' => $id === 0 ? (int)($_SESSION['admin_user_id'] ?? 0) : null,
        'resolved_by' => $status === 'resolved' ? (int)($_SESSION['admin_user_id'] ?? 0) : null,
    ];

    if (!$error) {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            if ($id > 0) {
                $stmt = db()->prepare("UPDATE network_incidents SET incident_number=:incident_number, title=:title, description=:description, type=:type, severity=:severity, status=:status, affected_services=:affected_services, affected_servers=:affected_servers, impact_description=:impact_description, root_cause=:root_cause, resolution=:resolution, started_at=:started_at, resolved_at=:resolved_at, estimated_resolution=:estimated_resolution, is_public=:is_public, notify_clients=:notify_clients, resolved_by=:resolved_by WHERE id=:id");
                $data['id'] = $id;
                $stmt->execute($data);
                $_SESSION['success'] = 'Falha atualizada com sucesso.';
            } else {
                $stmt = db()->prepare("INSERT INTO network_incidents (incident_number, title, description, type, severity, status, affected_services, affected_servers, impact_description, root_cause, resolution, started_at, resolved_at, estimated_resolution, is_public, notify_clients, created_by, resolved_by) VALUES (:incident_number, :title, :description, :type, :severity, :status, :affected_services, :affected_servers, :impact_description, :root_cause, :resolution, :started_at, :resolved_at, :estimated_resolution, :is_public, :notify_clients, :created_by, :resolved_by)");
                $stmt->execute($data);
                $_SESSION['success'] = 'Falha criada com sucesso.';
            }
            header('Location: /admin/network_incidents.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Erro ao salvar falha: ' . $e->getMessage();
        }
    }
    $item = array_merge($item, $data);
    if ($resolvedAt) {
        $item['resolved_at'] = date('Y-m-d\TH:i', strtotime($resolvedAt));
    }
    if ($estimatedResolution) {
        $item['estimated_resolution'] = date('Y-m-d\TH:i', strtotime($estimatedResolution));
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= $id ? 'Editar Falha na Rede' : 'Nova Falha na Rede' ?></h1>
        <a href="/admin/network_incidents.php" class="btn btn-secondary">
            <i class="las la-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= h($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">Informações do Incidente</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="incident_number" class="form-label">Número do Incidente <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="incident_number" name="incident_number" value="<?= h($item['incident_number']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="started_at" class="form-label">Data/Hora de Início <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="started_at" name="started_at" value="<?= h($item['started_at']) ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="title" class="form-label">Título <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" value="<?= h($item['title']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Descrição <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="5" required><?= h($item['description']) ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="type" class="form-label">Tipo <span class="text-danger">*</span></label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="network" <?= $item['type'] === 'network' ? 'selected' : '' ?>>Rede</option>
                                    <option value="server" <?= $item['type'] === 'server' ? 'selected' : '' ?>>Servidor</option>
                                    <option value="service" <?= $item['type'] === 'service' ? 'selected' : '' ?>>Serviço</option>
                                    <option value="database" <?= $item['type'] === 'database' ? 'selected' : '' ?>>Banco de Dados</option>
                                    <option value="other" <?= $item['type'] === 'other' ? 'selected' : '' ?>>Outros</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="severity" class="form-label">Severidade <span class="text-danger">*</span></label>
                                <select class="form-select" id="severity" name="severity" required>
                                    <option value="low" <?= $item['severity'] === 'low' ? 'selected' : '' ?>>Baixa</option>
                                    <option value="medium" <?= $item['severity'] === 'medium' ? 'selected' : '' ?>>Média</option>
                                    <option value="high" <?= $item['severity'] === 'high' ? 'selected' : '' ?>>Alta</option>
                                    <option value="critical" <?= $item['severity'] === 'critical' ? 'selected' : '' ?>>Crítica</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="affected_services" class="form-label">Serviços Afetados</label>
                            <textarea class="form-control" id="affected_services" name="affected_services" rows="2" placeholder="Liste os serviços afetados..."><?= h($item['affected_services']) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="affected_servers" class="form-label">Servidores Afetados</label>
                            <textarea class="form-control" id="affected_servers" name="affected_servers" rows="2" placeholder="Liste os servidores afetados..."><?= h($item['affected_servers']) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="impact_description" class="form-label">Descrição do Impacto</label>
                            <textarea class="form-control" id="impact_description" name="impact_description" rows="3" placeholder="Descreva o impacto para os clientes..."><?= h($item['impact_description']) ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Resolução</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="root_cause" class="form-label">Causa Raiz</label>
                            <textarea class="form-control" id="root_cause" name="root_cause" rows="3" placeholder="Causa raiz identificada..."><?= h($item['root_cause']) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="resolution" class="form-label">Resolução Aplicada</label>
                            <textarea class="form-control" id="resolution" name="resolution" rows="3" placeholder="Solução aplicada..."><?= h($item['resolution']) ?></textarea>
                        </div>
                    </div>
                </div>

                <?php if ($id > 0 && !empty($updates)): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">Atualizações de Status</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($updates as $update): ?>
                                <div class="mb-3 p-3 border rounded">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <strong><?= h($update['author_name'] ?? 'Sistema') ?></strong>
                                            <?php if ((int)$update['is_public'] === 1): ?>
                                                <span class="badge bg-success ms-2">Público</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary ms-2">Interno</span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted"><?= date('d/m/Y H:i', strtotime($update['created_at'])) ?></small>
                                    </div>
                                    <p class="mb-0"><?= nl2br(h($update['message'])) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Status e Configurações</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required onchange="toggleResolutionDate()">
                                <option value="investigating" <?= $item['status'] === 'investigating' ? 'selected' : '' ?>>Investigando</option>
                                <option value="identified" <?= $item['status'] === 'identified' ? 'selected' : '' ?>>Identificado</option>
                                <option value="monitoring" <?= $item['status'] === 'monitoring' ? 'selected' : '' ?>>Monitorando</option>
                                <option value="resolved" <?= $item['status'] === 'resolved' ? 'selected' : '' ?>>Resolvido</option>
                                <option value="false_alarm" <?= $item['status'] === 'false_alarm' ? 'selected' : '' ?>>Falso Alarme</option>
                            </select>
                        </div>

                        <div class="mb-3" id="resolvedDateContainer" style="display: <?= $item['status'] === 'resolved' ? 'block' : 'none' ?>;">
                            <label for="resolved_at" class="form-label">Data/Hora de Resolução</label>
                            <input type="datetime-local" class="form-control" id="resolved_at" name="resolved_at" value="<?= h($item['resolved_at']) ?>">
                        </div>

                        <div class="mb-3">
                            <label for="estimated_resolution" class="form-label">Estimativa de Resolução</label>
                            <input type="datetime-local" class="form-control" id="estimated_resolution" name="estimated_resolution" value="<?= h($item['estimated_resolution']) ?>">
                            <small class="text-muted">Quando esperamos resolver (opcional)</small>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_public" name="is_public" value="1" <?= (int)$item['is_public'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_public">
                                    Visível Publicamente (Status Page)
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="notify_clients" name="notify_clients" value="1" <?= (int)$item['notify_clients'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="notify_clients">
                                    Notificar Clientes
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($id > 0): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Adicionar Atualização</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="add_update" value="1">
                                <div class="mb-3">
                                    <label for="update_status" class="form-label">Status (opcional)</label>
                                    <select class="form-select" id="update_status" name="update_status">
                                        <option value="">Manter status atual</option>
                                        <option value="investigating">Investigando</option>
                                        <option value="identified">Identificado</option>
                                        <option value="monitoring">Monitorando</option>
                                        <option value="resolved">Resolvido</option>
                                        <option value="false_alarm">Falso Alarme</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="update_message" class="form-label">Mensagem <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="update_message" name="update_message" rows="3" required placeholder="Atualização sobre o incidente..."></textarea>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="update_public" name="update_public" value="1" checked>
                                        <label class="form-check-label" for="update_public">
                                            Atualização Pública
                                        </label>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="las la-plus me-1"></i> Adicionar Atualização
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Informações do Sistema</h5>
                    </div>
                    <div class="card-body">
                        <div class="small text-muted">
                            <?php if ($id > 0): ?>
                                <div><strong>ID:</strong> #<?= (int)$id ?></div>
                                <div><strong>Criado em:</strong> <?= date('d/m/Y H:i', strtotime($item['created_at'] ?? 'now')) ?></div>
                                <?php if ($item['created_by']): ?>
                                    <div><strong>Criado por:</strong> <?= h($item['created_by_name'] ?? 'N/A') ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="text-muted">Novo incidente será criado ao salvar.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="las la-save me-1"></i> Salvar
            </button>
            <a href="/admin/network_incidents.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
function toggleResolutionDate() {
    const status = document.getElementById('status').value;
    const container = document.getElementById('resolvedDateContainer');
    container.style.display = status === 'resolved' ? 'block' : 'none';
    if (status === 'resolved' && !document.getElementById('resolved_at').value) {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        document.getElementById('resolved_at').value = `${year}-${month}-${day}T${hours}:${minutes}`;
    }
}
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

