<?php
declare(strict_types=1);
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Configuração Fiscal';
$active = 'tax_settings';
require_once __DIR__ . '/partials/layout_start.php';

$activeTab = $_GET['tab'] ?? 'general';

// Processar salvamento de configurações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    csrf_verify($_POST['_csrf'] ?? null);
    
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->beginTransaction();
        
        foreach ($_POST as $key => $value) {
            if ($key === '_csrf' || $key === 'tab' || $key === 'save_settings') continue;
            
            if (is_array($value)) {
                $value = json_encode($value);
            }
            
            $settingType = 'text';
            if (is_numeric($value) && strpos($value, '.') === false) {
                $settingType = 'number';
            } elseif (in_array($value, ['0', '1', 'true', 'false'])) {
                $settingType = 'boolean';
                $value = in_array($value, ['1', 'true']) ? '1' : '0';
            }
            
            $group = 'general';
            if (strpos($key, 'vat_') === 0) $group = 'vat';
            elseif (strpos($key, 'advanced_') === 0) $group = 'advanced';
            
            $stmt = db()->prepare("INSERT INTO tax_settings (setting_key, setting_value, setting_type, setting_group) 
                                  VALUES (?, ?, ?, ?) 
                                  ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type)");
            $stmt->execute([$key, $value, $settingType, $group]);
        }
        
        db()->commit();
        $_SESSION['success'] = 'Configurações fiscais salvas com sucesso.';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=' . $activeTab);
        exit;
    } catch (Throwable $e) {
        db()->rollBack();
        $_SESSION['error'] = 'Erro ao salvar configurações: ' . $e->getMessage();
    }
}

// Processar regras fiscais
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_rule'])) {
    csrf_verify($_POST['_csrf'] ?? null);
    
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $id = $_POST['rule_id'] ?? null;
        $name = trim($_POST['name'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $tax_rate = floatval($_POST['tax_rate'] ?? 0);
        $tax_type = $_POST['tax_type'] ?? 'vat';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $sort_order = intval($_POST['sort_order'] ?? 0);
        
        if (empty($name)) {
            throw new Exception('Nome da regra é obrigatório.');
        }
        
        if ($id) {
            $stmt = db()->prepare("UPDATE tax_rules SET name = ?, country = ?, state = ?, tax_rate = ?, tax_type = ?, is_active = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$name, $country ?: null, $state ?: null, $tax_rate, $tax_type, $is_active, $sort_order, $id]);
        } else {
            $stmt = db()->prepare("INSERT INTO tax_rules (name, country, state, tax_rate, tax_type, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $country ?: null, $state ?: null, $tax_rate, $tax_type, $is_active, $sort_order]);
        }
        
        $_SESSION['success'] = 'Regra fiscal salva com sucesso.';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=rules');
        exit;
    } catch (Throwable $e) {
        $_SESSION['error'] = 'Erro ao salvar regra: ' . $e->getMessage();
    }
}

// Deletar regra
if (isset($_GET['delete_rule'])) {
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt = db()->prepare("DELETE FROM tax_rules WHERE id = ?");
        $stmt->execute([$_GET['delete_rule']]);
        $_SESSION['success'] = 'Regra fiscal excluída com sucesso.';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=rules');
        exit;
    } catch (Throwable $e) {
        $_SESSION['error'] = 'Erro ao excluir regra: ' . $e->getMessage();
    }
}

// Buscar configurações
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = db()->query("SELECT setting_key, setting_value, setting_type, setting_group FROM tax_settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = [
            'value' => $row['setting_value'],
            'type' => $row['setting_type'],
            'group' => $row['setting_group']
        ];
    }
} catch (Throwable $e) {
    $settings = [];
}

// Buscar regras fiscais
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = db()->query("SELECT * FROM tax_rules ORDER BY sort_order, name");
    $rules = $stmt->fetchAll();
} catch (Throwable $e) {
    $rules = [];
}

function getTaxSetting($key, $default = '') {
    global $settings;
    return $settings[$key]['value'] ?? $default;
}

$editingRule = null;
if (isset($_GET['edit_rule'])) {
    foreach ($rules as $rule) {
        if ($rule['id'] == $_GET['edit_rule']) {
            $editingRule = $rule;
            break;
        }
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Configuração Fiscal</h1>
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

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'general' ? 'active' : '' ?>" href="?tab=general">Geral</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'vat' ? 'active' : '' ?>" href="?tab=vat">IVA</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'rules' ? 'active' : '' ?>" href="?tab=rules">Regras Fiscais</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'advanced' ? 'active' : '' ?>" href="?tab=advanced">Avançado</a>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content">
        <!-- Geral -->
        <?php if ($activeTab === 'general'): ?>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="tab" value="general">
                <input type="hidden" name="save_settings" value="1">
                
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Configurações Gerais de Impostos</h5>
                        
                        <div class="mb-3">
                            <label class="form-label">Habilitar Impostos</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="tax_enabled" value="1" <?= getTaxSetting('tax_enabled') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label">Ativar cálculo de impostos</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo de Imposto Padrão</label>
                            <select class="form-select" name="default_tax_type">
                                <option value="vat" <?= getTaxSetting('default_tax_type') === 'vat' ? 'selected' : '' ?>>IVA</option>
                                <option value="sales_tax" <?= getTaxSetting('default_tax_type') === 'sales_tax' ? 'selected' : '' ?>>Imposto sobre Vendas</option>
                                <option value="gst" <?= getTaxSetting('default_tax_type') === 'gst' ? 'selected' : '' ?>>GST</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Taxa de Imposto Padrão (%)</label>
                            <input type="number" class="form-control" name="default_tax_rate" step="0.01" min="0" max="100" value="<?= h(getTaxSetting('default_tax_rate', '0')) ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Incluir Impostos nos Preços</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="tax_inclusive" value="1" <?= getTaxSetting('tax_inclusive') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label">Os preços já incluem impostos</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Aplicar Impostos a Serviços</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="tax_on_services" value="1" <?= getTaxSetting('tax_on_services') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label">Aplicar impostos a serviços</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Aplicar Impostos a Domínios</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="tax_on_domains" value="1" <?= getTaxSetting('tax_on_domains') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label">Aplicar impostos a domínios</label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Salvar Configurações</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
        
        <!-- IVA -->
        <?php if ($activeTab === 'vat'): ?>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="tab" value="vat">
                <input type="hidden" name="save_settings" value="1">
                
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Configurações de IVA</h5>
                        
                        <div class="mb-3">
                            <label class="form-label">Número de Registro IVA</label>
                            <input type="text" class="form-control" name="vat_registration_number" value="<?= h(getTaxSetting('vat_registration_number')) ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Validar Número IVA</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="vat_validation_enabled" value="1" <?= getTaxSetting('vat_validation_enabled') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label">Validar números de IVA automaticamente</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Taxa de IVA Padrão (%)</label>
                            <input type="number" class="form-control" name="vat_default_rate" step="0.01" min="0" max="100" value="<?= h(getTaxSetting('vat_default_rate', '0')) ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Aplicar IVA Reverso</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="vat_reverse_charge" value="1" <?= getTaxSetting('vat_reverse_charge') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label">Aplicar cobrança reversa de IVA</label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Salvar Configurações</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
        
        <!-- Regras Fiscais -->
        <?php if ($activeTab === 'rules'): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">Regras Fiscais</h5>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ruleModal">
                            <i class="las la-plus me-1"></i> Nova Regra
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>País</th>
                                    <th>Estado</th>
                                    <th>Taxa (%)</th>
                                    <th>Tipo</th>
                                    <th>Status</th>
                                    <th>Ordem</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rules)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">Nenhuma regra fiscal cadastrada</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rules as $rule): ?>
                                        <tr>
                                            <td><?= h($rule['name']) ?></td>
                                            <td><?= h($rule['country'] ?: '-') ?></td>
                                            <td><?= h($rule['state'] ?: '-') ?></td>
                                            <td><?= number_format($rule['tax_rate'], 2) ?>%</td>
                                            <td>
                                                <?php
                                                $types = ['vat' => 'IVA', 'sales_tax' => 'Imposto sobre Vendas', 'gst' => 'GST'];
                                                echo h($types[$rule['tax_type']] ?? $rule['tax_type']);
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($rule['is_active']): ?>
                                                    <span class="badge bg-success">Ativo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inativo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $rule['sort_order'] ?></td>
                                            <td>
                                                <a href="?tab=rules&edit_rule=<?= $rule['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="las la-edit"></i>
                                                </a>
                                                <a href="?tab=rules&delete_rule=<?= $rule['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Tem certeza que deseja excluir esta regra?')">
                                                    <i class="las la-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Modal para Nova/Editar Regra -->
            <div class="modal fade" id="ruleModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="tab" value="rules">
                            <input type="hidden" name="save_rule" value="1">
                            <input type="hidden" name="rule_id" value="<?= $editingRule['id'] ?? '' ?>">
                            
                            <div class="modal-header">
                                <h5 class="modal-title"><?= $editingRule ? 'Editar' : 'Nova' ?> Regra Fiscal</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Nome da Regra *</label>
                                    <input type="text" class="form-control" name="name" required value="<?= h($editingRule['name'] ?? '') ?>">
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">País (ISO 2 letras)</label>
                                        <input type="text" class="form-control" name="country" maxlength="2" value="<?= h($editingRule['country'] ?? '') ?>" placeholder="BR">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Estado/Província</label>
                                        <input type="text" class="form-control" name="state" value="<?= h($editingRule['state'] ?? '') ?>">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Taxa de Imposto (%) *</label>
                                        <input type="number" class="form-control" name="tax_rate" step="0.01" min="0" max="100" required value="<?= h($editingRule['tax_rate'] ?? '0') ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Tipo de Imposto *</label>
                                        <select class="form-select" name="tax_type" required>
                                            <option value="vat" <?= ($editingRule['tax_type'] ?? 'vat') === 'vat' ? 'selected' : '' ?>>IVA</option>
                                            <option value="sales_tax" <?= ($editingRule['tax_type'] ?? '') === 'sales_tax' ? 'selected' : '' ?>>Imposto sobre Vendas</option>
                                            <option value="gst" <?= ($editingRule['tax_type'] ?? '') === 'gst' ? 'selected' : '' ?>>GST</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Ordem de Exibição</label>
                                        <input type="number" class="form-control" name="sort_order" value="<?= h($editingRule['sort_order'] ?? '0') ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Status</label>
                                        <div class="form-check form-switch mt-2">
                                            <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= ($editingRule['is_active'] ?? 1) ? 'checked' : '' ?>>
                                            <label class="form-check-label">Regra ativa</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Salvar Regra</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <?php if ($editingRule): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var modal = new bootstrap.Modal(document.getElementById('ruleModal'));
                        modal.show();
                    });
                </script>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Avançado -->
        <?php if ($activeTab === 'advanced'): ?>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="tab" value="advanced">
                <input type="hidden" name="save_settings" value="1">
                
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Configurações Avançadas</h5>
                        
                        <div class="mb-3">
                            <label class="form-label">Arredondar Impostos</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="advanced_round_taxes" value="1" <?= getTaxSetting('advanced_round_taxes') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label">Arredondar valores de impostos</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Precisão de Arredondamento</label>
                            <select class="form-select" name="advanced_rounding_precision">
                                <option value="0.01" <?= getTaxSetting('advanced_rounding_precision') === '0.01' ? 'selected' : '' ?>>2 casas decimais (0.01)</option>
                                <option value="0.05" <?= getTaxSetting('advanced_rounding_precision') === '0.05' ? 'selected' : '' ?>>2 casas decimais (0.05)</option>
                                <option value="0.10" <?= getTaxSetting('advanced_rounding_precision') === '0.10' ? 'selected' : '' ?>>1 casa decimal (0.10)</option>
                                <option value="1.00" <?= getTaxSetting('advanced_rounding_precision') === '1.00' ? 'selected' : '' ?>>Sem decimais (1.00)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Exibir Impostos Separadamente</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="advanced_show_tax_separately" value="1" <?= getTaxSetting('advanced_show_tax_separately') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label">Mostrar impostos como linha separada nas faturas</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Permitir Taxa Zero</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="advanced_allow_zero_tax" value="1" <?= getTaxSetting('advanced_allow_zero_tax') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label">Permitir regras fiscais com taxa zero</label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Salvar Configurações</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

