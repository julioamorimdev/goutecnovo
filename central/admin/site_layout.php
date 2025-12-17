<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
require_once __DIR__ . '/../../app/bootstrap.php';
require_admin();

$page_title = 'Central • Menu e Footer';
$active = 'site_layout';

$includesDir = realpath(__DIR__ . '/../includes') ?: (__DIR__ . '/../includes');
$menuFile = $includesDir . '/menu.html';
$footerFile = $includesDir . '/footer.html';

if (!is_dir($includesDir)) {
    @mkdir($includesDir, 0755, true);
}

$success = null;
$error = null;

function read_if_exists(string $path): string {
    return is_file($path) ? (string)file_get_contents($path) : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $menu = (string)($_POST['menu_html'] ?? '');
    $footer = (string)($_POST['footer_html'] ?? '');

    // Proteção simples: não permitir PHP aqui (evita execução acidental).
    if (stripos($menu, '<?php') !== false || stripos($footer, '<?php') !== false) {
        $error = 'Não é permitido inserir código PHP aqui. Use apenas HTML.';
    } else {
        $ok1 = @file_put_contents($menuFile, $menu) !== false;
        $ok2 = @file_put_contents($footerFile, $footer) !== false;
        if ($ok1 && $ok2) {
            $success = 'Menu e footer atualizados com sucesso.';
        } else {
            $error = 'Não foi possível salvar os arquivos. Verifique permissões em /central/includes.';
        }
    }
}

$menuHtml = read_if_exists($menuFile);
$footerHtml = read_if_exists($footerFile);

require_once __DIR__ . '/partials/layout_start.php';
?>

<?php if ($success): ?>
  <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
  <div class="text-body-secondary small">
    Edite os arquivos da Central: <code>/central/includes/menu.html</code> e <code>/central/includes/footer.html</code>.
  </div>
  <a class="btn btn-sm btn-outline-dark" href="/" target="_blank" rel="noopener noreferrer">
    <i class="las la-external-link-alt me-1"></i> Ver Central
  </a>
</div>

<div class="card shadow-sm rounded-3">
  <div class="card-body">
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

      <ul class="nav nav-tabs" id="layoutTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="tab-menu" data-bs-toggle="tab" data-bs-target="#pane-menu" type="button" role="tab">
            <i class="las la-stream me-1"></i> Menu (HTML)
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-footer" data-bs-toggle="tab" data-bs-target="#pane-footer" type="button" role="tab">
            <i class="las la-window-minimize me-1"></i> Footer (HTML)
          </button>
        </li>
      </ul>

      <div class="tab-content border border-top-0 rounded-bottom p-3" id="layoutTabsContent">
        <div class="tab-pane fade show active" id="pane-menu" role="tabpanel" aria-labelledby="tab-menu">
          <div class="small text-body-secondary mb-2">Dica: mantenha as classes (ex: <code>topbar</code>, <code>nav__list</code>) para o CSS do <code>central/index.html</code> continuar funcionando.</div>
          <textarea class="form-control font-monospace" rows="18" name="menu_html"><?= h($menuHtml) ?></textarea>
        </div>
        <div class="tab-pane fade" id="pane-footer" role="tabpanel" aria-labelledby="tab-footer">
          <div class="small text-body-secondary mb-2">Footer simples com logo, direitos, idioma, redes sociais e botões.</div>
          <textarea class="form-control font-monospace" rows="14" name="footer_html"><?= h($footerHtml) ?></textarea>
        </div>
      </div>

      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary" type="submit"><i class="las la-save me-1"></i> Salvar</button>
        <a class="btn btn-outline-dark" href="/admin/dashboard.php">Voltar</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>


