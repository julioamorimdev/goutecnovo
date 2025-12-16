<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$page_title = $id ? 'Editar Afiliado' : 'Novo Afiliado';
$active = 'affiliates';
require_once __DIR__ . '/partials/layout_start.php';

$error = null;

$item = [
    'code' => '',
    'first_name' => '',
    'last_name' => '',
    'company_name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'address2' => '',
    'city' => '',
    'state' => '',
    'postal_code' => '',
    'country' => 'Brasil',
    'payment_method' => '',
    'payment_details' => '',
    'commission_type' => 'percentage',
    'commission_value' => '10.00',
    'minimum_payout' => '50.00',
    'status' => 'active',
    'notes' => '',
];

if ($id > 0) {
    try {
        // Garantir UTF-8 na conexão
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->exec("SET CHARACTER SET utf8mb4");
        db()->exec("SET character_set_connection=utf8mb4");
        
        $stmt = db()->prepare("SELECT * FROM affiliates WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            exit('Afiliado não encontrado.');
        }
        $item = array_merge($item, $row);
    } catch (Throwable $e) {
        http_response_code(404);
        exit('Erro ao buscar afiliado.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $code = trim((string)($_POST['code'] ?? ''));
    $firstName = trim((string)($_POST['first_name'] ?? ''));
    $lastName = trim((string)($_POST['last_name'] ?? ''));
    $companyName = trim((string)($_POST['company_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $address2 = trim((string)($_POST['address2'] ?? ''));
    $city = trim((string)($_POST['city'] ?? ''));
    $state = trim((string)($_POST['state'] ?? ''));
    $postalCode = trim((string)($_POST['postal_code'] ?? ''));
    $country = trim((string)($_POST['country'] ?? 'Brasil'));
    $paymentMethod = trim((string)($_POST['payment_method'] ?? ''));
    $paymentDetails = trim((string)($_POST['payment_details'] ?? ''));
    $commissionType = trim((string)($_POST['commission_type'] ?? 'percentage'));
    $commissionValue = (float)($_POST['commission_value'] ?? 0);
    $minimumPayout = (float)($_POST['minimum_payout'] ?? 50);
    $status = trim((string)($_POST['status'] ?? 'active'));
    $notes = trim((string)($_POST['notes'] ?? ''));
    
    if ($code === '') $error = 'O código do afiliado é obrigatório.';
    if ($firstName === '') $error = 'O nome é obrigatório.';
    if ($lastName === '') $error = 'O sobrenome é obrigatório.';
    if ($email === '') {
        $error = 'O email é obrigatório.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'O email informado é inválido.';
    }
    if ($commissionValue <= 0) $error = 'O valor da comissão deve ser maior que zero.';
    
    if (!in_array($status, ['active', 'inactive', 'suspended'], true)) {
        $status = 'active';
    }
    
    if (!in_array($commissionType, ['percentage', 'fixed'], true)) {
        $commissionType = 'percentage';
    }
    
    // Gerar código automaticamente se não fornecido
    if ($code === '' && $id === 0) {
        $code = strtoupper(substr($firstName, 0, 3) . substr($lastName, 0, 3) . rand(1000, 9999));
    }
    
    // Verificar se o código já existe (exceto para o próprio afiliado)
    if (!$error) {
        try {
            // Garantir UTF-8 na conexão
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            db()->exec("SET CHARACTER SET utf8mb4");
            db()->exec("SET character_set_connection=utf8mb4");
            
            $stmt = db()->prepare("SELECT id FROM affiliates WHERE code=? AND id != ?");
            $stmt->execute([$code, $id]);
            if ($stmt->fetch()) {
                $error = 'Este código de afiliado já está em uso.';
            }
        } catch (Throwable $e) {
            // Ignorar erro na verificação
        }
    }
    
    // Verificar se o email já existe (exceto para o próprio afiliado)
    if (!$error) {
        try {
            $stmt = db()->prepare("SELECT id FROM affiliates WHERE email=? AND id != ?");
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                $error = 'Este email já está cadastrado.';
            }
        } catch (Throwable $e) {
            // Ignorar erro na verificação
        }
    }

    $data = [
        'code' => $code,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'company_name' => $companyName !== '' ? $companyName : null,
        'email' => $email,
        'phone' => $phone !== '' ? $phone : null,
        'address' => $address !== '' ? $address : null,
        'address2' => $address2 !== '' ? $address2 : null,
        'city' => $city !== '' ? $city : null,
        'state' => $state !== '' ? $state : null,
        'postal_code' => $postalCode !== '' ? $postalCode : null,
        'country' => $country,
        'payment_method' => $paymentMethod !== '' ? $paymentMethod : null,
        'payment_details' => $paymentDetails !== '' ? $paymentDetails : null,
        'commission_type' => $commissionType,
        'commission_value' => $commissionValue,
        'minimum_payout' => $minimumPayout,
        'status' => $status,
        'notes' => $notes !== '' ? $notes : null,
    ];

    if (!$error) {
        try {
            // Garantir UTF-8 na conexão
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            db()->exec("SET CHARACTER SET utf8mb4");
            db()->exec("SET character_set_connection=utf8mb4");
            
            if ($id > 0) {
                $stmt = db()->prepare("UPDATE affiliates SET code=:code, first_name=:first_name, last_name=:last_name, company_name=:company_name, email=:email, phone=:phone, address=:address, address2=:address2, city=:city, state=:state, postal_code=:postal_code, country=:country, payment_method=:payment_method, payment_details=:payment_details, commission_type=:commission_type, commission_value=:commission_value, minimum_payout=:minimum_payout, status=:status, notes=:notes WHERE id=:id");
                $data['id'] = $id;
                $stmt->execute($data);
                $_SESSION['success'] = 'Afiliado atualizado com sucesso.';
            } else {
                $stmt = db()->prepare("INSERT INTO affiliates (code, first_name, last_name, company_name, email, phone, address, address2, city, state, postal_code, country, payment_method, payment_details, commission_type, commission_value, minimum_payout, status, notes) VALUES (:code, :first_name, :last_name, :company_name, :email, :phone, :address, :address2, :city, :state, :postal_code, :country, :payment_method, :payment_details, :commission_type, :commission_value, :minimum_payout, :status, :notes)");
                $stmt->execute($data);
                $_SESSION['success'] = 'Afiliado criado com sucesso.';
            }
            
            header('Location: /admin/affiliates.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Erro ao salvar afiliado: ' . $e->getMessage();
        }
    }
    $item = array_merge($item, $data);
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= $id ? 'Editar Afiliado' : 'Novo Afiliado' ?></h1>
        <a href="/admin/affiliates.php" class="btn btn-secondary">
            <i class="las la-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Informações Pessoais</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="code" class="form-label">Código do Afiliado <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="code" name="code" value="<?= h($item['code']) ?>" required>
                                <small class="text-muted">Código único para identificação (ex: AFF001)</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= h($item['email']) ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">Nome <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?= h($item['first_name']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Sobrenome <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?= h($item['last_name']) ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="company_name" class="form-label">Nome da Empresa</label>
                            <input type="text" class="form-control" id="company_name" name="company_name" value="<?= h($item['company_name']) ?>">
                            <small class="text-muted">Opcional</small>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Telefone</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?= h($item['phone']) ?>" placeholder="(11) 98765-4321">
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Endereço</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="address" class="form-label">Endereço</label>
                            <input type="text" class="form-control" id="address" name="address" value="<?= h($item['address']) ?>" placeholder="Rua, número">
                        </div>

                        <div class="mb-3">
                            <label for="address2" class="form-label">Complemento</label>
                            <input type="text" class="form-control" id="address2" name="address2" value="<?= h($item['address2']) ?>" placeholder="Apartamento, bloco, etc.">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="city" class="form-label">Cidade</label>
                                <input type="text" class="form-control" id="city" name="city" value="<?= h($item['city']) ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="state" class="form-label">Estado</label>
                                <input type="text" class="form-control" id="state" name="state" value="<?= h($item['state']) ?>" placeholder="SP" maxlength="2">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="postal_code" class="form-label">CEP</label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?= h($item['postal_code']) ?>" placeholder="01234-567">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="country" class="form-label">País</label>
                            <input type="text" class="form-control" id="country" name="country" value="<?= h($item['country']) ?>" required>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Informações de Pagamento</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="payment_method" class="form-label">Método de Pagamento</label>
                            <select class="form-select" id="payment_method" name="payment_method">
                                <option value="">Selecione...</option>
                                <option value="bank_transfer" <?= $item['payment_method'] === 'bank_transfer' ? 'selected' : '' ?>>Transferência Bancária</option>
                                <option value="pix" <?= $item['payment_method'] === 'pix' ? 'selected' : '' ?>>PIX</option>
                                <option value="paypal" <?= $item['payment_method'] === 'paypal' ? 'selected' : '' ?>>PayPal</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="payment_details" class="form-label">Detalhes do Pagamento</label>
                            <textarea class="form-control" id="payment_details" name="payment_details" rows="4" placeholder="Conta bancária, chave PIX, email PayPal, etc."><?= h($item['payment_details']) ?></textarea>
                            <small class="text-muted">Informações necessárias para realizar o pagamento</small>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Observações</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notas sobre o Afiliado</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4"><?= h($item['notes']) ?></textarea>
                            <small class="text-muted">Informações adicionais sobre o afiliado</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Configurações de Comissão</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="commission_type" class="form-label">Tipo de Comissão</label>
                            <select class="form-select" id="commission_type" name="commission_type">
                                <option value="percentage" <?= $item['commission_type'] === 'percentage' ? 'selected' : '' ?>>Percentual (%)</option>
                                <option value="fixed" <?= $item['commission_type'] === 'fixed' ? 'selected' : '' ?>>Valor Fixo (R$)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="commission_value" class="form-label">Valor da Comissão <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <?php if ($item['commission_type'] === 'percentage'): ?>
                                    <input type="number" class="form-control" id="commission_value" name="commission_value" value="<?= h($item['commission_value']) ?>" step="0.01" min="0" max="100" required>
                                    <span class="input-group-text">%</span>
                                <?php else: ?>
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="commission_value" name="commission_value" value="<?= h($item['commission_value']) ?>" step="0.01" min="0" required>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="minimum_payout" class="form-label">Valor Mínimo para Saque</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="number" class="form-control" id="minimum_payout" name="minimum_payout" value="<?= h($item['minimum_payout']) ?>" step="0.01" min="0" required>
                            </div>
                            <small class="text-muted">Valor mínimo acumulado necessário para solicitar saque</small>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?= $item['status'] === 'active' ? 'selected' : '' ?>>Ativo</option>
                                <option value="inactive" <?= $item['status'] === 'inactive' ? 'selected' : '' ?>>Inativo</option>
                                <option value="suspended" <?= $item['status'] === 'suspended' ? 'selected' : '' ?>>Suspenso</option>
                            </select>
                        </div>

                        <?php if ($id > 0): ?>
                            <div class="mb-3">
                                <label class="form-label">Estatísticas</label>
                                <div class="small text-muted">
                                    <div><strong>Total Ganho:</strong> R$ <?= number_format((float)$item['total_earnings'], 2, ',', '.') ?></div>
                                    <div><strong>Total Pago:</strong> R$ <?= number_format((float)$item['paid_earnings'], 2, ',', '.') ?></div>
                                    <div><strong>Pendente:</strong> R$ <?= number_format((float)$item['pending_earnings'], 2, ',', '.') ?></div>
                                    <div><strong>Indicações:</strong> <?= (int)$item['total_referrals'] ?></div>
                                    <div><strong>Vendas:</strong> <?= (int)$item['total_sales'] ?></div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Informações do Sistema</label>
                                <div class="small text-muted">
                                    <div><strong>ID:</strong> #<?= (int)$id ?></div>
                                    <div><strong>Registrado em:</strong> <?= date('d/m/Y H:i', strtotime($item['created_at'] ?? 'now')) ?></div>
                                    <?php if ($item['updated_at'] ?? ''): ?>
                                        <div><strong>Última atualização:</strong> <?= date('d/m/Y H:i', strtotime($item['updated_at'])) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="las la-save me-1"></i> Salvar
            </button>
            <a href="/admin/affiliates.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
document.getElementById('commission_type').addEventListener('change', function() {
    const valueInput = document.getElementById('commission_value');
    const inputGroup = valueInput.closest('.input-group');
    const currentValue = valueInput.value;
    
    if (this.value === 'percentage') {
        inputGroup.innerHTML = '<input type="number" class="form-control" id="commission_value" name="commission_value" value="' + currentValue + '" step="0.01" min="0" max="100" required><span class="input-group-text">%</span>';
    } else {
        inputGroup.innerHTML = '<span class="input-group-text">R$</span><input type="number" class="form-control" id="commission_value" name="commission_value" value="' + currentValue + '" step="0.01" min="0" required>';
    }
});
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

