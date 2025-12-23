<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
require_once __DIR__ . '/../../app/bootstrap.php';

// Processar POST ANTES de qualquer output HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $type = $_POST['type'] ?? ''; // 'section' ou 'link'
    
    if ($type === 'section') {
        if ($id > 0 && $action === 'toggle') {
            db()->prepare("UPDATE footer_sections SET is_enabled = IF(is_enabled=1,0,1) WHERE id=?")->execute([$id]);
            header('Location: /admin/footer.php');
            exit;
        }
        if ($id > 0 && ($action === 'move_up' || $action === 'move_down')) {
            $stmt = db()->prepare("SELECT id, sort_order FROM footer_sections WHERE id=?");
            $stmt->execute([$id]);
            $cur = $stmt->fetch();
            if ($cur) {
                $sort = (int)$cur['sort_order'];

                if ($action === 'move_up') {
                    $q = "SELECT id, sort_order FROM footer_sections
                          WHERE (sort_order < :sort OR (sort_order = :sort AND id < :id))
                          ORDER BY sort_order DESC, id DESC
                          LIMIT 1";
                } else {
                    $q = "SELECT id, sort_order FROM footer_sections
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
                        db()->prepare("UPDATE footer_sections SET sort_order=? WHERE id=?")->execute([$nsort, $id]);
                        db()->prepare("UPDATE footer_sections SET sort_order=? WHERE id=?")->execute([$sort, $nid]);
                        db()->commit();
                    } catch (Throwable $e) {
                        if ($tx) db()->rollBack();
                    }
                }
            }
            header('Location: /admin/footer.php');
            exit;
        }
        if ($id > 0 && $action === 'delete') {
            db()->prepare("DELETE FROM footer_sections WHERE id=?")->execute([$id]);
            header('Location: /admin/footer.php');
            exit;
        }
    }
    
    if ($type === 'link') {
        if ($id > 0 && $action === 'toggle') {
            db()->prepare("UPDATE footer_links SET is_enabled = IF(is_enabled=1,0,1) WHERE id=?")->execute([$id]);
            header('Location: /admin/footer.php');
            exit;
        }
        if ($id > 0 && ($action === 'move_up' || $action === 'move_down')) {
            $stmt = db()->prepare("SELECT id, section_id, sort_order FROM footer_links WHERE id=?");
            $stmt->execute([$id]);
            $cur = $stmt->fetch();
            if ($cur) {
                $sectionId = (int)$cur['section_id'];
                $sort = (int)$cur['sort_order'];

                if ($action === 'move_up') {
                    $q = "SELECT id, sort_order FROM footer_links
                          WHERE section_id = :section_id
                            AND (sort_order < :sort OR (sort_order = :sort AND id < :id))
                          ORDER BY sort_order DESC, id DESC
                          LIMIT 1";
                } else {
                    $q = "SELECT id, sort_order FROM footer_links
                          WHERE section_id = :section_id
                            AND (sort_order > :sort OR (sort_order = :sort AND id > :id))
                          ORDER BY sort_order ASC, id ASC
                          LIMIT 1";
                }

                $stmt2 = db()->prepare($q);
                $stmt2->execute([
                    ':section_id' => $sectionId,
                    ':sort' => $sort,
                    ':id' => $id,
                ]);
                $neighbor = $stmt2->fetch();

                if ($neighbor) {
                    $nid = (int)$neighbor['id'];
                    $nsort = (int)$neighbor['sort_order'];

                    $tx = db()->beginTransaction();
                    try {
                        db()->prepare("UPDATE footer_links SET sort_order=? WHERE id=?")->execute([$nsort, $id]);
                        db()->prepare("UPDATE footer_links SET sort_order=? WHERE id=?")->execute([$sort, $nid]);
                        db()->commit();
                    } catch (Throwable $e) {
                        if ($tx) db()->rollBack();
                    }
                }
            }
            header('Location: /admin/footer.php');
            exit;
        }
        if ($id > 0 && $action === 'delete') {
            db()->prepare("DELETE FROM footer_links WHERE id=?")->execute([$id]);
            header('Location: /admin/footer.php');
            exit;
        }
    }
}

$page_title = 'Footer do site';
$active = 'footer';
require_once __DIR__ . '/partials/layout_start.php';

$sections = db()->query("SELECT * FROM footer_sections ORDER BY sort_order ASC, id ASC")->fetchAll();
$linksBySection = [];
foreach ($sections as $sec) {
    $stmt = db()->prepare("SELECT * FROM footer_links WHERE section_id=? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([(int)$sec['id']]);
    $linksBySection[(int)$sec['id']] = $stmt->fetchAll();
}
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
    <div class="text-body-secondary small">Gerencie seções, links e conteúdo do footer.</div>
    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="/admin/footer_edit.php?type=section"><i class="las la-plus me-1"></i>Nova seção</a>
    </div>
</div>

<div class="card shadow-sm rounded-3">
    <div class="card-body">
        <?php if (empty($sections)): ?>
            <div class="text-center text-body-secondary py-5">
                <p>Nenhuma seção criada ainda.</p>
                <a class="btn btn-primary" href="/admin/footer_edit.php?type=section">Criar primeira seção</a>
            </div>
        <?php else: ?>
            <?php foreach ($sections as $sec): ?>
                <?php
                $secId = (int)$sec['id'];
                $enabled = (int)$sec['is_enabled'] === 1;
                $links = $linksBySection[$secId] ?? [];
                ?>
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div>
                            <h6 class="mb-0">
                                <?= h($sec['title']) ?>
                                <?php if (!$enabled): ?>
                                    <span class="badge bg-secondary">Desativado</span>
                                <?php endif; ?>
                            </h6>
                            <small class="text-body-secondary">
                                <?= count($links) ?> link(s)
                            </small>
                        </div>
                        <div class="d-flex gap-1">
                            <a class="btn btn-sm btn-outline-primary" href="/admin/footer_edit.php?type=link&section_id=<?= $secId ?>">
                                <i class="las la-plus me-1"></i>Adicionar link
                            </a>
                            <a class="btn btn-sm btn-outline-dark" href="/admin/footer_edit.php?type=section&id=<?= $secId ?>">
                                <i class="las la-edit"></i>
                            </a>
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-sm btn-outline-dark" type="button" data-bs-toggle="dropdown">
                                    <i class="las la-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <form method="post" class="m-0">
                                            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                            <input type="hidden" name="id" value="<?= $secId ?>">
                                            <input type="hidden" name="type" value="section">
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
                                            <input type="hidden" name="id" value="<?= $secId ?>">
                                            <input type="hidden" name="type" value="section">
                                            <button class="dropdown-item" name="action" value="move_up" type="submit">
                                                <i class="las la-arrow-up me-2"></i>Subir
                                            </button>
                                        </form>
                                    </li>
                                    <li>
                                        <form method="post" class="m-0">
                                            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                            <input type="hidden" name="id" value="<?= $secId ?>">
                                            <input type="hidden" name="type" value="section">
                                            <button class="dropdown-item" name="action" value="move_down" type="submit">
                                                <i class="las la-arrow-down me-2"></i>Descer
                                            </button>
                                        </form>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="post" class="m-0" onsubmit="return confirm('Excluir esta seção e todos os seus links?')">
                                            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                            <input type="hidden" name="id" value="<?= $secId ?>">
                                            <input type="hidden" name="type" value="section">
                                            <button class="dropdown-item text-danger" name="action" value="delete" type="submit">
                                                <i class="las la-trash me-2"></i>Excluir
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($links)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Link</th>
                                        <th>URL</th>
                                        <th>Status</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($links as $link): ?>
                                        <?php
                                        $linkId = (int)$link['id'];
                                        $linkEnabled = (int)$link['is_enabled'] === 1;
                                        ?>
                                        <tr>
                                            <td><?= h($link['label']) ?></td>
                                            <td><code><?= h($link['url']) ?></code></td>
                                            <td>
                                                <?php if ($linkEnabled): ?>
                                                    <span class="badge bg-success">Ativo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Desativado</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="dropdown d-inline-block">
                                                    <button class="btn btn-sm btn-outline-dark" type="button" data-bs-toggle="dropdown">
                                                        <i class="las la-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li>
                                                            <a class="dropdown-item" href="/admin/footer_edit.php?type=link&id=<?= $linkId ?>">
                                                                <i class="las la-edit me-2"></i>Editar
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <form method="post" class="m-0">
                                                                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                                                <input type="hidden" name="id" value="<?= $linkId ?>">
                                                                <input type="hidden" name="type" value="link">
                                                                <button class="dropdown-item" name="action" value="toggle" type="submit">
                                                                    <i class="las <?= $linkEnabled ? 'la-eye-slash' : 'la-eye' ?> me-2"></i>
                                                                    <?= $linkEnabled ? 'Desabilitar' : 'Habilitar' ?>
                                                                </button>
                                                            </form>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <form method="post" class="m-0">
                                                                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                                                <input type="hidden" name="id" value="<?= $linkId ?>">
                                                                <input type="hidden" name="type" value="link">
                                                                <button class="dropdown-item" name="action" value="move_up" type="submit">
                                                                    <i class="las la-arrow-up me-2"></i>Subir
                                                                </button>
                                                            </form>
                                                        </li>
                                                        <li>
                                                            <form method="post" class="m-0">
                                                                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                                                <input type="hidden" name="id" value="<?= $linkId ?>">
                                                                <input type="hidden" name="type" value="link">
                                                                <button class="dropdown-item" name="action" value="move_down" type="submit">
                                                                    <i class="las la-arrow-down me-2"></i>Descer
                                                                </button>
                                                            </form>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <form method="post" class="m-0" onsubmit="return confirm('Excluir este link?')">
                                                                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                                                <input type="hidden" name="id" value="<?= $linkId ?>">
                                                                <input type="hidden" name="type" value="link">
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
                    <?php else: ?>
                        <div class="text-body-secondary small">Nenhum link nesta seção.</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>
