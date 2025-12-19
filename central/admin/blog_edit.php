<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

// Garantir UTF-8 na conexão
db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
db()->exec("SET CHARACTER SET utf8mb4");
db()->exec("SET character_set_connection=utf8mb4");

function ensure_blog_upload_dir(): string {
    // Usar o diretório assets na raiz do site, não no admin
    $dir = __DIR__ . '/../../assets/img/blog';
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
$page_title = $id ? 'Editar artigo' : 'Novo artigo';
$active = 'blog';

$blog_item = [
    'image' => '',
    'title' => '',
    'author' => '',
    'published_date' => date('Y-m-d'),
    'url' => '',
    'content' => '',
    'is_featured' => 0,
    'sort_order' => 0,
    'is_enabled' => 1,
];

// Buscar dados do item se estiver editando (ANTES do POST para ter os dados disponíveis)
if ($id > 0) {
    $stmt = db()->prepare("SELECT * FROM blog_posts WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        require_once __DIR__ . '/partials/layout_start.php';
        echo '<div class="alert alert-danger">Artigo não encontrado.</div>';
        echo '<a class="btn btn-outline-dark" href="/admin/blog.php">Voltar</a>';
        require_once __DIR__ . '/partials/layout_end.php';
        exit;
    }
    $blog_item = array_merge($blog_item, $row);
    $blog_item['published_date'] = date('Y-m-d', strtotime($blog_item['published_date']));
    $blog_item['content'] = $blog_item['content'] ?? '';
}

// Processar POST ANTES de qualquer output HTML
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $image = trim((string)($_POST['image'] ?? ''));
    $title = trim((string)($_POST['title'] ?? ''));
    $author = trim((string)($_POST['author'] ?? ''));
    $publishedDate = trim((string)($_POST['published_date'] ?? ''));
    $url = trim((string)($_POST['url'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    
    // Processar upload da imagem
    if (!empty($_FILES['image_file']['name']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['image_file'];
        $orig = (string)($f['name'] ?? 'blog');
        $tmp = (string)$f['tmp_name'];
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, ['png','jpg','jpeg','webp','svg'], true)) {
            $error = 'Formato inválido para imagem. Use png, jpg, webp ou svg.';
        } else {
            $dir = ensure_blog_upload_dir();
            $stamp = date('Ymd_His');
            // Remover extensão do nome original antes de sanitizar para evitar duplicação
            $baseName = pathinfo($orig, PATHINFO_FILENAME);
            $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $baseName);
            $filename = "blog_{$stamp}_{$safe}.{$ext}";
            $dest = $dir . '/' . $filename;
            
            if (!move_uploaded_file($tmp, $dest)) {
                $error = 'Não foi possível salvar a imagem.';
            } else {
                // Caminho relativo à raiz do site
                $image = '/assets/img/blog/' . $filename;
            }
        }
    }
    
    // Se não houve upload, manter o valor existente
    if ($image === '') {
        $image = $blog_item['image'] ?? '';
    }
    
    // Se marcar como featured, desmarcar os outros (antes de salvar)
    if ($isFeatured) {
        if ($id > 0) {
            db()->prepare("UPDATE blog_posts SET is_featured=0 WHERE is_featured=1 AND id != ?")->execute([$id]);
        } else {
            db()->prepare("UPDATE blog_posts SET is_featured=0 WHERE is_featured=1")->execute();
        }
    }
    
    if ($image === '' && (empty($_FILES['image_file']['name']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK)) {
        $error = 'A imagem é obrigatória. Faça upload de uma imagem.';
    }
    if ($title === '') $error = 'O título é obrigatório.';
    if ($author === '') $error = 'O autor é obrigatório.';
    if ($publishedDate === '') $error = 'A data de publicação é obrigatória.';
    if ($url === '') $error = 'A URL é obrigatória.';

    $data = [
        'image' => $image,
        'title' => $title,
        'author' => $author,
        'published_date' => $publishedDate,
        'url' => $url,
        'content' => $content,
        'is_featured' => $isFeatured,
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
        'is_enabled' => isset($_POST['is_enabled']) ? 1 : 0,
    ];

    if (!$error) {
        if ($id > 0) {
            $stmt = db()->prepare("UPDATE blog_posts SET image=:image, title=:title, author=:author, published_date=:published_date, url=:url, content=:content, is_featured=:is_featured, sort_order=:sort_order, is_enabled=:is_enabled WHERE id=:id");
            $data['id'] = $id;
            $stmt->execute($data);
        } else {
            $stmt = db()->prepare("INSERT INTO blog_posts (image, title, author, published_date, url, content, is_featured, sort_order, is_enabled) VALUES (:image, :title, :author, :published_date, :url, :content, :is_featured, :sort_order, :is_enabled)");
            $stmt->execute($data);
        }
        header('Location: /admin/blog.php?success=1');
        exit;
    }
    // Se houver erro, manter os dados do POST para exibir no formulário
    $blog_item = array_merge($blog_item, $data);
}

require_once __DIR__ . '/partials/layout_start.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<?php if (isset($_GET['success']) && $_GET['success'] === '1'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="las la-check-circle me-2"></i>Artigo salvo com sucesso!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm rounded-3">
    <div class="card-body">
        <?php
        // Garantir que $blog_item está definido
        if (!isset($blog_item) || !is_array($blog_item)) {
            $blog_item = [
                'image' => '',
                'title' => '',
                'author' => '',
                'published_date' => date('Y-m-d'),
                'url' => '',
                'content' => '',
                'is_featured' => 0,
                'sort_order' => 0,
                'is_enabled' => 1,
            ];
        }
        ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="image" value="<?= h($blog_item['image'] ?? '') ?>">

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Imagem do artigo</label>
                    <input class="form-control" type="file" name="image_file" accept=".png,.jpg,.jpeg,.webp,.svg">
                    <div class="small text-body-secondary mt-1">
                        Faça upload de uma nova imagem ou mantenha a atual abaixo
                    </div>
                    <?php if (!empty($blog_item['image'])): ?>
                        <div class="mt-2 p-2 border rounded">
                            <div class="small text-body-secondary mb-1">Imagem atual:</div>
                            <img src="<?= h($blog_item['image'] ?? '') ?>" alt="Preview" style="max-height: 200px; width: auto;" onerror="this.style.display='none'">
                            <div class="small text-body-secondary mt-1">
                                <code><?= h($blog_item['image'] ?? '') ?></code>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mt-2">
                            <small>Nenhuma imagem selecionada. Faça upload de uma imagem.</small>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-8">
                    <label class="form-label">Título do artigo</label>
                    <input class="form-control" name="title" value="<?= h($blog_item['title'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Data de publicação</label>
                    <input class="form-control" type="date" name="published_date" value="<?= h($blog_item['published_date'] ?? '') ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Autor</label>
                    <input class="form-control" name="author" value="<?= h($blog_item['author'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">URL do artigo</label>
                    <input class="form-control" name="url" value="<?= h($blog_item['url'] ?? '') ?>" placeholder="ex: blog-details.html?id=1" required>
                    <div class="small text-body-secondary mt-1">
                        Link para a página de detalhes do artigo
                    </div>
                </div>
                
                <div class="col-12">
                    <label class="form-label">Conteúdo do artigo</label>
                    <div id="editor-container" style="height: 400px; background: #fff; border: 1px solid #dee2e6; border-radius: 4px;"></div>
                    <textarea name="content" id="content-hidden" style="display: none;"><?= htmlspecialchars($blog_item['content'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></textarea>
                    <div class="small text-body-secondary mt-1">
                        Use o editor acima para formatar o texto do artigo (negrito, itálico, tamanhos, etc.)
                    </div>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Ordem</label>
                    <input class="form-control" type="number" name="sort_order" value="<?= h((string)($blog_item['sort_order'] ?? 0)) ?>">
                </div>
                
                <div class="col-md-4">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="is_featured" id="is_featured" <?= ((int)($blog_item['is_featured'] ?? 0) === 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_featured">
                            <strong>Artigo em destaque</strong>
                            <div class="small text-body-secondary">(aparece como principal na página inicial)</div>
                        </label>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="is_enabled" id="is_enabled" <?= ((int)($blog_item['is_enabled'] ?? 1) === 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_enabled">Ativo</label>
                    </div>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Salvar</button>
                <a class="btn btn-outline-dark" href="/admin/blog.php">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<!-- Quill Editor -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<style>
    .ql-container {
        font-family: inherit;
        font-size: 14px;
    }
    .ql-editor {
        min-height: 350px;
    }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar Quill Editor
    var quill = new Quill('#editor-container', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                [{ 'size': ['small', false, 'large', 'huge'] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'color': [] }, { 'background': [] }],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                [{ 'align': [] }],
                ['link', 'image', 'blockquote', 'code-block'],
                ['clean']
            ]
        },
        placeholder: 'Digite o conteúdo do artigo aqui...'
    });
    
    // Carregar conteúdo existente
    var contentTextarea = document.getElementById('content-hidden');
    if (contentTextarea && contentTextarea.value) {
        try {
            quill.root.innerHTML = contentTextarea.value;
        } catch(e) {
            quill.setText(contentTextarea.value);
        }
    }
    
    // Atualizar textarea antes de submeter
    var form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (contentTextarea) {
                contentTextarea.value = quill.root.innerHTML;
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>
