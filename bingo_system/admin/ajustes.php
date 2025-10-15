<?php
$page_title = 'Ajustes Generales';
require_once 'includes/header.php';

if ($current_role !== 'admin') {
    echo '<div class="admin-section"><p><strong>Acceso denegado.</strong> No tienes los permisos necesarios para ver esta página.</p></div>';
    require_once 'includes/footer.php';
    exit;
}

$pdo = get_db_connection();

$stmt_settings = $pdo->query("SELECT * FROM bingo_settings");
$settings_raw = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
$settings = [
    'header_color'      => $settings_raw['carton_header_color'] ?? '#c82828',
    'header_text_color' => $settings_raw['carton_header_text_color'] ?? '#ffffff',
    'free_cell_color'   => $settings_raw['carton_free_cell_color'] ?? '#ffc107',
    'free_text_color'   => $settings_raw['carton_free_text_color'] ?? '#9c4221',
    'font'              => $settings_raw['carton_font'] ?? 'Poppins',
    'center_content'    => $settings_raw['carton_center_content'] ?? 'FREE',
    'center_is_free'    => $settings_raw['carton_center_is_free'] ?? '1',
    'grid_style'        => $settings_raw['carton_grid_style'] ?? 'solid',
    'border_color'      => $settings_raw['carton_border_color'] ?? '#e2e8f0',
    'number_color'      => $settings_raw['carton_number_color'] ?? '#2d3748',
    'show_decimals'     => $settings_raw['show_decimals'] ?? '1',
];

$payment_methods = $pdo->query("SELECT * FROM bingo_payment_methods ORDER BY is_active DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$currencies      = $pdo->query("SELECT * FROM bingo_currencies ORDER BY is_default DESC, code ASC")->fetchAll(PDO::FETCH_ASSOC);

// Detectar moneda por defecto para hints
$defaultCurrency = null;
foreach ($currencies as $c) {
    if ((int)$c['is_default'] === 1) { $defaultCurrency = $c; break; }
}
?>
<style>
:root{
  --ui-primary:#2563eb;
  --ui-primary-600:#1d4ed8;
  --ui-accent:#7c3aed;
  --ui-muted:#64748b;
  --ui-bg:#f1f5f9;
  --ui-card:#ffffff;
  --ui-border:#e2e8f0;
  --ui-danger:#ef4444;
  --ui-success:#10b981;
  --ui-warning:#f59e0b;
  --radius:14px;
  --radius-sm:10px;
  --shadow-lg:0 12px 28px rgba(2,8,23,.08);
  --shadow-sm:0 4px 12px rgba(2,8,23,.06);
}

/* Hero compacta */
.settings-hero{
  background:
    radial-gradient(900px 500px at 10% -10%, rgba(255,255,255,.28), transparent 60%) no-repeat,
    linear-gradient(135deg,#3b82f6 0%, #9333ea 100%);
  border-radius: 18px;
  padding: 16px 16px;
  box-shadow: var(--shadow-lg);
  color:#fff;
  margin-bottom: 14px;
}
.settings-hero h2{ margin:0; font-size:1.35rem; font-weight:900; letter-spacing:.2px; }
.settings-hero p{ margin:.25rem 0 0; opacity:.95; }

/* Chips informativos */
.compact-info{
  display:flex; gap:10px; flex-wrap:wrap; margin-top:10px;
}
.info-chip{
  background: rgba(255,255,255,.12);
  border:1px solid rgba(255,255,255,.25);
  padding:6px 10px; border-radius:999px; font-weight:700; display:inline-flex; gap:8px; align-items:center;
  font-size:.85rem;
}

/* Grid principal */
.settings-grid{
  display:grid; grid-template-columns: 1.1fr 1fr; gap:14px;
}
@media (max-width: 1100px){ .settings-grid{ grid-template-columns: 1fr; } }

/* Cards y bloques */
.card{ background:var(--ui-card); border:1px solid var(--ui-border); border-radius:16px; box-shadow: var(--shadow-sm); }
.card .block{ padding:14px; }
.card h3{ margin:0 0 8px; font-size:1.05rem; font-weight:900; color:#0f172a; }

/* Subtle */
.subtle{
  background:#fafafa; border:1px dashed var(--ui-border); border-radius:12px; padding:10px; color:#334155; font-size:.92rem;
}

/* Formularios compactos */
.form-group{ margin-bottom:10px; }
.form-group label{ display:block; font-weight:800; margin-bottom:6px; color:#111827; font-size:.92rem; }
.form-group input[type="text"],
.form-group input[type="number"],
.form-group input[type="color"],
.form-group textarea,
.form-group select{
  width:100%; padding:10px 12px; border-radius:12px; border:1px solid var(--ui-border); background:#fff;
  font-size:.95rem; outline:none; transition:border-color .15s, box-shadow .15s;
}
.form-group textarea{ min-height:88px; resize:vertical; }
.form-group input:focus, .form-group textarea:focus, .form-group select:focus{
  border-color:var(--ui-accent); box-shadow:0 0 0 3px rgba(124,58,237,.14);
}

/* Botones */
.btn{ border:none; cursor:pointer; font-weight:900; border-radius:10px; padding:10px 12px; font-size:.92rem;
  transition: background .15s ease, transform .05s ease, box-shadow .15s ease; display:inline-flex; align-items:center; gap:8px;
}
.btn-primary{ background:var(--ui-primary); color:#fff; }
.btn-primary:hover{ background:var(--ui-primary-600); transform: translateY(-1px); }
.btn-secondary{ background:#111827; color:#fff; }
.btn-secondary:hover{ background:#0b1220; transform: translateY(-1px); }
.btn-danger{ background:var(--ui-danger); color:#fff; }
.btn-danger:hover{ background:#dc2626; transform: translateY(-1px); }
.btn-outline{ background:#fff; color:#1f2937; border:1px solid var(--ui-border); }
.btn-outline:hover{ background:#f8fafc; }

/* Toolbars compactas */
.toolbar{
  display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin:8px 0 0;
}

/* Tabla responsiva -> tarjetas en móvil */
.table-wrap{ padding:10px; }
.table{ width:100%; border-collapse:separate; border-spacing:0; table-layout: fixed; }
.table thead th{
  background:#f8fafc; font-weight:900; color:#0f172a; font-size:.82rem; letter-spacing:.02em;
  border-bottom:1px solid var(--ui-border); padding:10px; white-space:nowrap;
}
.table tbody td{
  border-bottom:1px solid var(--ui-border); padding:12px 10px; vertical-align:middle; background:#fff; word-break: break-word; font-size:.92rem;
}
.table tbody tr:last-child td{ border-bottom:none; }

@media (max-width: 980px){
  .table thead{ display:none; }
  .table, .table tbody, .table tr, .table td{ display:block; width:100%; }
  .table tbody tr{
      margin-bottom:10px; border:1px solid var(--ui-border); border-radius:12px; overflow:hidden; box-shadow: var(--shadow-sm);
      background:#fff;
  }
  .table tbody td{
      display:flex; align-items:flex-start; gap:12px; padding:10px 12px; border-bottom:1px solid var(--ui-border);
  }
  .table tbody td:last-child{ border-bottom:none; }
  .table tbody td::before{
      content: attr(data-label);
      flex: 0 0 42%;
      max-width: 42%;
      font-weight:900; color:#64748b; font-size:.85rem;
  }
}

/* Badges */
.badge{
  display:inline-flex; align-items:center; gap:6px; padding:4px 8px; border-radius:999px; font-size:.78rem; font-weight:800; border:1px solid var(--ui-border);
}
.badge.active{ background:#ecfdf5; color:#065f46; border-color:#a7f3d0; }
.badge.base{ background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe; }

/* Customizer layout compacto */
.customizer{
  display:grid; grid-template-columns: 360px 1fr; gap:14px; padding:10px;
}
@media (max-width: 1060px){ .customizer{ grid-template-columns: 1fr; } }
.customizer .panel{ background:#fff; border:1px solid var(--ui-border); border-radius:16px; box-shadow:var(--shadow-sm); }
.customizer .panel .inner{ padding:14px; }
.preview-box{ background:#f8fafc; border:1px dashed var(--ui-border); border-radius:14px; min-height:260px; display:flex; align-items:center; justify-content:center; }

/* Vista previa de cartón */
.preview-card{
  width: min(520px, 100%);
  background:#fff; border-radius:12px; box-shadow: var(--shadow-sm); overflow:hidden; border:1px solid var(--ui-border);
  font-family: Poppins, Arial, sans-serif;
}
.preview-card .header{
  display:grid; grid-template-columns: repeat(5, 1fr); text-transform: uppercase; font-weight: 900; letter-spacing:.5px;
}
.preview-card .grid{ display:grid; grid-template-columns: repeat(5, 1fr); }
.preview-card .cell{ padding:12px 0; text-align:center; font-weight:800; font-size:1rem; background:#fff; }
.preview-card .cell.free{ font-weight:900; text-transform:uppercase; }

/* Toasts */
.toasts{ position:fixed; right:16px; bottom:16px; display:flex; flex-direction:column; gap:10px; z-index:9999; }
.toast{
  background:#111827; color:#fff; padding:10px 12px; border-radius:10px; box-shadow: var(--shadow-lg); font-weight:600;
  display:flex; align-items:center; gap:10px; min-width:240px;
}
.toast.success{ background:#065f46; }
.toast.error{ background:#991b1b; }

/* Modales centrados */
.modal-overlay{
  position: fixed; inset: 0; background: rgba(2,8,23,.5);
  display: none; align-items: center; justify-content: center; z-index: 1000;
}
.modal-overlay.open{ display: flex; }
.modal{
  width: min(560px, 92%); background:#fff; border-radius:14px; border:1px solid var(--ui-border);
  box-shadow: var(--shadow-lg); overflow:hidden; animation: pop .12s ease-out;
}
@keyframes pop{ from{ transform: scale(.98); opacity:.8; } to{ transform: scale(1); opacity:1; } }
.modal-header{
  padding:12px 14px; border-bottom:1px solid var(--ui-border); display:flex; align-items:center; justify-content:space-between;
}
.modal-title{ margin:0; font-size:1.05rem; font-weight:900; color:#0f172a; }
.modal-close{ border:none; background:transparent; font-size:1.6rem; line-height:1; cursor:pointer; color:#334155; }
.modal-body{ padding:14px; }
.modal-footer{ padding:12px 14px; border-top:1px solid var(--ui-border); display:flex; gap:8px; justify-content:flex-end; }
</style>

<div class="settings-hero">
  <div class="compact-info">
    <span class="info-chip">Moneda Predeterminada: <strong>
      <?php echo $defaultCurrency ? htmlspecialchars($defaultCurrency['code']) : '—'; ?>
    </strong></span>
    <span class="info-chip">Tasa Actual: 
      <?php
        if ($defaultCurrency) {
          if ($defaultCurrency['code'] === 'USD') {
            echo '<strong>1.0000 (Moneda Base)</strong>';
          } else {
            echo '<strong>1 USD = '.number_format((float)$defaultCurrency['rate'], 4).' '.$defaultCurrency['code'].'</strong>';
          }
        } else { echo '—'; }
      ?>
    </span>
    <span class="info-chip">Formato de Precios: <strong><?php echo $settings['show_decimals'] === '1' ? 'Con decimales' : 'Sin decimales'; ?></strong></span>
  </div>
</div>

<div class="settings-grid">
  <!-- Métodos de pago -->
  <section class="card">
    <div class="block" style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
      <div>
        <h3 style="margin-bottom:4px;">Métodos de Pago</h3>
        <div class="small-muted">Administra los métodos y sus datos mostrados a los usuarios.</div>
      </div>
      <button class="btn btn-primary" id="pm-open-create-btn" type="button">Agregar Método</button>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Nombre</th><th>Estado</th><th>Acción</th></tr></thead>
        <tbody>
        <?php foreach ($payment_methods as $pm): ?>
          <tr>
            <td data-label="Nombre"><?php echo htmlspecialchars(htmlspecialchars_decode($pm['name'])); ?></td>
            <td data-label="Estado">
              <?php echo $pm['is_active'] ? '<span class="badge active">Activo</span>' : '<span class="badge">Inactivo</span>'; ?>
            </td>
            <td data-label="Acción" style="display:flex; gap:8px; flex-wrap:wrap;">
              <button class="btn btn-outline" onclick="openPmModal(<?php echo htmlspecialchars(json_encode($pm, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)">Editar</button>
              <button class="btn <?php echo $pm['is_active'] ? 'btn-danger' : 'btn-secondary'; ?>"
                onclick="togglePaymentMethodStatus(<?php echo (int)$pm['id']; ?>, <?php echo $pm['is_active'] ? '0' : '1'; ?>)">
                <?php echo $pm['is_active'] ? 'Desactivar' : 'Activar'; ?>
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- Monedas -->
  <section class="card">
    <div class="block">
      <h3 style="margin-bottom:4px;">Ajustes de Moneda</h3>
      <div class="subtle" style="margin-bottom:10px;">
        La tasa se guarda como “1 USD = tasa Moneda” cuando la predeterminada no es USD. Los precios en USD se muestran en tiempo real con esta tasa.
      </div>
      <form id="currency-settings-form" autocomplete="off" novalidate onsubmit="return false;">
        <div class="form-group" style="max-width:360px;">
          <label for="show_decimals">Formato de Precios Públicos</label>
          <select id="show_decimals" name="show_decimals">
            <option value="1" <?php echo $settings['show_decimals'] == '1' ? 'selected' : ''; ?>>Mostrar decimales (Ej: 10,00)</option>
            <option value="0" <?php echo $settings['show_decimals'] == '0' ? 'selected' : ''; ?>>Ocultar decimales (Ej: 10)</option>
          </select>
        </div>
        <div class="toolbar">
          <button type="button" class="btn btn-secondary" id="currency-display-save-btn">Guardar Formato</button>
        </div>
      </form>
    </div>

    <div class="table-wrap">
      <h3 style="margin:0 0 8px;">Gestionar Monedas</h3>
      <table class="table">
        <thead><tr><th>Moneda</th><th>Tasa Actual</th><th>Acción</th></tr></thead>
        <tbody>
        <?php foreach ($currencies as $currency): ?>
          <tr>
            <td data-label="Moneda">
              <strong><?php echo htmlspecialchars($currency['name']); ?> (<?php echo htmlspecialchars($currency['code']); ?>)</strong>
              <?php if ($currency['is_default']): ?>
                <span class="badge active" style="margin-left:6px;">Default</span>
              <?php endif; ?>
              <?php if ($currency['code'] === 'USD'): ?>
                <span class="badge base" style="margin-left:6px;">Base</span>
              <?php endif; ?>
            </td>
            <td data-label="Tasa Actual">
              <?php 
                if ($currency['code'] === 'USD') {
                  echo '1.0000 (Moneda Base)';
                } else {
                  echo '1 USD = ' . number_format((float)$currency['rate'], 4) . ' ' . $currency['code'];
                }
              ?>
            </td>
            <td data-label="Acción" style="display:flex; gap:8px; flex-wrap:wrap;">
              <?php if (!$currency['is_default']): ?>
                <button class="btn btn-outline" onclick="setDefaultCurrency(<?php echo (int)$currency['id']; ?>)">Hacer Default</button>
              <?php endif; ?>
              <?php if ($currency['code'] !== 'USD'): ?>
                <button class="btn btn-primary" onclick="openCurrencyModal(<?php echo htmlspecialchars(json_encode($currency, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)">Editar Tasa</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<section class="card" style="margin-top:14px;">
  <div class="block">
    <h3>Personalizador de Cartones</h3>
  </div>
  <div class="customizer">
    <div class="panel">
      <div class="inner">
        <h4 style="margin:0 0 8px;">Estilos y Colores</h4>
        <div class="form-group"><label for="header_color">Color Encabezado</label><input type="color" id="header_color" value="<?php echo $settings['header_color']; ?>"></div>
        <div class="form-group"><label for="header_text_color">Color Texto Encabezado</label><input type="color" id="header_text_color" value="<?php echo $settings['header_text_color']; ?>"></div>
        <div class="form-group"><label for="free_cell_color">Color Celda "Libre"</label><input type="color" id="free_cell_color" value="<?php echo $settings['free_cell_color']; ?>"></div>
        <div class="form-group"><label for="free_text_color">Color Texto "Libre"</label><input type="color" id="free_text_color" value="<?php echo $settings['free_text_color']; ?>"></div>
        <div class="form-group"><label for="number_color">Color de Números</label><input type="color" id="number_color" value="<?php echo $settings['number_color']; ?>"></div>
        <div class="form-group"><label for="border_color">Color de Bordes</label><input type="color" id="border_color" value="<?php echo $settings['border_color']; ?>"></div>
        <hr style="margin:12px 0;">
        <h4 style="margin:0 0 8px;">Diseño y Tipografía</h4>
        <div class="form-group">
          <label for="font">Fuente del Cartón</label>
          <select id="font">
            <option value="Poppins" <?php echo $settings['font'] === 'Poppins' ? 'selected' : ''; ?>>Poppins (Moderna)</option>
            <option value="Roboto" <?php echo $settings['font'] === 'Roboto' ? 'selected' : ''; ?>>Roboto (Clara)</option>
            <option value="Lato" <?php echo $settings['font'] === 'Lato' ? 'selected' : ''; ?>>Lato (Amigable)</option>
            <option value="Oswald" <?php echo $settings['font'] === 'Oswald' ? 'selected' : ''; ?>>Oswald (Condensada)</option>
            <option value="Playfair Display" <?php echo $settings['font'] === 'Playfair Display' ? 'selected' : ''; ?>>Playfair (Elegante)</option>
            <option value="Lobster" <?php echo $settings['font'] === 'Lobster' ? 'selected' : ''; ?>>Lobster (Cursiva)</option>
            <option value="Arial" <?php echo $settings['font'] === 'Arial' ? 'selected' : ''; ?>>Arial (Segura)</option>
            <option value="Times New Roman" <?php echo $settings['font'] === 'Times New Roman' ? 'selected' : ''; ?>>Times (Clásica)</option>
            <option value="Courier New" <?php echo $settings['font'] === 'Courier New' ? 'selected' : ''; ?>>Courier (Máquina)</option>
          </select>
        </div>
        <div class="form-group">
          <label for="grid_style">Estilo de Cuadrícula</label>
          <select id="grid_style">
            <option value="solid" <?php echo $settings['grid_style'] === 'solid' ? 'selected' : ''; ?>>Sólido</option>
            <option value="dotted" <?php echo $settings['grid_style'] === 'dotted' ? 'selected' : ''; ?>>Punteado</option>
            <option value="dashed" <?php echo $settings['grid_style'] === 'dashed' ? 'selected' : ''; ?>>Discontinuo</option>
            <option value="minimalist" <?php echo $settings['grid_style'] === 'minimalist' ? 'selected' : ''; ?>>Minimalista (Solo horizontal)</option>
          </select>
        </div>
        <hr style="margin:12px 0;">
        <h4 style="margin:0 0 8px;">Contenido Celda Central</h4>
        <div class="form-group"><label for="center_content">Texto o Emoji</label><textarea id="center_content" rows="2"><?php echo htmlspecialchars($settings['center_content']); ?></textarea></div>
        <div class="form-group"><label><input type="checkbox" id="center_is_free" <?php echo $settings['center_is_free'] === '1' ? 'checked' : ''; ?>> Habilitar centro libre</label></div>
        <div class="toolbar">
          <button class="btn btn-primary" id="save_settings" type="button">Guardar Cambios</button>
          <div id="save_status" class="form-hint"></div>
        </div>
      </div>
    </div>
    <div class="panel">
      <div class="inner">
        <h4 style="margin:0 0 8px;">Vista Previa</h4>
        <div class="preview-box" id="carton-preview-container"></div>
      </div>
    </div>
  </div>
</section>

<!-- Modales -->
<!-- Modal Método de Pago -->
<div class="modal-overlay" id="pmModal" aria-hidden="true" role="dialog" aria-labelledby="pm-modal-title">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title" id="pm-modal-title">Nuevo Método de Pago</h3>
      <button class="modal-close" type="button" aria-label="Cerrar" onclick="closePmModal()">&times;</button>
    </div>
    <div class="modal-body">
      <form id="pm-modal-form" onsubmit="return false;">
        <input type="hidden" id="pm-id">
        <div class="form-group">
          <label for="pm-name-input">Nombre (Ej: Zelle)</label>
          <input type="text" id="pm-name-input" required placeholder="Ej: Zelle">
        </div>
        <div class="form-group">
          <label for="pm-details-input">Instrucciones / Datos</label>
          <textarea id="pm-details-input" rows="5" placeholder="CI: V-123, Teléfono: 0412..., Banco: ..."></textarea>
          <small class="form-hint">Formato: “CI: V-123, Teléfono: 0412...”. Cada valor tras “:” tendrá botón copiar en la web.</small>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" type="button" onclick="closePmModal()">Cancelar</button>
      <button class="btn btn-primary" type="button" id="pm-modal-save-btn">Guardar</button>
    </div>
  </div>
</div>

<!-- Modal Moneda -->
<div class="modal-overlay" id="currencyModal" aria-hidden="true" role="dialog" aria-labelledby="currency-modal-title">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title" id="currency-modal-title">Editar Tasa de Moneda</h3>
      <button class="modal-close" type="button" aria-label="Cerrar" onclick="closeCurrencyModal()">&times;</button>
    </div>
    <div class="modal-body">
      <form id="currency-modal-form" onsubmit="return false;">
        <input type="hidden" id="currency-id">
        <div class="form-group">
          <label for="currency-rate">Nuevo valor de 1 USD (Ej: 197.5000)</label>
          <input type="text" id="currency-rate" required inputmode="decimal" placeholder="Ej: 197.5000">
          <small class="form-hint">Usa el punto como separador decimal.</small>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" type="button" onclick="closeCurrencyModal()">Cancelar</button>
      <button class="btn btn-primary" type="button" id="currency-modal-save-btn">Guardar</button>
    </div>
  </div>
</div>

<div class="toasts" id="toasts"></div>

<script>
const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';

function notify(message, type='success'){
  const c = document.getElementById('toasts');
  const el = document.createElement('div');
  el.className = 'toast ' + (type === 'error' ? 'error':'success');
  el.textContent = message;
  c.appendChild(el);
  setTimeout(()=>{ el.style.opacity='0'; el.style.transform='translateY(4px)'; }, 2500);
  setTimeout(()=>{ el.remove(); }, 3200);
}

async function postForm(url, formData) {
  formData.append('csrf_token', CSRF_TOKEN);
  const res = await fetch(url, { method:'POST', body: formData });
  let data = {};
  try { data = await res.json(); } catch(e){}
  if (!res.ok || !data.success) throw new Error(data.message || 'Operación fallida.');
  return data;
}

/* ========== Modal genérico helpers ========== */
function openOverlay(id){ const m = document.getElementById(id); if (m){ m.classList.add('open'); m.setAttribute('aria-hidden','false'); } }
function closeOverlay(id){ const m = document.getElementById(id); if (m){ m.classList.remove('open'); m.setAttribute('aria-hidden','true'); } }
document.addEventListener('click', (e)=>{
  const pm = document.getElementById('pmModal');
  const cm = document.getElementById('currencyModal');
  if (e.target === pm) closeOverlay('pmModal');
  if (e.target === cm) closeOverlay('currencyModal');
});
document.addEventListener('keydown', (e)=>{
  if (e.key === 'Escape'){ closeOverlay('pmModal'); closeOverlay('currencyModal'); }
});

/* ========== Métodos de pago (modal) ========== */
const pmOpenCreateBtn = document.getElementById('pm-open-create-btn');
const pmSaveBtn       = document.getElementById('pm-modal-save-btn');

pmOpenCreateBtn?.addEventListener('click', () => openPmModal());

function openPmModal(pm = null){
  try {
    const data = pm ? (typeof pm === 'string' ? JSON.parse(pm) : pm) : null;
    document.getElementById('pm-id').value         = data?.id || '';
    document.getElementById('pm-name-input').value = (data?.name || '').replace(/&amp;/g,'&');
    document.getElementById('pm-details-input').value = (data?.details || '').replace(/&amp;/g,'&');

    document.getElementById('pm-modal-title').textContent = data ? 'Editar Método de Pago' : 'Nuevo Método de Pago';
    openOverlay('pmModal');
    setTimeout(()=> document.getElementById('pm-name-input').focus(), 50);
  } catch (e){
    console.error(e); notify('No se pudo abrir el modal.', 'error');
  }
}
function closePmModal(){ closeOverlay('pmModal'); }

pmSaveBtn?.addEventListener('click', async ()=>{
  const id      = document.getElementById('pm-id').value.trim();
  const name    = document.getElementById('pm-name-input').value.trim();
  const details = document.getElementById('pm-details-input').value.trim();
  if (!name) return notify('El nombre del método es obligatorio.', 'error');

  const fd = new FormData();
  if (id) fd.append('id', id);
  fd.append('name', name);
  fd.append('details', details);

  const original = pmSaveBtn.textContent; pmSaveBtn.textContent='Guardando...'; pmSaveBtn.disabled=true;
  try {
    await postForm('api.php?action=save_payment_method', fd);
    notify('Método guardado.');
    setTimeout(()=> window.location.reload(), 500);
  } catch(err){ notify(err.message, 'error'); }
  finally { pmSaveBtn.textContent=original; pmSaveBtn.disabled=false; }
});

async function togglePaymentMethodStatus(id, active){
  if (!confirm(`¿Seguro que deseas ${active ? 'activar' : 'desactivar'} este método?`)) return;
  const fd = new FormData();
  fd.append('id', id);
  fd.append('is_active', active ? 1 : 0);
  try {
    await postForm('api.php?action=toggle_payment_method_status', fd);
    notify('Estado actualizado.');
    setTimeout(()=> window.location.reload(), 500);
  } catch(err){ notify(err.message, 'error'); }
}

/* ========== Monedas (modal) ========== */
const currencyDisplayBtn = document.getElementById('currency-display-save-btn');
currencyDisplayBtn?.addEventListener('click', async ()=>{
  const fd = new FormData();
  fd.append('show_decimals', document.getElementById('show_decimals').value);
  const original = currencyDisplayBtn.textContent; currencyDisplayBtn.textContent='Guardando...'; currencyDisplayBtn.disabled=true;
  try {
    await postForm('api.php?action=save_currency_settings', fd);
    notify('Formato guardado.');
  } catch(err){ notify(err.message, 'error'); }
  finally { currencyDisplayBtn.textContent=original; currencyDisplayBtn.disabled=false; }
});

const currencySaveBtn = document.getElementById('currency-modal-save-btn');

function openCurrencyModal(currency){
  try{
    const data = typeof currency === 'string' ? JSON.parse(currency) : currency;
    document.getElementById('currency-id').value = data.id || '';
    document.getElementById('currency-rate').value = data.rate || '';
    document.getElementById('currency-modal-title').textContent = `Editar Tasa (${data.code})`;
    openOverlay('currencyModal');
    setTimeout(()=> document.getElementById('currency-rate').focus(), 50);
  }catch(e){ console.error(e); notify('No se pudo abrir el editor de tasa.', 'error'); }
}
function closeCurrencyModal(){ closeOverlay('currencyModal'); }

currencySaveBtn?.addEventListener('click', async ()=>{
  const id   = document.getElementById('currency-id').value.trim();
  const rate = document.getElementById('currency-rate').value.trim();
  if (!id || !rate || isNaN(rate)) return notify('Ingresa un valor numérico válido para 1 USD.', 'error');

  const fd = new FormData();
  fd.append('id', id);
  fd.append('rate', rate);

  const original = currencySaveBtn.textContent; currencySaveBtn.textContent='Guardando...'; currencySaveBtn.disabled=true;
  try {
    await postForm('api.php?action=save_currency', fd);
    notify('Tasa actualizada.');
    setTimeout(()=> window.location.reload(), 600);
  } catch(err){ notify(err.message, 'error'); }
  finally { currencySaveBtn.textContent=original; currencySaveBtn.disabled=false; }
});

function setDefaultCurrency(id){
  if (!confirm('¿Hacer esta moneda la predeterminada?')) return;
  const fd = new FormData(); fd.append('id', id);
  postForm('api.php?action=set_default_currency', fd)
    .then(()=>{ notify('Moneda predeterminada actualizada.'); setTimeout(()=> window.location.reload(), 500); })
    .catch(err=> notify(err.message, 'error'));
}

/* ========== Personalizador Vista Previa ========== */
const els = {
  headerColor: document.getElementById('header_color'),
  headerText:  document.getElementById('header_text_color'),
  freeCell:    document.getElementById('free_cell_color'),
  freeText:    document.getElementById('free_text_color'),
  numColor:    document.getElementById('number_color'),
  border:      document.getElementById('border_color'),
  font:        document.getElementById('font'),
  grid:        document.getElementById('grid_style'),
  centerText:  document.getElementById('center_content'),
  centerFree:  document.getElementById('center_is_free'),
  previewBox:  document.getElementById('carton-preview-container'),
};

function headerCellBorderInline(cfg){ return cfg.grid === 'minimalist' ? `border-bottom:1px solid ${cfg.border};` : `border:1px ${cfg.grid} ${cfg.border};`; }
function cellBorderInline(cfg){ return cfg.grid === 'minimalist' ? `border-top:1px solid ${cfg.border};` : `border:1px ${cfg.grid} ${cfg.border};`; }
function gridBordersInline(cfg){ return cfg.grid === 'minimalist' ? `border-top:1px ${cfg.grid} ${cfg.border}; border-bottom:1px ${cfg.grid} ${cfg.border};` : `border-left:1px ${cfg.grid} ${cfg.border}; border-right:1px ${cfg.grid} ${cfg.border};`; }
function sampleNumber(r,c){ return (r*5 + c + 1).toString().padStart(2,'0'); }
function escapeHtml(s){ return s.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

function drawPreview(){
  if (!els.previewBox) return;
  const cfg = {
    headerBg: els.headerColor.value || '#c82828',
    headerFg: els.headerText.value  || '#ffffff',
    freeBg:   els.freeCell.value    || '#ffc107',
    freeFg:   els.freeText.value    || '#9c4221',
    numColor: els.numColor.value    || '#2d3748',
    border:   els.border.value      || '#e2e8f0',
    font:     els.font.value        || 'Poppins',
    grid:     els.grid.value        || 'solid',
    centerTxt: (els.centerText.value || 'FREE').trim() || 'FREE',
    centerIsFree: !!els.centerFree.checked
  };
  const headers = ['B','I','N','G','O'];

  let headerHtml = `<div class="header" style="background:${cfg.headerBg}; color:${cfg.headerFg};">`;
  for (let i = 0; i < 5; i++){
    headerHtml += `<div class="hcell" style="${headerCellBorderInline(cfg)}">${headers[i]}</div>`;
  }
  headerHtml += `</div>`;

  let gridHtml = `<div class="grid" style="${gridBordersInline(cfg)}">`;
  for (let r=0; r<5; r++){
    for (let c=0; c<5; c++){
      const isFree = (r===2 && c===2 && cfg.centerIsFree);
      const content = isFree ? cfg.centerTxt : sampleNumber(r,c);
      const style = isFree
        ? `background:${cfg.freeBg}; color:${cfg.freeFg}; ${cellBorderInline(cfg)}`
        : `color:${cfg.numColor}; ${cellBorderInline(cfg)}`;
      gridHtml += `<div class="cell ${isFree?'free':''}" style="${style}">${escapeHtml(content)}</div>`;
    }
  }
  gridHtml += `</div>`;

  els.previewBox.innerHTML = `<div class="preview-card" style="font-family:${cfg.font}, Poppins, Arial, sans-serif; border-color:${cfg.border};">${headerHtml}${gridHtml}</div>`;
}

['input','change','keyup'].forEach(evt=>{
  [els.headerColor, els.headerText, els.freeCell, els.freeText, els.numColor, els.border, els.font, els.grid, els.centerText, els.centerFree]
    .forEach(el => el?.addEventListener(evt, drawPreview));
});
drawPreview();

document.getElementById('save_settings')?.addEventListener('click', async ()=>{
  const fd = new FormData();
  fd.append('carton_header_color', els.headerColor.value);
  fd.append('carton_header_text_color', els.headerText.value);
  fd.append('carton_free_cell_color', els.freeCell.value);
  fd.append('carton_free_text_color', els.freeText.value);
  fd.append('carton_number_color', els.numColor.value);
  fd.append('carton_border_color', els.border.value);
  fd.append('carton_font', els.font.value);
  fd.append('carton_grid_style', els.grid.value);
  fd.append('carton_center_content', els.centerText.value);
  fd.append('carton_center_is_free', els.centerFree.checked ? '1':'0');

  const btn = document.getElementById('save_settings');
  const original = btn.textContent; btn.textContent='Guardando...'; btn.disabled=true;
  try {
    await postForm('api.php?action=save_settings', fd);
    notify('Ajustes de cartón guardados.');
    const status = document.getElementById('save_status');
    if (status) { status.textContent = 'Guardado ✔'; setTimeout(()=> status.textContent = '', 2000); }
  } catch(err){ notify(err.message, 'error'); }
  finally { btn.textContent=original; btn.disabled=false; }
});
</script>

<?php
require_once 'includes/footer.php';
?>