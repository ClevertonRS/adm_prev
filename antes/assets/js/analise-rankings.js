/* analise-rankings.js — initGponTable, initCombTable, initLastTable, tab listeners */
(function(A) {
  'use strict';

  // ── Estado das DataTables ────────────────────────────────────────
  A.dtGpon        = null;
  A.dtComb        = null;
  A.dtLast        = null;
  A.maxComb       = 1;
  A._combOrigData = [];
  A.activeTab     = 'gpon';

  // ── Labels dinâmicos ─────────────────────────────────────────────
  A._OCORR_LABELS = {
    '': 'OC / Histórico', '24h': 'OC / 24h', 'hoje': 'OC / Hoje',
    'ontem': 'OC / Ontem', '7d': 'OC / 7 Dias', '15d': 'OC / 15 Dias',
    '30d': 'OC / 30 Dias', 'custom': 'OC / Período',
  };
  A._MES_NOMES = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                  'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

  A._updateGponTableHeaders = function() {
    var p     = document.getElementById('sel-periodo') ? document.getElementById('sel-periodo').value : '30d';
    var ocLbl = A._OCORR_LABELS[p] || 'OC / Período';
    var th    = document.getElementById('th-ocorr');
    if (th) th.textContent = ocLbl;
    if (A.dtGpon) $(A.dtGpon.column(2).header()).text(ocLbl);
  };

  A.getPeriodDays = function() {
    var selP = document.getElementById('sel-periodo');
    var p    = selP ? selP.value : '30d';
    if (p === '7d')  return 7;
    if (p === '15d') return 15;
    if (p === '30d') return 30;
    if (p === '24h' || p === 'hoje' || p === 'ontem') return 1;
    if (p === 'custom') {
      var d1 = document.getElementById('inp-inicio') ? document.getElementById('inp-inicio').value : '';
      var d2 = document.getElementById('inp-fim')    ? document.getElementById('inp-fim').value    : '';
      if (d1 && d2) {
        var diff = (new Date(d2) - new Date(d1)) / 86400000 + 1;
        return diff > 0 ? Math.round(diff) : 1;
      }
    }
    return 30;
  };

  // ── initGponTable ────────────────────────────────────────────────
  A.initGponTable = function(data) {
    A._updateGponTableHeaders();
    if (A.dtGpon) { A.dtGpon.clear().rows.add(data).draw(); return; }
    A.dtGpon = $('#tbl-gpon').DataTable({
      data: data, pageLength: 25, order: [[2, 'desc']], language: A.dtLang,
      dom: '<"gpon-dt-top"l>rt<"bottom"ip>',
      columns: [
        { data: 'rank',      render: function(d) { return A.rankNum(d); }, width: '50px', orderable: false },
        { data: 'gpon',      render: function(d) {
            return '<span class="mono fw-700 gpon-link-analitico" data-gpon="' + A.esc(d) + '" style="font-size:13px">' + A.esc(d) + '</span>';
          }
        },
        { data: 'count',     render: function(d, _, row) {
            return '<span class="mono fw-700 ocorrencia-link" data-gpon="' + A.esc(row.gpon) + '" data-tipo-historico="periodo" title="Clique para abrir o histórico de ocorrências">' + d + '</span>';
          }
        },
        { data: 'count',     render: function(d) {
            var dias  = A._periodDays || 1;
            var media = (d / dias).toLocaleString('pt-BR', {minimumFractionDigits:1, maximumFractionDigits:1});
            return '<span class="mono" style="color:#0369a1">' + media + '/dia</span>';
          }
        },
        { data: 'count',     render: function(d) { return A.fmtGponCritBadge(d); } },
        { data: null,        render: function(_, __, row) { return A.fmtTmrBadge(row.tmr_horas, row.tmr_count || 0); } },
        { data: 'gpon',      orderable: false, width: '72px',
          render: function(d) {
            return '<span class="gpon-tend-cell" data-gpon="' + A.esc(d) + '"><span class="gpon-td-skel"></span></span>';
          }
        },
      ],
    });

    // Campo de busca com contador
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
        var n = A.dtGpon.rows({ search: 'applied' }).count();
        $count.text(n + (n !== 1 ? ' resultados' : ' resultado')).css('display', 'inline');
      } else {
        $count.css('display', 'none');
      }
    }

    function _loadVisibleGpon() {
      setTimeout(function() {
        if (!A.dtGpon) return;
        _updateSearchCount();
        A.dtGpon.rows({ page: 'current' }).every(function() {
          var g = this.data() && this.data().gpon;
          if (g) A._fetchResumo(g);
        });
      }, 80);
    }
    A.dtGpon.on('draw.dt', _loadVisibleGpon);
    _loadVisibleGpon();

    $srch.on('input', function() {
      var v = this.value;
      A.dtGpon.search(v).draw();
      $clr.css('display', v ? 'inline-flex' : 'none');
    });
    $clr.on('click', function() {
      $srch.val('');
      A.dtGpon.search('').draw();
      $(this).hide();
      $count.hide();
    });
  };

  // ── _wireSearch (helper para outras tabelas) ──────────────────────
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

  // ── initCombTable ────────────────────────────────────────────────
  A.initCombTable = function(data) {
    A._combOrigData = data.map(function(r) { return Object.assign({}, r, { _rel: 0 }); });
    A.maxComb = data.length ? data[0].count : 1;
    if (A.dtComb) { A.dtComb.clear().rows.add(A._combOrigData).draw(); return; }
    A.dtComb = $('#tbl-comb').DataTable({
      data: A._combOrigData, pageLength: 25, order: [[3, 'desc']], language: A.dtLang,
      dom: '<"gpon-dt-top"l>rt<"bottom"ip>',
      columns: [
        { data: 'gpon',  render: function(d) { return '<span class="mono" style="font-size:12px">' + A.esc(d) + '</span>'; } },
        { data: 'sp',    render: function(d) { return d ? '<span class="badge" style="background:#f1f5f9;color:#475569;font-size:10px;font-weight:600;font-family:monospace">' + A.esc(d) + '</span>' : '—'; } },
        { data: 'count', render: function(d) { return '<span class="mono fw-700" style="font-size:14px;color:#7c3aed">' + d + '</span>'; } },
        { data: 'pct',   render: function(d) { return '<span class="mono">' + d + '%</span>'; } },
        { data: 'count', render: function(d) { return A.fmtCombCritBadge(d); } },
        { data: null,    render: function(_, __, row) {
            var pct = A.maxComb > 0 ? Math.round(row.count / A.maxComb * 100) : 0;
            return '<div class="bar-wrap" style="min-width:120px"><div class="bar-fill" style="width:' + pct + '%;background:' + A._combBarColor(row.count) + '"></div><span class="bar-label">' + pct + '%</span></div>';
          }
        },
        { data: '_rel', visible: false, searchable: false },
      ],
      rowCallback: function(row) { $(row).addClass('hist-tbl-clickable').attr('title', 'Clique para ver o histórico de OCs'); },
    });

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
        A.dtComb.order([[3, 'desc']]).clear().rows.add(A._combOrigData).draw();
        $c.hide(); $n.hide();
        return;
      }
      var vl = v.toLowerCase();
      function _fScore(f) { var s=(f||'').toLowerCase(); if(s===vl)return 0; if(s.indexOf(vl)===0)return 1; if(s.indexOf(vl)!==-1)return 2; return 99; }
      function _nScore(f) { var num=(f||'').toLowerCase().replace(/^\D+/,''); if(!num)return 99; if(num===vl)return 0; if(num.indexOf(vl)===0)return 1; if(num.indexOf(vl)!==-1)return 2; return 99; }
      var scored = [];
      A._combOrigData.forEach(function(row) {
        var score = Math.min(_fScore(row.sp), _nScore(row.sp), _fScore(row.gpon));
        if (score >= 99) return;
        scored.push({ row: row, score: score });
      });
      scored.sort(function(a, b) {
        return a.score !== b.score ? a.score - b.score : (b.row.count - a.row.count);
      });
      var finalRows = scored.map(function(s, i) { return Object.assign({}, s.row, { _rel: i }); });
      A.dtComb.order([[6, 'asc']]).clear().rows.add(finalRows).draw();
      var n = scored.length;
      $n.text(n + (n !== 1 ? ' resultados' : ' resultado')).css('display', 'inline');
      $c.css('display', 'inline-flex');
    }
    $s.on('input', function() { _combSearch(this.value); });
    $c.on('click', function() { $s.val(''); _combSearch(''); });
  };

  // ── initLastTable ────────────────────────────────────────────────
  A.initLastTable = function(data) {
    var maxC = data.length ? Math.max.apply(null, data.map(function(r) { return r.count; })) : 1;
    function fmtDt(iso) {
      if (!iso) return '—';
      var m = iso.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
      return m ? m[3] + '/' + m[2] + ' ' + m[4] + ':' + m[5] : iso;
    }
    if (A.dtLast) { A.dtLast.clear().rows.add(data).draw(); return; }
    A.dtLast = $('#tbl-last').DataTable({
      data: data, pageLength: 25, order: [[3, 'desc']], language: A.dtLang,
      dom: '<"gpon-dt-top"l>rt<"bottom"ip>',
      columns: [
        { data: 'gpon',  render: function(d) { return '<span class="mono" style="font-size:12px">' + A.esc(d) + '</span>'; } },
        { data: 'sp',    render: function(d) { return d ? '<span class="badge" style="background:#f1f5f9;color:#475569;font-size:10px;font-weight:600;font-family:monospace">' + A.esc(d) + '</span>' : '—'; } },
        { data: 'oc',    render: function(d) { return d ? '<span class="mono" style="font-size:11px;color:#7c3aed">' + A.esc(d) + '</span>' : '—'; } },
        { data: 'date',  render: function(d) { return '<span class="mono" style="font-size:12px">' + fmtDt(d) + '</span>'; } },
        { data: 'count', render: function(d) { return '<span class="mono fw-700" style="font-size:14px;color:#7c3aed">' + d + '</span>'; } },
        { data: null,    render: function(_, __, row) { return A.fmtLastCritBadge(row.count, row.date); } },
      ],
      rowCallback: function(row) { $(row).addClass('hist-tbl-clickable').attr('title', 'Clique para ver o histórico de OCs'); },
    });
    _wireSearch('tbl-last', A.dtLast, 'Buscar GPON...');
  };

  // ── Tabs ──────────────────────────────────────────────────────────
  document.querySelectorAll('.tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
      document.querySelectorAll('.tab-content').forEach(function(t) { t.style.display = 'none'; });
      this.classList.add('active');
      A.activeTab = this.dataset.tab;
      var el = document.getElementById('tab-' + A.activeTab);
      if (el) el.style.display = '';
      var d = window._analiseData;
      if (d && A.activeTab === 'comb') A.initCombTable(d.comb_ranking || []);
      if (d && A.activeTab === 'last') A.initLastTable(d.last_seen    || []);
      setTimeout(function() {
        var id = '#tbl-' + A.activeTab;
        if ($.fn.DataTable.isDataTable(id)) $(id).DataTable().columns.adjust();
      }, 50);
    });
  });

  // ── Click handlers das tabelas ────────────────────────────────────
  $(document).on('click', '#tbl-comb tbody tr', function() {
    if (!A.dtComb) return;
    var row = A.dtComb.row(this).data();
    if (!row) return;
    A.openHistModal(row.gpon, row.sp, row.count);
  });

  $(document).on('click', '#tbl-last tbody tr', function() {
    if (!A.dtLast) return;
    var row = A.dtLast.row(this).data();
    if (!row) return;
    A.openHistModal(row.gpon, row.sp, row.count);
  });

})(window.AnaliseApp = window.AnaliseApp || {});
