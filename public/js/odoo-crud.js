
/**
 * Helper functions untuk CRUD operations dengan Odoo
 */

// Show alert message
function showAlert(message, type = 'success') {
    const alertId = type === 'success' ? 'alertSuccess' : 'alertError';
    let alertDiv = document.getElementById(alertId);
    
    if (!alertDiv) {
        alertDiv = document.createElement('div');
        alertDiv.id = alertId;
        alertDiv.className = type === 'success' ? 'alert-success' : 'alert-error';
        document.querySelector('.container').insertBefore(alertDiv, document.querySelector('.container').children[1]);
    }
    
    alertDiv.textContent = message;
    alertDiv.style.display = 'block';
    
    setTimeout(() => {
        alertDiv.style.display = 'none';
    }, 5000);
}

// Generic fetch with error handling
async function apiRequest(url, options = {}) {
    try {
        const response = await fetch(url, options);
        const result = await response.json();
        return result;
    } catch (error) {
        return {
            success: false,
            message: 'Error: ' + error.message
        };
    }
}

// Delete record
async function deleteRecord(url, confirmMessage = 'Yakin ingin menghapus?') {
    if (!confirm(confirmMessage)) return;
    
    const result = await apiRequest(url, { method: 'POST' });
    
    if (result.success) {
        showAlert(result.message, 'success');
        setTimeout(() => location.reload(), 1500);
    } else {
        showAlert(result.message, 'error');
    }
}

// Generic modal functions
function showModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Handle form submission
async function handleFormSubmit(e, url, modalId) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    
    const result = await apiRequest(url, {
        method: 'POST',
        body: formData
    });
    
    if (result.success) {
        showAlert(result.message, 'success');
        if (modalId) closeModal(modalId);
        setTimeout(() => location.reload(), 1500);
    } else {
        showAlert(result.message, 'error');
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) { 
        event.target.style.display = 'none';
    }   
}
