<?php
$page_title = 'Gestionar Eventos';
require_once 'includes/header.php';

if (!function_exists('e')) {
    function e($v) { echo htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// Seguridad de rol
if (($current_role ?? '') !== 'admin') {
    echo '<div class="admin-section"><p><strong>Acceso denegado.</strong> No tienes los permisos necesarios para ver esta página.</p></div>';
    require_once 'includes/footer.php';
    exit;
}

$pdo = get_db_connection();

/* =========================
   Datos base
   ========================= */
$events = $pdo->query("SELECT * FROM bingo_events ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Moneda local (no USD)
$stmt_local_currency = $pdo->query("SELECT code FROM bingo_currencies WHERE is_default = 1 LIMIT 1");
$local_currency_code = $stmt_local_currency ? ($stmt_local_currency->fetchColumn() ?: 'VES') : 'VES';

/* =========================
   Métricas para UX
   ========================= */
$active_count     = (int)($pdo->query("SELECT COUNT(*) FROM bingo_events WHERE is_active = 1")->fetchColumn() ?: 0);
$inactive_count   = (int)($pdo->query("SELECT COUNT(*) FROM bingo_events WHERE is_active = 0")->fetchColumn() ?: 0);
$upcoming_count   = (int)($pdo->query("SELECT COUNT(*) FROM bingo_events WHERE event_date >= NOW()")->fetchColumn() ?: 0);
$next_event_row   = $pdo->query("SELECT name, event_date FROM bingo_events WHERE event_date >= NOW() ORDER BY event_date ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$next_event_label = $next_event_row ? ($next_event_row['name'] . ' - ' . date('d/m/Y h:i A', strtotime($next_event_row['event_date']))) : '—';
?>
<style>
:root{
    --ui-primary:#2563eb;
    --ui-primary-600:#1d4ed8;
    --ui-muted:#64748b;
    --ui-bg:#f1f5f9;
    --ui-card:#ffffff;
    --ui-border:#e2e8f0;
    --ui-danger:#ef4444;
    --ui-success:#10b981;
    --ui-warning:#f59e0b;
    --radius:14px;
    --shadow-lg:0 12px 28px rgba(2,8,23,.08);
    --shadow-sm:0 4px 12px rgba(2,8,23,.06);
}

/* Hero */
.events-hero{
    background:
      radial-gradient(1200px 600px at 10% -10%, rgba(255,255,255,.28), transparent 60%) no-repeat,
      linear-gradient(135deg,#22c55e 0%, #3b82f6 100%);
    border-radius: 20px;
    padding: 22px 20px;
    box-shadow: var(--shadow-lg);
    color:#fff;
    margin-bottom: 18px;
}
.events-hero .hero-top{
    display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap;
}
.events-hero h2{
    margin:0; font-size:1.6rem; line-height:1.2; font-weight:800; letter-spacing:.2px;
}
.events-hero .hero-sub{
    margin:.25rem 0 0; opacity:.9; font-weight:500;
}
.hero-metrics{
    display:flex; gap:14px; flex-wrap:wrap;
}
.metric-card{
    background: rgba(255,255,255,.12);
    border:1px solid rgba(255,255,255,.25);
    box-shadow: inset 0 1px 0 rgba(255,255,255,.2);
    padding:10px 12px;
    border-radius:12px;
    min-width:160px;
}
.metric-card .label{ font-size:.8rem; opacity:.9; }
.metric-card .value{ font-size:1.25rem; font-weight:800; }

/* Layout principal: formulario + tabla */
.events-grid{
    display:grid; grid-template-columns: 380px 1fr; gap:18px;
}
@media (max-width: 1060px){
    .events-grid{ grid-template-columns: 1fr; }
}

/* Card */
.card{
    background:var(--ui-card); border:1px solid var(--ui-border);
    border-radius:16px; box-shadow: var(--shadow-sm);
}

/* Formulario de evento */
.event-form{ padding:18px; }
.event-form h3{ margin:0 0 10px; font-size:1.2rem; font-weight:800; color:#0f172a; }
.form-row{ margin-bottom:12px; }
.form-row label{ display:block; font-weight:700; margin-bottom:8px; color:#111827; }
.form-row input{
    width:100%; border:1px solid #cbd5e1; border-radius:10px; padding:10px 12px; outline:none;
}
.form-actions{ display:flex; gap:10px; flex-wrap:wrap; }
.btn{ display:inline-flex; align-items:center; gap:8px; border:none; cursor:pointer; font-weight:800; border-radius:10px; padding:10px 12px; }
.btn-primary{ background:#111827; color:#fff; }
.btn-secondary{ background:#eef2ff; color:#1d4ed8; }
.btn-danger{ background:#fee2e2; color:#991b1b; }

/* Cards para eventos */
.event-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
    padding: 18px;
}

.event-card {
    background: var(--ui-card);
    border: 1px solid var(--ui-border);
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: var(--shadow-sm);
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    position: relative;
}

.event-details {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.event-details .detail-item {
    font-size: 0.95rem;
}

.event-details .detail-item strong {
    color: #111827;
    font-weight: 700;
    margin-right: 0.5rem;
}

.event-card .card-title {
    font-size: 1.2rem;
    font-weight: 800;
    margin: 0;
}

.event-card .card-footer {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: auto;
    padding-top: 0.5rem;
}

.status-badge{
    display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; font-size:.8rem; font-weight:700;
    position: absolute;
    top: 1rem;
    right: 1rem;
}
.status-badge.active{ background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; }
.status-badge.inactive{ background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }

/* Modal */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}
.modal-content {
    background-color: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    max-width: 500px;
    width: 90%;
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}
.modal-header .close-btn {
    font-size: 1.5rem;
    font-weight: bold;
    cursor: pointer;
    border: none;
    background: none;
}
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    margin-top: 1rem;
}

/* Botón flotante para crear evento */
.fab {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    background-color: var(--ui-primary);
    color: white;
    border: none;
    border-radius: 50%;
    width: 60px;
    height: 60px;
    font-size: 2rem;
    box-shadow: var(--shadow-lg);
    cursor: pointer;
    z-index: 999;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s ease, background-color 0.2s ease;
}
.fab:hover {
    transform: translateY(-2px);
    background-color: var(--ui-primary-600);
}

/* Toasts */
.toasts{ position:fixed; right:16px; bottom:16px; display:flex; flex-direction:column; gap:10px; z-index:9999; }
.toast{
    background:#111827; color:#fff; padding:10px 12px; border-radius:10px; box-shadow: var(--shadow-lg); font-weight:600;
    display:flex; align-items:center; gap:10px; min-width:240px;
}
.toast.success{ background:#065f46; }
.toast.error{ background:#991b1b; }

/* Ajuste de hero en móvil (optimización de espacio) */
@media (max-width: 480px){
  .events-hero{
    padding: 12px 14px;
    border-radius: 16px;
    margin-bottom: 12px;
  }
  .events-hero h2{ font-size: 1.2rem; }
  .events-hero .hero-sub{ font-size: .9rem; margin-top: .2rem; }

  .hero-metrics{
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    width: 100%;
  }
  .metric-card{
    min-width: 0;
    padding: 8px 10px;
  }
  .row-actions > *{ flex:1 1 140px; }
}
</style>

<div class="admin-section">
    <div class="events-hero">
        <div class="hero-top">
            <div class="hero-metrics">
                <div class="metric-card">
                    <div class="label">Activos</div>
                    <div class="value"><?php e($active_count); ?></div>
                </div>
                <div class="metric-card">
                    <div class="label">Inactivos</div>
                    <div class="value"><?php e($inactive_count); ?></div>
                </div>
                <div class="metric-card">
                    <div class="label">Próximos</div>
                    <div class="value"><?php e($upcoming_count); ?></div>
                </div>
                <div class="metric-card">
                    <div class="label">Siguiente</div>
                    <div class="value" style="font-size:.95rem;"><?php e($next_event_label); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="event-list">
        <?php if (empty($events)): ?>
            <p style="text-align:center;padding:24px;width:100%;">Sin eventos.</p>
        <?php else: ?>
            <?php foreach ($events as $ev): ?>
                <?php $active = ((int)($ev['is_active'] ?? 0) === 1); ?>
                <div class="event-card" id="event-card-<?php e($ev['id']); ?>">
                    <span class="status-badge <?php echo $active ? 'active':'inactive'; ?>">
                        <?php echo $active ? 'Activo' : 'Inactivo'; ?>
                    </span>
                    <h4 class="card-title"><?php e($ev['name']); ?></h4>
                    <div class="event-details">
                        <div class="detail-item"><strong>Fecha:</strong> <?php e(date('d/m/Y h:i A', strtotime($ev['event_date']))); ?></div>
                        <div class="detail-item"><strong>Precio (<?php e($local_currency_code); ?>):</strong> <?php e(number_format((float)$ev['price_local'], 2)); ?></div>
                        <div class="detail-item"><strong>Precio (USD):</strong> <?php e(number_format((float)$ev['price_usd'], 2)); ?></div>
                        <div class="detail-item"><strong>Creado:</strong> <?php e(date('d/m/Y H:i', strtotime($ev['created_at']))); ?></div>
                    </div>
                    <div class="card-footer">
                        <?php
                          $js = htmlspecialchars(json_encode([
                              'id' => (int)$ev['id'],
                              'name' => (string)$ev['name'],
                              'event_date' => (string)$ev['event_date'],
                              'price_local' => (float)$ev['price_local'],
                              'price_usd' => (float)$ev['price_usd'],
                              'is_active' => (int)($ev['is_active'] ?? 0),
                          ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                        ?>
                        <button class="btn btn-secondary" onclick='openEventModal(<?php echo "$js"; ?>)'>Editar</button>
                        <button class="btn <?php echo $active ? 'btn-danger' : 'btn-primary'; ?>" onclick="toggleEventStatus(<?php e($ev['id']); ?>, <?php echo $active ? 0 : 1; ?>)">
                            <?php echo $active ? 'Desactivar' : 'Activar'; ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<button class="fab" onclick="openEventModal()">+</button>

<div id="eventModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="event-modal-title">Crear Nuevo Evento</h3>
            <button class="close-btn" onclick="closeEventModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="event-form" class="event-form" autocomplete="off" novalidate onsubmit="return false;">
                <input type="hidden" id="event-id" value="">
                <div class="form-row">
                    <label for="event_name">Nombre del Evento</label>
                    <input type="text" id="event_name" required placeholder="Ej. Gran Bingo de Sábado">
                </div>
                <div class="form-row">
                    <label for="event_date">Fecha y Hora</label>
                    <input type="datetime-local" id="event_date" required>
                </div>
                <div class="form-row">
                    <label for="event_price">Precio por Cartón (en <?php e($local_currency_code); ?>)</label>
                    <input type="number" id="event_price" required step="0.01" min="0">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEventModal()">Cancelar</button>
                    <button type="submit" id="save-event-btn" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="toasts" id="toasts"></div>

<?php require_once 'includes/footer.php'; ?>