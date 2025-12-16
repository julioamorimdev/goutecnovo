<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
require_once __DIR__ . '/../../app/bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page_title = $id ? 'Editar feedback' : 'Novo feedback';
$active = 'feedback';
require_once __DIR__ . '/partials/layout_start.php';

function ensure_feedback_upload_dir(): string {
    $dir = __DIR__ . '/../assets/img/feedback';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    return $dir;
}

$item = [
    'brand_image' => '',
    'title' => '',
    'text' => '',
    'person_name' => '',
    'person_role' => '',
    'person_image' => '',
    'sort_order' => 0,
    'is_enabled' => 1,
];

if ($id > 0) {
    $stmt = db()->prepare("SELECT * FROM feedback_items WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        exit('Feedback não encontrado.');
    }
    $item = array_merge($item, $row);
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $brandImage = trim((string)($_POST['brand_image'] ?? ''));
    $title = trim((string)($_POST['title'] ?? ''));
    $text = trim((string)($_POST['text'] ?? ''));
    $personName = trim((string)($_POST['person_name'] ?? ''));
    $personRole = trim((string)($_POST['person_role'] ?? ''));
    $personImage = trim((string)($_POST['person_image'] ?? ''));
    
    // Processar upload da imagem da marca
    if (!empty($_FILES['brand_image_file']['name']) && $_FILES['brand_image_file']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['brand_image_file'];
        $orig = (string)($f['name'] ?? 'brand');
        $tmp = (string)$f['tmp_name'];
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, ['png','jpg','jpeg','webp','svg'], true)) {
            $error = 'Formato inválido para imagem da marca. Use png, jpg, webp ou svg.';
        } else {
            $dir = ensure_feedback_upload_dir();
            $stamp = date('Ymd_His');
            $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', pathinfo($orig, PATHINFO_FILENAME));
            $filename = "brand_{$stamp}_{$safe}.{$ext}";
            $dest = $dir . '/' . $filename;
            
            if (!move_uploaded_file($tmp, $dest)) {
                $error = 'Não foi possível salvar a imagem da marca.';
            } else {
                $brandImage = '/admin/assets/img/feedback/' . $filename;
            }
        }
    }
    
    // Processar upload da foto da pessoa
    if (!empty($_FILES['person_image_file']['name']) && $_FILES['person_image_file']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['person_image_file'];
        $orig = (string)($f['name'] ?? 'person');
        $tmp = (string)$f['tmp_name'];
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, ['png','jpg','jpeg','webp','svg'], true)) {
            $error = 'Formato inválido para foto da pessoa. Use png, jpg, webp ou svg.';
        } else {
            $dir = ensure_feedback_upload_dir();
            $stamp = date('Ymd_His');
            $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', pathinfo($orig, PATHINFO_FILENAME));
            $filename = "person_{$stamp}_{$safe}.{$ext}";
            $dest = $dir . '/' . $filename;
            
            if (!move_uploaded_file($tmp, $dest)) {
                $error = 'Não foi possível salvar a foto da pessoa.';
            } else {
                $personImage = '/admin/assets/img/feedback/' . $filename;
            }
        }
    }
    
    // Se não houve upload, manter o valor existente
    if ($brandImage === '') {
        $brandImage = $item['brand_image'] ?? '';
    }
    if ($personImage === '') {
        $personImage = $item['person_image'] ?? '';
    }
    
    // Validações
    if ($brandImage === '' && (empty($_FILES['brand_image_file']['name']) || $_FILES['brand_image_file']['error'] !== UPLOAD_ERR_OK)) {
        $error = 'A imagem da marca é obrigatória. Faça upload de uma imagem.';
    }
    if ($title === '') $error = 'O título é obrigatório.';
    if ($text === '') $error = 'O texto é obrigatório.';
    if ($personName === '') $error = 'O nome da pessoa é obrigatório.';
    if ($personRole === '') $error = 'O cargo/empresa é obrigatório.';
    if ($personImage === '' && (empty($_FILES['person_image_file']['name']) || $_FILES['person_image_file']['error'] !== UPLOAD_ERR_OK)) {
        $error = 'A foto da pessoa é obrigatória. Faça upload de uma foto.';
    }

    $data = [
        'brand_image' => $brandImage,
        'title' => $title,
        'text' => $text,
        'person_name' => $personName,
        'person_role' => $personRole,
        'person_image' => $personImage,
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
        'is_enabled' => isset($_POST['is_enabled']) ? 1 : 0,
    ];

    if (!$error) {
        if ($id > 0) {
            $stmt = db()->prepare("UPDATE feedback_items SET brand_image=:brand_image, title=:title, text=:text, person_name=:person_name, person_role=:person_role, person_image=:person_image, sort_order=:sort_order, is_enabled=:is_enabled WHERE id=:id");
            $data['id'] = $id;
            $stmt->execute($data);
        } else {
            $stmt = db()->prepare("INSERT INTO feedback_items (brand_image, title, text, person_name, person_role, person_image, sort_order, is_enabled) VALUES (:brand_image, :title, :text, :person_name, :person_role, :person_image, :sort_order, :is_enabled)");
            $stmt->execute($data);
        }
        header('Location: /admin/feedback.php');
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
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="brand_image" value="<?= h($item['brand_image']) ?>">
            <input type="hidden" name="person_image" value="<?= h($item['person_image']) ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Imagem da marca/empresa</label>
                    <input class="form-control" type="file" name="brand_image_file" accept=".png,.jpg,.jpeg,.webp,.svg">
                    <div class="small text-body-secondary mt-1">
                        Faça upload de uma nova imagem ou mantenha a atual abaixo
                    </div>
                    <?php if ($item['brand_image']): ?>
                        <div class="mt-2 p-2 border rounded">
                            <div class="small text-body-secondary mb-1">Imagem atual:</div>
                            <img src="<?= h($item['brand_image']) ?>" alt="Preview" style="max-height: 60px;" onerror="this.style.display='none'">
                            <div class="small text-body-secondary mt-1">
                                <code><?= h($item['brand_image']) ?></code>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mt-2">
                            <small>Nenhuma imagem selecionada. Faça upload de uma imagem.</small>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Foto da pessoa</label>
                    <input class="form-control" type="file" name="person_image_file" accept=".png,.jpg,.jpeg,.webp,.svg">
                    <div class="small text-body-secondary mt-1">
                        Faça upload de uma nova foto ou mantenha a atual abaixo
                    </div>
                    <?php if ($item['person_image']): ?>
                        <div class="mt-2 p-2 border rounded">
                            <div class="small text-body-secondary mb-1">Foto atual:</div>
                            <img src="<?= h($item['person_image']) ?>" alt="Preview" style="max-height: 60px; border-radius: 50%;" onerror="this.style.display='none'">
                            <div class="small text-body-secondary mt-1">
                                <code><?= h($item['person_image']) ?></code>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mt-2">
                            <small>Nenhuma foto selecionada. Faça upload de uma foto.</small>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Nome da pessoa</label>
                    <input class="form-control" name="person_name" value="<?= h($item['person_name']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cargo/Empresa</label>
                    <input class="form-control" name="person_role" value="<?= h($item['person_role']) ?>" placeholder="ex: Digital Marketing Director" required>
                </div>
                
                <div class="col-12">
                    <label class="form-label">Título do feedback</label>
                    <input class="form-control" name="title" value="<?= h($item['title']) ?>" placeholder="ex: O melhor designer criativo recomendado." required>
                </div>
                
                <div class="col-12">
                    <label class="form-label">Texto do feedback</label>
                    <textarea class="form-control" name="text" rows="4" required><?= h($item['text']) ?></textarea>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Ordem</label>
                    <input class="form-control" type="number" name="sort_order" value="<?= h((string)$item['sort_order']) ?>">
                </div>
                
                <div class="col-md-6">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="is_enabled" id="is_enabled" <?= ((int)$item['is_enabled'] === 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_enabled">Ativo</label>
                    </div>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Salvar</button>
                <a class="btn btn-outline-dark" href="/admin/feedback.php">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>
