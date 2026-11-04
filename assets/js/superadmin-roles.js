/* Aplica los badges institucionales sin modificar el backend ni sus permisos. */
(() => {
  const roleClasses = new Map([
    ['ADMIN', 'badge-admin'],
    ['ADMINISTRADOR GENERAL', 'badge-admin'],
    ['SUPERVISOR_COMERCIAL', 'badge-supervisor'],
    ['SUPERVISOR COMERCIAL', 'badge-supervisor'],
    ['VENDEDOR_INTERNO', 'badge-vendedor'],
    ['VENDEDOR INTERNO', 'badge-vendedor'],
    ['SUPERADMIN', 'badge-superadmin'],
    ['SUPERADMINISTRADOR', 'badge-superadmin']
  ]);
  function paint() {
    for (const table of document.querySelectorAll('table')) {
      const heads = [...table.querySelectorAll('thead th')].map(th => th.textContent.trim().toLocaleLowerCase('es'));
      const index = heads.findIndex(h => h === 'rol');
      if (index < 0) continue;
      for (const tr of table.querySelectorAll('tbody tr')) {
        const cell = tr.querySelectorAll('td')[index];
        if (!cell || cell.querySelector('.sa-role-pill')) continue;
        const raw = cell.textContent.trim();
        const role = roleClasses.get(raw.toLocaleUpperCase('es'));
        if (!role) continue;
        const span = document.createElement('span');
        span.className = 'sa-role-pill ' + role;
        span.textContent = raw;
        cell.replaceChildren(span);
      }
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', paint);
  else paint();
})();
