/* analise-modais.js — modal historico, modal heatmap, click handlers */
(function(A) {
  'use strict';

  // ── Estado dos modais ────────────────────────────────────────────
  A._aHistGpon   = '';
  A._aHistSp     = '';
  A._aHistCidade = '';

  var dtHistorico = null;
  var modalHist   = document.getElementById('modal-historico');
  var histLoading = document.getElementById('hist-loading');
  var histTableW  = document.getElementById('hist-table-wrap');

  // ── Definição canônica de colunas para todos os modais de histórico ─
  // Lê row._ctx_sp dinamicamente para destacar o splitter primário por linha.
  function _histCols() {
    return [
      { data: 'oc', render: function(v) {
          return '<span class="mono fw-700" style="font-size:11px">' + A.esc(v||'—') + '</span>';
        }
      },
      { data: 'splitters', width: '90px', render: function(v, _, row) {
          if (!v) return '<span style="color:#94a3b8;font-size:10px">—</span>';
          var canonical = [];
          String(v).split(',').forEach(function(token) {
            token = token.trim();
            if (!token) return;
            var m = token.match(/SP\d+/i);
            var csp = m ? m[0].toUpperCase() : token;
            if (canonical.indexOf(csp) === -1) canonical.push(csp);
          });
          if (!canonical.length) return '<span style="color:#94a3b8;font-size:10px">—</span>';
          var primary = (row._ctx_sp || '').toUpperCase();
          var ordered = primary
            ? [primary].concat(canonical.filter(function(csp) { return csp !== primary; }))
            : canonical;
          var cnt = ordered.length;
          var clr = cnt === 1 ? { bg: '#f1f5f9', fg: '#475569' } : cnt <= 5 ? { bg: '#fef3c7', fg: '#b45309' } : { bg: '#fee2e2', fg: '#dc2626' };
          var tooltip = ordered.map(function(s) { return A.esc(s); }).join('&#10;');
          var extra = cnt > 1 ? ' <span style="color:#94a3b8;font-size:9px">(+' + (cnt - 1) + ')</span>' : '';
          return '<span title="' + tooltip + '" style="white-space:nowrap;cursor:default"><span class="badge" style="background:' + clr.bg + ';color:' + clr.fg + ';font-size:10px;font-weight:600;font-family:monospace">' + A.esc(ordered[0]) + '</span>' + extra + '</span>';
        }
      },
      { data: 'status',       render: function(v) { return A.statusHistBadge(v); } },
      { data: 'abertura',     render: function(v) { return '<span style="font-size:10px;white-space:nowrap">' + A.fmtDateTimePt(v) + '</span>'; } },
      { data: 'encerramento', render: function(v) { return '<span style="font-size:10px;white-space:nowrap">' + A.fmtDateTimePt(v) + '</span>'; } },
      { data: 'sla_status',   render: function(v) { return A.slaHistBadge(v); } },
      { data: 'cidade',       render: function(v) { return '<span style="font-size:10px;white-space:nowrap">' + A.esc(v||'—') + '</span>'; } },
      { data: 'empresa',      render: function(v) { return '<span style="font-size:10px">' + A.esc(v||'—') + '</span>'; } },
      { data: 'baixa_causa',  render: function(v) { return '<span style="font-size:10px;max-width:160px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + A.esc(v||'') + '">' + A.esc(v||'—') + '</span>'; } },
      { data: 'baixa_reparo', render: function(v) { return '<span style="font-size:10px">' + A.esc(v||'—') + '</span>'; } },
      { data: 'aging',        render: function(v) { return '<span class="mono" style="font-size:10px">' + A.fmtAging(v) + '</span>'; } },
    ];
  }

  // ── Modal Histórico: close ───────────────────────────────────────
  A.closeHistModal = function() {
    modalHist.style.display = 'none';
    document.body.style.overflow = '';
  };

  document.getElementById('hist-modal-close').addEventListener('click', A.closeHistModal);
  modalHist.addEventListener('click', function(e) { if (e.target === modalHist) A.closeHistModal(); });
  document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && modalHist.style.display !== 'none') A.closeHistModal(); });

  // Botão de período dentro do modal historico
  document.addEventListener('click', function(e) {
    var btn = e.target.closest('#modal-historico .hist-period-btn');
    if (!btn || (!A._aHistGpon && !A._aHistCidade)) return;
    document.querySelectorAll('#modal-historico .hist-period-btn').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    var _p = btn.dataset.periodo || '';
    try { localStorage.setItem('gpon_hist_period', _p); } catch(e) {}
    if (A._aHistCidade) {
      A._fetchHistModal('', '', _p, 'cidade=' + encodeURIComponent(A._aHistCidade));
    } else {
      A._fetchHistModal(A._aHistGpon, A._aHistSp, _p);
    }
  });

  // ── _buildHistParams ─────────────────────────────────────────────
  function _buildHistParams(periodo) {
    var parts = A.buildParams().split('&').filter(function(p) {
      return p && !p.startsWith('periodo=') && !p.startsWith('inicio=') && !p.startsWith('fim=');
    });
    if (periodo) parts.push('periodo=' + encodeURIComponent(periodo));
    return parts.join('&');
  }

  // ── _fetchHistModal ──────────────────────────────────────────────
  var _histAbort = null;

  A._fetchHistModal = function(gpon, sp, periodo, extraQs, pLabelOverride) {
    var pqs    = _buildHistParams(periodo);
    var pLabel = pLabelOverride != null ? pLabelOverride : (periodo ? ' · últimos ' + periodo : ' · histórico completo');
    histLoading.style.display = '';
    histTableW.style.display  = 'none';
    var qs    = extraQs
      ? extraQs + (pqs ? '&' + pqs : '')
      : 'gpon=' + encodeURIComponent(gpon) + '&sp=' + encodeURIComponent(sp) + (pqs ? '&' + pqs : '');
    var url   = BASE_PATH + '/api/analise/historico?' + qs;
    var ctxSp = extraQs ? '' : (sp || '').toUpperCase();

    if (_histAbort) { _histAbort.abort(); }
    _histAbort = new AbortController();

    fetch(url, { signal: _histAbort.signal })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        _histAbort = null;
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
            data: rows, pageLength: 20, order: [[3, 'desc']], language: A.dtLang,
            dom: 'Blfrtip',
            buttons: [{ extend: 'excel', text: '<i class="bi bi-file-earmark-excel"></i> Excel', className: 'btn btn-sm btn-success', title: 'Histórico GPON' }],
            columns: _histCols(),
          });
        }
      })
      .catch(function(e) {
        if (e.name === 'AbortError') return;
        histLoading.innerHTML = '<i class="bi bi-exclamation-triangle" style="color:#dc2626"></i> Erro ao carregar dados.';
        console.error('historico error', e);
      });
  };

  // ── openHistModal ────────────────────────────────────────────────
  A.openHistModal = function(gpon, sp, count, subExtra) {
    A._aHistGpon   = gpon;
    A._aHistSp     = sp;
    A._aHistCidade = '';
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
    A._fetchHistModal(gpon, sp, _defPeriodo);
  };

  // ── _openHistModalMes ────────────────────────────────────────────
  A._openHistModalMes = function(gpon, ym) {
    A._aHistGpon   = gpon;
    A._aHistSp     = '';
    A._aHistCidade = '';
    var parts   = ym.split('-');
    var y       = parseInt(parts[0], 10);
    var m       = parseInt(parts[1], 10);
    var lastDay = new Date(y, m, 0).getDate();
    var inicio  = ym + '-01 00:00:00';
    var fim     = ym + '-' + String(lastDay).padStart(2, '0') + ' 23:59:59';
    var mesLbl  = A._MES_NOMES[m - 1] + '/' + y;
    document.getElementById('hist-modal-title').textContent = 'GPON — ' + gpon;
    document.getElementById('hist-modal-sub').textContent   = mesLbl + ' · carregando…';
    modalHist.style.display      = 'flex';
    document.body.style.overflow = 'hidden';
    document.querySelectorAll('#modal-historico .hist-period-btn').forEach(function(b) { b.classList.remove('active'); });
    var extraQs = 'gpon=' + encodeURIComponent(gpon)
                + '&inicio=' + encodeURIComponent(inicio)
                + '&fim='    + encodeURIComponent(fim);
    A._fetchHistModal('', '', '', extraQs, ' · ' + mesLbl);
  };

  // ── openHistModalCidade ──────────────────────────────────────────
  A.openHistModalCidade = function(cidade, count, mttr) {
    A._aHistGpon   = '';
    A._aHistSp     = '';
    A._aHistCidade = cidade;
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
    A._fetchHistModal('', '', _defPeriodo, 'cidade=' + encodeURIComponent(cidade));
  };

  // ── Modal Heatmap ────────────────────────────────────────────────
  var dtHmHistorico = null;
  var modalHmHist   = document.getElementById('modal-heatmap-historico');
  var hmHistLoading = document.getElementById('hm-hist-loading');
  var hmHistTableW  = document.getElementById('hm-hist-table-wrap');

  A.closeHmHistModal = function() {
    modalHmHist.style.display = 'none';
    document.body.style.overflow = '';
  };

  document.getElementById('hm-hist-modal-close').addEventListener('click', A.closeHmHistModal);
  modalHmHist.addEventListener('click', function(e) { if (e.target === modalHmHist) A.closeHmHistModal(); });
  document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && modalHmHist.style.display !== 'none') A.closeHmHistModal(); });

  function _fmtDateBr(iso) {
    if (!iso) return '';
    var p = iso.split('-');
    return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : iso;
  }

  function _initHmTable(rows, excelTitle) {
    if (dtHmHistorico) {
      dtHmHistorico.clear().rows.add(rows).draw();
    } else {
      dtHmHistorico = $('#tbl-hm-historico').DataTable({
        data: rows, pageLength: 20, order: [[3, 'desc']], language: A.dtLang,
        dom: 'Blfrtip',
        buttons: [{ extend: 'excel', text: '<i class="bi bi-file-earmark-excel"></i> Excel', className: 'btn btn-sm btn-success', title: excelTitle }],
        columns: _histCols(),
      });
    }
  }

  // ── Helper de fetch compartilhado para os 4 modais de heatmap ────
  var _hmAbort = null;

  function _openHmFetch(title, sub0, url, excelTitle, ctxSp, subFn) {
    document.getElementById('hm-hist-modal-title').textContent = title;
    document.getElementById('hm-hist-modal-sub').textContent   = sub0;
    modalHmHist.style.display    = 'flex';
    document.body.style.overflow = 'hidden';
    hmHistLoading.style.display  = '';
    hmHistTableW.style.display   = 'none';

    if (_hmAbort) { _hmAbort.abort(); }
    _hmAbort = new AbortController();

    fetch(url, { signal: _hmAbort.signal })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        _hmAbort = null;
        hmHistLoading.style.display = 'none';
        hmHistTableW.style.display  = '';
        var rows = d.rows || [];
        if (ctxSp) { rows.forEach(function(r) { r._ctx_sp = ctxSp; }); }
        document.getElementById('hm-hist-modal-sub').textContent = subFn(rows);
        _initHmTable(rows, excelTitle);
      })
      .catch(function(e) {
        if (e.name === 'AbortError') return;
        hmHistLoading.innerHTML = '<i class="bi bi-exclamation-triangle" style="color:#dc2626"></i> Erro ao carregar dados.';
        console.error('heatmap error', e);
      });
  }

  A.openHeatmapDayModal = function(gpon, sp, date, count) {
    var dateBr = _fmtDateBr(date);
    var pqs = A.buildParams().split('&').filter(function(p) {
      return p && !p.startsWith('periodo=') && !p.startsWith('inicio=') && !p.startsWith('fim=')
               && !p.startsWith('gpon=') && !p.startsWith('sp=');
    }).join('&');
    _openHmFetch(
      gpon + ' — ' + sp,
      dateBr + ' · carregando…',
      BASE_PATH + '/api/analise/historico?gpon=' + encodeURIComponent(gpon) + '&sp=' + encodeURIComponent(sp) + '&data=' + encodeURIComponent(date) + (pqs ? '&' + pqs : ''),
      'Heatmap GPON — ' + gpon + ' ' + sp + ' ' + dateBr,
      sp,
      function(rows) { return rows.length + (rows.length === 1 ? ' ocorrência' : ' ocorrências') + ' em ' + dateBr; }
    );
  };

  A.openHeatmapTotalModal = function(gpon, sp, total) {
    var pqs = A.buildParams().split('&').filter(function(p) {
      return p && !p.startsWith('gpon=') && !p.startsWith('sp=');
    }).join('&');
    _openHmFetch(
      gpon + ' — ' + sp,
      total + (total === 1 ? ' ocorrência' : ' ocorrências') + ' · carregando…',
      BASE_PATH + '/api/analise/historico?gpon=' + encodeURIComponent(gpon) + '&sp=' + encodeURIComponent(sp) + (pqs ? '&' + pqs : ''),
      'Heatmap GPON — ' + gpon + ' ' + sp,
      sp,
      function(rows) { return rows.length + (rows.length === 1 ? ' ocorrência' : ' ocorrências') + ' · total do período'; }
    );
  };

  A.openHeatmapPeriodModal = function(gpon, sp, periodo, periodoLabel) {
    var pqs = A.buildParams().split('&').filter(function(p) {
      return p && !p.startsWith('gpon=') && !p.startsWith('sp=')
               && !p.startsWith('periodo=') && !p.startsWith('inicio=') && !p.startsWith('fim=');
    }).join('&');
    _openHmFetch(
      gpon + ' — ' + sp,
      periodoLabel + ' · carregando…',
      BASE_PATH + '/api/analise/historico?gpon=' + encodeURIComponent(gpon) + '&sp=' + encodeURIComponent(sp) + '&periodo=' + encodeURIComponent(periodo) + (pqs ? '&' + pqs : ''),
      'Timeline — ' + gpon + ' ' + sp + ' ' + periodoLabel,
      sp,
      function(rows) { return periodoLabel + ' · ' + rows.length + (rows.length === 1 ? ' ocorrência' : ' ocorrências'); }
    );
  };

  A.openHeatmapCausaModal = function(causa, reparo) {
    var title    = causa || reparo || '—';
    var subLabel = causa ? 'Baixa Causa' : 'Baixa Reparo';
    var pqs = A.buildParams().split('&').filter(function(p) {
      return p && !p.startsWith('baixa_causa=') && !p.startsWith('baixa_reparo=');
    }).join('&');
    if (causa)  pqs = (pqs ? pqs + '&' : '') + 'baixa_causa='  + encodeURIComponent(causa);
    if (reparo) pqs = (pqs ? pqs + '&' : '') + 'baixa_reparo=' + encodeURIComponent(reparo);
    _openHmFetch(
      title,
      subLabel + ' · carregando…',
      BASE_PATH + '/api/analise/historico?' + pqs,
      'Causas — ' + title,
      '',
      function(rows) { return subLabel + ' · ' + rows.length + (rows.length === 1 ? ' ocorrência' : ' ocorrências'); }
    );
  };

  // ── Click no valor de Ocorrências abre modal-historico ────────────
  $(document).on('click', '#tbl-gpon .ocorrencia-link', function(e) {
    e.stopPropagation();
    if ($(this).data('tipo-historico') === 'mes') {
      A._openHistModalMes(String($(this).data('gpon')), String($(this).data('ym')));
      return;
    }
    if (!A.dtGpon) return;
    var row = A.dtGpon.row($(this).closest('tr')).data();
    if (!row) return;
    var dias   = A._periodDays || 1;
    var ocsdia = (row.count / dias).toLocaleString('pt-BR', {minimumFractionDigits:1, maximumFractionDigits:1});
    var tmrStr = row.tmr_horas != null ? ' • TMR ' + A.fmtAging(Math.round(row.tmr_horas)) : '';
    var sub    = row.count + ' ocorrências • ' + ocsdia + ' OCs/dia' + tmrStr;
    A.openHistModal(row.gpon, '', row.count, sub);
  });

})(window.AnaliseApp = window.AnaliseApp || {});
