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
$active = 'plans';
require_once __DIR__ . '/partials/layout_start.php';

$error = null;

$item = [
    'name' => '',
    'slug' => '',
    'description' => '',
    'icon_class' => '',
    'sort_order' => 0,
    'is_enabled' => 1,
];

if ($id > 0) {
    try {
        // Garantir UTF-8 na conexão
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->exec("SET CHARACTER SET utf8mb4");
        db()->exec("SET character_set_connection=utf8mb4");
        
        $stmt = db()->prepare("SELECT * FROM plan_categories WHERE id=?");
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $name = trim((string)($_POST['name'] ?? ''));
    $slug = trim((string)($_POST['slug'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $iconClass = trim((string)($_POST['icon_class'] ?? ''));
    
    if ($name === '') $error = 'O nome é obrigatório.';
    if ($slug === '') {
        // Gerar slug automaticamente se não fornecido
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
    }
    
    // Verificar se o slug já existe (exceto para o próprio item)
    if (!$error) {
        $stmt = db()->prepare("SELECT id FROM plan_categories WHERE slug=? AND id != ?");
        $stmt->execute([$slug, $id]);
        if ($stmt->fetch()) {
            $error = 'Este slug já está em uso.';
        }
    }

    $data = [
        'name' => $name,
        'slug' => $slug,
        'description' => $description,
        'icon_class' => $iconClass,
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
        'is_enabled' => isset($_POST['is_enabled']) ? 1 : 0,
    ];

    if (!$error) {
        try {
            // Garantir UTF-8 na conexão
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            db()->exec("SET CHARACTER SET utf8mb4");
            db()->exec("SET character_set_connection=utf8mb4");
            
            if ($id > 0) {
                $stmt = db()->prepare("UPDATE plan_categories SET name=:name, slug=:slug, description=:description, icon_class=:icon_class, sort_order=:sort_order, is_enabled=:is_enabled WHERE id=:id");
                $data['id'] = $id;
                $stmt->execute($data);
                $_SESSION['success'] = 'Categoria atualizada com sucesso.';
            } else {
                $stmt = db()->prepare("INSERT INTO plan_categories (name, slug, description, icon_class, sort_order, is_enabled) VALUES (:name, :slug, :description, :icon_class, :sort_order, :is_enabled)");
                $stmt->execute($data);
                $_SESSION['success'] = 'Categoria criada com sucesso.';
            }
            header('Location: /admin/plans.php');
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
        <a href="/admin/plans.php" class="btn btn-secondary">
            <i class="las la-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST">
                        <?= csrf_field() ?>
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Nome da Categoria <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= h($item['name']) ?>" required>
                            <small class="text-muted">Ex: Hospedagens, Servidores VPS, Dedicado</small>
                        </div>

                        <div class="mb-3">
                            <label for="slug" class="form-label">Slug</label>
                            <input type="text" class="form-control" id="slug" name="slug" value="<?= h($item['slug']) ?>" placeholder="Será gerado automaticamente se deixado em branco">
                            <small class="text-muted">URL amigável (ex: hospedagens, servidores-vps)</small>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Descrição</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= h($item['description']) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="icon_class" class="form-label">Classe do Ícone</label>
                            <input type="text" class="form-control" id="icon_class" name="icon_class" value="<?= h($item['icon_class']) ?>" placeholder="las la-server">
                            <small class="text-muted">Classe do ícone Line Awesome (ex: las la-server, las la-cloud)</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="sort_order" class="form-label">Ordem de Exibição</label>
                                <input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= (int)$item['sort_order'] ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="is_enabled" name="is_enabled" <?= (int)$item['is_enabled'] === 1 ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_enabled">
                                        Ativo
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="las la-save me-1"></i> Salvar
                            </button>
                            <a href="/admin/plans.php" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Gerar slug automaticamente a partir do nome
document.getElementById('name').addEventListener('input', function() {
    const slugInput = document.getElementById('slug');
    if (!slugInput.value || slugInput.dataset.autoGenerated === 'true') {
        const slug = this.value.toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
        slugInput.value = slug;
        slugInput.dataset.autoGenerated = 'true';
    }
});

document.getElementById('slug').addEventListener('input', function() {
    this.dataset.autoGenerated = 'false';
});
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>
