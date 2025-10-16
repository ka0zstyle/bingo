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
    // NUEVOS AJUSTES
    'cell_width'        => (int)($settings_raw['carton_cell_width'] ?? 48),
    'cell_height'       => (int)($settings_raw['carton_cell_height'] ?? 48),
    'header_height'     => (int)($settings_raw['carton_header_height'] ?? 36),
    'number_scale'      => (float)($settings_raw['carton_number_scale'] ?? 0.48),
    'header_scale'      => (float)($settings_raw['carton_header_scale'] ?? 0.60),
    'free_scale'        => (float)($settings_raw['carton_free_scale'] ?? 0.40),
    'cell_shape'        => $settings_raw['carton_cell_shape'] ?? 'square',
    'border_radius'     => (int)($settings_raw['carton_border_radius'] ?? 10),
    'header_bg_mode'    => $settings_raw['carton_header_bg_mode'] ?? 'solid',
    'header_grad_from'  => $settings_raw['carton_header_grad_from'] ?? '#3b82f6',
    'header_grad_to'    => $settings_raw['carton_header_grad_to'] ?? '#9333ea',
    'header_grad_dir'   => $settings_raw['carton_header_grad_dir'] ?? 'to right',
    'wrap_bg_mode'      => $settings_raw['carton_wrap_bg_mode'] ?? 'none',
    'wrap_bg_color'     => $settings_raw['carton_wrap_bg_color'] ?? '#ffffff',
    'wrap_grad_from'    => $settings_raw['carton_wrap_grad_from'] ?? '#ffffff',
    'wrap_grad_to'      => $settings_raw['carton_wrap_grad_to'] ?? '#ffffff',
    'wrap_grad_dir'     => $settings_raw['carton_wrap_grad_dir'] ?? 'to bottom',
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
  display:grid; grid-template-columns: 1.1fr 1fr; gap:14px; padding:10px;
}
@media (max-width: 1060px){ .customizer{ grid-template-columns: 1fr; } }
.customizer .panel{ background:#fff; border:1px solid var(--ui-border); border-radius:16px; box-shadow:var(--shadow-sm); }
.customizer .panel .inner{ padding:14px; }
.preview-box{
    background:#f8fafc; border:1px dashed var(--ui-border); border-radius:14px;
    display:flex; align-items:center; justify-content:center;
    padding:10px;
    height: 480px;
    overflow: hidden;
}
.preview-box iframe {
    width: 100%;
    height: 100%;
    border: none;
    background: transparent;
}


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

  <section class="card">
    <div class="block">
      <h3 style="margin-bottom:4px;">Ajustes de Moneda</h3>
      <div class="subtle" style="margin-bottom:10px;">
        La tasa se guarda como “1 USD = tasa Moneda” cuando la predeterminada no es USD. Los precios en USD se muestran en tiempo real con esta tasa.</div>
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
      <div class="toolbar" style="padding:0 10px 10px;">
        <button class="btn btn-primary" type="button" onclick="openNewCurrencyModal()">Agregar Moneda</button>
      </div>
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
        <div class="form-group">
          <label>Fondo del Encabezado</label>
          <div style="display:flex; gap:8px; margin-bottom:8px;">
            <label><input type="radio" name="header_bg_mode" value="solid" <?php echo $settings['header_bg_mode'] === 'solid' ? 'checked' : ''; ?>> Color sólido</label>
            <label><input type="radio" name="header_bg_mode" value="gradient" <?php echo $settings['header_bg_mode'] === 'gradient' ? 'checked' : ''; ?>> Degradado</label>
          </div>
          <div class="header-solid-opts" style="display: <?php echo $settings['header_bg_mode'] === 'solid' ? 'block' : 'none'; ?>;">
            <div class="form-group"><label for="header_color">Color principal</label><input type="color" id="header_color" value="<?php echo $settings['header_color']; ?>"></div>
          </div>
          <div class="header-grad-opts" style="display: <?php echo $settings['header_bg_mode'] === 'gradient' ? 'block' : 'none'; ?>;">
            <div class="form-group"><label for="header_grad_from">Degradado desde</label><input type="color" id="header_grad_from" value="<?php echo $settings['header_grad_from']; ?>"></div>
            <div class="form-group"><label for="header_grad_to">Degradado hasta</label><input type="color" id="header_grad_to" value="<?php echo $settings['header_grad_to']; ?>"></div>
            <div class="form-group">
              <label for="header_grad_dir">Dirección</label>
              <select id="header_grad_dir">
                <option value="to bottom" <?php echo $settings['header_grad_dir'] === 'to bottom' ? 'selected' : ''; ?>>Abajo</option>
                <option value="to top" <?php echo $settings['header_grad_dir'] === 'to top' ? 'selected' : ''; ?>>Arriba</option>
                <option value="to right" <?php echo $settings['header_grad_dir'] === 'to right' ? 'selected' : ''; ?>>Derecha</option>
                <option value="to left" <?php echo $settings['header_grad_dir'] === 'to left' ? 'selected' : ''; ?>>Izquierda</option>
              </select>
            </div>
          </div>
        </div>
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
        <div class="form-group">
          <label for="cell_width">Ancho de Celda (px)</label>
          <input type="number" id="cell_width" min="20" max="140" step="1" value="<?php echo (int)$settings['cell_width']; ?>">
          <small class="form-hint">Recomendado: 40-80 para email. El ajuste es directo en la vista previa.</small>
        </div>
        <div class="form-group">
          <label for="cell_height">Alto de Celda (px)</label>
          <input type="number" id="cell_height" min="20" max="140" step="1" value="<?php echo (int)$settings['cell_height']; ?>">
        </div>
        <div class="form-group">
          <label for="header_height">Alto del Encabezado (px)</label>
          <input type="number" id="header_height" min="20" max="100" step="1" value="<?php echo (int)$settings['header_height']; ?>">
        </div>
        <div class="form-group">
          <label for="number_scale">Escala de Números (0.28–0.60)</label>
          <input type="number" id="number_scale" min="0.28" max="0.60" step="0.01" value="<?php echo number_format((float)$settings['number_scale'], 2, '.', ''); ?>">
        </div>
        <div class="form-group">
          <label for="header_scale">Escala Letras BINGO (0.40–0.80)</label>
          <input type="number" id="header_scale" min="0.40" max="0.80" step="0.01" value="<?php echo number_format((float)$settings['header_scale'], 2, '.', ''); ?>">
        </div>
        <div class="form-group">
          <label for="free_scale">Escala Texto Celda Central (0.28–0.60)</label>
          <input type="number" id="free_scale" min="0.28" max="0.60" step="0.01" value="<?php echo number_format((float)$settings['free_scale'], 2, '.', ''); ?>">
        </div>
        <div class="form-group">
          <label for="cell_shape">Forma de la Celda</label>
          <select id="cell_shape">
            <option value="square" <?php echo $settings['cell_shape'] === 'square' ? 'selected' : ''; ?>>Cuadrado</option>
            <option value="circle" <?php echo $settings['cell_shape'] === 'circle' ? 'selected' : ''; ?>>Círculo</option>
            <option value="clover" <?php echo $settings['cell_shape'] === 'clover' ? 'selected' : ''; ?>>Trébol (requiere fuente Noto Color Emoji)</option>
            <option value="star" <?php echo $settings['cell_shape'] === 'star' ? 'selected' : ''; ?>>Estrella (requiere fuente Noto Color Emoji)</option>
          </select>
        </div>
        <div class="form-group">
          <label for="border_radius">Radio del Borde (px)</label>
          <input type="number" id="border_radius" min="0" max="50" step="1" value="<?php echo (int)$settings['border_radius']; ?>">
        </div>
        <hr style="margin:12px 0;">
        <h4 style="margin:0 0 8px;">Contenido Celda Central</h4>
        <div class="form-group"><label for="center_content">Texto o Emoji</label><textarea id="center_content" rows="2"><?php echo htmlspecialchars($settings['center_content']); ?></textarea></div>
        <div class="form-group"><label><input type="checkbox" id="center_is_free" <?php echo $settings['center_is_free'] === '1' ? 'checked' : ''; ?>> Habilitar centro libre</label></div>
        <div class="toolbar">
          <button class="btn btn-primary" id="save_settings" type="button">Guardar Cambios</button>
          <button class="btn btn-outline" id="send_test_email" type="button">Enviar cartón de prueba</button>
          <div id="save_status" class="form-hint"></div>
        </div>
      </div>
    </div>
    <div class="panel">
      <div class="inner">
        <h4 style="margin:0 0 8px;">Vista Previa</h4>
        <div class="preview-box">
            <iframe id="carton-preview-iframe" title="Vista Previa del Cartón de Bingo"></iframe>
        </div>
      </div>
    </div>
  </div>
</section>

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

<div class="modal-overlay" id="newCurrencyModal" aria-hidden="true" role="dialog" aria-labelledby="new-currency-title">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title" id="new-currency-title">Agregar Moneda</h3>
      <button class="modal-close" type="button" aria-label="Cerrar" onclick="closeNewCurrencyModal()">&times;</button>
    </div>
    <div class="modal-body">
      <form id="new-currency-form" onsubmit="return false;">
        <div class="form-group">
          <label for="new-currency-name">Nombre (Ej: Bolívar Soberano)</label>
          <input type="text" id="new-currency-name" required placeholder="Ej: Bolívar Soberano">
        </div>
        <div class="form-group">
          <label for="new-currency-code">Código (3 letras, ej: VES)</label>
          <input type="text" id="new-currency-code" required maxlength="3" placeholder="Ej: VES" style="text-transform:uppercase;">
        </div>
        <div class="form-group">
          <label for="new-currency-symbol">Símbolo (Ej: Bs.)</label>
          <input type="text" id="new-currency-symbol" required placeholder="Ej: Bs.">
        </div>
        <div class="form-group">
          <label for="new-currency-rate">Valor de 1 USD (Ej: 197.5000)</label>
          <input type="text" id="new-currency-rate" required inputmode="decimal" placeholder="Ej: 197.5000">
        </div>
        <div class="form-group">
          <label><input type="checkbox" id="new-currency-default"> Establecer como predeterminada</label>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" type="button" onclick="closeNewCurrencyModal()">Cancelar</button>
      <button class="btn btn-primary" type="button" id="new-currency-save">Crear</button>
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
  const ncm = document.getElementById('newCurrencyModal');
  if (e.target === pm) closeOverlay('pmModal');
  if (e.target === cm) closeOverlay('currencyModal');
  if (e.target === ncm) closeOverlay('newCurrencyModal');
});
document.addEventListener('keydown', (e)=>{
  if (e.key === 'Escape'){ closeOverlay('pmModal'); closeOverlay('currencyModal'); closeOverlay('newCurrencyModal'); }
});

/* ========== Métodos de pago (modal) ========== */
// ... [Mantén todo el código de métodos de pago igual hasta "Nueva Moneda"] ...

/* ========== Personalizador Vista Previa - VERSIÓN CORREGIDA ========== */
const PV = {
    header_color:      document.getElementById('header_color'),
    header_text_color: document.getElementById('header_text_color'),
    free_cell_color:   document.getElementById('free_cell_color'),
    free_text_color:   document.getElementById('free_text_color'),
    border_color:      document.getElementById('border_color'),
    number_color:      document.getElementById('number_color'),
    font:              document.getElementById('font'),
    grid_style:        document.getElementById('grid_style'),
    center_content:    document.getElementById('center_content'),
    center_is_free:    document.getElementById('center_is_free'),
    cell_width:        document.getElementById('cell_width'),
    cell_height:       document.getElementById('cell_height'),
    header_height:     document.getElementById('header_height'),
    number_scale:      document.getElementById('number_scale'),
    header_scale:      document.getElementById('header_scale'),
    free_scale:        document.getElementById('free_scale'),
    cell_shape:        document.getElementById('cell_shape'),
    border_radius:     document.getElementById('border_radius'),
    header_bg_mode:    document.querySelector('input[name="header_bg_mode"]:checked'),
    header_grad_from:  document.getElementById('header_grad_from'),
    header_grad_to:    document.getElementById('header_grad_to'),
    header_grad_dir:   document.getElementById('header_grad_dir'),
    previewIframe:     document.getElementById('carton-preview-iframe'),
};

let previewTimer = null;
let isPreviewLoading = false;

// ✅ INICIALIZAR dataset.lastValue al cargar
function initPreviewTracking() {
    PV.font.dataset.lastValue = PV.font.value;
    PV.grid_style.dataset.lastValue = PV.grid_style.value;
    PV.cell_shape.dataset.lastValue = PV.cell_shape.value;
    PV.center_is_free.dataset.lastValue = PV.center_is_free.checked.toString();
}

function getSettingsPayload() {
    return {
        header_color:      PV.header_color.value,
        header_text_color: PV.header_text_color.value,
        free_cell_color:   PV.free_cell_color.value,
        free_text_color:   PV.free_text_color.value,
        border_color:      PV.border_color.value,
        number_color:      PV.number_color.value,
        font:              PV.font.value,
        grid_style:        PV.grid_style.value,
        center_content:    PV.center_content.value,
        center_is_free:    PV.center_is_free.checked ? '1' : '0',
        cell_width:        PV.cell_width.value,
        cell_height:       PV.cell_height.value,
        header_height:     PV.header_height.value,
        number_scale:      PV.number_scale.value,
        header_scale:      PV.header_scale.value,
        free_scale:        PV.free_scale.value,
        cell_shape:        PV.cell_shape.value,
        border_radius:     PV.border_radius.value,
        header_bg_mode:    document.querySelector('input[name="header_bg_mode"]:checked').value,
        header_grad_from:  PV.header_grad_from.value,
        header_grad_to:    PV.header_grad_to.value,
        header_grad_dir:   PV.header_grad_dir.value,
    };
}

// ✅ MEJORADO: Aplica TODOS los estilos correctamente
function applyStylesToIframe(doc) {
    const payload = getSettingsPayload();
    const root = doc.documentElement; // ✅ Usar documentElement
    
    // CSS Variables
    root.style.setProperty('--header-color', payload.header_color);
    root.style.setProperty('--header-text-color', payload.header_text_color);
    root.style.setProperty('--free-cell-color', payload.free_cell_color);
    root.style.setProperty('--free-text-color', payload.free_text_color);
    root.style.setProperty('--number-color', payload.number_color);
    root.style.setProperty('--border-color', payload.border_color);
    root.style.setProperty('--cell-width', `${payload.cell_width}px`);
    root.style.setProperty('--cell-height', `${payload.cell_height}px`);
    root.style.setProperty('--header-height', `${payload.header_height}px`);
    root.style.setProperty('--number-scale', payload.number_scale);
    root.style.setProperty('--header-scale', payload.header_scale);
    root.style.setProperty('--free-scale', payload.free_scale);
    root.style.setProperty('--border-radius', `${payload.border_radius}px`);
    root.style.setProperty('--font-family', payload.font);
    root.style.setProperty('--grid-style', payload.grid_style);
    root.style.setProperty('--cell-shape', payload.cell_shape);

    // Header gradient
    const headerEl = doc.querySelector('.header-cell-container');
    if (headerEl) {
        if (payload.header_bg_mode === 'gradient') {
            headerEl.style.background = `linear-gradient(${payload.header_grad_dir}, ${payload.header_grad_from}, ${payload.header_grad_to})`;
        } else {
            headerEl.style.backgroundColor = payload.header_color;
        }
    }
    
    // Center content
    const centerCellEl = doc.querySelector('.grid-cell.free-cell');
    if (centerCellEl) {
        centerCellEl.textContent = payload.center_content;
    }

    // Cell shape
    doc.querySelectorAll('.grid-cell').forEach(cell => {
        if (payload.cell_shape === 'circle') {
            cell.style.borderRadius = '50%';
        } else {
            cell.style.borderRadius = `${payload.border_radius}px`;
        }
    });
}

// ✅ CARGAR PREVIEW INICIAL
function loadInitialPreview() {
    isPreviewLoading = true;
    const iframeDoc = PV.previewIframe.contentWindow.document;
    iframeDoc.open();
    iframeDoc.write('<div style="text-align:center; padding:24px; color:#2563eb; font-family:sans-serif; font-size:1.1rem;">🔄 Generando vista previa inicial...</div>');
    iframeDoc.close();
    
    fetchNewPreview();
}

// ✅ OPTIMIZADO: Solo 1 request cada 300ms
function queuePreview() {
    if (previewTimer) clearTimeout(previewTimer);
    
    previewTimer = setTimeout(() => {
        // Detectar cambios estructurales
        const isStructuralChange = 
            PV.font.value !== PV.font.dataset.lastValue || 
            PV.grid_style.value !== PV.grid_style.dataset.lastValue || 
            PV.cell_shape.value !== PV.cell_shape.dataset.lastValue ||
            PV.center_content.value !== PV.center_content.dataset.lastValue ||
            PV.center_is_free.checked !== (PV.center_is_free.dataset.lastValue === 'true');

        if (isStructuralChange) {
            fetchNewPreview();
            // Actualizar tracking
            PV.font.dataset.lastValue = PV.font.value;
            PV.grid_style.dataset.lastValue = PV.grid_style.value;
            PV.cell_shape.dataset.lastValue = PV.cell_shape.value;
            PV.center_content.dataset.lastValue = PV.center_content.value;
            PV.center_is_free.dataset.lastValue = PV.center_is_free.checked.toString();
        } else {
            // Cambios de estilo inmediato
            applyStylesToIframe(PV.previewIframe.contentWindow.document);
        }
    }, 300);
}

// ✅ MEJORADO: Fetch con mejor manejo de errores
function fetchNewPreview() {
    if (isPreviewLoading) return;
    isPreviewLoading = true;
    
    const payload = getSettingsPayload();
    const iframeDoc = PV.previewIframe.contentWindow.document;

    iframeDoc.open();
    iframeDoc.write('<div style="text-align:center; padding:24px; color:#2563eb; font-family:sans-serif;">🔄 Actualizando...</div>');
    iframeDoc.close();

    fetch('api.php?action=preview_carton', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.html) {
            renderIframeContent(data.html);
        } else {
            throw new Error(data.message || 'Error en preview');
        }
    })
    .catch(err => {
        console.error('Preview error:', err);
        iframeDoc.open();
        iframeDoc.write('<div style="text-align:center; padding:24px; color:#ef4444; font-family:sans-serif;">⚠️ Error al cargar preview<br><small>Revisa la consola</small></div>');
        iframeDoc.close();
        notify('Error en vista previa. Revisa la consola.', 'error');
    })
    .finally(() => { isPreviewLoading = false; });
}

function renderIframeContent(htmlContent) {
    const iframeDoc = PV.previewIframe.contentWindow.document;
    const payload = getSettingsPayload();

    let fontsHtml = '';
    if (payload.font && !['Arial', 'Times New Roman', 'Courier New'].includes(payload.font)) {
        fontsHtml += `<link href="https://fonts.googleapis.com/css2?family=${encodeURIComponent(payload.font)}:wght@400;700;900&display=swap" rel="stylesheet">`;
    }
    if (payload.cell_shape === 'clover' || payload.cell_shape === 'star' || /[\u{1F300}-\u{1F6FF}\u{1F900}-\u{1F9FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}]/u.test(payload.center_content)) {
        fontsHtml += `<link href="https://fonts.googleapis.com/css2?family=Noto+Color+Emoji&display=swap" rel="stylesheet">`;
    }

    iframeDoc.open();
    iframeDoc.write(`<!DOCTYPE html><html><head><meta charset="utf-8">${fontsHtml}<style>
        body{margin:0;padding:0;display:flex;justify-content:center;align-items:center;min-height:100vh;background:#f8fafc;}
        .preview-container{max-width:100%;height:auto;}
        .bingo-card{transform-origin:top left;}
    </style></head><body><div class="preview-container">${htmlContent}</div></body></html>`);
    iframeDoc.close();

    // Aplicar estilos después del render
    setTimeout(() => applyStylesToIframe(iframeDoc), 150);
}

// ✅ EVENTOS - UNA SOLA VEZ
function initEvents() {
    // Radio buttons header
    document.querySelectorAll('input[name="header_bg_mode"]').forEach(el => {
        el.addEventListener('change', () => {
            document.querySelector('.header-solid-opts').style.display = el.value === 'solid' ? 'block' : 'none';
            document.querySelector('.header-grad-opts').style.display = el.value === 'gradient' ? 'block' : 'none';
            queuePreview();
        });
    });

    // Todos los inputs/selects/textarea
    document.querySelectorAll('.customizer input, .customizer select, .customizer textarea').forEach(el => {
        let timeout;
        el.addEventListener('input', () => {
            clearTimeout(timeout);
            timeout = setTimeout(queuePreview, 200);
        });
        el.addEventListener('change', queuePreview);
    });
}

// ✅ INICIALIZACIÓN AL CARGA
document.addEventListener('DOMContentLoaded', () => {
    initPreviewTracking();
    initEvents();
    loadInitialPreview(); // Cargar preview inicial
});

// Fallback si DOM ya está listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initPreviewTracking();
        initEvents();
        loadInitialPreview();
    });
} else {
    initPreviewTracking();
    initEvents();
    loadInitialPreview();
}

// ✅ Botones guardar y email (sin cambios)
document.getElementById('save_settings')?.addEventListener('click', async ()=>{
    const fd = new FormData();
    const payload = getSettingsPayload();
    for (const key in payload) fd.append(key, payload[key]);

    const btn = document.getElementById('save_settings');
    const original = btn.textContent; btn.textContent='Guardando...'; btn.disabled=true;
    try {
        await postForm('api.php?action=save_settings', fd);
        notify('Ajustes guardados ✔');
        document.getElementById('save_status').textContent = 'Guardado ✔';
        setTimeout(()=> document.getElementById('save_status').textContent = '', 2000);
    } catch(err){ notify(err.message, 'error'); }
    finally { btn.textContent=original; btn.disabled=false; }
});

document.getElementById('send_test_email')?.addEventListener('click', async ()=>{
    const btn = document.getElementById('send_test_email');
    const original = btn.textContent; btn.textContent='Enviando...'; btn.disabled=true;
    try {
        const fd = new FormData();
        const payload = getSettingsPayload();
        for (const key in payload) fd.append(key, payload[key]);
        
        const email = prompt('Correo para prueba:', '<?php echo htmlspecialchars($current_user['email'] ?? ''); ?>');
        if (email) {
            fd.append('test_email', email);
            await postForm('api.php?action=send_test_card', fd);
            notify('¡Cartón enviado!');
        }
    } catch(err){ notify(err.message, 'error'); }
    finally { btn.textContent=original; btn.disabled=false; }
});
</script>

<?php
require_once 'includes/footer.php';
?>