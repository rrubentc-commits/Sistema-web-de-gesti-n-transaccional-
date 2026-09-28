// assets/js/script.js

// Auto-cerrar alertas después de 5 segundos
setTimeout(() => {
    document.querySelectorAll('.alert-dismissible').forEach(el => {
        const alert = bootstrap.Alert.getOrCreateInstance(el);
        alert.close();
    });
}, 5000);

// Confirmar eliminaciones
document.querySelectorAll('.confirm-delete').forEach(el => {
    el.addEventListener('click', (e) => {
        if (!confirm('¿Está seguro de eliminar este registro?')) {
            e.preventDefault();
        }
    });
});