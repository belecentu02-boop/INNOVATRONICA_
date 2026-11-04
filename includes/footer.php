  <!-- Pie de Página Global -->
  <footer class="app-footer">
    <div class="app-footer-brand">
      <span>Innovatrónica S.A.</span>
      <span>·</span>
      <span><?= !empty($es_portal_empleados) ? 'Portal interno de empleados' : 'Sistema de Control de Stock v1.2 (Fase 2)' ?></span>
    </div>
    <div style="display:flex; align-items:center; gap:1rem;">
      <span>Zona horaria: <code>America/Argentina/Buenos_Aires</code></span>
      <span>·</span>
      <?php if (!empty($es_portal_empleados)): ?>
        <span>Acceso interno</span>
      <?php else: ?>
        <span style="color:var(--success); font-weight:600;">● Sistema Operativo</span>
      <?php endif; ?>
    </div>
  </footer>

  <script src="assets/js/ui-feedback.js" defer></script>
</body>
</html>

