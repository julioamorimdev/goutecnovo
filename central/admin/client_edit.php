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

// Estados brasileiros
$estados = [
    'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
    'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
    'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
    'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
    'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
    'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
    'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins'
];

// Países
$paises = [
    'Brasil' => 'Brasil',
    'Argentina' => 'Argentina',
    'Chile' => 'Chile',
    'Colômbia' => 'Colômbia',
    'Paraguai' => 'Paraguai',
    'Uruguai' => 'Uruguai',
    'Estados Unidos' => 'Estados Unidos',
    'Portugal' => 'Portugal',
    'Espanha' => 'Espanha',
    'Outro' => 'Outro'
];

// Códigos de país para telefone
$countryCodes = [
    '+55' => '🇧🇷 Brasil (+55)',
    '+1' => '🇺🇸 EUA/Canadá (+1)',
    '+351' => '🇵🇹 Portugal (+351)',
    '+34' => '🇪🇸 Espanha (+34)',
    '+54' => '🇦🇷 Argentina (+54)',
    '+56' => '🇨🇱 Chile (+56)',
    '+57' => '🇨🇴 Colômbia (+57)',
    '+595' => '🇵🇾 Paraguai (+595)',
    '+598' => '🇺🇾 Uruguai (+598)',
];

$item = [
    'first_name' => '',
    'last_name' => '',
    'company_name' => '',
    'email' => '',
    'phone' => '',
    'phone_code' => '+55',
    'cpf' => '',
    'cnpj' => '',
    'address' => '',
    'address_number' => '',
    'address2' => '',
    'neighborhood' => '',
    'city' => '',
    'state' => '',
    'postal_code' => '',
    'country' => 'Brasil',
    'status' => 'active',
    'notes' => '',
    'email_verified' => 0,
    'newsletter_subscribed' => 0,
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
        
        // Remover máscaras de CPF e CNPJ para exibição no formulário
        if (!empty($item['cpf'])) {
            $item['cpf'] = preg_replace('/[^0-9]/', '', $item['cpf']);
        }
        if (!empty($item['cnpj'])) {
            $item['cnpj'] = preg_replace('/[^0-9]/', '', $item['cnpj']);
        }
        if (!empty($item['postal_code'])) {
            $item['postal_code'] = preg_replace('/[^0-9]/', '', $item['postal_code']);
        }
        
        // Extrair código de país do telefone se existir
        if (!empty($item['phone'])) {
            $phoneValue = $item['phone'];
            foreach ($countryCodes as $code => $label) {
                if (strpos($phoneValue, $code) === 0) {
                    $item['phone_code'] = $code;
                    $item['phone'] = trim(str_replace($code, '', $phoneValue));
                    // Remover máscara do telefone
                    $item['phone'] = preg_replace('/[^0-9]/', '', $item['phone']);
                    break;
                }
            }
            // Se não encontrou código, manter o telefone original e usar +55 como padrão
            if (!isset($item['phone_code'])) {
                $item['phone_code'] = '+55';
                // Remover máscara do telefone
                $item['phone'] = preg_replace('/[^0-9]/', '', $phoneValue);
            }
        } else {
            $item['phone_code'] = '+55';
        }
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
    $phoneCode = trim((string)($_POST['phone_code'] ?? '+55'));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');
    $cnpj = preg_replace('/[^0-9]/', '', $_POST['cnpj'] ?? '');
    $address = trim((string)($_POST['address'] ?? ''));
    $addressNumber = trim((string)($_POST['address_number'] ?? ''));
    $address2 = trim((string)($_POST['address2'] ?? ''));
    $neighborhood = trim((string)($_POST['neighborhood'] ?? ''));
    $city = trim((string)($_POST['city'] ?? ''));
    $state = trim((string)($_POST['state'] ?? ''));
    $postalCode = preg_replace('/[^0-9]/', '', $_POST['postal_code'] ?? '');
    $country = trim((string)($_POST['country'] ?? 'Brasil'));
    $status = trim((string)($_POST['status'] ?? 'active'));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $password = trim((string)($_POST['password'] ?? ''));
    $emailVerified = isset($_POST['email_verified']) && $_POST['email_verified'] === '1' ? 1 : 0;
    $newsletterSubscribed = isset($_POST['newsletter_subscribed']) && $_POST['newsletter_subscribed'] === '1' ? 1 : 0;
    
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
            
            // Verificar CPF se fornecido
            if (!$error && !empty($cpf)) {
                $stmt = db()->prepare("SELECT id FROM clients WHERE cpf=? AND id != ?");
                $stmt->execute([$cpf, $id]);
                if ($stmt->fetch()) {
                    $error = 'Este CPF já está cadastrado.';
                }
            }
            
            // Verificar CNPJ se fornecido
            if (!$error && !empty($cnpj)) {
                $stmt = db()->prepare("SELECT id FROM clients WHERE cnpj=? AND id != ?");
                $stmt->execute([$cnpj, $id]);
                if ($stmt->fetch()) {
                    $error = 'Este CNPJ já está cadastrado.';
                }
            }
        } catch (Throwable $e) {
            // Ignorar erro na verificação
        }
    }

    // Formatar CPF e CNPJ
    $cpfFormatted = null;
    if (!empty($cpf) && strlen($cpf) === 11) {
        $cpfFormatted = substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    } elseif (!empty($cpf)) {
        $cpfFormatted = $cpf;
    }
    
    $cnpjFormatted = null;
    if (!empty($cnpj) && strlen($cnpj) === 14) {
        $cnpjFormatted = substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
    } elseif (!empty($cnpj)) {
        $cnpjFormatted = $cnpj;
    }
    
    // Formatar CEP
    $cepFormatted = null;
    if (!empty($postalCode) && strlen($postalCode) === 8) {
        $cepFormatted = substr($postalCode, 0, 5) . '-' . substr($postalCode, 5, 3);
    } elseif (!empty($postalCode)) {
        $cepFormatted = $postalCode;
    }
    
    // Formatar telefone completo
    $phoneFull = null;
    if (!empty($phone)) {
        $phoneFull = $phoneCode . ' ' . $phone;
    }
    
    $data = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'company_name' => $companyName !== '' ? $companyName : null,
        'email' => $email,
        'phone' => $phoneFull,
        'cpf' => $cpfFormatted,
        'cnpj' => $cnpjFormatted,
        'address' => $address !== '' ? $address : null,
        'address_number' => $addressNumber !== '' ? $addressNumber : null,
        'address2' => $address2 !== '' ? $address2 : null,
        'neighborhood' => $neighborhood !== '' ? $neighborhood : null,
        'city' => $city !== '' ? $city : null,
        'state' => $state !== '' ? $state : null,
        'postal_code' => $cepFormatted,
        'country' => $country,
        'status' => $status,
        'notes' => $notes !== '' ? $notes : null,
        'email_verified' => $emailVerified,
        'newsletter_subscribed' => $newsletterSubscribed,
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
            
            // Tentar adicionar colunas se não existirem
            try {
                db()->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS cpf VARCHAR(14) NULL");
                db()->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS cnpj VARCHAR(18) NULL");
                db()->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS address_number VARCHAR(20) NULL");
                db()->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS neighborhood VARCHAR(100) NULL");
                db()->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) NOT NULL DEFAULT 0");
                db()->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS newsletter_subscribed TINYINT(1) NOT NULL DEFAULT 0");
            } catch (Throwable $e) {
                // Ignorar erros de colunas já existentes
            }
            
            if ($id > 0) {
                $updateFields = [
                    'first_name', 'last_name', 'company_name', 'email', 'phone', 
                    'cpf', 'cnpj', 'address', 'address_number', 'address2', 
                    'neighborhood', 'city', 'state', 'postal_code', 'country', 
                    'status', 'notes', 'email_verified', 'newsletter_subscribed'
                ];
                $updateSet = [];
                foreach ($updateFields as $field) {
                    $updateSet[] = "$field=:$field";
                }
                $updateSql = "UPDATE clients SET " . implode(', ', $updateSet);
                
                if (isset($data['password_hash'])) {
                    $updateSql = str_replace('newsletter_subscribed=:newsletter_subscribed', 'newsletter_subscribed=:newsletter_subscribed, password_hash=:password_hash', $updateSql);
                }
                $updateSql .= " WHERE id=:id";
                
                $stmt = db()->prepare($updateSql);
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
                    $insertFields = [
                        'first_name', 'last_name', 'company_name', 'email', 'phone', 
                        'cpf', 'cnpj', 'address', 'address_number', 'address2', 
                        'neighborhood', 'city', 'state', 'postal_code', 'country', 
                        'status', 'notes', 'email_verified', 'newsletter_subscribed', 'password_hash'
                    ];
                    $insertSql = "INSERT INTO clients (" . implode(', ', $insertFields) . ") VALUES (:" . implode(', :', $insertFields) . ")";
                    $stmt = db()->prepare($insertSql);
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

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="company_name" class="form-label">Nome da Empresa</label>
                                <input type="text" class="form-control" id="company_name" name="company_name" value="<?= h($item['company_name']) ?>">
                                <small class="text-muted">Opcional</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="cnpj" class="form-label">CNPJ</label>
                                <input type="text" class="form-control" id="cnpj" name="cnpj" value="<?= h($item['cnpj'] ?? '') ?>" placeholder="00.000.000/0000-00">
                                <small class="text-muted">Opcional</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= h($item['email']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Telefone</label>
                            <div class="input-group">
                                <select class="form-select" id="phone_code" name="phone_code" style="max-width: 180px;">
                                    <?php foreach ($countryCodes as $code => $label): ?>
                                    <option value="<?= h($code) ?>" <?= ($item['phone_code'] ?? '+55') === $code ? 'selected' : '' ?>>
                                        <?= h($label) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?= h($item['phone']) ?>" placeholder="(11) 98765-4321">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="cpf" class="form-label">CPF</label>
                            <input type="text" class="form-control" id="cpf" name="cpf" value="<?= h($item['cpf'] ?? '') ?>" placeholder="000.000.000-00">
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Endereço</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="address" class="form-label">Endereço</label>
                                <input type="text" class="form-control" id="address" name="address" value="<?= h($item['address']) ?>" placeholder="Rua, Avenida">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="address_number" class="form-label">Número</label>
                                <input type="text" class="form-control" id="address_number" name="address_number" value="<?= h($item['address_number'] ?? '') ?>" placeholder="123">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="address2" class="form-label">Complemento</label>
                                <input type="text" class="form-control" id="address2" name="address2" value="<?= h($item['address2']) ?>" placeholder="Apartamento, bloco, etc.">
                                <small class="text-muted">Opcional</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="neighborhood" class="form-label">Bairro</label>
                                <input type="text" class="form-control" id="neighborhood" name="neighborhood" value="<?= h($item['neighborhood'] ?? '') ?>" placeholder="Centro">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="city" class="form-label">Cidade</label>
                                <input type="text" class="form-control" id="city" name="city" value="<?= h($item['city']) ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="state" class="form-label">Estado</label>
                                <select class="form-select" id="state" name="state">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($estados as $sigla => $nome): ?>
                                    <option value="<?= h($sigla) ?>" <?= ($item['state'] ?? '') === $sigla ? 'selected' : '' ?>>
                                        <?= h($sigla) ?> - <?= h($nome) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="postal_code" class="form-label">CEP</label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?= h($item['postal_code']) ?>" placeholder="00000-000">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="country" class="form-label">País</label>
                            <select class="form-select" id="country" name="country" required>
                                <?php foreach ($paises as $codigo => $nome): ?>
                                <option value="<?= h($nome) ?>" <?= ($item['country'] ?? 'Brasil') === $nome ? 'selected' : '' ?>>
                                    <?= h($nome) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
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

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="email_verified" name="email_verified" value="1" <?= ($item['email_verified'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="email_verified">
                                    Email Verificado
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="newsletter_subscribed" name="newsletter_subscribed" value="1" <?= ($item['newsletter_subscribed'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="newsletter_subscribed">
                                    Inscrito na Newsletter
                                </label>
                            </div>
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

<script>
    // Máscaras
    function maskCPF(input) {
        let value = input.value.replace(/\D/g, '');
        if (value.length <= 11) {
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            input.value = value;
        }
    }

    function maskCNPJ(input) {
        let value = input.value.replace(/\D/g, '');
        if (value.length <= 14) {
            value = value.replace(/(\d{2})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1/$2');
            value = value.replace(/(\d{4})(\d{1,2})$/, '$1-$2');
            input.value = value;
        }
    }

    function maskCEP(input) {
        let value = input.value.replace(/\D/g, '');
        if (value.length <= 8) {
            value = value.replace(/(\d{5})(\d)/, '$1-$2');
            input.value = value;
        }
    }

    function maskPhone(input) {
        let value = input.value.replace(/\D/g, '');
        if (value.length <= 11) {
            value = value.replace(/(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{5})(\d)/, '$1-$2');
            input.value = value;
        }
    }

    // Aplicar máscaras quando a página carregar
    document.addEventListener('DOMContentLoaded', function() {
        const cpfInput = document.getElementById('cpf');
        const cnpjInput = document.getElementById('cnpj');
        const cepInput = document.getElementById('postal_code');
        const phoneInput = document.getElementById('phone');

        if (cpfInput) {
            cpfInput.addEventListener('input', (e) => maskCPF(e.target));
        }
        if (cnpjInput) {
            cnpjInput.addEventListener('input', (e) => maskCNPJ(e.target));
        }
        if (cepInput) {
            cepInput.addEventListener('input', (e) => maskCEP(e.target));
        }
        if (phoneInput) {
            phoneInput.addEventListener('input', (e) => maskPhone(e.target));
        }
    });
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>
