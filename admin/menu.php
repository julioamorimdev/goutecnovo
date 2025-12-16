<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/menu.php';

// Garantir UTF-8 na conexão
db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
db()->exec("SET CHARACTER SET utf8mb4");
db()->exec("SET character_set_connection=utf8mb4");

$page_title = 'Menu do site';
$active = 'menu';
require_once __DIR__ . '/partials/layout_start.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0 && $action === 'toggle') {
        db()->prepare("UPDATE menu_items SET is_enabled = IF(is_enabled=1,0,1) WHERE id=?")->execute([$id]);
        header('Location: /admin/menu.php');
        exit;
    }
    if ($id > 0 && ($action === 'move_up' || $action === 'move_down')) {
        $stmt = db()->prepare("SELECT id, parent_id, sort_order FROM menu_items WHERE id=?");
        $stmt->execute([$id]);
        $cur = $stmt->fetch();
        if ($cur) {
            $parentId = $cur['parent_id'];
            $sort = (int)$cur['sort_order'];

            if ($action === 'move_up') {
                $q = "SELECT id, sort_order FROM menu_items
                      WHERE parent_id <=> :parent_id
                        AND (sort_order < :sort OR (sort_order = :sort AND id < :id))
                      ORDER BY sort_order DESC, id DESC
                      LIMIT 1";
            } else {
                $q = "SELECT id, sort_order FROM menu_items
                      WHERE parent_id <=> :parent_id
                        AND (sort_order > :sort OR (sort_order = :sort AND id > :id))
                      ORDER BY sort_order ASC, id ASC
                      LIMIT 1";
            }

            $stmt2 = db()->prepare($q);
            $stmt2->execute([
                ':parent_id' => $parentId,
                ':sort' => $sort,
                ':id' => $id,
            ]);
            $neighbor = $stmt2->fetch();

            if ($neighbor) {
                $nid = (int)$neighbor['id'];
                $nsort = (int)$neighbor['sort_order'];

                $tx = db()->beginTransaction();
                try {
                    db()->prepare("UPDATE menu_items SET sort_order=? WHERE id=?")->execute([$nsort, $id]);
                    db()->prepare("UPDATE menu_items SET sort_order=? WHERE id=?")->execute([$sort, $nid]);
                    db()->commit();
                } catch (Throwable $e) {
                    if ($tx) db()->rollBack();
                }
            }
        }
        header('Location: /admin/menu.php');
        exit;
    }
    if ($id > 0 && $action === 'delete') {
        db()->prepare("DELETE FROM menu_items WHERE id=?")->execute([$id]);
        header('Location: /admin/menu.php');
        exit;
    }
}

$tree = menu_build_tree(menu_fetch_all());

function render_tree(array $items, int $level = 0): void {
    foreach ($items as $it) {
        $id = (int)$it['id'];
        $enabled = (int)$it['is_enabled'] === 1;
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
        $hasChildren = !empty($it['children']);
        echo '<tr>';
        echo '<td>' . $indent . ($hasChildren ? '<b>' : '') . h($it['label']) . ($hasChildren ? '</b>' : '') . '</td>';
        echo '<td><code>' . h($it['url']) . '</code></td>';
        echo '<td><code>' . h($it['icon_class']) . '</code></td>';
        echo '<td>' . ($enabled ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Desativado</span>') . '</td>';
        echo '<td class="text-end">';
        echo '<div class="dropdown d-inline-block">';
        echo '<button class="btn btn-sm btn-outline-dark" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Ações"><i class="las la-ellipsis-v"></i></button>';
        echo '<ul class="dropdown-menu dropdown-menu-end">';
        if ($it['parent_id'] === null) {
            echo '<li><a class="dropdown-item" href="/admin/menu_edit.php?parent_id=' . $id . '"><i class="las la-level-down-alt me-2"></i>Criar subitem</a></li>';
            echo '<li><hr class="dropdown-divider"></li>';
        }
        echo '<li><a class="dropdown-item" href="/admin/menu_edit.php?id=' . $id . '"><i class="las la-edit me-2"></i>Editar</a></li>';
        echo '<li>';
        echo '<form method="post" class="m-0">';
        echo '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
        echo '<input type="hidden" name="id" value="' . $id . '">';
        echo '<button class="dropdown-item" name="action" value="toggle" type="submit"><i class="las ' . ($enabled ? 'la-eye-slash' : 'la-eye') . ' me-2"></i>' . ($enabled ? 'Desabilitar' : 'Habilitar') . '</button>';
        echo '</form>';
        echo '</li>';
        echo '<li><hr class="dropdown-divider"></li>';
        echo '<li>';
        echo '<form method="post" class="m-0">';
        echo '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
        echo '<input type="hidden" name="id" value="' . $id . '">';
        echo '<button class="dropdown-item" name="action" value="move_up" type="submit"><i class="las la-arrow-up me-2"></i>Subir</button>';
        echo '</form>';
        echo '</li>';
        echo '<li>';
        echo '<form method="post" class="m-0">';
        echo '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
        echo '<input type="hidden" name="id" value="' . $id . '">';
        echo '<button class="dropdown-item" name="action" value="move_down" type="submit"><i class="las la-arrow-down me-2"></i>Descer</button>';
        echo '</form>';
        echo '</li>';
        echo '<li><hr class="dropdown-divider"></li>';
        echo '<li>';
        echo '<form method="post" class="m-0" onsubmit="return confirm(\'Excluir este item?\')">';
        echo '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
        echo '<input type="hidden" name="id" value="' . $id . '">';
        echo '<button class="dropdown-item text-danger" name="action" value="delete" type="submit"><i class="las la-trash me-2"></i>Excluir</button>';
        echo '</form>';
        echo '</li>';
        echo '</ul>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';

        if (!empty($it['children'])) {
            render_tree($it['children'], $level + 1);
        }
    }
}
?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
    <div class="text-body-secondary small">Gerencie abas, dropdowns, ícones, textos e visibilidade.</div>
    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="/admin/menu_edit.php"><i class="las la-plus me-1"></i>Novo item</a>
    </div>
</div>

<div class="card shadow-sm rounded-3">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>Item</th>
                    <th>URL</th>
                    <th>Ícone (classe)</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
                </thead>
                <tbody>
                <?php render_tree($tree); ?>
                </tbody>
            </table>
        </div>
        <div class="small text-body-secondary">
            Dica: para dropdown, crie um item pai com URL “#” e depois crie itens filhos apontando para esse pai.
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>


