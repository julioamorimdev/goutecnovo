<?php
declare(strict_types=1);
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../app/bootstrap.php';

$adminId = $_SESSION['admin_user_id'] ?? null;

// Garantir tabela de preferências
function ensure_dashboard_preferences_table(): void {
    try {
        db()->query("SELECT 1 FROM dashboard_preferences LIMIT 1");
    } catch (Throwable $e) {
        try {
            db()->exec("
                CREATE TABLE IF NOT EXISTS dashboard_preferences (
                  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                  admin_id BIGINT UNSIGNED NOT NULL,
                  widget_key VARCHAR(100) NOT NULL,
                  widget_type VARCHAR(50) NOT NULL,
                  position INT NOT NULL DEFAULT 0,
                  is_visible TINYINT(1) NOT NULL DEFAULT 1,
                  config JSON NULL,
                  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (id),
                  UNIQUE KEY uk_admin_widget (admin_id, widget_key),
                  KEY idx_admin_position (admin_id, position),
                  CONSTRAINT fk_dashboard_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Throwable $ignored) {}
    }
}
ensure_dashboard_preferences_table();

// Processar ações AJAX - DEVE SER ANTES DE INCLUIR O LAYOUT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Não incluir layout para requisições AJAX
    try {
        csrf_verify($_POST['_csrf'] ?? null);
        $action = $_POST['action'] ?? '';
        
        if ($action === 'save_widgets') {
            $widgetsJson = $_POST['widgets'] ?? '[]';
            $widgets = json_decode($widgetsJson, true);
            
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($widgets)) {
                throw new Exception('JSON inválido');
            }
            
            db()->beginTransaction();
            try {
                $stmt = db()->prepare("UPDATE dashboard_preferences SET is_visible = 0 WHERE admin_id = ?");
                $stmt->execute([$adminId]);
                
                foreach ($widgets as $index => $widget) {
                    if (!is_array($widget)) continue;
                    
                    $widgetKey = isset($widget['key']) ? trim((string)$widget['key']) : '';
                    $widgetType = isset($widget['type']) ? trim((string)$widget['type']) : 'stat';
                    $isVisible = 0;
                    
                    if (isset($widget['visible'])) {
                        $vis = $widget['visible'];
                        if ($vis === true || $vis === 1 || $vis === '1' || $vis === 'true') {
                            $isVisible = 1;
                        }
                    }
                    
                    // Validar widget_key - mais permissivo
                    if (empty($widgetKey)) {
                        error_log("Widget key vazio ignorado no índice $index");
                        continue;
                    }
                    
                    // Validar formato do widget_key (apenas alfanumérico, underscore e hífen)
                    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $widgetKey)) {
                        error_log("Widget key inválido ignorado: " . substr($widgetKey, 0, 50));
                        continue;
                    }
                    
                    // Validar e limitar widget_type
                    if (!in_array($widgetType, ['stat', 'chart', 'list'])) {
                        $widgetType = 'stat';
                    }
                    
                    // Validar position
                    $position = is_numeric($index) ? max(0, (int)$index) : 0;
                    
                    try {
                        $stmt = db()->prepare("INSERT INTO dashboard_preferences (admin_id, widget_key, widget_type, position, is_visible) 
                                              VALUES (?, ?, ?, ?, ?)
                                              ON DUPLICATE KEY UPDATE widget_type = VALUES(widget_type), position = VALUES(position), is_visible = VALUES(is_visible)");
                        $stmt->execute([$adminId, $widgetKey, $widgetType, $position, $isVisible]);
                    } catch (PDOException $e) {
                        error_log("Erro ao salvar widget $widgetKey: " . $e->getMessage());
                        // Continuar com próximo widget
                        continue;
                    }
                }
                
                db()->commit();
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                echo json_encode(['success' => true]);
                exit;
            } catch (Throwable $e) {
                db()->rollBack();
                throw $e;
            }
        } elseif ($action === 'toggle_widget') {
            $widgetKey = trim($_POST['widget_key'] ?? '');
            $isVisible = isset($_POST['is_visible']) && ($_POST['is_visible'] === '1' || $_POST['is_visible'] === 1) ? 1 : 0;
            
            if (empty($widgetKey) || !preg_match('/^[a-zA-Z0-9_-]+$/', $widgetKey)) {
                throw new Exception('Widget key inválido');
            }
            
            $stmt = db()->prepare("SELECT widget_type FROM dashboard_preferences WHERE admin_id = ? AND widget_key = ?");
            $stmt->execute([$adminId, $widgetKey]);
            $existing = $stmt->fetch();
            $widgetType = $existing ? $existing['widget_type'] : 'stat';
            
            $stmt = db()->prepare("INSERT INTO dashboard_preferences (admin_id, widget_key, widget_type, position, is_visible) 
                                  VALUES (?, ?, ?, ?, ?)
                                  ON DUPLICATE KEY UPDATE is_visible = VALUES(is_visible)");
            $stmt->execute([$adminId, $widgetKey, $widgetType, 0, $isVisible]);
            
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['success' => true]);
            exit;
        }
    } catch (Throwable $e) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Incluir layout apenas se não for requisição AJAX
$page_title = 'Dashboard';
$active = 'dashboard';
require_once __DIR__ . '/partials/layout_start.php';

// Processar ações de atalhos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shortcut_action'])) {
    csrf_verify($_POST['_csrf'] ?? null);
    $action = $_POST['shortcut_action'] ?? '';
    $id = isset($_POST['shortcut_id']) ? (int)$_POST['shortcut_id'] : 0;
    
    if ($action === 'add') {
        $label = trim((string)($_POST['label'] ?? ''));
        $url = trim((string)($_POST['url'] ?? ''));
        $icon_class = trim((string)($_POST['icon_class'] ?? ''));
        if ($label && $url) {
            $maxSort = (int)db()->query("SELECT COALESCE(MAX(sort_order), 0) AS m FROM dashboard_shortcuts")->fetch()['m'];
            db()->prepare("INSERT INTO dashboard_shortcuts (label, url, icon_class, sort_order) VALUES (?, ?, ?, ?)")
                ->execute([$label, $url, $icon_class, $maxSort + 10]);
        }
        header('Location: /admin/dashboard.php');
        exit;
    }
    if ($action === 'delete' && $id > 0) {
        db()->prepare("DELETE FROM dashboard_shortcuts WHERE id=?")->execute([$id]);
        header('Location: /admin/dashboard.php');
        exit;
    }
    if ($action === 'toggle' && $id > 0) {
        db()->prepare("UPDATE dashboard_shortcuts SET is_enabled = IF(is_enabled=1,0,1) WHERE id=?")->execute([$id]);
        header('Location: /admin/dashboard.php');
        exit;
    }
}

// Buscar preferências
$preferences = [];
try {
    $stmt = db()->prepare("SELECT widget_key, widget_type, position, is_visible FROM dashboard_preferences WHERE admin_id = ? ORDER BY position");
    $stmt->execute([$adminId]);
    foreach ($stmt->fetchAll() as $pref) {
        $preferences[$pref['widget_key']] = $pref;
    }
} catch (Throwable $e) {
    $preferences = [];
}

// Estatísticas
$counts = [
    'clients_total' => (int)db()->query("SELECT COUNT(*) AS c FROM clients")->fetch()['c'],
    'clients_active' => (int)db()->query("SELECT COUNT(*) AS c FROM clients WHERE status='active'")->fetch()['c'],
    'orders_total' => (int)db()->query("SELECT COUNT(*) AS c FROM orders")->fetch()['c'],
    'orders_pending' => (int)db()->query("SELECT COUNT(*) AS c FROM orders WHERE status='pending'")->fetch()['c'],
    'orders_active' => (int)db()->query("SELECT COUNT(*) AS c FROM orders WHERE status='active'")->fetch()['c'],
    'invoices_total' => (int)db()->query("SELECT COUNT(*) AS c FROM invoices")->fetch()['c'],
    'invoices_unpaid' => (int)db()->query("SELECT COUNT(*) AS c FROM invoices WHERE status='unpaid'")->fetch()['c'],
    'invoices_paid' => (int)db()->query("SELECT COUNT(*) AS c FROM invoices WHERE status='paid'")->fetch()['c'],
    'invoices_total_unpaid' => (float)db()->query("SELECT COALESCE(SUM(total), 0) AS total FROM invoices WHERE status='unpaid'")->fetch()['total'],
    'invoices_total_paid' => (float)db()->query("SELECT COALESCE(SUM(total), 0) AS total FROM invoices WHERE status='paid'")->fetch()['total'],
];

try {
    $counts['tickets_total'] = (int)db()->query("SELECT COUNT(*) AS c FROM tickets")->fetch()['c'];
    $counts['tickets_open'] = (int)db()->query("SELECT COUNT(*) AS c FROM tickets WHERE status='open'")->fetch()['c'];
    $counts['tickets_answered'] = (int)db()->query("SELECT COUNT(*) AS c FROM tickets WHERE status='answered'")->fetch()['c'];
    $counts['tickets_closed'] = (int)db()->query("SELECT COUNT(*) AS c FROM tickets WHERE status='closed'")->fetch()['c'];
} catch (Throwable $e) {
    $counts['tickets_total'] = $counts['tickets_open'] = $counts['tickets_answered'] = $counts['tickets_closed'] = 0;
}

try {
    $counts['plans_total'] = (int)db()->query("SELECT COUNT(*) AS c FROM plans")->fetch()['c'];
    $counts['plans_active'] = (int)db()->query("SELECT COUNT(*) AS c FROM plans WHERE is_active=1")->fetch()['c'];
    $counts['packages_total'] = (int)db()->query("SELECT COUNT(*) AS c FROM product_packages")->fetch()['c'];
    $counts['promotions_total'] = (int)db()->query("SELECT COUNT(*) AS c FROM promotions")->fetch()['c'];
} catch (Throwable $e) {
    $counts['plans_total'] = $counts['plans_active'] = $counts['packages_total'] = $counts['promotions_total'] = 0;
}

// Dados para gráficos
$chartData = ['orders' => [], 'revenue' => [], 'tickets' => [], 'dates' => []];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartData['dates'][] = date('d/m', strtotime("-$i days"));
    try {
        $stmt = db()->prepare("SELECT COUNT(*) AS c FROM orders WHERE DATE(created_at) = ?");
        $stmt->execute([$date]);
        $chartData['orders'][] = (int)$stmt->fetch()['c'];
        $stmt = db()->prepare("SELECT COALESCE(SUM(total), 0) AS total FROM invoices WHERE DATE(created_at) = ? AND status='paid'");
        $stmt->execute([$date]);
        $chartData['revenue'][] = (float)$stmt->fetch()['total'];
        $stmt = db()->prepare("SELECT COUNT(*) AS c FROM tickets WHERE DATE(created_at) = ?");
        $stmt->execute([$date]);
        $chartData['tickets'][] = (int)$stmt->fetch()['c'];
    } catch (Throwable $e) {
        $chartData['orders'][] = 0;
        $chartData['revenue'][] = 0;
        $chartData['tickets'][] = 0;
    }
}

$ordersByStatus = [];
$invoicesByStatus = [];
try {
    $stmt = db()->query("SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status");
    $ordersByStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $stmt = db()->query("SELECT status, COUNT(*) AS cnt FROM invoices GROUP BY status");
    $invoicesByStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Throwable $e) {}

$shortcuts = db()->query("SELECT * FROM dashboard_shortcuts ORDER BY sort_order ASC, id ASC")->fetchAll();

// Widgets disponíveis
$availableWidgets = [
    'clients' => ['label' => 'Clientes', 'icon' => 'las la-users', 'color' => 'success', 'type' => 'stat'],
    'orders' => ['label' => 'Pedidos', 'icon' => 'las la-shopping-cart', 'color' => 'info', 'type' => 'stat'],
    'invoices' => ['label' => 'Faturas', 'icon' => 'las la-file-invoice', 'color' => 'warning', 'type' => 'stat'],
    'tickets' => ['label' => 'Tickets', 'icon' => 'las la-headset', 'color' => 'primary', 'type' => 'stat'],
    'financial' => ['label' => 'Resumo Financeiro', 'icon' => 'las la-dollar-sign', 'color' => 'success', 'type' => 'stat'],
    'products' => ['label' => 'Produtos', 'icon' => 'las la-box', 'color' => 'info', 'type' => 'stat'],
    'chart_orders' => ['label' => 'Gráfico de Pedidos', 'icon' => 'las la-chart-line', 'color' => 'info', 'type' => 'chart'],
    'chart_revenue' => ['label' => 'Gráfico de Receita', 'icon' => 'las la-chart-area', 'color' => 'success', 'type' => 'chart'],
    'chart_tickets' => ['label' => 'Gráfico de Tickets', 'icon' => 'las la-chart-bar', 'color' => 'primary', 'type' => 'chart'],
    'chart_status' => ['label' => 'Status de Pedidos/Faturas', 'icon' => 'las la-pie-chart', 'color' => 'warning', 'type' => 'chart'],
];

$defaultOrder = ['clients', 'orders', 'invoices', 'tickets', 'financial', 'products', 'chart_orders', 'chart_revenue', 'chart_tickets', 'chart_status'];
$widgetOrder = $defaultOrder;

if (!empty($preferences)) {
    $ordered = [];
    $unordered = [];
    foreach ($defaultOrder as $key) {
        if (isset($preferences[$key])) {
            $ordered[$preferences[$key]['position']] = $key;
        } else {
            $unordered[] = $key;
        }
    }
    ksort($ordered);
    $widgetOrder = array_merge(array_values($ordered), $unordered);
}

function isWidgetVisible($key, $preferences, $default = true) {
    return isset($preferences[$key]) ? (bool)$preferences[$key]['is_visible'] : $default;
}
?>

<style>
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 1.5rem;
}
.dashboard-widget {
    grid-column: span 3;
}
.dashboard-widget--wide {
    grid-column: span 9;
}
@media (max-width: 1400px) {
    .dashboard-widget--wide { grid-column: span 8; }
}
@media (max-width: 1200px) {
    .dashboard-widget { grid-column: span 6; }
    .dashboard-widget--wide { grid-column: span 12; }
}
@media (max-width: 768px) {
    .dashboard-widget, .dashboard-widget--wide { grid-column: span 12; }
}
.dashboard-widget.dragging {
    opacity: 0.5;
}
.widget-handle {
    cursor: move;
    color: rgba(0,0,0,0.4);
}
.widget-handle:hover {
    color: rgba(0,0,0,0.6);
}
.chart-container {
    position: relative;
    height: 400px;
    padding: 15px;
    width: 100%;
}
.chart-container--small {
    height: 350px;
    padding: 15px;
    width: 100%;
}
.chart-container canvas,
.chart-container--small canvas {
    width: 100% !important;
    height: 100% !important;
    display: block;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Dashboard</h1>
    <div>
        <a class="btn btn-outline-dark me-2" href="/admin/reports.php">
            <i class="las la-chart-bar me-1"></i> Relatórios
        </a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#widgetModal">
            <i class="las la-cog me-1"></i> Personalizar
        </button>
    </div>
</div>

<div id="dashboardWidgets" class="dashboard-grid">
    <?php foreach ($widgetOrder as $widgetKey): ?>
        <?php if (!isset($availableWidgets[$widgetKey]) || !isWidgetVisible($widgetKey, $preferences)) continue; ?>
        <?php $widget = $availableWidgets[$widgetKey]; ?>
        <div class="dashboard-widget <?= $widget['type'] === 'chart' ? 'dashboard-widget--wide' : '' ?>" 
             draggable="true" 
             data-widget-key="<?= h($widgetKey) ?>" 
             data-widget-type="<?= h($widget['type']) ?>">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-<?= $widget['color'] ?> text-white d-flex justify-content-between align-items-center">
                    <div>
                        <i class="<?= $widget['icon'] ?> me-2"></i>
                        <?= h($widget['label']) ?>
                    </div>
                    <div class="widget-handle">
                        <i class="las la-grip-vertical"></i>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($widgetKey === 'clients'): ?>
                        <div class="h3 mb-1"><?= $counts['clients_total'] ?></div>
                        <div class="text-muted small">Ativos: <?= $counts['clients_active'] ?></div>
                        <a class="btn btn-sm btn-success w-100 mt-3" href="/admin/clients.php">Gerenciar</a>
                    <?php elseif ($widgetKey === 'orders'): ?>
                        <div class="h3 mb-1"><?= $counts['orders_total'] ?></div>
                        <div class="text-muted small">Pendentes: <?= $counts['orders_pending'] ?> | Ativos: <?= $counts['orders_active'] ?></div>
                        <a class="btn btn-sm btn-info w-100 mt-3" href="/admin/orders.php">Gerenciar</a>
                    <?php elseif ($widgetKey === 'invoices'): ?>
                        <div class="h3 mb-1"><?= $counts['invoices_total'] ?></div>
                        <div class="text-muted small">Não pagas: <?= $counts['invoices_unpaid'] ?> | Pagas: <?= $counts['invoices_paid'] ?></div>
                        <a class="btn btn-sm btn-warning w-100 mt-3" href="/admin/invoices.php">Gerenciar</a>
                    <?php elseif ($widgetKey === 'tickets'): ?>
                        <div class="h3 mb-1"><?= $counts['tickets_total'] ?></div>
                        <div class="text-muted small">Abertos: <?= $counts['tickets_open'] ?> | Respondidos: <?= $counts['tickets_answered'] ?> | Fechados: <?= $counts['tickets_closed'] ?></div>
                        <a class="btn btn-sm btn-primary w-100 mt-3" href="/admin/tickets.php">Gerenciar</a>
                    <?php elseif ($widgetKey === 'financial'): ?>
                        <div class="mb-3">
                            <div class="text-muted small">Total Não Pago</div>
                            <div class="h4 text-danger">R$ <?= number_format($counts['invoices_total_unpaid'], 2, ',', '.') ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="text-muted small">Total Pago</div>
                            <div class="h4 text-success">R$ <?= number_format($counts['invoices_total_paid'], 2, ',', '.') ?></div>
                        </div>
                        <a class="btn btn-sm btn-success w-100" href="/admin/reports_revenue.php">Ver Relatórios</a>
                    <?php elseif ($widgetKey === 'products'): ?>
                        <div class="h3 mb-1"><?= $counts['plans_total'] ?></div>
                        <div class="text-muted small">Ativos: <?= $counts['plans_active'] ?> | Pacotes: <?= $counts['packages_total'] ?> | Promoções: <?= $counts['promotions_total'] ?></div>
                        <a class="btn btn-sm btn-info w-100 mt-3" href="/admin/plans.php">Gerenciar</a>
                    <?php elseif ($widgetKey === 'chart_orders'): ?>
                        <div class="chart-container"><canvas id="chartOrders"></canvas></div>
                    <?php elseif ($widgetKey === 'chart_revenue'): ?>
                        <div class="chart-container"><canvas id="chartRevenue"></canvas></div>
                    <?php elseif ($widgetKey === 'chart_tickets'): ?>
                        <div class="chart-container"><canvas id="chartTickets"></canvas></div>
                    <?php elseif ($widgetKey === 'chart_status'): ?>
                        <div class="row">
                            <div class="col-6">
                                <div class="chart-container chart-container--small"><canvas id="chartOrdersStatus"></canvas></div>
                            </div>
                            <div class="col-6">
                                <div class="chart-container chart-container--small"><canvas id="chartInvoicesStatus"></canvas></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal Personalizar -->
<div class="modal fade" id="widgetModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Personalizar Dashboard</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Arraste para reordenar. Marque/desmarque para mostrar/ocultar.</p>
                <div id="widgetList" class="list-group">
                    <?php foreach ($widgetOrder as $widgetKey): ?>
                        <?php if (!isset($availableWidgets[$widgetKey])) continue; ?>
                        <?php $widget = $availableWidgets[$widgetKey]; ?>
                        <div class="list-group-item d-flex align-items-center justify-content-between" 
                             draggable="true" 
                             data-widget-key="<?= h($widgetKey) ?>">
                            <div class="d-flex align-items-center gap-3">
                                <i class="las la-grip-vertical text-muted" style="cursor: move;"></i>
                                <i class="<?= $widget['icon'] ?> text-<?= $widget['color'] ?>"></i>
                                <span><?= h($widget['label']) ?></span>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input widget-toggle" type="checkbox" 
                                       data-widget-key="<?= h($widgetKey) ?>"
                                       <?= isWidgetVisible($widgetKey, $preferences) ? 'checked' : '' ?>>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" id="saveWidgetsBtn">Salvar Alterações</button>
            </div>
        </div>
    </div>
</div>

<!-- Atalhos -->
<div class="card shadow-sm mt-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Atalhos Rápidos</h5>
            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#shortcutsManager">
                <i class="las la-cog me-1"></i>Gerenciar
            </button>
        </div>
        <div class="d-grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">
            <?php foreach ($shortcuts as $sc): ?>
                <?php if ((int)$sc['is_enabled'] === 1): ?>
                    <a class="btn btn-outline-dark" href="<?= h($sc['url']) ?>">
                        <?php if ($sc['icon_class']): ?>
                            <i class="<?= h($sc['icon_class']) ?> me-1"></i>
                        <?php endif; ?>
                        <?= h($sc['label']) ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <div class="collapse mt-4" id="shortcutsManager">
            <hr>
            <h6 class="mb-3">Gerenciar atalhos</h6>
            <?php foreach ($shortcuts as $sc): ?>
                <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                    <div>
                        <?php if ($sc['icon_class']): ?>
                            <i class="<?= h($sc['icon_class']) ?> me-1"></i>
                        <?php endif; ?>
                        <strong><?= h($sc['label']) ?></strong>
                        <span class="text-muted small ms-2"><?= h($sc['url']) ?></span>
                        <?php if ((int)$sc['is_enabled'] === 0): ?>
                            <span class="badge bg-secondary ms-2">Desativado</span>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-1">
                        <form method="post" class="m-0">
                            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="shortcut_id" value="<?= (int)$sc['id'] ?>">
                            <button class="btn btn-sm btn-outline-secondary" name="shortcut_action" value="toggle" type="submit">
                                <i class="las <?= (int)$sc['is_enabled'] === 1 ? 'la-eye-slash' : 'la-eye' ?>"></i>
                            </button>
                        </form>
                        <form method="post" class="m-0" onsubmit="return confirm('Excluir?')">
                            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="shortcut_id" value="<?= (int)$sc['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" name="shortcut_action" value="delete" type="submit">
                                <i class="las la-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            <form method="post" class="border-top pt-3">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="shortcut_action" value="add">
                <div class="mb-2">
                    <label class="form-label small">Título</label>
                    <input class="form-control form-control-sm" name="label" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small">URL</label>
                    <input class="form-control form-control-sm" name="url" placeholder="/admin/menu.php" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Ícone (classe Line Awesome)</label>
                    <input class="form-control form-control-sm" name="icon_class" placeholder="las la-plus">
                </div>
                <button class="btn btn-sm btn-primary w-100" type="submit">
                    <i class="las la-plus me-1"></i>Adicionar atalho
                </button>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';
    
    const chartData = {
        dates: <?= json_encode($chartData['dates']) ?>,
        orders: <?= json_encode($chartData['orders']) ?>,
        revenue: <?= json_encode($chartData['revenue']) ?>,
        tickets: <?= json_encode($chartData['tickets']) ?>,
        ordersStatus: <?= json_encode($ordersByStatus) ?>,
        invoicesStatus: <?= json_encode($invoicesByStatus) ?>
    };
    
    const csrfToken = '<?= h(csrf_token()) ?>';
    
    // Funções de gráficos
    function setupCanvas(canvas) {
        if (!canvas) {
            console.warn('Canvas é null');
            return null;
        }
        const container = canvas.parentElement;
        if (!container) {
            console.warn('Container do canvas não encontrado');
            return null;
        }
        
        // Aguardar um frame para garantir que o layout foi calculado
        const dpr = window.devicePixelRatio || 1;
        const containerRect = container.getBoundingClientRect();
        
        // Usar o tamanho completo do container menos o padding
        const padding = 30; // 15px cada lado
        let width = Math.max(200, containerRect.width - padding);
        let height = Math.max(200, containerRect.height - padding);
        
        // Se o container ainda não tem tamanho, usar valores padrão maiores
        if (width < 200 || height < 200) {
            width = container.classList.contains('chart-container--small') ? 300 : 500;
            height = container.classList.contains('chart-container--small') ? 300 : 400;
        }
        
        // Forçar tamanho do canvas via CSS
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';
        canvas.style.display = 'block';
        canvas.style.maxWidth = '100%';
        canvas.style.maxHeight = '100%';
        
        // Tamanho real do canvas (para alta resolução)
        const realWidth = Math.floor(width * dpr);
        const realHeight = Math.floor(height * dpr);
        canvas.width = realWidth;
        canvas.height = realHeight;
        
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            console.error('Não foi possível obter contexto 2d');
            return null;
        }
        
        ctx.scale(dpr, dpr);
        
        console.log(`Canvas ${canvas.id}: Container=${containerRect.width}x${containerRect.height}px, Canvas CSS=${width}x${height}px, Real=${realWidth}x${realHeight}px (dpr: ${dpr})`);
        return {ctx: ctx, width: width, height: height};
    }
    
    function drawLineChart(canvasId, labels, data, color, title) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            console.warn('Canvas não encontrado:', canvasId);
            return;
        }
        const setup = setupCanvas(canvas);
        if (!setup) return;
        
        const ctx = setup.ctx;
        const w = setup.width;
        const h = setup.height;
        
        ctx.clearRect(0, 0, w, h);
        
        if (!data || data.length === 0) {
            ctx.fillStyle = '#999';
            ctx.font = '14px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('Sem dados', w / 2, h / 2);
            return;
        }
        
        const max = Math.max(...data, 1);
        const leftPad = 50;
        const rightPad = 20;
        const topPad = 40;
        const bottomPad = 50;
        const plotW = w - leftPad - rightPad;
        const plotH = h - topPad - bottomPad;
        
        // Título
        if (title) {
            ctx.fillStyle = '#333';
            ctx.font = 'bold 14px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(title, w / 2, 20);
        }
        
        // Grid horizontal
        ctx.strokeStyle = 'rgba(0,0,0,0.08)';
        ctx.lineWidth = 1;
        for (let i = 0; i <= 5; i++) {
            const y = topPad + (plotH * i / 5);
            ctx.beginPath();
            ctx.moveTo(leftPad, y);
            ctx.lineTo(leftPad + plotW, y);
            ctx.stroke();
        }
        
        // Grid vertical (linhas pontilhadas)
        ctx.setLineDash([2, 2]);
        ctx.strokeStyle = 'rgba(0,0,0,0.05)';
        if (labels && labels.length > 0) {
            for (let i = 0; i < labels.length; i++) {
                const x = leftPad + (plotW * i / (labels.length - 1));
                ctx.beginPath();
                ctx.moveTo(x, topPad);
                ctx.lineTo(x, topPad + plotH);
                ctx.stroke();
            }
        }
        ctx.setLineDash([]);
        
        // Eixo Y - valores
        ctx.fillStyle = '#666';
        ctx.font = '11px sans-serif';
        ctx.textAlign = 'right';
        for (let i = 0; i <= 5; i++) {
            const value = Math.round((max * (5 - i)) / 5);
            const y = topPad + (plotH * i / 5);
            ctx.fillText(value.toString(), leftPad - 10, y + 4);
        }
        
        // Linha do gráfico
        ctx.strokeStyle = color;
        ctx.lineWidth = 3;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.beginPath();
        data.forEach((v, i) => {
            const x = leftPad + (plotW * (data.length === 1 ? 0 : i / (data.length - 1)));
            const y = topPad + plotH - (v / max) * plotH;
            if (i === 0) ctx.moveTo(x, y);
            else ctx.lineTo(x, y);
        });
        ctx.stroke();
        
        // Preenchimento
        ctx.fillStyle = color.replace('rgb', 'rgba').replace(')', ', 0.15)');
        ctx.lineTo(leftPad + plotW, topPad + plotH);
        ctx.lineTo(leftPad, topPad + plotH);
        ctx.closePath();
        ctx.fill();
        
        // Pontos
        ctx.fillStyle = color;
        data.forEach((v, i) => {
            const x = leftPad + (plotW * (data.length === 1 ? 0 : i / (data.length - 1)));
            const y = topPad + plotH - (v / max) * plotH;
            ctx.beginPath();
            ctx.arc(x, y, 4, 0, Math.PI * 2);
            ctx.fill();
            ctx.fillStyle = '#fff';
            ctx.beginPath();
            ctx.arc(x, y, 2, 0, Math.PI * 2);
            ctx.fill();
            ctx.fillStyle = color;
        });
        
        // Labels do eixo X
        if (labels && labels.length > 0) {
            ctx.fillStyle = '#666';
            ctx.font = '11px sans-serif';
            ctx.textAlign = 'center';
            labels.forEach((label, i) => {
                const x = leftPad + (plotW * i / (labels.length - 1));
                ctx.fillText(label, x, h - bottomPad + 20);
            });
        }
    }
    
    function drawBarChart(canvasId, labels, data, color, title) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            console.warn('Canvas não encontrado:', canvasId);
            return;
        }
        const setup = setupCanvas(canvas);
        if (!setup) return;
        
        const ctx = setup.ctx;
        const w = setup.width;
        const h = setup.height;
        
        ctx.clearRect(0, 0, w, h);
        
        if (!data || data.length === 0) {
            ctx.fillStyle = '#999';
            ctx.font = '14px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('Sem dados', w / 2, h / 2);
            return;
        }
        
        const max = Math.max(...data, 1);
        const leftPad = 50;
        const rightPad = 20;
        const topPad = 40;
        const bottomPad = 50;
        const plotW = w - leftPad - rightPad;
        const plotH = h - topPad - bottomPad;
        const barW = plotW / data.length;
        
        // Título
        if (title) {
            ctx.fillStyle = '#333';
            ctx.font = 'bold 14px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(title, w / 2, 20);
        }
        
        // Grid horizontal
        ctx.strokeStyle = 'rgba(0,0,0,0.08)';
        ctx.lineWidth = 1;
        for (let i = 0; i <= 5; i++) {
            const y = topPad + (plotH * i / 5);
            ctx.beginPath();
            ctx.moveTo(leftPad, y);
            ctx.lineTo(leftPad + plotW, y);
            ctx.stroke();
        }
        
        // Eixo Y - valores (formatação para receita)
        ctx.fillStyle = '#666';
        ctx.font = '11px sans-serif';
        ctx.textAlign = 'right';
        for (let i = 0; i <= 5; i++) {
            let value = (max * (5 - i)) / 5;
            let formattedValue = value >= 1000 ? (value / 1000).toFixed(1) + 'k' : value.toFixed(0);
            const y = topPad + (plotH * i / 5);
            ctx.fillText(formattedValue, leftPad - 10, y + 4);
        }
        
        // Barras
        const gradient = ctx.createLinearGradient(0, topPad, 0, topPad + plotH);
        gradient.addColorStop(0, color.replace('rgba', 'rgba').replace('0.8', '1'));
        gradient.addColorStop(1, color);
        
        ctx.fillStyle = gradient;
        data.forEach((v, i) => {
            const bh = (v / max) * plotH;
            const x = leftPad + i * barW + barW * 0.15;
            const y = topPad + (plotH - bh);
            const bw = barW * 0.7;
            
            // Sombra
            ctx.fillStyle = 'rgba(0,0,0,0.1)';
            ctx.fillRect(x + 2, y + 2, bw, bh);
            
            // Barra
            ctx.fillStyle = gradient;
            ctx.fillRect(x, y, bw, bh);
            
            // Borda
            ctx.strokeStyle = color.replace('rgba', 'rgba').replace('0.8', '1');
            ctx.lineWidth = 1;
            ctx.strokeRect(x, y, bw, bh);
            
            // Valor no topo da barra
            if (v > 0) {
                ctx.fillStyle = '#333';
                ctx.font = '10px sans-serif';
                ctx.textAlign = 'center';
                const formattedValue = v >= 1000 ? (v / 1000).toFixed(1) + 'k' : v.toFixed(0);
                ctx.fillText(formattedValue, x + bw / 2, y - 5);
            }
        });
        
        // Labels do eixo X
        if (labels && labels.length > 0) {
            ctx.fillStyle = '#666';
            ctx.font = '11px sans-serif';
            ctx.textAlign = 'center';
            labels.forEach((label, i) => {
                const x = leftPad + i * barW + barW / 2;
                ctx.fillText(label, x, h - bottomPad + 20);
            });
        }
    }
    
    function drawDonutChart(canvasId, dataObj, colors, title) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            console.warn('Canvas não encontrado:', canvasId);
            return;
        }
        const setup = setupCanvas(canvas);
        if (!setup) return;
        
        const ctx = setup.ctx;
        const w = setup.width;
        const h = setup.height;
        
        ctx.clearRect(0, 0, w, h);
        
        const labels = Object.keys(dataObj || {});
        const values = Object.values(dataObj || {}).map(v => Number(v) || 0);
        const total = values.reduce((a, b) => a + b, 0);
        
        if (total === 0) {
            ctx.fillStyle = '#999';
            ctx.font = '14px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('Sem dados', w / 2, h / 2);
            return;
        }
        
        const cx = w / 2;
        const cy = h / 2 - 10;
        const r = Math.min(w, h) * 0.32;
        const ir = r * 0.55;
        let start = -Math.PI / 2;
        
        // Título
        if (title) {
            ctx.fillStyle = '#333';
            ctx.font = 'bold 13px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(title, cx, 18);
        }
        
        // Desenhar fatias
        values.forEach((v, i) => {
            const ang = (v / total) * Math.PI * 2;
            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.fillStyle = colors[i % colors.length];
            ctx.arc(cx, cy, r, start, start + ang);
            ctx.closePath();
            ctx.fill();
            
            // Borda da fatia
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 2;
            ctx.stroke();
            
            start += ang;
        });
        
        // Corte interno (donut)
        ctx.globalCompositeOperation = 'destination-out';
        ctx.beginPath();
        ctx.arc(cx, cy, ir, 0, Math.PI * 2);
        ctx.fill();
        ctx.globalCompositeOperation = 'source-over';
        
        // Total no centro
        ctx.fillStyle = '#333';
        ctx.font = 'bold 16px sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(total.toString(), cx, cy - 5);
        ctx.fillStyle = '#666';
        ctx.font = '11px sans-serif';
        ctx.fillText('Total', cx, cy + 12);
        
        // Legenda
        const legendY = cy + r + 30;
        const legendX = cx - (labels.length * 60) / 2;
        labels.forEach((label, i) => {
            const x = legendX + i * 60;
            const value = values[i];
            const percent = ((value / total) * 100).toFixed(1);
            
            // Quadrado da cor
            ctx.fillStyle = colors[i % colors.length];
            ctx.fillRect(x - 20, legendY - 6, 12, 12);
            
            // Label
            ctx.fillStyle = '#333';
            ctx.font = '10px sans-serif';
            ctx.textAlign = 'left';
            ctx.fillText(label.substring(0, 8), x - 5, legendY);
            
            // Valor
            ctx.fillStyle = '#666';
            ctx.font = '9px sans-serif';
            ctx.fillText(value + ' (' + percent + '%)', x - 5, legendY + 12);
        });
    }
    
    // Renderizar gráficos
    function renderCharts() {
        console.log('Iniciando renderização dos gráficos...');
        setTimeout(() => {
            console.log('Renderizando gráficos...');
            drawLineChart('chartOrders', chartData.dates, chartData.orders, 'rgb(13, 110, 253)', 'Pedidos (últimos 7 dias)');
            drawBarChart('chartRevenue', chartData.dates, chartData.revenue, 'rgba(25, 135, 84, 0.8)', 'Receita (R$)');
            drawLineChart('chartTickets', chartData.dates, chartData.tickets, 'rgb(13, 110, 253)', 'Tickets (últimos 7 dias)');
            drawDonutChart('chartOrdersStatus', chartData.ordersStatus, 
                ['rgba(255,193,7,0.9)', 'rgba(25,135,84,0.9)', 'rgba(220,53,69,0.9)', 'rgba(108,117,125,0.9)', 'rgba(13,110,253,0.9)'], 'Pedidos');
            drawDonutChart('chartInvoicesStatus', chartData.invoicesStatus,
                ['rgba(220,53,69,0.9)', 'rgba(25,135,84,0.9)', 'rgba(108,117,125,0.9)', 'rgba(13,110,253,0.9)'], 'Faturas');
            console.log('Gráficos renderizados!');
        }, 200);
    }
    
    // Drag & Drop
    function initDragDrop(container, itemSelector, onSave) {
        if (!container) return;
        let dragging = null;
        let dragHandle = null;
        
        container.addEventListener('mousedown', (e) => {
            if (e.target.closest('.widget-handle, .la-grip-vertical')) {
                dragHandle = true;
            }
        });
        
        container.addEventListener('mouseup', () => {
            dragHandle = null;
        });
        
        container.addEventListener('dragstart', (e) => {
            const item = e.target.closest(itemSelector);
            if (!item) return;
            if (itemSelector === '.dashboard-widget' && !dragHandle) {
                e.preventDefault();
                return;
            }
            dragging = item;
            item.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        
        container.addEventListener('dragend', () => {
            if (dragging) {
                dragging.classList.remove('dragging');
                dragging = null;
            }
            dragHandle = null;
            if (onSave) setTimeout(onSave, 100);
        });
        
        container.addEventListener('dragover', (e) => {
            e.preventDefault();
            if (!dragging) return;
            const target = e.target.closest(itemSelector);
            if (!target || target === dragging) return;
            const rect = target.getBoundingClientRect();
            const before = e.clientY < rect.top + rect.height / 2;
            if (before) {
                target.parentNode.insertBefore(dragging, target);
            } else {
                target.parentNode.insertBefore(dragging, target.nextSibling);
            }
        });
        
        container.addEventListener('drop', (e) => {
            e.preventDefault();
        });
    }
    
    // Salvar posições
    function saveWidgetPositions() {
        const widgets = [];
        document.querySelectorAll('.dashboard-widget').forEach((el, index) => {
            const key = el.dataset.widgetKey;
            if (!key) return;
            widgets.push({
                key: key,
                type: el.dataset.widgetType || 'stat',
                position: index,
                visible: true
            });
        });
        
        if (widgets.length === 0) return;
        
        fetch('/admin/dashboard.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                _csrf: csrfToken,
                action: 'save_widgets',
                widgets: JSON.stringify(widgets)
            })
        }).then(r => r.json()).then(data => {
            if (data.success) {
                showToast('Posições salvas!', 'success');
            }
        }).catch(err => console.error(err));
    }
    
    // Salvar do modal
    document.getElementById('saveWidgetsBtn')?.addEventListener('click', function() {
        const btn = this;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Salvando...';
        
        const widgets = [];
        const widgetList = document.getElementById('widgetList');
        if (!widgetList) {
            btn.disabled = false;
            btn.innerHTML = originalText;
            showToast('Lista de widgets não encontrada', 'danger');
            return;
        }
        
        const seenKeys = new Set();
        widgetList.querySelectorAll('[data-widget-key]').forEach((item, index) => {
            const key = item.dataset.widgetKey;
            if (!key || typeof key !== 'string') {
                console.warn('Widget sem key válida:', item);
                return;
            }
            
            const trimmedKey = key.trim();
            
            // Validar key antes de adicionar
            if (!/^[a-zA-Z0-9_-]+$/.test(trimmedKey)) {
                console.warn('Widget key inválida ignorada:', trimmedKey);
                return;
            }
            
            // Evitar duplicatas
            if (seenKeys.has(trimmedKey)) {
                console.warn('Widget duplicado ignorado:', trimmedKey);
                return;
            }
            seenKeys.add(trimmedKey);
            
            const toggle = item.querySelector('.widget-toggle');
            widgets.push({
                key: trimmedKey,
                type: getWidgetType(trimmedKey),
                position: widgets.length, // Usar length em vez de index para evitar gaps
                visible: toggle ? toggle.checked : true
            });
        });
        
        if (widgets.length === 0) {
            btn.disabled = false;
            btn.innerHTML = originalText;
            showToast('Nenhum widget válido encontrado', 'warning');
            return;
        }
        
        console.log('Enviando widgets:', widgets);
        
        try {
            console.log('=== INÍCIO DO SALVAMENTO ===');
            console.log('Widgets coletados:', widgets);
            console.log('Quantidade de widgets:', widgets.length);
            
            const widgetsJson = JSON.stringify(widgets);
            console.log('JSON gerado:', widgetsJson);
            console.log('Tamanho do JSON:', widgetsJson.length, 'bytes');
            
            // Validar JSON
            try {
                JSON.parse(widgetsJson);
                console.log('✓ JSON válido');
            } catch (e) {
                console.error('✗ JSON inválido:', e.message);
                throw new Error('JSON inválido: ' + e.message);
            }
            
            const formData = new URLSearchParams();
            formData.append('_csrf', csrfToken);
            formData.append('action', 'save_widgets');
            formData.append('widgets', widgetsJson);
            
            console.log('CSRF Token:', csrfToken ? 'Presente' : 'Ausente');
            console.log('Action:', 'save_widgets');
            console.log('Enviando requisição...');
            
            fetch('/admin/dashboard.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: formData
            })
            .then(response => {
                console.log('=== RESPOSTA RECEBIDA ===');
                console.log('Status:', response.status);
                console.log('Status Text:', response.statusText);
                console.log('Headers:', Object.fromEntries(response.headers.entries()));
                
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('=== ERRO HTTP ===');
                        console.error('Status:', response.status);
                        console.error('Resposta completa:', text);
                        console.error('Primeiros 500 caracteres:', text.substring(0, 500));
                        throw new Error('HTTP ' + response.status + ': ' + text.substring(0, 200));
                    });
                }
                
                return response.text().then(text => {
                    console.log('Resposta texto:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Erro ao fazer parse do JSON:', e);
                        console.error('Texto recebido:', text);
                        throw new Error('Resposta não é JSON válido: ' + text.substring(0, 200));
                    }
                });
            })
            .then(data => {
                console.log('=== DADOS PROCESSADOS ===');
                console.log('Dados recebidos:', data);
                console.log('Success:', data.success);
                console.log('Error:', data.error);
                
                if (data.success) {
                    console.log('✓ Salvamento bem-sucedido!');
                    showToast('Dashboard personalizado!', 'success');
                    setTimeout(() => location.reload(), 500);
                } else {
                    console.error('✗ Salvamento falhou:', data.error);
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    showToast('Erro: ' + (data.error || 'Erro desconhecido'), 'danger');
                }
            })
            .catch(err => {
                console.error('=== ERRO CAPTURADO ===');
                console.error('Tipo do erro:', err.constructor.name);
                console.error('Mensagem:', err.message);
                console.error('Stack:', err.stack);
                console.error('Erro completo:', err);
                
                btn.disabled = false;
                btn.innerHTML = originalText;
                showToast('Erro ao salvar: ' + (err.message || 'Erro desconhecido'), 'danger');
            });
        } catch (err) {
            console.error('=== ERRO NO TRY/CATCH ===');
            console.error('Tipo do erro:', err.constructor.name);
            console.error('Mensagem:', err.message);
            console.error('Stack:', err.stack);
            console.error('Erro completo:', err);
            
            btn.disabled = false;
            btn.innerHTML = originalText;
            showToast('Erro ao preparar dados: ' + err.message, 'danger');
        }
    });
    
    function getWidgetType(key) {
        return key.startsWith('chart_') ? 'chart' : 'stat';
    }
    
    // Toggle widgets
    document.querySelectorAll('.widget-toggle').forEach(toggle => {
        toggle.addEventListener('change', function() {
            const key = this.dataset.widgetKey;
            if (!key) return;
            
            fetch('/admin/dashboard.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    _csrf: csrfToken,
                    action: 'toggle_widget',
                    widget_key: key,
                    is_visible: this.checked ? '1' : '0'
                })
            }).then(r => r.json()).then(data => {
                if (!data.success) {
                    this.checked = !this.checked;
                }
            });
        });
    });
    
    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    
    // Inicializar
    renderCharts();
    initDragDrop(document.getElementById('dashboardWidgets'), '.dashboard-widget', saveWidgetPositions);
    initDragDrop(document.getElementById('widgetList'), '.list-group-item', null);
    
    window.addEventListener('resize', () => {
        clearTimeout(window.resizeTimer);
        window.resizeTimer = setTimeout(renderCharts, 250);
    });
})();
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>
