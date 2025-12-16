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

$page_title = $id ? 'Editar Download' : 'Novo Download';
$active = 'downloads';
require_once __DIR__ . '/partials/layout_start.php';

$error = null;
$uploadDir = __DIR__ . '/../uploads/downloads/';

// Criar diretório se não existir
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

$item = [
    'title' => '',
    'description' => '',
    'category' => 'general',
    'access_level' => 'public',
    'required_client_ids' => null,
    'version' => '',
    'is_active' => 1,
    'sort_order' => 0,
    'file_path' => '',
    'file_name' => '',
    'file_size' => null,
    'file_type' => '',
];

if ($id > 0) {
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt = db()->prepare("SELECT * FROM downloads WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            exit('Download não encontrado.');
        }
        $item = array_merge($item, $row);
        if ($item['required_client_ids']) {
            $item['required_client_ids'] = json_decode($item['required_client_ids'], true);
        }
    } catch (Throwable $e) {
        http_response_code(404);
        exit('Erro ao buscar download.');
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
    $description = trim((string)($_POST['description'] ?? ''));
    $category = trim((string)($_POST['category'] ?? 'general'));
    $accessLevel = trim((string)($_POST['access_level'] ?? 'public'));
    $version = trim((string)($_POST['version'] ?? ''));
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    
    $requiredClientIds = null;
    if ($accessLevel === 'specific' && isset($_POST['required_client_ids']) && is_array($_POST['required_client_ids'])) {
        $requiredClientIds = array_map('intval', $_POST['required_client_ids']);
        $requiredClientIds = array_filter($requiredClientIds, function($id) { return $id > 0; });
        $requiredClientIds = !empty($requiredClientIds) ? json_encode(array_values($requiredClientIds)) : null;
    }
    
    if ($title === '') $error = 'O título é obrigatório.';
    
    if (!in_array($category, ['general', 'documentation', 'software', 'template', 'other'], true)) {
        $category = 'general';
    }
    
    if (!in_array($accessLevel, ['public', 'clients', 'admins', 'specific'], true)) {
        $accessLevel = 'public';
    }
    
    // Processar upload de arquivo
    $filePath = $item['file_path'];
    $fileName = $item['file_name'];
    $fileSize = $item['file_size'];
    $fileType = $item['file_type'];
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadedFile = $_FILES['file'];
        $originalName = $uploadedFile['name'];
        $tmpPath = $uploadedFile['tmp_name'];
        $fileSize = $uploadedFile['size'];
        $fileType = $uploadedFile['type'];
        
        // Validar tipo de arquivo (opcional - você pode adicionar validações mais específicas)
        $allowedTypes = ['application/pdf', 'application/zip', 'application/x-zip-compressed', 'application/octet-stream', 'text/plain'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        // Gerar nome único
        $fileName = $originalName;
        $filePath = $uploadDir . uniqid() . '_' . basename($originalName);
        
        if (move_uploaded_file($tmpPath, $filePath)) {
            // Remover arquivo antigo se existir
            if ($id > 0 && $item['file_path'] && file_exists($item['file_path'])) {
                @unlink($item['file_path']);
            }
        } else {
            $error = 'Erro ao fazer upload do arquivo.';
        }
    } elseif (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $error = 'Erro no upload: ' . $_FILES['file']['error'];
    }
    
    // Se é novo e não tem arquivo, erro
    if ($id === 0 && !$filePath) {
        $error = 'É necessário fazer upload de um arquivo.';
    }

    $data = [
        'title' => $title,
        'description' => $description !== '' ? $description : null,
        'category' => $category,
        'access_level' => $accessLevel,
        'required_client_ids' => $requiredClientIds,
        'version' => $version !== '' ? $version : null,
        'is_active' => $isActive,
        'sort_order' => $sortOrder,
        'file_path' => $filePath,
        'file_name' => $fileName,
        'file_size' => $fileSize,
        'file_type' => $fileType !== '' ? $fileType : null,
        'created_by' => $id === 0 ? (int)($_SESSION['admin_user_id'] ?? 0) : null,
    ];

    if (!$error) {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            if ($id > 0) {
                $stmt = db()->prepare("UPDATE downloads SET title=:title, description=:description, category=:category, access_level=:access_level, required_client_ids=:required_client_ids, version=:version, is_active=:is_active, sort_order=:sort_order, file_path=:file_path, file_name=:file_name, file_size=:file_size, file_type=:file_type WHERE id=:id");
                $data['id'] = $id;
                $stmt->execute($data);
                $_SESSION['success'] = 'Download atualizado com sucesso.';
            } else {
                $stmt = db()->prepare("INSERT INTO downloads (title, description, category, access_level, required_client_ids, version, is_active, sort_order, file_path, file_name, file_size, file_type, created_by) VALUES (:title, :description, :category, :access_level, :required_client_ids, :version, :is_active, :sort_order, :file_path, :file_name, :file_size, :file_type, :created_by)");
                $stmt->execute($data);
                $_SESSION['success'] = 'Download criado com sucesso.';
            }
            header('Location: /admin/downloads.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Erro ao salvar download: ' . $e->getMessage();
        }
    }
    $item = array_merge($item, $data);
    if ($requiredClientIds) {
        $item['required_client_ids'] = json_decode($requiredClientIds, true);
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= $id ? 'Editar Download' : 'Novo Download' ?></h1>
        <a href="/admin/downloads.php" class="btn btn-secondary">
            <i class="las la-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Informações do Download</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">Título <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" value="<?= h($item['title']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Descrição</label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?= h($item['description']) ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="category" class="form-label">Categoria <span class="text-danger">*</span></label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="general" <?= $item['category'] === 'general' ? 'selected' : '' ?>>Geral</option>
                                    <option value="documentation" <?= $item['category'] === 'documentation' ? 'selected' : '' ?>>Documentação</option>
                                    <option value="software" <?= $item['category'] === 'software' ? 'selected' : '' ?>>Software</option>
                                    <option value="template" <?= $item['category'] === 'template' ? 'selected' : '' ?>>Template</option>
                                    <option value="other" <?= $item['category'] === 'other' ? 'selected' : '' ?>>Outros</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="version" class="form-label">Versão</label>
                                <input type="text" class="form-control" id="version" name="version" value="<?= h($item['version']) ?>" placeholder="Ex: 1.0.0">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="file" class="form-label">Arquivo <?= $id > 0 ? '(deixe em branco para manter o atual)' : '<span class="text-danger">*</span>' ?></label>
                            <input type="file" class="form-control" id="file" name="file" <?= $id === 0 ? 'required' : '' ?>>
                            <?php if ($id > 0 && $item['file_name']): ?>
                                <small class="text-muted">Arquivo atual: <?= h($item['file_name']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Nível de Acesso</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="access_level" class="form-label">Acesso <span class="text-danger">*</span></label>
                            <select class="form-select" id="access_level" name="access_level" required onchange="toggleClientSelection()">
                                <option value="public" <?= $item['access_level'] === 'public' ? 'selected' : '' ?>>Público</option>
                                <option value="clients" <?= $item['access_level'] === 'clients' ? 'selected' : '' ?>>Apenas Clientes</option>
                                <option value="admins" <?= $item['access_level'] === 'admins' ? 'selected' : '' ?>>Apenas Administradores</option>
                                <option value="specific" <?= $item['access_level'] === 'specific' ? 'selected' : '' ?>>Clientes Específicos</option>
                            </select>
                        </div>

                        <div class="mb-3" id="clientSelection" style="display: <?= $item['access_level'] === 'specific' ? 'block' : 'none' ?>;">
                            <label class="form-label">Selecionar Clientes</label>
                            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem; padding: 0.5rem;">
                                <?php foreach ($clients as $client): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="required_client_ids[]" value="<?= (int)$client['id'] ?>" id="client_<?= (int)$client['id'] ?>"
                                               <?= is_array($item['required_client_ids']) && in_array((int)$client['id'], $item['required_client_ids']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="client_<?= (int)$client['id'] ?>">
                                            <?= h($client['first_name'] . ' ' . $client['last_name']) ?> (<?= h($client['email']) ?>)
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Configurações</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (int)$item['is_active'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">
                                    Download Ativo
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="sort_order" class="form-label">Ordem de Exibição</label>
                            <input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= (int)$item['sort_order'] ?>" min="0">
                            <small class="text-muted">Menor número aparece primeiro</small>
                        </div>

                        <?php if ($id > 0): ?>
                            <div class="mb-3">
                                <label class="form-label">Estatísticas</label>
                                <div class="small text-muted">
                                    <div><strong>Downloads:</strong> <?= number_format((int)$item['download_count']) ?></div>
                                    <div><strong>Criado em:</strong> <?= date('d/m/Y H:i', strtotime($item['created_at'] ?? 'now')) ?></div>
                                    <?php if ($item['file_size']): ?>
                                        <div><strong>Tamanho:</strong> <?php
                                        function formatFileSize($bytes) {
                                            if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
                                            elseif ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
                                            elseif ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
                                            else return $bytes . ' bytes';
                                        }
                                        echo formatFileSize((int)$item['file_size']);
                                        ?></div>
                                    <?php endif; ?>
                                </div>
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
            <a href="/admin/downloads.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
function toggleClientSelection() {
    const access = document.getElementById('access_level').value;
    const clientSelection = document.getElementById('clientSelection');
    clientSelection.style.display = access === 'specific' ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

