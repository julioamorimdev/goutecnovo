<?php
declare(strict_types=1);
// Garantir UTF-8 antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = 'Calendário';
$active = 'calendar';
require_once __DIR__ . '/partials/layout_start.php';

$adminId = (int)($_SESSION['admin_user_id'] ?? 0);

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    
    $action = $_POST['action'] ?? '';
    
    try {
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        if ($action === 'create' || $action === 'update') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $startDate = $_POST['start_date'] ?? '';
            $endDate = $_POST['end_date'] ?? '';
            $allDay = isset($_POST['all_day']) ? 1 : 0;
            $color = $_POST['color'] ?? '#007bff';
            $location = trim($_POST['location'] ?? '');
            $reminderMinutes = !empty($_POST['reminder_minutes']) ? (int)$_POST['reminder_minutes'] : null;
            
            if (empty($title) || empty($startDate)) {
                $_SESSION['error'] = 'Título e data de início são obrigatórios.';
            } else {
                if ($action === 'create') {
                    $stmt = db()->prepare("INSERT INTO calendar_events (admin_id, title, description, start_date, end_date, all_day, color, location, reminder_minutes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$adminId, $title, $description ?: null, $startDate, $endDate ?: null, $allDay, $color, $location ?: null, $reminderMinutes]);
                    $_SESSION['success'] = 'Evento criado com sucesso.';
                } else {
                    $stmt = db()->prepare("UPDATE calendar_events SET title=?, description=?, start_date=?, end_date=?, all_day=?, color=?, location=?, reminder_minutes=? WHERE id=? AND admin_id=?");
                    $stmt->execute([$title, $description ?: null, $startDate, $endDate ?: null, $allDay, $color, $location ?: null, $reminderMinutes, $id, $adminId]);
                    $_SESSION['success'] = 'Evento atualizado com sucesso.';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $stmt = db()->prepare("DELETE FROM calendar_events WHERE id=? AND admin_id=?");
            $stmt->execute([$id, $adminId]);
            $_SESSION['success'] = 'Evento excluído com sucesso.';
        } elseif ($action === 'toggle_complete') {
            $id = (int)$_POST['id'];
            $stmt = db()->prepare("UPDATE calendar_events SET is_completed = NOT is_completed WHERE id=? AND admin_id=?");
            $stmt->execute([$id, $adminId]);
            $_SESSION['success'] = 'Status do evento atualizado.';
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = 'Erro ao processar ação: ' . $e->getMessage();
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Buscar eventos
try {
    db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $startDate = $_GET['start'] ?? date('Y-m-01');
    $endDate = $_GET['end'] ?? date('Y-m-t');
    
    $stmt = db()->prepare("SELECT * FROM calendar_events WHERE admin_id = ? AND DATE(start_date) BETWEEN ? AND ? ORDER BY start_date ASC");
    $stmt->execute([$adminId, $startDate, $endDate]);
    $events = $stmt->fetchAll();
    
    // Formatar eventos para FullCalendar
    $calendarEvents = [];
    foreach ($events as $event) {
        $calendarEvents[] = [
            'id' => $event['id'],
            'title' => $event['title'],
            'start' => $event['start_date'],
            'end' => $event['end_date'],
            'allDay' => (bool)$event['all_day'],
            'backgroundColor' => $event['color'],
            'borderColor' => $event['color'],
            'extendedProps' => [
                'description' => $event['description'],
                'location' => $event['location'],
                'completed' => (bool)$event['is_completed'],
            ],
        ];
    }
} catch (Throwable $e) {
    $events = [];
    $calendarEvents = [];
}
?>

<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/locales/pt-br.js'></script>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Calendário</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#eventModal" onclick="openEventModal()">
            <i class="las la-plus me-1"></i> Novo Evento
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

    <div class="card shadow-sm">
        <div class="card-body">
            <div id="calendar"></div>
        </div>
    </div>
</div>

<!-- Modal de Evento -->
<div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="eventForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="eventAction" value="create">
                <input type="hidden" name="id" id="eventId">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="eventModalTitle">Novo Evento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="eventTitle" class="form-label">Título <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="eventTitle" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="eventDescription" class="form-label">Descrição</label>
                        <textarea class="form-control" id="eventDescription" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="eventStartDate" class="form-label">Data/Hora de Início <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="eventStartDate" name="start_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="eventEndDate" class="form-label">Data/Hora de Término</label>
                            <input type="datetime-local" class="form-control" id="eventEndDate" name="end_date">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="eventColor" class="form-label">Cor</label>
                            <input type="color" class="form-control form-control-color" id="eventColor" name="color" value="#007bff">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="eventLocation" class="form-label">Local</label>
                            <input type="text" class="form-control" id="eventLocation" name="location">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="eventAllDay" name="all_day" onchange="toggleAllDay()">
                                <label class="form-check-label" for="eventAllDay">
                                    Dia inteiro
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="eventReminder" class="form-label">Lembrete (minutos antes)</label>
                            <select class="form-select" id="eventReminder" name="reminder_minutes">
                                <option value="">Sem lembrete</option>
                                <option value="5">5 minutos</option>
                                <option value="15">15 minutos</option>
                                <option value="30">30 minutos</option>
                                <option value="60">1 hora</option>
                                <option value="1440">1 dia</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="eventSubmitBtn">Criar Evento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let calendar;
let selectedEvent = null;

document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    
    calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'pt-br',
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
        events: <?= json_encode($calendarEvents) ?>,
        editable: true,
        selectable: true,
        selectMirror: true,
        dayMaxEvents: true,
        select: function(arg) {
            openEventModal();
            document.getElementById('eventStartDate').value = arg.startStr.replace('T', 'T').substring(0, 16);
            if (arg.endStr) {
                document.getElementById('eventEndDate').value = arg.endStr.replace('T', 'T').substring(0, 16);
            }
        },
        eventClick: function(arg) {
            selectedEvent = arg.event;
            loadEventData(arg.event);
            openEventModal();
        },
        eventDrop: function(arg) {
            updateEventDates(arg.event);
        },
        eventResize: function(arg) {
            updateEventDates(arg.event);
        }
    });
    
    calendar.render();
});

function openEventModal() {
    document.getElementById('eventForm').reset();
    document.getElementById('eventAction').value = 'create';
    document.getElementById('eventId').value = '';
    document.getElementById('eventModalTitle').textContent = 'Novo Evento';
    document.getElementById('eventSubmitBtn').textContent = 'Criar Evento';
    document.getElementById('eventColor').value = '#007bff';
    selectedEvent = null;
}

function loadEventData(event) {
    const extendedProps = event.extendedProps;
    document.getElementById('eventAction').value = 'update';
    document.getElementById('eventId').value = event.id;
    document.getElementById('eventTitle').value = event.title;
    document.getElementById('eventDescription').value = extendedProps.description || '';
    document.getElementById('eventLocation').value = extendedProps.location || '';
    document.getElementById('eventColor').value = event.backgroundColor || '#007bff';
    document.getElementById('eventModalTitle').textContent = 'Editar Evento';
    document.getElementById('eventSubmitBtn').textContent = 'Atualizar Evento';
    
    // Formatar datas
    const start = event.start;
    const end = event.end || start;
    document.getElementById('eventStartDate').value = formatDateTimeLocal(start);
    document.getElementById('eventEndDate').value = formatDateTimeLocal(end);
    document.getElementById('eventAllDay').checked = event.allDay;
}

function formatDateTimeLocal(date) {
    if (!date) return '';
    const d = new Date(date);
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function toggleAllDay() {
    const allDay = document.getElementById('eventAllDay').checked;
    const startInput = document.getElementById('eventStartDate');
    const endInput = document.getElementById('eventEndDate');
    
    if (allDay) {
        startInput.type = 'date';
        endInput.type = 'date';
    } else {
        startInput.type = 'datetime-local';
        endInput.type = 'datetime-local';
    }
}

function updateEventDates(event) {
    const formData = new FormData();
    formData.append('_csrf', '<?= csrf_token() ?>');
    formData.append('action', 'update');
    formData.append('id', event.id);
    formData.append('start_date', formatDateTimeLocal(event.start));
    formData.append('end_date', event.end ? formatDateTimeLocal(event.end) : '');
    formData.append('all_day', event.allDay ? '1' : '0');
    
    fetch('<?= $_SERVER['PHP_SELF'] ?>', {
        method: 'POST',
        body: formData
    }).then(() => {
        location.reload();
    });
}
</script>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

