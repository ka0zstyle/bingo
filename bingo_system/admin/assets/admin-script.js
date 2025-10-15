document.addEventListener('DOMContentLoaded', function() {
    // --- OBTENER EL TOKEN CSRF DESDE EL META TAG ---
    const csrfToken = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : null;

    // --- MENÚ RESPONSIVE PARA MÓVIL ---
    const sidebarToggle = document.getElementById('sidebarToggle');
    const navList = document.getElementById('admin-nav-list');

    if (sidebarToggle && navList) {
        sidebarToggle.addEventListener('click', function() {
            const isOpen = navList.classList.toggle('open');
            sidebarToggle.classList.toggle('is-open', isOpen);
            sidebarToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        document.addEventListener('click', function(e) {
            if (navList.classList.contains('open') && !navList.contains(e.target) && e.target !== sidebarToggle) {
                navList.classList.remove('open');
                sidebarToggle.classList.remove('is-open');
                sidebarToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // --- MANEJADORES DE FORMULARIOS Y EVENTOS ---
    const eventForm = document.getElementById('event-form');
    if (eventForm) {
        eventForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveEvent();
        });
    }

    const paymentSearch = document.getElementById('payment-search');
    if (paymentSearch) {
        paymentSearch.addEventListener('keyup', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.payment-card');
            cards.forEach(card => {
                const text = card.getAttribute('data-search-term');
                if (text.includes(searchTerm)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }
});

// --- FUNCIONES GLOBALES (ACCESIBLES DESDE onclick) ---

// --- TOASTS ---
function notify(message, type='success'){
    const c = document.getElementById('toasts');
    if (!c) return;
    const el = document.createElement('div');
    el.className = 'toast ' + (type === 'error' ? 'error':'success');
    el.textContent = message;
    c.appendChild(el);
    setTimeout(()=>{ el.style.opacity='0'; el.style.transform='translateY(4px)'; }, 2500);
    setTimeout(()=>{ el.remove(); }, 3200);
}

// --- MODAL DE CARTONES ---
let currentCardIndex = 0;
let cardsHtml = [];

function viewCartons(purchaseId) {
    const modal = document.getElementById('cartons-modal');
    if (!modal) return;
    const modalBody = document.getElementById('modal-body');
    const modalTitle = document.querySelector('#cartons-modal .modal-header h3');

    modalBody.innerHTML = '<p>Cargando cartones...</p>';
    modalTitle.textContent = 'Cartones de la Compra';
    modal.style.display = 'flex';

    fetch(`api.php?action=get_cartons_for_purchase&purchase_id=${purchaseId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.cards.length > 0) {
                cardsHtml = data.cards;
                currentCardIndex = 0;
                displayCurrentCard();
            } else {
                modalBody.innerHTML = `<p style="color: red;">Error: ${data.message || 'No se pudieron cargar los cartones.'}</p>`;
            }
        })
        .catch(err => {
            modalBody.innerHTML = '<p style="color: red;">Error de red. Inténtalo de nuevo.</p>';
        });
}

function displayCurrentCard() {
    const modalBody = document.getElementById('modal-body');
    const modalTitle = document.querySelector('#cartons-modal .modal-header h3');
    const modalFooter = document.querySelector('#cartons-modal .modal-footer');

    if (!modalBody || !modalTitle || !modalFooter) return;

    modalBody.innerHTML = cardsHtml[currentCardIndex];
    modalTitle.textContent = `Cartón ${currentCardIndex + 1} de ${cardsHtml.length}`;
    modalFooter.querySelector('#prev-card-btn').disabled = (currentCardIndex === 0);
    modalFooter.querySelector('#next-card-btn').disabled = (currentCardIndex === cardsHtml.length - 1);
}

function changeCard(direction) {
    const newIndex = currentCardIndex + direction;
    if (newIndex >= 0 && newIndex < cardsHtml.length) {
        currentCardIndex = newIndex;
        displayCurrentCard();
    }
}

function closeCartonsModal() {
    const modal = document.getElementById('cartons-modal');
    if (!modal) return;
    modal.style.display = 'none';
    document.getElementById('modal-body').innerHTML = '';
    cardsHtml = [];
    currentCardIndex = 0;
}

// --- MODAL DE RECIBOS ---
function viewReceipt(receiptUrl) {
    const modal = document.getElementById('receipt-modal');
    if (!modal) return;
    const img = document.getElementById('receipt-image');
    
    img.src = receiptUrl;
    modal.style.display = 'flex';
}

function closeReceiptModal() {
    const modal = document.getElementById('receipt-modal');
    if (!modal) return;
    modal.style.display = 'none';
    document.getElementById('receipt-image').src = '';
}

// --- MODAL DE HISTORIAL ---
function viewHistory(purchaseId) {
    const modal = document.getElementById('history-modal');
    if (!modal) return;
    const body = document.getElementById('history-modal-body');
    if (!body) return;
    
    body.innerHTML = '<p>Cargando historial...</p>';
    modal.style.display = 'flex';

    fetch(`api.php?action=get_purchase_history&id=${purchaseId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.history.length > 0) {
                let html = '<ul style="list-style:none; padding:0;">';
                data.history.forEach(log => {
                    const date = new Date(log.created_at + ' UTC').toLocaleString();
                    html += `<li style="background:white; border:1px solid #e2e8f0; border-radius:8px; padding:1rem; margin-bottom:1rem;">`;
                    html += `<p style="margin:0; font-size:0.9rem; color:#4a5568;">${date}</p>`;
                    html += `<p style="margin:0.25rem 0; font-weight:500;">${log.description}</p>`;
                    html += `<p style="margin:0; font-size:0.9rem;">Usuario: <strong>${log.username}</strong></p>`;
                    html += `</li>`;
                });
                html += '</ul>';
                body.innerHTML = html;
            } else {
                body.innerHTML = '<p>No hay historial de modificaciones para esta compra.</p>';
            }
        })
        .catch(error => {
            body.innerHTML = '<p>Error al cargar el historial.</p>';
            console.error('Error:', error);
        });
}

function closeHistoryModal() {
    const modal = document.getElementById('history-modal');
    if (!modal) return;
    modal.style.display = 'none';
    document.getElementById('history-modal-body').innerHTML = '';
}

// --- FUNCIONES DEL MODAL DE EVENTOS ---
function openEventModal(eventObj = null) {
    const modal = document.getElementById('eventModal');
    if (!modal) return;
    const title = document.getElementById('event-modal-title');
    const form = document.getElementById('event-form');
    const saveBtn = document.getElementById('save-event-btn');

    form.reset();

    if (eventObj) {
        title.textContent = 'Editar Evento';
        saveBtn.textContent = 'Guardar Cambios';

        const data = typeof eventObj === 'string' ? JSON.parse(eventObj) : eventObj;

        document.getElementById('event-id').value = data.id || '';
        document.getElementById('event_name').value = data.name || '';
        
        const date = new Date(data.event_date);
        const formatted = date.getFullYear()
            + '-' + ('0' + (date.getMonth() + 1)).slice(-2)
            + '-' + ('0' + date.getDate()).slice(-2)
            + 'T' + ('0' + date.getHours()).slice(-2)
            + ':' + ('0' + date.getMinutes()).slice(-2);
        
        document.getElementById('event_date').value = formatted;
        document.getElementById('event_price').value = data.price_local ?? '';
    } else {
        title.textContent = 'Crear Nuevo Evento';
        saveBtn.textContent = 'Guardar';
    }

    modal.style.display = 'flex';
}

function closeEventModal() {
    const modal = document.getElementById('eventModal');
    if (!modal) return;
    modal.style.display = 'none';
    document.getElementById('event-form').reset();
}

function saveEvent() {
    const id    = document.getElementById('event-id').value.trim();
    const name  = document.getElementById('event_name').value.trim();
    const date  = document.getElementById('event_date').value;
    const pLoc  = document.getElementById('event_price').value;
    const csrf  = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    if (!name || !date || !pLoc) {
        notify('Completa todos los campos del formulario.', 'error');
        return;
    }

    const payload = {
        id: id ? parseInt(id, 10) : 0,
        name,
        event_date: date,
        price_local: parseFloat(pLoc),
        csrf_token: csrf
    };

    fetch('api.php?action=save_event', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            notify(data.message || 'Evento guardado.');
            setTimeout(() => location.reload(), 800);
        } else {
            notify(data.message || 'Error al guardar el evento.', 'error');
        }
    })
    .catch(() => notify('Error de red al guardar el evento.', 'error'));
}

// --- ACCIONES DE ESTADO DE EVENTO ---
function toggleEventStatus(id, newStatus) {
    const csrfTokenEl = document.querySelector('meta[name="csrf-token"]');
    if (!csrfTokenEl) { notify('Error Crítico: Falta el meta tag CSRF.', 'error'); return; }
    const csrfToken = csrfTokenEl.getAttribute('content');

    const formData = new FormData();
    formData.append('id', id);
    formData.append('is_active', newStatus);
    formData.append('csrf_token', csrfToken);

    fetch('api.php?action=toggle_event_status', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            notify(data.message || 'Estado actualizado con éxito.');
            setTimeout(() => location.reload(), 600);
        }
        else {
            notify(data.message || 'Error al cambiar el estado del evento.', 'error');
        }
    });
}

// --- ACCIONES DE COMPRAS ---
function updateStatus(id, newStatus) {
    const csrfTokenEl = document.querySelector('meta[name="csrf-token"]');
    if (!csrfTokenEl) { notify('Error Crítico: Falta el meta tag CSRF.', 'error'); return; }
    const csrfToken = csrfTokenEl.getAttribute('content');

    const actionText = newStatus === 'approved' ? 'aprobar' : 'rechazar';
    if (!confirm(`¿Estás seguro de que quieres ${actionText} esta compra?`)) return;
    
    const card = document.querySelector(`.payment-card[data-id="${id}"]`);
    if (card) {
        card.style.opacity = '0.5';
        card.style.pointerEvents = 'none';
    }
    
    const formData = new FormData();
    formData.append('id', id);
    formData.append('status', newStatus);
    formData.append('csrf_token', csrfToken);

    fetch('api.php?action=update_purchase_status', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            notify(data.message || 'Estado de compra actualizado con éxito.');
            if (card) card.remove(); // Elimina la tarjeta si la acción fue exitosa
        } else {
            notify(data.message || 'Error al actualizar el estado de la compra.', 'error');
            if (card) { // Si hubo un error, revertir el estilo
                card.style.opacity = '1';
                card.style.pointerEvents = 'auto';
            }
        }
    })
    .catch(error => {
        notify('Error de red al procesar la solicitud.', 'error');
        if (card) {
            card.style.opacity = '1';
            card.style.pointerEvents = 'auto';
        }
        console.error('Error:', error);
    });
}

// --- AJUSTAR CANTIDAD ---
function adjustQuantity(purchaseId, action){
    const csrfTokenEl = document.querySelector('meta[name="csrf-token"]');
    if (!csrfTokenEl) { notify('Error Crítico: Falta el meta tag CSRF.', 'error'); return; }
    const csrfToken = csrfTokenEl.getAttribute('content');

    const quantity = action === 'add' ? 1 : -1;
    const formData = new FormData();
    formData.append('purchase_id', purchaseId);
    formData.append('quantity', quantity);
    formData.append('csrf_token', csrfToken);

    fetch('api.php?action=adjust_card_quantity', { method:'POST', body: formData })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
            notify(data.message || 'Cantidad ajustada con éxito.');
            setTimeout(() => location.reload(), 800);
        } else {
            notify(data.message || 'Error al ajustar la cantidad.', 'error');
        }
      })
      .catch(() => notify('Error de red.', 'error'));
}

// --- WINDOW CLICK HANDLER ---
window.onclick = function(event) {
    const modals = [
        document.getElementById('cartons-modal'),
        document.getElementById('receipt-modal'),
        document.getElementById('history-modal'),
        document.getElementById('eventModal')
    ];
    modals.forEach(modal => {
        if (modal && event.target === modal) {
            modal.style.display = 'none';
        }
    });
};