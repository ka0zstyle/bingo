<?php
$page_title = 'Gestión de Usuarios';
require_once 'includes/header.php';

// --- INICIO DEL BLOQUE DE SEGURIDAD DE ROL ---
if ($current_role !== 'admin') {
    echo '<div class="admin-section"><p><strong>Acceso denegado.</strong> No tienes los permisos necesarios para ver esta página.</p></div>';
    require_once 'includes/footer.php';
    exit;
}

$pdo = get_db_connection();
$stmt = $pdo->query("
    SELECT u.id, u.username, u.role, u.active, u.created_at, a.username AS leader_name
    FROM bingo_admin_users u
    LEFT JOIN bingo_admin_users a ON u.team_leader_id = a.id
    ORDER BY u.created_at DESC
");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
$admins = $pdo->query("SELECT id, username FROM bingo_admin_users WHERE role='admin' AND active=1")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
:root{
    --ui-primary:#2563eb;
    --ui-primary-600:#1d4ed8;
    --ui-muted:#64748b;
    --ui-card:#ffffff;
    --ui-border:#e2e8f0;
    --ui-danger:#ef4444;
    --radius:14px;
    --shadow-lg:0 12px 28px rgba(2,8,23,.08);
    --shadow-sm:0 4px 12px rgba(2,8,23,.06);
}

/* Hero */
.users-hero{
    background:
      radial-gradient(1200px 600px at 10% -10%, rgba(255,255,255,.28), transparent 60%) no-repeat,
      linear-gradient(135deg,#3b82f6 0%, #7c3aed 100%);
    border-radius: 20px;
    padding: 22px 20px;
    box-shadow: var(--shadow-lg);
    color:#fff;
    margin-bottom: 18px;
}
.users-hero h2{ margin:0; font-size:1.6rem; font-weight:800; }
.users-hero p{ margin:.25rem 0 0; opacity:.95; }

/* Grid: formulario + tabla */
.users-grid{
    display:grid; grid-template-columns: 380px 1fr; gap:18px;
}
@media (max-width: 1060px){ .users-grid{ grid-template-columns: 1fr; } }

/* Card + formulario */
.card{ background:var(--ui-card); border:1px solid var(--ui-border); border-radius:16px; box-shadow:var(--shadow-sm); }
.card .block{ padding:18px; }
.card h3{ margin:0 0 10px; font-size:1.2rem; font-weight:800; color:#0f172a; }

.form-group{ margin-bottom:12px; }
.form-group label{ display:block; font-weight:700; margin-bottom:8px; color:#111827; }
.form-group input, .form-group select{
    width:100%; padding:12px 14px; border-radius:12px; border:1px solid var(--ui-border); background:#fff;
    font-size:.95rem; outline:none; transition:border-color .15s, box-shadow .15s;
}
.form-group input:focus, .form-group select:focus{
    border-color:#7c3aed; box-shadow:0 0 0 3px rgba(124,58,237,.14);
}

/* Botones */
.btn-primary, .btn-danger{
    border:none; cursor:pointer; font-weight:800; border-radius:10px; padding:11px 14px; font-size:.92rem;
    transition: background .15s ease, transform .05s ease, box-shadow .15s ease;
}
.btn-primary{ background:var(--ui-primary); color:#fff; }
.btn-primary:hover{ background:var(--ui-primary-600); transform: translateY(-1px); }
.btn-danger{ background:var(--ui-danger); color:#fff; }
.btn-danger:hover{ background:#dc2626; transform: translateY(-1px); }

/* Tabla responsive sin scroll horizontal */
.table-wrap{ padding:14px; }
.table{ width:100%; border-collapse:separate; border-spacing:0; }
.table thead th{
    background:#f8fafc; font-weight:800; color:#0f172a; font-size:.85rem; letter-spacing:.02em;
    border-bottom:1px solid var(--ui-border); padding:12px; white-space:nowrap;
}
.table tbody td{
    border-bottom:1px solid var(--ui-border); padding:14px 12px; vertical-align:middle; background:#fff;
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
        flex: 0 0 42%;
        max-width: 42%;
        font-weight:800; color:#64748b;
    }
    .table .row-actions{ width:100%; display:flex; flex-wrap:wrap; gap:8px; }
    .table .row-actions form{ flex:1 1 140px; }
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

<div class="users-hero">
    <h2>Gestión de Usuarios</h2>
    <p>Crea administradores y moderadores, y gestiona su estado de forma segura.</p>
</div>

<div class="users-grid">
    <section class="card">
        <div class="block">
            <h3>Crear Nuevo Usuario</h3>
            <form id="crear-usuario-form" method="post" action="api.php?action=crear_usuario" autocomplete="off" novalidate>
                <div class="form-group">
                    <label for="username">Nombre de Usuario</label>
                    <input type="text" name="username" id="username" required placeholder="Ej. juanperez">
                </div>
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" name="password" id="password" required>
                </div>
                <div class="form-group">
                    <label for="role">Rol</label>
                    <select name="role" id="role" required>
                        <option value="moderador" selected>Moderador</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group" id="team-leader-group">
                    <label for="team_leader_id">Admin responsable (solo si es moderador)</label>
                    <select name="team_leader_id" id="team_leader_id">
                        <option value="">Selecciona un admin</option>
                        <?php foreach ($admins as $admin): ?>
                            <option value="<?php echo (int)$admin['id']; ?>"><?php echo htmlspecialchars($admin['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:8px;">
                    <button type="submit" class="btn-primary" id="submit-button">Crear Usuario</button>
                </div>
            </form>
        </div>
    </section>

    <section class="card">
        <div class="table-wrap">
            <h3 style="margin:0 0 10px;">Usuarios Existentes</h3>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Admin Responsable</th>
                            <th>Activo</th>
                            <th>Fecha Creación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="user-table-body">
                    <?php foreach ($usuarios as $u): ?>
                        <tr id="user-row-<?php e($u['id']); ?>">
                            <td data-label="Usuario"><?php e($u['username']); ?></td>
                            <td data-label="Rol"><?php e(ucfirst($u['role'])); ?></td>
                            <td data-label="Admin Responsable"><?php e($u['leader_name'] ?? '-'); ?></td>
                            <td data-label="Activo"><?php echo $u['active'] ? 'Sí' : 'No'; ?></td>
                            <td data-label="Fecha Creación"><?php e(date('d/m/Y H:i', strtotime($u['created_at']))); ?></td>
                            <td data-label="Acciones">
                                <div class="row-actions">
                                    <form class="delete-user-form" style="display:inline;">
                                        <input type="hidden" name="user_id" value="<?php e($u['id']); ?>">
                                        <button type="submit" class="btn-danger">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<div class="toasts" id="toasts"></div>

<script>
// Define el token CSRF globalmente.
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

// Toast unificado
function notify(message, type='success'){
    const c = document.getElementById('toasts');
    const el = document.createElement('div');
    el.className = 'toast ' + (type === 'error' ? 'error':'success');
    el.textContent = message;
    c.appendChild(el);
    setTimeout(()=>{ el.style.opacity='0'; el.style.transform='translateY(4px)'; }, 2500);
    setTimeout(()=>{ el.remove(); }, 3200);
}
// Compatibilidad con showToast existente
function showToast(message, type='success'){ notify(message, type); }

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('crear-usuario-form');
    const submitButton = document.getElementById('submit-button');

    form.addEventListener('submit', function(event) {
        event.preventDefault();

        const formData = new FormData(form);
        formData.append('csrf_token', CSRF_TOKEN); // Agregar el token al FormData

        const originalButtonText = submitButton.textContent;
        submitButton.disabled = true;
        submitButton.textContent = 'Creando...';

        fetch('api.php?action=crear_usuario', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                notify(data.message || 'Usuario creado con éxito.', 'success');
                form.reset();
                setTimeout(() => location.reload(), 1400);
            } else {
                throw new Error(data.message || 'Ocurrió un error desconocido.');
            }
        })
        .catch(error => notify(error.message, 'error'))
        .finally(() => {
            submitButton.disabled = false;
            submitButton.textContent = originalButtonText;
        });
    });

    // Eliminar usuario (AJAX)
    document.querySelectorAll('.delete-user-form').forEach(deleteForm => {
        deleteForm.addEventListener('submit', function(event) {
            event.preventDefault();
            if (!confirm('¿Estás seguro de que quieres eliminar a este usuario?')) return;

            const formData = new FormData(deleteForm);
            formData.append('csrf_token', CSRF_TOKEN); // Agregar el token al FormData
            const userId = formData.get('user_id');

            fetch('api.php?action=eliminar_usuario', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    notify(data.message || 'Usuario eliminado.', 'success');
                    const row = document.getElementById(`user-row-${userId}`);
                    if (row) row.remove();
                } else {
                    throw new Error(data.message || 'No se pudo eliminar el usuario.');
                }
            })
            .catch(error => notify(error.message, 'error'));
        });
    });

    // Mostrar/ocultar Admin responsable
    const roleSelect = document.getElementById('role');
    const teamLeaderGroup = document.getElementById('team-leader-group');
    function toggleLeader(){ teamLeaderGroup.style.display = roleSelect.value === 'moderador' ? 'block' : 'none'; }
    roleSelect.addEventListener('change', toggleLeader);
    toggleLeader();
});
</script>

<?php require_once 'includes/footer.php'; ?>