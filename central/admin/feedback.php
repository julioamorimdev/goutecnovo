<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
require_once __DIR__ . '/../../app/bootstrap.php';

$page_title = 'Feedbacks';
$active = 'feedback';
require_once __DIR__ . '/partials/layout_start.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id > 0 && $action === 'toggle') {
        db()->prepare("UPDATE feedback_items SET is_enabled = IF(is_enabled=1,0,1) WHERE id=?")->execute([$id]);
        header('Location: /admin/feedback.php');
        exit;
    }
    
    if ($id > 0 && ($action === 'move_up' || $action === 'move_down')) {
        $stmt = db()->prepare("SELECT id, sort_order FROM feedback_items WHERE id=?");
        $stmt->execute([$id]);
        $cur = $stmt->fetch();
        if ($cur) {
            $sort = (int)$cur['sort_order'];

            if ($action === 'move_up') {
                $q = "SELECT id, sort_order FROM feedback_items
                      WHERE (sort_order < :sort OR (sort_order = :sort AND id < :id))
                      ORDER BY sort_order DESC, id DESC
                      LIMIT 1";
            } else {
                $q = "SELECT id, sort_order FROM feedback_items
                      WHERE (sort_order > :sort OR (sort_order = :sort AND id > :id))
                      ORDER BY sort_order ASC, id ASC
                      LIMIT 1";
            }

            $stmt2 = db()->prepare($q);
            $stmt2->execute([
                ':sort' => $sort,
                ':id' => $id,
            ]);
            $neighbor = $stmt2->fetch();

            if ($neighbor) {
                $nid = (int)$neighbor['id'];
                $nsort = (int)$neighbor['sort_order'];

                $tx = db()->beginTransaction();
                try {
                    db()->prepare("UPDATE feedback_items SET sort_order=? WHERE id=?")->execute([$nsort, $id]);
                    db()->prepare("UPDATE feedback_items SET sort_order=? WHERE id=?")->execute([$sort, $nid]);
                    db()->commit();
                } catch (Throwable $e) {
                    if ($tx) db()->rollBack();
                }
            }
        }
        header('Location: /admin/feedback.php');
        exit;
    }
    
    if ($id > 0 && $action === 'delete') {
        db()->prepare("DELETE FROM feedback_items WHERE id=?")->execute([$id]);
        header('Location: /admin/feedback.php');
        exit;
    }
}

$feedbacks = db()->query("SELECT * FROM feedback_items ORDER BY sort_order ASC, id ASC")->fetchAll();
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
    <div class="text-body-secondary small">Gerencie os depoimentos de clientes exibidos na página inicial.</div>
    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="/admin/feedback_edit.php"><i class="las la-plus me-1"></i>Novo feedback</a>
    </div>
</div>

<div class="card shadow-sm rounded-3">
    <div class="card-body">
        <?php if (empty($feedbacks)): ?>
            <div class="text-center text-body-secondary py-5">
                <p>Nenhum feedback cadastrado ainda.</p>
                <a class="btn btn-primary" href="/admin/feedback_edit.php">Criar primeiro feedback</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Imagem</th>
                            <th>Nome</th>
                            <th>Cargo/Empresa</th>
                            <th>Título</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedbacks as $fb): ?>
                            <?php
                            $id = (int)$fb['id'];
                            $enabled = (int)$fb['is_enabled'] === 1;
                            ?>
                            <tr>
                                <td>
                                    <img src="<?= h($fb['person_image']) ?>" alt="<?= h($fb['person_name']) ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%;">
                                </td>
                                <td>
                                    <strong><?= h($fb['person_name']) ?></strong>
                                </td>
                                <td>
                                    <small class="text-body-secondary"><?= h($fb['person_role']) ?></small>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 200px;" title="<?= h($fb['title']) ?>">
                                        <?= h($fb['title']) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($enabled): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Desativado</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown d-inline-block">
                                        <button class="btn btn-sm btn-outline-dark" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Ações">
                                            <i class="las la-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="/admin/feedback_edit.php?id=<?= $id ?>">
                                                    <i class="las la-edit me-2"></i>Editar
                                                </a>
                                            </li>
                                            <li>
                                                <form method="post" class="m-0">
                                                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                                    <input type="hidden" name="id" value="<?= $id ?>">
                                                    <button class="dropdown-item" name="action" value="toggle" type="submit">
                                                        <i class="las <?= $enabled ? 'la-eye-slash' : 'la-eye' ?> me-2"></i>
                                                        <?= $enabled ? 'Desabilitar' : 'Habilitar' ?>
                                                    </button>
                                                </form>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="post" class="m-0">
                                                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                                    <input type="hidden" name="id" value="<?= $id ?>">
                                                    <button class="dropdown-item" name="action" value="move_up" type="submit">
                                                        <i class="las la-arrow-up me-2"></i>Subir
                                                    </button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="post" class="m-0">
                                                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                                    <input type="hidden" name="id" value="<?= $id ?>">
                                                    <button class="dropdown-item" name="action" value="move_down" type="submit">
                                                        <i class="las la-arrow-down me-2"></i>Descer
                                                    </button>
                                                </form>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="post" class="m-0" onsubmit="return confirm('Excluir este feedback?')">
                                                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                                    <input type="hidden" name="id" value="<?= $id ?>">
                                                    <button class="dropdown-item text-danger" name="action" value="delete" type="submit">
                                                        <i class="las la-trash me-2"></i>Excluir
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>
