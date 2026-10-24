// NovaBase — comportements globaux (chargé en defer, compatible CSP stricte : aucun eval, aucun gestionnaire inline).
(() => {
  'use strict';

  const root = document.documentElement;
  const STORAGE_KEY = 'nb-theme';

  /* ---------- Thème clair / sombre ---------- */
  const storedTheme = () => {
    try {
      return localStorage.getItem(STORAGE_KEY);
    } catch {
      return null;
    }
  };

  const applyTheme = (theme) => {
    root.dataset.theme = theme;
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', theme === 'dark' ? '#0c0d0f' : '#fafaf9');
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
      button.setAttribute('aria-pressed', String(theme === 'light'));
      button.setAttribute('aria-label', theme === 'dark' ? 'Activer le thème clair' : 'Activer le thème sombre');
    });
  };

  const toggleTheme = () => {
    const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
    try {
      localStorage.setItem(STORAGE_KEY, next);
    } catch {
      // Stockage indisponible (navigation privée) : le choix vaut pour la page courante.
    }
    applyTheme(next);
    document.dispatchEvent(new CustomEvent('nb:theme', { detail: { theme: next } }));
  };

  /* ---------- Menu mobile ---------- */
  const setMenu = (open) => {
    const button = document.querySelector('[data-nav-toggle]');
    const menu = button ? document.getElementById(button.getAttribute('aria-controls')) : null;
    if (!button || !menu) {
      return;
    }
    menu.hidden = !open;
    button.setAttribute('aria-expanded', String(open));
    button.setAttribute('aria-label', open ? 'Fermer le menu' : 'Ouvrir le menu');
    button.querySelector('[data-nav-icon-open]').hidden = open;
    button.querySelector('[data-nav-icon-close]').hidden = !open;
  };

  /* ---------- Afficher / masquer un mot de passe ---------- */
  const togglePassword = (button) => {
    const input = document.getElementById(button.dataset.passwordToggle);
    if (!input) {
      return;
    }
    const visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    button.setAttribute('aria-pressed', String(visible));
    button.setAttribute('aria-label', visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
    button.querySelector('[data-eye]').hidden = visible;
    button.querySelector('[data-eye-off]').hidden = !visible;
  };

  /* ---------- Copier dans le presse-papiers ---------- */
  const copy = async (button) => {
    try {
      await navigator.clipboard.writeText(button.dataset.copy);
      const label = button.getAttribute('aria-label');
      button.setAttribute('aria-label', 'Copié !');
      window.nbToast?.('success', 'Copié dans le presse-papiers.');
      button.classList.add('text-accent-text');
      setTimeout(() => {
        if (label) {
          button.setAttribute('aria-label', label);
        }
        button.classList.remove('text-accent-text');
      }, 1600);
    } catch {
      window.nbToast?.('error', 'Copie impossible : sélectionnez le texte manuellement.');
    }
  };

  /* ---------- Onglets accessibles ---------- */
  const selectTab = (tab) => {
    const container = tab.closest('[data-tabs]');
    container.querySelectorAll('[data-tab]').forEach((t) => {
      if (t.closest('[data-tabs]') !== container) {
        return;
      }
      const selected = t === tab;
      t.setAttribute('aria-selected', String(selected));
      t.tabIndex = selected ? 0 : -1;
    });
    container.querySelectorAll('[data-panel]').forEach((panel) => {
      if (panel.closest('[data-tabs]') === container) {
        panel.hidden = panel.dataset.panel !== tab.dataset.tab;
        // Les démonstrations repartent du début à chaque ouverture d'onglet.
        if (!panel.hidden && typeof panel.getAnimations === 'function') {
          panel.getAnimations({ subtree: true }).forEach((animation) => {
            animation.currentTime = 0;
          });
        }
      }
    });
    if (container.dataset.tabs) {
      document.dispatchEvent(new CustomEvent('nb:tab', { detail: { group: container.dataset.tabs, label: tab.dataset.label ?? tab.textContent.trim() } }));
    }
  };

  document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) {
      return;
    }
    if (target.closest('[data-theme-toggle]')) {
      toggleTheme();
      return;
    }
    const navToggle = target.closest('[data-nav-toggle]');
    if (navToggle) {
      setMenu(navToggle.getAttribute('aria-expanded') !== 'true');
      return;
    }
    if (target.closest('[data-nav-close]')) {
      setMenu(false);
      return;
    }
    const passwordToggle = target.closest('[data-password-toggle]');
    if (passwordToggle) {
      togglePassword(passwordToggle);
      return;
    }
    const preset = target.closest('[data-smtp-preset]');
    if (preset) {
      const form = preset.closest('[data-smtp-form]');
      form.querySelector('[name="host"]').value = preset.dataset.host;
      form.querySelector('[name="port"]').value = preset.dataset.port;
      form.querySelector('[name="encryption"]').value = preset.dataset.encryption;
      form.querySelector('[name="username"]')?.focus();
      return;
    }
    const copyPanel = target.closest('[data-copy-panel]');
    if (copyPanel) {
      const visible = copyPanel.closest('[data-tabs]')?.querySelector('[data-panel]:not([hidden]) code');
      if (visible) {
        navigator.clipboard.writeText(visible.textContent ?? '').then(() => window.nbToast?.('success', 'Exemple copié.'));
      }
      return;
    }
    const reveal = target.closest('[data-reveal-secret]');
    if (reveal) {
      const code = document.getElementById(reveal.dataset.revealSecret);
      if (code) {
        const shown = reveal.getAttribute('aria-pressed') === 'true';
        code.textContent = shown ? code.dataset.masked : code.dataset.secretValue;
        reveal.setAttribute('aria-pressed', String(!shown));
        reveal.setAttribute('aria-label', shown ? 'Afficher la valeur' : 'Masquer la valeur');
      }
      return;
    }
    const copyButton = target.closest('[data-copy]');
    if (copyButton) {
      copy(copyButton);
      return;
    }
    const tab = target.closest('[data-tab]');
    if (tab) {
      selectTab(tab);
    }
  });

  /* ---------- Fenêtre de confirmation personnalisée (aucun confirm() natif) ---------- */
  const dialog = document.getElementById('confirm-dialog');
  let pendingForm = null;
  document.addEventListener('submit', (event) => {
    const form = event.target instanceof HTMLFormElement ? event.target : null;
    if (!form?.dataset.confirm || form.dataset.confirmed === '1' || !(dialog instanceof HTMLDialogElement)) {
      return;
    }
    event.preventDefault();
    pendingForm = form;
    dialog.querySelector('#confirm-title').textContent = form.dataset.confirmTitle ?? 'Confirmation';
    dialog.querySelector('#confirm-message').textContent = form.dataset.confirm;
    dialog.querySelector('[data-confirm-ok-btn]').textContent = form.dataset.confirmOk ?? 'Confirmer';
    dialog.querySelector('[data-confirm-cancel-btn]').textContent = form.dataset.confirmCancel ?? 'Annuler';
    dialog.showModal();
    dialog.querySelector('[data-confirm-cancel-btn]').focus();
  });
  if (dialog instanceof HTMLDialogElement) {
    dialog.querySelector('[data-confirm-ok-btn]').addEventListener('click', () => {
      dialog.close();
      if (pendingForm) {
        pendingForm.dataset.confirmed = '1';
        pendingForm.requestSubmit();
      }
    });
    dialog.querySelector('[data-confirm-cancel-btn]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) {
        dialog.close();
      }
    });
    dialog.addEventListener('close', () => {
      pendingForm = null;
    });
  }

  /* ---------- Table Editor : lignes de colonnes dynamiques ---------- */
  const syncColumnRow = (row) => {
    const type = row.querySelector('[data-column-type]')?.value;
    const fk = row.querySelector('[data-fk-fields]');
    if (fk) {
      fk.hidden = type !== 'foreign';
    }
    const length = row.querySelector('input[name$="[length]"]');
    if (length) {
      length.disabled = type !== 'string';
    }
  };
  document.querySelectorAll('[data-column-row]').forEach(syncColumnRow);
  document.addEventListener('change', (event) => {
    const select = event.target instanceof HTMLSelectElement && event.target.matches('[data-column-type]') ? event.target : null;
    if (select) {
      syncColumnRow(select.closest('[data-column-row]'));
    }
  });
  document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const add = target?.closest('[data-column-add]');
    if (add) {
      const builder = add.closest('[data-column-builder]');
      const template = builder?.querySelector('[data-column-template]');
      const list = builder?.querySelector('[data-column-list]');
      if (template && list) {
        const index = Date.now();
        const html = template.innerHTML.replaceAll('__i__', String(index));
        const holder = document.createElement('div');
        holder.innerHTML = html;
        const node = holder.firstElementChild;
        list.append(node);
        node.querySelectorAll('[data-column-row]').forEach(syncColumnRow);
        node.querySelector('input')?.focus();
      }
      return;
    }
    const remove = target?.closest('[data-column-remove]');
    if (remove) {
      const list = remove.closest('[data-column-list]');
      if (list && list.children.length > 1) {
        remove.closest('[data-column-list] > *')?.remove();
      }
    }
  });

  /* ---------- Formulaires : envoi automatique et anti double-clic ---------- */
  document.addEventListener('change', (event) => {
    const select = event.target instanceof HTMLSelectElement ? event.target : null;
    select?.closest('form[data-autosubmit]')?.requestSubmit();
    const file = event.target instanceof HTMLInputElement && event.target.matches('[data-autosubmit-file]') ? event.target : null;
    if (file?.files?.length) {
      file.form?.requestSubmit();
    }
  });
  document.addEventListener('submit', (event) => {
    const form = event.target instanceof HTMLFormElement ? event.target : null;
    if (!form || event.defaultPrevented || !form.dataset.submitLoading) {
      return;
    }
    const button = form.querySelector('button[type="submit"]');
    if (button) {
      button.disabled = true;
      button.textContent = form.dataset.submitLoading;
    }
  });

  /* ---------- Notifications : fermeture automatique après 4 s ---------- */
  const dismissToast = (toast) => {
    toast.classList.add('is-leaving');
    setTimeout(() => toast.remove(), 260);
  };
  document.querySelectorAll('[data-toast]').forEach((toast) => {
    const bar = toast.querySelector('.toast-progress');
    bar?.addEventListener('animationend', () => dismissToast(toast));
    toast.querySelector('[data-toast-close]')?.addEventListener('click', () => dismissToast(toast));
  });
  window.nbToast = (type, message) => {
    const region = document.querySelector('[data-toast-region]');
    if (!region) {
      return;
    }
    const toast = document.createElement('div');
    toast.className = 'pointer-events-auto relative w-full overflow-hidden rounded-xl border bg-surface shadow-card animate-fade-up ' + (type === 'error' ? 'border-danger/40' : 'border-accent/40');
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
    const text = document.createElement('p');
    text.className = 'p-4 pr-10 text-sm';
    text.textContent = message;
    const bar = document.createElement('span');
    bar.className = 'toast-progress absolute bottom-0 left-0 h-0.5 w-full origin-left ' + (type === 'error' ? 'bg-danger' : 'bg-accent');
    bar.addEventListener('animationend', () => dismissToast(toast));
    toast.append(text, bar);
    region.append(toast);
  };

  /* ---------- Barre latérale (tiroir sur mobile) ---------- */
  const sidebar = document.querySelector('[data-sidebar]');
  const backdrop = document.querySelector('[data-sidebar-backdrop]');
  const setSidebar = (open) => {
    if (!sidebar) {
      return;
    }
    sidebar.dataset.open = String(open);
    if (backdrop) {
      backdrop.hidden = !open;
    }
    document.querySelector('[data-sidebar-open]')?.setAttribute('aria-expanded', String(open));
    document.body.classList.toggle('overflow-hidden', open);
  };
  document.querySelector('[data-sidebar-open]')?.addEventListener('click', () => setSidebar(true));
  document.querySelector('[data-sidebar-close]')?.addEventListener('click', () => setSidebar(false));
  backdrop?.addEventListener('click', () => setSidebar(false));
  window.matchMedia('(min-width: 1024px)').addEventListener('change', (query) => {
    if (query.matches) {
      setSidebar(false);
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      setMenu(false);
      setSidebar(false);
    }
    const tab = event.target instanceof Element ? event.target.closest('[data-tab]') : null;
    if (tab && (event.key === 'ArrowRight' || event.key === 'ArrowLeft')) {
      const tabs = [...tab.closest('[data-tabs]').querySelectorAll('[data-tab]')];
      const next = tabs[(tabs.indexOf(tab) + (event.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
      selectTab(next);
      next.focus();
    }
  });

  window.matchMedia('(min-width: 768px)').addEventListener('change', (query) => {
    if (query.matches) {
      setMenu(false);
    }
  });

  /* ---------- QR code 2FA (bibliothèque qrcode-generator, dessin sur canvas) ---------- */
  const drawQr = () => {
    document.querySelectorAll('canvas[data-qr]').forEach((canvas) => {
      if (typeof window.qrcode !== 'function') {
        return;
      }
      const qr = window.qrcode(0, 'M');
      qr.addData(canvas.dataset.qr);
      qr.make();
      const count = qr.getModuleCount();
      const ratio = window.devicePixelRatio || 1;
      const size = canvas.clientWidth || 176;
      canvas.width = size * ratio;
      canvas.height = size * ratio;
      const ctx = canvas.getContext('2d');
      const cell = canvas.width / count;
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.fillStyle = '#0c0d0f';
      for (let row = 0; row < count; row += 1) {
        for (let col = 0; col < count; col += 1) {
          if (qr.isDark(row, col)) {
            ctx.fillRect(Math.floor(col * cell), Math.floor(row * cell), Math.ceil(cell), Math.ceil(cell));
          }
        }
      }
    });
  };

  /* ---------- Apparition des sections au défilement ---------- */
  const revealables = document.querySelectorAll('[data-reveal]');
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    revealables.forEach((el) => observer.observe(el));
  } else {
    revealables.forEach((el) => el.classList.add('is-visible'));
  }

  /* ---------- En-tête : ombre après défilement ---------- */
  const header = document.querySelector('[data-site-header]');
  if (header) {
    const onScroll = () => header.classList.toggle('shadow-card', window.scrollY > 8);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ---------- Titre lié aux onglets (« Utilisez NovaBase avec … ») ---------- */
  document.addEventListener('nb:tab', (event) => {
    const target = document.querySelector(`[data-tab-label-for="${event.detail.group}"]`);
    if (target) {
      target.textContent = event.detail.label;
    }
  });

  // Largeur des jauges (attribut data-percent ; les styles en ligne sont interdits par la CSP).
  document.querySelectorAll('[data-percent]').forEach((bar) => {
    bar.style.width = `${Math.max(0, Math.min(100, Number(bar.dataset.percent) || 0))}%`;
  });

  /* ---------- Grille de données : sélection de lignes pour la suppression groupée ---------- */
  // Rebranchée après chaque recherche instantanée (la grille est remplacée).
  const bindSelection = () => {
    const rowChecks = [...document.querySelectorAll('[data-row-check]')];
    const selectAll = document.querySelector('[data-select-all]');
    const sync = () => {
      const selected = rowChecks.filter((box) => box.checked).length;
      document.querySelectorAll('[data-selected-count]').forEach((el) => { el.textContent = String(selected); });
      document.querySelectorAll('[data-bulk-button]').forEach((button) => { button.disabled = selected === 0; });
      if (selectAll) {
        selectAll.checked = selected > 0 && selected === rowChecks.length;
        selectAll.indeterminate = selected > 0 && selected < rowChecks.length;
      }
    };
    rowChecks.forEach((box) => box.addEventListener('change', sync));
    selectAll?.addEventListener('change', () => {
      rowChecks.forEach((box) => { box.checked = selectAll.checked; });
      sync();
    });
    sync();
  };
  bindSelection();

  /* ---------- Recherche instantanée : la grille se met à jour pendant la frappe ---------- */
  document.querySelectorAll('form[data-live-search]').forEach((form) => {
    const spinner = form.querySelector('[data-live-spinner]');
    let timer = 0;
    let controller = null;
    const run = async () => {
      const params = new URLSearchParams(new FormData(form));
      [...params.keys()].forEach((key) => { if (params.get(key) === '') params.delete(key); });
      const url = `${form.action.split('?')[0]}${params.toString() ? `?${params}` : ''}`;
      controller?.abort();
      controller = new AbortController();
      spinner?.classList.remove('hidden');
      try {
        const response = await fetch(url, { signal: controller.signal, headers: { 'X-Requested-With': 'fetch' } });
        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
        document.querySelectorAll('[data-live-region]').forEach((region) => {
          const next = doc.querySelector(`[data-live-region="${region.dataset.liveRegion}"]`);
          if (next) region.replaceWith(next);
        });
        history.replaceState(null, '', url);
        bindSelection();
      } catch (error) {
        if (error.name !== 'AbortError') form.submit();
      } finally {
        spinner?.classList.add('hidden');
      }
    };
    form.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(run, 250);
    });
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      clearTimeout(timer);
      run();
    });
  });

  /* ---------- Ligne de saisie du tableau : focus après une insertion ou depuis le menu ---------- */
  const focusInline = () => {
    const first = document.querySelector('[data-inline-row] input:not([type=checkbox]), [data-inline-row] select');
    first?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    first?.focus({ preventScroll: true });
  };
  document.querySelector('[data-inline-autofocus]')?.focus();
  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-focus-inline]')) {
      event.target.closest('details')?.removeAttribute('open');
      focusInline();
    }
  });

  // Pas de case obligatoire vide ni de ligne entièrement vide.
  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.id !== 'inline-insert') return;
    const fields = [...form.elements].filter((el) => el.closest('[data-inline-row]'));
    const values = fields.filter((el) => el.type !== 'checkbox');
    const missing = values.filter((el) => el.hasAttribute('data-required') && el.value.trim() === '');
    const empty = values.length > 0 && values.every((el) => el.value.trim() === '');
    fields.forEach((el) => el.removeAttribute('aria-invalid'));
    const errorRow = document.querySelector('[data-inline-error]');
    if (!empty && missing.length === 0) {
      if (errorRow) errorRow.hidden = true;
      return;
    }
    event.preventDefault();
    event.stopImmediatePropagation();
    (empty ? values : missing).forEach((el) => el.setAttribute('aria-invalid', 'true'));
    if (errorRow) {
      errorRow.querySelector('[data-inline-error-text]').textContent = empty
        ? 'Ligne vide : remplissez au moins une case avant d\'insérer.'
        : `Case obligatoire vide : ${missing.map((el) => el.getAttribute('aria-label')).join(', ')}.`;
      errorRow.hidden = false;
    }
    (missing[0] ?? values[0])?.focus();
  }, true);

  /* ---------- Menus déroulants (details) et fenêtres ouvertes par un bouton ---------- */
  document.addEventListener('click', (event) => {
    document.querySelectorAll('details[data-menu][open]').forEach((menu) => {
      if (!menu.contains(event.target) || event.target.closest('[data-open-dialog], a, label')) menu.removeAttribute('open');
    });
    const opener = event.target.closest('[data-open-dialog]');
    if (opener) {
      const target = document.getElementById(opener.dataset.openDialog);
      if (target instanceof HTMLDialogElement) {
        target.showModal();
        target.querySelector('input:not([type=hidden]):not([type=checkbox]), textarea, select')?.focus();
      }
    }
    const closer = event.target.closest('[data-close-dialog]');
    if (closer) closer.closest('dialog')?.close();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') document.querySelectorAll('details[data-menu][open]').forEach((menu) => menu.removeAttribute('open'));
  });
  document.querySelectorAll('dialog[data-autoshow]').forEach((target) => {
    target.showModal();
    (target.querySelector('[aria-invalid="true"]') ?? target.querySelector('input:not([type=hidden]):not([type=checkbox]), textarea, select'))?.focus();
  });

  /* ---------- Éditeur SQL : modèles, Ctrl+Entrée, tabulation ---------- */
  const sqlEditor = document.querySelector('[data-sql-editor]');
  if (sqlEditor) {
    const sqlForm = sqlEditor.closest('form');
    document.querySelectorAll('[data-sql-snippet]').forEach((button) => {
      button.addEventListener('click', () => {
        const snippet = button.dataset.sqlSnippet ?? '';
        const current = sqlEditor.value.trimEnd();
        sqlEditor.value = current === '' ? snippet : `${current}\n\n${snippet}`;
        sqlEditor.focus();
        sqlEditor.setSelectionRange(sqlEditor.value.length, sqlEditor.value.length);
        sqlEditor.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      });
    });
    document.querySelector('[data-sql-clear]')?.addEventListener('click', () => {
      sqlEditor.value = '';
      sqlEditor.focus();
    });
    sqlEditor.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
        event.preventDefault();
        sqlForm?.requestSubmit();
      } else if (event.key === 'Tab' && !event.shiftKey) {
        event.preventDefault();
        sqlEditor.setRangeText('  ', sqlEditor.selectionStart, sqlEditor.selectionEnd, 'end');
      }
    });
  }

  applyTheme(storedTheme() ?? root.dataset.theme ?? 'dark');
  window.addEventListener('load', drawQr);

  /* ---------- Jeton CSRF ajouté aux requêtes fetch modifiantes de même origine ---------- */
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
