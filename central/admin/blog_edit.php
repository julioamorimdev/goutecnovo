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

// Função auxiliar para formatar bytes
if (!function_exists('formatBytes')) {
    function formatBytes(int $bytes, int $precision = 2): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
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
    if (!empty($_FILES['image_file']['name'])) {
        $f = $_FILES['image_file'];
        $uploadError = $f['error'] ?? UPLOAD_ERR_NO_FILE;
        
        // Verificar erros de upload
        if ($uploadError !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'A imagem excede o tamanho máximo permitido pelo servidor PHP (upload_max_filesize: ' . ini_get('upload_max_filesize') . '). Tamanho do arquivo: ' . formatBytes($f['size'] ?? 0),
                UPLOAD_ERR_FORM_SIZE => 'A imagem excede o tamanho máximo permitido pelo formulário (MAX_FILE_SIZE). Tamanho do arquivo: ' . formatBytes($f['size'] ?? 0),
                UPLOAD_ERR_PARTIAL => 'O upload da imagem foi interrompido. O arquivo pode ser muito grande ou a conexão foi perdida. Tamanho do arquivo: ' . formatBytes($f['size'] ?? 0) . '. Tente novamente com uma imagem menor ou comprima a imagem antes de fazer upload.',
                UPLOAD_ERR_NO_FILE => 'Nenhuma imagem foi enviada.',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta um diretório temporário no servidor. Contate o administrador.',
                UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever a imagem no disco. Pode ser falta de espaço em disco ou permissões incorretas. Contate o administrador.',
                UPLOAD_ERR_EXTENSION => 'Uma extensão PHP interrompeu o upload da imagem. Contate o administrador.',
            ];
            $error = $errorMessages[$uploadError] ?? 'Erro desconhecido no upload da imagem (código: ' . $uploadError . '). Tamanho do arquivo: ' . formatBytes($f['size'] ?? 0);
        } else {
            $orig = (string)($f['name'] ?? 'blog');
            $tmp = (string)$f['tmp_name'];
            $size = (int)($f['size'] ?? 0);
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            
            // Verificar se o arquivo temporário existe
            if (!is_uploaded_file($tmp)) {
                $error = 'Arquivo inválido ou não foi enviado corretamente. Tamanho do arquivo: ' . formatBytes($size);
            } elseif (!in_array($ext, ['png','jpg','jpeg','webp','svg'], true)) {
                $error = 'Formato inválido para imagem. Use png, jpg, jpeg, webp ou svg. Arquivo enviado: ' . $ext;
            } elseif ($size === 0) {
                $error = 'O arquivo está vazio ou não foi enviado corretamente.';
            } elseif ($size > 50 * 1024 * 1024) { // 50MB
                $error = 'A imagem é muito grande. Tamanho do arquivo: ' . formatBytes($size) . '. Tamanho máximo permitido: 50MB. Por favor, comprima a imagem ou use uma versão menor.';
            } else {
                $dir = ensure_blog_upload_dir();
                
                // Verificar se o diretório é gravável
                if (!is_writable($dir)) {
                    $error = 'O diretório de upload não tem permissão de escrita. Contate o administrador.';
                } elseif (!is_dir($dir)) {
                    $error = 'O diretório de upload não existe. Contate o administrador.';
                } else {
                    $stamp = date('Ymd_His');
                    // Remover extensão do nome original antes de sanitizar para evitar duplicação
                    $baseName = pathinfo($orig, PATHINFO_FILENAME);
                    $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $baseName);
                    $filename = "blog_{$stamp}_{$safe}.{$ext}";
                    $dest = $dir . '/' . $filename;
                    
                    // Verificar espaço em disco antes de tentar salvar
                    $freeSpace = disk_free_space($dir);
                    if ($freeSpace !== false && $freeSpace < $size * 2) {
                        $error = 'Espaço insuficiente em disco para salvar a imagem. Espaço disponível: ' . formatBytes($freeSpace) . '. Tamanho necessário: ' . formatBytes($size);
                    } elseif (!move_uploaded_file($tmp, $dest)) {
                        $error = 'Não foi possível salvar a imagem. Verifique as permissões do diretório ou espaço em disco. Tamanho do arquivo: ' . formatBytes($size);
                    } else {
                        // Verificar se o arquivo foi realmente salvo
                        if (!file_exists($dest)) {
                            $error = 'A imagem foi processada mas não foi encontrada no destino.';
                        } elseif (filesize($dest) !== $size) {
                            $error = 'A imagem foi salva mas o tamanho não corresponde ao arquivo original. Arquivo pode estar corrompido.';
                        } else {
                            // Caminho relativo à raiz do site
                            $image = '/assets/img/blog/' . $filename;
                        }
                    }
                }
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
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="las la-exclamation-triangle me-2"></i>
        <strong>Erro:</strong> <?= h($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php endif; ?>

<?php 
// Verificar limites de upload do PHP
$uploadMax = ini_get('upload_max_filesize');
$postMax = ini_get('post_max_size');
if ($uploadMax && (int)$uploadMax < 10): 
?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="las la-info-circle me-2"></i>
        <strong>Atenção:</strong> O limite de upload do servidor está configurado para <?= h($uploadMax) ?>. 
        Se sua imagem for maior que isso, o upload falhará. Contate o administrador para aumentar o limite.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
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
                    <input class="form-control" type="file" name="image_file" id="image_file_input" accept=".png,.jpg,.jpeg,.webp,.svg">
                    <div class="small text-body-secondary mt-1">
                        Faça upload de uma nova imagem ou mantenha a atual abaixo. Tamanho máximo: 50MB. Formatos aceitos: PNG, JPG, JPEG, WEBP, SVG.
                    </div>
                    <div id="file_size_info" class="small mt-2" style="display: none;"></div>
                    <div id="file_size_error" class="alert alert-danger mt-2" style="display: none;"></div>
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
    
    // Validar tamanho do arquivo antes de submeter
    var imageInput = document.getElementById('image_file_input');
    var fileSizeInfo = document.getElementById('file_size_info');
    var fileSizeError = document.getElementById('file_size_error');
    var maxSize = 50 * 1024 * 1024; // 50MB em bytes
    
    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        var k = 1024;
        var sizes = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
    
    if (imageInput) {
        imageInput.addEventListener('change', function(e) {
            var file = e.target.files[0];
            if (file) {
                var fileSize = file.size;
                fileSizeError.style.display = 'none';
                
                if (fileSize > maxSize) {
                    fileSizeError.innerHTML = '<i class="las la-exclamation-triangle me-1"></i> <strong>Arquivo muito grande!</strong> Tamanho: ' + formatBytes(fileSize) + '. Tamanho máximo: 50MB. Por favor, comprima a imagem ou escolha uma versão menor.';
                    fileSizeError.style.display = 'block';
                    imageInput.value = ''; // Limpar seleção
                    fileSizeInfo.style.display = 'none';
                } else {
                    fileSizeInfo.innerHTML = '<i class="las la-info-circle me-1"></i> Tamanho do arquivo: ' + formatBytes(fileSize) + ' (máximo: 50MB)';
                    fileSizeInfo.style.display = 'block';
                    fileSizeInfo.className = 'small mt-2 text-success';
                }
            } else {
                fileSizeInfo.style.display = 'none';
                fileSizeError.style.display = 'none';
            }
        });
    }
    
    // Atualizar textarea antes de submeter
    var form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            // Verificar tamanho do arquivo novamente antes de submeter
            if (imageInput && imageInput.files.length > 0) {
                var file = imageInput.files[0];
                if (file.size > maxSize) {
                    e.preventDefault();
                    fileSizeError.innerHTML = '<i class="las la-exclamation-triangle me-1"></i> <strong>Erro:</strong> O arquivo é muito grande (' + formatBytes(file.size) + '). Tamanho máximo: 50MB.';
                    fileSizeError.style.display = 'block';
                    imageInput.focus();
                    return false;
                }
            }
            
            // Atualizar conteúdo do editor
            if (contentTextarea) {
                contentTextarea.value = quill.root.innerHTML;
            }
            
            // Mostrar loading no botão
            var submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                var originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="las la-spinner la-spin me-1"></i> Salvando...';
                
                // Reabilitar após 60 segundos (timeout de segurança)
                setTimeout(function() {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }, 60000);
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>
