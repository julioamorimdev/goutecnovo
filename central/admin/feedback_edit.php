<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

// Garantir UTF-8 na conexão
db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
db()->exec("SET CHARACTER SET utf8mb4");
db()->exec("SET character_set_connection=utf8mb4");

// Verificar e adicionar campo show_brand_image se não existir
try {
    $stmt = db()->query("SHOW COLUMNS FROM feedback_items LIKE 'show_brand_image'");
    $exists = $stmt->fetch();
    if (!$exists) {
        db()->exec("ALTER TABLE feedback_items ADD COLUMN show_brand_image TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Exibir imagem da marca (1=sim, 0=não)' AFTER brand_image");
    }
} catch (Throwable $e) {
    // Ignorar erro se a tabela não existir ou outro problema
}

function ensure_feedback_upload_dir(): string {
    // Usar o diretório assets na raiz do site, não no admin
    $dir = __DIR__ . '/../../assets/img/feedback';
    if (!is_dir($dir)) {
        // Criar diretório com permissões adequadas
        $parentDir = dirname($dir);
        if (!is_dir($parentDir)) {
            mkdir($parentDir, 0775, true);
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        // Garantir permissões de escrita
        if (is_dir($dir)) {
            chmod($dir, 0775);
        }
    }
    return $dir;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page_title = $id ? 'Editar feedback' : 'Novo feedback';
$active = 'feedback';

$feedback_item = [
    'brand_image' => '',
    'show_brand_image' => 1,
    'title' => '',
    'text' => '',
    'person_name' => '',
    'person_role' => '',
    'person_image' => '',
    'sort_order' => 0,
    'is_enabled' => 1,
];

// Buscar dados do item se estiver editando (ANTES do POST para ter os dados disponíveis)
if ($id > 0) {
    try {
        // Garantir UTF-8 na conexão antes da busca
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->exec("SET CHARACTER SET utf8mb4");
        db()->exec("SET character_set_connection=utf8mb4");
        
        $stmt = db()->prepare("SELECT * FROM feedback_items WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row || !is_array($row) || empty($row)) {
            require_once __DIR__ . '/partials/layout_start.php';
            echo '<div class="alert alert-danger">Feedback não encontrado (ID: ' . h((string)$id) . ').</div>';
            echo '<a class="btn btn-outline-dark" href="/admin/feedback.php">Voltar</a>';
            require_once __DIR__ . '/partials/layout_end.php';
            exit;
        }
        
        // Garantir que todos os campos necessários existam
        // Converter valores NULL para strings vazias para evitar problemas
        foreach ($row as $key => $value) {
            if ($value === null) {
                $row[$key] = '';
            }
        }
        
        // Merge: dados do banco sobrescrevem os valores padrão
        $feedback_item = array_merge($feedback_item, $row);
    } catch (Throwable $e) {
        require_once __DIR__ . '/partials/layout_start.php';
        echo '<div class="alert alert-danger">Erro ao buscar feedback: ' . h($e->getMessage()) . '</div>';
        echo '<a class="btn btn-outline-dark" href="/admin/feedback.php">Voltar</a>';
        require_once __DIR__ . '/partials/layout_end.php';
        exit;
    }
}

// Processar POST ANTES de qualquer output HTML
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
                // Caminho relativo à raiz do site
                $brandImage = '/assets/img/feedback/' . $filename;
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
                // Caminho relativo à raiz do site
                $personImage = '/assets/img/feedback/' . $filename;
            }
        }
    }
    
    // Se não houve upload, manter o valor existente
    if ($brandImage === '') {
        $brandImage = $feedback_item['brand_image'] ?? '';
    }
    if ($personImage === '') {
        $personImage = $feedback_item['person_image'] ?? '';
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
        'show_brand_image' => isset($_POST['show_brand_image']) ? 1 : 0,
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
            $stmt = db()->prepare("UPDATE feedback_items SET brand_image=:brand_image, show_brand_image=:show_brand_image, title=:title, text=:text, person_name=:person_name, person_role=:person_role, person_image=:person_image, sort_order=:sort_order, is_enabled=:is_enabled WHERE id=:id");
            $data['id'] = $id;
            $stmt->execute($data);
        } else {
            $stmt = db()->prepare("INSERT INTO feedback_items (brand_image, show_brand_image, title, text, person_name, person_role, person_image, sort_order, is_enabled) VALUES (:brand_image, :show_brand_image, :title, :text, :person_name, :person_role, :person_image, :sort_order, :is_enabled)");
            $stmt->execute($data);
        }
        header('Location: /admin/feedback.php');
        exit;
    }
    // Se houver erro, manter os dados do POST para exibir no formulário
    $feedback_item = array_merge($feedback_item, $data);
}

require_once __DIR__ . '/partials/layout_start.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
    <div>
        <h5 class="mb-0"><?= $id ? 'Editar feedback' : 'Novo feedback' ?></h5>
        <div class="text-body-secondary small">Preencha os dados do depoimento</div>
    </div>
    <a class="btn btn-outline-dark" href="/admin/feedback.php">
        <i class="las la-arrow-left me-1"></i>Voltar
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<div class="card shadow-sm rounded-3">
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <?php
            // Garantir que $feedback_item está definido
            if (!isset($feedback_item) || !is_array($feedback_item)) {
                $feedback_item = [
                    'brand_image' => '',
                    'title' => '',
                    'text' => '',
                    'person_name' => '',
                    'person_role' => '',
                    'person_image' => '',
                    'sort_order' => 0,
                    'is_enabled' => 1,
                ];
            }
            ?>
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="brand_image" value="<?= h($feedback_item['brand_image'] ?? '') ?>">
            <input type="hidden" name="person_image" value="<?= h($feedback_item['person_image'] ?? '') ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Imagem da marca/empresa</label>
                    <input class="form-control" type="file" name="brand_image_file" accept=".png,.jpg,.jpeg,.webp,.svg">
                    <div class="small text-body-secondary mt-1">
                        Faça upload de uma nova imagem ou mantenha a atual abaixo
                    </div>
                    <?php if (!empty($feedback_item['brand_image'])): ?>
                        <div class="mt-2 p-2 border rounded">
                            <div class="small text-body-secondary mb-1">Imagem atual:</div>
                            <img src="<?= h($feedback_item['brand_image'] ?? '') ?>" alt="Preview" style="max-height: 60px;" onerror="this.style.display='none'">
                            <div class="small text-body-secondary mt-1">
                                <code><?= h($feedback_item['brand_image'] ?? '') ?></code>
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
                    <?php if (!empty($feedback_item['person_image'])): ?>
                        <div class="mt-2 p-2 border rounded">
                            <div class="small text-body-secondary mb-1">Foto atual:</div>
                            <img src="<?= h($feedback_item['person_image'] ?? '') ?>" alt="Preview" style="max-height: 60px; border-radius: 50%;" onerror="this.style.display='none'">
                            <div class="small text-body-secondary mt-1">
                                <code><?= h($feedback_item['person_image'] ?? '') ?></code>
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
                    <input class="form-control" name="person_name" value="<?= h($feedback_item['person_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cargo/Empresa</label>
                    <input class="form-control" name="person_role" value="<?= h($feedback_item['person_role'] ?? '') ?>" placeholder="ex: Digital Marketing Director" required>
                </div>
                
                <div class="col-12">
                    <label class="form-label">Título do feedback</label>
                    <input class="form-control" name="title" value="<?= h($feedback_item['title'] ?? '') ?>" placeholder="ex: O melhor designer criativo recomendado." required>
                </div>
                
                <div class="col-12">
                    <label class="form-label">Texto do feedback</label>
                    <textarea class="form-control" name="text" rows="4" required><?= h($feedback_item['text'] ?? '') ?></textarea>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Ordem</label>
                    <input class="form-control" type="number" name="sort_order" value="<?= h((string)($feedback_item['sort_order'] ?? 0)) ?>">
                </div>
                
                <div class="col-md-6">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="show_brand_image" id="show_brand_image" <?= ((int)($feedback_item['show_brand_image'] ?? 1) === 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_brand_image">Exibir imagem da marca</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="is_enabled" id="is_enabled" <?= ((int)($feedback_item['is_enabled'] ?? 1) === 1) ? 'checked' : '' ?>>
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
