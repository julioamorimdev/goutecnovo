<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
require_once __DIR__ . '/../../app/bootstrap.php';

$type = $_GET['type'] ?? 'section'; // 'section' ou 'link'
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sectionId = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;

$page_title = $id ? 'Editar ' . ($type === 'section' ? 'seção' : 'link') : 'Nova ' . ($type === 'section' ? 'seção' : 'link');
$active = 'footer';
require_once __DIR__ . '/partials/layout_start.php';

$error = null;

if ($type === 'section') {
    $item = [
        'title' => '',
        'sort_order' => 0,
        'is_enabled' => 1,
    ];

    if ($id > 0) {
        $stmt = db()->prepare("SELECT * FROM footer_sections WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            exit('Seção não encontrada.');
        }
        $item = array_merge($item, $row);
    }

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
        $item = array_merge($item, $data);
    }
} else { // type === 'link'
    $item = [
        'section_id' => $sectionId ?: null,
        'label' => '',
        'url' => '',
        'sort_order' => 0,
        'is_enabled' => 1,
    ];

    if ($id > 0) {
        $stmt = db()->prepare("SELECT * FROM footer_links WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            exit('Link não encontrado.');
        }
        $item = array_merge($item, $row);
    }

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
        $item = array_merge($item, $data);
    }
    
    $sections = db()->query("SELECT id, title FROM footer_sections ORDER BY sort_order ASC, id ASC")->fetchAll();
}
?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<div class="card shadow-sm rounded-3">
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

            <?php if ($type === 'section'): ?>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Título da seção</label>
                        <input class="form-control" name="title" value="<?= h($item['title']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Ordem</label>
                        <input class="form-control" type="number" name="sort_order" value="<?= h((string)$item['sort_order']) ?>">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_enabled" id="is_enabled" <?= ((int)$item['is_enabled'] === 1) ? 'checked' : '' ?>>
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
                                <option value="<?= (int)$sec['id'] ?>" <?= ((string)$item['section_id'] === (string)$sec['id']) ? 'selected' : '' ?>>
                                    <?= h($sec['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ordem</label>
                        <input class="form-control" type="number" name="sort_order" value="<?= h((string)$item['sort_order']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Rótulo</label>
                        <input class="form-control" name="label" value="<?= h($item['label']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">URL</label>
                        <input class="form-control" name="url" value="<?= h($item['url']) ?>" placeholder="ex: shared-hosting.html" required>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_enabled" id="is_enabled" <?= ((int)$item['is_enabled'] === 1) ? 'checked' : '' ?>>
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
