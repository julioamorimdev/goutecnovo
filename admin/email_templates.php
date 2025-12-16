<?php
declare(strict_types=1);
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Modelos de Email';
$active = 'email_templates';
require_once __DIR__ . '/partials/layout_start.php';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $action = $_POST['action'] ?? '';
        
        if ($action === 'save') {
            $id = $_POST['id'] ?? null;
            $name = trim($_POST['name'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            $body_html = trim($_POST['body_html'] ?? '');
            $body_text = trim($_POST['body_text'] ?? '');
            $template_type = $_POST['template_type'] ?? 'custom';
            $applicable_to = $_POST['applicable_to'] ?? 'all';
            $applicable_ids = !empty($_POST['applicable_ids']) ? json_encode($_POST['applicable_ids']) : null;
            $variables = !empty($_POST['variables']) ? json_encode(explode(',', $_POST['variables'])) : null;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name) || empty($slug) || empty($subject) || empty($body_html)) {
                throw new Exception('Nome, slug, assunto e corpo HTML são obrigatórios.');
            }
            
            // Gerar slug se não fornecido
            if (empty($slug)) {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
            }
            
            if ($id) {
                // Verificar se é modelo do sistema
                $stmt = db()->prepare("SELECT is_system FROM email_templates WHERE id = ?");
                $stmt->execute([$id]);
                $existing = $stmt->fetch();
                if ($existing && $existing['is_system']) {
                    throw new Exception('Não é possível editar modelos do sistema.');
                }
                
                // Verificar se slug já existe em outro modelo
                $stmt = db()->prepare("SELECT id FROM email_templates WHERE slug = ? AND id != ?");
                $stmt->execute([$slug, $id]);
                if ($stmt->fetch()) {
                    throw new Exception('Já existe um modelo com este slug.');
                }
                
                $stmt = db()->prepare("UPDATE email_templates SET name = ?, slug = ?, subject = ?, body_html = ?, body_text = ?, template_type = ?, applicable_to = ?, applicable_ids = ?, variables = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$name, $slug, $subject, $body_html, $body_text ?: null, $template_type, $applicable_to, $applicable_ids, $variables, $is_active, $id]);
            } else {
                // Verificar se slug já existe
                $stmt = db()->prepare("SELECT id FROM email_templates WHERE slug = ?");
                $stmt->execute([$slug]);
                if ($stmt->fetch()) {
                    throw new Exception('Já existe um modelo com este slug.');
                }
                
                $stmt = db()->prepare("INSERT INTO email_templates (name, slug, subject, body_html, body_text, template_type, applicable_to, applicable_ids, variables, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $subject, $body_html, $body_text ?: null, $template_type, $applicable_to, $applicable_ids, $variables, $is_active]);
            }
            
            $_SESSION['success'] = 'Modelo de email salvo com sucesso.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            
            // Verificar se é modelo do sistema
            $stmt = db()->prepare("SELECT is_system FROM email_templates WHERE id = ?");
            $stmt->execute([$id]);
            $template = $stmt->fetch();
            if ($template && $template['is_system']) {
                throw new Exception('Não é possível excluir modelos do sistema.');
            }
            
            $stmt = db()->prepare("DELETE FROM email_templates WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = 'Modelo excluído com sucesso.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Buscar modelos
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = db()->query("SELECT * FROM email_templates ORDER BY is_system DESC, template_type, name");
    $templates = $stmt->fetchAll();
} catch (Throwable $e) {
    $templates = [];
}

$editingTemplate = null;
if (isset($_GET['edit'])) {
    foreach ($templates as $template) {
        if ($template['id'] == $_GET['edit']) {
            $editingTemplate = $template;
            break;
        }
    }
}

// Buscar planos para select
try {
    $stmt = db()->query("SELECT id, name FROM plans WHERE is_active = 1 ORDER BY name");
    $plans = $stmt->fetchAll();
} catch (Throwable $e) {
    $plans = [];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Modelos de Email</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#templateModal">
            <i class="las la-plus me-1"></i> Novo Modelo
        </button>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= h($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Slug</th>
                            <th>Assunto</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($templates)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">Nenhum modelo cadastrado</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($templates as $template): ?>
                                <tr>
                                    <td>
                                        <strong><?= h($template['name']) ?></strong>
                                        <?php if ($template['is_system']): ?>
                                            <span class="badge bg-primary ms-2">Sistema</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?= h($template['slug']) ?></code></td>
                                    <td><?= h($template['subject']) ?></td>
                                    <td>
                                        <?php
                                        $types = [
                                            'system' => 'Sistema',
                                            'custom' => 'Personalizado',
                                            'product_welcome' => 'Boas-vindas do Produto'
                                        ];
                                        echo h($types[$template['template_type']] ?? $template['template_type']);
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($template['is_active']): ?>
                                            <span class="badge bg-success">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?edit=<?= $template['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="las la-edit"></i> Editar
                                        </a>
                                        <?php if (!$template['is_system']): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este modelo?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $template['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="las la-trash"></i> Excluir
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Novo/Editar Modelo -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $editingTemplate['id'] ?? '' ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title"><?= $editingTemplate ? 'Editar' : 'Novo' ?> Modelo de Email</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nome *</label>
                            <input type="text" class="form-control" name="name" required value="<?= h($editingTemplate['name'] ?? '') ?>" <?= ($editingTemplate['is_system'] ?? 0) ? 'readonly' : '' ?>>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Slug *</label>
                            <input type="text" class="form-control" name="slug" required value="<?= h($editingTemplate['slug'] ?? '') ?>" <?= ($editingTemplate['is_system'] ?? 0) ? 'readonly' : '' ?>>
                            <small class="text-muted">Identificador único (ex: welcome, order_created)</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Assunto *</label>
                        <input type="text" class="form-control" name="subject" required value="<?= h($editingTemplate['subject'] ?? '') ?>" placeholder="Ex: Bem-vindo ao {{company_name}}!">
                        <small class="text-muted">Use variáveis como {{company_name}}, {{client_name}}, etc.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tipo de Modelo</label>
                        <select class="form-select" name="template_type" <?= ($editingTemplate['is_system'] ?? 0) ? 'disabled' : '' ?>>
                            <option value="custom" <?= ($editingTemplate['template_type'] ?? 'custom') === 'custom' ? 'selected' : '' ?>>Personalizado</option>
                            <option value="product_welcome" <?= ($editingTemplate['template_type'] ?? '') === 'product_welcome' ? 'selected' : '' ?>>Boas-vindas do Produto</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Corpo do Email (HTML) *</label>
                        <textarea class="form-control" name="body_html" rows="10" required><?= h($editingTemplate['body_html'] ?? '') ?></textarea>
                        <small class="text-muted">Use HTML e variáveis como {{company_name}}, {{client_name}}, etc.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Corpo do Email (Texto Plano)</label>
                        <textarea class="form-control" name="body_text" rows="6"><?= h($editingTemplate['body_text'] ?? '') ?></textarea>
                        <small class="text-muted">Versão em texto plano (opcional)</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Aplicável a</label>
                            <select class="form-select" name="applicable_to" id="applicableTo">
                                <option value="all" <?= ($editingTemplate['applicable_to'] ?? 'all') === 'all' ? 'selected' : '' ?>>Todos</option>
                                <option value="specific_plans" <?= ($editingTemplate['applicable_to'] ?? '') === 'specific_plans' ? 'selected' : '' ?>>Planos Específicos</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3" id="plansContainer" style="display: none;">
                            <label class="form-label">Planos</label>
                            <select class="form-select" name="applicable_ids[]" multiple size="5">
                                <?php foreach ($plans as $plan): ?>
                                    <option value="<?= $plan['id'] ?>" <?= (in_array($plan['id'], json_decode($editingTemplate['applicable_ids'] ?? '[]', true) ?: [])) ? 'selected' : '' ?>>
                                        <?= h($plan['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Variáveis Disponíveis</label>
                        <input type="text" class="form-control" name="variables" value="<?= h(implode(',', json_decode($editingTemplate['variables'] ?? '[]', true) ?: [])) ?>" placeholder="company_name, client_name, client_email">
                        <small class="text-muted">Lista de variáveis separadas por vírgula</small>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= ($editingTemplate['is_active'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label">Modelo ativo</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <?php if (!($editingTemplate['is_system'] ?? 0)): ?>
                        <button type="submit" class="btn btn-primary">Salvar Modelo</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('applicableTo').addEventListener('change', function() {
    document.getElementById('plansContainer').style.display = this.value === 'specific_plans' ? 'block' : 'none';
});
document.getElementById('applicableTo').dispatchEvent(new Event('change'));
</script>

<?php if ($editingTemplate): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = new bootstrap.Modal(document.getElementById('templateModal'));
            modal.show();
        });
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

