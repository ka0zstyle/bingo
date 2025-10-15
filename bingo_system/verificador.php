<?php
session_start();
define('BINGO_SYSTEM', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

// Cargamos los eventos para que el usuario pueda elegir
$pdo = get_db_connection();

// MODIFICACIÓN: Añadimos "WHERE is_active = 1" para mostrar solo rifas activas o completadas, ocultando las canceladas.
$stmt = $pdo->query("SELECT id, name, event_date FROM bingo_events WHERE is_active = 1 ORDER BY event_date DESC");
$eventos = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="form-container">

    <h2>Verificador de Cartones</h2>
    <p>Selecciona el evento e introduce tus datos para descargar los cartones.</p>

    <?php if (isset($_SESSION['verifier_error'])): ?>
        <div class="alert alert-error"><?php echo $_SESSION['verifier_error']; unset($_SESSION['verifier_error']); ?></div>
    <?php endif; ?>

    <form action="descargar.php" method="POST" target="_blank">
        
        <div class="form-group">
            <label for="event_id">Selecciona el Evento</label>
            <select id="event_id" name="event_id" required>
                <option value="">-- Por favor, elige un evento --</option>
                <?php foreach ($eventos as $evento): ?>
                    <option value="<?php echo (int)$evento['id']; ?>">
                        <?php echo htmlspecialchars($evento['name']) . ' (' . date('d/m/Y', strtotime($evento['event_date'])) . ')'; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="email">Correo Electrónico</label>
            <input type="email" id="email" name="email" required placeholder="tu.correo@ejemplo.com">
        </div>
        
        <div class="form-group">
            <label for="id_card">Número de Cédula</label>
            <input type="text" id="id_card" name="id_card" required placeholder="V-12345678">
        </div>

        <button type="submit" class="btn btn-primary">Buscar y Descargar PDF</button>
    </form>
    
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>