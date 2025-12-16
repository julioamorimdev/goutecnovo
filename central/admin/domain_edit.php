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

$page_title = $id ? 'Editar Registro de Domínio' : 'Novo Registro de Domínio';
$active = 'domains';
require_once __DIR__ . '/partials/layout_start.php';

$error = null;

$item = [
    'client_id' => 0,
    'tld_id' => 0,
    'domain_name' => '',
    'full_domain' => '',
    'registration_date' => date('Y-m-d'),
    'expiration_date' => date('Y-m-d', strtotime('+1 year')),
    'years' => 1,
    'status' => 'active',
    'auto_renew' => 1,
    'privacy_protection' => 0,
    'nameservers' => '[]',
    'registrar' => '',
    'epp_code' => '',
    'notes' => '',
];

if ($id > 0) {
    try {
        // Garantir UTF-8 na conexão
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->exec("SET CHARACTER SET utf8mb4");
        db()->exec("SET character_set_connection=utf8mb4");
        
        $stmt = db()->prepare("SELECT * FROM domain_registrations WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            exit('Registro de domínio não encontrado.');
        }
        $item = array_merge($item, $row);
        // Converter nameservers JSON para string
        if (is_string($item['nameservers'])) {
            $nameserversArray = json_decode($item['nameservers'], true) ?: [];
        } else {
            $nameserversArray = $item['nameservers'] ?: [];
        }
        $item['nameservers'] = is_array($nameserversArray) ? implode("\n", $nameserversArray) : '';
    } catch (Throwable $e) {
        http_response_code(404);
        exit('Erro ao buscar registro de domínio.');
    }
}

// Buscar clientes e TLDs
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $clients = db()->query("SELECT id, first_name, last_name, email, company_name FROM clients WHERE status='active' ORDER BY first_name, last_name")->fetchAll();
    $tlds = db()->query("SELECT id, tld, name FROM tlds WHERE is_enabled=1 ORDER BY sort_order ASC")->fetchAll();
} catch (Throwable $e) {
    $clients = [];
    $tlds = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $clientId = (int)($_POST['client_id'] ?? 0);
    $tldId = (int)($_POST['tld_id'] ?? 0);
    $domainName = trim((string)($_POST['domain_name'] ?? ''));
    $registrationDate = trim((string)($_POST['registration_date'] ?? ''));
    $expirationDate = trim((string)($_POST['expiration_date'] ?? ''));
    $years = (int)($_POST['years'] ?? 1);
    $status = trim((string)($_POST['status'] ?? 'active'));
    $autoRenew = isset($_POST['auto_renew']) ? 1 : 0;
    $privacyProtection = isset($_POST['privacy_protection']) ? 1 : 0;
    $nameservers = trim((string)($_POST['nameservers'] ?? ''));
    $registrar = trim((string)($_POST['registrar'] ?? ''));
    $eppCode = trim((string)($_POST['epp_code'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));
    
    if ($clientId <= 0) $error = 'O cliente é obrigatório.';
    if ($tldId <= 0) $error = 'O TLD é obrigatório.';
    if ($domainName === '') $error = 'O nome do domínio é obrigatório.';
    if ($registrationDate === '') $error = 'A data de registro é obrigatória.';
    if ($expirationDate === '') $error = 'A data de expiração é obrigatória.';
    if ($years < 1) $error = 'Os anos devem ser pelo menos 1.';
    
    if (!in_array($status, ['active', 'expired', 'suspended', 'cancelled', 'pending_transfer'], true)) {
        $status = 'active';
    }
    
    // Construir full_domain
    if (!$error && $tldId > 0 && $domainName !== '') {
        $stmt = db()->prepare("SELECT tld FROM tlds WHERE id=?");
        $stmt->execute([$tldId]);
        $tldRow = $stmt->fetch();
        if ($tldRow) {
            $fullDomain = $domainName . $tldRow['tld'];
            
            // Verificar se o domínio já existe (exceto para o próprio registro)
            $stmt = db()->prepare("SELECT id FROM domain_registrations WHERE full_domain=? AND id != ?");
            $stmt->execute([$fullDomain, $id]);
            if ($stmt->fetch()) {
                $error = 'Este domínio já está registrado.';
            }
        }
    }
    
    // Processar nameservers
    $nameserversArray = [];
    if ($nameservers !== '') {
        $nsLines = explode("\n", $nameservers);
        foreach ($nsLines as $ns) {
            $ns = trim($ns);
            if ($ns !== '') {
                $nameserversArray[] = $ns;
            }
        }
    }

    $data = [
        'client_id' => $clientId,
        'tld_id' => $tldId,
        'domain_name' => $domainName,
        'full_domain' => $fullDomain ?? '',
        'registration_date' => $registrationDate,
        'expiration_date' => $expirationDate,
        'years' => $years,
        'status' => $status,
        'auto_renew' => $autoRenew,
        'privacy_protection' => $privacyProtection,
        'nameservers' => json_encode($nameserversArray),
        'registrar' => $registrar !== '' ? $registrar : null,
        'epp_code' => $eppCode !== '' ? $eppCode : null,
        'notes' => $notes !== '' ? $notes : null,
    ];

    if (!$error) {
        try {
            // Garantir UTF-8 na conexão
            db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            db()->exec("SET CHARACTER SET utf8mb4");
            db()->exec("SET character_set_connection=utf8mb4");
            
            if ($id > 0) {
                $stmt = db()->prepare("UPDATE domain_registrations SET client_id=:client_id, tld_id=:tld_id, domain_name=:domain_name, full_domain=:full_domain, registration_date=:registration_date, expiration_date=:expiration_date, years=:years, status=:status, auto_renew=:auto_renew, privacy_protection=:privacy_protection, nameservers=:nameservers, registrar=:registrar, epp_code=:epp_code, notes=:notes WHERE id=:id");
                $data['id'] = $id;
                $stmt->execute($data);
                $_SESSION['success'] = 'Registro de domínio atualizado com sucesso.';
            } else {
                $stmt = db()->prepare("INSERT INTO domain_registrations (client_id, tld_id, domain_name, full_domain, registration_date, expiration_date, years, status, auto_renew, privacy_protection, nameservers, registrar, epp_code, notes) VALUES (:client_id, :tld_id, :domain_name, :full_domain, :registration_date, :expiration_date, :years, :status, :auto_renew, :privacy_protection, :nameservers, :registrar, :epp_code, :notes)");
                $stmt->execute($data);
                $_SESSION['success'] = 'Registro de domínio criado com sucesso.';
            }
            
            header('Location: /admin/domains.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Erro ao salvar registro de domínio: ' . $e->getMessage();
        }
    }
    $item = array_merge($item, $data);
    $item['nameservers'] = $nameservers;
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= $id ? 'Editar Registro de Domínio' : 'Novo Registro de Domínio' ?></h1>
        <a href="/admin/domains.php" class="btn btn-secondary">
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
                        <h5 class="mb-0">Informações do Domínio</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="client_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                                <select class="form-select" id="client_id" name="client_id" required>
                                    <option value="">Selecione um cliente...</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?= (int)$client['id'] ?>" <?= (int)$item['client_id'] === (int)$client['id'] ? 'selected' : '' ?>>
                                            <?= h($client['first_name'] . ' ' . $client['last_name']) ?>
                                            <?php if ($client['company_name']): ?>
                                                - <?= h($client['company_name']) ?>
                                            <?php endif; ?>
                                            (<?= h($client['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="tld_id" class="form-label">TLD <span class="text-danger">*</span></label>
                                <select class="form-select" id="tld_id" name="tld_id" required>
                                    <option value="">Selecione um TLD...</option>
                                    <?php foreach ($tlds as $tld): ?>
                                        <option value="<?= (int)$tld['id'] ?>" <?= (int)$item['tld_id'] === (int)$tld['id'] ? 'selected' : '' ?>>
                                            <?= h($tld['tld']) ?> - <?= h($tld['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="domain_name" class="form-label">Nome do Domínio <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="domain_name" name="domain_name" value="<?= h($item['domain_name']) ?>" placeholder="exemplo" required>
                                    <span class="input-group-text" id="tld_display">.tld</span>
                                </div>
                                <small class="text-muted">Apenas o nome, sem o TLD</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="years" class="form-label">Anos <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="years" name="years" value="<?= (int)$item['years'] ?>" min="1" max="10" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="registration_date" class="form-label">Data de Registro <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="registration_date" name="registration_date" value="<?= h($item['registration_date']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="expiration_date" class="form-label">Data de Expiração <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="expiration_date" name="expiration_date" value="<?= h($item['expiration_date']) ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="nameservers" class="form-label">Nameservers</label>
                            <textarea class="form-control" id="nameservers" name="nameservers" rows="4" placeholder="ns1.exemplo.com.br&#10;ns2.exemplo.com.br"><?= h($item['nameservers']) ?></textarea>
                            <small class="text-muted">Um nameserver por linha</small>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Informações Adicionais</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="registrar" class="form-label">Registrador</label>
                                <input type="text" class="form-control" id="registrar" name="registrar" value="<?= h($item['registrar']) ?>" placeholder="Nome do registrador">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="epp_code" class="form-label">Código EPP</label>
                                <input type="text" class="form-control" id="epp_code" name="epp_code" value="<?= h($item['epp_code']) ?>" placeholder="Código EPP para transferência">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Observações</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?= h($item['notes']) ?></textarea>
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
                                <option value="expired" <?= $item['status'] === 'expired' ? 'selected' : '' ?>>Expirado</option>
                                <option value="suspended" <?= $item['status'] === 'suspended' ? 'selected' : '' ?>>Suspenso</option>
                                <option value="cancelled" <?= $item['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
                                <option value="pending_transfer" <?= $item['status'] === 'pending_transfer' ? 'selected' : '' ?>>Transferência Pendente</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="auto_renew" name="auto_renew" value="1" <?= (int)$item['auto_renew'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="auto_renew">
                                    Renovação Automática
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="privacy_protection" name="privacy_protection" value="1" <?= (int)$item['privacy_protection'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="privacy_protection">
                                    Proteção de Privacidade
                                </label>
                            </div>
                        </div>

                        <?php if ($id > 0): ?>
                            <div class="mb-3">
                                <label class="form-label">Informações do Sistema</label>
                                <div class="small text-muted">
                                    <div><strong>ID:</strong> #<?= (int)$id ?></div>
                                    <div><strong>Domínio Completo:</strong> <?= h($item['full_domain']) ?></div>
                                    <div><strong>Criado em:</strong> <?= date('d/m/Y H:i', strtotime($item['created_at'] ?? 'now')) ?></div>
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
            <a href="/admin/domains.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
document.getElementById('tld_id').addEventListener('change', function() {
    const tldSelect = this;
    const tldDisplay = document.getElementById('tld_display');
    const selectedOption = tldSelect.options[tldSelect.selectedIndex];
    if (selectedOption.value) {
        const tldText = selectedOption.text.split(' - ')[0];
        tldDisplay.textContent = tldText;
    } else {
        tldDisplay.textContent = '.tld';
    }
});

// Atualizar display ao carregar
if (document.getElementById('tld_id').value) {
    document.getElementById('tld_id').dispatchEvent(new Event('change'));
}
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

