<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/menu.php';

$page_title = 'Editar item do menu';
$active = 'menu';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page_title = $id ? 'Editar item do menu' : 'Novo item do menu';
require_once __DIR__ . '/partials/layout_start.php';
$item = [
    'label' => '',
    'url' => '',
    'icon_class' => '',
    'description' => '',
    'badge_text' => '',
    'badge_class' => '',
    'dropdown_layout' => 'default',
    'custom_html' => '',
    'is_enabled' => 1,
    'open_new_tab' => 0,
    'parent_id' => null,
    'sort_order' => 0,
];

if ($id <= 0 && isset($_GET['parent_id']) && $_GET['parent_id'] !== '') {
    $item['parent_id'] = (int)$_GET['parent_id'];
}

if ($id > 0) {
    $stmt = db()->prepare("SELECT * FROM menu_items WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        exit('Item não encontrado.');
    }
    $item = array_merge($item, $row);
}

$parents = db()->query("SELECT id, label FROM menu_items WHERE parent_id IS NULL ORDER BY sort_order ASC, id ASC")->fetchAll();

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $label = trim((string)($_POST['label'] ?? ''));
    if ($label === '') $error = 'O título é obrigatório.';

    $data = [
        'parent_id' => ($_POST['parent_id'] ?? '') !== '' ? (int)$_POST['parent_id'] : null,
        'label' => $label,
        'url' => trim((string)($_POST['url'] ?? '')),
        'icon_class' => trim((string)($_POST['icon_class'] ?? '')),
        'description' => trim((string)($_POST['description'] ?? '')),
        'badge_text' => trim((string)($_POST['badge_text'] ?? '')),
        'badge_class' => trim((string)($_POST['badge_class'] ?? '')),
        'dropdown_layout' => in_array(($_POST['dropdown_layout'] ?? 'default'), ['default','xl','mega'], true) ? (string)$_POST['dropdown_layout'] : 'default',
        'custom_html' => trim((string)($_POST['custom_html'] ?? '')),
        'is_enabled' => isset($_POST['is_enabled']) ? 1 : 0,
        'open_new_tab' => isset($_POST['open_new_tab']) ? 1 : 0,
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
    ];

    if (!$error) {
        if ($id > 0) {
            $stmt = db()->prepare("UPDATE menu_items SET parent_id=:parent_id,label=:label,url=:url,icon_class=:icon_class,description=:description,badge_text=:badge_text,badge_class=:badge_class,dropdown_layout=:dropdown_layout,custom_html=:custom_html,is_enabled=:is_enabled,open_new_tab=:open_new_tab,sort_order=:sort_order WHERE id=:id");
            $data['id'] = $id;
            $stmt->execute($data);
        } else {
            $stmt = db()->prepare("INSERT INTO menu_items (parent_id,label,url,icon_class,description,badge_text,badge_class,dropdown_layout,custom_html,is_enabled,open_new_tab,sort_order) VALUES (:parent_id,:label,:url,:icon_class,:description,:badge_text,:badge_class,:dropdown_layout,:custom_html,:is_enabled,:open_new_tab,:sort_order)");
            $stmt->execute($data);
        }
        header('Location: /admin/menu.php');
        exit;
    }
    $item = array_merge($item, $data);
}
?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<div class="card shadow-sm rounded-3">
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

            <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Título</label>
                        <input class="form-control" name="label" value="<?= h($item['label']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">URL</label>
                        <input class="form-control" name="url" value="<?= h($item['url']) ?>" placeholder="ex: index.html ou #">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Pai (dropdown)</label>
                        <select class="form-select" name="parent_id">
                            <option value="">(Sem pai — item de topo)</option>
                            <?php foreach ($parents as $p): ?>
                                <?php if ($id && (int)$p['id'] === $id) continue; ?>
                                <option value="<?= (int)$p['id'] ?>" <?= ((string)$item['parent_id'] === (string)$p['id']) ? 'selected' : '' ?>>
                                    <?= h($p['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Ordem</label>
                        <input class="form-control" type="number" name="sort_order" value="<?= h((string)$item['sort_order']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Layout (pai)</label>
                        <select class="form-select" name="dropdown_layout">
                            <option value="default" <?= ($item['dropdown_layout'] ?? 'default') === 'default' ? 'selected' : '' ?>>Default</option>
                            <option value="xl" <?= ($item['dropdown_layout'] ?? 'default') === 'xl' ? 'selected' : '' ?>>XL (mega)</option>
                            <option value="mega" <?= ($item['dropdown_layout'] ?? 'default') === 'mega' ? 'selected' : '' ?>>Mega menu</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Ícone (classe Line Awesome)</label>
                        <input class="form-control" name="icon_class" value="<?= h($item['icon_class']) ?>" placeholder="ex: las la-server fs-3 text-primary">
                        <div class="small text-body-secondary mt-1">
                            Exemplo: <code>las la-server fs-3 text-primary</code>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Descrição (dropdown)</label>
                        <input class="form-control" name="description" value="<?= h($item['description']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Badge (texto)</label>
                        <input class="form-control" name="badge_text" value="<?= h($item['badge_text']) ?>" placeholder="ex: Novo!">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Badge (classe)</label>
                        <input class="form-control" name="badge_class" value="<?= h($item['badge_class']) ?>" placeholder="ex: flex-shrink-0 badge bg-primary-subtle text-primary-emphasis fw-bold py-1">
                    </div>

                    <div class="col-12">
                        <label class="form-label">HTML customizado (opcional)</label>
                        <textarea class="form-control" rows="6" name="custom_html" placeholder="Se preencher, este HTML será renderizado diretamente no menu (uso avançado)."><?= h($item['custom_html']) ?></textarea>
                        <div class="small text-body-secondary mt-1">Use com cuidado (renderiza sem escapar).</div>
                    </div>

                    <div class="col-12 d-flex gap-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_enabled" id="is_enabled" <?= ((int)$item['is_enabled'] === 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_enabled">Ativo</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="open_new_tab" id="open_new_tab" <?= ((int)$item['open_new_tab'] === 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="open_new_tab">Abrir em nova aba</label>
                        </div>
                    </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Salvar</button>
                <a class="btn btn-outline-dark" href="/admin/menu.php">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>


