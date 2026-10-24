// NovaBase — graphiques de la console (Chart.js chargé localement), métriques et journal en direct.
(() => {
  'use strict';

  const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  const charts = [];
  const nf = new Intl.NumberFormat('fr-FR');
  const shortDate = (iso) => new Date(`${iso}T00:00:00Z`).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', timeZone: 'UTC' });

  const baseOptions = (unit) => ({
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 400 },
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: css('--nb-surface'),
        borderColor: css('--nb-border-strong'),
        borderWidth: 1,
        titleColor: css('--nb-fg'),
        bodyColor: css('--nb-muted'),
        padding: 10,
        displayColors: true,
        boxWidth: 8,
        boxHeight: 8,
        callbacks: { label: (ctx) => ` ${ctx.dataset.label} : ${nf.format(ctx.parsed.y)}${unit}` },
      },
    },
    scales: {
      x: { grid: { display: false }, border: { color: css('--nb-border') }, ticks: { color: css('--nb-muted'), maxTicksLimit: 8, maxRotation: 0, font: { size: 11 } } },
      y: { beginAtZero: true, grid: { color: css('--nb-border') }, border: { display: false }, ticks: { color: css('--nb-muted'), precision: 0, maxTicksLimit: 5, font: { size: 11 } } },
    },
  });

  // Graphiques statiques : <canvas data-chart='{"type":"line","label":"…","labels":[…],"values":[…]}'>
  const buildStatic = (canvas) => {
    const spec = JSON.parse(canvas.dataset.chart);
    const color = css('--nb-chart-1');
    const isBar = spec.type === 'bar';
    return new window.Chart(canvas, {
      type: isBar ? 'bar' : 'line',
      data: {
        labels: spec.labels.map(shortDate),
        datasets: [{
          label: spec.label,
          data: spec.values,
          borderColor: color,
          backgroundColor: isBar ? color : `${color}26`,
          fill: !isBar,
          borderWidth: 2,
          borderRadius: isBar ? 4 : 0,
          maxBarThickness: 18,
          tension: 0.35,
          pointRadius: 0,
          pointHoverRadius: 5,
          pointHoverBorderWidth: 2,
          pointHoverBorderColor: css('--nb-surface'),
        }],
      },
      options: baseOptions(spec.unit ?? ''),
    });
  };

  // Graphique en direct CPU / RAM (même unité : %, un seul axe).
  const live = { canvas: document.querySelector('canvas[data-live-chart]'), chart: null, labels: [], cpu: [], ram: [] };
  const buildLive = () => {
    if (!live.canvas) {
      return null;
    }
    const options = baseOptions(' %');
    options.scales.y.max = 100;
    options.plugins.legend = { display: true, position: 'top', align: 'end', labels: { color: css('--nb-muted'), boxWidth: 10, boxHeight: 10, usePointStyle: true, font: { size: 11 } } };
    return new window.Chart(live.canvas, {
      type: 'line',
      data: {
        labels: live.labels,
        datasets: [
          { label: 'CPU', data: live.cpu, borderColor: css('--nb-chart-1'), backgroundColor: css('--nb-chart-1'), borderWidth: 2, tension: 0.3, pointRadius: 0, pointHoverRadius: 4 },
          { label: 'RAM', data: live.ram, borderColor: css('--nb-chart-2'), backgroundColor: css('--nb-chart-2'), borderWidth: 2, tension: 0.3, pointRadius: 0, pointHoverRadius: 4, borderDash: [5, 4] },
        ],
      },
      options,
    });
  };

  const renderAll = () => {
    if (typeof window.Chart !== 'function') {
      return;
    }
    charts.splice(0).forEach((chart) => chart.destroy());
    document.querySelectorAll('canvas[data-chart]').forEach((canvas) => charts.push(buildStatic(canvas)));
    live.chart?.destroy();
    live.chart = buildLive();
  };

  // Jauges et chiffres serveur, interrogés toutes les 5 s.
  const levelOf = (percent) => (percent >= 90 ? 'critical' : percent >= 70 ? 'warning' : 'ok');
  const levelLabel = { ok: 'Normal', warning: 'Élevé', critical: 'Critique' };
  const levelClass = { ok: 'bg-accent', warning: 'bg-warning', critical: 'bg-danger' };
  const bytes = (value) => {
    if (value === null || value === undefined) {
      return '—';
    }
    const units = ['o', 'Ko', 'Mo', 'Go', 'To'];
    let v = value;
    let i = 0;
    while (v >= 1024 && i < units.length - 1) {
      v /= 1024;
      i += 1;
    }
    return `${v.toLocaleString('fr-FR', { maximumFractionDigits: 1 })} ${units[i]}`;
  };
  const applyMetrics = (m) => {
    [['cpu', m.cpu_percent], ['ram', m.ram_percent], ['disk', m.disk_percent]].forEach(([key, percent]) => {
      const root = document.querySelector(`[data-gauge="${key}"]`);
      if (!root || percent === null) {
        return;
      }
      const level = levelOf(percent);
      root.querySelector('[data-gauge-value]').textContent = `${percent.toLocaleString('fr-FR')} %`;
      const bar = root.querySelector('[data-gauge-bar]');
      bar.style.width = `${Math.min(100, percent)}%`;
      bar.className = levelClass[level];
      root.querySelector('[data-gauge-level]').textContent = levelLabel[level];
    });
    const set = (sel, text) => {
      const el = document.querySelector(sel);
      if (el) {
        el.textContent = text;
      }
    };
    set('[data-metric="ram-detail"]', `${bytes(m.ram_used)} / ${bytes(m.ram_total)}`);
    set('[data-metric="disk-detail"]', `${bytes(m.disk_used)} / ${bytes(m.disk_total)}`);
    set('[data-metric="load"]', m.load ? m.load.join(' · ') : '—');
    set('[data-metric="updated"]', new Date(m.time).toLocaleTimeString('fr-FR'));
    if (live.chart && (m.cpu_percent !== null || m.ram_percent !== null)) {
      live.labels.push(new Date(m.time).toLocaleTimeString('fr-FR', { minute: '2-digit', second: '2-digit' }));
      live.cpu.push(m.cpu_percent);
      live.ram.push(m.ram_percent);
      if (live.labels.length > 36) {
        live.labels.shift();
        live.cpu.shift();
        live.ram.shift();
      }
      live.chart.update('none');
    }
  };
  const metricsRoot = document.querySelector('[data-metrics-url]');
  if (metricsRoot) {
    const poll = async () => {
      if (document.hidden) {
        return;
      }
      try {
        const res = await fetch(metricsRoot.dataset.metricsUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (res.ok) {
          applyMetrics(await res.json());
        }
      } catch {
        // Réseau momentanément indisponible : nouvel essai au prochain cycle.
      }
    };
    poll();
    setInterval(poll, 5000);
  }

  // Journal des connexions en direct.
  const feed = document.querySelector('[data-login-feed]');
  if (feed) {
    const body = feed.querySelector('tbody');
    const toggle = document.querySelector('[data-live-toggle]');
    const badge = { login: 'text-accent-text border-accent/40', logout: 'text-muted border-line-strong', invitation_accepted: 'text-accent-text border-accent/40' };
    const cell = (text, cls = '') => {
      const td = document.createElement('td');
      td.className = `px-4 py-3 align-top ${cls}`;
      td.textContent = text;
      return td;
    };
    const poll = async () => {
      if (document.hidden || (toggle && !toggle.checked)) {
        return;
      }
      try {
        const res = await fetch(`${feed.dataset.url}&after=${feed.dataset.after}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!res.ok) {
          return;
        }
        const { events } = await res.json();
        events.forEach((ev) => {
          const tr = document.createElement('tr');
          tr.className = 'bg-accent-soft/60 transition-colors duration-[2000ms]';
          const who = cell(ev.user || ev.email || '—', 'font-medium');
          if (ev.user && ev.email) {
            const small = document.createElement('span');
            small.className = 'block text-xs font-normal text-muted';
            small.textContent = ev.email;
            who.append(small);
          }
          const status = document.createElement('td');
          status.className = 'px-4 py-3 align-top';
          const pill = document.createElement('span');
          pill.className = `badge py-0 ${badge[ev.event] ?? 'text-danger border-danger/40'}`;
          pill.textContent = ev.event_label;
          status.append(pill);
          tr.append(who, status, cell(ev.created_at.replace(' ', ' à '), 'font-mono text-xs whitespace-nowrap'), cell(ev.location), cell(`${ev.browser} · ${ev.os}`), cell(ev.ip, 'font-mono text-xs'));
          body.prepend(tr);
          setTimeout(() => tr.classList.remove('bg-accent-soft/60'), 50);
          feed.dataset.after = String(ev.id);
        });
        document.querySelector('[data-feed-empty]')?.remove();
      } catch {
        // Nouvel essai au prochain cycle.
      }
    };
    setInterval(poll, 8000);
  }

  window.addEventListener('load', renderAll);
  document.addEventListener('nb:theme', () => setTimeout(renderAll, 30));
})();
