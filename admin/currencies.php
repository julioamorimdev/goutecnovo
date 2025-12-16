<?php
declare(strict_types=1);
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Moedas';
$active = 'currencies';
require_once __DIR__ . '/partials/layout_start.php';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $action = $_POST['action'] ?? '';
        
        if ($action === 'save') {
            $id = $_POST['id'] ?? null;
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $name = trim($_POST['name'] ?? '');
            $symbol = trim($_POST['symbol'] ?? '');
            $exchange_rate = floatval($_POST['exchange_rate'] ?? 1.0);
            $is_base = isset($_POST['is_base']) ? 1 : 0;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $sort_order = intval($_POST['sort_order'] ?? 0);
            
            if (empty($code) || empty($name) || empty($symbol)) {
                throw new Exception('Código, nome e símbolo são obrigatórios.');
            }
            
            if (strlen($code) !== 3) {
                throw new Exception('O código da moeda deve ter 3 caracteres (ISO 4217).');
            }
            
            if ($is_base) {
                // Se esta moeda será a base, remover base de outras
                $stmt = db()->prepare("UPDATE currencies SET is_base = 0 WHERE id != ?");
                $stmt->execute([$id ?: 0]);
            }
            
            if ($id) {
                // Verificar se código já existe em outra moeda
                $stmt = db()->prepare("SELECT id FROM currencies WHERE code = ? AND id != ?");
                $stmt->execute([$code, $id]);
                if ($stmt->fetch()) {
                    throw new Exception('Já existe uma moeda com este código.');
                }
                
                $stmt = db()->prepare("UPDATE currencies SET code = ?, name = ?, symbol = ?, exchange_rate = ?, is_base = ?, is_active = ?, sort_order = ? WHERE id = ?");
                $stmt->execute([$code, $name, $symbol, $exchange_rate, $is_base, $is_active, $sort_order, $id]);
            } else {
                // Verificar se código já existe
                $stmt = db()->prepare("SELECT id FROM currencies WHERE code = ?");
                $stmt->execute([$code]);
                if ($stmt->fetch()) {
                    throw new Exception('Já existe uma moeda com este código.');
                }
                
                $stmt = db()->prepare("INSERT INTO currencies (code, name, symbol, exchange_rate, is_base, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$code, $name, $symbol, $exchange_rate, $is_base, $is_active, $sort_order]);
            }
            
            $_SESSION['success'] = 'Moeda salva com sucesso.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            
            // Verificar se é moeda base
            $stmt = db()->prepare("SELECT is_base FROM currencies WHERE id = ?");
            $stmt->execute([$id]);
            $currency = $stmt->fetch();
            if ($currency && $currency['is_base']) {
                throw new Exception('Não é possível excluir a moeda base.');
            }
            
            $stmt = db()->prepare("DELETE FROM currencies WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = 'Moeda excluída com sucesso.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } elseif ($action === 'update_rates') {
            // Atualizar taxas de câmbio (simulado - em produção usar API)
            $rates = $_POST['rates'] ?? [];
            foreach ($rates as $currencyId => $rate) {
                $stmt = db()->prepare("UPDATE currencies SET exchange_rate = ? WHERE id = ?");
                $stmt->execute([floatval($rate), intval($currencyId)]);
            }
            $_SESSION['success'] = 'Taxas de câmbio atualizadas com sucesso.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Buscar moedas
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = db()->query("SELECT * FROM currencies ORDER BY is_base DESC, sort_order, name");
    $currencies = $stmt->fetchAll();
} catch (Throwable $e) {
    $currencies = [];
}

$editingCurrency = null;
if (isset($_GET['edit'])) {
    foreach ($currencies as $currency) {
        if ($currency['id'] == $_GET['edit']) {
            $editingCurrency = $currency;
            break;
        }
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Moedas</h1>
        <div>
            <button type="button" class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#updateRatesModal">
                <i class="las la-sync me-1"></i> Atualizar Taxas
            </button>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#currencyModal">
                <i class="las la-plus me-1"></i> Nova Moeda
            </button>
        </div>
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
                            <th>Código</th>
                            <th>Nome</th>
                            <th>Símbolo</th>
                            <th>Taxa de Câmbio</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th>Ordem</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($currencies)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">Nenhuma moeda cadastrada</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($currencies as $currency): ?>
                                <tr>
                                    <td><strong><?= h($currency['code']) ?></strong></td>
                                    <td><?= h($currency['name']) ?></td>
                                    <td><?= h($currency['symbol']) ?></td>
                                    <td><?= number_format($currency['exchange_rate'], 4) ?></td>
                                    <td>
                                        <?php if ($currency['is_base']): ?>
                                            <span class="badge bg-primary">Base</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Secundária</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($currency['is_active']): ?>
                                            <span class="badge bg-success">Ativa</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inativa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $currency['sort_order'] ?></td>
                                    <td>
                                        <a href="?edit=<?= $currency['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="las la-edit"></i> Editar
                                        </a>
                                        <?php if (!$currency['is_base']): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir esta moeda?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $currency['id'] ?>">
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

<!-- Modal para Nova/Editar Moeda -->
<div class="modal fade" id="currencyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $editingCurrency['id'] ?? '' ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title"><?= $editingCurrency ? 'Editar' : 'Nova' ?> Moeda</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Código (ISO 4217) *</label>
                        <input type="text" class="form-control" name="code" required maxlength="3" value="<?= h($editingCurrency['code'] ?? '') ?>" placeholder="BRL">
                        <small class="text-muted">Código de 3 letras (ex: BRL, USD, EUR)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nome *</label>
                        <input type="text" class="form-control" name="name" required value="<?= h($editingCurrency['name'] ?? '') ?>" placeholder="Real Brasileiro">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Símbolo *</label>
                        <input type="text" class="form-control" name="symbol" required value="<?= h($editingCurrency['symbol'] ?? '') ?>" placeholder="R$">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Taxa de Câmbio</label>
                        <input type="number" class="form-control" name="exchange_rate" step="0.0001" min="0" value="<?= h($editingCurrency['exchange_rate'] ?? '1.0000') ?>">
                        <small class="text-muted">Taxa em relação à moeda base (1.0000 = mesma que a base)</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ordem de Exibição</label>
                            <input type="number" class="form-control" name="sort_order" value="<?= h($editingCurrency['sort_order'] ?? '0') ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Opções</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_base" value="1" <?= ($editingCurrency['is_base'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label">Moeda base</label>
                            </div>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= ($editingCurrency['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label">Moeda ativa</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Moeda</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Atualizar Taxas -->
<div class="modal fade" id="updateRatesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_rates">
                
                <div class="modal-header">
                    <h5 class="modal-title">Atualizar Taxas de Câmbio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Atualize as taxas de câmbio das moedas secundárias em relação à moeda base.</p>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Moeda</th>
                                    <th>Taxa Atual</th>
                                    <th>Nova Taxa</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($currencies as $currency): ?>
                                    <?php if (!$currency['is_base']): ?>
                                        <tr>
                                            <td><strong><?= h($currency['code']) ?> - <?= h($currency['name']) ?></strong></td>
                                            <td><?= number_format($currency['exchange_rate'], 4) ?></td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm" name="rates[<?= $currency['id'] ?>]" step="0.0001" min="0" value="<?= $currency['exchange_rate'] ?>">
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Atualizar Taxas</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editingCurrency): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = new bootstrap.Modal(document.getElementById('currencyModal'));
            modal.show();
        });
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

