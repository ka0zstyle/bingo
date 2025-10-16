<?php
declare(strict_types=1);
$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

// Seguridad de rol
if (($current_role ?? '') !== 'admin') {
    echo '<div class="admin-section"><p><strong>Acceso denegado.</strong> No tienes los permisos necesarios para ver esta página.</p></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Helper de moneda
$currency_settings = ['symbol' => 'Bs.', 'code' => 'VES', 'rate' => 1, 'show_decimals' => true];
$helper_path = __DIR__ . '/../includes/currency_helper.php';
if (file_exists($helper_path)) {
    require_once $helper_path;
    if (function_exists('get_currency_settings')) {
        $currency_settings = get_currency_settings();
    }
}
$symbol   = (string)($currency_settings['symbol'] ?? 'Bs.');
$code     = (string)($currency_settings['code'] ?? 'VES');
$rate     = (float)($currency_settings['rate'] ?? 1);
$decimals = !empty($currency_settings['show_decimals']) ? 2 : 0;

$pdo = get_db_connection();

// ================== Datos base para filtros ==================
$events_all = $pdo->query("SELECT id, name, event_date FROM bingo_events ORDER BY event_date DESC")->fetchAll(PDO::FETCH_ASSOC);

// Evento por defecto: último por fecha (si no hay GET)
$default_event_id = null;
if (!empty($events_all)) {
    $default_event_id = (int)$events_all[0]['id'];
}

// Lee filtros (con valores por defecto)
$filter_event_id = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
if (!$filter_event_id && $default_event_id) {
    $filter_event_id = $default_event_id;
}
$filter_pm        = trim((string)($_GET['pm'] ?? ''));
$filter_user_key  = trim((string)($_GET['user_key'] ?? ''));
$filter_admin_id  = filter_input(INPUT_GET, 'admin_id', FILTER_VALIDATE_INT);

// Lista de métodos de pago (para filtro)
$pm_list = $pdo->query("SELECT DISTINCT payment_method FROM bingo_purchases WHERE payment_method IS NOT NULL AND payment_method <> '' ORDER BY payment_method ASC")->fetchAll(PDO::FETCH_COLUMN);

// Lista de admins (para filtro)
$admin_list = $pdo->query("SELECT id, username FROM bingo_admin_users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);

// Lista de usuarios (acotada por evento/método/admin para no ser enorme)
$user_sql = "SELECT DISTINCT
                COALESCE(NULLIF(TRIM(owner_email),''), NULLIF(TRIM(owner_id_card),''), TRIM(owner_name)) AS user_key,
                TRIM(owner_name) AS owner_name,
                TRIM(owner_email) AS owner_email,
                TRIM(owner_id_card) AS owner_id_card
             FROM bingo_purchases";
$user_where  = [];
$user_params = [];
if ($filter_event_id) { $user_where[] = "event_id = ?"; $user_params[] = $filter_event_id; }
if ($filter_pm !== '') { $user_where[] = "payment_method = ?"; $user_params[] = $filter_pm; }
if ($filter_admin_id) { $user_where[] = "processed_by_admin_id = ?"; $user_params[] = $filter_admin_id; }
if (!empty($user_where)) { $user_sql .= " WHERE " . implode(" AND ", $user_where); }
$user_sql .= " ORDER BY owner_name ASC";
$user_stmt = $pdo->prepare($user_sql);
$user_stmt->execute($user_params);
$users_list = $user_stmt->fetchAll(PDO::FETCH_ASSOC);

// ================== Construcción del WHERE global ==================
$where  = [];
$params = [];

// Filtro por evento
if ($filter_event_id) { $where[] = 'p.event_id = ?'; $params[] = $filter_event_id; }
// Filtro por método de pago
if ($filter_pm !== '') { $where[] = 'p.payment_method = ?'; $params[] = $filter_pm; }
// Filtro por admin (quien procesó)
if ($filter_admin_id) { $where[] = 'p.processed_by_admin_id = ?'; $params[] = $filter_admin_id; }
// Filtro por usuario (clave flexible)
if ($filter_user_key !== '') {
    $where[] = '(p.owner_email = ? OR p.owner_id_card = ? OR p.owner_name = ?)';
    $params[] = $filter_user_key;
    $params[] = $filter_user_key;
    $params[] = $filter_user_key;
}

// Helper para anexar WHERE al SQL
$appendWhere = function (string $sqlBase) use ($where) {
    if (!empty($where)) { $sqlBase .= (stripos($sqlBase, 'WHERE') === false ? ' WHERE ' : ' AND ') . implode(' AND ', $where); }
    return $sqlBase;
};

// Conversor USD
$to_usd = function(float $local) use ($code, $rate): float {
    if ($code === 'USD') return $local;
    $r = max(1e-12, (float)$rate);
    return $local / $r;
};

// ================== Métricas globales (con filtros) ==================
$counts = ['pending'=>0,'approved'=>0,'rejected'=>0];
foreach (['pending','approved','rejected'] as $st) {
    $sql = "SELECT COUNT(*) FROM bingo_purchases p WHERE p.status = ?";
    if (!empty($where)) $sql .= " AND " . implode(' AND ', $where);
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$st], $params));
    $counts[$st] = (int)($stmt->fetchColumn() ?: 0);
}
$pending_count  = $counts['pending'];
$approved_count = $counts['approved'];
$rejected_count = $counts['rejected'];

$sql_total_local = "SELECT SUM(p.total_local) FROM bingo_purchases p WHERE p.status='approved'";
if (!empty($where)) { $sql_total_local .= " AND " . implode(' AND ', $where); }
$stmt_total = $pdo->prepare($sql_total_local);
$stmt_total->execute($params);
$total_local_approved = (float)($stmt_total->fetchColumn() ?: 0);
$total_usd_approved   = $to_usd($total_local_approved);

// Ingresos Hoy/Semana/Mes (aprobados) filtrados
$sql_today = "SELECT SUM(p.total_local) FROM bingo_purchases p WHERE p.status='approved' AND DATE(p.processed_at)=CURDATE()";
$sql_today = $appendWhere($sql_today);
$stmt = $pdo->prepare($sql_today); $stmt->execute($params); $today_local = (float)($stmt->fetchColumn() ?: 0);

$sql_week  = "SELECT SUM(p.total_local) FROM bingo_purchases p WHERE p.status='approved' AND YEARWEEK(p.processed_at,1)=YEARWEEK(CURDATE(),1)";
$sql_week  = $appendWhere($sql_week);
$stmt = $pdo->prepare($sql_week);  $stmt->execute($params); $week_local  = (float)($stmt->fetchColumn() ?: 0);

$sql_month = "SELECT SUM(p.total_local) FROM bingo_purchases p WHERE p.status='approved' AND YEAR(p.processed_at)=YEAR(CURDATE()) AND MONTH(p.processed_at)=MONTH(CURDATE())";
$sql_month = $appendWhere($sql_month);
$stmt = $pdo->prepare($sql_month); $stmt->execute($params); $month_local = (float)($stmt->fetchColumn() ?: 0);

$today_usd = $to_usd($today_local);
$week_usd  = $to_usd($week_local);
$month_usd = $to_usd($month_local);

// ================== Progreso por evento (con filtros) ==================
$event_progress = [];
if ($filter_event_id) {
    // evento filtrado
    $evName = ''; $evDate = '';
    foreach ($events_all as $ev) { if ((int)$ev['id'] === (int)$filter_event_id) { $evName = (string)$ev['name']; $evDate = (string)$ev['event_date']; break; } }

    // Aprobados
    $sqlA = "SELECT COUNT(c.id)
             FROM bingo_cards c
             JOIN bingo_purchases p ON p.id = c.purchase_id
             WHERE c.event_id = ? AND p.status='approved'";
    $pParamsA = [$filter_event_id];
    if ($filter_pm !== '')      { $sqlA .= " AND p.payment_method = ?";        $pParamsA[] = $filter_pm; }
    if ($filter_admin_id)       { $sqlA .= " AND p.processed_by_admin_id = ?"; $pParamsA[] = $filter_admin_id; }
    if ($filter_user_key !== ''){ $sqlA .= " AND (p.owner_email=? OR p.owner_id_card=? OR p.owner_name=?)"; $pParamsA[] = $filter_user_key; $pParamsA[] = $filter_user_key; $pParamsA[] = $filter_user_key; }
    $stmtA = $pdo->prepare($sqlA); $stmtA->execute($pParamsA); $appr = (int)($stmtA->fetchColumn() ?: 0);

    // Pendientes
    $sqlP = "SELECT COUNT(c.id)
             FROM bingo_cards c
             JOIN bingo_purchases p ON p.id = c.purchase_id
             WHERE c.event_id = ? AND p.status='pending'";
    $pParamsP = [$filter_event_id];
    if ($filter_pm !== '')      { $sqlP .= " AND p.payment_method = ?";        $pParamsP[] = $filter_pm; }
    if ($filter_admin_id)       { $sqlP .= " AND p.processed_by_admin_id = ?"; $pParamsP[] = $filter_admin_id; } // normalmente NULL para pending
    if ($filter_user_key !== ''){ $sqlP .= " AND (p.owner_email=? OR p.owner_id_card=? OR p.owner_name=?)"; $pParamsP[] = $filter_user_key; $pParamsP[] = $filter_user_key; $pParamsP[] = $filter_user_key; }
    $stmtP = $pdo->prepare($sqlP); $stmtP->execute($pParamsP); $pend = (int)($stmtP->fetchColumn() ?: 0);

    $event_progress[] = ['name'=>$evName,'event_date'=>$evDate,'approved'=>$appr,'pending'=>$pend];
} else {
    // próximos eventos (compacto)
    $next_events = $pdo->query("SELECT id, name, event_date FROM bingo_events WHERE is_active=1 AND event_date >= NOW() ORDER BY event_date ASC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($next_events as $ev) {
        $eid = (int)$ev['id'];
        $sqlA = "SELECT COUNT(c.id)
                 FROM bingo_cards c
                 JOIN bingo_purchases p ON p.id = c.purchase_id
                 WHERE c.event_id = ? AND p.status='approved'";
        $pParamsA = [$eid];
        if ($filter_pm !== '')      { $sqlA .= " AND p.payment_method = ?";        $pParamsA[] = $filter_pm; }
        if ($filter_admin_id)       { $sqlA .= " AND p.processed_by_admin_id = ?"; $pParamsA[] = $filter_admin_id; }
        if ($filter_user_key !== ''){ $sqlA .= " AND (p.owner_email=? OR p.owner_id_card=? OR p.owner_name=?)"; $pParamsA[] = $filter_user_key; $pParamsA[] = $filter_user_key; $pParamsA[] = $filter_user_key; }
        $stmtA = $pdo->prepare($sqlA); $stmtA->execute($pParamsA); $appr = (int)($stmtA->fetchColumn() ?: 0);

        $sqlP = "SELECT COUNT(c.id)
                 FROM bingo_cards c
                 JOIN bingo_purchases p ON p.id = c.purchase_id
                 WHERE c.event_id = ? AND p.status='pending'";
        $pParamsP = [$eid];
        if ($filter_pm !== '')      { $sqlP .= " AND p.payment_method = ?";        $pParamsP[] = $filter_pm; }
        if ($filter_admin_id)       { $sqlP .= " AND p.processed_by_admin_id = ?"; $pParamsP[] = $filter_admin_id; }
        if ($filter_user_key !== ''){ $sqlP .= " AND (p.owner_email=? OR p.owner_id_card=? OR p.owner_name=?)"; $pParamsP[] = $filter_user_key; $pParamsP[] = $filter_user_key; $pParamsP[] = $filter_user_key; }
        $stmtP = $pdo->prepare($sqlP); $stmtP->execute($pParamsP); $pend = (int)($stmtP->fetchColumn() ?: 0);

        $event_progress[] = ['name'=>$ev['name'],'event_date'=>$ev['event_date'],'approved'=>$appr,'pending'=>$pend];
    }
}

// ================== Movimientos (últimos pagos) con filtros ==================
$sqlLast = "SELECT
                p.id, p.owner_name, p.owner_email,
                p.total_local, p.total_usd, p.status,
                p.created_at, p.processed_at,
                e.name AS event_name,
                u.username AS processed_by_username
            FROM bingo_purchases p
            LEFT JOIN bingo_events e ON e.id = p.event_id
            LEFT JOIN bingo_admin_users u ON u.id = p.processed_by_admin_id";
if (!empty($where)) { $sqlLast .= " WHERE " . implode(' AND ', $where); }
$sqlLast .= " ORDER BY p.created_at DESC LIMIT 25";
$stmtLast = $pdo->prepare($sqlLast);
$stmtLast->execute($params);
$last_payments = $stmtLast->fetchAll(PDO::FETCH_ASSOC);

// ================== Actividad reciente (aplica filtros; admin directo por log) ==================
$actWhere   = [];
$actParams  = [];
// adapta los mismos filtros de purchases
if ($filter_event_id) { $actWhere[] = 'p.event_id = ?'; $actParams[] = $filter_event_id; }
if ($filter_pm !== '') { $actWhere[] = 'p.payment_method = ?'; $actParams[] = $filter_pm; }
if ($filter_user_key !== '') { $actWhere[] = '(p.owner_email=? OR p.owner_id_card=? OR p.owner_name=?)'; $actParams[] = $filter_user_key; $actParams[] = $filter_user_key; $actParams[] = $filter_user_key; }
// filtro específico por admin en el log
if ($filter_admin_id) { $actWhere[] = 'a.admin_user_id = ?'; $actParams[] = $filter_admin_id; }

$sqlAct = "SELECT a.description, a.created_at, u.username AS admin_username, p.owner_name, p.status AS purchase_status
           FROM bingo_purchase_audit_log a
           LEFT JOIN bingo_admin_users u ON u.id = a.admin_user_id
           LEFT JOIN bingo_purchases p ON p.id = a.purchase_id";
if (!empty($actWhere)) { $sqlAct .= " WHERE " . implode(' AND ', $actWhere); }
$sqlAct .= " ORDER BY a.created_at DESC LIMIT 12";
$stmtAct = $pdo->prepare($sqlAct);
$stmtAct->execute($actParams);
$recent_activity = $stmtAct->fetchAll(PDO::FETCH_ASSOC);

// Helpers de formato
$fmt_local = fn(float $v) => $symbol . ' ' . number_format($v, $decimals, ',', '.');
$fmt_usd   = fn(float $v) => '$ ' . number_format($v, 2, ',', '.');

?>
<style>
:root{
  --ui-primary:#2563eb; --ui-primary-600:#1d4ed8; --ui-muted:#64748b; --ui-bg:#f1f5f9; --ui-card:#ffffff; --ui-border:#e2e8f0;
  --ui-danger:#ef4444; --ui-success:#10b981; --ui-warning:#f59e0b; --radius:14px; --shadow-lg:0 12px 28px rgba(2,8,23,.08); --shadow-sm:0 4px 12px rgba(2,8,23,.06);
  --grad: linear-gradient(135deg,#22c55e 0%, #3b82f6 100%);
}
.dashboard-grid{ display:grid; grid-template-columns: repeat(12, 1fr); gap:14px; }
@media (max-width:1100px){ .dashboard-grid{ grid-template-columns: 1fr; } }
.card{ background:var(--ui-card); border:1px solid var(--ui-border); border-radius:16px; box-shadow: var(--shadow-sm); }
.card .block{ padding:14px; }

.filter-bar{
  display:flex; gap:8px; flex-wrap:wrap; align-items:end; margin-bottom:14px;
  background:#fff; border:1px solid var(--ui-border); border-radius:12px; padding:10px;
}
.filter-bar .form-group{ display:flex; flex-direction:column; gap:6px; }
.filter-bar label{ color:#334155; font-weight:800; font-size:.85rem; }
.filter-bar select, .filter-bar input{
  border:1px solid var(--ui-border); border-radius:10px; padding:8px 10px; min-width:200px;
}

.stat-grid{ display:grid; grid-template-columns: repeat(4, 1fr); gap:10px; }
@media (max-width:900px){ .stat-grid{ grid-template-columns: repeat(2, 1fr); } }
.stat-card{
  border-radius:12px; padding:12px; border:1px solid rgba(255,255,255,.28);
  background: var(--grad); color:#fff; box-shadow: var(--shadow-sm);
}
.stat-card .stat-title{ font-weight:800; font-size:.85rem; opacity:.95; }
.stat-card .stat-value{ font-size:1.4rem; font-weight:900; }
.stat-card .stat-sub{ font-weight:700; font-size:.85rem; opacity:.9; }

.neutral-card{ background:#fff; border:1px solid var(--ui-border); border-radius:12px; padding:12px; }
.badge{ display:inline-flex; padding:4px 8px; border-radius:999px; font-size:.78rem; font-weight:800; border:1px solid var(--ui-border); }
.badge.approved{ background:#ecfdf5; color:#065f46; border-color:#a7f3d0; }
.badge.rejected{ background:#fef2f2; color:#991b1b; border-color:#fecaca; }
.badge.pending{ background:#fff7ed; color:#9a3412; border-color:#fed7aa; }

.progress-wrap{ display:flex; flex-direction:column; gap:8px; }
.progress-row{ display:flex; align-items:center; justify-content:space-between; gap:8px; font-weight:800; color:#0f172a; }
.progress{ width:100%; height:10px; border-radius:999px; background:#f1f5f9; overflow:hidden; border:1px solid var(--ui-border); }
.progress > span{ display:block; height:100%; background:var(--ui-primary); }

.list{ display:flex; flex-direction:column; gap:10px; }
.activity-item{ display:flex; gap:10px; align-items:flex-start; border:1px solid var(--ui-border); border-radius:12px; padding:10px; }

.table{ width:100%; border-collapse:separate; border-spacing:0; }
.table th, .table td{ padding:10px; border-bottom:1px solid var(--ui-border); text-align:left; }
.table th{ background:#f8fafc; font-size:.85rem; font-weight:900; color:#0f172a; }
.small-muted{ color:#64748b; font-size:.85rem; }
</style>

<form class="filter-bar" method="get" action="">
  <div class="form-group">
    <label for="event_id">Evento</label>
    <select id="event_id" name="event_id">
      <?php foreach ($events_all as $ev): ?>
        <option value="<?= (int)$ev['id'] ?>" <?= ($filter_event_id == (int)$ev['id']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($ev['name']) ?> (<?= htmlspecialchars(date('d/m H:i', strtotime((string)$ev['event_date']))) ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group">
    <label for="user_key">Usuario</label>
    <select id="user_key" name="user_key">
      <option value="">Todos</option>
      <?php foreach ($users_list as $u):
        $key = (string)($u['user_key'] ?? '');
        if ($key === '') continue;
        $label = trim($u['owner_name'] ?: $key);
        if (!empty($u['owner_email']))   $label .= ' · ' . $u['owner_email'];
        if (!empty($u['owner_id_card'])) $label .= ' · CI ' . $u['owner_id_card'];
      ?>
        <option value="<?= htmlspecialchars($key) ?>" <?= ($filter_user_key === $key ? 'selected' : '') ?>>
          <?= htmlspecialchars($label) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group">
    <label for="pm">Método de Pago</label>
    <select id="pm" name="pm">
      <option value="">Todos</option>
      <?php foreach ($pm_list as $pm): ?>
        <option value="<?= htmlspecialchars($pm) ?>" <?= ($filter_pm === $pm ? 'selected' : '') ?>><?= htmlspecialchars($pm) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group">
    <label for="admin_id">Admin</label>
    <select id="admin_id" name="admin_id">
      <option value="">Todos</option>
      <?php foreach ($admin_list as $ad): ?>
        <option value="<?= (int)$ad['id'] ?>" <?= ($filter_admin_id == (int)$ad['id'] ? 'selected' : '') ?>><?= htmlspecialchars($ad['username']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group" style="flex-direction:row; gap:8px;">
    <button type="submit" class="neutral-card" style="padding:8px 12px; font-weight:800;">Aplicar</button>
    <a href="dashboard.php" class="neutral-card" style="padding:8px 12px; font-weight:800; text-decoration:none;">Limpiar</a>
  </div>
</form>

<div class="dashboard-grid">
  <div style="grid-column: span 8;">
    <section class="card">
      <div class="block">
        <div class="stat-grid">
          <div class="stat-card">
            <div class="stat-title">Ingresos Aprobados</div>
            <div class="stat-value"><?= htmlspecialchars($fmt_local($total_local_approved)) ?></div>
            <div class="stat-sub">(<?= htmlspecialchars($fmt_usd($total_usd_approved)) ?>)</div>
          </div>
          <div class="stat-card">
            <div class="stat-title">Pendientes</div>
            <div class="stat-value"><?= (int)$pending_count ?></div>
            <div class="stat-sub">Pagos por revisar</div>
          </div>
          <div class="stat-card">
            <div class="stat-title">Aprobados</div>
            <div class="stat-value"><?= (int)$approved_count ?></div>
            <div class="stat-sub">Pagos confirmados</div>
          </div>
          <div class="stat-card">
            <div class="stat-title">Rechazados</div>
            <div class="stat-value"><?= (int)$rejected_count ?></div>
            <div class="stat-sub">Pagos anulados</div>
          </div>
        </div>
      </div>
    </section>

    <section class="card" style="margin-top:14px;">
      <div class="block">
        <div class="stat-grid">
          <div class="stat-card">
            <div class="stat-title">Hoy</div>
            <div class="stat-value"><?= htmlspecialchars($fmt_local($today_local)) ?></div>
            <div class="stat-sub">(<?= htmlspecialchars($fmt_usd($today_usd)) ?>)</div>
          </div>
          <div class="stat-card">
            <div class="stat-title">Semana</div>
            <div class="stat-value"><?= htmlspecialchars($fmt_local($week_local)) ?></div>
            <div class="stat-sub">(<?= htmlspecialchars($fmt_usd($week_usd)) ?>)</div>
          </div>
          <div class="stat-card">
            <div class="stat-title">Mes</div>
            <div class="stat-value"><?= htmlspecialchars($fmt_local($month_local)) ?></div>
            <div class="stat-sub">(<?= htmlspecialchars($fmt_usd($month_usd)) ?>)</div>
          </div>
          <div class="stat-card">
            <div class="stat-title">Moneda Predeterminada</div>
            <div class="stat-value"><?= htmlspecialchars($code) ?></div>
            <div class="stat-sub">
              <?php if ($code === 'USD'): ?>
                1.0000 (Base)
              <?php else: ?>
                1 USD = <?= number_format($rate, 4, ',', '.') . ' ' . htmlspecialchars($code) ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="card" style="margin-top:14px;">
      <div class="block">
        <h3 style="margin:0 0 8px; font-size:1.05rem; font-weight:900; color:#0f172a;">Movimientos</h3>
        <div class="small-muted" style="margin-bottom:8px;">Últimos 25, aplicando filtros</div>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>Evento</th>
                <th>Total (<?= htmlspecialchars($code) ?>)</th>
                <th>Total (USD)</th>
                <th>Estado</th>
                <th>Aprobado por</th>
                <th>Fecha</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($last_payments)): ?>
                <tr><td colspan="8" class="small-muted">Sin resultados.</td></tr>
              <?php else: foreach ($last_payments as $p): ?>
                <tr>
                  <td><?= (int)$p['id'] ?></td>
                  <td><?= htmlspecialchars($p['owner_name']) ?></td>
                  <td><?= htmlspecialchars($p['event_name'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($fmt_local((float)$p['total_local'])) ?></td>
                  <td><?= htmlspecialchars($fmt_usd((float)$p['total_usd'])) ?></td>
                  <td>
                    <?php $st = (string)$p['status']; $cls = ($st==='approved'?'approved':($st==='rejected'?'rejected':'pending')); ?>
                    <span class="badge <?= $cls ?>"><?= ucfirst($st) ?></span>
                  </td>
                  <td><?= htmlspecialchars($p['processed_by_username'] ?? '—') ?></td>
                  <td>
                    <?php
                      $when = ($p['status'] === 'approved' && !empty($p['processed_at']))
                        ? (string)$p['processed_at']
                        : (string)$p['created_at'];
                      echo htmlspecialchars(date('d/m/Y H:i', strtotime($when)));
                    ?>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>

  <div style="grid-column: span 4;">
    <section class="card">
      <div class="block">
        <h3 style="margin:0 0 8px; font-size:1.05rem; font-weight:900; color:#0f172a;">Progreso por Evento</h3>
        <div class="list">
          <?php if (empty($event_progress)): ?>
            <div class="small-muted">Sin datos.</div>
          <?php else: foreach ($event_progress as $E):
            $approved = (int)$E['approved'];
            $pending  = (int)$E['pending'];
            $total    = max(1, $approved + $pending);
            $pct      = ($approved / $total) * 100;
          ?>
            <div class="neutral-card">
              <div class="progress-row">
                <div style="font-weight:900;"><?= htmlspecialchars($E['name'] ?: 'Evento') ?></div>
                <?php if (!empty($E['event_date'])): ?>
                  <div class="small-muted"><?= htmlspecialchars(date('d/m H:i', strtotime($E['event_date']))) ?></div>
                <?php endif; ?>
              </div>
              <div class="small-muted" style="display:flex; gap:8px; margin:6px 0;">
                <span>Aprobados: <strong><?= $approved ?></strong></span>
                <span>Pendientes: <strong><?= $pending ?></strong></span>
              </div>
              <div class="progress"><span style="width: <?= number_format($pct, 2, '.', '') ?>%;"></span></div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </section>

    <section class="card" style="margin-top:14px;">
      <div class="block">
        <h3 style="margin:0 0 8px; font-size:1.05rem; font-weight:900; color:#0f172a;">Actividad Reciente</h3>
        <div class="list">
          <?php if (empty($recent_activity)): ?>
            <div class="small-muted">Sin actividad.</div>
          <?php else: foreach ($recent_activity as $a):
            $admin = htmlspecialchars($a['admin_username'] ?? 'Sistema');
            $desc  = htmlspecialchars($a['description'] ?? '');
            $when  = htmlspecialchars(date('d/m H:i', strtotime((string)$a['created_at'])));
            $status = (string)($a['purchase_status'] ?? '');
            if ($status === 'approved' || stripos($desc, 'aprob') !== false) {
              $cls = 'approved';
            } elseif ($status === 'rejected' || stripos($desc, 'rechaz') !== false) {
              $cls = 'rejected';
            } else {
              $cls = 'pending';
            }
          ?>
            <div class="activity-item">
              <span class="badge <?= $cls ?>"><?= ucfirst($cls) ?></span>
              <div>
                <div style="font-weight:800;"><?= $desc ?: 'Actualización' ?></div>
                <div class="small-muted"><?= $when ?> · por <?= $admin ?></div>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </section>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>