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

$page_title = $id ? 'Editar Categoria' : 'Nova Categoria';
$active = 'kb_categories';
require_once __DIR__ . '/partials/layout_start.php';

$error = null;

$item = [
    'name' => '',
    'slug' => '',
    'description' => '',
    'icon' => '',
    'parent_id' => null,
    'sort_order' => 0,
    'is_active' => 1,
];

if ($id > 0) {
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt = db()->prepare("SELECT * FROM knowledge_base_categories WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            exit('Categoria não encontrada.');
        }
        $item = array_merge($item, $row);
    } catch (Throwable $e) {
        http_response_code(404);
        exit('Erro ao buscar categoria.');
    }
}

// Buscar categorias para seleção de pai
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $categories = db()->query("SELECT id, name FROM knowledge_base_categories WHERE id != " . ($id ?: 0) . " ORDER BY name")->fetchAll();
} catch (Throwable $e) {
    $categories = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $name = trim((string)($_POST['name'] ?? ''));
    $slug = trim((string)($_POST['slug'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $icon = trim((string)($_POST['icon'] ?? ''));
    $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    if ($name === '') $error = 'O nome é obrigatório.';
    
    // Gerar slug se não fornecido
    if ($slug === '' && $name !== '') {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
    }
    
    // Verificar se o slug já existe
    if (!$error) {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $stmt = db()->prepare("SELECT id FROM knowledge_base_categories WHERE slug=? AND id != ?");
            $stmt->execute([$slug, $id]);
            if ($stmt->fetch()) {
                $error = 'Este slug já está em uso.';
            }
        } catch (Throwable $e) {
            // Ignorar erro na verificação
        }
    }
    
    // Verificar se não está tentando ser pai de si mesmo
    if (!$error && $parentId === $id) {
        $error = 'Uma categoria não pode ser pai de si mesma.';
    }

    $data = [
        'name' => $name,
        'slug' => $slug,
        'description' => $description !== '' ? $description : null,
        'icon' => $icon !== '' ? $icon : null,
        'parent_id' => $parentId,
        'sort_order' => $sortOrder,
        'is_active' => $isActive,
    ];

    if (!$error) {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            if ($id > 0) {
                $stmt = db()->prepare("UPDATE knowledge_base_categories SET name=:name, slug=:slug, description=:description, icon=:icon, parent_id=:parent_id, sort_order=:sort_order, is_active=:is_active WHERE id=:id");
                $data['id'] = $id;
                $stmt->execute($data);
                $_SESSION['success'] = 'Categoria atualizada com sucesso.';
            } else {
                $stmt = db()->prepare("INSERT INTO knowledge_base_categories (name, slug, description, icon, parent_id, sort_order, is_active) VALUES (:name, :slug, :description, :icon, :parent_id, :sort_order, :is_active)");
                $stmt->execute($data);
                $_SESSION['success'] = 'Categoria criada com sucesso.';
            }
            header('Location: /admin/kb_categories.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Erro ao salvar categoria: ' . $e->getMessage();
        }
    }
    $item = array_merge($item, $data);
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= $id ? 'Editar Categoria' : 'Nova Categoria' ?></h1>
        <a href="/admin/kb_categories.php" class="btn btn-secondary">
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
                        <h5 class="mb-0">Informações da Categoria</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= h($item['name']) ?>" required onkeyup="generateSlug()">
                        </div>

                        <div class="mb-3">
                            <label for="slug" class="form-label">Slug <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="slug" name="slug" value="<?= h($item['slug']) ?>" required>
                            <small class="text-muted">URL-friendly (gerado automaticamente a partir do nome)</small>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Descrição</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= h($item['description']) ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="icon" class="form-label">Ícone (classe CSS)</label>
                                <input type="text" class="form-control" id="icon" name="icon" value="<?= h($item['icon']) ?>" placeholder="las la-folder">
                                <small class="text-muted">Ex: las la-folder, las la-book, etc.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="parent_id" class="form-label">Categoria Pai</label>
                                <select class="form-select" id="parent_id" name="parent_id">
                                    <option value="">Nenhuma (categoria raiz)</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= (int)$cat['id'] ?>" <?= $item['parent_id'] && (int)$item['parent_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
                                            <?= h($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Configurações</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (int)$item['is_active'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">
                                    Categoria Ativa
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
                                    <div><strong>Artigos:</strong> <?= (int)$item['article_count'] ?></div>
                                    <div><strong>Criado em:</strong> <?= date('d/m/Y H:i', strtotime($item['created_at'] ?? 'now')) ?></div>
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
            <a href="/admin/kb_categories.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
function generateSlug() {
    const name = document.getElementById('name').value;
    const slug = name.toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
    document.getElementById('slug').value = slug;
}
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

