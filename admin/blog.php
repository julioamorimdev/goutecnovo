<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Blog';
$active = 'blog';
require_once __DIR__ . '/partials/layout_start.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id > 0 && $action === 'toggle') {
        db()->prepare("UPDATE blog_posts SET is_enabled = IF(is_enabled=1,0,1) WHERE id=?")->execute([$id]);
        header('Location: /admin/blog.php');
        exit;
    }
    
    if ($id > 0 && $action === 'toggle_featured') {
        // Se marcar como featured, desmarcar os outros
        if (isset($_POST['is_featured']) && (int)$_POST['is_featured'] === 1) {
            db()->prepare("UPDATE blog_posts SET is_featured=0 WHERE is_featured=1")->execute();
        }
        db()->prepare("UPDATE blog_posts SET is_featured = IF(is_featured=1,0,1) WHERE id=?")->execute([$id]);
        header('Location: /admin/blog.php');
        exit;
    }
    
    if ($id > 0 && ($action === 'move_up' || $action === 'move_down')) {
        $stmt = db()->prepare("SELECT id, sort_order FROM blog_posts WHERE id=?");
        $stmt->execute([$id]);
        $cur = $stmt->fetch();
        if ($cur) {
            $sort = (int)$cur['sort_order'];

            if ($action === 'move_up') {
                $q = "SELECT id, sort_order FROM blog_posts
                      WHERE (sort_order < :sort OR (sort_order = :sort AND id < :id))
                      ORDER BY sort_order DESC, id DESC
                      LIMIT 1";
            } else {
                $q = "SELECT id, sort_order FROM blog_posts
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
                    db()->prepare("UPDATE blog_posts SET sort_order=? WHERE id=?")->execute([$nsort, $id]);
                    db()->prepare("UPDATE blog_posts SET sort_order=? WHERE id=?")->execute([$sort, $nid]);
                    db()->commit();
                } catch (Throwable $e) {
                    if ($tx) db()->rollBack();
                }
            }
        }
        header('Location: /admin/blog.php');
        exit;
    }
    
    if ($id > 0 && $action === 'delete') {
        db()->prepare("DELETE FROM blog_posts WHERE id=?")->execute([$id]);
        header('Location: /admin/blog.php');
        exit;
    }
}

$posts = db()->query("SELECT * FROM blog_posts ORDER BY is_featured DESC, sort_order ASC, id ASC")->fetchAll();
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
    <div class="text-body-secondary small">Gerencie os artigos do blog exibidos na página inicial.</div>
    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="/admin/blog_edit.php"><i class="las la-plus me-1"></i>Novo artigo</a>
    </div>
</div>

<div class="card shadow-sm rounded-3">
    <div class="card-body">
        <?php if (empty($posts)): ?>
            <div class="text-center text-body-secondary py-5">
                <p>Nenhum artigo cadastrado ainda.</p>
                <a class="btn btn-primary" href="/admin/blog_edit.php">Criar primeiro artigo</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Imagem</th>
                            <th>Título</th>
                            <th>Autor</th>
                            <th>Data</th>
                            <th>Destaque</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post): ?>
                            <?php
                            $postId = (int)$post['id'];
                            $enabled = (int)$post['is_enabled'] === 1;
                            $featured = (int)$post['is_featured'] === 1;
                            ?>
                            <tr>
                                <td>
                                    <?php if (!empty($post['image'])): ?>
                                        <div style="width: 80px; height: 60px; position: relative; border: 1px solid #dee2e6; border-radius: 4px; overflow: hidden; background: #f8f9fa;">
                                            <img src="<?= h($post['image']) ?>" alt="<?= h($post['title']) ?>" style="width: 100%; height: 100%; object-fit: cover; display: block;" onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\'width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:10px;color:#6c757d;\'>Sem imagem</div>';">
                                        </div>
                                    <?php else: ?>
                                        <div style="width: 80px; height: 60px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #6c757d;">
                                            Sem imagem
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= h($post['title']) ?></strong>
                                </td>
                                <td>
                                    <small class="text-body-secondary"><?= h($post['author']) ?></small>
                                </td>
                                <td>
                                    <small><?= date('d/m/Y', strtotime($post['published_date'])) ?></small>
                                </td>
                                <td>
                                    <?php if ($featured): ?>
                                        <span class="badge bg-warning">Destaque</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Normal</span>
                                    <?php endif; ?>
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
                                                <a class="dropdown-item" href="/admin/blog_edit.php?id=<?= $postId ?>">
                                                    <i class="las la-edit me-2"></i>Editar
                                                </a>
                                            </li>
                                            <li>
                                                <form method="post" class="m-0">
                                                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                                    <input type="hidden" name="id" value="<?= $postId ?>">
                                                    <button class="dropdown-item" name="action" value="toggle_featured" type="submit">
                                                        <i class="las <?= $featured ? 'la-star' : 'la-star-o' ?> me-2"></i>
                                                        <?= $featured ? 'Remover destaque' : 'Marcar como destaque' ?>
                                                    </button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="post" class="m-0">
                                                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                                    <input type="hidden" name="id" value="<?= $postId ?>">
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
                                                    <input type="hidden" name="id" value="<?= $postId ?>">
                                                    <button class="dropdown-item" name="action" value="move_up" type="submit">
                                                        <i class="las la-arrow-up me-2"></i>Subir
                                                    </button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="post" class="m-0">
                                                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                                    <input type="hidden" name="id" value="<?= $postId ?>">
                                                    <button class="dropdown-item" name="action" value="move_down" type="submit">
                                                        <i class="las la-arrow-down me-2"></i>Descer
                                                    </button>
                                                </form>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="post" class="m-0" onsubmit="return confirm('Excluir este artigo?')">
                                                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                                    <input type="hidden" name="id" value="<?= $postId ?>">
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
            <div class="mt-3 small text-body-secondary">
                <strong>Nota:</strong> Apenas um artigo pode ser marcado como destaque (principal). Os demais aparecerão na lateral.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>
