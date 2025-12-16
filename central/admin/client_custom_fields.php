<?php
declare(strict_types=1);
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

$page_title = 'Campos Personalizados dos Clientes';
$active = 'client_custom_fields';
require_once __DIR__ . '/partials/layout_start.php';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $action = $_POST['action'] ?? '';
        
        if ($action === 'save') {
            $id = $_POST['id'] ?? null;
            $field_name = trim($_POST['field_name'] ?? '');
            $field_label = trim($_POST['field_label'] ?? '');
            $field_type = $_POST['field_type'] ?? 'text';
            $field_options = trim($_POST['field_options'] ?? '');
            $is_required = isset($_POST['is_required']) ? 1 : 0;
            $is_encrypted = isset($_POST['is_encrypted']) ? 1 : 0;
            $validation_regex = trim($_POST['validation_regex'] ?? '');
            $placeholder = trim($_POST['placeholder'] ?? '');
            $help_text = trim($_POST['help_text'] ?? '');
            $default_value = trim($_POST['default_value'] ?? '');
            $sort_order = intval($_POST['sort_order'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $show_in_registration = isset($_POST['show_in_registration']) ? 1 : 0;
            $show_in_profile = isset($_POST['show_in_profile']) ? 1 : 0;
            
            if (empty($field_name) || empty($field_label)) {
                throw new Exception('Nome interno e rótulo são obrigatórios.');
            }
            
            // Validar nome do campo (sem espaços, apenas letras, números e underscore)
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $field_name)) {
                throw new Exception('Nome interno deve começar com letra e conter apenas letras, números e underscore.');
            }
            
            if ($id) {
                // Verificar se nome já existe em outro campo
                $stmt = db()->prepare("SELECT id FROM client_custom_fields WHERE field_name = ? AND id != ?");
                $stmt->execute([$field_name, $id]);
                if ($stmt->fetch()) {
                    throw new Exception('Já existe um campo com este nome interno.');
                }
                
                $stmt = db()->prepare("UPDATE client_custom_fields SET field_name = ?, field_label = ?, field_type = ?, field_options = ?, is_required = ?, is_encrypted = ?, validation_regex = ?, placeholder = ?, help_text = ?, default_value = ?, sort_order = ?, is_active = ?, show_in_registration = ?, show_in_profile = ? WHERE id = ?");
                $stmt->execute([$field_name, $field_label, $field_type, $field_options ?: null, $is_required, $is_encrypted, $validation_regex ?: null, $placeholder ?: null, $help_text ?: null, $default_value ?: null, $sort_order, $is_active, $show_in_registration, $show_in_profile, $id]);
            } else {
                // Verificar se nome já existe
                $stmt = db()->prepare("SELECT id FROM client_custom_fields WHERE field_name = ?");
                $stmt->execute([$field_name]);
                if ($stmt->fetch()) {
                    throw new Exception('Já existe um campo com este nome interno.');
                }
                
                $stmt = db()->prepare("INSERT INTO client_custom_fields (field_name, field_label, field_type, field_options, is_required, is_encrypted, validation_regex, placeholder, help_text, default_value, sort_order, is_active, show_in_registration, show_in_profile) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$field_name, $field_label, $field_type, $field_options ?: null, $is_required, $is_encrypted, $validation_regex ?: null, $placeholder ?: null, $help_text ?: null, $default_value ?: null, $sort_order, $is_active, $show_in_registration, $show_in_profile]);
            }
            
            $_SESSION['success'] = 'Campo personalizado salvo com sucesso.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            $stmt = db()->prepare("DELETE FROM client_custom_fields WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = 'Campo excluído com sucesso.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Buscar campos
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = db()->query("SELECT * FROM client_custom_fields ORDER BY sort_order, field_label");
    $fields = $stmt->fetchAll();
} catch (Throwable $e) {
    $fields = [];
}

$editingField = null;
if (isset($_GET['edit'])) {
    foreach ($fields as $field) {
        if ($field['id'] == $_GET['edit']) {
            $editingField = $field;
            break;
        }
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Campos Personalizados dos Clientes</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#fieldModal">
            <i class="las la-plus me-1"></i> Novo Campo
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
                            <th>Nome Interno</th>
                            <th>Rótulo</th>
                            <th>Tipo</th>
                            <th>Obrigatório</th>
                            <th>No Registro</th>
                            <th>No Perfil</th>
                            <th>Status</th>
                            <th>Ordem</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($fields)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">Nenhum campo cadastrado</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($fields as $field): ?>
                                <tr>
                                    <td><code><?= h($field['field_name']) ?></code></td>
                                    <td><?= h($field['field_label']) ?></td>
                                    <td>
                                        <?php
                                        $types = [
                                            'text' => 'Texto',
                                            'textarea' => 'Área de Texto',
                                            'email' => 'Email',
                                            'phone' => 'Telefone',
                                            'number' => 'Número',
                                            'date' => 'Data',
                                            'select' => 'Seleção',
                                            'checkbox' => 'Checkbox',
                                            'radio' => 'Radio'
                                        ];
                                        echo h($types[$field['field_type']] ?? $field['field_type']);
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($field['is_required']): ?>
                                            <span class="badge bg-danger">Sim</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Não</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($field['show_in_registration']): ?>
                                            <span class="badge bg-success">Sim</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Não</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($field['show_in_profile']): ?>
                                            <span class="badge bg-success">Sim</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Não</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($field['is_active']): ?>
                                            <span class="badge bg-success">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $field['sort_order'] ?></td>
                                    <td>
                                        <a href="?edit=<?= $field['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="las la-edit"></i> Editar
                                        </a>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este campo?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $field['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="las la-trash"></i> Excluir
                                            </button>
                                        </form>
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

<!-- Modal para Novo/Editar Campo -->
<div class="modal fade" id="fieldModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $editingField['id'] ?? '' ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title"><?= $editingField ? 'Editar' : 'Novo' ?> Campo Personalizado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nome Interno *</label>
                            <input type="text" class="form-control" name="field_name" required value="<?= h($editingField['field_name'] ?? '') ?>" pattern="[a-zA-Z][a-zA-Z0-9_]*" placeholder="ex: company_name">
                            <small class="text-muted">Apenas letras, números e underscore (sem espaços)</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rótulo Exibido *</label>
                            <input type="text" class="form-control" name="field_label" required value="<?= h($editingField['field_label'] ?? '') ?>" placeholder="Ex: Nome da Empresa">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo de Campo *</label>
                            <select class="form-select" name="field_type" id="fieldType">
                                <option value="text" <?= ($editingField['field_type'] ?? 'text') === 'text' ? 'selected' : '' ?>>Texto</option>
                                <option value="textarea" <?= ($editingField['field_type'] ?? '') === 'textarea' ? 'selected' : '' ?>>Área de Texto</option>
                                <option value="email" <?= ($editingField['field_type'] ?? '') === 'email' ? 'selected' : '' ?>>Email</option>
                                <option value="phone" <?= ($editingField['field_type'] ?? '') === 'phone' ? 'selected' : '' ?>>Telefone</option>
                                <option value="number" <?= ($editingField['field_type'] ?? '') === 'number' ? 'selected' : '' ?>>Número</option>
                                <option value="date" <?= ($editingField['field_type'] ?? '') === 'date' ? 'selected' : '' ?>>Data</option>
                                <option value="select" <?= ($editingField['field_type'] ?? '') === 'select' ? 'selected' : '' ?>>Seleção</option>
                                <option value="checkbox" <?= ($editingField['field_type'] ?? '') === 'checkbox' ? 'selected' : '' ?>>Checkbox</option>
                                <option value="radio" <?= ($editingField['field_type'] ?? '') === 'radio' ? 'selected' : '' ?>>Radio</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ordem de Exibição</label>
                            <input type="number" class="form-control" name="sort_order" value="<?= h($editingField['sort_order'] ?? '0') ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3" id="optionsContainer" style="display: none;">
                        <label class="form-label">Opções (uma por linha)</label>
                        <textarea class="form-control" name="field_options" rows="4" placeholder="Opção 1&#10;Opção 2&#10;Opção 3"><?= h($editingField['field_options'] ?? '') ?></textarea>
                        <small class="text-muted">Para campos do tipo Select ou Radio, digite uma opção por linha</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Placeholder</label>
                            <input type="text" class="form-control" name="placeholder" value="<?= h($editingField['placeholder'] ?? '') ?>" placeholder="Texto de exemplo">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Valor Padrão</label>
                            <input type="text" class="form-control" name="default_value" value="<?= h($editingField['default_value'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Texto de Ajuda</label>
                        <input type="text" class="form-control" name="help_text" value="<?= h($editingField['help_text'] ?? '') ?>" placeholder="Texto explicativo">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Regex de Validação</label>
                        <input type="text" class="form-control" name="validation_regex" value="<?= h($editingField['validation_regex'] ?? '') ?>" placeholder="/^[a-zA-Z]+$/">
                        <small class="text-muted">Expressão regular para validação (opcional)</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_required" value="1" <?= ($editingField['is_required'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label">Campo obrigatório</label>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_encrypted" value="1" <?= ($editingField['is_encrypted'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label">Criptografar valor</label>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= ($editingField['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label">Campo ativo</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="show_in_registration" value="1" <?= ($editingField['show_in_registration'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label">Exibir no registro</label>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="show_in_profile" value="1" <?= ($editingField['show_in_profile'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label">Exibir no perfil</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Campo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('fieldType').addEventListener('change', function() {
    var type = this.value;
    var optionsContainer = document.getElementById('optionsContainer');
    optionsContainer.style.display = (type === 'select' || type === 'radio') ? 'block' : 'none';
});
document.getElementById('fieldType').dispatchEvent(new Event('change'));
</script>

<?php if ($editingField): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = new bootstrap.Modal(document.getElementById('fieldModal'));
            modal.show();
        });
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

