<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

// Garantir UTF-8 na conexão
db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
db()->exec("SET CHARACTER SET utf8mb4");
db()->exec("SET character_set_connection=utf8mb4");

$type = $_GET['type'] ?? 'section'; // 'section' ou 'link'
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sectionId = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;

$page_title = $id ? 'Editar ' . ($type === 'section' ? 'seção' : 'link') : 'Nova ' . ($type === 'section' ? 'seção' : 'link');
$active = 'footer';

$error = null;

if ($type === 'section') {
    $footer_item = [
        'title' => '',
        'sort_order' => 0,
        'is_enabled' => 1,
    ];

    // Processar POST ANTES de qualquer output HTML
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify($_POST['_csrf'] ?? null);

        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') $error = 'O título é obrigatório.';

        $data = [
            'title' => $title,
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_enabled' => isset($_POST['is_enabled']) ? 1 : 0,
        ];

        if (!$error) {
            // Garantir UTF-8 antes de salvar
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            db()->exec("SET CHARACTER SET utf8mb4");
            db()->exec("SET character_set_connection=utf8mb4");
            
            if ($id > 0) {
                $stmt = db()->prepare("UPDATE footer_sections SET title=:title, sort_order=:sort_order, is_enabled=:is_enabled WHERE id=:id");
                $data['id'] = $id;
                $stmt->execute($data);
            } else {
                $stmt = db()->prepare("INSERT INTO footer_sections (title, sort_order, is_enabled) VALUES (:title, :sort_order, :is_enabled)");
                $stmt->execute($data);
            }
            header('Location: /admin/footer.php');
            exit;
        }
        // Se houver erro, manter os dados do POST para exibir no formulário
        $footer_item = array_merge($footer_item, $data);
    }
    
    // Buscar dados do item se estiver editando (ANTES do layout para ter os dados disponíveis)
    if ($id > 0) {
        $stmt = db()->prepare("SELECT * FROM footer_sections WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            require_once __DIR__ . '/partials/layout_start.php';
            echo '<div class="alert alert-danger">Seção não encontrada.</div>';
            echo '<a class="btn btn-outline-dark" href="/admin/footer.php">Voltar</a>';
            require_once __DIR__ . '/partials/layout_end.php';
            exit;
        }
        $footer_item = array_merge($footer_item, $row);
    }
} else { // type === 'link'
    $footer_item = [
        'section_id' => $sectionId ?: null,
        'label' => '',
        'url' => '',
        'sort_order' => 0,
        'is_enabled' => 1,
    ];

    // Processar POST ANTES de qualquer output HTML
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify($_POST['_csrf'] ?? null);

        $label = trim((string)($_POST['label'] ?? ''));
        $url = trim((string)($_POST['url'] ?? ''));
        $sectionIdPost = (int)($_POST['section_id'] ?? 0);
        
        if ($label === '') $error = 'O rótulo é obrigatório.';
        if ($url === '') $error = 'A URL é obrigatória.';
        if ($sectionIdPost <= 0) $error = 'A seção é obrigatória.';

        $data = [
            'section_id' => $sectionIdPost,
            'label' => $label,
            'url' => $url,
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_enabled' => isset($_POST['is_enabled']) ? 1 : 0,
        ];

        if (!$error) {
            // Garantir UTF-8 antes de salvar
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            db()->exec("SET CHARACTER SET utf8mb4");
            db()->exec("SET character_set_connection=utf8mb4");
            
            if ($id > 0) {
                $stmt = db()->prepare("UPDATE footer_links SET section_id=:section_id, label=:label, url=:url, sort_order=:sort_order, is_enabled=:is_enabled WHERE id=:id");
                $data['id'] = $id;
                $stmt->execute($data);
            } else {
                $stmt = db()->prepare("INSERT INTO footer_links (section_id, label, url, sort_order, is_enabled) VALUES (:section_id, :label, :url, :sort_order, :is_enabled)");
                $stmt->execute($data);
            }
            header('Location: /admin/footer.php');
            exit;
        }
        // Se houver erro, manter os dados do POST para exibir no formulário
        $footer_item = array_merge($footer_item, $data);
    }
    
    // Buscar dados do item se estiver editando (ANTES do layout para ter os dados disponíveis)
    if ($id > 0) {
        $stmt = db()->prepare("SELECT * FROM footer_links WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            require_once __DIR__ . '/partials/layout_start.php';
            echo '<div class="alert alert-danger">Link não encontrado.</div>';
            echo '<a class="btn btn-outline-dark" href="/admin/footer.php">Voltar</a>';
            require_once __DIR__ . '/partials/layout_end.php';
            exit;
        }
        $footer_item = array_merge($footer_item, $row);
    }
    
    $sections = db()->query("SELECT id, title FROM footer_sections ORDER BY sort_order ASC, id ASC")->fetchAll();
}

require_once __DIR__ . '/partials/layout_start.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<div class="card shadow-sm rounded-3">
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

            <?php
            // Garantir que $footer_item está definido
            if (!isset($footer_item) || !is_array($footer_item)) {
                if ($type === 'section') {
                    $footer_item = [
                        'title' => '',
                        'sort_order' => 0,
                        'is_enabled' => 1,
                    ];
                } else {
                    $footer_item = [
                        'section_id' => $sectionId ?: null,
                        'label' => '',
                        'url' => '',
                        'sort_order' => 0,
                        'is_enabled' => 1,
                    ];
                }
            }
            ?>
            <?php if ($type === 'section'): ?>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Título da seção</label>
                        <input class="form-control" name="title" value="<?= h($footer_item['title'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Ordem</label>
                        <input class="form-control" type="number" name="sort_order" value="<?= h((string)($footer_item['sort_order'] ?? 0)) ?>">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_enabled" id="is_enabled" <?= ((int)($footer_item['is_enabled'] ?? 1) === 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_enabled">Ativo</label>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Seção</label>
                        <select class="form-select" name="section_id" required>
                            <option value="">Selecione uma seção</option>
                            <?php foreach ($sections as $sec): ?>
                                <option value="<?= (int)$sec['id'] ?>" <?= ((string)($footer_item['section_id'] ?? '') === (string)$sec['id']) ? 'selected' : '' ?>>
                                    <?= h($sec['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ordem</label>
                        <input class="form-control" type="number" name="sort_order" value="<?= h((string)($footer_item['sort_order'] ?? 0)) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Rótulo</label>
                        <input class="form-control" name="label" value="<?= h($footer_item['label'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">URL</label>
                        <input class="form-control" name="url" value="<?= h($footer_item['url'] ?? '') ?>" placeholder="ex: shared-hosting.html" required>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_enabled" id="is_enabled" <?= ((int)($footer_item['is_enabled'] ?? 1) === 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_enabled">Ativo</label>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Salvar</button>
                <a class="btn btn-outline-dark" href="/admin/footer.php">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>
