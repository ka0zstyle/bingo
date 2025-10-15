<?php
$page_title = 'Ajustes Generales';
require_once 'includes/header.php';

// --- INICIO DEL BLOQUE DE SEGURIDAD DE ROL ---
if ($current_role !== 'admin') {
    echo '<div class="admin-section"><p><strong>Acceso denegado.</strong> No tienes los permisos necesarios para ver esta página.</p></div>';
    require_once 'includes/footer.php';
    exit;
}

$pdo = get_db_connection();

$stmt_settings = $pdo->query("SELECT * FROM bingo_settings");
$settings_raw = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
$settings = [
    'header_color' => $settings_raw['carton_header_color'] ?? '#c82828',
    'header_text_color' => $settings_raw['carton_header_text_color'] ?? '#ffffff',
    'free_cell_color' => $settings_raw['carton_free_cell_color'] ?? '#ffc107',
    'free_text_color' => $settings_raw['carton_free_text_color'] ?? '#9c4221',
    'font' => $settings_raw['carton_font'] ?? 'Poppins',
    'center_content' => $settings_raw['carton_center_content'] ?? 'FREE',
    'center_is_free' => $settings_raw['carton_center_is_free'] ?? '1',
    'grid_style' => $settings_raw['carton_grid_style'] ?? 'solid',
    'border_color' => $settings_raw['carton_border_color'] ?? '#e2e8f0',
    'number_color' => $settings_raw['carton_number_color'] ?? '#2d3748',
    'show_decimals' => $settings_raw['show_decimals'] ?? '1',
];

$payment_methods = $pdo->query("SELECT * FROM payment_methods ORDER BY name ASC")->fetchAll();
$currencies = $pdo->query("SELECT * FROM currencies ORDER BY name ASC")->fetchAll();
?>

<style>
:root{
    --ui-primary:#2563eb;
    --ui-primary-600:#1d4ed8;
    --ui-muted:#64748b;
    --ui-card:#ffffff;
    --ui-border:#e2e8f0;
    --ui-danger:#ef4444;
    --ui-success:#10b981;
    --radius:14px;
    --shadow-lg:0 12px 28px rgba(2,8,23,.08);
    --shadow-sm:0 4px 12px rgba(2,8,23,.06);
}
/* Hero */
.settings-hero{
    background:
      radial-gradient(1200px 600px at 10% -10%, rgba(255,255,255,.28), transparent 60%) no-repeat,
      linear-gradient(135deg,#3b82f6 0%, #9333ea 100%);
    border-radius: 20px;
    padding: 22px 20px;
    box-shadow: var(--shadow-lg);
    color:#fff;
    margin-bottom: 18px;
}
.settings-hero h2{ margin:0; font-size:1.6rem; font-weight:800; }
.settings-hero p{ margin:.25rem 0 0; opacity:.95; }

/* Grid principal */
.settings-grid{
    display:grid; grid-template-columns: repeat(2, minmax(320px, 1fr)); gap:18px;
}
@media (max-width: 1080px){ .settings-grid{ grid-template-columns: 1fr; } }

/* Card base */
.card{
    background:var(--ui-card); border:1px solid var(--ui-border);
    border-radius:16px; box-shadow: var(--shadow-sm);
}
.card .block{ padding:18px; }
.card h3{ margin:0 0 10px; font-size:1.2rem; font-weight:800; color:#0f172a; }

/* Formulario base */
.form-group{ margin-bottom:12px; }
.form-group label{ display:block; font-weight:700; margin-bottom:8px; color:#111827; }
.form-group input[type="text"],
.form-group input[type="number"],
.form-group input[type="color"],
.form-group textarea,
.form-group select{
    width:100%; padding:12px 14px; border-radius:12px; border:1px solid var(--ui-border); background:#fff;
    font-size:.95rem; outline:none; transition:border-color .15s, box-shadow .15s;
}
.form-group textarea{ min-height:96px; resize:vertical; }
.form-group input:focus, .form-group textarea:focus, .form-group select:focus{
    border-color:#7c3aed; box-shadow:0 0 0 3px rgba(124,58,237,.14);
}

/* Botones */
.btn-primary, .btn-secondary, .btn-danger{
    border:none; cursor:pointer; font-weight:800; border-radius:10px; padding:11px 14px; font-size:.92rem;
    transition: background .15s ease, transform .05s ease, box-shadow .15s ease;
}
.btn-primary{ background:var(--ui-primary); color:#fff; }
.btn-primary:hover{ background:var(--ui-primary-600); transform: translateY(-1px); }
.btn-secondary{ background:#111827; color:#fff; }
.btn-secondary:hover{ background:#0b1220; transform: translateY(-1px); }
.btn-danger{ background:var(--ui-danger); color:#fff; }
.btn-danger:hover{ background:#dc2626; transform: translateY(-1px); }

/* Tablas responsive sin scroll horizontal */
.table-wrap{ padding:14px; }
.table{ width:100%; border-collapse:separate; border-spacing:0; table-layout: fixed; }
.table thead th{
    background:#f8fafc; font-weight:800; color:#0f172a; font-size:.85rem; letter-spacing:.02em;
    border-bottom:1px solid var(--ui-border); padding:12px; white-space:nowrap;
}
.table tbody td{
    border-bottom:1px solid var(--ui-border); padding:14px 12px; vertical-align:middle; background:#fff; word-break: break-word;
}
.table tbody tr:last-child td{ border-bottom:none; }
@media (max-width: 1024px){
    .table thead{ display:none; }
    .table, .table tbody, .table tr, .table td{ display:block; width:100%; }
    .table tbody tr{
        margin-bottom:14px; border:1px solid var(--ui-border); border-radius:12px; overflow:hidden; box-shadow: var(--shadow-sm);
        background:#fff;
    }
    .table tbody td{
        display:flex; align-items:flex-start; gap:12px; padding:12px 14px; border-bottom:1px solid var(--ui-border);
    }
    .table tbody td:last-child{ border-bottom:none; }
    .table tbody td::before{
        content: attr(data-label);
        flex: 0 0 40%;
        max-width: 40%;
        font-weight:800; color:#64748b;
    }
}

/* Personalizador */
.customizer{
    display:grid; grid-template-columns: 360px 1fr; gap:18px; padding:14px;
}
@media (max-width: 1060px){ .customizer{ grid-template-columns: 1fr; } }
.customizer .panel{ background:#fff; border:1px solid var(--ui-border); border-radius:16px; box-shadow:var(--shadow-sm); }
.customizer .panel .inner{ padding:18px; }
.preview-box{ background:#f8fafc; border:1px dashed var(--ui-border); border-radius:14px; min-height:280px; display:flex; align-items:center; justify-content:center; }

/* Cartón de vista previa */
.preview-card{
    width: min(520px, 100%);
    background:#fff;
    border-radius:12px;
    box-shadow: var(--shadow-sm);
    overflow:hidden;
    border:1px solid var(--ui-border);
    font-family: Poppins, Arial, sans-serif;
}

/* Encabezado B I N G O como grilla de 5 columnas, alineado con la grilla inferior */
.preview-card .header{
    display:grid;
    grid-template-columns: repeat(5, 1fr);
    text-transform: uppercase;
    font-weight: 900;
    letter-spacing:.5px;
}
.preview-card .header .hcell{
    padding:10px 0;
    text-align:center;
    /* Los bordes de cada hcell se asignan inline desde JS para igualar el estilo del grid */
}

/* Grilla del cuerpo */
.preview-card .grid{
    display:grid;
    grid-template-columns: repeat(5, 1fr);
}
.preview-card .cell{
    padding:12px 0;
    text-align:center;
    font-weight:700;
    font-size:1rem;
    background:#fff;
}
.preview-card .cell.free{
    font-weight:900;
    text-transform:uppercase;
}

/* Toasts */
.toasts{ position:fixed; right:16px; bottom:16px; display:flex; flex-direction:column; gap:10px; z-index:9999; }
.toast{
    background:#111827; color:#fff; padding:10px 12px; border-radius:10px; box-shadow: var(--shadow-lg); font-weight:600;
    display:flex; align-items:center; gap:10px; min-width:240px;
}
.toast.success{ background:#065f46; }
.toast.error{ background:#991b1b; }
</style>

<div class="settings-hero">
    <h2>Ajustes Generales</h2>
    <p>Administra métodos de pago, monedas y personaliza el diseño de los cartones.</p>
</div>

<div class="settings-grid">
    <section class="card">
        <div class="block">
            <h3>Métodos de Pago</h3>
            <form id="payment-method-form" autocomplete="off" novalidate onsubmit="return false;">
                <input type="hidden" name="id" id="pm-id">
                <div class="form-group">
                    <label for="pm-name">Nombre (Ej: Zelle)</label>
                    <input type="text" id="pm-name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="pm-details">Instrucciones / Datos</label>
                    <textarea id="pm-details" name="details" rows="4" placeholder="CI: V-123, Teléfono: 0412..., Banco: ..."></textarea>
                    <small>Formato: "CI: V-123, Teléfono: 0412...". Cada valor tras ":" tendrá botón copiar.</small>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button type="button" class="btn-primary" id="pm-save-btn">Guardar Método</button>
                    <button type="button" id="pm-clear-btn" class="btn-danger" style="display:none;">Cancelar Edición</button>
                </div>
            </form>
        </div>
        <div class="table-wrap">
            <h3 style="margin:0 0 10px;">Métodos Existentes</h3>
            <table class="table">
                <thead><tr><th>Nombre</th><th>Estado</th><th>Acción</th></tr></thead>
                <tbody>
                    <?php foreach ($payment_methods as $pm): ?>
                    <tr>
                        <td data-label="Nombre"><?php echo htmlspecialchars(htmlspecialchars_decode($pm['name'])); ?></td>
                        <td data-label="Estado"><?php echo $pm['is_active'] ? 'Activo' : 'Inactivo'; ?></td>
                        <td data-label="Acción" style="display:flex; gap:8px; flex-wrap:wrap;">
                            <button class="btn-secondary" onclick="editPaymentMethod(<?php echo htmlspecialchars(json_encode($pm, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)">Editar</button>
                            <button class="btn-danger" onclick="togglePaymentMethodStatus(<?php echo (int)$pm['id']; ?>, <?php echo $pm['is_active'] ? '0' : '1'; ?>)"><?php echo $pm['is_active'] ? 'Desactivar' : 'Activar'; ?></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="block">
            <h3>Ajustes de Moneda</h3>
            <form id="currency-settings-form" autocomplete="off" novalidate onsubmit="return false;">
                <div class="form-group">
                    <label for="show_decimals">Formato de Precios Públicos</label>
                    <select id="show_decimals" name="show_decimals">
                        <option value="1" <?php echo $settings['show_decimals'] == '1' ? 'selected' : ''; ?>>Mostrar decimales (Ej: Bs. 10,00)</option>
                        <option value="0" <?php echo $settings['show_decimals'] == '0' ? 'selected' : ''; ?>>Ocultar decimales (Ej: Bs. 10)</option>
                    </select>
                </div>
                <button type="button" class="btn-primary" id="currency-display-save-btn">Guardar Formato</button>
            </form>
        </div>
        <div class="table-wrap">
            <h3 style="margin:0 0 10px;">Gestionar Monedas</h3>
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
                        </td>
                        <td data-label="Tasa Actual">
                            <?php 
                            if ($currency['code'] === 'USD') {
                                echo '1.00 (Moneda Base)';
                            } else {
                                echo '1 USD = ' . number_format(1 / $currency['rate'], 4) . ' ' . $currency['code'];
                            }
                            ?>
                        </td>
                        <td data-label="Acción" style="display:flex; gap:8px; flex-wrap:wrap;">
                            <?php if (!$currency['is_default']): ?>
                                <button class="btn-secondary" onclick="setDefaultCurrency(<?php echo (int)$currency['id']; ?>)">Hacer Default</button>
                            <?php endif; ?>
                            <?php if ($currency['code'] !== 'USD'): ?>
                                <button class="btn-primary" onclick="editCurrency(<?php echo htmlspecialchars(json_encode($currency, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)">Editar Tasa</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="no-border" style="padding:0;">
                            <div class="block" style="display:none;" id="currency-form-wrap">
                                <h3 style="margin-top:0;">Editar Tasa de Moneda</h3>
                                <form id="currency-form" style="padding:0;" onsubmit="return false;">
                                    <input type="hidden" name="id" id="currency-id">
                                    <div class="form-group">
                                        <label for="currency-rate-inverse">Nuevo valor de 1 USD (Ej: 36.5)</label>
                                        <input type="text" id="currency-rate-inverse" name="rate_inverse" required>
                                        <small class="form-hint">Usa el punto como separador decimal.</small>
                                    </div>
                                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                        <button type="button" class="btn-primary" id="currency-save-btn">Guardar Nueva Tasa</button>
                                        <button type="button" class="btn-danger" onclick="clearCurrencyForm()">Cancelar</button>
                                    </div>
                                </form>
                            </div>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
</div>

<section class="card" style="margin-top:18px;">
    <div class="block">
        <h3>Personalizador de Cartones</h3>
    </div>
    <div class="customizer">
        <div class="panel">
            <div class="inner">
                <h4 style="margin:0 0 10px;">Estilos y Colores</h4>
                <div class="form-group"><label for="header_color">Color Encabezado</label><input type="color" id="header_color" value="<?php echo $settings['header_color']; ?>"></div>
                <div class="form-group"><label for="header_text_color">Color Texto Encabezado</label><input type="color" id="header_text_color" value="<?php echo $settings['header_text_color']; ?>"></div>
                <div class="form-group"><label for="free_cell_color">Color Celda "Libre"</label><input type="color" id="free_cell_color" value="<?php echo $settings['free_cell_color']; ?>"></div>
                <div class="form-group"><label for="free_text_color">Color Texto "Libre"</label><input type="color" id="free_text_color" value="<?php echo $settings['free_text_color']; ?>"></div>
                <div class="form-group"><label for="number_color">Color de Números</label><input type="color" id="number_color" value="<?php echo $settings['number_color']; ?>"></div>
                <div class="form-group"><label for="border_color">Color de Bordes</label><input type="color" id="border_color" value="<?php echo $settings['border_color']; ?>"></div>
                <hr>
                <h4 style="margin:10px 0;">Diseño y Tipografía</h4>
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
                <hr>
                <h4 style="margin:10px 0;">Contenido Celda Central</h4>
                <div class="form-group"><label for="center_content">Texto o Emoji</label><textarea id="center_content" rows="2"><?php echo htmlspecialchars($settings['center_content']); ?></textarea></div>
                <div class="form-group"><label><input type="checkbox" id="center_is_free" <?php echo $settings['center_is_free'] === '1' ? 'checked' : ''; ?>> Habilitar centro libre</label></div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button class="btn-primary" id="save_settings" type="button">Guardar Cambios</button>
                    <div id="save_status" class="form-hint"></div>
                </div>
            </div>
        </div>
        <div class="panel">
            <div class="inner">
                <h4 style="margin:0 0 10px;">Vista Previa</h4>
                <div class="preview-box" id="carton-preview-container">
                    </div>
            </div>
        </div>
    </div>
</section>

<div class="toasts" id="toasts"></div>

<script>
// CSRF desde PHP
const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';

// Toasts
function notify(message, type='success'){
    const c = document.getElementById('toasts');
    const el = document.createElement('div');
    el.className = 'toast ' + (type === 'error' ? 'error':'success');
    el.textContent = message;
    c.appendChild(el);
    setTimeout(()=>{ el.style.opacity='0'; el.style.transform='translateY(4px)'; }, 2500);
    setTimeout(()=>{ el.remove(); }, 3200);
}

// Helper fetch
async function postForm(url, formData) {
    // Asegurar que el token CSRF siempre esté en el cuerpo de la solicitud
    formData.append('csrf_token', CSRF_TOKEN);
    const res = await fetch(url, { method:'POST', body: formData });
    let data = {};
    try { data = await res.json(); } catch(e){}
    if (!res.ok || !data.success) throw new Error(data.message || 'Operación fallida.');
    return data;
}

/* ==========
   MÉTODOS DE PAGO (sin submit nativo)
   ========== */
const pmSaveBtn  = document.getElementById('pm-save-btn');
const pmClearBtn = document.getElementById('pm-clear-btn');

pmSaveBtn?.addEventListener('click', async (ev) => {
    ev.preventDefault(); ev.stopPropagation(); ev.stopImmediatePropagation();
    const id = document.getElementById('pm-id').value.trim();
    const name = document.getElementById('pm-name').value.trim();
    const details = document.getElementById('pm-details').value.trim();
    if (!name) return notify('El nombre del método es obligatorio.', 'error');

    const fd = new FormData();
    if (id) fd.append('id', id);
    fd.append('name', name);
    fd.append('details', details);
    // CSRF TOKEN se agrega en la función postForm

    const original = pmSaveBtn.textContent; pmSaveBtn.textContent='Guardando...'; pmSaveBtn.disabled=true;
    try {
        await postForm('api.php?action=save_payment_method', fd);
        notify('Método guardado.');
        setTimeout(()=> window.location.reload(), 600);
    } catch(err){ notify(err.message, 'error'); }
    finally { pmSaveBtn.textContent=original; pmSaveBtn.disabled=false; }
});

pmClearBtn?.addEventListener('click', () => {
    document.getElementById('pm-id').value = '';
    document.getElementById('pm-name').value = '';
    document.getElementById('pm-details').value = '';
    pmClearBtn.style.display = 'none';
});

function editPaymentMethod(pm){
    try {
        const data = typeof pm === 'string' ? JSON.parse(pm) : pm;
        document.getElementById('pm-id').value   = data.id || '';
        document.getElementById('pm-name').value = (data.name || '').replace(/&amp;/g,'&');
        document.getElementById('pm-details').value = (data.details || '').replace(/&amp;/g,'&');
        pmClearBtn.style.display = 'inline-block';
        document.getElementById('pm-name').focus();
    } catch(e){ console.error(e); notify('No se pudo cargar el método.', 'error'); }
}

async function togglePaymentMethodStatus(id, active){
    if (!confirm(`¿Seguro que deseas ${active ? 'activar' : 'desactivar'} este método?`)) return;
    const fd = new FormData();
    fd.append('id', id);
    fd.append('is_active', active ? 1 : 0);
    // CSRF TOKEN se agrega en la función postForm
    try {
        await postForm('api.php?action=toggle_payment_method_status', fd);
        notify('Estado actualizado.');
        setTimeout(()=> window.location.reload(), 500);
    } catch(err){ notify(err.message, 'error'); }
}

/* ==========
   FORMATO DE PRECIOS
   ========== */
const currencyDisplayBtn = document.getElementById('currency-display-save-btn');
currencyDisplayBtn?.addEventListener('click', async (ev)=>{
    ev.preventDefault(); ev.stopPropagation(); ev.stopImmediatePropagation();
    const show = document.getElementById('show_decimals').value;
    const fd = new FormData();
    fd.append('show_decimals', show);
    // CSRF TOKEN se agrega en la función postForm
    const original = currencyDisplayBtn.textContent; currencyDisplayBtn.textContent='Guardando...'; currencyDisplayBtn.disabled=true;
    try {
        await postForm('api.php?action=save_currency_settings', fd);
        notify('Formato guardado.');
    } catch(err){ notify(err.message, 'error'); }
    finally { currencyDisplayBtn.textContent=original; currencyDisplayBtn.disabled=false; }
});

/* ==========
   MONEDAS
   ========== */
function setDefaultCurrency(id){
    if (!confirm('¿Hacer esta moneda la predeterminada?')) return;
    const fd = new FormData();
    fd.append('id', id);
    // CSRF TOKEN se agrega en la función postForm
    postForm('api.php?action=set_default_currency', fd)
      .then(()=>{ notify('Moneda predeterminada actualizada.'); setTimeout(()=> window.location.reload(), 500); })
      .catch(err=> notify(err.message, 'error'));
}

function editCurrency(currency){
    try{
        const data = typeof currency === 'string' ? JSON.parse(currency) : currency;
        document.getElementById('currency-id').value = data.id || '';
        document.getElementById('currency-rate-inverse').value = (data.rate && data.rate > 0) ? (1 / data.rate).toFixed(4) : '';
        document.getElementById('currency-form-wrap').style.display = 'block';
        document.getElementById('currency-rate-inverse').focus();
    }catch(e){ console.error(e); notify('No se pudo abrir el editor de tasa.', 'error'); }
}
function clearCurrencyForm(){
    document.getElementById('currency-form-wrap').style.display = 'none';
    document.getElementById('currency-form')?.reset();
}

const currencySaveBtn = document.getElementById('currency-save-btn');
currencySaveBtn?.addEventListener('click', async (ev)=>{
    ev.preventDefault(); ev.stopPropagation(); ev.stopImmediatePropagation();
    const id  = document.getElementById('currency-id').value.trim();
    const inv = document.getElementById('currency-rate-inverse').value.trim();
    if (!id || !inv || isNaN(inv)) return notify('Ingresa un valor numérico válido para 1 USD.', 'error');

    const fd = new FormData();
    fd.append('id', id);
    fd.append('rate_inverse', inv);
    // CSRF TOKEN se agrega en la función postForm

    const original = currencySaveBtn.textContent; currencySaveBtn.textContent='Guardando...'; currencySaveBtn.disabled=true;
    try {
        await postForm('api.php?action=save_currency', fd);
        notify('Tasa actualizada.');
        setTimeout(()=> window.location.reload(), 600);
    } catch(err){ notify(err.message, 'error'); }
    finally { currencySaveBtn.textContent=original; currencySaveBtn.disabled=false; }
});

/* ==========
   PERSONALIZADOR DE CARTONES: Vista previa en vivo
   ========== */
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

    // Encabezado: 5 celdas alineadas con columnas; sin texto "Vista previa"
    let headerHtml = `<div class="header" style="background:${cfg.headerBg}; color:${cfg.headerFg};">`;
    for (let i = 0; i < 5; i++){
        headerHtml += `<div class="hcell" style="${headerCellBorderInline(cfg)}">${headers[i]}</div>`;
    }
    headerHtml += `</div>`;

    // Cuerpo de la grilla 5x5
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

    const html = `<div class="preview-card" style="font-family:${cfg.font}, Poppins, Arial, sans-serif; border-color:${cfg.border};">
        ${headerHtml}
        ${gridHtml}
    </div>`;
    els.previewBox.innerHTML = html;
}

function gridBordersInline(cfg){
    // Para 'minimalist' solo líneas horizontales
    if (cfg.grid === 'minimalist') {
        return `border-top:1px ${cfg.grid} ${cfg.border}; border-bottom:1px ${cfg.grid} ${cfg.border};`;
    }
    return `border-left:1px ${cfg.grid} ${cfg.border}; border-right:1px ${cfg.grid} ${cfg.border};`;
}
function cellBorderInline(cfg){
    if (cfg.grid === 'minimalist') {
        return `border-top:1px solid ${cfg.border};`;
    }
    return `border:1px ${cfg.grid} ${cfg.border};`;
}
// Bordes de cada celda del encabezado, para que coincidan con el estilo del grid
function headerCellBorderInline(cfg){
    if (cfg.grid === 'minimalist') {
        // Solo una línea inferior para separar encabezado de la grilla
        return `border-bottom:1px solid ${cfg.border};`;
    }
    // Mismo estilo que las celdas: las verticales quedarán alineadas
    return `border:1px ${cfg.grid} ${cfg.border};`;
}
function sampleNumber(r,c){
    // Solo demostración, no importa el rango real
    return (r*5 + c + 1).toString().padStart(2,'0');
}
function escapeHtml(s){ return s.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

// Redibujar al cambiar cualquier control
['input','change','keyup'].forEach(evt=>{
    [
        els.headerColor, els.headerText, els.freeCell, els.freeText,
        els.numColor, els.border, els.font, els.grid, els.centerText, els.centerFree
    ].forEach(el => el?.addEventListener(evt, drawPreview));
});

// Primera carga
drawPreview();

// Guardar ajustes del cartón
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
    // CSRF TOKEN se agrega en la función postForm

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