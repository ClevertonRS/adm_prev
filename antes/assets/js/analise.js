(function() {
  'use strict';

  var CRIT_COLOR = { alta: '#dc2626', media: '#d97706', baixa: '#16a34a' };
  var CRIT_LABEL = { alta: 'Alta', media: 'Média', baixa: 'Baixa' };
  var CRIT_CLS   = { alta: 'crit-alta', media: 'crit-media', baixa: 'crit-baixa' };

  var dtLang = {
    search: 'Buscar:', lengthMenu: 'Exibir _MENU_',
    info: 'Mostrando _START_–_END_ de _TOTAL_',
    paginate: { previous: '‹', next: '›' },
    zeroRecords: 'Nenhum registro encontrado', emptyTable: 'Nenhum dado disponível',
  };

  function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }
  function rankNum(n) {
    return '<span class="rank-num">' + (n < 10 ? '0' + n : n) + '</span>';
  }
  function critBadge(c) {
    return '<span class="badge-status crit-badge ' + (CRIT_CLS[c] || '') + '">' + (CRIT_LABEL[c] || c) + '</span>';
  }
  function barHtml(count, max, crit) {
    var pct = max > 0 ? Math.round(count / max * 100) : 0;
    return '<div class="bar-wrap" style="min-width:120px">' +
      '<div class="bar-fill" style="width:' + pct + '%;background:' + (CRIT_COLOR[crit] || '#7c3aed') + '"></div>' +
      '<span class="bar-label">' + pct + '%</span></div>';
  }

  var _CRIT_LEVELS = [
    { label: 'Baixa', bg: '#dcfce7', color: '#15803d', border: '#86efac', barColor: '#16a34a' },
    { label: 'Média', bg: '#fef3c7', color: '#b45309', border: '#fde68a', barColor: '#d97706' },
    { label: 'Alta',  bg: '#fee2e2', color: '#b91c1c', border: '#fca5a5', barColor: '#dc2626' },
  ];

  function _fixedCritLevel(count) {
    if (count >= 5) return 2;   // Alta
    if (count >= 2) return 1;   // Média
    return 0;                    // Baixa
  }

  function _critBadgeHtml(level, title) {
    var l = _CRIT_LEVELS[level];
    var t = title ? ' title="' + title + '"' : '';
    return '<span class="badge-status crit-badge"' + t + ' style="background:' + l.bg + ';color:' + l.color + ';border-color:' + l.border + ';font-size:10px">' + l.label + '</span>';
  }

  function fmtGponCritBadge(count) {
    return _critBadgeHtml(_fixedCritLevel(count));
  }

  function fmtCombCritBadge(count) {
    var tip = count + ' ocorrência' + (count !== 1 ? 's' : '');
    return _critBadgeHtml(_fixedCritLevel(count), tip);
  }

  function fmtLastCritBadge(count, dateStr) {
    var level = _fixedCritLevel(count);
    var recLabel = '';
    if (dateStr) {
      var diffH = (Date.now() - new Date(dateStr).getTime()) / 3600000;
      if      (diffH < 24)  { recLabel = 'hoje'; }
      else if (diffH < 48)  { recLabel = 'ontem'; }
      else if (diffH < 72)  { recLabel = '2-3 dias'; }
      else if (diffH > 168) { recLabel = '>7 dias'; }
      else                  { recLabel = '3-7 dias'; }
    }
    var tip = count + ' ocorrência' + (count !== 1 ? 's' : '') + (recLabel ? ' • Última OC: ' + recLabel : '');
    return _critBadgeHtml(level, tip);
  }

  function _combBarColor(count) {
    return _CRIT_LEVELS[_fixedCritLevel(count)].barColor;
  }

  function fmtTmrBadge(horas, cnt) {
    if (horas == null) return '<span style="color:#94a3b8;font-size:11px">—</span>';
    var h = Math.round(horas);
    var label, color, bg;
    if (h <= 4)       { color = '#15803d'; bg = '#dcfce7'; label = 'Bom';     }
    else if (h <= 8)  { color = '#b45309'; bg = '#fef9c3'; label = 'Atenção'; }
    else              { color = '#dc2626'; bg = '#fee2e2'; label = 'Crítico';  }
    var fmt = h < 24 ? h + 'h' : (Math.floor(h/24) + 'd ' + (h%24 > 0 ? h%24 + 'h' : ''));
    var title = 'Tempo médio baseado em: ' + cnt + ' OC' + (cnt !== 1 ? 's' : '') + ' encerrada' + (cnt !== 1 ? 's' : '');
    return '<span class="mono" style="font-size:12px;color:' + color + ';background:' + bg +
           ';padding:2px 7px;border-radius:4px;font-weight:700" title="' + title + '">' +
           fmt + '</span>';
  }

  /* ── DataTables ─────────────────────────────────────────── */
  var dtGpon = null, dtComb = null, dtLast = null;
  var maxGpon = 1, maxComb = 1;
  var _combOrigData = [];
  var _periodDays = 30;

  var _OCORR_LABELS = {
    '': 'OC / Histórico', '24h': 'OC / 24h', 'hoje': 'OC / Hoje',
    'ontem': 'OC / Ontem', '7d': 'OC / 7 Dias', '15d': 'OC / 15 Dias',
    '30d': 'OC / 30 Dias', 'custom': 'OC / Período'
  };
  var _MES_NOMES = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                    'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
  function _updateGponTableHeaders() {
    var p      = selPeriodo ? selPeriodo.value : '30d';
    var ocLbl  = _OCORR_LABELS[p] || 'OC / Período';
    var now    = new Date();
    var cur    = now.getMonth();
    var prev   = (cur + 11) % 12;
    var th     = document.getElementById('th-ocorr');
    var thAnt  = document.getElementById('th-mes-ant');
    var thAtual = document.getElementById('th-mes-atual');
    if (th)      th.textContent      = ocLbl;
    if (thAnt)   thAnt.textContent   = _MES_NOMES[prev];
    if (thAtual) thAtual.textContent = _MES_NOMES[cur];
    if (dtGpon) {
      $(dtGpon.column(2).header()).text(ocLbl);
      $(dtGpon.column(3).header()).text(_MES_NOMES[prev]);
      $(dtGpon.column(4).header()).text(_MES_NOMES[cur]);
    }
  }

  function getPeriodDays() {
    var p = selPeriodo.value;
    if (p === '7d')  return 7;
    if (p === '15d') return 15;
    if (p === '30d') return 30;
    if (p === '24h' || p === 'hoje' || p === 'ontem') return 1;
    if (p === 'custom') {
      var d1 = inpInicio.value, d2 = inpFim.value;
      if (d1 && d2) {
        var diff = (new Date(d2) - new Date(d1)) / 86400000 + 1;
        return diff > 0 ? Math.round(diff) : 1;
      }
    }
    return 30;
  }

  function initGponTable(data) {
    maxGpon = data.length ? data[0].count : 1;
    _updateGponTableHeaders();
    if (dtGpon) { dtGpon.clear().rows.add(data).draw(); return; }
    dtGpon = $('#tbl-gpon').DataTable({
      data: data, pageLength: 25, order: [[2, 'desc']], language: dtLang,
      dom: '<"gpon-dt-top"l>rt<"bottom"ip>',
      columns: [
        { data: 'rank',      render: function(d) { return rankNum(d); }, width: '50px', orderable: false },
        { data: 'gpon',      render: function(d) { return '<span class="mono fw-700 gpon-link-analitico" data-gpon="' + esc(d) + '" style="font-size:13px">' + esc(d) + '</span>'; } },
        { data: 'count',     render: function(d, _, row) { return '<span class="mono fw-700 ocorrencia-link" data-gpon="' + esc(row.gpon) + '" data-tipo-historico="periodo" title="Clique para abrir o histórico de ocorrências">' + d + '</span>'; } },
        { data: 'mes_ant',   className: 'text-center', width: '65px',
          render: function(d) { return d ? '<span class="mono" style="color:#64748b">' + d + '</span>' : '<span class="gpon-td-nd">—</span>'; }
        },
        { data: 'mes_atual', className: 'text-center', width: '65px',
          render: function(d) { return d ? '<span class="mono" style="color:#334155">' + d + '</span>' : '<span class="gpon-td-nd">—</span>'; }
        },
        { data: 'count',     render: function(d) {
            var dias = _periodDays || 1;
            var media = (d / dias).toLocaleString('pt-BR', {minimumFractionDigits:1, maximumFractionDigits:1});
            return '<span class="mono" style="color:#0369a1">' + media + '/dia</span>';
          }
        },
        { data: 'count',     render: function(d) { return fmtGponCritBadge(d); } },
        { data: null,        render: function(_, __, row) { return fmtTmrBadge(row.tmr_horas, row.tmr_count || 0); } },
        { data: 'gpon',      orderable: false, width: '72px',
          render: function(d) {
            return '<span class="gpon-tend-cell" data-gpon="' + esc(d) + '"><span class="gpon-td-skel"></span></span>';
          }
        },
      ],
    });
    // Injetar campo de busca no topo, ao lado do seletor "Exibir"
    $('#tbl-gpon').closest('.dataTables_wrapper').find('.gpon-dt-top').append(
      '<div class="filter-pill gpon-search-pill" id="gpon-search-pill">'
      + '<i class="bi bi-search"></i>'
      + '<input type="text" id="gpon-search-input" class="analise-inp" placeholder="Buscar GPON..." autocomplete="off">'
      + '<span class="gpon-search-count" id="gpon-search-count"></span>'
      + '<button class="filter-clear" id="gpon-search-clear" title="Limpar pesquisa"><i class="bi bi-x-lg"></i></button>'
      + '</div>'
    );
    var $srch  = $('#gpon-search-input');
    var $clr   = $('#gpon-search-clear');
    var $count = $('#gpon-search-count');

    function _updateSearchCount() {
      var v = $srch.val();
      if (v) {
        var n = dtGpon.rows({ search: 'applied' }).count();
        $count.text(n + (n !== 1 ? ' resultados' : ' resultado')).css('display', 'inline');
      } else {
        $count.css('display', 'none');
      }
    }

    function _loadVisibleGpon() {
      setTimeout(function() {
        if (!dtGpon) return;
        _updateSearchCount();
        dtGpon.rows({ page: 'current' }).every(function() {
          var g = this.data() && this.data().gpon;
          if (g) _fetchResumo(g);
        });
      }, 80);
    }
    dtGpon.on('draw.dt', _loadVisibleGpon);
    _loadVisibleGpon();

    $srch.on('input', function() {
      var v = this.value;
      dtGpon.search(v).draw();
      $clr.css('display', v ? 'inline-flex' : 'none');
    });
    $clr.on('click', function() {
      $srch.val('');
      dtGpon.search('').draw();
      $(this).hide();
      $count.hide();
    });
  }

  function _wireSearch(tblId, dt, placeholder) {
    $('#' + tblId).closest('.dataTables_wrapper').find('.gpon-dt-top').append(
      '<div class="filter-pill gpon-search-pill">'
      + '<i class="bi bi-search"></i>'
      + '<input type="text" id="' + tblId + '-search" class="analise-inp" placeholder="' + placeholder + '" autocomplete="off">'
      + '<span class="gpon-search-count" id="' + tblId + '-search-count"></span>'
      + '<button class="filter-clear" id="' + tblId + '-search-clear" title="Limpar pesquisa"><i class="bi bi-x-lg"></i></button>'
      + '</div>'
    );
    var $s = $('#' + tblId + '-search');
    var $c = $('#' + tblId + '-search-clear');
    var $n = $('#' + tblId + '-search-count');
    function _upd() {
      var v = $s.val();
      if (v) { var n = dt.rows({search:'applied'}).count(); $n.text(n + (n !== 1 ? ' resultados' : ' resultado')).css('display','inline'); }
      else $n.css('display','none');
    }
    dt.on('draw.dt', function() { setTimeout(_upd, 80); });
    $s.on('input', function() { dt.search(this.value).draw(); $c.css('display', this.value ? 'inline-flex' : 'none'); });
    $c.on('click', function() { $s.val(''); dt.search('').draw(); $(this).hide(); $n.hide(); });
  }

  function initCombTable(data) {
    _combOrigData = data.map(function(r) { return Object.assign({}, r, { _rel: 0 }); });
    maxComb = data.length ? data[0].count : 1;
    if (dtComb) { dtComb.clear().rows.add(_combOrigData).draw(); return; }
    dtComb = $('#tbl-comb').DataTable({
      data: _combOrigData, pageLength: 25, order: [[3, 'desc']], language: dtLang,
      dom: '<"gpon-dt-top"l>rt<"bottom"ip>',
      columns: [
        { data: 'gpon',  render: function(d) { return '<span class="mono" style="font-size:12px">' + esc(d) + '</span>'; } },
        { data: 'sp',    render: function(d) { return d ? '<span class="badge" style="background:#f1f5f9;color:#475569;font-size:10px;font-weight:600;font-family:monospace">' + esc(d) + '</span>' : '—'; } },
        { data: 'count', render: function(d) { return '<span class="mono fw-700" style="font-size:14px;color:#7c3aed">' + d + '</span>'; } },
        { data: 'pct',   render: function(d) { return '<span class="mono">' + d + '%</span>'; } },
        { data: 'count', render: function(d) { return fmtCombCritBadge(d); } },
        { data: null,    render: function(_, __, row) {
            var pct = maxComb > 0 ? Math.round(row.count / maxComb * 100) : 0;
            return '<div class="bar-wrap" style="min-width:120px"><div class="bar-fill" style="width:' + pct + '%;background:' + _combBarColor(row.count) + '"></div><span class="bar-label">' + pct + '%</span></div>';
          }
        },
        { data: '_rel', visible: false, searchable: false }, // col 6 — relevância para ordenação
      ],
      rowCallback: function(row) { $(row).addClass('hist-tbl-clickable').attr('title', 'Clique para ver o histórico de OCs'); },
    });
    // Campo de busca com ordenação por relevância
    $('#tbl-comb').closest('.dataTables_wrapper').find('.gpon-dt-top').append(
      '<div class="filter-pill gpon-search-pill">'
      + '<i class="bi bi-search"></i>'
      + '<input type="text" id="tbl-comb-search" class="analise-inp" placeholder="Buscar GPON ou Splitter..." autocomplete="off">'
      + '<span class="gpon-search-count" id="tbl-comb-search-count"></span>'
      + '<button class="filter-clear" id="tbl-comb-search-clear" title="Limpar pesquisa"><i class="bi bi-x-lg"></i></button>'
      + '</div>'
    );
    var $s = $('#tbl-comb-search'), $c = $('#tbl-comb-search-clear'), $n = $('#tbl-comb-search-count');
    function _combSearch(v) {
      if (!v) {
        dtComb.order([[3, 'desc']]).clear().rows.add(_combOrigData).draw();
        $c.hide(); $n.hide();
        return;
      }
      var vl = v.toLowerCase();
      function _fScore(f) { var s=(f||'').toLowerCase(); if(s===vl)return 0; if(s.indexOf(vl)===0)return 1; if(s.indexOf(vl)!==-1)return 2; return 99; }
      function _nScore(f) { var num=(f||'').toLowerCase().replace(/^\D+/,''); if(!num)return 99; if(num===vl)return 0; if(num.indexOf(vl)===0)return 1; if(num.indexOf(vl)!==-1)return 2; return 99; }
      var scored = [];
      _combOrigData.forEach(function(row) {
        var score = Math.min(_fScore(row.sp), _nScore(row.sp), _fScore(row.gpon));
        if (score >= 99) return;
        scored.push({ row: row, score: score });
      });
      // Ordena em JS: relevância primeiro, depois contagem desc
      scored.sort(function(a, b) {
        return a.score !== b.score ? a.score - b.score : (b.row.count - a.row.count);
      });
      // _rel vira índice sequencial para que o DataTables preserve a ordem exata
      var finalRows = scored.map(function(s, i) { return Object.assign({}, s.row, { _rel: i }); });
      dtComb.order([[6, 'asc']]).clear().rows.add(finalRows).draw();
      var n = scored.length;
      $n.text(n + (n !== 1 ? ' resultados' : ' resultado')).css('display', 'inline');
      $c.css('display', 'inline-flex');
    }
    $s.on('input', function() { _combSearch(this.value); });
    $c.on('click', function() { $s.val(''); _combSearch(''); });
  }

  function initLastTable(data) {
    var maxC = data.length ? Math.max.apply(null, data.map(function(r) { return r.count; })) : 1;
    function fmtDt(iso) {
      if (!iso) return '—';
      var m = iso.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
      return m ? m[3] + '/' + m[2] + ' ' + m[4] + ':' + m[5] : iso;
    }
    if (dtLast) { dtLast.clear().rows.add(data).draw(); return; }
    dtLast = $('#tbl-last').DataTable({
      data: data, pageLength: 25, order: [[3, 'desc']], language: dtLang,
      dom: '<"gpon-dt-top"l>rt<"bottom"ip>',
      columns: [
        { data: 'gpon',  render: function(d) { return '<span class="mono" style="font-size:12px">' + esc(d) + '</span>'; } },
        { data: 'sp',    render: function(d) { return d ? '<span class="badge" style="background:#f1f5f9;color:#475569;font-size:10px;font-weight:600;font-family:monospace">' + esc(d) + '</span>' : '—'; } },
        { data: 'oc',    render: function(d) { return d ? '<span class="mono" style="font-size:11px;color:#7c3aed">' + esc(d) + '</span>' : '—'; } },
        { data: 'date',  render: function(d) { return '<span class="mono" style="font-size:12px">' + fmtDt(d) + '</span>'; } },
        { data: 'count', render: function(d) { return '<span class="mono fw-700" style="font-size:14px;color:#7c3aed">' + d + '</span>'; } },
        { data: null,    render: function(_, __, row) { return fmtLastCritBadge(row.count, row.date); } },
      ],
      rowCallback: function(row) { $(row).addClass('hist-tbl-clickable').attr('title', 'Clique para ver o histórico de OCs'); },
    });
    _wireSearch('tbl-last', dtLast, 'Buscar GPON...');
  }

  /* ── Tabs ───────────────────────────────────────────────── */
  var activeTab = 'gpon';
  document.querySelectorAll('.tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
      document.querySelectorAll('.tab-content').forEach(function(t) { t.style.display = 'none'; });
      this.classList.add('active');
      activeTab = this.dataset.tab;
      var el = document.getElementById('tab-' + activeTab);
      if (el) el.style.display = '';
      var d = window._analiseData;
      if (d && activeTab === 'comb') initCombTable(d.comb_ranking || []);
      if (d && activeTab === 'last') initLastTable(d.last_seen    || []);
      setTimeout(function() {
        var id = '#tbl-' + activeTab;
        if ($.fn.DataTable.isDataTable(id)) $(id).DataTable().columns.adjust();
      }, 50);
    });
  });

  /* ── Filtros ────────────────────────────────────────────── */
  var selPeriodo   = document.getElementById('sel-periodo');
  var customDates  = document.getElementById('custom-dates');
  var inpInicio    = document.getElementById('inp-inicio');
  var inpFim       = document.getElementById('inp-fim');
  var btnAplicar   = document.getElementById('btn-aplicar');
  var periodoBadge = document.getElementById('periodo-badge');
  var inpGpon      = document.getElementById('inp-gpon');
  var inpSp        = document.getElementById('inp-sp');
  var inpCausa     = document.getElementById('inp-causa');
  var chkValidos   = document.getElementById('chk-validos');
  var activeUf     = '';
  var ufBtns       = document.querySelectorAll('.uf-btn');

  // ── Switches: estado padrão ON + persistência localStorage ──
  var SWITCH_STORE_KEY = 'gpon_analise_switches';
  function _saveSwitches() {
    var chkFib = document.getElementById('chk-ocultar-fibrasil');
    try {
      localStorage.setItem(SWITCH_STORE_KEY, JSON.stringify({
        improcedentes: chkValidos ? chkValidos.checked : true,
        fibrasil:      chkFib    ? chkFib.checked     : true
      }));
    } catch(e) {}
  }
  (function _restoreSwitches() {
    try {
      var saved = JSON.parse(localStorage.getItem(SWITCH_STORE_KEY) || 'null');
      if (saved && typeof saved === 'object') {
        if (chkValidos) chkValidos.checked = saved.improcedentes !== false;
        var chkFib = document.getElementById('chk-ocultar-fibrasil');
        if (chkFib) chkFib.checked = saved.fibrasil !== false;
      }
      // sem chave salva: mantém o checked=true do HTML (padrão ON)
    } catch(e) {}
  })();

  var PERIODO_LABEL = {
    '24h':'Últimas 24h','hoje':'Hoje','ontem':'Ontem',
    '7d':'Últimos 7 dias','15d':'Últimos 15 dias','30d':'Últimos 30 dias',
  };

  var debTimer = null;
  function debounceLoad() { clearTimeout(debTimer); debTimer = setTimeout(loadAnalise, 600); }

  selPeriodo.addEventListener('change', function() {
    customDates.style.display = (this.value === 'custom') ? 'flex' : 'none';
    if (this.value !== 'custom') loadAnalise();
  });
  btnAplicar.addEventListener('click', function() { if (inpInicio.value && inpFim.value) loadAnalise(); });
  inpGpon.addEventListener('input', debounceLoad);
  inpSp.addEventListener('input', debounceLoad);
  inpCausa.addEventListener('input', debounceLoad);
  chkValidos.addEventListener('change', function() { _saveSwitches(); loadAnalise(); });
  var chkFibrasisEl = document.getElementById('chk-ocultar-fibrasil');
  if (chkFibrasisEl) chkFibrasisEl.addEventListener('change', function() { _saveSwitches(); loadAnalise(); });

  // ── Filtros: botão X individual por campo ────────────────────
  var btnClearGpon  = document.getElementById('clear-inp-gpon');
  var btnClearSp    = document.getElementById('clear-inp-sp');
  var btnClearCausa = document.getElementById('clear-inp-causa');

  function _updateClearBtns() {
    if (btnClearGpon)  btnClearGpon.style.display  = inpGpon.value.trim()  ? 'flex' : 'none';
    if (btnClearSp)    btnClearSp.style.display    = inpSp.value.trim()    ? 'flex' : 'none';
    if (btnClearCausa) btnClearCausa.style.display = inpCausa.value.trim() ? 'flex' : 'none';
  }

  if (btnClearGpon)  btnClearGpon.addEventListener('click',  function() { inpGpon.value  = ''; _updateClearBtns(); loadAnalise(); });
  if (btnClearSp)    btnClearSp.addEventListener('click',    function() { inpSp.value    = ''; _updateClearBtns(); loadAnalise(); });
  if (btnClearCausa) btnClearCausa.addEventListener('click', function() { inpCausa.value = ''; _updateClearBtns(); loadAnalise(); });

  inpGpon.addEventListener('input', _updateClearBtns);
  inpSp.addEventListener('input', _updateClearBtns);
  inpCausa.addEventListener('input', _updateClearBtns);
  ufBtns.forEach(function(btn) {
    btn.addEventListener('click', function() {
      activeUf = this.dataset.uf;
      ufBtns.forEach(function(b) { b.classList.remove('active', 'active-mt', 'active-ms', 'active-df', 'active-go'); });
      this.classList.add('active');
      if (activeUf === 'MT') this.classList.add('active-mt');
      if (activeUf === 'MS') this.classList.add('active-ms');
      if (activeUf === 'DF') this.classList.add('active-df');
      if (activeUf === 'GO') this.classList.add('active-go');
      loadAnalise();
    });
  });

  function buildParams() {
    var parts = [];
    var p = selPeriodo.value;
    if (p) {
      parts.push('periodo=' + p);
      if (p === 'custom') {
        if (inpInicio.value) parts.push('inicio=' + inpInicio.value);
        if (inpFim.value)    parts.push('fim='    + inpFim.value);
      }
    }
    var gv = inpGpon.value.trim(), sv = inpSp.value.trim(), cv = inpCausa.value.trim();
    if (activeUf) parts.push('uf=' + encodeURIComponent(activeUf));
    if (gv) parts.push('gpon='        + encodeURIComponent(gv));
    if (sv) parts.push('sp='          + encodeURIComponent(sv));
    if (cv) parts.push('baixa_causa=' + encodeURIComponent(cv));
    if (chkValidos.checked) parts.push('ocultar_improcedentes=1');
    var chkFibrasil = document.getElementById('chk-ocultar-fibrasil');
    if (chkFibrasil && chkFibrasil.checked) parts.push('ocultar_fibrasil=1');
    return parts.join('&');
  }

  function updatePeriodoBadge() {
    var v = selPeriodo.value;
    if (!v) { periodoBadge.style.display = 'none'; return; }
    var label = v === 'custom'
      ? (inpInicio.value || '?') + ' → ' + (inpFim.value || '?')
      : (PERIODO_LABEL[v] || v);
    periodoBadge.textContent = '📅 ' + label;
    periodoBadge.style.display = 'inline-flex';
  }

  /* ── Heatmap ────────────────────────────────────────────── */
  function renderHeatmap(h) {
    var el = document.getElementById('heatmap-wrap');
    if (!h || !h.combos || !h.combos.length || !h.dates || !h.dates.length) {
      el.innerHTML = '<div class="analise-empty"><i class="bi bi-grid"></i>Sem dados suficientes para o mapa de calor</div>';
      return;
    }

    var maxCell = 0;
    h.combos.forEach(function(combo) {
      h.dates.forEach(function(d) { if ((combo.days[d] || 0) > maxCell) maxCell = combo.days[d] || 0; });
    });
    if (maxCell === 0) maxCell = 1;

    function dayCrit(v) {
      if (v === 0) return 'zero';
      if (v >= 5)  return 'alta';
      if (v >= 2)  return 'media';
      return 'baixa';
    }

    function fmtDate(iso) {
      var p = iso.split('-');
      return p[2] + '/' + p[1];
    }

    var html = '<div class="heatmap-scroll"><table class="heatmap-tbl"><thead><tr>';
    html += '<th style="text-align:left;position:sticky;left:0;background:#f8f7ff;min-width:150px">GPON + Splitter</th>';
    h.dates.forEach(function(d) { html += '<th class="hm-date-th">' + fmtDate(d) + '</th>'; });
    html += '<th class="hm-total">Total</th>';
    html += '</tr></thead><tbody>';

    h.combos.forEach(function(combo) {
      html += '<tr>';
      html += '<td class="heatmap-gpon-label">' + esc(combo.gpon) + ' - ' + esc(combo.sp) + '</td>';
      h.dates.forEach(function(d) {
        var v   = combo.days[d] || 0;
        var cls = 'hm-' + dayCrit(v);
        var tip = v ? esc(combo.gpon) + ' - ' + esc(combo.sp) + ' • ' + v + (v === 1 ? ' ocorrência' : ' ocorrências') + ' — clique para ver detalhes' : esc(combo.gpon) + ' - ' + esc(combo.sp);
        if (v > 0) {
          html += '<td class="' + cls + ' hm-clickable" title="' + tip + '" data-gpon="' + esc(combo.gpon) + '" data-sp="' + esc(combo.sp) + '" data-date="' + esc(d) + '" data-count="' + v + '">' + v + '</td>';
        } else {
          html += '<td class="' + cls + '" title="' + tip + '">·</td>';
        }
      });
      if (combo.total > 0) {
        html += '<td class="hm-total hm-total-clickable"'
              + ' title="Ver todas as ' + combo.total + ' ocorrência' + (combo.total === 1 ? '' : 's') + ' de ' + esc(combo.gpon) + ' — ' + esc(combo.sp) + '"'
              + ' data-gpon="' + esc(combo.gpon) + '"'
              + ' data-sp="'   + esc(combo.sp)   + '"'
              + ' data-total="' + combo.total + '">' + combo.total + '</td>';
      } else {
        html += '<td class="hm-total">·</td>';
      }
      html += '</tr>';
    });

    html += '</tbody></table></div>';
    el.innerHTML = html;
  }

  document.getElementById('heatmap-wrap').addEventListener('click', function(e) {
    var td = e.target.closest('.hm-clickable');
    if (td) { openHeatmapDayModal(td.dataset.gpon, td.dataset.sp, td.dataset.date, parseInt(td.dataset.count, 10) || 0); return; }
    var ttd = e.target.closest('.hm-total-clickable');
    if (ttd) { openHeatmapTotalModal(ttd.dataset.gpon, ttd.dataset.sp, parseInt(ttd.dataset.total, 10) || 0); }
  });

  var _tlPeriodoLabels = { hoje: 'Hoje', '7d': 'Últimos 7 dias', '15d': 'Últimos 15 dias', '30d': 'Últimos 30 dias' };
  document.getElementById('timeline-wrap').addEventListener('click', function(e) {
    var val = e.target.closest('.tl-period-val.tl-clickable');
    if (!val) return;
    openHeatmapPeriodModal(val.dataset.gpon, val.dataset.sp, val.dataset.periodo, _tlPeriodoLabels[val.dataset.periodo] || val.dataset.periodo);
  });

  document.getElementById('causas-wrap').addEventListener('click', function(e) {
    var cnt = e.target.closest('.causa-bar-link');
    if (cnt) openHeatmapCausaModal(cnt.dataset.causa, null);
  });

  /* ── Timeline ────────────────────────────────────────────── */
  function renderTimeline(data) {
    var el = document.getElementById('timeline-wrap');
    if (!data || !data.length) {
      el.innerHTML = '<div class="analise-empty"><i class="bi bi-activity"></i>Sem dados de timeline para o período</div>';
      return;
    }
    var maxTotal = data[0].total || 1;
    var html = '<div class="timeline-list">';
    data.forEach(function(item) {
      var cls      = 'tl-item crit-' + (item.crit || 'baixa');
      var spCls    = { alta:'reinc-vermelho', media:'reinc-amarelo', baixa:'reinc-verde' }[item.crit] || 'reinc-cinza';
      var barColor = CRIT_COLOR[item.crit] || '#7c3aed';
      var pct      = maxTotal > 0 ? Math.round(item.total / maxTotal * 100) : 0;
      html += '<div class="' + cls + '">';
      html += '<div class="tl-combo">';
      html += '<span class="tl-gpon">' + esc(item.gpon) + '</span>';
      html += '<span class="badge-reinc ' + spCls + ' tl-sp">' + esc(item.sp) + '</span>';
      html += '</div>';
      var _g = esc(item.gpon), _s = esc(item.sp);
      var _tlv = function(periodo, cls) { return ' class="tl-period-val tl-clickable' + cls + '" data-gpon="' + _g + '" data-sp="' + _s + '" data-periodo="' + periodo + '"'; };
      html += '<div class="tl-periods">';
      html += '<div class="tl-period"><span class="tl-period-label">Hoje</span><span' + _tlv('hoje', '') + '>'  + item.hoje     + '</span></div>';
      html += '<div class="tl-period"><span class="tl-period-label">7d</span><span'   + _tlv('7d',   '') + '>'  + item['7d']   + '</span></div>';
      html += '<div class="tl-period"><span class="tl-period-label">15d</span><span'  + _tlv('15d',  '') + '>'  + item['15d']  + '</span></div>';
      html += '<div class="tl-period"><span class="tl-period-label">30d</span><span'  + _tlv('30d', ' hl') + '>' + item['30d'] + '</span></div>';
      html += '</div>';
      html += '<div class="tl-bar-wrap"><div class="tl-bar-fill" style="width:' + pct + '%;background:' + barColor + '"></div></div>';
      html += '</div>';
    });
    html += '</div>';
    el.innerHTML = html;
  }

  /* ── Causas ──────────────────────────────────────────────── */
  function renderCausas(data) {
    var el = document.getElementById('causas-wrap');
    if (!data || !data.length) {
      el.innerHTML = '<div class="analise-empty"><i class="bi bi-bar-chart"></i>Sem dados de causa<br><small style="font-size:10px;margin-top:4px;display:block">Preencha o campo "baixa_causa" nas ocorrências para habilitar esta análise</small></div>';
      return;
    }
    var maxCount = data[0].count || 1;
    var html = '<div class="causas-list">';
    data.forEach(function(item) {
      var pct = maxCount > 0 ? Math.round(item.count / maxCount * 100) : 0;
      html += '<div class="causa-item">';
      html += '<div class="causa-name" title="' + esc(item.causa) + '">' + esc(item.causa) + '</div>';
      html += '<div class="causa-bar-wrap">';
      html += '<div class="causa-bar causa-bar-link" data-causa="' + esc(item.causa) + '"><div class="causa-fill" style="width:' + pct + '%"></div></div>';
      html += '<span class="causa-count">' + item.count + ' OC' + (item.count !== 1 ? 's' : '') + '</span>';
      html += '</div>';
      if (item.top_reparo) {
        html += '<div class="causa-reparo"><span class="causa-reparo-label">Reparo: </span>' + esc(item.top_reparo) + '</div>';
      }
      html += '</div>';
    });
    html += '</div>';
    el.innerHTML = html;
  }

  /* ── MTTR ────────────────────────────────────────────────── */
  function renderMttr(data) {
    var el    = document.getElementById('mttr-wrap');
    var badge = document.getElementById('mttr-geral-badge');
    if (!data) { el.innerHTML = ''; badge.style.display = 'none'; return; }

    if (data.geral != null) {
      badge.textContent = 'Média geral: ' + data.geral.toLocaleString('pt-BR',{minimumFractionDigits:1,maximumFractionDigits:1}) + 'h';
      badge.style.display = 'inline-flex';
    } else {
      badge.style.display = 'none';
    }

    var cities   = data.por_cidade || [];
    var withMttr = cities.filter(function(c) { return c.mttr != null; });

    if (!cities.length) {
      el.innerHTML = '<div class="analise-empty"><i class="bi bi-stopwatch"></i>Sem ocorrências com localidade no período</div>';
      return;
    }
    if (!withMttr.length) {
      el.innerHTML = '<div class="analise-empty"><i class="bi bi-stopwatch"></i>' +
        'MTTR indisponível — encerramento não registrado nas OCs do período.<br>' +
        '<small style="font-size:10px;margin-top:4px;display:block">Importe planilhas com <em>data_encerramento</em> e <em>aging_encerrados</em> para habilitar esta métrica.</small>' +
        '</div>';
      return;
    }

    var html = '<div class="mttr-grid">';
    cities.forEach(function(item) {
      var mttrStr = item.mttr != null
        ? item.mttr.toLocaleString('pt-BR',{minimumFractionDigits:1,maximumFractionDigits:1}) + 'h'
        : null;
      var mttrData = item.mttr != null ? item.mttr : '';
      html += '<div class="mttr-item" style="cursor:pointer" title="Clique para ver ocorrências de ' + esc(item.cidade) + '"'
            + ' data-cidade="' + esc(item.cidade) + '" data-count="' + item.count + '" data-mttr="' + mttrData + '">';
      html += '<span class="mttr-cidade">' + esc(item.cidade) + '</span>';
      html += '<span class="mttr-count">'  + item.count.toLocaleString('pt-BR') + ' ocorrências</span>';
      html += mttrStr
        ? '<span class="mttr-val">' + mttrStr + '</span>'
        : '<span class="mttr-no-data">Sem encerramento</span>';
      html += '</div>';
    });
    html += '</div>';
    el.innerHTML = html;
  }

  /* ── Load via AJAX ──────────────────────────────────────── */
  var _analiseAbort = null;

  function loadAnalise() {
    updatePeriodoBadge();
    var params = buildParams();
    var url    = BASE_PATH + '/api/analise' + (params ? '?' + params : '');

    if (_analiseAbort) { _analiseAbort.abort(); }
    _analiseAbort = new AbortController();

    fetch(url, { signal: _analiseAbort.signal })
      .then(function(r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function(d) {
        if (d && d.error) { console.error('Analise API error:', d.message); return; }
        window._analiseData = d;
        var t = d.totals || {};

        document.getElementById('kpi-analisados').textContent    = (t.analisados        || 0).toLocaleString('pt-BR');
        document.getElementById('kpi-analisados-sub').textContent =
          (t.gpon_uniq || 0).toLocaleString('pt-BR') + ' OLTs • ' + (t.sp_uniq || 0).toLocaleString('pt-BR') + ' SPs';
        document.getElementById('kpi-reincidentes').textContent  = (t.comb_reincidentes  || 0).toLocaleString('pt-BR');
        var lastSeen    = d.last_seen || [];
        var ultimaReinc = null;
        for (var _i = 0; _i < lastSeen.length; _i++) { if (lastSeen[_i].count > 1) { ultimaReinc = lastSeen[_i]; break; } }
        if (ultimaReinc) {
          document.getElementById('kpi-ultima-reinc-val').textContent  = ultimaReinc.sp + ' • ' + ultimaReinc.count + 'x';
          document.getElementById('kpi-ultima-reinc-gpon').textContent = ultimaReinc.gpon;
          var _dtM = (ultimaReinc.date || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
          document.getElementById('kpi-ultima-reinc-data').textContent = _dtM ? 'Última OC: ' + _dtM[3] + '/' + _dtM[2] + ' - ' + _dtM[4] + ':' + _dtM[5] : '—';
        } else {
          document.getElementById('kpi-ultima-reinc-val').textContent  = '—';
          document.getElementById('kpi-ultima-reinc-gpon').textContent = 'sem reincidências';
          document.getElementById('kpi-ultima-reinc-data').textContent = '—';
        }
        var topComb = t.top_comb;
        document.getElementById('kpi-taxa-reinc').textContent = topComb
          ? topComb.sp + ' • ' + topComb.count + 'x'
          : '—';
        document.getElementById('kpi-top-comb').textContent = topComb
          ? topComb.gpon
          : '—';
        document.getElementById('kpi-indice-reinc').textContent = t.indice_reincidencia != null
          ? t.indice_reincidencia.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}) + '%'
          : '—';
        document.getElementById('kpi-indice-sub').textContent =
          (t.comb_reincidentes || 0).toLocaleString('pt-BR') + ' combinações • ' +
          (t.oc_reincidentes   || 0).toLocaleString('pt-BR') + ' ocorrências';

        document.getElementById('kpi-tmr-val').textContent = t.tmr != null ? fmtAging(t.tmr) : '—';
        document.getElementById('kpi-tmr-sub').textContent = t.tmr_count > 0
          ? (t.tmr_count).toLocaleString('pt-BR') + ' OC' + (t.tmr_count !== 1 ? 's' : '') + ' encerradas no período'
          : 'sem OCs encerradas no período';

        document.getElementById('cnt-gpon').textContent = (d.gpon_ranking || []).length;
        document.getElementById('cnt-comb').textContent = (d.comb_ranking || []).length;
        document.getElementById('cnt-last').textContent = (d.last_seen    || []).length;

        renderHeatmap(d.heatmap   || null);
        renderTimeline(d.timeline || []);
        renderCausas(d.causas     || []);
        renderMttr(d.mttr         || null);

        _periodDays = getPeriodDays();
        _resumoCache = {}; _resumoPending = {};
        initGponTable(d.gpon_ranking || []);
        if (activeTab === 'comb') initCombTable(d.comb_ranking || []);
        if (activeTab === 'last') initLastTable(d.last_seen    || []);
      })
      .catch(function(e) {
        if (e.name !== 'AbortError') console.error('Erro ao carregar análise:', e);
      });
  }

  document.addEventListener('DOMContentLoaded', loadAnalise);
  document.getElementById('btn-reload-page')?.addEventListener('click', function() { location.reload(); });

  /* ── Modal Histórico ─────────────────────────────────────── */
  var dtHistorico  = null;
  var _aHistGpon   = '';
  var _aHistSp     = '';
  var _aHistCidade = '';
  var modalHist   = document.getElementById('modal-historico');
  var histLoading = document.getElementById('hist-loading');
  var histTableW  = document.getElementById('hist-table-wrap');

  function closeHistModal() {
    modalHist.style.display = 'none';
    document.body.style.overflow = '';
  }

  document.getElementById('hist-modal-close').addEventListener('click', closeHistModal);
  modalHist.addEventListener('click', function(e) { if (e.target === modalHist) closeHistModal(); });
  document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && modalHist.style.display !== 'none') closeHistModal(); });
  document.addEventListener('click', function(e) {
    var btn = e.target.closest('#modal-historico .hist-period-btn');
    if (!btn || (!_aHistGpon && !_aHistCidade)) return;
    document.querySelectorAll('#modal-historico .hist-period-btn').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    var _p = btn.dataset.periodo || '';
    try { localStorage.setItem('gpon_hist_period', _p); } catch(e) {}
    if (_aHistCidade) {
      _fetchHistModal('', '', _p, 'cidade=' + encodeURIComponent(_aHistCidade));
    } else {
      _fetchHistModal(_aHistGpon, _aHistSp, _p);
    }
  });

  function fmtDateTimePt(iso) {
    if (!iso) return '—';
    var m = iso.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    return m ? m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] + ':' + m[5] : iso;
  }

  function fmtAging(h) {
    if (h == null) return '—';
    h = parseInt(h, 10);
    if (h < 24) return h + 'h';
    var d = Math.floor(h / 24), r = h % 24;
    return r > 0 ? d + 'd ' + r + 'h' : d + 'd';
  }

  function slaHistBadge(status) {
    if (!status) return '<span style="color:#94a3b8;font-size:10px">—</span>';
    var labels = { ok: 'No Prazo', atencao: 'Atenção', violado: 'Fora do Prazo' };
    var colors = { ok: '#16a34a', atencao: '#d97706', violado: '#dc2626' };
    var bg     = { ok: '#dcfce7', atencao: '#fef9c3', violado: '#fee2e2' };
    return '<span style="font-size:9px;font-weight:700;padding:2px 7px;border-radius:10px;background:' + bg[status] + ';color:' + colors[status] + '">'
         + labels[status] + '</span>';
  }

  function statusHistBadge(st) {
    if (!st) return '—';
    var aberto = /aberto|open|ativo/i.test(st);
    var bg = aberto ? '#eff6ff' : '#f0fdf4';
    var cl = aberto ? '#1d4ed8' : '#15803d';
    return '<span style="font-size:9px;font-weight:700;padding:2px 7px;border-radius:10px;background:' + bg + ';color:' + cl + '">' + esc(st) + '</span>';
  }

  function _buildHistParams(periodo) {
    var parts = buildParams().split('&').filter(function(p) {
      return p && !p.startsWith('periodo=') && !p.startsWith('inicio=') && !p.startsWith('fim=');
    });
    if (periodo) parts.push('periodo=' + encodeURIComponent(periodo));
    return parts.join('&');
  }

  function _fetchHistModal(gpon, sp, periodo, extraQs, pLabelOverride) {
    var pqs    = _buildHistParams(periodo);
    var pLabel = pLabelOverride != null ? pLabelOverride : (periodo ? ' · últimos ' + periodo : ' · histórico completo');
    histLoading.style.display = '';
    histTableW.style.display  = 'none';
    var qs = extraQs
      ? extraQs + (pqs ? '&' + pqs : '')
      : 'gpon=' + encodeURIComponent(gpon) + '&sp=' + encodeURIComponent(sp) + (pqs ? '&' + pqs : '');
    var url    = BASE_PATH + '/api/analise/historico?' + qs;
    var ctxSp  = extraQs ? '' : (sp || '').toUpperCase();
    fetch(url)
      .then(function(r) { return r.json(); })
      .then(function(d) {
        histLoading.style.display = 'none';
        histTableW.style.display  = '';
        document.getElementById('hist-modal-sub').textContent =
          (d.total || 0) + ((d.total === 1) ? ' ocorrência' : ' ocorrências') + pLabel;
        var rows = d.rows || [];
        rows.forEach(function(row) { row._ctx_sp = ctxSp; });
        if (dtHistorico) {
          dtHistorico.clear().rows.add(rows).draw();
        } else {
          dtHistorico = $('#tbl-historico').DataTable({
            data: rows, pageLength: 20, order: [[3, 'desc']], language: dtLang,
            dom: 'Blfrtip',
            buttons: [{ extend: 'excel', text: '<i class="bi bi-file-earmark-excel"></i> Excel', className: 'btn btn-sm btn-success', title: 'Histórico GPON' }],
            columns: [
              { data: 'oc',          render: function(v) { return '<span class="mono fw-700" style="font-size:11px">' + esc(v||'—') + '</span>'; } },
              { data: 'splitters',   width: '90px', render: function(v, _, row) {
                  if (!v) return '<span style="color:#94a3b8;font-size:10px">—</span>';
                  // Canonicaliza tokens brutos (ex: "N03SP193SS1" → "SP193"), deduplica
                  var canonical = [];
                  String(v).split(',').forEach(function(token) {
                    token = token.trim();
                    if (!token) return;
                    var m = token.match(/SP\d+/i);
                    var csp = m ? m[0].toUpperCase() : token;
                    if (canonical.indexOf(csp) === -1) canonical.push(csp);
                  });
                  if (!canonical.length) return '<span style="color:#94a3b8;font-size:10px">—</span>';
                  // SP de contexto vem da própria linha — independente de estado global
                  var primary = (row._ctx_sp || '').toUpperCase();
                  var ordered = primary
                    ? [primary].concat(canonical.filter(function(csp) { return csp !== primary; }))
                    : canonical;
                  var cnt = ordered.length;
                  var clr = cnt === 1 ? { bg: '#f1f5f9', fg: '#475569' } : cnt <= 5 ? { bg: '#fef3c7', fg: '#b45309' } : { bg: '#fee2e2', fg: '#dc2626' };
                  var tooltip = ordered.map(function(s) { return esc(s); }).join('&#10;');
                  var extra = cnt > 1 ? ' <span style="color:#94a3b8;font-size:9px">(+' + (cnt - 1) + ')</span>' : '';
                  return '<span title="' + tooltip + '" style="white-space:nowrap;cursor:default"><span class="badge" style="background:' + clr.bg + ';color:' + clr.fg + ';font-size:10px;font-weight:600;font-family:monospace">' + esc(ordered[0]) + '</span>' + extra + '</span>';
                }
              },
              { data: 'status',      render: function(v) { return statusHistBadge(v); } },
              { data: 'abertura',    render: function(v) { return '<span style="font-size:10px;white-space:nowrap">' + fmtDateTimePt(v) + '</span>'; } },
              { data: 'encerramento',render: function(v) { return '<span style="font-size:10px;white-space:nowrap">' + fmtDateTimePt(v) + '</span>'; } },
              { data: 'sla_status',  render: function(v) { return slaHistBadge(v); } },
              { data: 'cidade',      render: function(v) { return '<span style="font-size:10px;white-space:nowrap">' + esc(v||'—') + '</span>'; } },
              { data: 'empresa',     render: function(v) { return '<span style="font-size:10px">' + esc(v||'—') + '</span>'; } },
              { data: 'baixa_causa', render: function(v) { return '<span style="font-size:10px;max-width:160px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + esc(v||'') + '">' + esc(v||'—') + '</span>'; } },
              { data: 'baixa_reparo',render: function(v) { return '<span style="font-size:10px">' + esc(v||'—') + '</span>'; } },
              { data: 'aging',       render: function(v) { return '<span class="mono" style="font-size:10px">' + fmtAging(v) + '</span>'; } },
            ],
          });
        }
      })
      .catch(function(e) {
        histLoading.innerHTML = '<i class="bi bi-exclamation-triangle" style="color:#dc2626"></i> Erro ao carregar dados.';
        console.error('historico error', e);
      });
  }

  function openHistModal(gpon, sp, count, subExtra) {
    _aHistGpon   = gpon;
    _aHistSp     = sp;
    _aHistCidade = '';
    var title = sp ? (gpon + ' — ' + sp) : ('GPON — ' + gpon);
    var sub   = subExtra || (count + (count === 1 ? ' ocorrência' : ' ocorrências'));
    document.getElementById('hist-modal-title').textContent = title;
    document.getElementById('hist-modal-sub').textContent   = sub + ' · carregando…';
    modalHist.style.display      = 'flex';
    document.body.style.overflow = 'hidden';
    var _savedHp = null;
    try { _savedHp = localStorage.getItem('gpon_hist_period'); } catch(e) {}
    var _defPeriodo = ['', '7d', '15d', '30d'].indexOf(_savedHp) !== -1 ? _savedHp : '30d';
    document.querySelectorAll('#modal-historico .hist-period-btn').forEach(function(b) {
      b.classList.toggle('active', (b.dataset.periodo || '') === _defPeriodo);
    });
    _fetchHistModal(gpon, sp, _defPeriodo);
  }

  function _openHistModalMes(gpon, ym) {
    _aHistGpon   = gpon;
    _aHistSp     = '';
    _aHistCidade = '';
    var parts   = ym.split('-');
    var y       = parseInt(parts[0], 10);
    var m       = parseInt(parts[1], 10);
    var lastDay = new Date(y, m, 0).getDate();
    var inicio  = ym + '-01 00:00:00';
    var fim     = ym + '-' + String(lastDay).padStart(2, '0') + ' 23:59:59';
    var mesLbl  = _MES_NOMES[m - 1] + '/' + y;
    document.getElementById('hist-modal-title').textContent = 'GPON — ' + gpon;
    document.getElementById('hist-modal-sub').textContent   = mesLbl + ' · carregando…';
    modalHist.style.display      = 'flex';
    document.body.style.overflow = 'hidden';
    document.querySelectorAll('#modal-historico .hist-period-btn').forEach(function(b) {
      b.classList.remove('active');
    });
    var extraQs = 'gpon=' + encodeURIComponent(gpon)
                + '&inicio=' + encodeURIComponent(inicio)
                + '&fim='    + encodeURIComponent(fim);
    _fetchHistModal('', '', '', extraQs, ' · ' + mesLbl);
  }

  function openHistModalCidade(cidade, count, mttr) {
    _aHistGpon   = '';
    _aHistSp     = '';
    _aHistCidade = cidade;
    var mttrStr = mttr != null
      ? mttr.toLocaleString('pt-BR', {minimumFractionDigits:1, maximumFractionDigits:1}) + 'h'
      : null;
    var sub = count.toLocaleString('pt-BR') + (count === 1 ? ' ocorrência' : ' ocorrências')
            + (mttrStr ? ' · MTTR: ' + mttrStr : '');
    document.getElementById('hist-modal-title').textContent = cidade;
    document.getElementById('hist-modal-sub').textContent   = sub + ' · carregando…';
    modalHist.style.display      = 'flex';
    document.body.style.overflow = 'hidden';
    var _savedHp = null;
    try { _savedHp = localStorage.getItem('gpon_hist_period'); } catch(e) {}
    var _defPeriodo = ['', '7d', '15d', '30d'].indexOf(_savedHp) !== -1 ? _savedHp : '30d';
    document.querySelectorAll('#modal-historico .hist-period-btn').forEach(function(b) {
      b.classList.toggle('active', (b.dataset.periodo || '') === _defPeriodo);
    });
    _fetchHistModal('', '', _defPeriodo, 'cidade=' + encodeURIComponent(cidade));
  }

  /* ── Modal Heatmap: ocorrências do dia ──────────────────────── */
  var dtHmHistorico  = null;
  var modalHmHist    = document.getElementById('modal-heatmap-historico');
  var hmHistLoading  = document.getElementById('hm-hist-loading');
  var hmHistTableW   = document.getElementById('hm-hist-table-wrap');

  function closeHmHistModal() {
    modalHmHist.style.display = 'none';
    document.body.style.overflow = '';
  }

  document.getElementById('hm-hist-modal-close').addEventListener('click', closeHmHistModal);
  modalHmHist.addEventListener('click', function(e) { if (e.target === modalHmHist) closeHmHistModal(); });
  document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && modalHmHist.style.display !== 'none') closeHmHistModal(); });

  function _fmtDateBr(iso) {
    if (!iso) return '';
    var p = iso.split('-');
    return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : iso;
  }

  function openHeatmapDayModal(gpon, sp, date, count) {
    var dateBr = _fmtDateBr(date);
    document.getElementById('hm-hist-modal-title').textContent = gpon + ' — ' + sp;
    document.getElementById('hm-hist-modal-sub').textContent   = dateBr + ' · carregando…';
    modalHmHist.style.display    = 'flex';
    document.body.style.overflow = 'hidden';
    hmHistLoading.style.display  = '';
    hmHistTableW.style.display   = 'none';

    // Inclui ocultar_improcedentes e outros filtros ativos (uf, baixa_causa),
    // mas não o período — a data específica já limita o escopo da consulta.
    var pqs = buildParams().split('&').filter(function(p) {
      return p && !p.startsWith('periodo=') && !p.startsWith('inicio=') && !p.startsWith('fim=')
               && !p.startsWith('gpon=') && !p.startsWith('sp=');
    }).join('&');
    var url = BASE_PATH + '/api/analise/historico?gpon=' + encodeURIComponent(gpon)
            + '&sp=' + encodeURIComponent(sp)
            + '&data=' + encodeURIComponent(date)
            + (pqs ? '&' + pqs : '');
    fetch(url)
      .then(function(r) { return r.json(); })
      .then(function(d) {
        hmHistLoading.style.display = 'none';
        hmHistTableW.style.display  = '';
        var rows  = d.rows || [];
        var label = rows.length + (rows.length === 1 ? ' ocorrência' : ' ocorrências') + ' em ' + dateBr;
        document.getElementById('hm-hist-modal-sub').textContent = label;
        if (dtHmHistorico) {
          dtHmHistorico.clear().rows.add(rows).draw();
        } else {
          dtHmHistorico = $('#tbl-hm-historico').DataTable({
            data: rows, pageLength: 20, order: [[3, 'desc']], language: dtLang,
            dom: 'Blfrtip',
            buttons: [{ extend: 'excel', text: '<i class="bi bi-file-earmark-excel"></i> Excel', className: 'btn btn-sm btn-success', title: 'Heatmap GPON — ' + gpon + ' ' + sp + ' ' + dateBr }],
            columns: [
              { data: 'oc',          render: function(v) { return '<span class="mono fw-700" style="font-size:11px">' + esc(v||'—') + '</span>'; } },
              { data: 'splitters',   render: function(v) {
                  if (!v) return '<span style="font-size:10px;color:#94a3b8">—</span>';
                  var parts = v.split(',').map(function(s){ return s.trim(); }).filter(Boolean);
                  if (parts.length <= 1) return '<span class="mono" style="font-size:10px">' + esc(v) + '</span>';
                  return '<span class="mono" style="font-size:10px;cursor:default" title="' + esc(parts.join(', ')) + '">' + esc(parts[0]) + '…</span>';
                }
              },
              { data: 'status',      render: function(v) { return statusHistBadge(v); } },
              { data: 'abertura',    render: function(v) { return '<span style="font-size:10px;white-space:nowrap">' + fmtDateTimePt(v) + '</span>'; } },
              { data: 'encerramento',render: function(v) { return '<span style="font-size:10px;white-space:nowrap">' + fmtDateTimePt(v) + '</span>'; } },
              { data: 'sla_status',  render: function(v) { return slaHistBadge(v); } },
              { data: 'cidade',      render: function(v) { return '<span style="font-size:10px;white-space:nowrap">' + esc(v||'—') + '</span>'; } },
              { data: 'empresa',     render: function(v) { return '<span style="font-size:10px">' + esc(v||'—') + '</span>'; } },
              { data: 'baixa_causa', render: function(v) { return '<span style="font-size:10px;max-width:160px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + esc(v||'') + '">' + esc(v||'—') + '</span>'; } },
              { data: 'baixa_reparo',render: function(v) { return '<span style="font-size:10px">' + esc(v||'—') + '</span>'; } },
              { data: 'aging',       render: function(v) { return '<span class="mono" style="font-size:10px">' + fmtAging(v) + '</span>'; } },
            ],
          });
        }
      })
      .catch(function(e) {
        hmHistLoading.innerHTML = '<i class="bi bi-exclamation-triangle" style="color:#dc2626"></i> Erro ao carregar dados.';
        console.error('heatmap hist error', e);
      });
  }

  function openHeatmapTotalModal(gpon, sp, total) {
    document.getElementById('hm-hist-modal-title').textContent = gpon + ' — ' + sp;
    document.getElementById('hm-hist-modal-sub').textContent   =
      total + (total === 1 ? ' ocorrência' : ' ocorrências') + ' · carregando…';
    modalHmHist.style.display    = 'flex';
    document.body.style.overflow = 'hidden';
    hmHistLoading.style.display  = '';
    hmHistTableW.style.display   = 'none';

    // Inclui filtros ativos (período, UF, causa, validos) excluindo gpon/sp do formulário
    var pqs = buildParams().split('&').filter(function(p) {
      return p && !p.startsWith('gpon=') && !p.startsWith('sp=');
    }).join('&');
    var url = BASE_PATH + '/api/analise/historico?gpon=' + encodeURIComponent(gpon)
            + '&sp=' + encodeURIComponent(sp)
            + (pqs ? '&' + pqs : '');

    fetch(url)
      .then(function(r) { return r.json(); })
      .then(function(d) {
        hmHistLoading.style.display = 'none';
        hmHistTableW.style.display  = '';
        var rows  = d.rows || [];
        document.getElementById('hm-hist-modal-sub').textContent =
          rows.length + (rows.length === 1 ? ' ocorrência' : ' ocorrências') + ' · total do período';
        if (dtHmHistorico) {
          dtHmHistorico.clear().rows.add(rows).draw();
        } else {
          dtHmHistorico = $('#tbl-hm-historico').DataTable({
            data: rows, pageLength: 20, order: [[3, 'desc']], language: dtLang,
            dom: 'Blfrtip',
            buttons: [{ extend: 'excel', text: '<i class="bi bi-file-earmark-excel"></i> Excel', className: 'btn btn-sm btn-success', title: 'Heatmap GPON — ' + gpon + ' ' + sp }],
            columns: [
              { data: 'oc',          render: function(v) { return '<span class="mono fw-700" style="font-size:11px">' + esc(v||'—') + '</span>'; } },
              { data: 'splitters',   render: function(v) {
                  if (!v) return '<span style="font-size:10px;color:#94a3b8">—</span>';
                  var parts = v.split(',').map(function(s){ return s.trim(); }).filter(Boolean);
                  if (parts.length <= 1) return '<span class="mono" style="font-size:10px">' + esc(v) + '</span>';
                  return '<span class="mono" style="font-size:10px;cursor:default" title="' + esc(parts.join(', ')) + '">' + esc(parts[0]) + '…</span>';
                }
              },
              { data: 'status',      render: function(v) { return statusHistBadge(v); } },
              { data: 'abertura',    render: function(v) { return '<span style="font-size:10px;white-space:nowrap">' + fmtDateTimePt(v) + '</span>'; } },
              { data: 'encerramento',render: function(v) { return '<span style="font-size:10px;white-space:nowrap">' + fmtDateTimePt(v) + '</span>'; } },
              { data: 'sla_status',  render: function(v) { return slaHistBadge(v); } },
              { data: 'cidade',      render: function(v) { return '<span style="font-size:10px;white-space:nowrap">' + esc(v||'—') + '</span>'; } },
              { data: 'empresa',     render: function(v) { return '<span style="font-size:10px">' + esc(v||'—') + '</span>'; } },
              { data: 'baixa_causa', render: function(v) { return '<span style="font-size:10px;max-width:160px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + esc(v||'') + '">' + esc(v||'—') + '</span>'; } },
              { data: 'baixa_reparo',render: function(v) { return '<span style="font-size:10px">' + esc(v||'—') + '</span>'; } },
              { data: 'aging',       render: function(v) { return '<span class="mono" style="font-size:10px">' + fmtAging(v) + '</span>'; } },
            ],
          });
        }
      })
      .catch(function(e) {
        hmHistLoading.innerHTML = '<i class="bi bi-exclamation-triangle" style="color:#dc2626"></i> Erro ao carregar dados.';
        console.error('heatmap total error', e);
      });
  }

  function openHeatmapPeriodModal(gpon, sp, periodo, periodoLabel) {
    document.getElementById('hm-hist-modal-title').textContent = gpon + ' — ' + sp;
    document.getElementById('hm-hist-modal-sub').textContent   = periodoLabel + ' · carregando…';
    modalHmHist.style.display    = 'flex';
    document.body.style.overflow = 'hidden';
    hmHistLoading.style.display  = '';
    hmHistTableW.style.display   = 'none';

    var pqs = buildParams().split('&').filter(function(p) {
      return p && !p.startsWith('gpon=') && !p.startsWith('sp=')
               && !p.startsWith('periodo=') && !p.startsWith('inicio=') && !p.startsWith('fim=');
    }).join('&');
    var url = BASE_PATH + '/api/analise/historico?gpon=' + encodeURIComponent(gpon)
            + '&sp=' + encodeURIComponent(sp)
            + '&periodo=' + encodeURIComponent(periodo)
            + (pqs ? '&' + pqs : '');

    fetch(url)
      .then(function(r) { return r.json(); })
      .then(function(d) {
        hmHistLoading.style.display = 'none';
        hmHistTableW.style.display  = '';
        var rows = d.rows || [];
        document.getElementById('hm-hist-modal-sub').textContent =
          periodoLabel + ' · ' + rows.length + (rows.length === 1 ? ' ocorrência' : ' ocorrências');
        if (dtHmHistorico) {
          dtHmHistorico.clear().rows.add(rows).draw();
        } else {
          dtHmHistorico = $('#tbl-hm-historico').DataTable({
            data: rows, pageLength: 20, order: [[3, 'desc']], language: dtLang,
            dom: 'Blfrtip',
            buttons: [{ extend: 'excel', text: '<i class="bi bi-file-earmark-excel"></i> Excel', className: 'btn btn-sm btn-success', title: 'Timeline — ' + gpon + ' ' + sp + ' ' + periodoLabel }],
            columns: [
              { data: 'oc',          render: function(v) { return '<span class="mono fw-700" style="font-size:11px">' + esc(v||'—') + '</span>'; } },
              { data: 'splitters',   render: function(v) {
                  if (!v) return '<span style="font-size:10px;color:#94a3b8">—</span>';
                  var parts = v.split(',').map(function(s){ return s.trim(); }).filter(Boolean);
                  if (parts.length <= 1) return '<span class="mono" style="font-size:10px">' + esc(v) + '</span>';
                  return '<span class="mono" style="font-size:10px;cursor:default" title="' + esc(parts.join(', ')) + '">' + esc(parts[0]) + '…</span>';
                }
              },
              { data: 'status',      render: function(v) { return statusHistBadge(v); } },
              { data: 'abertura',    render: function(v) { return '<span style="font-size:10px;white-space:nowrap">' + fmtDateTimePt(v) + '</span>'; } },
              { data: 'encerramento',render: function(v) { return '<span style="font-size:10px;white-space:nowrap">' + fmtDateTimePt(v) + '</span>'; } },
              { data: 'sla_status',  render: function(v) { return slaHistBadge(v); } },
              { data: 'cidade',      render: function(v) { return '<span style="font-size:10px;white-space:nowrap">' + esc(v||'—') + '</span>'; } },
              { data: 'empresa',     render: function(v) { return '<span style="font-size:10px">' + esc(v||'—') + '</span>'; } },
              { data: 'baixa_causa', render: function(v) { return '<span style="font-size:10px;max-width:160px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + esc(v||'') + '">' + esc(v||'—') + '</span>'; } },
              { data: 'baixa_reparo',render: function(v) { return '<span style="font-size:10px">' + esc(v||'—') + '</span>'; } },
              { data: 'aging',       render: function(v) { return '<span class="mono" style="font-size:10px">' + fmtAging(v) + '</span>'; } },
            ],
          });
        }
      })
      .catch(function(e) {
        hmHistLoading.innerHTML = '<i class="bi bi-exclamation-triangle" style="color:#dc2626"></i> Erro ao carregar dados.';
        console.error('heatmap period error', e);
      });
  }

  function openHeatmapCausaModal(causa, reparo) {
    var title = causa || reparo || '—';
    var subLabel = causa ? 'Baixa Causa' : 'Baixa Reparo';
    document.getElementById('hm-hist-modal-title').textContent = title;
    document.getElementById('hm-hist-modal-sub').textContent   = subLabel + ' · carregando…';
    modalHmHist.style.display    = 'flex';
    document.body.style.overflow = 'hidden';
    hmHistLoading.style.display  = '';
    hmHistTableW.style.display   = 'none';

    // Herda todos os filtros ativos exceto baixa_causa/baixa_reparo (que sobrepomos)
    var pqs = buildParams().split('&').filter(function(p) {
      return p && !p.startsWith('baixa_causa=') && !p.startsWith('baixa_reparo=');
    }).join('&');
    if (causa)  pqs = (pqs ? pqs + '&' : '') + 'baixa_causa='  + encodeURIComponent(causa);
    if (reparo) pqs = (pqs ? pqs + '&' : '') + 'baixa_reparo=' + encodeURIComponent(reparo);
    var url = BASE_PATH + '/api/analise/historico?' + pqs;

    fetch(url)
      .then(function(r) { return r.json(); })
      .then(function(d) {
        hmHistLoading.style.display = 'none';
        hmHistTableW.style.display  = '';
        var rows = d.rows || [];
        document.getElementById('hm-hist-modal-sub').textContent =
          subLabel + ' · ' + rows.length + (rows.length === 1 ? ' ocorrência' : ' ocorrências');
        if (dtHmHistorico) {
          dtHmHistorico.clear().rows.add(rows).draw();
        } else {
          dtHmHistorico = $('#tbl-hm-historico').DataTable({
            data: rows, pageLength: 20, order: [[3, 'desc']], language: dtLang,
            dom: 'Blfrtip',
            buttons: [{ extend: 'excel', text: '<i class="bi bi-file-earmark-excel"></i> Excel', className: 'btn btn-sm btn-success', title: 'Causas — ' + title }],
            columns: [
              { data: 'oc',          render: function(v) { return '<span class="mono fw-700" style="font-size:11px">' + esc(v||'—') + '</span>'; } },
              { data: 'splitters',   render: function(v) {
                  if (!v) return '<span style="font-size:10px;color:#94a3b8">—</span>';
                  var parts = v.split(',').map(function(s){ return s.trim(); }).filter(Boolean);
                  if (parts.length <= 1) return '<span class="mono" style="font-size:10px">' + esc(v) + '</span>';
                  return '<span class="mono" style="font-size:10px;cursor:default" title="' + esc(parts.join(', ')) + '">' + esc(parts[0]) + '…</span>';
                }
              },
              { data: 'status',      render: function(v) { return statusHistBadge(v); } },
              { data: 'abertura',    render: function(v) { return '<span style="font-size:10px;white-space:nowrap">' + fmtDateTimePt(v) + '</span>'; } },
              { data: 'encerramento',render: function(v) { return '<span style="font-size:10px;white-space:nowrap">' + fmtDateTimePt(v) + '</span>'; } },
              { data: 'sla_status',  render: function(v) { return slaHistBadge(v); } },
              { data: 'cidade',      render: function(v) { return '<span style="font-size:10px;white-space:nowrap">' + esc(v||'—') + '</span>'; } },
              { data: 'empresa',     render: function(v) { return '<span style="font-size:10px">' + esc(v||'—') + '</span>'; } },
              { data: 'baixa_causa', render: function(v) { return '<span style="font-size:10px;max-width:160px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + esc(v||'') + '">' + esc(v||'—') + '</span>'; } },
              { data: 'baixa_reparo',render: function(v) { return '<span style="font-size:10px">' + esc(v||'—') + '</span>'; } },
              { data: 'aging',       render: function(v) { return '<span class="mono" style="font-size:10px">' + fmtAging(v) + '</span>'; } },
            ],
          });
        }
      })
      .catch(function(e) {
        hmHistLoading.innerHTML = '<i class="bi bi-exclamation-triangle" style="color:#dc2626"></i> Erro ao carregar dados.';
        console.error('heatmap causa error', e);
      });
  }

  /* ── Polling: Última Atualização ──────────────────────────── */
  (function() {
    var el   = document.getElementById('topbar-ua-text');
    var wrap = document.getElementById('topbar-last-update');
    if (!el || !wrap) return;
    var _lastTs = 0;
    function pollUa() {
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
    setInterval(pollUa, 60000);
  })();

  /* ── Modal Analítico ─────────────────────────────────────── */
  var modalAna   = document.getElementById('modal-analitico');
  var anaLoading = document.getElementById('ana-loading');
  var anaContent = document.getElementById('ana-content');

  /* ── Resumo executivo / status dos botões ─────────────────── */
  var _resumoCache   = {};
  var _resumoPending = {};

  function _calcStatus(d) {
    if (!d || !d.tendencia) return { label: '—',          cls: 'nd',         icon: 'bi-graph-up-arrow'    };
    if (d.tendencia === 'reducao')
      return { label: 'Em Redução',  cls: 'melhorando', icon: 'bi-graph-down-arrow'  };
    if (d.tendencia === 'estavel')
      return { label: 'Estável',     cls: 'estavel',    icon: 'bi-dash-lg'            };
    return Math.abs(d.tendencia_pct) <= 50
      ? { label: 'Em Atenção', cls: 'atencao', icon: 'bi-graph-up-arrow' }
      : { label: 'Crítica',    cls: 'critica', icon: 'bi-graph-up-arrow' };
  }

  function _buildResumoBullets(d) {
    var b   = [];
    var med = parseFloat(d.media_mensal || d.media || 0);
    var n   = parseInt(d.n_meses || (d.meses && d.meses.length) || 0, 10);
    if (d.tendencia === 'reducao') {
      b.push('Ocorrências em <strong>queda de ' + Math.abs(d.tendencia_pct) + '%</strong> no período recente'
        + ((d.grupo_ant && d.grupo_ult) ? ' (' + esc(d.grupo_ult) + ' vs ' + esc(d.grupo_ant) + ').' : '.'));
    } else if (d.tendencia === 'crescimento') {
      b.push('Ocorrências em <strong>alta de ' + Math.abs(d.tendencia_pct) + '%</strong> no período recente'
        + ((d.grupo_ant && d.grupo_ult) ? ' (' + esc(d.grupo_ult) + ' vs ' + esc(d.grupo_ant) + ').' : '.'));
    } else {
      b.push('<strong>Padrão estável</strong> nas ocorrências no período analisado.');
    }
    if (d.pior_mes)   b.push('Maior volume: <strong>' + esc(d.pior_mes.label)   + '</strong> (' + d.pior_mes.total   + ' ocorrências).');
    if (d.melhor_mes) b.push('Menor volume: <strong>' + esc(d.melhor_mes.label) + '</strong> (' + d.melhor_mes.total + ' ocorrências).');
    b.push('Média mensal de <strong>' + Math.round(med) + ' ocorrências</strong>'
      + (n > 1 ? ' (base: ' + n + ' meses).' : '.'));
    if (d.tendencia === 'reducao')
      b.push('Tendência geral de <strong>redução</strong> das ocorrências.');
    else if (d.tendencia === 'crescimento')
      b.push('Tendência geral de <strong>crescimento</strong> — requer atenção.');
    else
      b.push('Série histórica <strong>estável</strong> no período analisado.');
    return b;
  }

  function _renderResumo(d) {
    var el = document.getElementById('ana-resumo');
    if (!el) return;
    var st = _calcStatus(d);
    el.innerHTML =
      '<div class="ana-resumo-header"><i class="bi bi-clipboard2-pulse"></i> Diagnóstico Operacional '
        + '<span class="ana-ev-badge ana-ev-' + st.cls + '">' + st.label + '</span>'
      + '</div>'
      + '<ul class="ana-resumo-list">'
        + _buildResumoBullets(d).map(function(b) { return '<li>' + b + '</li>'; }).join('')
      + '</ul>';
    el.style.display = '';
  }

  function _renderTendCell(d) {
    if (!d || !d.tendencia) return '<span class="gpon-td-nd">—</span>';
    var icon  = d.tendencia === 'reducao' ? '📉' : d.tendencia === 'crescimento' ? '📈' : '➡️';
    var cls   = d.tendencia === 'reducao' ? 'gpon-tend-down' : d.tendencia === 'crescimento' ? 'gpon-tend-up' : 'gpon-tend-stable';
    var label = d.tendencia === 'reducao' ? 'Queda' : d.tendencia === 'crescimento' ? 'Alta' : 'Estável';
    var pct   = d.tendencia !== 'estavel' ? ' ' + Math.abs(d.tendencia_pct) + '%' : '';
    return '<span class="gpon-tend ' + cls + '">' + icon + ' ' + label + pct + '</span>';
  }

  function _renderStatusCell(d, st) {
    if (!d || !st || st.cls === 'nd') return '<span class="gpon-td-nd">—</span>';
    var emoji = { melhorando: '🟢', estavel: '🟡', atencao: '🟠', critica: '🔴' }[st.cls] || '—';
    return '<span class="gpon-status-badge gpon-status-' + st.cls + '">' + emoji + ' ' + st.label + '</span>';
  }

  function _updateBtnEvolucao(gpon) {
    var d = _resumoCache[gpon];
    if (!d) return;
    var st  = _calcStatus(d);
    var tip = 'Histórico Analítico do ' + gpon + '\n'
            + 'Status: ' + st.label + '\n'
            + 'Tendência: ' + (d.tendencia !== 'estavel'
                ? (d.tendencia === 'reducao' ? 'Queda' : 'Alta') + ' de ' + Math.abs(d.tendencia_pct) + '%'
                : 'Estável') + '\n'
            + (d.pior_mes   ? 'Maior volume: ' + d.pior_mes.label   + ' (' + d.pior_mes.total   + ')\n' : '')
            + (d.melhor_mes ? 'Menor volume: ' + d.melhor_mes.label + ' (' + d.melhor_mes.total  + ')\n' : '')
            + 'Clique para abrir a análise completa.';
    $('#tbl-gpon .btn-evolucao[data-gpon="' + esc(gpon) + '"]')
      .attr('title', tip).attr('data-ev', st.cls)
      .html('<i class="bi ' + st.icon + '"></i>');
    $('#tbl-gpon .gpon-link-analitico[data-gpon="' + esc(gpon) + '"]').attr('title', tip);
    // Células de tendência e status
    var tendEl = document.querySelector('#tbl-gpon .gpon-tend-cell[data-gpon="' + gpon + '"]');
    var statEl = document.querySelector('#tbl-gpon .gpon-status-cell[data-gpon="' + gpon + '"]');
    if (tendEl) tendEl.innerHTML = _renderTendCell(d);
    if (statEl) statEl.innerHTML = _renderStatusCell(d, st);
    // Borda lateral por status operacional
    if (tendEl) {
      var row = tendEl.closest('tr');
      if (row) {
        row.classList.remove('gpon-row-atencao', 'gpon-row-critica');
        if (st.cls === 'atencao') row.classList.add('gpon-row-atencao');
        else if (st.cls === 'critica') row.classList.add('gpon-row-critica');
      }
    }
  }

  function _fetchResumo(gpon) {
    if (_resumoCache[gpon]) { _updateBtnEvolucao(gpon); return; }
    if (_resumoPending[gpon]) return;
    _resumoPending[gpon] = true;
    var qs = 'gpon=' + encodeURIComponent(gpon) + '&' + _buildAnaliticoParams();
    fetch(BASE_PATH + '/api/analise/resumo?' + qs)
      .then(function(r) { return r.json(); })
      .then(function(d) {
        delete _resumoPending[gpon];
        if (!d.error) { _resumoCache[gpon] = d; _updateBtnEvolucao(gpon); }
      })
      .catch(function() { delete _resumoPending[gpon]; });
  }

  function closeAnaliticoModal() {
    modalAna.style.display = 'none';
    document.body.style.overflow = '';
  }

  document.getElementById('ana-modal-close').addEventListener('click', closeAnaliticoModal);
  modalAna.addEventListener('click', function(e) { if (e.target === modalAna) closeAnaliticoModal(); });
  document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && modalAna.style.display !== 'none') closeAnaliticoModal(); });

  function _buildAnaliticoParams() {
    var parts = [];
    var chkImp = document.getElementById('chk-validos');
    var chkFib = document.getElementById('chk-ocultar-fibrasil');
    if (chkImp && chkImp.checked) parts.push('ocultar_improcedentes=1');
    if (chkFib && chkFib.checked) parts.push('ocultar_fibrasil=1');
    return parts.join('&');
  }

// ── Helpers de gráfico SVG ───────────────────────────────────
  function _linReg(values) {
    var n = values.length, sx = 0, sy = 0, sxy = 0, sx2 = 0;
    values.forEach(function(v, i) { sx += i; sy += v; sxy += i * v; sx2 += i * i; });
    var den = n * sx2 - sx * sx;
    if (!den) return { slope: 0, intercept: n ? sy / n : 0 };
    var m = (n * sxy - sx * sy) / den;
    return { slope: m, intercept: (sy - m * sx) / n };
  }

  function _svgTip(container, svgEl) {
    var tip = container.querySelector('.ana-svg-tip');
    if (!tip || !svgEl) return;
    svgEl.addEventListener('mousemove', function(e) {
      var hit = e.target;
      if (!hit.classList.contains('ana-hit')) hit = hit.closest ? hit.closest('.ana-hit') : null;
      if (!hit) { tip.style.display = 'none'; return; }
      var v = hit.getAttribute('data-v'), delta = hit.getAttribute('data-d'), l = hit.getAttribute('data-l'), unit = hit.getAttribute('data-u') || '';
      var dc = delta && delta.charAt(0) === '+' ? '#f87171' : delta && delta.charAt(0) === '-' ? '#6ee7b7' : '#94a3b8';
      var isMttr = unit === 'h';
      tip.innerHTML = '<span style="font-family:monospace;color:#c4b5fd;font-weight:700">' + esc(l) + '</span><br>'
        + (isMttr ? 'MTTR' : 'Ocorrências') + ': <strong>' + v + (isMttr ? '' : ' oc.') + '</strong><br>'
        + 'vs mês anterior: <span style="color:' + dc + '">' + (delta || '—') + (isMttr || !delta || delta === '—' ? '' : ' oc.') + '</span>';
      var r = container.getBoundingClientRect();
      var lx = e.clientX - r.left + 12;
      if (lx + 168 > r.width) lx -= 188;
      tip.style.left = lx + 'px';
      tip.style.top  = (e.clientY - r.top - 44) + 'px';
      tip.style.display = '';
    });
    svgEl.addEventListener('mouseleave', function() { tip.style.display = 'none'; });
  }

  function _buildBarSvg(container, meses, avg) {
    var n = meses.length;
    if (!n) { container.innerHTML = '<p style="color:#94a3b8;font-size:11px;text-align:center;padding:32px 0">Sem dados para o período</p>'; return; }
    var W = 640, H = 180, pt = 28, pr = 28, pb = 30, pl = 46;
    var pW = W - pl - pr, pH = H - pt - pb;
    var vals = meses.map(function(m) { return m.total || 0; });
    var maxV = Math.max.apply(null, vals) || 1;
    var nonZ = vals.filter(function(v) { return v > 0; });
    var minV = nonZ.length ? Math.min.apply(null, nonZ) : 0;
    var maxI = vals.indexOf(maxV);
    var minI = minV > 0 ? vals.indexOf(minV) : -1;
    var bGap = pW / n, bW = Math.max(8, Math.floor(bGap * 0.58));
    function bx(i)  { return pl + i * bGap + (bGap - bW) / 2; }
    function bh(v)  { return pH * v / maxV; }
    function by(v)  { return pt + pH - bh(v); }
    function mx(i)  { return bx(i) + bW / 2; }
    var lr = _linReg(vals);
    function ty(i)  { return pt + pH - pH * Math.max(0, Math.min(maxV, lr.slope * i + lr.intercept)) / maxV; }

    var s = [];
    s.push('<svg viewBox="0 0 ' + W + ' ' + H + '" style="width:100%;height:188px;display:block;overflow:visible" preserveAspectRatio="none" role="img" aria-label="Ocorrências por mês">');
    s.push('<title>Ocorrências por mês: ' + meses.map(function(m) { return m.label + ' ' + (m.total || 0) + ' ocorrências'; }).join('; ') + '</title>');

    // Título eixo Y (rotacionado)
    var yCx = (pt + pH / 2).toFixed(1);
    s.push('<text x="8" y="' + yCx + '" text-anchor="middle" fill="#c4b5fd" font-size="8" font-weight="600" font-family="Plus Jakarta Sans,sans-serif" transform="rotate(-90 8 ' + yCx + ')">Ocorrências</text>');

    // Grid
    [0.25, 0.5, 0.75, 1].forEach(function(r) {
      var gy = (pt + pH * (1 - r)).toFixed(1);
      s.push('<line x1="' + pl + '" y1="' + gy + '" x2="' + (W - pr) + '" y2="' + gy + '" stroke="#ede9fe" stroke-width="1"/>');
      s.push('<text x="' + (pl - 4) + '" y="' + (parseFloat(gy) + 3) + '" text-anchor="end" fill="#c4b5fd" font-size="8">' + Math.round(maxV * r) + '</text>');
    });

    // Média com rótulo dinâmico
    if (avg > 0 && avg <= maxV) {
      var avgy = (pt + pH - pH * avg / maxV).toFixed(1);
      s.push('<line x1="' + pl + '" y1="' + avgy + '" x2="' + (W - pr) + '" y2="' + avgy + '" stroke="#a78bfa" stroke-width="1" stroke-dasharray="5 3" opacity="0.8"/>');
      s.push('<text x="' + (W - pr - 2) + '" y="' + (parseFloat(avgy) + 3).toFixed(1) + '" fill="#7c3aed" font-size="8" font-weight="600" font-family="IBM Plex Mono,monospace" text-anchor="end" opacity="0.9">Média: ' + Math.round(avg) + ' oc.</text>');
    }

    // Tendência (regressão linear)
    s.push('<line x1="' + mx(0).toFixed(1) + '" y1="' + ty(0).toFixed(1) + '" x2="' + mx(n - 1).toFixed(1) + '" y2="' + ty(n - 1).toFixed(1) + '" stroke="#f59e0b" stroke-width="2" stroke-dasharray="7 3" opacity="0.85"/>');

    // Barras + hit areas
    meses.forEach(function(m, i) {
      var v = vals[i];
      var x = bx(i).toFixed(1), bH = bh(v).toFixed(1), bY = by(v).toFixed(1), cx = mx(i).toFixed(1);
      var isMax = (i === maxI && v > 0), isMin = (i === minI && v > 0);
      var fill  = isMax ? '#dc2626' : isMin ? '#16a34a' : '#7c3aed';
      var prev  = i > 0 ? vals[i - 1] : null;
      var dv    = prev !== null ? v - prev : null;
      var ds    = dv !== null ? (dv > 0 ? '+' + dv : String(dv)) : '—';
      var rx    = Math.min(4, bW / 5);
      // hit area com title acessível
      var hitTitle = esc(m.label) + ': ' + v + ' ocorrência' + (v !== 1 ? 's' : '') + (isMax ? ' — Máximo do período' : isMin ? ' — Mínimo do período' : '');
      s.push('<rect class="ana-hit" data-i="' + i + '" data-l="' + esc(m.label) + '" data-v="' + v + '" data-d="' + ds + '" x="' + bx(i).toFixed(1) + '" y="' + pt + '" width="' + bW.toFixed(1) + '" height="' + pH + '" fill="transparent" cursor="crosshair"><title>' + hitTitle + '</title></rect>');
      // barra (inicia com altura 0, animada via JS); stroke adicional em max/min
      var barStroke = (isMax || isMin) ? ' stroke="' + fill + '" stroke-width="1.5" stroke-opacity="0.45"' : '';
      s.push('<rect class="ana-bar-a" data-fy="' + bY + '" data-fh="' + bH + '" x="' + x + '" y="' + (pt + pH) + '" width="' + bW.toFixed(1) + '" height="0" fill="' + fill + '" rx="' + rx + '" opacity="0.88"' + barStroke + '/>');
      // rótulo de valor: dentro da barra se alta o suficiente, senão acima
      if (v > 0) {
        var fhNum = parseFloat(bH), bYNum = parseFloat(bY);
        var lblInside = fhNum >= 22;
        var lblY      = lblInside ? (bYNum + Math.min(fhNum - 5, 16)).toFixed(1) : Math.max(pt - 2, bYNum - 5).toFixed(1);
        var lblFill   = lblInside ? '#fff' : fill;
        var badge     = isMax ? ' ↑' : isMin ? ' ↓' : '';
        var valTxt    = lblInside ? (v + badge) : (v + ' oc.' + badge);
        s.push('<text class="ana-vlbl" x="' + cx + '" y="' + lblY + '" text-anchor="middle" fill="' + lblFill + '" font-size="9" font-weight="700" font-family="IBM Plex Mono,monospace" opacity="0">' + valTxt + '</text>');
      }
      // badge textual max/min entre barra e label (não depende de cor — acessibilidade)
      if (isMax && v > 0) s.push('<text x="' + cx + '" y="' + (pt + pH + 12) + '" text-anchor="middle" fill="#dc2626" font-size="7.5" font-weight="800" font-family="Plus Jakarta Sans,sans-serif" aria-hidden="true">▲ MÁX</text>');
      if (isMin && v > 0) s.push('<text x="' + cx + '" y="' + (pt + pH + 12) + '" text-anchor="middle" fill="#16a34a" font-size="7.5" font-weight="800" font-family="Plus Jakarta Sans,sans-serif" aria-hidden="true">▼ MÍN</text>');
      // label do mês
      s.push('<text x="' + cx + '" y="' + (H - 3) + '" text-anchor="middle" fill="#94a3b8" font-size="9" font-family="Plus Jakarta Sans,sans-serif">' + esc(m.label) + '</text>');
    });

    s.push('</svg>');
    s.push('<div class="ana-svg-tip"></div>');
    container.style.position = 'relative';
    container.innerHTML = s.join('');

    // Animação das barras
    var bars = container.querySelectorAll('.ana-bar-a');
    var base = pt + pH;
    bars.forEach(function(r, i) {
      var fh = parseFloat(r.getAttribute('data-fh'));
      function ease(t) { return 1 - Math.pow(1 - t, 3); }
      var t0 = null;
      setTimeout(function() {
        requestAnimationFrame(function frame(ts) {
          if (!t0) t0 = ts;
          var p = ease(Math.min((ts - t0) / 480, 1));
          r.setAttribute('y', (base - fh * p).toFixed(1));
          r.setAttribute('height', (fh * p).toFixed(1));
          if (p < 1) requestAnimationFrame(frame);
        });
      }, i * 48);
    });
    setTimeout(function() {
      container.querySelectorAll('.ana-vlbl').forEach(function(el) { el.setAttribute('opacity', '1'); });
    }, bars.length * 48 + 520);

    _svgTip(container, container.querySelector('svg'));
  }

  function _buildLineSvg(container, meses) {
    var n = meses.length;
    if (!n) { container.innerHTML = '<p style="color:#94a3b8;font-size:11px;text-align:center;padding:32px 0">Sem dados</p>'; return; }
    var W = 640, H = 180, pt = 28, pr = 28, pb = 30, pl = 32;
    var pW = W - pl - pr, pH = H - pt - pb;
    var vals = meses.map(function(m) { return m.mttr_avg != null ? m.mttr_avg : null; });
    var nonN = vals.filter(function(v) { return v !== null; });
    var maxV = nonN.length ? Math.max.apply(null, nonN) : 0; if (!maxV) maxV = 1;
    var minV = nonN.length ? Math.min.apply(null, nonN) : 0;
    var maxI = vals.indexOf(maxV), minI = vals.indexOf(minV);
    var bGap = pW / n;
    function px(i) { return pl + i * bGap + bGap / 2; }
    function py(v) { return pt + pH - pH * v / maxV; }

    var s = [];
    s.push('<svg viewBox="0 0 ' + W + ' ' + H + '" style="width:100%;height:188px;display:block;overflow:visible" preserveAspectRatio="none" role="img" aria-label="Gráfico de evolução do MTTR por mês">');
    s.push('<title>MTTR mensal: ' + meses.map(function(m) { return m.label + ' ' + (m.mttr_avg != null ? m.mttr_avg + 'h' : '—'); }).join('; ') + '</title>');

    // Grid
    [0.25, 0.5, 0.75, 1].forEach(function(r) {
      var gy = (pt + pH * (1 - r)).toFixed(1);
      s.push('<line x1="' + pl + '" y1="' + gy + '" x2="' + (W - pr) + '" y2="' + gy + '" stroke="#ede9fe" stroke-width="1"/>');
      s.push('<text x="' + (pl - 4) + '" y="' + (parseFloat(gy) + 3) + '" text-anchor="end" fill="#c4b5fd" font-size="8">' + Math.round(maxV * r) + 'h</text>');
    });

    // Área preenchida + linha
    var pts = [];
    vals.forEach(function(v, i) { if (v !== null) pts.push([px(i).toFixed(1), py(v).toFixed(1)]); });
    if (pts.length > 1) {
      var lineD = 'M ' + pts[0].join(',') + ' L ' + pts.slice(1).map(function(p) { return p.join(','); }).join(' L ');
      var areaD = lineD + ' L ' + pts[pts.length - 1][0] + ',' + (pt + pH) + ' L ' + pts[0][0] + ',' + (pt + pH) + ' Z';
      s.push('<path d="' + areaD + '" fill="#7c3aed" opacity="0.07"/>');
      s.push('<path class="ana-line-path" d="' + lineD + '" fill="none" stroke="#7c3aed" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>');
    }

    // Pontos + hit areas + labels
    meses.forEach(function(m, i) {
      var v  = vals[i];
      var cx = px(i).toFixed(1);
      var prev  = i > 0 ? vals[i - 1] : null;
      var dv    = (v !== null && prev !== null) ? (v - prev).toFixed(1) : null;
      var ds    = dv !== null ? (parseFloat(dv) > 0 ? '+' + dv + 'h' : dv + 'h') : '—';
      var isMaxL = i === maxI, isMinL = i === minI;
      var hitLbl = esc(m.label) + ': MTTR ' + (v !== null ? v + 'h' : '—') + (isMaxL ? ' — Pior MTTR do período' : isMinL ? ' — Melhor MTTR do período' : '');
      s.push('<rect class="ana-hit" data-i="' + i + '" data-l="' + esc(m.label) + '" data-v="' + (v !== null ? v + 'h' : '—') + '" data-d="' + ds + '" data-u="h" x="' + (px(i) - bGap / 2).toFixed(1) + '" y="' + pt + '" width="' + bGap.toFixed(1) + '" height="' + pH + '" fill="transparent" cursor="crosshair"><title>' + hitLbl + '</title></rect>');
      if (v !== null) {
        var isMax = isMaxL, isMin = isMinL;
        var fill  = isMax ? '#dc2626' : isMin ? '#16a34a' : '#7c3aed';
        var cy    = py(v).toFixed(1);
        var r     = isMax || isMin ? 6 : 4;
        s.push('<circle class="ana-dot" cx="' + cx + '" cy="' + cy + '" r="' + r + '" fill="' + fill + '" stroke="#fff" stroke-width="1.5" opacity="0"/>');
        s.push('<text class="ana-vlbl" x="' + cx + '" y="' + (parseFloat(cy) - 9).toFixed(1) + '" text-anchor="middle" fill="' + fill + '" font-size="9" font-weight="700" font-family="IBM Plex Mono,monospace" opacity="0">' + v + 'h</text>');
      }
      // badge textual max/min para MTTR (não depende de cor)
      if (isMaxL && v !== null) s.push('<text x="' + cx + '" y="' + (pt + pH + 12) + '" text-anchor="middle" fill="#dc2626" font-size="7.5" font-weight="800" font-family="Plus Jakarta Sans,sans-serif" aria-hidden="true">▲ PIOR</text>');
      if (isMinL && v !== null) s.push('<text x="' + cx + '" y="' + (pt + pH + 12) + '" text-anchor="middle" fill="#16a34a" font-size="7.5" font-weight="800" font-family="Plus Jakarta Sans,sans-serif" aria-hidden="true">▼ MELHOR</text>');
      s.push('<text x="' + cx + '" y="' + (H - 3) + '" text-anchor="middle" fill="#94a3b8" font-size="9" font-family="Plus Jakarta Sans,sans-serif">' + esc(m.label) + '</text>');
    });

    s.push('</svg>');
    s.push('<div class="ana-svg-tip"></div>');
    container.style.position = 'relative';
    container.innerHTML = s.join('');

    // Animação da linha (stroke-dashoffset)
    var lp = container.querySelector('.ana-line-path');
    if (lp) {
      requestAnimationFrame(function() {
        try {
          var len = lp.getTotalLength();
          lp.style.strokeDasharray = len;
          lp.style.strokeDashoffset = len;
          requestAnimationFrame(function() {
            lp.style.transition = 'stroke-dashoffset 1s ease';
            lp.style.strokeDashoffset = '0';
          });
        } catch(e) {}
      });
    }
    setTimeout(function() {
      container.querySelectorAll('.ana-dot, .ana-vlbl').forEach(function(el) { el.setAttribute('opacity', '1'); });
    }, 1100);

    _svgTip(container, container.querySelector('svg'));
  }

  function _renderAnalitico(d) {
    var meses = d.meses || [];
    var n     = meses.length;
    var media = d.media_mensal || 0;
    var ano   = new Date().getFullYear();
    // label vem como "Jan/26" — extraímos só o nome do mês para montar o período limpo
    var _mes  = function(lbl) { return lbl ? lbl.split('/')[0] : lbl; };
    var periodoLabel = n === 0 ? String(ano)
      : n === 1 ? (_mes(meses[0].label) + '/' + ano)
      : (_mes(meses[0].label) + '–' + _mes(meses[n - 1].label) + '/' + ano);

    // Títulos dinâmicos
    var evolTitle = document.getElementById('ana-evolucao-title');
    if (evolTitle) evolTitle.innerHTML = '<i class="bi bi-bar-chart-fill"></i> Ocorrências por Mês — ' + periodoLabel;
    var mttrTitle = document.getElementById('ana-mttr-title');
    if (mttrTitle) mttrTitle.innerHTML = '<i class="bi bi-stopwatch"></i> TMR Médio por Mês — ' + periodoLabel;
    var anaTitle = document.getElementById('ana-modal-title');
    if (anaTitle) anaTitle.innerHTML = '<i class="bi bi-graph-up-arrow"></i> Histórico Analítico do GPON';

    // Sub com tendência
    var tendIcon  = d.tendencia === 'crescimento' ? '📈' : d.tendencia === 'reducao' ? '📉' : '➡️';
    var tendColor = d.tendencia === 'crescimento' ? '#dc2626' : d.tendencia === 'reducao' ? '#16a34a' : '#d97706';
    var tendLabel = d.tendencia === 'crescimento' ? 'Alta' : d.tendencia === 'reducao' ? 'Queda' : 'Estável';
    var tendPct   = d.tendencia !== 'estavel' ? ' de ' + Math.abs(d.tendencia_pct) + '%' : '';
    var tendTip   = (d.grupo_ult && d.grupo_ant)
      ? 'Soma de ' + d.grupo_ult + ' comparada com ' + d.grupo_ant + '. Variação de ' + Math.abs(d.tendencia_pct) + '%.'
      : 'Comparação entre a soma do período mais recente e do período imediatamente anterior.';
    var st        = _calcStatus(d);
    var tendLine1 = tendIcon + ' ' + tendLabel + tendPct + ' nas ocorrências';
    var tendLine2 = (d.grupo_ult && d.grupo_ant) ? d.grupo_ult + ' comparado a ' + d.grupo_ant : '';
    document.getElementById('ana-modal-sub').innerHTML =
      '<span class="mono">' + esc(d.gpon) + '</span>'
      + ' · <span class="ana-ev-badge ana-ev-' + st.cls + '">' + st.label + '</span>'
      + ' · ' + periodoLabel + '<br>'
      + '<span style="font-weight:700;color:' + tendColor + '">' + tendLine1 + '</span>'
      + (tendLine2 ? ' <span style="color:#94a3b8;font-weight:400;font-size:10px">' + tendLine2 + '</span>' : '')
      + ' <i class="bi bi-info-circle" title="' + esc(tendTip) + '" style="cursor:help;color:#a78bfa;font-size:11px;vertical-align:middle"></i>';

    // KPIs
    document.getElementById('ana-kpis').innerHTML =
      '<div class="ana-kpi">'
        + '<div class="ana-kpi-val">' + (d.total_12m || 0) + '</div>'
        + '<div class="ana-kpi-label">Total no Ano</div>'
        + '<div class="ana-kpi-sub">ocorrências</div>'
      + '</div>'
      + '<div class="ana-kpi" title="Média calculada: ' + (d.total_12m || 0) + ' ocorrências ÷ ' + n + ' meses do ano corrente.">'
        + '<div class="ana-kpi-val">' + media.toLocaleString('pt-BR', {minimumFractionDigits:1, maximumFractionDigits:1}) + '</div>'
        + '<div class="ana-kpi-label">Média Mensal</div>'
        + '<div class="ana-kpi-sub">' + (d.total_12m || 0) + ' ÷ ' + n + ' mes' + (n !== 1 ? 'es' : '') + ' <i class="bi bi-info-circle" style="font-size:9px;vertical-align:middle;opacity:.7"></i></div>'
      + '</div>'
      + '<div class="ana-kpi" style="border-color:#dcfce7" title="Mês com a menor quantidade de ocorrências do período analisado.">'
        + '<div class="ana-kpi-val" style="color:#16a34a">' + (d.melhor_mes ? d.melhor_mes.total : '—') + '</div>'
        + '<div class="ana-kpi-label">Menor Volume ↓</div>'
        + '<div class="ana-kpi-sub">' + (d.melhor_mes ? esc(d.melhor_mes.label) : 'sem dados') + '</div>'
        + (d.melhor_mes ? '<div class="ana-kpi-sub" style="opacity:.72">Menor volume do período</div>' : '')
      + '</div>'
      + '<div class="ana-kpi" style="border-color:#fee2e2" title="Mês com a maior quantidade de ocorrências do período analisado.">'
        + '<div class="ana-kpi-val" style="color:#dc2626">' + (d.pior_mes ? d.pior_mes.total : '—') + '</div>'
        + '<div class="ana-kpi-label">Maior Volume ↑</div>'
        + '<div class="ana-kpi-sub">' + (d.pior_mes ? esc(d.pior_mes.label) : 'sem dados') + '</div>'
        + (d.pior_mes ? '<div class="ana-kpi-sub" style="opacity:.72">Maior volume do período</div>' : '')
      + '</div>';

    // Gráficos SVG
    _buildBarSvg(document.getElementById('ana-chart'), meses, media);
    _buildLineSvg(document.getElementById('ana-mttr'), meses);

    // Causas
    var causas   = d.causas || [];
    var maxCausa = causas.length ? causas[0].total : 1;
    var causasHtml = causas.length ? '' : '<span style="color:#94a3b8;font-size:11px">Sem dados</span>';
    causas.forEach(function(c) {
      var pct = Math.round(c.total / maxCausa * 100);
      causasHtml += '<div class="ana-causa-row">'
        + '<div class="ana-causa-name" title="' + esc(c.causa) + '">' + esc(c.causa || '—') + '</div>'
        + '<div class="ana-causa-bar-wrap"><div class="ana-causa-bar" style="width:' + pct + '%"></div></div>'
        + '<div class="ana-causa-val">' + c.total + ' <span style="color:#c4b5fd">(' + c.pct + '%)</span></div>'
        + '</div>';
    });
    document.getElementById('ana-causas').innerHTML = causasHtml;

    // Splitters
    var splitters = d.splitters || [];
    var maxSp     = splitters.length ? splitters[0].total : 1;
    var spHtml    = splitters.length ? '' : '<span style="color:#94a3b8;font-size:11px">Sem dados</span>';
    splitters.forEach(function(s) {
      var pct = Math.round(s.total / maxSp * 100);
      spHtml += '<div class="ana-causa-row">'
        + '<div class="ana-causa-name" style="min-width:72px;max-width:80px;font-family:monospace;font-weight:600;color:#5b21b6">' + esc(s.sp) + '</div>'
        + '<div class="ana-causa-bar-wrap"><div class="ana-causa-bar" style="width:' + pct + '%;background:#a78bfa"></div></div>'
        + '<div class="ana-causa-val">' + s.total + '</div>'
        + '</div>';
    });
    document.getElementById('ana-splitters').innerHTML = spHtml;

    _renderResumo(d);
  }

  function openAnaliticoModal(gpon) {
    document.getElementById('ana-modal-title').innerHTML = '<i class="bi bi-graph-up-arrow"></i> Histórico Analítico da OLT';
    document.getElementById('ana-modal-sub').textContent = gpon + ' · carregando…';
    modalAna.style.display      = 'flex';
    document.body.style.overflow = 'hidden';
    anaLoading.style.display = '';
    anaContent.style.display = 'none';
    anaLoading.innerHTML = '<i class="bi bi-hourglass-split"></i> Carregando…';
    var qs  = 'gpon=' + encodeURIComponent(gpon) + '&' + _buildAnaliticoParams();
    var url = BASE_PATH + '/api/analise/analitico?' + qs;
    fetch(url)
      .then(function(r) { return r.json(); })
      .then(function(d) {
        anaLoading.style.display = 'none';
        if (!d.error) { _resumoCache[gpon] = d; _updateBtnEvolucao(gpon); }
        _renderAnalitico(d);
        anaContent.style.display = '';
      })
      .catch(function(e) {
        anaLoading.innerHTML = '<i class="bi bi-exclamation-triangle" style="color:#dc2626"></i> Erro ao carregar dados.';
        console.error('analitico error', e);
      });
  }

  $(document).on('click', '#tbl-gpon .btn-evolucao', function(e) {
    e.stopPropagation();
    openAnaliticoModal($(this).data('gpon'));
  });

  $(document).on('click', '#tbl-gpon .gpon-link-analitico', function(e) {
    e.stopPropagation();
    openAnaliticoModal($(this).data('gpon'));
  });

  // Click no valor de Ocorrências abre modal-historico
  $(document).on('click', '#tbl-gpon .ocorrencia-link', function(e) {
    e.stopPropagation();
    if ($(this).data('tipo-historico') === 'mes') {
      _openHistModalMes(String($(this).data('gpon')), String($(this).data('ym')));
      return;
    }
    if (!dtGpon) return;
    var row = dtGpon.row($(this).closest('tr')).data();
    if (!row) return;
    var dias   = _periodDays || 1;
    var ocsdia = (row.count / dias).toLocaleString('pt-BR', {minimumFractionDigits:1, maximumFractionDigits:1});
    var tmrStr = row.tmr_horas != null ? ' • TMR ' + fmtAging(Math.round(row.tmr_horas)) : '';
    var sub    = row.count + ' ocorrências • ' + ocsdia + ' OCs/dia' + tmrStr;
    openHistModal(row.gpon, '', row.count, sub);
  });

  // Click handler na tab GPON+Splitter
  $(document).on('click', '#tbl-comb tbody tr', function() {
    if (!dtComb) return;
    var row = dtComb.row(this).data();
    if (!row) return;
    openHistModal(row.gpon, row.sp, row.count);
  });

  // Click handler na tab Última Reincidência
  $(document).on('click', '#tbl-last tbody tr', function() {
    if (!dtLast) return;
    var row = dtLast.row(this).data();
    if (!row) return;
    openHistModal(row.gpon, row.sp, row.count);
  });

  // Click handler no card MTTR por Cidade
  $(document).on('click', '.mttr-item', function() {
    var cidade = this.dataset.cidade;
    var count  = parseInt(this.dataset.count, 10) || 0;
    var mttr   = this.dataset.mttr !== '' ? parseFloat(this.dataset.mttr) : null;
    if (!cidade) return;
    openHistModalCidade(cidade, count, mttr);
  });

})();
