<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

// Garantir UTF-8 na conexão
db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
db()->exec("SET CHARACTER SET utf8mb4");
db()->exec("SET character_set_connection=utf8mb4");

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$page_title = $id ? 'Editar ícone de rede social' : 'Novo ícone de rede social';
$active = 'footer';

$error = null;

$social_icon = [
    'name' => '',
    'icon_class' => '',
    'url' => '#',
    'sort_order' => 0,
    'is_enabled' => 1,
];

// Processar POST ANTES de qualquer output HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $name = trim((string)($_POST['name'] ?? ''));
    $icon_class = trim((string)($_POST['icon_class'] ?? ''));
    $url = trim((string)($_POST['url'] ?? '#'));
    
    if ($name === '') $error = 'O nome é obrigatório.';
    if ($icon_class === '') $error = 'A classe do ícone é obrigatória.';
    if ($url === '') $url = '#';

    $data = [
        'name' => $name,
        'icon_class' => $icon_class,
        'url' => $url,
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
        'is_enabled' => isset($_POST['is_enabled']) ? 1 : 0,
    ];

    if (!$error) {
        // Garantir UTF-8 antes de salvar
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->exec("SET CHARACTER SET utf8mb4");
        db()->exec("SET character_set_connection=utf8mb4");
        
        try {
            if ($id > 0) {
                $stmt = db()->prepare("UPDATE footer_social_icons SET name=:name, icon_class=:icon_class, url=:url, sort_order=:sort_order, is_enabled=:is_enabled WHERE id=:id");
                $data['id'] = $id;
                $stmt->execute($data);
            } else {
                $stmt = db()->prepare("INSERT INTO footer_social_icons (name, icon_class, url, sort_order, is_enabled) VALUES (:name, :icon_class, :url, :sort_order, :is_enabled)");
                $stmt->execute($data);
            }
            header('Location: /admin/footer.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
    // Se houver erro, manter os dados do POST para exibir no formulário
    $social_icon = array_merge($social_icon, $data);
}

// Buscar dados do item se estiver editando (ANTES do layout para ter os dados disponíveis)
if ($id > 0) {
    try {
        $stmt = db()->prepare("SELECT * FROM footer_social_icons WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            require_once __DIR__ . '/partials/layout_start.php';
            echo '<div class="alert alert-danger">Ícone não encontrado.</div>';
            echo '<a class="btn btn-outline-dark" href="/admin/footer.php">Voltar</a>';
            require_once __DIR__ . '/partials/layout_end.php';
            exit;
        }
        $social_icon = array_merge($social_icon, $row);
    } catch (Throwable $e) {
        require_once __DIR__ . '/partials/layout_start.php';
        echo '<div class="alert alert-danger">Erro ao buscar ícone: ' . h($e->getMessage()) . '</div>';
        echo '<a class="btn btn-outline-dark" href="/admin/footer.php">Voltar</a>';
        require_once __DIR__ . '/partials/layout_end.php';
        exit;
    }
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

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nome da rede social</label>
                    <input class="form-control" name="name" value="<?= h($social_icon['name'] ?? '') ?>" placeholder="ex: Facebook, WhatsApp" required>
                    <small class="text-body-secondary">Nome que identifica a rede social</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Classe do ícone</label>
                    <input class="form-control" name="icon_class" value="<?= h($social_icon['icon_class'] ?? '') ?>" placeholder="ex: lab la-facebook-f" required>
                    <small class="text-body-secondary">Classe CSS do ícone (Line Awesome)</small>
                </div>
                <div class="col-md-8">
                    <label class="form-label">URL</label>
                    <input class="form-control" name="url" value="<?= h($social_icon['url'] ?? '#') ?>" placeholder="https://..." required>
                    <small class="text-body-secondary">Link para o perfil/rede social</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Ordem</label>
                    <input class="form-control" type="number" name="sort_order" value="<?= h((string)($social_icon['sort_order'] ?? 0)) ?>">
                    <small class="text-body-secondary">Ordem de exibição (menor = primeiro)</small>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_enabled" id="is_enabled" <?= ((int)($social_icon['is_enabled'] ?? 1) === 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_enabled">Ativo</label>
                    </div>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Salvar</button>
                <a class="btn btn-outline-dark" href="/admin/footer.php">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm rounded-3 mt-3">
    <div class="card-body">
        <h6 class="mb-3">Ícones disponíveis (Line Awesome)</h6>
        <div class="small text-body-secondary">
            <p>Alguns exemplos de classes de ícones para redes sociais:</p>
            <ul class="mb-0">
                <li><code>lab la-facebook-f</code> - Facebook</li>
                <li><code>lab la-whatsapp</code> - WhatsApp</li>
                <li><code>lab la-instagram</code> - Instagram</li>
                <li><code>lab la-discord</code> - Discord</li>
                <li><code>lab la-twitter</code> - Twitter</li>
                <li><code>lab la-linkedin-in</code> - LinkedIn</li>
                <li><code>lab la-youtube</code> - YouTube</li>
                <li><code>lab la-telegram</code> - Telegram</li>
                <li><code>lab la-tiktok</code> - TikTok</li>
            </ul>
            <p class="mt-2 mb-0">Consulte a <a href="https://icons8.com/line-awesome" target="_blank">documentação do Line Awesome</a> para mais ícones.</p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

