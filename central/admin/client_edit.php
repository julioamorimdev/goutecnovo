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

$page_title = $id ? 'Editar Cliente' : 'Novo Cliente';
$active = 'clients';
require_once __DIR__ . '/partials/layout_start.php';

$error = null;

$item = [
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
    'status' => 'active',
    'notes' => '',
];

if ($id > 0) {
    try {
        // Garantir UTF-8 na conexão
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->exec("SET CHARACTER SET utf8mb4");
        db()->exec("SET character_set_connection=utf8mb4");
        
        $stmt = db()->prepare("SELECT * FROM clients WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            exit('Cliente não encontrado.');
        }
        $item = array_merge($item, $row);
    } catch (Throwable $e) {
        http_response_code(404);
        exit('Erro ao buscar cliente.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

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
    $status = trim((string)($_POST['status'] ?? 'active'));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $password = trim((string)($_POST['password'] ?? ''));
    
    if ($firstName === '') $error = 'O nome é obrigatório.';
    if ($lastName === '') $error = 'O sobrenome é obrigatório.';
    if ($email === '') {
        $error = 'O email é obrigatório.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'O email informado é inválido.';
    }
    
    if (!in_array($status, ['active', 'inactive', 'closed'], true)) {
        $status = 'active';
    }
    
    // Verificar se o email já existe (exceto para o próprio cliente)
    if (!$error) {
        try {
            // Garantir UTF-8 na conexão
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            db()->exec("SET CHARACTER SET utf8mb4");
            db()->exec("SET character_set_connection=utf8mb4");
            
            $stmt = db()->prepare("SELECT id FROM clients WHERE email=? AND id != ?");
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                $error = 'Este email já está cadastrado.';
            }
        } catch (Throwable $e) {
            // Ignorar erro na verificação
        }
    }

    $data = [
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
        'status' => $status,
        'notes' => $notes !== '' ? $notes : null,
    ];
    
    // Se uma senha foi fornecida, criar hash
    if ($password !== '') {
        $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }

    if (!$error) {
        try {
            // Garantir UTF-8 na conexão
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            db()->exec("SET CHARACTER SET utf8mb4");
            db()->exec("SET character_set_connection=utf8mb4");
            
            if ($id > 0) {
                if (isset($data['password_hash'])) {
                    $stmt = db()->prepare("UPDATE clients SET first_name=:first_name, last_name=:last_name, company_name=:company_name, email=:email, phone=:phone, address=:address, address2=:address2, city=:city, state=:state, postal_code=:postal_code, country=:country, status=:status, notes=:notes, password_hash=:password_hash WHERE id=:id");
                } else {
                    $stmt = db()->prepare("UPDATE clients SET first_name=:first_name, last_name=:last_name, company_name=:company_name, email=:email, phone=:phone, address=:address, address2=:address2, city=:city, state=:state, postal_code=:postal_code, country=:country, status=:status, notes=:notes WHERE id=:id");
                }
                $data['id'] = $id;
                $stmt->execute($data);
                $_SESSION['success'] = 'Cliente atualizado com sucesso.';
            } else {
                // Para novo cliente, senha é obrigatória
                if (!isset($data['password_hash'])) {
                    if ($password === '') {
                        $error = 'A senha é obrigatória para novos clientes.';
                    } else {
                        $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                    }
                }
                
                if (!$error) {
                    $stmt = db()->prepare("INSERT INTO clients (first_name, last_name, company_name, email, phone, address, address2, city, state, postal_code, country, status, notes, password_hash) VALUES (:first_name, :last_name, :company_name, :email, :phone, :address, :address2, :city, :state, :postal_code, :country, :status, :notes, :password_hash)");
                    $stmt->execute($data);
                    $_SESSION['success'] = 'Cliente criado com sucesso.';
                }
            }
            
            if (!$error) {
                header('Location: /admin/clients.php');
                exit;
            }
        } catch (Throwable $e) {
            $error = 'Erro ao salvar cliente: ' . $e->getMessage();
        }
    }
    $item = array_merge($item, $data);
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= $id ? 'Editar Cliente' : 'Novo Cliente' ?></h1>
        <a href="/admin/clients.php" class="btn btn-secondary">
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
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= h($item['email']) ?>" required>
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
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Observações</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notas sobre o Cliente</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4"><?= h($item['notes']) ?></textarea>
                            <small class="text-muted">Informações adicionais sobre o cliente</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Configurações</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?= $item['status'] === 'active' ? 'selected' : '' ?>>Ativo</option>
                                <option value="inactive" <?= $item['status'] === 'inactive' ? 'selected' : '' ?>>Inativo</option>
                                <option value="closed" <?= $item['status'] === 'closed' ? 'selected' : '' ?>>Fechado</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label"><?= $id ? 'Nova Senha' : 'Senha' ?> <?= $id ? '' : '<span class="text-danger">*</span>' ?></label>
                            <input type="password" class="form-control" id="password" name="password" <?= $id ? '' : 'required' ?>>
                            <small class="text-muted"><?= $id ? 'Deixe em branco para manter a senha atual' : 'Senha para acesso ao painel do cliente' ?></small>
                        </div>

                        <?php if ($id > 0): ?>
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
            <a href="/admin/clients.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>
