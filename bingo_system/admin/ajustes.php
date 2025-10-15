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

$payment_methods = $pdo->query("SELECT * FROM bingo_payment_methods ORDER BY name ASC")->fetchAll();
$currencies = $pdo->query("SELECT * FROM bingo_currencies ORDER BY name ASC")->fetchAll();
?>

<style>
/* estilos omitidos por brevedad (deja los que ya tienes) */
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
                                // rate almacenado como "1 USD = rate <code>"
                                echo '1 USD = ' . number_format((float)$currency['rate'], 4) . ' ' . $currency['code'];
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
                                        <label for="currency-rate">Nuevo valor de 1 USD (Ej: 197.5)</label>
                                        <input type="text" id="currency-rate" name="rate" required>
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
    <!-- Panel del personalizador: deja tu HTML/CSS/JS como ya lo tenías -->
</section>

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

/* Métodos de pago */
const pmSaveBtn  = document.getElementById('pm-save-btn');
const pmClearBtn = document.getElementById('pm-clear-btn');

pmSaveBtn?.addEventListener('click', async (ev) => {
    ev.preventDefault();
    const id = document.getElementById('pm-id').value.trim();
    const name = document.getElementById('pm-name').value.trim();
    const details = document.getElementById('pm-details').value.trim();
    if (!name) return notify('El nombre del método es obligatorio.', 'error');

    const fd = new FormData();
    if (id) fd.append('id', id);
    fd.append('name', name);
    fd.append('details', details);

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
    try {
        await postForm('api.php?action=toggle_payment_method_status', fd);
        notify('Estado actualizado.');
        setTimeout(()=> window.location.reload(), 500);
    } catch(err){ notify(err.message, 'error'); }
}

/* Formato público de precios */
const currencyDisplayBtn = document.getElementById('currency-display-save-btn');
currencyDisplayBtn?.addEventListener('click', async (ev)=>{
    ev.preventDefault();
    const show = document.getElementById('show_decimals').value;
    const fd = new FormData();
    fd.append('show_decimals', show);

    const original = currencyDisplayBtn.textContent; currencyDisplayBtn.textContent='Guardando...'; currencyDisplayBtn.disabled=true;
    try {
        await postForm('api.php?action=save_currency_settings', fd);
        notify('Formato guardado.');
    } catch(err){ notify(err.message, 'error'); }
    finally { currencyDisplayBtn.textContent=original; currencyDisplayBtn.disabled=false; }
});

/* Monedas */
function setDefaultCurrency(id){
    if (!confirm('¿Hacer esta moneda la predeterminada?')) return;
    const fd = new FormData();
    fd.append('id', id);
    postForm('api.php?action=set_default_currency', fd)
      .then(()=>{ notify('Moneda predeterminada actualizada.'); setTimeout(()=> window.location.reload(), 500); })
      .catch(err=> notify(err.message, 'error'));
}

function editCurrency(currency){
    try{
        const data = typeof currency === 'string' ? JSON.parse(currency) : currency;
        document.getElementById('currency-id').value = data.id || '';
        document.getElementById('currency-rate').value = data.rate || '';
        document.getElementById('currency-form-wrap').style.display = 'block';
        document.getElementById('currency-rate').focus();
    }catch(e){ console.error(e); notify('No se pudo abrir el editor de tasa.', 'error'); }
}
function clearCurrencyForm(){
    document.getElementById('currency-form-wrap').style.display = 'none';
    document.getElementById('currency-form')?.reset();
}
const currencySaveBtn = document.getElementById('currency-save-btn');
currencySaveBtn?.addEventListener('click', async (ev)=>{
    ev.preventDefault();
    const id  = document.getElementById('currency-id').value.trim();
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
</script>

<?php
require_once 'includes/footer.php';
?>