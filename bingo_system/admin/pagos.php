<?php
$page_title = 'Gestión de Pagos';
require_once 'includes/header.php';

if (!function_exists('e')) {
    function e($v) { echo htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$pdo = get_db_connection();

/* =========================
   Filtros
   ========================= */
$status_filter = $_GET['status'] ?? 'pending';
$valid_statuses = ['pending', 'approved', 'rejected', 'history'];
if (!in_array($status_filter, $valid_statuses, true)) {
    $status_filter = 'pending';
}

/* =========================
   Monedas
   ========================= */
$stmt_currencies = $pdo->query("SELECT code, symbol FROM bingo_currencies");
$currencies = $stmt_currencies ? $stmt_currencies->fetchAll(PDO::FETCH_KEY_PAIR) : [];
$local_currency_code = array_search('Bs.', $currencies, true);
$base_currency_code  = array_search('$',   $currencies, true);
if ($local_currency_code === false) $local_currency_code = 'VES';
if ($base_currency_code  === false) $base_currency_code  = 'USD';

/* =========================
   Contadores para pestañas
   ========================= */
$pending_count = (int)($pdo->query("SELECT COUNT(*) FROM bingo_purchases WHERE status = 'pending'")->fetchColumn() ?: 0);
$approved_count = (int)($pdo->query("SELECT COUNT(*) FROM bingo_purchases WHERE status = 'approved'")->fetchColumn() ?: 0);
$rejected_count = (int)($pdo->query("SELECT COUNT(*) FROM bingo_purchases WHERE status = 'rejected'")->fetchColumn() ?: 0);
$history_count = (int)($pdo->query("SELECT COUNT(*) FROM bingo_purchase_audit_log")->fetchColumn() ?: 0);
$total_pending_amount = (float)($pdo->query("SELECT SUM(total_local) FROM bingo_purchases WHERE status = 'pending'")->fetchColumn() ?: 0);
$today_approved_count = (int)($pdo->query("SELECT COUNT(*) FROM bingo_purchases WHERE status = 'approved' AND processed_at >= CURDATE()")->fetchColumn() ?: 0);

/* =========================
   Datos de compras
   ========================= */
$purchases = [];
$history_rows = [];

if ($status_filter !== 'history') {
    $purchases_stmt = $pdo->prepare("
        SELECT
            p.id, p.owner_name, p.owner_email, p.payment_ref, p.created_at, p.status,
            p.total_local, p.total_usd, p.payment_receipt_path, p.processed_at,
            e.name as event_name,
            u.username as processed_by_username,
            COUNT(c.id) as card_count
        FROM bingo_purchases p
        LEFT JOIN bingo_events e ON p.event_id = e.id
        LEFT JOIN bingo_cards c ON p.id = c.purchase_id
        LEFT JOIN bingo_admin_users u ON p.processed_by_admin_id = u.id
        WHERE p.status = ?
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ");
    $purchases_stmt->execute([$status_filter]);
    $purchases = $purchases_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $history_stmt = $pdo->prepare("
        SELECT
            a.id AS audit_id,
            a.purchase_id,
            a.description,
            a.created_at,
            u.username,
            p.owner_name,
            p.owner_email,
            p.status AS purchase_status,
            e.name AS event_name
        FROM bingo_purchase_audit_log a
        JOIN bingo_purchases p ON a.purchase_id = p.id
        LEFT JOIN bingo_events e ON p.event_id = e.id
        LEFT JOIN bingo_admin_users u ON a.admin_user_id = u.id
        ORDER BY a.created_at DESC
        LIMIT 200
    ");
    $history_stmt->execute();
    $history_rows = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<style>
/* Estilos para el hero */
.payments-hero{
    background:
      radial-gradient(1200px 600px at 10% -10%, rgba(255,255,255,.28), transparent 60%) no-repeat,
      linear-gradient(135deg,#3b82f6 0%, #7c3aed 100%);
    border-radius: 20px;
    padding: 22px 20px;
    box-shadow: 0 12px 28px rgba(2,8,23,.08);
    color:#fff;
    margin-bottom: 18px;
}
.payments-hero h2{ margin:0; font-size:1.6rem; font-weight:800; }
.hero-stats{
    display:flex; gap:14px; flex-wrap:wrap; margin-top:10px;
}
.stat-card{
    background: rgba(255,255,255,.12);
    border:1px solid rgba(255,255,255,.25);
    box-shadow: inset 0 1px 0 rgba(255,255,255,.2);
    padding:10px 12px;
    border-radius:12px;
    min-width:160px;
}
.stat-card .label{ font-size:.8rem; opacity:.9; }
.stat-card .value{ font-size:1.25rem; font-weight:800; }

/* Estilos para los botones de estado */
.tab-grid{
    display:grid; grid-template-columns: 1fr 1fr; gap:8px;
    margin-top:20px;
}
.tab-grid .tab{
    background:#e0e7ff; color:#1e3a8a; border:1px solid #c7d2fe; border-radius:999px;
    padding:8px 12px; font-weight:800; text-decoration:none; display:flex; flex-direction:column; align-items:center; text-align:center;
    transition: background-color .15s, border-color .15s;
}
.tab-grid .tab.active{ background:#111827; color:#fff; border-color:#111827; }
.tab-grid .tab .count{
    background:#fff; color:#111827; border-radius:999px; padding:2px 8px; font-weight:900;
    margin-top:4px;
}

/* Responsive para el hero */
@media (min-width: 769px){
    .tab-grid{
        display:flex; flex-wrap:wrap;
        justify-content:flex-start;
        gap:8px;
    }
    .tab-grid .tab{
        flex-direction:row;
        align-items:center;
        padding:8px 12px;
    }
    .tab-grid .tab .count{
        margin-left:6px;
        margin-top:0;
    }
}
@media (max-width: 480px){
    .hero-stats{
        display:grid; grid-template-columns: 1fr 1fr;
    }
    .stat-card{
        min-width:unset;
    }
}

/* Estilos de las tarjetas de pago */
.payments-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 1rem;
}
.payment-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.5rem;
    box-shadow: 0 4px 12px rgba(2,8,23,.06);
    display: flex;
    flex-direction: column;
    /* Animación para la eliminación */
    transition: opacity 0.5s ease, transform 0.5s ease;
}
/* Estilo para cuando una tarjeta se está eliminando */
.payment-card.removing {
    opacity: 0;
    transform: scale(0.95);
}
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}
.client-info h3 { margin: 0; font-size: 1.1rem; font-weight: 700; }
.client-info small { color: #64748b; }
.payment-details { display: grid; gap: 0.5rem; }
.detail-item strong { color: #64748b; }
.card-actions {
    margin-top: auto; padding-top: 1rem;
    display: flex; flex-wrap: wrap; gap: 0.5rem;
}

/* Estilos para badges de estado */
.status-badge {
    padding: 0.4rem 0.8rem; border-radius: 999px;
    font-size: 0.75rem; font-weight: 700;
}
.status-badge.pending { background-color: #fff7ed; color: #c2410c; }
.status-badge.approved { background-color: #ecfdf5; color: #065f46; }
.status-badge.rejected { background-color: #fef2f2; color: #991b1b; }

/* Nuevo: estilos para las tarjetas de historial */
.history-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.5rem;
    box-shadow: 0 4px 12px rgba(2,8,23,.06);
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.history-card .history-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0;
    color: #111827;
}
.history-card .history-details {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.history-card .detail-item strong {
    color: #64748b;
}

/* Resto de estilos para la tabla de historial */
.table-card{ background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; overflow:hidden; box-shadow: 0 4px 12px rgba(2,8,23,.06); }
.table-card table{ width:100%; border-collapse:separate; border-spacing:0; }
.table-card thead th{
    background:#f8fafc; font-weight:800; color:#0f172a; font-size:.85rem; letter-spacing:.02em;
    border-bottom:1px solid #e2e8f0; padding:12px; white-space:nowrap;
}
.table-card tbody td{
    border-bottom:1px solid #e2e8f0; padding:14px 12px; vertical-align:middle;
}
.table-card tbody tr:last-child td{ border-bottom:none; }
.cell-client small{ color:#64748b; display:block; }
.cell-event small{ color:#64748b; }

.modal-overlay{
  display:none; position:fixed; inset:0; background:rgba(2,8,23,.6); z-index:9990; align-items:center; justify-content:center; padding:16px;
}
.modal-content{
  background:#fff; border:1px solid #e2e8f0; border-radius:14px; width:min(680px, 96vw);
  box-shadow: 0 12px 28px rgba(2,8,23,.08);
}
.modal-header{ padding:14px 18px; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; justify-content:space-between; }
.modal-body{ padding:18px; max-height:70vh; overflow:auto; }
.modal-footer{ padding:14px 18px; border-top:1px solid #e2e8f0; display:flex; align-items:center; gap:8px; justify-content:flex-end; }
.close-modal{ background:transparent; border:none; font-weight:900; font-size:20px; cursor:pointer; }
.toasts{ position:fixed; right:16px; bottom:16px; display:flex; flex-direction:column; gap:10px; z-index:9999; }
.toast{
    background:#111827; color:#fff; padding:10px 12px; border-radius:10px; box-shadow: 0 12px 28px rgba(2,8,23,.08); font-weight:600;
    display:flex; align-items:center; gap:10px; min-width:240px;
}
.toast.success{ background:#065f46; }
.toast.error{ background:#991b1b; }
</style>

<div class="admin-section" data-status-filter="<?php e($status_filter); ?>">
    <div class="payments-hero">
        <div class="hero-top">
            <div class="hero-stats">
                <div class="stat-card">
                    <div class="label">Pendientes (<?php e($local_currency_code); ?>)</div>
                    <div class="value" id="pending-total-amount"><?php echo ($currencies[$local_currency_code] ?? 'Bs.') . ' ' . number_format($total_pending_amount, 2); ?></div>
                </div>
                <div class="stat-card">
                    <div class="label">Aprobados hoy</div>
                    <div class="value" id="approved-today-count"><?php echo (int)$today_approved_count; ?></div>
                </div>
            </div>
            
            <div class="tab-grid">
                <a class="tab <?php echo $status_filter==='pending'?'active':''; ?>" href="?status=pending">
                    <div>Pendientes</div>
                    <div class="count" id="pending-count"><?php echo (int)$pending_count; ?></div>
                </a>
                <a class="tab <?php echo $status_filter==='approved'?'active':''; ?>" href="?status=approved">
                    <div>Aprobadas</div>
                    <div class="count" id="approved-count"><?php echo (int)$approved_count; ?></div>
                </a>
                <a class="tab <?php echo $status_filter==='rejected'?'active':''; ?>" href="?status=rejected">
                    <div>Rechazadas</div>
                    <div class="count" id="rejected-count"><?php echo (int)$rejected_count; ?></div>
                </a>
                <a class="tab <?php echo $status_filter==='history'?'active':''; ?>" href="?status=history">
                    <div>Historial</div>
                    <div class="count" id="history-count"><?php echo (int)$history_count; ?></div>
                </a>
            </div>
        </div>
    </div>
    
    <div style="margin-bottom:1rem;">
        <input type="search" id="payment-search" placeholder="Buscar por cliente, email, evento o referencia" style="border-radius:999px;border:1px solid #cbd5e1;padding:10px 12px;outline:none;width:100%;max-width:400px;">
    </div>

    <?php if ($status_filter === 'history'): ?>
        <div class="payments-list" id="payments-list-container">
            <?php if (empty($history_rows)): ?>
                <div class="empty-state" style="text-align:center;padding:24px;">
                    <div class="emoji" style="font-size:2rem;">🧾</div>
                    <h4 style="margin:.5rem 0;">No hay registros en el historial.</h4>
                    <p style="color:#64748b;margin:0;">Los cambios en los pagos aparecerán aquí.</p>
                </div>
            <?php else: ?>
                <?php foreach ($history_rows as $row): ?>
                    <div class="history-card">
                        <h4 class="history-title">Compra #<?php e($row['purchase_id']); ?></h4>
                        <div class="history-details">
                            <div class="detail-item"><strong>Cliente:</strong> <?php e($row['owner_name']); ?> (<?php e($row['owner_email']); ?>)</div>
                            <div class="detail-item"><strong>Evento:</strong> <?php e($row['event_name'] ?? '-'); ?></div>
                            <div class="detail-item"><strong>Descripción:</strong> <?php e($row['description']); ?></div>
                            <div class="detail-item"><strong>Usuario:</strong> <?php e($row['username'] ?? '-'); ?></div>
                            <div class="detail-item"><strong>Fecha:</strong> <?php e(date('d/m/Y H:i', strtotime($row['created_at']))); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="payments-list" id="payments-list-container">
            <?php if (empty($purchases)): ?>
                <div class="empty-state" style="text-align:center;padding:24px;">
                    <div class="emoji" style="font-size:2rem;">🧾</div>
                    <h4 style="margin:.5rem 0;">No hay compras en esta categoría</h4>
                    <p style="color:#64748b;margin:0;">Cuando lleguen pagos aparecerán aquí para tu revisión.</p>
                </div>
            <?php else: ?>
                <?php foreach ($purchases as $purchase): ?>
                    <div class="payment-card" id="payment-card-<?php e($purchase['id']); ?>" data-id="<?php e($purchase['id']); ?>" data-search-term="<?php e(strtolower($purchase['owner_name'] . ' ' . $purchase['owner_email'] . ' ' . $purchase['event_name'] . ' ' . $purchase['payment_ref'])); ?>">
                        <div class="card-header">
                            <div class="client-info">
                                <h3><?php e($purchase['owner_name']); ?></h3>
                                <small><?php e($purchase['owner_email']); ?></small>
                            </div>
                            <span class="status-badge <?php echo $purchase['status']; ?>">
                                <?php echo ucfirst($purchase['status']); ?>
                            </span>
                        </div>
                        <div class="payment-details">
                            <div class="detail-item"><strong>Evento:</strong> <?php e($purchase['event_name']); ?></div>
                            <div class="detail-item"><strong>Cartones:</strong> <?php e($purchase['card_count']); ?></div>
                            <div class="detail-item"><strong>Referencia:</strong> <?php e($purchase['payment_ref']); ?></div>
                            <div class="detail-item"><strong>Total:</strong> <?php e(number_format((float)$purchase['total_local'], 2)); ?> <?php e($local_currency_code); ?> (<?php e(number_format((float)$purchase['total_usd'], 2)); ?> USD)</div>
                            <div class="detail-item"><strong>Fecha:</strong> <?php e(date('d/m/Y H:i', strtotime($purchase['created_at']))); ?></div>
                        </div>
                        <div class="card-actions">
                            <?php if ($status_filter === 'pending'): ?>
                                <button class="btn btn-approve" onclick="updateStatus(<?php e($purchase['id']); ?>, 'approved', this)">Aprobar</button>
                                <button class="btn btn-reject" onclick="updateStatus(<?php e($purchase['id']); ?>, 'rejected', this)">Rechazar</button>
                            <?php endif; ?>
                            <button class="btn btn-view" onclick="viewCartons(<?php e($purchase['id']); ?>)">Ver Cartones</button>
                            <?php if (!empty($purchase['payment_receipt_path'])): ?>
                                <button class="btn btn-view" onclick="viewReceipt('<?php echo BASE_URL . 'uploads/receipts/' . $purchase['payment_receipt_path']; ?>')">Ver Comprobante</button>
                            <?php endif; ?>
                            <button class="btn btn-view" onclick="viewHistory(<?php e($purchase['id']); ?>)">Historial</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div id="cartons-modal" class="modal-overlay">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Cartones de la Compra</h3>
      <button type="button" class="close-modal" onclick="closeCartonsModal()" aria-label="Cerrar">×</button>
    </div>
    <div id="modal-body" class="modal-body"></div>
    <div class="modal-footer">
      <button class="btn-ghost" id="prev-card-btn" onclick="changeCard(-1)">Anterior</button>
      <button class="btn-ghost" id="next-card-btn" onclick="changeCard(1)">Siguiente</button>
      <button class="btn-solid" onclick="closeCartonsModal()">Cerrar</button>
    </div>
  </div>
</div>

<div id="receipt-modal" class="modal-overlay">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Comprobante</h3>
      <button type="button" class="close-modal" onclick="closeReceiptModal()" aria-label="Cerrar">×</button>
    </div>
    <div class="modal-body" style="text-align:center;">
      <img id="receipt-image" src="" alt="Comprobante" style="max-width:100%; height:auto; border-radius:12px; box-shadow: 0 4px 12px rgba(2,8,23,.06);" />
    </div>
    <div class="modal-footer">
      <button class="btn-solid" onclick="closeReceiptModal()">Cerrar</button>
    </div>
  </div>
</div>

<div id="history-modal" class="modal-overlay">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Historial de la compra</h3>
      <button type="button" class="close-modal" onclick="closeHistoryModal()" aria-label="Cerrar">×</button>
    </div>
    <div id="history-modal-body" class="modal-body"></div>
    <div class="modal-footer">
      <button class="btn-solid" onclick="closeHistoryModal()">Cerrar</button>
    </div>
  </div>
</div>

<div class="toasts" id="toasts"></div>

<?php require_once 'includes/footer.php'; ?>