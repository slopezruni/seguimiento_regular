</div> <!-- Fin container-fluid -->
        
        <footer class="bg-white border-top py-2 px-4 mt-auto text-center text-md-between d-flex flex-column flex-md-row justify-content-between align-items-center small text-muted">
            <div>
                <strong class="text-success">SENA</strong> &bull; Centro de Electricidad y Automatización Industrial (CEAI) - Regional Valle
            </div>
            <div>
                Sistema de Gestión y Seguimiento de Apoyos Socioeconómicos &copy; <?= date('Y') ?>
            </div>
        </footer>
    </div> <!-- Fin page-content-wrapper -->
</div> <!-- Fin wrapper -->

<!-- Bootstrap 5.3 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Lógica del Menú Lateral Colapsable con memoria en el navegador
    const wrapper = document.getElementById('wrapper');
    const toggleBtn = document.getElementById('sidebarToggle');

    if (localStorage.getItem('sena_sidebar_collapsed') === 'true') {
        wrapper.classList.add('toggled');
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            wrapper.classList.toggle('toggled');
            localStorage.setItem('sena_sidebar_collapsed', wrapper.classList.contains('toggled'));
        });
    }

    // 2. Inicializar todos los Tooltips de Bootstrap automáticamente
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.forEach(function (el) {
        if (!el.getAttribute('data-bs-toggle')) {
            new bootstrap.Tooltip(el, { trigger: 'hover' });
        }
    });
});
</script>
</body>
</html>