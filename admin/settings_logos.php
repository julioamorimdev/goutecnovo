<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

$page_title = 'Configurações • Logos';
$active = 'settings';
require_once __DIR__ . '/partials/layout_start.php';

function logo_current(string $theme): ?array {
    $stmt = db()->prepare("
        SELECT *
        FROM site_logos
        WHERE theme = ?
          AND is_deleted = 0
          AND (start_at IS NULL OR start_at <= NOW())
          AND (end_at IS NULL OR end_at >= NOW())
        ORDER BY COALESCE(start_at, created_at) DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute([$theme]);
    return $stmt->fetch() ?: null;
}

function logo_list(string $theme): array {
    $stmt = db()->prepare("SELECT * FROM site_logos WHERE theme=? ORDER BY created_at DESC, id DESC LIMIT 50");
    $stmt->execute([$theme]);
    return $stmt->fetchAll();
}

function ensure_logo_dir(): string {
    $dir = __DIR__ . '/../assets/img/logos';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    return $dir;
}

$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $action = (string)($_POST['action'] ?? '');
    $theme = (string)($_POST['theme'] ?? '');
    if (!in_array($theme, ['light','dark'], true)) $theme = 'light';

    if ($action === 'upload') {
        if (empty($_FILES['logo_file']) || $_FILES['logo_file']['error'] !== UPLOAD_ERR_OK) {
            $err = 'Falha no upload.';
        } else {
            $f = $_FILES['logo_file'];
            $orig = (string)($f['name'] ?? 'logo');
            $tmp = (string)$f['tmp_name'];
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (!in_array($ext, ['png','jpg','jpeg','webp','svg'], true)) {
                $err = 'Formato inválido. Use png, jpg, webp ou svg.';
            } else {
                $dir = ensure_logo_dir();
                $stamp = date('Ymd_His');
                $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', pathinfo($orig, PATHINFO_FILENAME));
                $filename = "{$theme}_{$stamp}_{$safe}.{$ext}";
                $dest = $dir . '/' . $filename;

                if (!move_uploaded_file($tmp, $dest)) {
                    $err = 'Não foi possível salvar o arquivo.';
                } else {
                    $start_at = trim((string)($_POST['start_at'] ?? '')) ?: null;
                    $end_at = trim((string)($_POST['end_at'] ?? '')) ?: null;
                    if ($start_at !== null) $start_at = str_replace('T',' ', $start_at) . ':00';
                    if ($end_at !== null) $end_at = str_replace('T',' ', $end_at) . ':00';

                    $publicPath = '/assets/img/logos/' . $filename;
                    $stmt = db()->prepare("INSERT INTO site_logos (theme,file_path,original_name,start_at,end_at) VALUES (?,?,?,?,?)");
                    $stmt->execute([$theme, $publicPath, $orig, $start_at, $end_at]);
                    $msg = 'Logo salva com sucesso.';
                }
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = db()->prepare("SELECT * FROM site_logos WHERE id=? LIMIT 1");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row) {
                db()->prepare("UPDATE site_logos SET is_deleted=1 WHERE id=?")->execute([$id]);
                // tenta remover arquivo
                $fp = (string)$row['file_path'];
                if (str_starts_with($fp, '/assets/img/logos/')) {
                    $real = __DIR__ . '/..' . $fp;
                    if (is_file($real)) @unlink($real);
                }
                $msg = 'Logo excluída.';
            }
        }
    }

    if ($action === 'activate_now') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // ativa agora: start_at = NOW, end_at = NULL
            db()->prepare("UPDATE site_logos SET start_at=NOW(), end_at=NULL, is_deleted=0 WHERE id=?")->execute([$id]);
            $msg = 'Logo ativada agora.';
        }
    }

    if ($action === 'reset_default') {
        // “voltar para a principal”: encerra quaisquer logos vigentes (end_at=NOW)
        db()->prepare("UPDATE site_logos SET end_at=NOW() WHERE theme=? AND is_deleted=0 AND (end_at IS NULL OR end_at > NOW())")->execute([$theme]);
        $msg = 'Voltando para a logo principal do tema.';
    }
}

$currentLight = logo_current('light');
$currentDark = logo_current('dark');
$listLight = logo_list('light');
$listDark = logo_list('dark');

$defaultLight = '/assets/img/logo-light.png';
$defaultDark = '/assets/img/logo-dark.png';
?>

<?php if ($msg): ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card shadow-sm rounded-3 h-100">
            <div class="card-body">
                <h5 class="mb-1">Logo do menu — Tema escuro</h5>
                <div class="text-body-secondary small mb-3">Preview atual (quando o site estiver no tema escuro).</div>
                <div class="p-3 rounded-3 bg-dark d-flex align-items-center justify-content-center" style="min-height:110px;">
                    <img src="<?= h($currentLight['file_path'] ?? $defaultLight) ?>" alt="Logo escura" style="max-height:42px;">
                </div>

                <form class="mt-3" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="upload">
                    <input type="hidden" name="theme" value="light">
                    <div class="row g-2 align-items-end">
                        <div class="col-12">
                            <label class="form-label">Enviar nova logo</label>
                            <input class="form-control" type="file" name="logo_file" accept=".png,.jpg,.jpeg,.webp,.svg" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Programar início (opcional)</label>
                            <input class="form-control" type="datetime-local" name="start_at">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Programar fim (opcional)</label>
                            <input class="form-control" type="datetime-local" name="end_at">
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-primary" type="submit"><i class="las la-save me-1"></i>Salvar</button>
                            <button class="btn btn-outline-dark" type="submit" name="action" value="reset_default" formnovalidate><i class="las la-undo me-1"></i>Voltar para padrão</button>
                        </div>
                    </div>
                </form>

                <hr class="my-4">
                <div class="d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">Anteriores (escuro)</h6>
                    <span class="small text-body-secondary">Últimas 50</span>
                </div>
                <div class="table-responsive mt-2">
                    <table class="table table-sm align-middle">
                        <thead>
                        <tr>
                            <th>Preview</th>
                            <th>Janela</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($listLight as $l): ?>
                            <?php
                            $activeNow = ($currentLight && (int)$currentLight['id'] === (int)$l['id']);
                            $win = trim((string)$l['start_at'] ?: 'agora') . ' → ' . trim((string)$l['end_at'] ?: 'indefinido');
                            ?>
                            <tr>
                                <td><img src="<?= h($l['file_path']) ?>" style="height:24px;" alt=""></td>
                                <td class="small text-body-secondary"><?= h($win) ?></td>
                                <td><?= $activeNow ? '<span class="badge bg-success">Ativa</span>' : ((int)$l['is_deleted'] ? '<span class="badge bg-secondary">Excluída</span>' : '<span class="badge bg-light text-dark">Histórico</span>') ?></td>
                                <td class="text-end">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="theme" value="light">
                                        <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                                        <button class="btn btn-sm btn-outline-dark" name="action" value="activate_now" type="submit">Ativar</button>
                                    </form>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Excluir essa logo?')">
                                        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="theme" value="light">
                                        <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" name="action" value="delete" type="submit">Excluir</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm rounded-3 h-100">
            <div class="card-body">
                <h5 class="mb-1">Logo do menu — Tema claro</h5>
                <div class="text-body-secondary small mb-3">Preview atual (quando o site estiver no tema claro).</div>
                <div class="p-3 rounded-3 bg-white border d-flex align-items-center justify-content-center" style="min-height:110px;">
                    <img src="<?= h($currentDark['file_path'] ?? $defaultDark) ?>" alt="Logo clara" style="max-height:42px;">
                </div>

                <form class="mt-3" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="upload">
                    <input type="hidden" name="theme" value="dark">
                    <div class="row g-2 align-items-end">
                        <div class="col-12">
                            <label class="form-label">Enviar nova logo</label>
                            <input class="form-control" type="file" name="logo_file" accept=".png,.jpg,.jpeg,.webp,.svg" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Programar início (opcional)</label>
                            <input class="form-control" type="datetime-local" name="start_at">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Programar fim (opcional)</label>
                            <input class="form-control" type="datetime-local" name="end_at">
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-primary" type="submit"><i class="las la-save me-1"></i>Salvar</button>
                            <button class="btn btn-outline-dark" type="submit" name="action" value="reset_default" formnovalidate><i class="las la-undo me-1"></i>Voltar para padrão</button>
                        </div>
                    </div>
                </form>

                <hr class="my-4">
                <div class="d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">Anteriores (claro)</h6>
                    <span class="small text-body-secondary">Últimas 50</span>
                </div>
                <div class="table-responsive mt-2">
                    <table class="table table-sm align-middle">
                        <thead>
                        <tr>
                            <th>Preview</th>
                            <th>Janela</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($listDark as $l): ?>
                            <?php
                            $activeNow = ($currentDark && (int)$currentDark['id'] === (int)$l['id']);
                            $win = trim((string)$l['start_at'] ?: 'agora') . ' → ' . trim((string)$l['end_at'] ?: 'indefinido');
                            ?>
                            <tr>
                                <td><img src="<?= h($l['file_path']) ?>" style="height:24px;" alt=""></td>
                                <td class="small text-body-secondary"><?= h($win) ?></td>
                                <td><?= $activeNow ? '<span class="badge bg-success">Ativa</span>' : ((int)$l['is_deleted'] ? '<span class="badge bg-secondary">Excluída</span>' : '<span class="badge bg-light text-dark">Histórico</span>') ?></td>
                                <td class="text-end">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="theme" value="dark">
                                        <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                                        <button class="btn btn-sm btn-outline-dark" name="action" value="activate_now" type="submit">Ativar</button>
                                    </form>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Excluir essa logo?')">
                                        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="theme" value="dark">
                                        <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" name="action" value="delete" type="submit">Excluir</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>


