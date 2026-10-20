  <!-- Pie de Página Global -->
  <footer class="app-footer">
    <div class="app-footer-brand">
      <span>Innovatrónica S.A.</span>
      <span>·</span>
      <span>Sistema de Control de Stock v1.2 (Fase 2)</span>
    </div>
    <div style="display:flex; align-items:center; gap:1rem;">
      <span>Zona horaria: <code>America/Argentina/Buenos_Aires</code></span>
      <span>·</span>
      <span style="color:var(--success); font-weight:600;">● Sistema Operativo</span>
    </div>
  </footer>

  <!-- Script para interactividad de la barra de navegación -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const toggleBtn = document.getElementById('navToggle');
      const navMenu = document.getElementById('navMenu');
      if (toggleBtn && navMenu) {
        toggleBtn.addEventListener('click', function() {
          navMenu.classList.toggle('is-active');
        });
      }
    });
  </script>
</body>
</html>
