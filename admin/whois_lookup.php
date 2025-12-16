<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Pesquisa WHOIS de Domínio';
$active = 'whois_lookup';
require_once __DIR__ . '/partials/layout_start.php';

$domain = trim($_GET['domain'] ?? '');
$whoisData = null;
$error = null;

// Função para fazer pesquisa WHOIS
function performWhoisLookup(string $domain): array {
    $domain = strtolower(trim($domain));
    
    // Remover protocolo se presente
    $domain = preg_replace('#^https?://#', '', $domain);
    $domain = preg_replace('#^www\.#', '', $domain);
    $domain = preg_replace('#/.*$#', '', $domain);
    
    // Validar formato do domínio
    if (!preg_match('/^([a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $domain)) {
        return ['error' => 'Formato de domínio inválido.'];
    }
    
    // Extrair TLD
    $parts = explode('.', $domain);
    $tld = end($parts);
    
    // Tentar usar socket para WHOIS
    $whoisServer = getWhoisServer($tld);
    if (!$whoisServer) {
        return ['error' => 'TLD não suportado ou servidor WHOIS não encontrado.'];
    }
    
    try {
        $whoisResult = queryWhoisServer($whoisServer, $domain);
        
        if (empty($whoisResult)) {
            return ['error' => 'Nenhum dado WHOIS encontrado.'];
        }
        
        return parseWhoisData($whoisResult, $domain);
    } catch (Throwable $e) {
        return ['error' => 'Erro ao consultar WHOIS: ' . $e->getMessage()];
    }
}

// Obter servidor WHOIS baseado no TLD
function getWhoisServer(string $tld): ?string {
    $servers = [
        'com' => 'whois.verisign-grs.com',
        'net' => 'whois.verisign-grs.com',
        'org' => 'whois.pir.org',
        'info' => 'whois.afilias.net',
        'biz' => 'whois.nic.biz',
        'br' => 'whois.registro.br',
        'co' => 'whois.nic.co',
        'uk' => 'whois.nic.uk',
        'de' => 'whois.denic.de',
        'fr' => 'whois.afnic.fr',
        'it' => 'whois.nic.it',
        'es' => 'whois.nic.es',
        'nl' => 'whois.domain-registry.nl',
        'au' => 'whois.aunic.net',
        'ca' => 'whois.cira.ca',
        'mx' => 'whois.mx',
        'jp' => 'whois.jprs.jp',
        'cn' => 'whois.cnnic.net.cn',
        'in' => 'whois.inregistry.net',
        'ru' => 'whois.tcinet.ru',
        'io' => 'whois.nic.io',
        'tv' => 'whois.tv',
        'cc' => 'whois.nic.cc',
        'ws' => 'whois.website.ws',
        'me' => 'whois.nic.me',
        'co.uk' => 'whois.nic.uk',
        'org.uk' => 'whois.nic.uk',
    ];
    
    return $servers[strtolower($tld)] ?? null;
}

// Consultar servidor WHOIS
function queryWhoisServer(string $server, string $domain): string {
    $timeout = 10;
    $port = 43;
    
    $fp = @fsockopen($server, $port, $errno, $errstr, $timeout);
    
    if (!$fp) {
        throw new Exception("Não foi possível conectar ao servidor WHOIS: {$errstr}");
    }
    
    fwrite($fp, $domain . "\r\n");
    
    $response = '';
    while (!feof($fp)) {
        $response .= fgets($fp, 1024);
    }
    
    fclose($fp);
    
    return $response;
}

// Parsear dados WHOIS
function parseWhoisData(string $rawData, string $domain): array {
    $data = [
        'domain' => $domain,
        'raw' => $rawData,
        'parsed' => []
    ];
    
    $lines = explode("\n", $rawData);
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, ':') === false) {
            continue;
        }
        
        list($key, $value) = explode(':', $line, 2);
        $key = trim($key);
        $value = trim($value);
        
        if (empty($value)) {
            continue;
        }
        
        // Normalizar chaves comuns
        $normalizedKey = strtolower($key);
        
        // Mapear campos comuns
        if (preg_match('/domain\s+name/i', $key) || preg_match('/domain:/i', $key)) {
            $data['parsed']['Domain Name'] = $value;
        } elseif (preg_match('/registrar/i', $key)) {
            $data['parsed']['Registrar'] = $value;
        } elseif (preg_match('/creation\s+date|created|registered/i', $key)) {
            $data['parsed']['Creation Date'] = $value;
        } elseif (preg_match('/expiration\s+date|expires|expiry|expires on/i', $key)) {
            $data['parsed']['Expiration Date'] = $value;
        } elseif (preg_match('/updated\s+date|last\s+updated|modified/i', $key)) {
            $data['parsed']['Last Updated'] = $value;
        } elseif (preg_match('/name\s+server|nameserver/i', $key)) {
            if (!isset($data['parsed']['Name Servers'])) {
                $data['parsed']['Name Servers'] = [];
            }
            $data['parsed']['Name Servers'][] = $value;
        } elseif (preg_match('/registrant\s+name|owner|organization/i', $key)) {
            $data['parsed']['Registrant Name'] = $value;
        } elseif (preg_match('/registrant\s+email|admin\s+email|email/i', $key) && !isset($data['parsed']['Registrant Email'])) {
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $data['parsed']['Registrant Email'] = $value;
            }
        } elseif (preg_match('/status/i', $key)) {
            if (!isset($data['parsed']['Status'])) {
                $data['parsed']['Status'] = [];
            }
            $data['parsed']['Status'][] = $value;
        } elseif (preg_match('/dnssec/i', $key)) {
            $data['parsed']['DNSSEC'] = $value;
        }
    }
    
    // Se não encontrou campos específicos, adicionar campos brutos importantes
    if (empty($data['parsed'])) {
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, ':') === false) {
                continue;
            }
            
            list($key, $value) = explode(':', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            if (!empty($value) && strlen($key) < 50) {
                $data['parsed'][$key] = $value;
            }
        }
    }
    
    return $data;
}

// Processar pesquisa
if ($domain !== '') {
    $result = performWhoisLookup($domain);
    
    if (isset($result['error'])) {
        $error = $result['error'];
    } else {
        $whoisData = $result;
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Pesquisa WHOIS de Domínio</h1>
    </div>

    <!-- Formulário de Pesquisa -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="las la-search me-2"></i> Consultar Domínio</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-10">
                    <label for="domain" class="form-label">Domínio</label>
                    <input type="text" 
                           class="form-control form-control-lg" 
                           id="domain" 
                           name="domain" 
                           value="<?= h($domain) ?>" 
                           placeholder="exemplo.com ou www.exemplo.com.br"
                           required>
                    <small class="text-muted">Digite o domínio sem protocolo (http/https) ou www</small>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="las la-search me-1"></i> Pesquisar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Erro -->
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="las la-exclamation-triangle me-2"></i>
            <strong>Erro:</strong> <?= h($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Resultados -->
    <?php if ($whoisData): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="las la-check-circle me-2"></i>
                    Resultados para: <strong><?= h($whoisData['domain']) ?></strong>
                </h5>
                <button type="button" class="btn btn-light btn-sm" onclick="copyWhoisData()">
                    <i class="las la-copy me-1"></i> Copiar Dados
                </button>
            </div>
            <div class="card-body">
                <!-- Dados Parseados -->
                <?php if (!empty($whoisData['parsed'])): ?>
                    <h6 class="mb-3 text-primary">Informações do Domínio</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered table-hover">
                            <tbody>
                                <?php foreach ($whoisData['parsed'] as $key => $value): ?>
                                    <tr>
                                        <td class="fw-bold" style="width: 200px; background-color: #f8f9fa;">
                                            <?= h($key) ?>
                                        </td>
                                        <td>
                                            <?php if (is_array($value)): ?>
                                                <ul class="mb-0">
                                                    <?php foreach ($value as $item): ?>
                                                        <li><?= h($item) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <?= h($value) ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- Dados Brutos -->
                <div class="mt-4">
                    <h6 class="mb-3 text-secondary">Dados WHOIS Completos (Raw)</h6>
                    <div class="bg-light p-3 rounded" style="max-height: 500px; overflow-y: auto;">
                        <pre id="whoisRawData" class="mb-0" style="font-size: 11px; white-space: pre-wrap; word-wrap: break-word;"><?= h($whoisData['raw']) ?></pre>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informações Adicionais -->
        <div class="row">
            <div class="col-md-6">
                <div class="card shadow-sm border-info">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="las la-info-circle me-2"></i> Sobre WHOIS</h6>
                    </div>
                    <div class="card-body">
                        <p class="small mb-0">
                            WHOIS é um protocolo de consulta e resposta usado para consultar bancos de dados 
                            que armazenam informações sobre recursos de Internet, como nomes de domínio e endereços IP.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="las la-exclamation-triangle me-2"></i> Limitações</h6>
                    </div>
                    <div class="card-body">
                        <p class="small mb-0">
                            Alguns TLDs podem ter servidores WHOIS diferentes ou restrições de acesso. 
                            Dados podem variar dependendo do registro do domínio.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Instruções -->
    <?php if (!$whoisData && !$error && $domain === ''): ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="mb-3">Como usar a Pesquisa WHOIS</h5>
                <ol>
                    <li class="mb-2">Digite o domínio que deseja consultar no campo acima (ex: exemplo.com)</li>
                    <li class="mb-2">Clique em "Pesquisar" para iniciar a consulta</li>
                    <li class="mb-2">Os resultados mostrarão informações como:
                        <ul class="mt-2">
                            <li>Data de criação do domínio</li>
                            <li>Data de expiração</li>
                            <li>Registrador</li>
                            <li>Servidores de nome (DNS)</li>
                            <li>Status do domínio</li>
                            <li>Informações do registrante (quando disponíveis)</li>
                        </ul>
                    </li>
                </ol>
                <div class="alert alert-info mt-3 mb-0">
                    <i class="las la-lightbulb me-2"></i>
                    <strong>Dica:</strong> Você pode pesquisar domínios com ou sem "www" e o sistema irá processar automaticamente.
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function copyWhoisData() {
    const rawData = document.getElementById('whoisRawData');
    if (rawData) {
        const text = rawData.textContent || rawData.innerText;
        navigator.clipboard.writeText(text).then(function() {
            alert('Dados WHOIS copiados para a área de transferência!');
        }, function(err) {
            console.error('Erro ao copiar:', err);
            alert('Erro ao copiar dados. Tente selecionar e copiar manualmente.');
        });
    }
}
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

