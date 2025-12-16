<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$page_title = $id ? 'Editar Grupo de Clientes' : 'Novo Grupo de Clientes';
$active = 'client_groups';
require_once __DIR__ . '/partials/layout_start.php';

$error = null;

$item = [
    'name' => '',
    'description' => '',
    'color' => '#6c757d',
    'discount_percentage' => '0.00',
    'is_default' => 0,
    'sort_order' => 0,
];

if ($id > 0) {
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt = db()->prepare("SELECT * FROM client_groups WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            exit('Grupo não encontrado.');
        }
        $item = array_merge($item, $row);
    } catch (Throwable $e) {
        http_response_code(404);
        exit('Erro ao buscar grupo.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $color = trim((string)($_POST['color'] ?? '#6c757d'));
    $discount_percentage = (float)($_POST['discount_percentage'] ?? 0);
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    // Validação
    if (empty($name)) {
        $error = 'O nome do grupo é obrigatório.';
    } elseif (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        $error = 'A cor deve estar no formato hexadecimal (#RRGGBB).';
    } elseif ($discount_percentage < 0 || $discount_percentage > 100) {
        $error = 'O desconto deve estar entre 0 e 100%.';
    } else {
        try {
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Se está marcando como padrão, remover padrão de outros grupos
            if ($is_default) {
                db()->prepare("UPDATE client_groups SET is_default = 0")->execute();
            }
            
            if ($id > 0) {
                // Atualizar
                $stmt = db()->prepare("UPDATE client_groups SET name=?, description=?, color=?, discount_percentage=?, is_default=?, sort_order=? WHERE id=?");
                $stmt->execute([$name, $description ?: null, $color, $discount_percentage, $is_default, $sort_order, $id]);
                $_SESSION['success'] = 'Grupo atualizado com sucesso.';
            } else {
                // Criar
                $stmt = db()->prepare("INSERT INTO client_groups (name, description, color, discount_percentage, is_default, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $description ?: null, $color, $discount_percentage, $is_default, $sort_order]);
                $_SESSION['success'] = 'Grupo criado com sucesso.';
            }
            
            header('Location: /admin/client_groups.php');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'Já existe um grupo com este nome.';
            } else {
                $error = 'Erro ao salvar grupo: ' . $e->getMessage();
            }
        } catch (Throwable $e) {
            $error = 'Erro ao salvar grupo: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><?= $id ? 'Editar' : 'Novo' ?> Grupo de Clientes</h2>
            <p class="text-body-secondary mb-0"><?= $id ? 'Atualize as informações do grupo' : 'Crie um novo grupo de clientes' ?></p>
        </div>
        <a href="/admin/client_groups.php" class="btn btn-outline-dark">
            <i class="las la-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Nome do Grupo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= h($item['name']) ?>" required placeholder="Ex: VIP, Premium, Básico">
                            <small class="text-muted">Nome único para identificar o grupo</small>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Descrição</label>
                            <textarea class="form-control" id="description" name="description" rows="3" placeholder="Descrição opcional do grupo"><?= h($item['description']) ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="color" class="form-label">Cor <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="color" name="color" value="<?= h($item['color']) ?>" title="Escolha a cor">
                                    <input type="text" class="form-control" value="<?= h($item['color']) ?>" id="colorText" pattern="^#[0-9A-Fa-f]{6}$" required>
                                </div>
                                <small class="text-muted">Cor para identificar visualmente o grupo</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="discount_percentage" class="form-label">Desconto (%)</label>
                                <input type="number" class="form-control" id="discount_percentage" name="discount_percentage" value="<?= number_format((float)$item['discount_percentage'], 2, '.', '') ?>" min="0" max="100" step="0.01" placeholder="0.00">
                                <small class="text-muted">Desconto percentual automático para clientes deste grupo (0-100%)</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="sort_order" class="form-label">Ordem de Exibição</label>
                                <input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= (int)$item['sort_order'] ?>" min="0" step="1">
                                <small class="text-muted">Número menor aparece primeiro na lista</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="is_default" name="is_default" value="1" <?= (int)$item['is_default'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_default">
                                        Grupo Padrão
                                    </label>
                                    <div class="form-text">Novos clientes serão automaticamente adicionados a este grupo</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="las la-save me-1"></i> Salvar
                            </button>
                            <a href="/admin/client_groups.php" class="btn btn-outline-dark">
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><i class="las la-info-circle me-2"></i> Informações</h5>
                    <p class="card-text small">
                        <strong>Grupos de Clientes</strong> permitem organizar seus clientes e aplicar políticas diferentes para cada grupo.
                    </p>
                    <ul class="small mb-0">
                        <li>Descontos são aplicados automaticamente em pedidos</li>
                        <li>Apenas um grupo pode ser o padrão</li>
                        <li>Grupos com clientes não podem ser excluídos</li>
                        <li>O grupo padrão não pode ser excluído</li>
                    </ul>
                </div>
            </div>

            <?php if ($id > 0): ?>
                <div class="card mt-3">
                    <div class="card-body">
                        <h5 class="card-title"><i class="las la-chart-bar me-2"></i> Estatísticas</h5>
                        <?php
                        try {
                            $stmt = db()->prepare("SELECT COUNT(*) as count FROM clients WHERE group_id = ?");
                            $stmt->execute([$id]);
                            $result = $stmt->fetch();
                            $clientCount = (int)($result['count'] ?? 0);
                        } catch (Throwable $e) {
                            $clientCount = 0;
                        }
                        ?>
                        <p class="mb-0">
                            <strong>Clientes neste grupo:</strong> <?= $clientCount ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Sincronizar color picker com input de texto
document.getElementById('color').addEventListener('input', function(e) {
    document.getElementById('colorText').value = e.target.value.toUpperCase();
});

document.getElementById('colorText').addEventListener('input', function(e) {
    const value = e.target.value;
    if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
        document.getElementById('color').value = value;
    }
});
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

