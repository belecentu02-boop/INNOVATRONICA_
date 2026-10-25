(function () {
  'use strict';
  const pending = new Map();

  function setBusy(button, busy, label) {
    if (!button) return;
    if (busy) {
      if (pending.has(button)) return;
      pending.set(button, { nodes: Array.from(button.childNodes), disabled: button.disabled });
      button.textContent = label || 'Procesando…';
      button.classList.add('ui-loading');
      button.setAttribute('aria-busy', 'true');
      button.disabled = true;
    } else {
      const previous = pending.get(button);
      if (!previous) return;
      button.replaceChildren(...previous.nodes);
      button.disabled = previous.disabled;
      button.classList.remove('ui-loading');
      button.removeAttribute('aria-busy');
      pending.delete(button);
    }
  }
  window.InnovatronicaUI = { setBusy };

  function init() {
    const forms = document.querySelectorAll('form[data-loading-text]');
    forms.forEach(form => {
      form.addEventListener('submit', event => {
        if (event.defaultPrevented) return;
        if (form.getAttribute('aria-busy') === 'true') {
          event.preventDefault();
          return;
        }
        const button = form.querySelector('button[type="submit"]');
        if (!button) return;
        // The native submit event occurs only after browser validation.
        // Keep inputs enabled so the POST retains every field and CSRF token.
        form.setAttribute('aria-busy', 'true');
        setBusy(button, true, form.dataset.loadingText);
      });
    });
    window.addEventListener('pageshow', () => {
      forms.forEach(form => {
        form.removeAttribute('aria-busy');
        setBusy(form.querySelector('button[type="submit"]'), false);
      });
    });

    const toggle = document.getElementById('navToggle');
    const menu = document.getElementById('navMenu');
    if (!toggle || !menu) return;
    const mobile = window.matchMedia('(max-width: 768px)');
    function setMenu(open, focusToggle) {
      open = mobile.matches && open;
      menu.classList.toggle('is-active', open);
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Cerrar menú de navegación' : 'Abrir menú de navegación');
      if (mobile.matches) {
        menu.inert = !open;
        menu.setAttribute('aria-hidden', String(!open));
      } else {
        menu.inert = false;
        menu.removeAttribute('aria-hidden');
      }
      if (focusToggle) toggle.focus();
    }
    toggle.addEventListener('click', () => setMenu(!menu.classList.contains('is-active')));
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && mobile.matches && menu.classList.contains('is-active')) {
        setMenu(false, true);
      }
    });
    menu.addEventListener('click', event => {
      if (event.target.closest('a')) setMenu(false);
    });
    mobile.addEventListener('change', () => setMenu(false));
    setMenu(false);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
