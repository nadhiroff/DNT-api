// NovaBase — comportements globaux (chargé en defer, compatible CSP stricte : aucun eval).
(() => {
  'use strict';

  const root = document.documentElement;
  const STORAGE_KEY = 'nb-theme';

  const storedTheme = () => {
    try {
      return localStorage.getItem(STORAGE_KEY);
    } catch {
      return null;
    }
  };

  const applyTheme = (theme) => {
    root.dataset.theme = theme;
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
      button.setAttribute('aria-pressed', String(theme === 'light'));
      button.setAttribute('aria-label', theme === 'dark' ? 'Activer le thème clair' : 'Activer le thème sombre');
    });
  };

  document.addEventListener('click', (event) => {
    const toggle = event.target instanceof Element ? event.target.closest('[data-theme-toggle]') : null;
    if (!toggle) {
      return;
    }
    const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
    try {
      localStorage.setItem(STORAGE_KEY, next);
    } catch {
      // Stockage indisponible (navigation privée) : le choix vaut pour la page courante.
    }
    applyTheme(next);
  });

  applyTheme(storedTheme() ?? root.dataset.theme ?? 'dark');

  // Jeton CSRF ajouté automatiquement aux requêtes fetch modifiantes de même origine.
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  if (csrf) {
    const nativeFetch = window.fetch.bind(window);
    window.fetch = (input, init = {}) => {
      const method = (init.method ?? 'GET').toUpperCase();
      const url = new URL(typeof input === 'string' ? input : input.url, window.location.href);
      if (!['GET', 'HEAD', 'OPTIONS'].includes(method) && url.origin === window.location.origin) {
        const headers = new Headers(init.headers ?? {});
        headers.set('X-CSRF-Token', csrf);
        init = { ...init, headers };
      }
      return nativeFetch(input, init);
    };
  }
})();
