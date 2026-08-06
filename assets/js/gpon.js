/* ============================================================
   GPON Module — Main JavaScript
   ============================================================ */
'use strict';

const GPON = (() => {
  /* ── State ─────────────────────────────────────────────── */
  const state = {
    filters: {
      uf:           [],
      empresa:      [],
      localidade:   [],
      gpon:         [],
      status_prazo: [],
    },
    table:       null,
    data:        [],
    loading:     false,
    reincPeriod: '',
  };

  const SF_CONFIG = [
    { key: 'uf',           listId: 'sf-list-uf',         badgeId: 'sf-badge-uf',         bodyId: 'sf-body-uf'         },
    { key: 'empresa',      listId: 'sf-list-empresa',    badgeId: 'sf-badge-empresa',    bodyId: 'sf-body-empresa'    },
    { key: 'localidade',   listId: 'sf-list-localidade', badgeId: 'sf-badge-localidade', bodyId: 'sf-body-localidade' },
    { key: 'gpon',         listId: 'sf-list-gpon',       badgeId: 'sf-badge-gpon',       bodyId: 'sf-body-gpon'       },
    { key: 'status_prazo', listId: 'sf-list-sp',         badgeId: 'sf-badge-sp',         bodyId: 'sf-body-sp'         },
  ];

  /* ── Flash messages ─────────────────────────────────────── */
  function flash(msg, type = 'info', duration = 4000) {
    const bar = document.getElementById('flash-bar');
    if (!bar) return;
    const el = document.createElement('div');
    el.className = `flash-msg ${type}`;
    el.textContent = msg;
    bar.appendChild(el);
    setTimeout(() => el.remove(), duration);
  }

  function flashToast(title, details, type, duration) {
    type     = type     || 'success';
    duration = duration || 5000;
    var bar = document.getElementById('flash-bar');
    if (!bar) return;
    var iconMap = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', info: 'bi-info-circle-fill' };
    var el = document.createElement('div');
    el.className = 'flash-toast ' + type;
    el.innerHTML =
      '<div class="flash-toast-header">' +
        '<i class="bi ' + (iconMap[type] || 'bi-info-circle-fill') + ' flash-toast-icon"></i>' +
        '<span class="flash-toast-title">' + escHtml(title) + '</span>' +
      '</div>' +
      '<div class="flash-toast-body">' +
        (details || []).map(function(d) { return escHtml(d); }).join('<br>') +
      '</div>';
    bar.appendChild(el);
    setTimeout(function() {
      el.style.opacity = '0';
      setTimeout(function() { el.remove(); }, 320);
    }, duration);
  }

  /* ── Format aging (minutes → "2h 15m", "1d 4h") ─────────── */
  function formatAging(mins) {
    if (mins === null || mins === undefined || mins === '') return '—';
    const m = parseInt(mins, 10);
    if (isNaN(m) || m < 0) return '—';
    if (m < 60) return `${m}m`;
    if (m < 1440) {
      const h = Math.floor(m / 60);
      const rm = m % 60;
      return rm > 0 ? `${h}h ${rm}m` : `${h}h`;
    }
    const d = Math.floor(m / 1440);
    const rh = Math.floor((m % 1440) / 60);
    return rh > 0 ? `${d}d ${rh}h` : `${d}d`;
  }

  /* ── Format prazo (minutes → "Restam 2h 15m" / "Excedido em 5h") */
  function formatPrazo(mins) {
    if (mins === null || mins === undefined || mins === '') return '—';
    const m = parseInt(mins, 10);
    if (isNaN(m)) return '—';
    if (m >= 0) {
      return 'Restam ' + formatAging(m);
    }
    return 'Excedido em ' + formatAging(Math.abs(m));
  }

  /* ── Format date ─────────────────────────────────────────── */
  function fmtDate(str) {
    if (!str) return '—';
    const m = str.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    if (!m) return str;
    return m[3] + '/' + m[2] + '/' + m[1] + ', ' + m[4] + ':' + m[5];
  }

  /* Formato reduzido para timeline: "10/05 - 19:57" */
  function fmtDateShort(str) {
    if (!str) return '—';
    const m = str.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    if (!m) return str;
    return m[3] + '/' + m[2] + ' - ' + m[4] + ':' + m[5];
  }

  /* ── Badge builders ──────────────────────────────────────── */
  function badgeStatus(status) {
    if (!status) return '<span class="text-muted">—</span>';
    const s = status.toLowerCase();
    let cls = 'badge-andamento';
    if (s.includes('encerrad') || s.includes('fechad') || s.includes('resolvid')) cls = 'badge-encerrada';
    else if (s.includes('aberta') || s.includes('aberto') || s.includes('pendente')) cls = 'badge-aberta';
    return `<span class="badge-status ${cls}">${escHtml(status)}</span>`;
  }

  function badgePrazo(sp, previsao, previsaoStatus) {
    if (!sp) return '<span class="text-muted">—</span>';
    const cls = {
      'Dentro do Prazo': 'badge-dentro-prazo',
      'Atenção':         'badge-proximo-prazo',
      'Fora do Prazo':   'badge-fora-prazo',
    }[sp] || 'badge-nao';
    let html = `<span class="badge-status ${cls}">${escHtml(sp)}</span>`;
    if (previsao) {
      const m = String(previsao).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
      const dateFmt = m ? `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}` : previsao;
      const riskMap = {
        ok:      { color: '#16a34a', tip: 'Previsão dentro do SLA'         },
        atencao: { color: '#d97706', tip: 'Previsão próxima ao vencimento' },
        critica: { color: '#dc2626', tip: 'Previsão excede o SLA'          },
      };
      const risk    = riskMap[previsaoStatus] || { color: null, tip: 'Previsão cadastrada' };
      const style   = risk.color ? ` style="color:${risk.color}"` : '';
      const tooltip = `${risk.tip} · ${dateFmt}`;
      html += ` <i class="bi bi-clock previsao-icon"${style} title="${tooltip}" aria-label="${tooltip}"></i>`;
    }
    return html;
  }

  function badgePrevisao(prev, status) {
    if (!prev) return '<span class="text-muted">—</span>';
    const m = String(prev).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    if (!m) return '<span class="text-muted">—</span>';
    const hora    = m[4] + ':' + m[5];
    const dateFmt = `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}`;
    const colorMap = { ok: '#16a34a', atencao: '#d97706', critica: '#dc2626' };
    const color = colorMap[status] || '#0891b2';
    return `<span style="color:${color};font-weight:600;font-size:11px;font-family:monospace;white-space:nowrap" title="Previsão: ${dateFmt}">${hora}</span>`;
  }

  function badgeRep(val) {
    const v = String(val);
    if (v === '1' || v.toLowerCase() === 'sim') {
      return '<span class="badge-status badge-sim">Sim</span>';
    }
    return '<span class="badge-status badge-nao">Não</span>';
  }

  function badgeReincidencia(max, detail, recente) {
    var base = (max && max > 0)
      ? '<span class="badge-reinc badge-reinc-clickable ' + (max >= 7 ? 'reinc-vermelho' : max >= 4 ? 'reinc-laranja' : max >= 2 ? 'reinc-amarelo' : 'reinc-cinza') + '" title="' + escHtml((detail || []).map(function(d) { return d.sp + ' → ' + d.cnt + 'x'; }).join('\n') + '\nClique para ver hist\xf3rico') + '">' + max + 'x</span>'
      : '<span class="text-muted">—</span>';
    if (!recente) return base;
    var isCrit = recente.tipo === 'critica';
    var icon   = isCrit ? '🔥' : '⚠';
    var tip2   = escHtml((isCrit ? 'Cr\xedtico' : 'Recente') + ': retornou em ' + recente.horas + 'h ap\xf3s encerramento');
    return base + '<span class="reinc-alerta reinc-alerta-' + recente.tipo + '" title="' + tip2 + '">' + icon + '</span>';
  }

  function agingHtml(mins, statusPrazo) {
    if (mins === null || mins === undefined || mins === '') return '<span class="aging-value">—</span>';
    let cls = 'ok';
    if (statusPrazo === 'Fora do Prazo') cls = 'danger';
    else if (statusPrazo === 'Atenção')  cls = 'warning';
    return `<span class="aging-value ${cls}">${formatAging(mins)}</span>`;
  }

  /* ── Escape HTML ─────────────────────────────────────────── */
  function escHtml(s) {
    if (!s) return '';
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  /* ── Row class based on status prazo ─────────────────────── */
  function rowClass(row) {
    if (!row.status) return '';
    const st = row.status.toLowerCase();
    if (st.includes('encerrad') || st.includes('fechad') || st.includes('resolvid')) return 'row-encerrada';
    const sp = row.status_prazo || '';
    if (sp === 'Fora do Prazo') return 'row-fora-prazo';
    if (sp === 'Atenção')       return 'row-proximo-prazo';
    return 'row-dentro-prazo';
  }

  /* ── Load KPI cards ──────────────────────────────────────── */
  async function loadStats() {
    const reincEl = document.getElementById('kpi-reinc');
    if (reincEl) reincEl.classList.add('kpi-reinc-updating');
    try {
      const params = buildFilterParams();
      const res = await fetch(`${BASE_PATH}/api/stats?${params}`);
      const data = await res.json();
      if (!data.ok) return;
      const s = data.stats;
      safeSet('kpi-abertas',   s.abertas    ?? 0);
      safeSet('kpi-atraso',    s.fora_prazo ?? 0);
      safeSet('kpi-prazo',     s.dentro_prazo ?? 0);
      safeSet('kpi-proximo',   s.proximo_prazo ?? 0);
      // KPI Reincidência — combinação GPON + Splitter, filtrado igual à grid
      if (reincEl) {
        const r = s.reincidencia;
        var html;
        if (!r) {
          html = '<span class="kpi-reinc-empty">Sem dados</span>';
        } else {
          var clsMap = { alta: 'reinc-vermelho', media: 'reinc-amarelo', baixa: 'reinc-verde' };
          var cls    = clsMap[r.criticidade] || 'reinc-cinza';
          var timeHtml = r.penultima_ocorrencia
            ? '<div class="kpi-reinc-time">Última OC: ' + fmtDateShort(r.penultima_ocorrencia) + '</div>'
            : '';
          html =
            '<div class="kpi-reinc-label">Reincidência Crítica</div>' +
            '<div class="kpi-reinc-gpon">' + escHtml(r.gpon) + '</div>' +
            '<span class="badge-reinc ' + cls + ' kpi-reinc-sp">' +
              escHtml(r.sp) + ' <strong>' + r.total + 'x</strong>' +
            '</span>' +
            timeHtml;
        }
        reincEl.classList.remove('kpi-reinc-updating');
        reincEl.innerHTML = html;
      }

      const empresasEl = document.getElementById('kpi-empresas');
      if (empresasEl) {
        const lista = s.empresas || [];
        const total = lista.reduce(function(sum, e) { return sum + (parseInt(e.count, 10) || 0); }, 0);
        safeSet('kpi-empresas-total', total || '—');

        var empColors = { 'ABILITY': '#7c3aed', 'ONDACOM': '#ea580c', 'VIVO': '#0e7490' };
        var palette   = ['#16a34a', '#be185d', '#1a6bcc', '#d97706'];
        var pi = 0;

        if (lista.length === 0) {
          empresasEl.innerHTML = '<span class="kpi-empresa-loading">—</span>';
        } else {
          empresasEl.innerHTML = lista.map(function(e) {
            var nome = (e.empresa || '').toUpperCase();
            var col  = empColors[nome] || palette[pi++ % palette.length];
            return '<span class="kpi-empresa-row">' +
              '<span class="kpi-empresa-dot" style="background:' + col + '"></span>' +
              '<span class="kpi-empresa-nome">' + (e.empresa || '—') + '</span>' +
              '<span class="kpi-empresa-val" style="color:' + col + '">' + e.count + '</span>' +
              '</span>';
          }).join('');
        }
      }
    } catch (e) {
      console.warn('loadStats error:', e);
      if (reincEl) reincEl.classList.remove('kpi-reinc-updating');
    }
  }

  function safeSet(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  }

  /* ── Build filter query params ───────────────────────────── */
  function buildFilterParams() {
    const p = new URLSearchParams();
    for (const [k, v] of Object.entries(state.filters)) {
      if (v.length > 0) p.set(k, v.join('|||'));
    }
    const chkEnc      = document.getElementById('chk-enc');
    if (chkEnc && chkEnc.checked) p.set('incluir_encerradas', '1');
    const chkFibrasil = document.getElementById('chk-ocultar-fibrasil');
    if (chkFibrasil && chkFibrasil.checked) p.set('ocultar_fibrasil', '1');
    return p.toString();
  }

  /* ── Sidebar Filters ────────────────────────────────────── */
  async function loadFilterOptions() {
    await refreshFilters(false);
  }

  async function refreshFilters(shouldReload) {
    try {
      const params = buildFilterParams();
      const url    = `${BASE_PATH}/api/filters${params ? '?' + params : ''}`;
      const res    = await fetch(url);
      const data   = await res.json();
      if (!data.ok) return;
      SF_CONFIG.forEach(cfg => {
        const items     = data[cfg.key] || [];
        const validVals = items.map(i => i.value);
        // Remove seleções que já não existem nos dados filtrados
        state.filters[cfg.key] = state.filters[cfg.key].filter(v => validVals.includes(v));
        renderSfItems(cfg.listId, items, cfg.key);
        updateSfBadge(cfg.key);
      });
      updateSfFooter();
      if (shouldReload) await loadData();
    } catch (e) {
      console.warn('refreshFilters error:', e);
    }
  }

  function renderSfItems(listId, items, filterKey) {
    const list = document.getElementById(listId);
    if (!list) return;
    if (!items || !items.length) {
      list.innerHTML = '<div style="padding:8px 10px;font-size:11px;color:var(--gpon-muted)">Nenhum item</div>';
      return;
    }
    list.innerHTML = items.map(item => {
      const label    = item.value || '—';
      const count    = item.count || 0;
      const isActive = state.filters[filterKey].includes(label);
      return `<div class="sf-item${isActive ? ' active' : ''}" data-filter="${filterKey}" data-value="${escHtml(label)}" title="${escHtml(label)}">
        <span class="sf-check"><i class="bi bi-check"></i></span>
        <span class="sf-item-label">${escHtml(label)}</span>
        <span class="sf-item-count">${count}</span>
      </div>`;
    }).join('');
    list.querySelectorAll('.sf-item').forEach(item => {
      item.addEventListener('click', () => toggleFilter(filterKey, item.dataset.value));
    });
  }

  function toggleFilter(key, value) {
    const idx = state.filters[key].indexOf(value);
    if (idx >= 0) {
      state.filters[key].splice(idx, 1);
    } else {
      state.filters[key].push(value);
    }
    // Persiste a seleção de UF entre recargas de página
    if (key === 'uf') {
      try { localStorage.setItem('gpon_uf', JSON.stringify(state.filters.uf)); } catch(e) {}
    }
    // Atualização optimista: reflete o clique imediatamente antes da resposta
    document.querySelectorAll(`.sf-item[data-filter="${key}"]`).forEach(item => {
      item.classList.toggle('active', state.filters[key].includes(item.dataset.value));
    });
    updateSfBadge(key);
    updateSfFooter();
    refreshFilters(true);
  }

  function updateSfBadge(key) {
    const cfg   = SF_CONFIG.find(c => c.key === key);
    if (!cfg) return;
    const badge = document.getElementById(cfg.badgeId);
    if (!badge) return;
    const count = state.filters[key].length;
    badge.textContent = count;
    badge.classList.toggle('visible', count > 0);
  }

  function updateSfFooter() {
    const el = document.getElementById('sf-active-info');
    if (!el) return;
    const total = Object.values(state.filters).reduce((s, v) => s + v.length, 0);
    if (total === 0) {
      el.textContent = 'Nenhum filtro ativo';
      el.classList.remove('has-filters');
    } else {
      el.textContent = `${total} filtro${total > 1 ? 's' : ''} ativo${total > 1 ? 's' : ''}`;
      el.classList.add('has-filters');
    }
  }

  function initSfCollapse() {
    document.querySelectorAll('.sf-card').forEach(card => {
      const key    = card.dataset.sfKey;
      const header = card.querySelector('.sf-card-header');
      const body   = card.querySelector('.sf-card-body');
      if (!header || !body) return;
      let collapsed = false;
      try { collapsed = localStorage.getItem('gpon_sf_' + key) === '1'; } catch(e) {}
      if (collapsed) { card.classList.add('sf-collapsed'); body.classList.add('collapsed'); }
      header.addEventListener('click', () => {
        const now = card.classList.toggle('sf-collapsed');
        body.classList.toggle('collapsed', now);
        try { localStorage.setItem('gpon_sf_' + key, now ? '1' : '0'); } catch(e) {}
      });
    });
  }

  function initSfSearch() {
    document.querySelectorAll('.sf-search').forEach(input => {
      const listId = input.dataset.sfList;
      if (!listId) return;
      input.addEventListener('input', () => {
        const q    = input.value.toLowerCase().trim();
        const list = document.getElementById(listId);
        if (!list) return;
        list.querySelectorAll('.sf-item').forEach(item => {
          item.style.display = (!q || (item.dataset.value || '').toLowerCase().includes(q)) ? '' : 'none';
        });
      });
    });
  }

  /* ── Load table data ─────────────────────────────────────── */
  function updateMetricBar() {
    var rows     = state.data || [];
    var clientes = 0;
    var gponSet  = new Set();
    var spSet    = new Set();

    rows.forEach(function(row) {
      var af = parseInt(row.afetacao, 10);
      if (!isNaN(af) && af > 0) clientes += af;
      var g = (row.gpon || '').trim();
      if (g) gponSet.add(g);
      (row.splitters || '').split(',').forEach(function(s) {
        s = s.trim(); if (s) spSet.add(s);
      });
    });

    var f = function(n) { return n.toLocaleString('pt-BR'); };
    function chip(icon, val, lbl, tip) {
      return '<span class="om-chip" title="' + tip + '">' +
        '<i class="bi ' + icon + '"></i>' +
        '<strong>' + f(val) + '</strong>' +
        '<span>' + lbl + '</span>' +
        '</span>';
    }

    var html = chip('bi-clipboard2-pulse', rows.length,  'OCs',       'Ocorrências ativas')
             + chip('bi-people',           clientes,     'Clientes',  'Clientes afetados')
             + chip('bi-hdd-network',      gponSet.size, 'OLTs',      'OLTs impactadas')
             + chip('bi-diagram-3',        spSet.size,   'Splitters', 'Splitters afetados');

    var el = document.querySelector('.ops-metric-inline');
    if (el) el.innerHTML = html;
  }

  async function loadData() {
    if (state.loading) return;
    state.loading = true;
    const params = buildFilterParams();
    try {
      const res = await fetch(`${BASE_PATH}/api/data?${params}`);
      const data = await res.json();
      if (!data.ok) throw new Error(data.message || 'Erro ao carregar dados');
      state.data = data.rows || [];
      renderTable(state.data);
      updateMetricBar();
      updateTableTitle();
      loadStats();
      if (state.reincPeriod !== '') fetchReincCounts(state.reincPeriod, false);
    } catch (e) {
      flash('Erro ao carregar dados: ' + e.message, 'error');
    } finally {
      state.loading = false;
    }
  }

  /* ── Render DataTable ────────────────────────────────────── */
  function renderTable(rows) {
    if (state.table) {
      state.table.clear();
      state.table.rows.add(rows);
      state.table.draw();
      // Update row classes
      state.table.rows().every(function() {
        const node = this.node();
        if (!node) return;
        const row = this.data();
        $(node).removeClass('row-fora-prazo row-proximo-prazo row-dentro-prazo row-encerrada');
        $(node).addClass(rowClass(row));
      });
      return;
    }

    state.table = $('#gpon-table').DataTable({
      data: rows,
      pageLength: 25,
      lengthMenu: [[15, 25, 50, 100, -1], [15, 25, 50, 100, 'Todos']],
      scrollX: true,
      fixedColumns: {
        left: 2,
        right: 1,
      },
      language: {
        url: '/assets/js/pt-BR.json',
      },
      dom: '<"table-card-header"<"table-card-title d-flex align-center gap-4"><"ops-metric-inline"><"table-actions"Bf>>rt<"d-flex justify-content-between align-items-center mt-2"lip>',
      buttons: [
        {
          text: '<i class="bi bi-file-earmark-excel"></i> Excel',
          className: 'tbtn success',
          action: function() {
            const params = buildFilterParams();
            window.location.href = BASE_PATH + '/exportar' + (params ? '?' + params : '');
          },
        },
        {
          extend: 'colvis',
          text: '<i class="bi bi-layout-three-columns"></i> Colunas',
          className: 'tbtn',
        },
      ],
      initComplete: function() {
        var $actions = $('#gpon-table_wrapper .table-actions');
        $actions.append(
          '<label class="enc-toggle-lbl" title="Incluir ocorrências com status Fechado na pesquisa">' +
          '<input type="checkbox" id="chk-enc" class="enc-chk">' +
          '<span>Encerradas</span>' +
          '</label>'
        );
        $('#chk-enc').on('change', function() { loadData(); });
        $('#gpon-table_filter input[type=search]')
          .attr('placeholder', 'Buscar OC');
        setTimeout(updateMetricBar, 0);
      },
      columnDefs: [
        { targets: '_all', defaultContent: '—' },
        { targets: [0], width: '110px' },  // OC
      ],
      columns: [
        {
          data: 'oc',
          title: 'OC',
          render: (d) => d ? `<span class="mono fw-700">${escHtml(d)}</span>` : '—',
        },
        {
          data: 'ta',
          title: 'TA',
          render: (d, type) => {
            if (type !== 'display') return d || '';
            if (!d) return '—';
            return `<a href="https://sigitm.vivo.com.br/app/app.jsp#TA=${escHtml(d)}" target="_blank" rel="noopener noreferrer" class="ta-link" title="Abrir TA no SIGITM"><span class="mono">${escHtml(d)}</span></a>`;
          },
        },
        {
          data: 'localidade',
          title: 'Cidade',
          render: (d) => d ? `<span title="${escHtml(d)}">${escHtml(truncate(d, 25))}</span>` : '—',
        },
        {
          data: 'data_criacao',
          title: 'Abertura',
          render: (d) => d ? `<span>${fmtDate(d)}</span>` : '—',
        },
        {
          data: 'gpon',
          title: 'Gpon',
          render: (d) => d ? `<span class="mono">${escHtml(d)}</span>` : '—',
        },
        {
          data: 'splitters',
          title: 'SP',
          render: (d, type, row) => {
            if (!d) return type === 'display' ? '—' : '';
            if (type !== 'display') return d;
            const parts = d.split(',').map(s => s.trim()).filter(Boolean);
            const cnt = parts.length;
            const clr = cnt === 1
              ? { bg: '#f1f5f9', fg: '#475569' }
              : cnt <= 5
              ? { bg: '#fef3c7', fg: '#b45309' }
              : { bg: '#fee2e2', fg: '#dc2626' };
            // Reutiliza reincidencia_detail (mesma fonte da coluna Reinc)
            const detailMap = {};
            (row.reincidencia_detail || []).forEach(item => { detailMap[item.sp] = item.cnt; });
            // Ordena pelo maior reincidente (mesma lógica do reincidencia_max)
            const sorted = parts.slice().sort((a, b) => (detailMap[b] || 0) - (detailMap[a] || 0));
            const tooltipLines = sorted.map(sp => {
              const r = detailMap[sp];
              return escHtml(sp) + (r ? ' → ' + r + 'x' : '');
            });
            const tooltip = tooltipLines.join('&#10;');
            const first = escHtml(sorted[0]);
            const extra = cnt > 1 ? ` <span style="color:#94a3b8;font-size:9px">(+${cnt - 1})</span>` : '';
            return `<span title="${tooltip}" style="white-space:nowrap;cursor:default"><span class="badge" style="background:${clr.bg};color:${clr.fg};font-size:10px;font-weight:600;font-family:monospace">${first}</span>${extra}</span>`;
          },
        },
        {
          data: 'reincidencia_max',
          title: 'Reinc.',
          render: (d, type, row) => type !== 'display' ? (d || 0) : badgeReincidencia(d, row.reincidencia_detail, row.reincidencia_recente),
        },
        {
          data: 'afetacao',
          title: 'CLI',
          render: (d) => d ? `<span title="${escHtml(d)}">${escHtml(truncate(d, 30))}</span>` : '—',
        },
        {
          data: 'status_prazo',
          title: 'SLA',
          render: (d, _type, row) => badgePrazo(d, row.previsao_finalizacao, row.previsao_status),
        },
        {
          data: 'previsao_finalizacao',
          title: 'Prev.',
          render: (d, type, row) => type !== 'display' ? (d || '') : badgePrevisao(d, row.previsao_status),
        },
        {
          data: 'prazo_abertas',
          title: 'Prazo',
          render: (d) => {
            if (d === null || d === undefined || d === '') return '—';
            const m = parseInt(d, 10);
            if (isNaN(m)) return '—';
            return formatPrazo(m);
          },
        },
        {
          data: 'aging_abertos',
          title: 'Tempo',
          render: (d) => {
            if (d === null || d === undefined || d === '') return '—';
            return formatAging(parseInt(d, 10));
          },
        },
        {
          data: 'empresa',
          title: 'Empresa',
          render: (d) => d ? escHtml(d) : '—',
        },
        {
          data: null,
          title: 'Ações',
          orderable: false,
          className: 'text-center',
          render: (_, __, row) =>
            `<button class="action-btn history${row.tem_comentarios > 0 ? ' has-hist' : ''}" onclick="GPON.openHistory(${row.id})" title="${row.tem_comentarios > 0 ? 'Possui histórico' : 'Histórico'}"><i class="bi bi-clock-history"></i></button>`,
        },
      ],
      createdRow(row, data) {
        $(row).addClass(rowClass(data));
        $(row).attr('title', 'Duplo clique para visualizar');
      },
      order: [[2, 'desc']], // sort by Abertura desc
    });

    // Abrir modal View ao dar duplo clique na linha
    $('#gpon-table tbody').on('dblclick', 'tr', function(e) {
      if ($(e.target).closest('button').length) return;
      const data = state.table.row(this).data();
      if (data) openView(data.id);
    });

    // Restaurar preferência de colunas salva
    var savedCols = JSON.parse(localStorage.getItem('gpon_colvis') || 'null');
    if (savedCols && Array.isArray(savedCols) && savedCols.length === state.table.columns().count()) {
      savedCols.forEach(function(vis, idx) {
        state.table.column(idx).visible(vis, false);
      });
      state.table.draw(false);
    }

    // Salvar preferência sempre que o usuário alterar a visibilidade
    state.table.on('column-visibility.dt', function() {
      var vis = [];
      state.table.columns().every(function() { vis.push(this.visible()); });
      localStorage.setItem('gpon_colvis', JSON.stringify(vis));
    });

    // Remover espaço reservado da scrollbar quando não há overflow horizontal
    function adjustScrollSpace() {
      var body = document.querySelector('.dataTables_scrollBody');
      if (!body) return;
      if (body.scrollWidth <= body.clientWidth) {
        body.classList.add('no-hscroll');
      } else {
        body.classList.remove('no-hscroll');
      }
    }
    state.table.on('draw.dt column-visibility.dt', adjustScrollSpace);
    adjustScrollSpace();
  }

  function truncate(s, n) {
    return s && s.length > n ? s.substring(0, n) + '…' : s;
  }

  /* ── Reload data and stats ───────────────────────────────── */
  function reloadData() {
    loadData();
  }

  /* ── Título dinâmico da tabela por UF ───────────────────── */
  function updateTableTitle() {
    const badge = document.getElementById('table-uf-badge');
    if (!badge) return;
    const ufs = state.filters.uf || [];
    if (!ufs.length) {
      badge.textContent   = '';
      badge.style.display = 'none';
      return;
    }
    badge.textContent   = '[' + ufs.join(' • ') + ']';
    badge.style.display = '';
  }

  /* ── Reincidência: seletor de período ───────────────────── */
  function applyReincCounts(counts) {
    if (!state.table) return;
    state.table.rows().every(function() {
      const d       = this.data();
      const gpon    = (d.gpon      || '').trim();
      const splits  = (d.splitters || '').trim();
      const detail  = [];
      let   maxCnt  = 0;
      if (gpon && splits) {
        splits.split(/\s*,\s*/).forEach(sp => {
          sp = sp.trim();
          if (!sp) return;
          const cnt = counts[gpon + '|||' + sp] || 0;
          detail.push({ sp, cnt });
          if (cnt > maxCnt) maxCnt = cnt;
        });
      }
      d.reincidencia_max    = maxCnt;
      d.reincidencia_detail = detail;
      this.invalidate();
    });
    state.table.draw(false);
    updateKpiReinc();
  }

  function updateKpiReinc() {
    const reincEl = document.getElementById('kpi-reinc');
    if (!reincEl || !state.data || !state.data.length) return;

    // Encontra o pior combo (maior contagem) entre as linhas filtradas
    var best = null;
    state.data.forEach(function(row) {
      (row.reincidencia_detail || []).forEach(function(d) {
        if (!best || d.cnt > best.cnt) {
          best = { gpon: row.gpon, sp: d.sp, cnt: d.cnt };
        }
      });
    });

    if (!best || best.cnt < 2) {
      reincEl.innerHTML = '<span class="kpi-reinc-empty">Sem reincidências</span>';
      return;
    }

    var cls = best.cnt >= 7 ? 'reinc-vermelho'
            : best.cnt >= 4 ? 'reinc-laranja'
            : 'reinc-amarelo';

    reincEl.innerHTML =
      '<div class="kpi-reinc-label">Reincidência Crítica</div>' +
      '<div class="kpi-reinc-gpon">' + escHtml(best.gpon) + '</div>' +
      '<span class="badge-reinc ' + cls + ' kpi-reinc-sp">' +
        escHtml(best.sp) + ' <strong>' + best.cnt + 'x</strong>' +
      '</span>';
  }

  function fetchReincCounts(period, showLoading = true) {
    const toolbar = document.getElementById('reinc-period-toolbar');
    if (showLoading && toolbar) toolbar.classList.add('loading');
    const fp    = buildFilterParams();
    const fib   = fp.split('&').find(p => p.startsWith('ocultar_fibrasil=')) || '';
    const parts = [];
    if (period) parts.push('period=' + encodeURIComponent(period));
    if (fib)    parts.push(fib);
    const qs = parts.length ? '?' + parts.join('&') : '';
    fetch(BASE_PATH + '/api/reinc-counts' + qs)
      .then(r => r.json())
      .then(data => { if (data.ok) applyReincCounts(data.counts); })
      .catch(e => console.warn('reinc-counts error', e))
      .then(() => { if (toolbar) toolbar.classList.remove('loading'); });
  }

  function initReincPeriod() {
    const toolbar = document.getElementById('reinc-period-toolbar');
    if (!toolbar) return;

    let saved = null;
    try { saved = localStorage.getItem('gpon_reinc_period'); } catch(e) {}
    const period = ['', '7d', '15d', '30d'].includes(saved) ? saved : '30d';
    state.reincPeriod = period;

    toolbar.querySelectorAll('.reinc-period-btn').forEach(btn => {
      btn.classList.toggle('active', (btn.dataset.period || '') === period);
    });

    fetchReincCounts(period);

    toolbar.addEventListener('click', e => {
      const btn = e.target.closest('.reinc-period-btn');
      if (!btn) return;
      const period = btn.dataset.period || '';
      toolbar.querySelectorAll('.reinc-period-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.reincPeriod = period;
      try { localStorage.setItem('gpon_reinc_period', period); } catch(e) {}
      fetchReincCounts(period);
    });
  }

  /* ── Modal Histórico GPON+SP (compartilhado com /analise) ─── */
  var _dtHistorico = null;
  var _histGpon    = '';
  var _histSp      = '';

  function extractMainSp(raw) {
    var m = String(raw).match(/SP\d+/i);
    return m ? m[0].toUpperCase() : raw;
  }

  function _fmtAging(h) {
    if (h == null) return '—';
    h = parseInt(h, 10);
    if (h < 24) return h + 'h';
    var d = Math.floor(h / 24), r = h % 24;
    return r > 0 ? d + 'd ' + r + 'h' : d + 'd';
  }

  function _histSlaHistBadge(status) {
    if (!status) return '<span class="text-muted">—</span>';
    var cls    = { ok: 'badge-dentro-prazo', atencao: 'badge-proximo-prazo', violado: 'badge-fora-prazo' }[status] || 'badge-nao';
    var labels = { ok: 'No Prazo', atencao: 'Aten\xe7\xe3o', violado: 'Fora do Prazo' };
    return '<span class="badge-status ' + cls + '">' + (labels[status] || escHtml(status)) + '</span>';
  }

  function _histStatusBadge(st) {
    if (!st) return '—';
    var aberto = /aberto|open|ativo/i.test(st);
    return '<span class="badge-status ' + (aberto ? 'badge-aberta' : 'badge-encerrada') + '">' + escHtml(st) + '</span>';
  }

  function _histFmtDt(iso) {
    if (!iso) return '—';
    var m = iso.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    return m ? m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] + ':' + m[5] : iso;
  }

  function _fetchHistorico(gpon, sp, periodo) {
    var loading   = document.getElementById('hist-loading');
    var tableWrap = document.getElementById('hist-table-wrap');
    if (!loading || !tableWrap) return;
    var pqs = periodo ? '&periodo=' + encodeURIComponent(periodo) : '';
    // Propaga flags booleanos ativos (ocultar_fibrasil e futuros)
    buildFilterParams().split('&').forEach(function(p) {
      if (p.startsWith('ocultar_fibrasil=')) pqs += '&' + p;
    });
    var pLabel = periodo ? ' \xb7 \xfaltimos ' + periodo : ' \xb7 hist\xf3rico completo';
    loading.style.display   = '';
    tableWrap.style.display = 'none';
    fetch(BASE_PATH + '/api/analise/historico?gpon=' + encodeURIComponent(gpon) + '&sp=' + encodeURIComponent(sp) + pqs)
      .then(function(r) { return r.json(); })
      .then(function(d) {
        loading.style.display   = 'none';
        tableWrap.style.display = '';
        document.getElementById('hist-modal-sub').textContent =
          (d.total || 0) + ((d.total === 1) ? ' ocorr\xeancia' : ' ocorr\xeancias') + pLabel;
        var rows = d.rows || [];
        // Injeta o SP de contexto em cada linha — evita dependência de estado global no render
        var ctxSp = (sp || '').toUpperCase();
        rows.forEach(function(row) { row._ctx_sp = ctxSp; });
        var dtLang = { url: '/assets/js/pt-BR.json' };
        if (_dtHistorico) {
          _dtHistorico.clear().rows.add(rows).draw();
        } else {
          _dtHistorico = $('#tbl-historico').DataTable({
            data: rows, pageLength: 20, order: [[3, 'desc']], language: dtLang,
            dom: 'Blfrtip',
            buttons: [{ extend: 'excel', text: '<i class="bi bi-file-earmark-excel"></i> Excel', className: 'btn btn-sm btn-success', title: 'Histórico GPON' }],
            columns: [
              { data: 'oc',            render: function(v) { return v ? '<span class="mono mono-oc">' + escHtml(v) + '</span>' : '—'; } },
              { data: 'splitters',     width: '90px', render: function(v, _, row) {
                  if (!v) return '—';
                  // Canonicaliza tokens brutos (ex: "N03SP193SS1" → "SP193"), deduplica
                  var canonical = [];
                  String(v).split(',').forEach(function(token) {
                    token = token.trim();
                    if (!token) return;
                    var m = token.match(/SP\d+/i);
                    var csp = m ? m[0].toUpperCase() : token;
                    if (canonical.indexOf(csp) === -1) canonical.push(csp);
                  });
                  if (!canonical.length) return '—';
                  // SP de contexto vem da própria linha — independente de estado global
                  var primary = (row._ctx_sp || '').toUpperCase();
                  var ordered = primary
                    ? [primary].concat(canonical.filter(function(csp) { return csp !== primary; }))
                    : canonical;
                  var cnt = ordered.length;
                  var clr = cnt === 1 ? { bg: '#f1f5f9', fg: '#475569' } : cnt <= 5 ? { bg: '#fef3c7', fg: '#b45309' } : { bg: '#fee2e2', fg: '#dc2626' };
                  var tooltip = ordered.map(function(s) { return escHtml(s); }).join('&#10;');
                  var extra = cnt > 1 ? ' <span style="color:#94a3b8;font-size:9px">(+' + (cnt - 1) + ')</span>' : '';
                  return '<span title="' + tooltip + '" style="white-space:nowrap;cursor:default"><span class="badge" style="background:' + clr.bg + ';color:' + clr.fg + ';font-size:10px;font-weight:600;font-family:monospace">' + escHtml(ordered[0]) + '</span>' + extra + '</span>';
                }
              },
              { data: 'status',        render: function(v) { return _histStatusBadge(v); } },
              { data: 'abertura',      render: function(v) { return '<span style="font-size:10px;white-space:nowrap">' + _histFmtDt(v) + '</span>'; } },
              { data: 'encerramento',  render: function(v) { return '<span style="font-size:10px;white-space:nowrap">' + _histFmtDt(v) + '</span>'; } },
              { data: 'sla_status',    render: function(v) { return _histSlaHistBadge(v); } },
              { data: 'cidade',        render: function(v) { return '<span style="font-size:10px;white-space:nowrap">' + escHtml(v || '—') + '</span>'; } },
              { data: 'empresa',       render: function(v) { return '<span style="font-size:10px">' + escHtml(v || '—') + '</span>'; } },
              { data: 'baixa_causa',   render: function(v) { return '<span style="font-size:10px;max-width:160px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + escHtml(v || '') + '">' + escHtml(v || '—') + '</span>'; } },
              { data: 'baixa_reparo',  render: function(v) { return '<span style="font-size:10px">' + escHtml(v || '—') + '</span>'; } },
              { data: 'aging',         render: function(v) { return '<span class="mono" style="font-size:10px">' + _fmtAging(v) + '</span>'; } },
            ],
          });
          $('#tbl-historico_filter input[type=search]').attr('placeholder', 'Buscar OC');
        }
      })
      .catch(function(e) {
        loading.innerHTML = '<i class="bi bi-exclamation-triangle" style="color:#dc2626"></i> Erro ao carregar dados.';
        console.error('historico error', e);
      });
  }

  function openHistoricoModal(gpon, sp, count) {
    var overlay = document.getElementById('modal-historico');
    if (!overlay) return;
    _histGpon = gpon;
    _histSp   = sp;
    document.getElementById('hist-modal-title').textContent = gpon + ' — ' + sp;
    document.getElementById('hist-modal-sub').textContent   = (count || '…') + ' ocorr\xeancia(s) \xb7 carregando…';
    overlay.style.display        = 'flex';
    document.body.style.overflow = 'hidden';

    var saved = null;
    try { saved = localStorage.getItem('gpon_hist_period'); } catch(e) {}
    var periodo = ['', '7d', '15d', '30d'].includes(saved) ? saved : '30d';

    document.querySelectorAll('#modal-historico .hist-period-btn').forEach(function(b) {
      b.classList.toggle('active', (b.dataset.periodo || '') === periodo);
    });
    _fetchHistorico(gpon, sp, periodo);
  }

  function closeHistoricoModal() {
    var overlay = document.getElementById('modal-historico');
    if (overlay) overlay.style.display = 'none';
    document.body.style.overflow = '';
  }

  /* ── Clear all filters ───────────────────────────────────── */
  function clearFilters() {
    for (const k of Object.keys(state.filters)) state.filters[k] = [];
    try { localStorage.removeItem('gpon_uf'); } catch(e) {}
    document.querySelectorAll('.sf-item.active').forEach(i => i.classList.remove('active'));
    document.querySelectorAll('.kpi-card-filterable.kpi-active').forEach(c => c.classList.remove('kpi-active'));
    SF_CONFIG.forEach(cfg => updateSfBadge(cfg.key));
    updateSfFooter();
    refreshFilters(true);
  }

  /* ── Open View modal ─────────────────────────────────────── */
  let viewId  = null;
  let viewOc  = '';
  async function openView(id) {
    try {
      const res = await fetch(`${BASE_PATH}/api/ocorrencia/${id}`);
      const data = await res.json();
      if (!data.ok) { flash('Registro não encontrado', 'error'); return; }
      const r = data.row;

      viewId = id;
      viewOc = r.oc || '';

      document.getElementById('view-oc').textContent        = r.oc || '—';
      document.getElementById('view-ta').textContent        = r.ta || '—';
      document.getElementById('view-status').innerHTML      = badgeStatus(r.status);
      document.getElementById('view-sp').innerHTML          = badgePrazo(r.status_prazo, r.previsao_finalizacao, r.previsao_status);
      document.getElementById('view-repetida').innerHTML    = badgeRep(r.repetida);
      document.getElementById('view-gpon').textContent      = r.gpon || '—';
      document.getElementById('view-criacao').textContent   = fmtDate(r.data_criacao);
      document.getElementById('view-aging-a').innerHTML     = agingHtml(r.aging_abertos, r.status_prazo);
      document.getElementById('view-splitters').textContent = r.splitters || '—';
      document.getElementById('view-afetacao').textContent  = r.afetacao || '—';
      document.getElementById('view-empresa').textContent   = r.empresa || '—';
      document.getElementById('view-local').textContent     = r.localidade || '—';
      document.getElementById('view-obs').textContent        = r.observacoes_operacionais || '—';

      new bootstrap.Modal(document.getElementById('modalView')).show();
    } catch (e) {
      flash('Erro ao carregar registro: ' + e.message, 'error');
    }
  }

  /* ── Open Edit modal ─────────────────────────────────────── */
  let editingId = null;
  async function openEdit(id) {
    editingId = id;
    try {
      const res = await fetch(`${BASE_PATH}/api/ocorrencia/${id}`);
      const data = await res.json();
      if (!data.ok) { flash('Registro não encontrado', 'error'); return; }
      const r = data.row;

      document.getElementById('edit-id').value         = r.id;
      document.getElementById('edit-oc').value         = r.oc || '';
      document.getElementById('edit-ta').value         = r.ta || '';
      document.getElementById('edit-status').value     = r.status || '';
      document.getElementById('edit-gpon').value       = r.gpon || '';
      document.getElementById('edit-splitters').value  = r.splitters || '';
      document.getElementById('edit-empresa').value    = r.empresa || '';
      document.getElementById('edit-localidade').value = r.localidade || '';
      document.getElementById('edit-afetacao').value   = r.afetacao || '';
      document.getElementById('edit-obs').value         = r.observacoes_operacionais || '';
      document.getElementById('edit-data-enc').value   = r.data_encerramento ? r.data_encerramento.substring(0,16) : '';

      new bootstrap.Modal(document.getElementById('modalEdit')).show();
    } catch (e) {
      flash('Erro ao carregar registro: ' + e.message, 'error');
    }
  }

  /* ── Save edit ───────────────────────────────────────────── */
  async function saveEdit() {
    if (!editingId) return;
    const form = document.getElementById('form-edit');
    const payload = {
      ta:               document.getElementById('edit-ta').value.trim(),
      status:           document.getElementById('edit-status').value.trim(),
      gpon:             document.getElementById('edit-gpon').value.trim(),
      splitters:        document.getElementById('edit-splitters').value.trim(),
      empresa:          document.getElementById('edit-empresa').value.trim(),
      localidade:       document.getElementById('edit-localidade').value.trim(),
      afetacao:         document.getElementById('edit-afetacao').value.trim(),
      observacoes_operacionais: document.getElementById('edit-obs').value.trim(),
      data_encerramento: document.getElementById('edit-data-enc').value || null,
    };

    try {
      const res = await fetch(`${BASE_PATH}/api/ocorrencia/${editingId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.message || 'Erro ao salvar');

      bootstrap.Modal.getInstance(document.getElementById('modalEdit'))?.hide();
      flash('Registro atualizado com sucesso', 'success');
      reloadData();
    } catch (e) {
      flash('Erro: ' + e.message, 'error');
    }
  }

  /* ── Open History modal ──────────────────────────────────── */
  let historyId  = null;
  let historyRow = null;
  async function openHistory(id) {
    historyId  = id;
    historyRow = state.data.find(function(r) { return r.id === id; }) || {};
    document.getElementById('hist-ta').textContent         = historyRow.ta         ? 'TA - ' + historyRow.ta : 'TA - —';
    document.getElementById('hist-splitter').textContent   = (function() {
      const raw = historyRow.splitters || '';
      if (!raw) return '—';
      const parts = raw.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
      if (parts.length === 1) return parts[0];
      const detailMap = {};
      (historyRow.reincidencia_detail || []).forEach(function(item) { detailMap[item.sp] = item.cnt; });
      const top = parts.slice().sort(function(a, b) { return (detailMap[b] || 0) - (detailMap[a] || 0); })[0];
      const topCnt = detailMap[top] || 0;
      return top + (topCnt > 0 ? ' (' + topCnt + 'x)' : '') + ' +' + (parts.length - 1);
    })();
    document.getElementById('hist-localidade').textContent = historyRow.localidade || '—';
    const container = document.getElementById('history-timeline');
    container.innerHTML = '<div class="text-muted text-center p-3"><i class="bi bi-hourglass-split"></i> Carregando...</div>';
    _renderPrevisaoBlock(historyRow);
    new bootstrap.Modal(document.getElementById('modalHistory')).show();
    await refreshHistory();
  }

  async function refreshHistory() {
    if (!historyId) return;
    try {
      const res = await fetch(`${BASE_PATH}/api/historico/${historyId}`);
      const data = await res.json();
      const container = document.getElementById('history-timeline');

      // Filtra eventos automáticos de importação — exibe apenas histórico operacional
      const allItems    = data.items || [];
      const items       = allItems.filter(function(i) { return i.tipo !== 'importacao'; });
      const comentCount = allItems.filter(function(i) { return i.tipo === 'comentario'; }).length;

      if (!data.ok || !items.length) {
        container.innerHTML = '<div style="padding:12px 4px;color:var(--gpon-muted);font-size:12px;display:flex;align-items:center;gap:6px"><i class="bi bi-chat-square" style="font-size:14px;opacity:.5"></i> Nenhum comentário registrado</div>';
        syncHistButton(historyId, false);
        return;
      }

      syncHistButton(historyId, comentCount > 0);

      var typeIcons  = { comentario: 'bi-chat-left-text', edicao: 'bi-pencil', exclusao: 'bi-trash', previsao: 'bi-clock-history' };
      var typeLabels = { comentario: 'Comentário', edicao: 'Edição', exclusao: 'Exclusão', previsao: 'Previsão' };
      var typeColors = { comentario: '#7c3aed', edicao: '#d97706', exclusao: '#dc2626', previsao: '#16a34a' };
      container.innerHTML = '<ul class="timeline">' + items.map(function(item) {
        var dotCls    = item.tipo || 'comentario';
        var icon      = typeIcons[dotCls]  || 'bi-info-circle';
        var label     = typeLabels[dotCls] || dotCls;
        var color     = typeColors[dotCls] || '#64748b';
        var rawText        = (item.texto || item.valor_novo || '').replace(/"/g, '&quot;');
        var isComment      = dotCls === 'comentario';
        var isComboPrevisao = isComment && /·\s*Prev\./.test(item.texto || '');
        var userName  = item.usuario_nome || 'Sistema';
        var initial   = userName.charAt(0).toUpperCase();
        var dotContent = isComment
          ? '<span>' + escHtml(initial) + '</span>'
          : '<i class="bi ' + icon + '" style="font-size:11px"></i>';
        var _chip = function(ic, lbl, clr) {
          return '<span style="display:inline-flex;align-items:center;gap:3px;font-size:9px;font-weight:700;' +
            'text-transform:uppercase;letter-spacing:.05em;padding:1px 7px;border-radius:10px;' +
            'background:' + clr + '18;color:' + clr + '">' +
            '<i class="bi ' + ic + '"></i> ' + escHtml(lbl) + '</span>';
        };
        var typeChip = _chip(icon, label, color) +
          (isComboPrevisao ? ' ' + _chip('bi-clock-history', 'Prev.', '#16a34a') : '');
        return '<li class="timeline-item" data-hist-id="' + item.id + '">' +
          '<div class="timeline-dot ' + dotCls + '">' + dotContent + '</div>' +
          '<div class="timeline-body tl-' + dotCls + '">' +
            '<div class="timeline-meta">' +
              '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">' +
                '<strong style="font-size:12px;color:var(--gpon-text)">' + escHtml(userName) + '</strong>' +
                typeChip +
              '</div>' +
              '<span style="font-size:10px;color:var(--gpon-muted);white-space:nowrap;flex-shrink:0">' + fmtDateShort(item.created_at) + '</span>' +
            '</div>' +
            (item.campo ? '<div style="font-size:10px;color:var(--gpon-muted);margin-bottom:4px">Campo: <strong>' + escHtml(item.campo) + '</strong></div>' : '') +
            '<div class="timeline-text" data-raw="' + rawText + '">' + escHtml(item.texto || item.valor_novo || '—') + '</div>' +
            (isComment
              ? '<div class="hist-item-actions">' +
                  '<button class="hist-btn edit" onclick="GPON.editHistItem(' + item.id + ')" title="Editar"><i class="bi bi-pencil"></i></button>' +
                  '<button class="hist-btn delete" onclick="GPON.deleteHistItem(' + item.id + ')" title="Excluir"><i class="bi bi-trash"></i></button>' +
                '</div>'
              : '') +
          '</div>' +
        '</li>';
      }).join('') + '</ul>';
    } catch (e) {
      document.getElementById('history-timeline').innerHTML = '<div class="flash-msg error">Erro ao carregar histórico</div>';
    }
  }

  function syncHistButton(id, hasComments) {
    const row = state.data.find(function(r) { return r.id === id; });
    if (row) row.tem_comentarios = hasComments ? 1 : 0;
    document.querySelectorAll('.action-btn.history[onclick="GPON.openHistory(' + id + ')"]').forEach(function(btn) {
      btn.classList.toggle('has-hist', hasComments);
      btn.title = hasComments ? 'Possui histórico' : 'Histórico';
    });
  }

  /* ── Previsão de Finalização ─────────────────────────────── */
  function _previsaoStatusInfo(status) {
    var map = {
      ok:      { cls: 'previsao-ok',      icon: 'bi-check-circle-fill', label: 'Dentro do Prazo'   },
      atencao: { cls: 'previsao-atencao', icon: 'bi-exclamation-circle-fill', label: 'Próximo do SLA' },
      critica: { cls: 'previsao-critica', icon: 'bi-x-circle-fill',     label: 'Fora do Prazo'     },
    };
    return map[status] || null;
  }

  function _renderPrevisaoBlock(row) {
    var input    = document.getElementById('previsao-input');
    var badge    = document.getElementById('previsao-status-badge');
    var slaInfo  = document.getElementById('previsao-sla-info');
    var dateDisp = document.getElementById('mh-prev-date-display');
    if (!input) return;

    var prev = row.previsao_finalizacao || '';
    input.value = prev ? prev.replace(' ', 'T').slice(0, 16) : '';

    if (dateDisp) {
      dateDisp.textContent = prev ? _fmtPrevisaoDate(prev) : '—';
      dateDisp.className = 'mh-prev-date-display' + (row.previsao_status ? ' mh-prev-' + row.previsao_status : '');
    }

    _renderPrevisaoBadge(badge, row.previsao_status);

    if (slaInfo && row.sla_limite) {
      var slaFmt = _fmtPrevisaoDate(row.sla_limite);
      slaInfo.innerHTML = '<i class="bi bi-clock"></i> Limite SLA: <strong>' + slaFmt + '</strong>';
      slaInfo.style.display = '';
    } else if (slaInfo) {
      slaInfo.style.display = 'none';
    }
  }

  function _renderPrevisaoBadge(el, status) {
    if (!el) return;
    if (!status) { el.innerHTML = ''; return; }
    var info = _previsaoStatusInfo(status);
    if (!info) { el.innerHTML = ''; return; }
    el.innerHTML = '<span class="previsao-badge ' + info.cls + '"><i class="bi ' + info.icon + '"></i> ' + info.label + '</span>';
  }

  function _fmtPrevisaoDate(iso) {
    if (!iso) return '—';
    var m = iso.match(/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    return m ? m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] + ':' + m[5] : iso;
  }

  function _invalidateTableRow(id) {
    if (!state.table) return;
    state.table.rows(function(_, rowData) { return rowData.id === id; }).invalidate().draw(false);
  }

  async function savePrevisao() {
    if (!historyId) return;
    var input      = document.getElementById('previsao-input');
    var val        = input ? input.value.trim() : '';
    var commentEl  = document.getElementById('new-comment');
    var commentTxt = commentEl ? commentEl.value.trim() : '';

    // Comentário pendente + previsão preenchida: combina em evento único
    if (val && commentTxt) {
      await addComment();
      return;
    }

    try {
      var res  = await fetch(BASE_PATH + '/api/previsao/' + historyId, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ previsao_finalizacao: val || null }),
      });
      var data = await res.json();
      if (!data.ok) throw new Error(data.message || 'Erro ao salvar');
      // Atualiza state.data para refletir na tabela
      if (historyRow) {
        historyRow.previsao_finalizacao = data.previsao_finalizacao;
        historyRow.previsao_status      = data.previsao_status;
        historyRow.sla_limite           = data.sla_limite;
      }
      _renderPrevisaoBlock(historyRow);
      _invalidateTableRow(historyId);
      flash(val ? 'Previsão salva' : 'Previsão removida', 'success');
      refreshHistory();
    } catch(e) {
      flash('Erro: ' + e.message, 'error');
    }
  }

  async function clearPrevisao() {
    if (!historyId) return;
    var input = document.getElementById('previsao-input');
    if (input) input.value = '';
    try {
      var res  = await fetch(BASE_PATH + '/api/previsao/' + historyId, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ previsao_finalizacao: null }),
      });
      var data = await res.json();
      if (!data.ok) throw new Error(data.message || 'Erro');
      if (historyRow) {
        historyRow.previsao_finalizacao = null;
        historyRow.previsao_status      = null;
      }
      _renderPrevisaoBadge(document.getElementById('previsao-status-badge'), null);
      var _dd = document.getElementById('mh-prev-date-display');
      if (_dd) { _dd.textContent = '—'; _dd.className = 'mh-prev-date-display'; }
      _invalidateTableRow(historyId);
      flash('Previsão removida', 'success');
    } catch(e) {
      flash('Erro: ' + e.message, 'error');
    }
  }

  async function addComment() {
    const txt = document.getElementById('new-comment').value.trim();
    if (!txt) { flash('Digite um comentário', 'info'); return; }

    // Verifica se há previsão preenchida e diferente da atual — combina no mesmo evento
    const prevInput = document.getElementById('previsao-input');
    const prevVal   = prevInput ? prevInput.value.trim() : '';
    const prevAtual = historyRow ? (historyRow.previsao_finalizacao || '').replace(' ', 'T').slice(0, 16) : '';
    const prevMudou = prevVal !== '' && prevVal !== prevAtual;

    const body = { texto: txt };
    if (prevMudou) body.previsao_finalizacao = prevVal;

    try {
      const res = await fetch(`${BASE_PATH}/api/historico/${historyId}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.message);
      document.getElementById('new-comment').value = '';
      if (prevMudou) {
        const pi = document.getElementById('previsao-input');
        if (pi) pi.value = '';
      }

      // Se previsão foi combinada: atualiza estado e UI do bloco de previsão
      if (prevMudou && data.previsao_finalizacao !== undefined) {
        if (historyRow) {
          historyRow.previsao_finalizacao = data.previsao_finalizacao;
          historyRow.previsao_status      = data.previsao_status;
          historyRow.sla_limite           = data.sla_limite;
        }
        _renderPrevisaoBadge(document.getElementById('previsao-status-badge'), data.previsao_status);
        var _dd = document.getElementById('mh-prev-date-display');
        if (_dd) {
          _dd.textContent = data.previsao_finalizacao ? _fmtPrevisaoDate(data.previsao_finalizacao) : '—';
          _dd.className = 'mh-prev-date-display' + (data.previsao_status ? ' mh-prev-' + data.previsao_status : '');
        }
        _invalidateTableRow(historyId);
      }

      flash('Comentário adicionado', 'success');
      refreshHistory();
    } catch (e) {
      flash('Erro: ' + e.message, 'error');
    }
  }

  /* ── Edit / Delete history comments ────────────────────── */
  function editHistItem(itemId) {
    const li = document.querySelector('.timeline-item[data-hist-id="' + itemId + '"]');
    if (!li) return;
    const textEl = li.querySelector('.timeline-text');
    const raw    = textEl.dataset.raw || textEl.textContent;
    const actEl  = li.querySelector('.hist-item-actions');
    if (actEl) actEl.style.display = 'none';
    textEl.innerHTML =
      '<textarea class="hist-edit-area" id="hist-edit-' + itemId + '">' + escHtml(raw) + '</textarea>' +
      '<div class="hist-edit-footer">' +
        '<button class="btn btn-outline-secondary btn-sm" onclick="GPON.cancelHistEdit()">Cancelar</button>' +
        '<button class="btn btn-success btn-sm" onclick="GPON.saveHistItem(' + itemId + ')">Salvar</button>' +
      '</div>';
    const ta = document.getElementById('hist-edit-' + itemId);
    if (ta) { ta.focus(); ta.setSelectionRange(ta.value.length, ta.value.length); }
  }

  function cancelHistEdit() { refreshHistory(); }

  async function saveHistItem(itemId) {
    const ta = document.getElementById('hist-edit-' + itemId);
    if (!ta) return;
    const texto = ta.value.trim();
    if (!texto) { flash('Digite o texto', 'info'); return; }
    try {
      const res = await fetch(BASE_PATH + '/api/historico-item/' + itemId, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ texto }),
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.message);
      flash('Comentário atualizado', 'success');
      refreshHistory();
    } catch (e) {
      flash('Erro: ' + e.message, 'error');
    }
  }

  let deleteCommentId = null;
  function deleteHistItem(itemId) {
    deleteCommentId = itemId;
    new bootstrap.Modal(document.getElementById('modalDeleteComment')).show();
  }

  async function execDeleteComment() {
    if (deleteCommentId === null || deleteCommentId === undefined) return;
    const id = deleteCommentId;
    deleteCommentId = null;
    bootstrap.Modal.getInstance(document.getElementById('modalDeleteComment'))?.hide();
    try {
      const res = await fetch(BASE_PATH + '/api/historico-item/' + id, { method: 'DELETE' });
      const data = await res.json();
      if (!data.ok) throw new Error(data.message);
      flash('Comentário excluído', 'success');
      refreshHistory();
    } catch (e) {
      flash('Erro: ' + e.message, 'error');
    }
  }

  /* ── Confirm & Execute Delete ────────────────────────────── */
  let deleteId = null;
  function confirmDelete(id, oc) {
    deleteId = id;
    document.getElementById('del-oc-label').textContent = oc;
    new bootstrap.Modal(document.getElementById('modalDelete')).show();
  }

  async function execDelete() {
    if (!deleteId) return;
    try {
      const res = await fetch(`${BASE_PATH}/api/ocorrencia/${deleteId}`, { method: 'DELETE' });
      const data = await res.json();
      if (!data.ok) throw new Error(data.message);
      bootstrap.Modal.getInstance(document.getElementById('modalDelete'))?.hide();
      flash('Registro excluído com sucesso', 'success');
      deleteId = null;
      reloadData();
    } catch (e) {
      flash('Erro: ' + e.message, 'error');
    }
  }

  /* ── Upload (Excel import) ───────────────────────────────── */

  /* Overlay helpers */
  var _ovTimer = null;
  function ovShow() {
    var ov = document.getElementById('upload-overlay');
    if (!ov) return;
    ov.setAttribute('aria-hidden', 'false');
    ov.classList.add('active');
    _ovSetPct(0);
    _ovStep(null);
    document.getElementById('overlay-title').textContent = 'Processando planilha';
    document.getElementById('overlay-sub').textContent   = 'Enviando arquivo…';
  }
  function ovHide() {
    var ov = document.getElementById('upload-overlay');
    if (!ov) return;
    ov.setAttribute('aria-hidden', 'true');
    ov.classList.remove('active');
    if (_ovTimer) { clearTimeout(_ovTimer); _ovTimer = null; }
  }
  function _ovSetPct(p) {
    var bar = document.getElementById('overlay-bar');
    var pct = document.getElementById('overlay-pct');
    if (bar) bar.style.width = p + '%';
    if (pct) pct.textContent = Math.round(p) + '%';
  }
  function _ovStep(active) {
    ['upload','process','db','done'].forEach(function(id) {
      var el = document.getElementById('ovstep-' + id);
      if (!el) return;
      el.classList.remove('active', 'done');
      if (id === active) el.classList.add('active');
    });
  }
  function _ovMarkDone(upToStep) {
    var order = ['upload','process','db','done'];
    var idx   = order.indexOf(upToStep);
    order.forEach(function(id, i) {
      var el = document.getElementById('ovstep-' + id);
      if (!el) return;
      el.classList.remove('active','done');
      if (i < idx)  el.classList.add('done');
      if (i === idx) el.classList.add('active');
    });
  }
  function _ovSub(txt) {
    var el = document.getElementById('overlay-sub');
    if (el) el.textContent = txt;
  }

  /* Topbar update after successful import */
  function _updateTopbarUa(updatedAt) {
    var el   = document.getElementById('topbar-ua-text');
    var wrap = document.getElementById('topbar-last-update');
    if (!el || !updatedAt) return;
    el.textContent = 'Atualizado: ' + updatedAt;
    if (wrap) {
      wrap.style.animation = 'none';
      wrap.offsetHeight; // reflow
      wrap.style.animation = 'ua-pulse .6s ease';
      wrap.classList.remove('stale');
    }
  }

  var _REQUIRED_COLS = [
    'Tíquete Referência',
    'Nome Localidade',
    'Status',
    'Data Criacao',
    'Data Encerramento',
    'Sigla Site V2',
    'Serviço FTTX',
    'Splitters Nível 1',
  ];

  function _normalizeCol(s) {
    return (s || '').trim().replace(/\s+/g, ' ');
  }

  function _validateXlsx(file, onDone) {
    var reader = new FileReader();
    reader.onload = function(e) {
      try {
        var wb  = XLSX.read(new Uint8Array(e.target.result), { type: 'array', cellFormula: false, cellHTML: false, cellText: false, cellDates: false });
        var ws  = wb.Sheets[wb.SheetNames[0]];
        var ref = ws['!ref'] ? XLSX.utils.decode_range(ws['!ref']) : null;
        if (!ref) { onDone(new Error('Planilha vazia ou sem dados.')); return; }
        var rowCount = ref.e.r; // header is row 0, data starts at row 1
        var header   = [];
        for (var c = ref.s.c; c <= ref.e.c; c++) {
          var cell = ws[XLSX.utils.encode_cell({ r: 0, c: c })];
          header.push(cell ? _normalizeCol(String(cell.v)) : '');
        }
        var missing = _REQUIRED_COLS.filter(function(col) { return header.indexOf(col) === -1; });
        onDone(null, {
          filename: file.name,
          colCount: header.filter(function(h) { return h !== ''; }).length,
          rowCount: rowCount,
          missing:  missing,
          valid:    missing.length === 0,
        });
      } catch (err) {
        onDone(err);
      }
    };
    reader.onerror = function() { onDone(new Error('Não foi possível ler o arquivo.')); };
    reader.readAsArrayBuffer(file);
  }

  function initUpload() {
    var area      = document.getElementById('upload-area');
    var input     = document.getElementById('upload-file');
    var result    = document.getElementById('upload-result');
    var btnImport = document.getElementById('btn-do-import');

    if (!area) return;

    function handleUploadFile(file) {
      updateUploadLabel(file.name);
      btnImport.disabled = true;
      result.className     = 'import-result info';
      result.innerHTML     = '<i class="bi bi-hourglass-split"></i> Validando layout da planilha…';
      result.style.display = 'block';

      _validateXlsx(file, function(err, info) {
        if (err) {
          result.className = 'import-result error';
          result.innerHTML = '<i class="bi bi-x-circle-fill"></i> <strong>Erro ao ler o arquivo:</strong> ' + escHtml(err.message);
          return;
        }
        if (info.valid) {
          result.className = 'import-result success';
          result.innerHTML =
            '<div><i class="bi bi-check-circle-fill"></i> <strong>Layout Radar GPON validado</strong></div>' +
            '<div><i class="bi bi-check2"></i> Estrutura compatível</div>' +
            '<div><i class="bi bi-check2"></i> Pronto para importação</div>' +
            '<hr style="margin:8px 0;border-color:currentColor;opacity:.2">' +
            '<div><strong>Arquivo:</strong> ' + escHtml(info.filename) + '</div>' +
            '<div>' + info.colCount + ' colunas obrigatórias encontradas</div>' +
            '<div>' + info.rowCount.toLocaleString('pt-BR') + ' registros identificados</div>';
          btnImport.disabled = false;
        } else {
          result.className = 'import-result error';
          result.innerHTML =
            '<div style="text-align:center;padding:4px 0">' +
            '<i class="bi bi-exclamation-triangle" style="font-size:22px;display:block;margin-bottom:6px"></i>' +
            '<strong>Planilha não reconhecida</strong>' +
            '</div>';
        }
      });
    }

    area.addEventListener('click', function() { input.click(); });
    area.addEventListener('dragover', function(e) { e.preventDefault(); area.classList.add('dragover'); });
    area.addEventListener('dragleave', function() { area.classList.remove('dragover'); });
    area.addEventListener('drop', function(e) {
      e.preventDefault();
      area.classList.remove('dragover');
      if (e.dataTransfer.files[0]) {
        input.files = e.dataTransfer.files;
        handleUploadFile(e.dataTransfer.files[0]);
      }
    });

    input.addEventListener('change', function() {
      if (input.files[0]) {
        handleUploadFile(input.files[0]);
      }
    });

    if (!btnImport) return;

    btnImport.addEventListener('click', function() {
      if (!input.files[0]) return;

      var fd = new FormData();
      fd.append('file', input.files[0]);

      btnImport.disabled = true;
      result.style.display = 'none';

      /* Show overlay immediately */
      ovShow();
      _ovStep('upload');
      _ovSetPct(2);

      var xhr = new XMLHttpRequest();

      /* Real upload progress (0 → 50%) */
      xhr.upload.addEventListener('progress', function(e) {
        if (!e.lengthComputable) return;
        var pct = (e.loaded / e.total) * 48 + 2; // 2%–50%
        _ovSetPct(pct);
        if (pct > 20) _ovSub('Enviando arquivo… ' + Math.round((e.loaded / e.total) * 100) + '%');
      });

      /* Upload complete → server is now processing */
      xhr.upload.addEventListener('load', function() {
        _ovMarkDone('process');
        _ovSub('Lendo planilha Excel…');
        _ovSetPct(52);

        /* Animate fake server-side progress 52% → 82% */
        var fakePct = 52;
        _ovTimer = setInterval(function() {
          fakePct += (82 - fakePct) * 0.08;
          _ovSetPct(fakePct);
          if (fakePct >= 65 && fakePct < 80) {
            _ovMarkDone('db');
            _ovSub('Gravando no banco de dados…');
          }
        }, 200);
      });

      xhr.addEventListener('load', function() {
        if (_ovTimer) { clearInterval(_ovTimer); _ovTimer = null; }

        var data;
        try { data = JSON.parse(xhr.responseText); } catch(e) { data = { ok: false, message: 'Resposta inválida do servidor' }; }

        if (data.ok) {
          _ovMarkDone('done');
          _ovSub('Importação concluída!');
          _ovSetPct(100);

          /* Update topbar without reload */
          if (data.updated_at) _updateTopbarUa(data.updated_at);

          _ovTimer = setTimeout(function() {
            ovHide();
            // Fecha o modal automaticamente após sucesso
            var modalEl   = document.getElementById('modalUpload');
            var modalInst = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;
            if (modalInst) modalInst.hide();
            // Garante limpeza do backdrop caso Bootstrap não remova a tempo
            document.body.classList.remove('modal-open');
            document.querySelectorAll('.modal-backdrop').forEach(function(el) { el.remove(); });
            // Reset do modal para próxima importação
            result.style.display = 'none';
            result.className     = 'import-result';
            input.value          = '';
            updateUploadLabel('Clique ou arraste um arquivo .xlsx aqui');
            btnImport.disabled   = true;
            // Toast estruturado com resumo da importação
            var toastLines = [data.inseridos + ' inseridos · ' + data.atualizados + ' atualizados'];
            if (data.erros     > 0) toastLines.push(data.erros     + ' erros');
            if (data.ignorados > 0) toastLines.push(data.ignorados + ' ignorados');
            flashToast('Importação concluída', toastLines, 'success', 6000);
            refreshFilters(true);
          }, 700);
        } else {
          ovHide();
          result.className = 'import-result error';
          result.innerHTML = '<i class="bi bi-x-circle-fill"></i> <strong>Erro:</strong> ' + escHtml(data.message || 'Falha na importação');
          result.style.display = 'block';
          btnImport.disabled = false;
        }
      });

      xhr.addEventListener('error', function() {
        if (_ovTimer) { clearInterval(_ovTimer); _ovTimer = null; }
        ovHide();
        result.className = 'import-result error';
        result.innerHTML = '<i class="bi bi-x-circle-fill"></i> Erro de conexão. Verifique a rede e tente novamente.';
        result.style.display = 'block';
        btnImport.disabled = false;
      });

      xhr.open('POST', BASE_PATH + '/upload');
      xhr.send(fd);
    });
  }

  function updateUploadLabel(filename) {
    const lbl = document.getElementById('upload-filename');
    if (lbl) lbl.textContent = filename;
  }

  /* ── Recalcular colunas DataTables após mudança de layout ── */
  function adjustTable() {
    if (!state.table) return;
    state.table.columns.adjust().draw(false);
    try { state.table.fixedColumns().relayout(); } catch (e) { /* ignorar se não disponível */ }
  }

  /* ── Sidebar toggle ──────────────────────────────────────── */
  function initSidebar() {
    const btn = document.getElementById('btn-sidebar-toggle');
    const sb  = document.getElementById('gpon-sidebar');
    if (!btn || !sb) return;

    // Padrão: recolhida. Respeita preferência salva se existir.
    let saved = null;
    try { saved = localStorage.getItem('gpon_sidebar'); } catch(e) {}
    if (saved !== 'open') sb.classList.add('collapsed');

    btn.addEventListener('click', () => {
      sb.classList.toggle('collapsed');
      try { localStorage.setItem('gpon_sidebar', sb.classList.contains('collapsed') ? 'collapsed' : 'open'); } catch(e) {}
      setTimeout(adjustTable, 250); // aguarda fim da transição CSS (.2s)
    });

    // Redimensionamento da janela (debounce 150ms)
    var resizeTimer;
    window.addEventListener('resize', function() {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(adjustTable, 150);
    });
  }

  /* ── Admin: Users ────────────────────────────────────────── */
  let adminEditId = null;

  async function loadUsers() {
    try {
      const res = await fetch(`${BASE_PATH}/api/admin/usuarios`);
      const data = await res.json();
      if (!data.ok) return;
      renderUsers(data.users || []);
    } catch (e) {
      console.warn('loadUsers error:', e);
    }
  }

  const NIVEL_LABEL = {
    admin: 'Administrador',
    backoffice: 'Backoffice',
    supervisor: 'Supervisor',
    operador: 'Operador',
    tecnico: 'Técnico',
  };
  const NIVEL_BADGE_CLASS = {
    admin: 'badge-nivel-admin',
    backoffice: 'badge-nivel-backoffice',
    supervisor: 'badge-nivel-supervisor',
    operador: 'badge-nivel-operador',
    tecnico: 'badge-nivel-tecnico',
  };

  function renderUsers(users) {
    const tbody = document.getElementById('users-tbody');
    if (!tbody) return;
    if (!users.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Nenhum usuário</td></tr>';
      return;
    }
    tbody.innerHTML = users.map(u => `
      <tr>
        <td>${escHtml(u.nome)}</td>
        <td><code>${escHtml(u.usuario)}</code></td>
        <td><span class="badge-status ${NIVEL_BADGE_CLASS[u.nivel] || 'badge-nivel-operador'}">${NIVEL_LABEL[u.nivel] || 'Operador'}</span></td>
        <td><span class="badge-status ${u.status == 1 ? 'badge-dentro-prazo' : 'badge-fora-prazo'}">${u.status == 1 ? 'Ativo' : 'Inativo'}</span></td>
        <td>
          <div class="action-group">
            <button class="action-btn edit" onclick="GPON.openUserEdit(${u.id})" title="Editar"><i class="bi bi-pencil"></i></button>
            <button class="action-btn del" onclick="GPON.deleteUser(${u.id},'${escHtml(u.nome)}')" title="Excluir"><i class="bi bi-trash"></i></button>
          </div>
        </td>
      </tr>`).join('');
  }

  function openUserCreate() {
    adminEditId = null;
    document.getElementById('user-modal-title').textContent = 'Novo Usuário';
    document.getElementById('form-user').reset();
    document.getElementById('user-senha-row').style.display = '';
    new bootstrap.Modal(document.getElementById('modalUser')).show();
  }

  async function openUserEdit(id) {
    adminEditId = id;
    try {
      const res = await fetch(`${BASE_PATH}/api/admin/usuario/${id}`);
      const data = await res.json();
      if (!data.ok) { flash('Usuário não encontrado', 'error'); return; }
      const u = data.user;
      document.getElementById('user-modal-title').textContent = 'Editar Usuário';
      document.getElementById('uform-nome').value    = u.nome || '';
      document.getElementById('uform-usuario').value = u.usuario || '';
      document.getElementById('uform-nivel').value   = u.nivel || 'operador';
      document.getElementById('uform-status').value  = String(u.status);
      document.getElementById('uform-senha').value   = '';
      document.getElementById('user-senha-row').style.display = '';
      new bootstrap.Modal(document.getElementById('modalUser')).show();
    } catch (e) {
      flash('Erro: ' + e.message, 'error');
    }
  }

  async function saveUser() {
    const payload = {
      nome:    document.getElementById('uform-nome').value.trim(),
      usuario: document.getElementById('uform-usuario').value.trim(),
      nivel:   document.getElementById('uform-nivel').value,
      status:  document.getElementById('uform-status').value,
      senha:   document.getElementById('uform-senha').value,
    };
    if (!payload.nome || !payload.usuario) { flash('Nome e usuário são obrigatórios', 'error'); return; }
    if (!adminEditId && !payload.senha) { flash('Senha obrigatória para novo usuário', 'error'); return; }

    const url    = adminEditId ? `${BASE_PATH}/api/admin/usuario/${adminEditId}` : `${BASE_PATH}/api/admin/usuarios`;
    const method = adminEditId ? 'PUT' : 'POST';

    try {
      const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.message);
      bootstrap.Modal.getInstance(document.getElementById('modalUser'))?.hide();
      flash(adminEditId ? 'Usuário atualizado' : 'Usuário criado', 'success');
      loadUsers();
    } catch (e) {
      flash('Erro: ' + e.message, 'error');
    }
  }

  async function deleteUser(id, nome) {
    if (!confirm(`Excluir usuário "${nome}"?`)) return;
    try {
      const res = await fetch(`${BASE_PATH}/api/admin/usuario/${id}`, { method: 'DELETE' });
      const data = await res.json();
      if (!data.ok) throw new Error(data.message);
      flash('Usuário excluído', 'success');
      loadUsers();
    } catch (e) {
      flash('Erro: ' + e.message, 'error');
    }
  }

  /* ── Popup Resumo Empresas ───────────────────────────────── */
  function _empGroupData() {
    var rows   = state.data || [];
    var groups = {};
    rows.forEach(function(r) {
      var emp = (r.empresa || '—').toUpperCase();
      if (!groups[emp]) groups[emp] = [];
      groups[emp].push(r);
    });
    // Ordena por contagem decrescente
    return Object.entries(groups).sort(function(a, b) { return b[1].length - a[1].length; });
  }

  function openEmpresasPopup() {
    var sorted = _empGroupData();
    var body   = document.getElementById('emp-popup-body');
    if (!body) return;

    if (!sorted.length) {
      body.innerHTML = '<div class="emp-popup-empty"><i class="bi bi-inbox" style="font-size:20px;display:block;margin-bottom:6px;opacity:.4"></i>Sem dados para exibir</div>';
    } else {
      body.innerHTML = sorted.map(function(entry) {
        var emp     = entry[0];
        var empRows = entry[1].slice().sort(function(a, b) {
          return (parseInt(b.aging_abertos, 10) || 0) - (parseInt(a.aging_abertos, 10) || 0);
        });
        var rowsHtml = empRows.map(function(r) {
          var oc     = r.oc       || '—';
          var gpon   = r.gpon     || '—';
          var sp     = (r.splitters || '—').split(',')[0].trim();
          var mins   = parseInt(r.aging_abertos, 10) || 0;
          var tempo  = formatAging(mins);
          var cidade = (r.localidade || '—').toUpperCase();
          var pm     = r.previsao_finalizacao ? String(r.previsao_finalizacao).match(/[ T](\d{2}):(\d{2})/) : null;
          var prevHtml = pm ? ' - <span style="color:#0891b2;font-weight:600">Prev. às ' + pm[1] + ':' + pm[2] + '</span>' : '';
          var tempoHtml = mins >= 480
            ? '🔴 <span style="color:#dc2626;font-weight:600">' + escHtml(tempo) + '</span>'
            : mins >= 420
            ? '🟠 <span style="color:#d97706;font-weight:600">' + escHtml(tempo) + '</span>'
            : escHtml(tempo);
          return '<div class="emp-popup-oc-row">' +
            escHtml(oc) + ' - ' + escHtml(gpon) + ' - ' + escHtml(sp) +
            ' - ' + tempoHtml + ' - ' + escHtml(cidade) + prevHtml +
          '</div>';
        }).join('');
        return '<div class="emp-popup-group">' +
          '<div class="emp-popup-group-header">' +
            '<span class="emp-popup-group-name">' + escHtml(emp) + '</span>' +
            '<span class="emp-popup-group-count">= ' + empRows.length + '</span>' +
          '</div>' +
          rowsHtml +
        '</div>';
      }).join('');
    }

    document.getElementById('popup-empresas').style.display       = 'flex';
    document.getElementById('emp-popup-backdrop').style.display   = 'block';
    document.body.style.overflow = 'hidden';
  }

  function closeEmpresasPopup() {
    var popup    = document.getElementById('popup-empresas');
    var backdrop = document.getElementById('emp-popup-backdrop');
    if (popup)    popup.style.display    = 'none';
    if (backdrop) backdrop.style.display = 'none';
    document.body.style.overflow = '';
  }

  function copyEmpresasPopup() {
    var sorted = _empGroupData();
    var lines  = [];
    sorted.forEach(function(entry) {
      var emp     = entry[0];
      var empRows = entry[1].slice().sort(function(a, b) {
        return (parseInt(b.aging_abertos, 10) || 0) - (parseInt(a.aging_abertos, 10) || 0);
      });
      lines.push(emp + ' = ' + empRows.length);
      lines.push('');
      empRows.forEach(function(r) {
        var oc      = r.oc       || '—';
        var gpon    = r.gpon     || '—';
        var sp      = (r.splitters || '—').split(',')[0].trim();
        var mins    = parseInt(r.aging_abertos, 10) || 0;
        var tempo   = formatAging(mins);
        var cidade  = (r.localidade || '—').toUpperCase();
        var pm      = r.previsao_finalizacao ? String(r.previsao_finalizacao).match(/[ T](\d{2}):(\d{2})/) : null;
        var prevTxt = pm ? ' - Prev. às ' + pm[1] + ':' + pm[2] : '';
        var tempoFmt = mins >= 450 ? '*' + tempo + '*' : tempo;
        var emoji    = mins >= 720 ? '🔴 ' : mins >= 450 ? '🟠 ' : '';
        lines.push(emoji + oc + ' - ' + gpon + ' - ' + sp + ' - ' + tempoFmt + ' - ' + cidade + prevTxt);
        lines.push('');
      });
    });
    var text = lines.join('\n').trim();
    var btn  = document.getElementById('emp-popup-copy');
    
    // Tentar usar Clipboard API
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function() {
        if (btn) {
          btn.innerHTML = '<i class="bi bi-check2"></i> Copiado';
          btn.classList.add('copied');
          setTimeout(function() {
            btn.innerHTML = '<i class="bi bi-clipboard"></i> Copiar';
            btn.classList.remove('copied');
          }, 2000);
        }
      }).catch(function(err) {
        console.error('Clipboard API failed:', err);
        fallbackCopy(text, btn);
      });
    } else {
      // Fallback para navegadores mais antigos
      fallbackCopy(text, btn);
    }
  }
  
  function fallbackCopy(text, btn) {
    var textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
      document.execCommand('copy');
      if (btn) {
        btn.innerHTML = '<i class="bi bi-check2"></i> Copiado';
        btn.classList.add('copied');
        setTimeout(function() {
          btn.innerHTML = '<i class="bi bi-clipboard"></i> Copiar';
          btn.classList.remove('copied');
        }, 2000);
      }
    } catch (err) {
      console.error('Fallback copy failed:', err);
      flash('Erro ao copiar', 'error');
    }
    document.body.removeChild(textarea);
  }

  /* ── Init ────────────────────────────────────────────────── */
  function init() {
    initSidebar();
    initSfCollapse();
    initSfSearch();
    initUpload();
    initReincPeriod();

    const clearBtn = document.getElementById('btn-clear-filters');
    if (clearBtn) clearBtn.addEventListener('click', clearFilters);
    const sfClearAll = document.getElementById('sf-clear-all');
    if (sfClearAll) sfClearAll.addEventListener('click', clearFilters);

    const chkFibrasil = document.getElementById('chk-ocultar-fibrasil');
    if (chkFibrasil) chkFibrasil.addEventListener('change', () => refreshFilters(true));

    const btnSaveEdit = document.getElementById('btn-save-edit');
    if (btnSaveEdit) btnSaveEdit.addEventListener('click', saveEdit);

    const btnAddComment = document.getElementById('btn-add-comment');
    if (btnAddComment) btnAddComment.addEventListener('click', addComment);

    const btnPrevisaoSave = document.getElementById('btn-previsao-save');
    if (btnPrevisaoSave) btnPrevisaoSave.addEventListener('click', savePrevisao);

    const btnPrevisaoClear = document.getElementById('btn-previsao-clear');
    if (btnPrevisaoClear) btnPrevisaoClear.addEventListener('click', clearPrevisao);

    const btnViewEdit = document.getElementById('btn-view-edit');
    if (btnViewEdit) btnViewEdit.addEventListener('click', function() {
      bootstrap.Modal.getInstance(document.getElementById('modalView'))?.hide();
      if (viewId) openEdit(viewId);
    });

    const btnViewDelete = document.getElementById('btn-view-delete');
    if (btnViewDelete) btnViewDelete.addEventListener('click', function() {
      bootstrap.Modal.getInstance(document.getElementById('modalView'))?.hide();
      if (viewId) confirmDelete(viewId, viewOc);
    });

    const btnExecDelete = document.getElementById('btn-exec-delete');
    if (btnExecDelete) btnExecDelete.addEventListener('click', execDelete);

    const btnExecDeleteComment = document.getElementById('btn-exec-delete-comment');
    if (btnExecDeleteComment) btnExecDeleteComment.addEventListener('click', execDeleteComment);

    const btnNewUser = document.getElementById('btn-new-user');
    if (btnNewUser) btnNewUser.addEventListener('click', openUserCreate);

    const btnSaveUser = document.getElementById('btn-save-user');
    if (btnSaveUser) btnSaveUser.addEventListener('click', saveUser);

    // KPI cards filtráveis — acionam toggleFilter diretamente
    document.querySelectorAll('.kpi-card-filterable').forEach(card => {
      card.addEventListener('click', () => {
        const value = card.dataset.slaFilter;
        toggleFilter('status_prazo', value);
        card.classList.toggle('kpi-active', state.filters.status_prazo.includes(value));
      });
    });

    // Modal Histórico — fechar
    var histCloseBtn = document.getElementById('hist-modal-close');
    if (histCloseBtn) histCloseBtn.addEventListener('click', closeHistoricoModal);
    var histOverlay = document.getElementById('modal-historico');
    if (histOverlay) histOverlay.addEventListener('click', function(e) { if (e.target === histOverlay) closeHistoricoModal(); });
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && histOverlay && histOverlay.style.display !== 'none') closeHistoricoModal();
    });

    // Modal Histórico — filtros de período
    document.addEventListener('click', function(e) {
      var btn = e.target.closest('#modal-historico .hist-period-btn');
      if (!btn || !_histGpon) return;
      document.querySelectorAll('#modal-historico .hist-period-btn').forEach(function(b) { b.classList.remove('active'); });
      btn.classList.add('active');
      var periodo = btn.dataset.periodo || '';
      try { localStorage.setItem('gpon_hist_period', periodo); } catch(e) {}
      _fetchHistorico(_histGpon, _histSp, periodo);
    });

    // Badge Reinc. — clique abre modal histórico (delegação no tbody)
    $(document).on('click', '#gpon-table .badge-reinc', function(e) {
      e.stopPropagation(); // não abrir modal de linha
      if (!state.table) return;
      var rowData = state.table.row($(this).closest('tr')).data();
      if (!rowData || !rowData.gpon) return;
      var detail  = rowData.reincidencia_detail || [];
      var topSp   = detail.reduce(function(best, d) { return (!best || d.cnt > best.cnt) ? d : best; }, null);
      if (!topSp) return;
      var mainSp  = extractMainSp(topSp.sp);
      openHistoricoModal(rowData.gpon, mainSp, rowData.reincidencia_max);
    });

    // KPI Empresas — popup de detalhamento
    var kpiEmpCard = document.querySelector('.kpi-card[data-type="empresas"]');
    if (kpiEmpCard) kpiEmpCard.addEventListener('click', openEmpresasPopup);
    var empClose    = document.getElementById('emp-popup-close');
    var empBackdrop = document.getElementById('emp-popup-backdrop');
    var empCopy     = document.getElementById('emp-popup-copy');
    if (empClose)    empClose.addEventListener('click',    closeEmpresasPopup);
    if (empBackdrop) empBackdrop.addEventListener('click', closeEmpresasPopup);
    if (empCopy)     empCopy.addEventListener('click',     copyEmpresasPopup);
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        var p = document.getElementById('popup-empresas');
        if (p && p.style.display !== 'none') closeEmpresasPopup();
      }
    });

    // Load initial data
    if (document.getElementById('gpon-table')) {
      // Restaura UF salva anteriormente pelo usuário
      try {
        var _savedUf = JSON.parse(localStorage.getItem('gpon_uf') || '[]');
        if (Array.isArray(_savedUf) && _savedUf.length > 0) {
          state.filters.uf = _savedUf;
        }
      } catch(e) {}
      loadFilterOptions();
      loadData();
    }

    // Admin panel
    if (document.getElementById('users-tbody')) {
      loadUsers();
    }
  }

  /* ── Public API ──────────────────────────────────────────── */
  return {
    init,
    openView,
    openEdit,
    openHistory,
    editHistItem,
    cancelHistEdit,
    saveHistItem,
    deleteHistItem,
    execDeleteComment,
    confirmDelete,
    execDelete,
    openUserCreate,
    openUserEdit,
    deleteUser,
    clearFilters,
    flash,
    openHistoricoModal,
    savePrevisao,
    clearPrevisao,
  };
})();

// Global BASE_PATH defined inline in HTML before this script
document.addEventListener('DOMContentLoaded', () => GPON.init());

/* ── Polling: Última Atualização ───────────────────────────── */
(function() {
  var el   = document.getElementById('topbar-ua-text');
  var wrap = document.getElementById('topbar-last-update');
  if (!el || !wrap) return;

  var _lastTs = 0;

  function pollUltimaAtualizacao() {
    fetch(BASE_PATH + '/api/ultima-atualizacao')
      .then(function(r) { return r.json(); })
      .then(function(d) {
        var txt = d.ultima_atualizacao ? 'Atualizado: ' + d.ultima_atualizacao : 'Sem importações';
        el.textContent = txt;
        if (_lastTs > 0 && d.ts > _lastTs) {
          wrap.style.animation = 'none';
          wrap.offsetHeight;
          wrap.style.animation = 'ua-pulse .6s ease';
        }
        _lastTs = d.ts || 0;
        var age = d.ts ? (Date.now() / 1000 - d.ts) : 0;
        wrap.classList.toggle('stale', age > 86400);
      })
      .catch(function() {});
  }

  setInterval(pollUltimaAtualizacao, 60000);
})();
