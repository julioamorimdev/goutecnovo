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

$page_title = $id ? 'Editar Anúncio' : 'Novo Anúncio';
$active = 'announcements';
require_once __DIR__ . '/partials/layout_start.php';

$error = null;

$item = [
    'title' => '',
    'content' => '',
    'type' => 'info',
    'target_audience' => 'all',
    'target_client_ids' => null,
    'start_date' => '',
    'end_date' => '',
    'is_active' => 1,
    'is_dismissible' => 1,
    'priority' => 0,
    'show_on_dashboard' => 1,
    'show_on_client_area' => 1,
    'click_url' => '',
];

if ($id > 0) {
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt = db()->prepare("SELECT * FROM announcements WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            exit('Anúncio não encontrado.');
        }
        $item = array_merge($item, $row);
        if ($item['start_date']) {
            $item['start_date'] = date('Y-m-d\TH:i', strtotime($item['start_date']));
        }
        if ($item['end_date']) {
            $item['end_date'] = date('Y-m-d\TH:i', strtotime($item['end_date']));
        }
        if ($item['target_client_ids']) {
            $item['target_client_ids'] = json_decode($item['target_client_ids'], true);
        }
    } catch (Throwable $e) {
        http_response_code(404);
        exit('Erro ao buscar anúncio.');
    }
}

// Buscar clientes para seleção específica
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $clients = db()->query("SELECT id, first_name, last_name, email FROM clients WHERE status='active' ORDER BY first_name, last_name")->fetchAll();
} catch (Throwable $e) {
    $clients = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $title = trim((string)($_POST['title'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));
    $type = trim((string)($_POST['type'] ?? 'info'));
    $targetAudience = trim((string)($_POST['target_audience'] ?? 'all'));
    $startDate = trim((string)($_POST['start_date'] ?? ''));
    $endDate = trim((string)($_POST['end_date'] ?? ''));
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $isDismissible = isset($_POST['is_dismissible']) ? 1 : 0;
    $priority = (int)($_POST['priority'] ?? 0);
    $showOnDashboard = isset($_POST['show_on_dashboard']) ? 1 : 0;
    $showOnClientArea = isset($_POST['show_on_client_area']) ? 1 : 0;
    $clickUrl = trim((string)($_POST['click_url'] ?? ''));
    
    $targetClientIds = null;
    if ($targetAudience === 'specific' && isset($_POST['target_client_ids']) && is_array($_POST['target_client_ids'])) {
        $targetClientIds = array_map('intval', $_POST['target_client_ids']);
        $targetClientIds = array_filter($targetClientIds, function($id) { return $id > 0; });
        $targetClientIds = !empty($targetClientIds) ? json_encode(array_values($targetClientIds)) : null;
    }
    
    if ($title === '') $error = 'O título é obrigatório.';
    if ($content === '') $error = 'O conteúdo é obrigatório.';
    
    if (!in_array($type, ['info', 'warning', 'success', 'error', 'maintenance'], true)) {
        $type = 'info';
    }
    
    if (!in_array($targetAudience, ['all', 'clients', 'admins', 'specific'], true)) {
        $targetAudience = 'all';
    }

    $data = [
        'title' => $title,
        'content' => $content,
        'type' => $type,
        'target_audience' => $targetAudience,
        'target_client_ids' => $targetClientIds,
        'start_date' => $startDate !== '' ? date('Y-m-d H:i:s', strtotime($startDate)) : null,
        'end_date' => $endDate !== '' ? date('Y-m-d H:i:s', strtotime($endDate)) : null,
        'is_active' => $isActive,
        'is_dismissible' => $isDismissible,
        'priority' => $priority,
        'show_on_dashboard' => $showOnDashboard,
        'show_on_client_area' => $showOnClientArea,
        'click_url' => $clickUrl !== '' ? $clickUrl : null,
        'created_by' => $id === 0 ? (int)($_SESSION['admin_user_id'] ?? 0) : null,
    ];

    if (!$error) {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            if ($id > 0) {
                $stmt = db()->prepare("UPDATE announcements SET title=:title, content=:content, type=:type, target_audience=:target_audience, target_client_ids=:target_client_ids, start_date=:start_date, end_date=:end_date, is_active=:is_active, is_dismissible=:is_dismissible, priority=:priority, show_on_dashboard=:show_on_dashboard, show_on_client_area=:show_on_client_area, click_url=:click_url WHERE id=:id");
                $data['id'] = $id;
                $stmt->execute($data);
                $_SESSION['success'] = 'Anúncio atualizado com sucesso.';
            } else {
                $stmt = db()->prepare("INSERT INTO announcements (title, content, type, target_audience, target_client_ids, start_date, end_date, is_active, is_dismissible, priority, show_on_dashboard, show_on_client_area, click_url, created_by) VALUES (:title, :content, :type, :target_audience, :target_client_ids, :start_date, :end_date, :is_active, :is_dismissible, :priority, :show_on_dashboard, :show_on_client_area, :click_url, :created_by)");
                $stmt->execute($data);
                $_SESSION['success'] = 'Anúncio criado com sucesso.';
            }
            header('Location: /admin/announcements.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Erro ao salvar anúncio: ' . $e->getMessage();
        }
    }
    $item = array_merge($item, $data);
    if ($targetClientIds) {
        $item['target_client_ids'] = json_decode($targetClientIds, true);
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= $id ? 'Editar Anúncio' : 'Novo Anúncio' ?></h1>
        <a href="/admin/announcements.php" class="btn btn-secondary">
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
                        <h5 class="mb-0">Informações do Anúncio</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">Título <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" value="<?= h($item['title']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="content" class="form-label">Conteúdo <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="content" name="content" rows="8" required><?= h($item['content']) ?></textarea>
                            <small class="text-muted">HTML permitido</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="type" class="form-label">Tipo <span class="text-danger">*</span></label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="info" <?= $item['type'] === 'info' ? 'selected' : '' ?>>Informação</option>
                                    <option value="warning" <?= $item['type'] === 'warning' ? 'selected' : '' ?>>Aviso</option>
                                    <option value="success" <?= $item['type'] === 'success' ? 'selected' : '' ?>>Sucesso</option>
                                    <option value="error" <?= $item['type'] === 'error' ? 'selected' : '' ?>>Erro</option>
                                    <option value="maintenance" <?= $item['type'] === 'maintenance' ? 'selected' : '' ?>>Manutenção</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="priority" class="form-label">Prioridade</label>
                                <input type="number" class="form-control" id="priority" name="priority" value="<?= (int)$item['priority'] ?>" min="0">
                                <small class="text-muted">Maior número = maior prioridade</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="click_url" class="form-label">URL ao Clicar (opcional)</label>
                            <input type="url" class="form-control" id="click_url" name="click_url" value="<?= h($item['click_url']) ?>" placeholder="https://...">
                            <small class="text-muted">URL para redirecionar quando o anúncio for clicado</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Público-Alvo</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="target_audience" class="form-label">Público <span class="text-danger">*</span></label>
                            <select class="form-select" id="target_audience" name="target_audience" required onchange="toggleClientSelection()">
                                <option value="all" <?= $item['target_audience'] === 'all' ? 'selected' : '' ?>>Todos</option>
                                <option value="clients" <?= $item['target_audience'] === 'clients' ? 'selected' : '' ?>>Apenas Clientes</option>
                                <option value="admins" <?= $item['target_audience'] === 'admins' ? 'selected' : '' ?>>Apenas Administradores</option>
                                <option value="specific" <?= $item['target_audience'] === 'specific' ? 'selected' : '' ?>>Clientes Específicos</option>
                            </select>
                        </div>

                        <div class="mb-3" id="clientSelection" style="display: <?= $item['target_audience'] === 'specific' ? 'block' : 'none' ?>;">
                            <label class="form-label">Selecionar Clientes</label>
                            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem; padding: 0.5rem;">
                                <?php foreach ($clients as $client): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="target_client_ids[]" value="<?= (int)$client['id'] ?>" id="client_<?= (int)$client['id'] ?>"
                                               <?= is_array($item['target_client_ids']) && in_array((int)$client['id'], $item['target_client_ids']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="client_<?= (int)$client['id'] ?>">
                                            <?= h($client['first_name'] . ' ' . $client['last_name']) ?> (<?= h($client['email']) ?>)
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="show_on_dashboard" name="show_on_dashboard" value="1" <?= (int)$item['show_on_dashboard'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="show_on_dashboard">
                                    Exibir no Dashboard
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="show_on_client_area" name="show_on_client_area" value="1" <?= (int)$item['show_on_client_area'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="show_on_client_area">
                                    Exibir na Área do Cliente
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Período de Exibição</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="start_date" class="form-label">Data/Hora de Início</label>
                            <input type="datetime-local" class="form-control" id="start_date" name="start_date" value="<?= h($item['start_date']) ?>">
                            <small class="text-muted">Deixe em branco para exibir imediatamente</small>
                        </div>

                        <div class="mb-3">
                            <label for="end_date" class="form-label">Data/Hora de Fim</label>
                            <input type="datetime-local" class="form-control" id="end_date" name="end_date" value="<?= h($item['end_date']) ?>">
                            <small class="text-muted">Deixe em branco para exibir indefinidamente</small>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Configurações</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (int)$item['is_active'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">
                                    Anúncio Ativo
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_dismissible" name="is_dismissible" value="1" <?= (int)$item['is_dismissible'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_dismissible">
                                    Permite Fechar
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="las la-save me-1"></i> Salvar
            </button>
            <a href="/admin/announcements.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
function toggleClientSelection() {
    const target = document.getElementById('target_audience').value;
    const clientSelection = document.getElementById('clientSelection');
    clientSelection.style.display = target === 'specific' ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

