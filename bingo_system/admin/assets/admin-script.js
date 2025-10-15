// Variables globales para modales y datos
let currentCardIndex = 0;
let cardsInModal = [];
let lastPaymentCheck = new Date().toISOString();

document.addEventListener('DOMContentLoaded', () => {
    // Búsqueda en la lista de pagos
    const searchInput = document.getElementById('payment-search');
    if (searchInput) {
        searchInput.addEventListener('keyup', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            document.querySelectorAll('.payment-card, .history-card').forEach(card => {
                const text = card.dataset.searchTerm || '';
                card.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }

    // Polling para actualizaciones de pagos si estamos en la página de pagos
    const paymentsSection = document.querySelector('.admin-section[data-status-filter]');
    if (paymentsSection) {
        setInterval(checkForUpdates, 15000); // Chequea cada 15 segundos
    }

    // Listener para el formulario de eventos, si existe
    const eventForm = document.getElementById('event-form');
    if (eventForm) {
        eventForm.addEventListener('submit', (e) => {
            e.preventDefault();
            saveEvent();
        });
    }
});


// --- Notificaciones Toast ---
function notify(message, type = 'success') {
    const container = document.getElementById('toasts');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        setTimeout(() => toast.remove(), 500);
    }, 3000);
}


// --- Lógica de Pagos ---
function updateStatus(purchaseId, newStatus, buttonEl) {
    const card = document.getElementById(`payment-card-${purchaseId}`);
    if (!card) return;

    const confirmationMessage = newStatus === 'approved' 
        ? '¿Seguro que quieres APROBAR esta compra? Se enviarán los cartones por correo.' 
        : '¿Seguro que quieres RECHAZAR esta compra?';
        
    if (!confirm(confirmationMessage)) return;

    buttonEl.disabled = true;
    buttonEl.textContent = 'Procesando...';

    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'update_purchase_status',
            id: purchaseId,
            status: newStatus,
            csrf_token: csrfToken
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            notify(data.message, 'success');
            card.classList.add('removing');
            setTimeout(() => card.remove(), 500);
            updateStats(data.stats);
        } else {
            notify(data.message || 'Ocurrió un error.', 'error');
            buttonEl.disabled = false;
            buttonEl.textContent = newStatus === 'approved' ? 'Aprobar' : 'Rechazar';
        }
    })
    .catch(err => {
        console.error('Error en updateStatus:', err);
        notify('Error de conexión con el servidor.', 'error');
        buttonEl.disabled = false;
        buttonEl.textContent = newStatus === 'approved' ? 'Aprobar' : 'Rechazar';
    });
}

function checkForUpdates() {
    const paymentsSection = document.querySelector('.admin-section[data-status-filter]');
    if (!paymentsSection) return;

    fetch(`api.php?action=get_payment_updates&since=${lastPaymentCheck}`)
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            lastPaymentCheck = new Date().toISOString(); // Actualizar el timestamp de chequeo
            updateStats(data.stats);

            const currentFilter = paymentsSection.dataset.statusFilter;
            if (currentFilter === 'pending' && data.new_purchases && data.new_purchases.length > 0) {
                const container = document.getElementById('payments-list-container');
                
                const emptyState = container.querySelector('.empty-state');
                if (emptyState) emptyState.remove();

                data.new_purchases.forEach(purchase => {
                    if (!document.getElementById(`payment-card-${purchase.id}`)) {
                        const cardHtml = createPaymentCardHtml(purchase);
                        container.insertAdjacentHTML('afterbegin', cardHtml);
                    }
                });
                notify(`${data.new_purchases.length} nuevo(s) pago(s) pendiente(s).`, 'success');
            }
        }
    })
    .catch(err => console.error("Error chequeando actualizaciones:", err));
}

function updateStats(stats) {
    if (!stats) return;

    const localCurrencySymbol = document.getElementById('pending-total-amount')?.textContent.split(' ')[0] || 'Bs.';

    document.getElementById('pending-count').textContent = stats.pending || 0;
    document.getElementById('approved-count').textContent = stats.approved || 0;
    document.getElementById('rejected-count').textContent = stats.rejected || 0;
    document.getElementById('history-count').textContent = stats.history || 0;
    document.getElementById('pending-total-amount').textContent = `${localCurrencySymbol} ${parseFloat(stats.total_pending || 0).toFixed(2)}`;
}

function createPaymentCardHtml(p) {
    const localCurrencyCode = 'VES';
    const searchTerm = `${p.owner_name} ${p.owner_email} ${p.event_name} ${p.payment_ref}`.toLowerCase();
    const receiptButton = p.payment_receipt_path 
        ? `<button class="btn btn-view" onclick="viewReceipt('${BASE_URL}uploads/receipts/${p.payment_receipt_path}')">Ver Comprobante</button>` 
        : '';
        
    return `
        <div class="payment-card" id="payment-card-${p.id}" data-id="${p.id}" data-search-term="${searchTerm}">
            <div class="card-header">
                <div class="client-info">
                    <h3>${p.owner_name}</h3>
                    <small>${p.owner_email}</small>
                </div>
                <span class="status-badge ${p.status}">${p.status.charAt(0).toUpperCase() + p.status.slice(1)}</span>
            </div>
            <div class="payment-details">
                <div class="detail-item"><strong>Evento:</strong> ${p.event_name}</div>
                <div class="detail-item"><strong>Cartones:</strong> ${p.card_count}</div>
                <div class="detail-item"><strong>Referencia:</strong> ${p.payment_ref}</div>
                <div class="detail-item"><strong>Total:</strong> ${parseFloat(p.total_local).toFixed(2)} ${localCurrencyCode} (${parseFloat(p.total_usd).toFixed(2)} USD)</div>
                <div class="detail-item"><strong>Fecha:</strong> ${new Date(p.created_at).toLocaleString()}</div>
            </div>
            <div class="card-actions">
                <button class="btn btn-approve" onclick="updateStatus(${p.id}, 'approved', this)">Aprobar</button>
                <button class="btn btn-reject" onclick="updateStatus(${p.id}, 'rejected', this)">Rechazar</button>
                <button class="btn btn-view" onclick="viewCartons(${p.id})">Ver Cartones</button>
                ${receiptButton}
                <button class="btn btn-view" onclick="viewHistory(${p.id})">Historial</button>
            </div>
        </div>
    `;
}

// --- LÓGICA DE EVENTOS ---
function openEventModal(eventData = null) {
    const modal = document.getElementById('eventModal');
    const form = document.getElementById('event-form');
    const title = document.getElementById('event-modal-title');

    form.reset(); 

    if (eventData) {
        title.textContent = 'Editar Evento';
        document.getElementById('event-id').value = eventData.id;
        document.getElementById('event_name').value = eventData.name;
        const date = new Date(eventData.event_date);
        date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
        document.getElementById('event_date').value = date.toISOString().slice(0,16);
        
        document.getElementById('event_price').value = eventData.price_local;
    } else {
        title.textContent = 'Crear Nuevo Evento';
        document.getElementById('event-id').value = '';
    }
    modal.style.display = 'flex';
}

function closeEventModal() {
    document.getElementById('eventModal').style.display = 'none';
}

function saveEvent() {
    const button = document.getElementById('save-event-btn');
    button.disabled = true;
    button.textContent = 'Guardando...';

    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const eventData = {
        id: document.getElementById('event-id').value,
        name: document.getElementById('event_name').value,
        event_date: document.getElementById('event_date').value,
        price_local: document.getElementById('event_price').value,
        csrf_token: csrfToken
    };

    fetch('api.php?action=save_event', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(eventData)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            notify(data.message || 'Evento guardado con éxito.', 'success');
            closeEventModal();
            setTimeout(() => window.location.reload(), 1000);
        } else {
            notify(data.message || 'Error al guardar el evento.', 'error');
        }
    })
    .catch(err => {
        console.error('Error en saveEvent:', err);
        notify('Error de conexión al guardar.', 'error');
    })
    .finally(() => {
        button.disabled = false;
        button.textContent = 'Guardar';
    });
}

function toggleEventStatus(eventId, newStatus) {
    if (!confirm(`¿Seguro que quieres ${newStatus === 1 ? 'activar' : 'desactivar'} este evento?`)) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    
    fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'toggle_event_status',
            id: eventId,
            is_active: newStatus,
            csrf_token: csrfToken
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            notify(data.message || 'Estado actualizado.', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            notify(data.message || 'Error al actualizar el estado.', 'error');
        }
    })
    .catch(err => {
        console.error('Error en toggleEventStatus:', err);
        notify('Error de conexión.', 'error');
    });
}


// --- MODALES (Genéricos) ---
function viewCartons(purchaseId) {
    fetch(`api.php?action=get_cartons_for_purchase&purchase_id=${purchaseId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.cards.length > 0) {
                cardsInModal = data.cards;
                currentCardIndex = 0;
                document.getElementById('modal-body').innerHTML = cardsInModal[currentCardIndex];
                document.getElementById('cartons-modal').style.display = 'flex';
                updateCardNav();
            } else {
                notify(data.message || 'No se encontraron cartones.', 'error');
            }
        })
        .catch(err => {
            console.error('Error en viewCartons:', err);
            notify('Error de conexión al buscar cartones.', 'error');
        });
}

function closeCartonsModal() {
    document.getElementById('cartons-modal').style.display = 'none';
    cardsInModal = [];
}

function changeCard(direction) {
    const newIndex = currentCardIndex + direction;
    if (newIndex >= 0 && newIndex < cardsInModal.length) {
        currentCardIndex = newIndex;
        document.getElementById('modal-body').innerHTML = cardsInModal[currentCardIndex];
        updateCardNav();
    }
}

function updateCardNav() {
    const prevBtn = document.getElementById('prev-card-btn');
    const nextBtn = document.getElementById('next-card-btn');
    prevBtn.style.display = cardsInModal.length > 1 ? 'block' : 'none';
    nextBtn.style.display = cardsInModal.length > 1 ? 'block' : 'none';
    prevBtn.disabled = currentCardIndex === 0;
    nextBtn.disabled = currentCardIndex === cardsInModal.length - 1;
}

function viewReceipt(imageUrl) {
    document.getElementById('receipt-image').src = imageUrl;
    document.getElementById('receipt-modal').style.display = 'flex';
}

function closeReceiptModal() {
    document.getElementById('receipt-modal').style.display = 'none';
}

function viewHistory(purchaseId) {
    fetch(`api.php?action=get_purchase_history&id=${purchaseId}`)
        .then(res => res.json())
        .then(data => {
            const body = document.getElementById('history-modal-body');
            if (data.success && data.history.length > 0) {
                let html = '<ul>';
                data.history.forEach(item => {
                    html += `<li><strong>${new Date(item.created_at).toLocaleString()}:</strong> ${item.description} (por ${item.username})</li>`;
                });
                html += '</ul>';
                body.innerHTML = html;
            } else {
                body.innerHTML = '<p>No hay historial para esta compra.</p>';
            }
            document.getElementById('history-modal').style.display = 'flex';
        })
        .catch(err => {
            console.error('Error en viewHistory:', err);
            notify('Error al cargar el historial.', 'error');
        });
}

function closeHistoryModal() {
    document.getElementById('history-modal').style.display = 'none';
}