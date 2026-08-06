/* analise-core.js — constantes, helpers, loadAnalise, KPIs, polling */
(function(A) {
  'use strict';

  // ── Constantes ──────────────────────────────────────────────────
  A.CRIT_COLOR  = { alta: '#dc2626', media: '#d97706', baixa: '#16a34a' };
  A.CRIT_LABEL  = { alta: 'Alta', media: 'Média', baixa: 'Baixa' };
  A.CRIT_CLS    = { alta: 'crit-alta', media: 'crit-media', baixa: 'crit-baixa' };

  A.dtLang = {
    search: 'Buscar:', lengthMenu: 'Exibir _MENU_',
    info: 'Mostrando _START_–_END_ de _TOTAL_',
    paginate: { previous: '‹', next: '›' },
    zeroRecords: 'Nenhum registro encontrado', emptyTable: 'Nenhum dado disponível',
  };

  A._CRIT_LEVELS = [
    { label: 'Baixa', bg: '#dcfce7', color: '#15803d', border: '#86efac', barColor: '#16a34a' },
    { label: 'Média', bg: '#fef3c7', color: '#b45309', border: '#fde68a', barColor: '#d97706' },
    { label: 'Alta',  bg: '#fee2e2', color: '#b91c1c', border: '#fca5a5', barColor: '#dc2626' },
  ];

  // ── Helpers privados ────────────────────────────────────────────
  function _fixedCritLevel(count) {
    if (count >= 5) return 2;
    if (count >= 2) return 1;
    return 0;
  }
  function _critBadgeHtml(level, title) {
    var l = A._CRIT_LEVELS[level];
    var t = title ? ' title="' + title + '"' : '';
    return '<span class="badge-status crit-badge"' + t + ' style="background:' + l.bg + ';color:' + l.color + ';border-color:' + l.border + ';font-size:10px">' + l.label + '</span>';
  }

  // ── Helpers públicos (usados em múltiplos módulos) ──────────────
  A.esc = function(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  };
  A.rankNum = function(n) {
    return '<span class="rank-num">' + (n < 10 ? '0' + n : n) + '</span>';
  };
  A.fmtGponCritBadge = function(count) {
    return _critBadgeHtml(_fixedCritLevel(count));
  };
  A.fmtCombCritBadge = function(count) {
    var tip = count + ' ocorrência' + (count !== 1 ? 's' : '');
    return _critBadgeHtml(_fixedCritLevel(count), tip);
  };
  A.fmtLastCritBadge = function(count, dateStr) {
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
  };
  A._combBarColor = function(count) {
    return A._CRIT_LEVELS[_fixedCritLevel(count)].barColor;
  };
  A.fmtTmrBadge = function(horas, cnt) {
    if (horas == null) return '<span style="color:#94a3b8;font-size:11px">—</span>';
    var h = Math.round(horas);
    var color, bg;
    if (h <= 4)      { color = '#15803d'; bg = '#dcfce7'; }
    else if (h <= 8) { color = '#b45309'; bg = '#fef9c3'; }
    else             { color = '#dc2626'; bg = '#fee2e2'; }
    var fmt = h < 24 ? h + 'h' : (Math.floor(h/24) + 'd ' + (h%24 > 0 ? h%24 + 'h' : ''));
    var title = 'Tempo médio baseado em: ' + cnt + ' OC' + (cnt !== 1 ? 's' : '') + ' encerrada' + (cnt !== 1 ? 's' : '');
    return '<span class="mono" style="font-size:12px;color:' + color + ';background:' + bg +
           ';padding:2px 7px;border-radius:4px;font-weight:700" title="' + title + '">' + fmt + '</span>';
  };
  A.fmtDateTimePt = function(iso) {
    if (!iso) return '—';
    var m = iso.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    return m ? m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] + ':' + m[5] : iso;
  };
  A.fmtAging = function(h) {
    if (h == null) return '—';
    h = parseInt(h, 10);
    if (h < 24) return h + 'h';
    var d = Math.floor(h / 24), r = h % 24;
    return r > 0 ? d + 'd ' + r + 'h' : d + 'd';
  };
  A.slaHistBadge = function(status) {
    if (!status) return '<span style="color:#94a3b8;font-size:10px">—</span>';
    var labels = { ok: 'No Prazo', atencao: 'Atenção', violado: 'Fora do Prazo' };
    var colors = { ok: '#16a34a', atencao: '#d97706', violado: '#dc2626' };
    var bg     = { ok: '#dcfce7', atencao: '#fef9c3', violado: '#fee2e2' };
    return '<span style="font-size:9px;font-weight:700;padding:2px 7px;border-radius:10px;background:' + bg[status] + ';color:' + colors[status] + '">' + labels[status] + '</span>';
  };
  A.statusHistBadge = function(st) {
    if (!st) return '—';
    var aberto = /aberto|open|ativo/i.test(st);
    var bg = aberto ? '#eff6ff' : '#f0fdf4';
    var cl = aberto ? '#1d4ed8' : '#15803d';
    return '<span style="font-size:9px;font-weight:700;padding:2px 7px;border-radius:10px;background:' + bg + ';color:' + cl + '">' + A.esc(st) + '</span>';
  };

  // ── Estado compartilhado ────────────────────────────────────────
  A._analiseAbort = null;
  A._periodDays   = 30;

  // ── loadAnalise ─────────────────────────────────────────────────
  A.loadAnalise = function() {
    A.updatePeriodoBadge();
    var params = A.buildParams();
    var url    = BASE_PATH + '/api/analise' + (params ? '?' + params : '');

    if (A._analiseAbort) { A._analiseAbort.abort(); }
    A._analiseAbort = new AbortController();

    fetch(url, { signal: A._analiseAbort.signal })
      .then(function(r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function(d) {
        if (d && d.error) { console.error('Analise API error:', d.message); return; }
        window._analiseData = d;
        var t = d.totals || {};

        document.getElementById('kpi-analisados').textContent    = (t.analisados       || 0).toLocaleString('pt-BR');
        document.getElementById('kpi-analisados-sub').textContent =
          (t.gpon_uniq || 0).toLocaleString('pt-BR') + ' OLTs • ' + (t.sp_uniq || 0).toLocaleString('pt-BR') + ' SPs';
        document.getElementById('kpi-reincidentes').textContent  = (t.comb_reincidentes || 0).toLocaleString('pt-BR');

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
        document.getElementById('kpi-taxa-reinc').textContent = topComb ? topComb.sp  + ' • ' + topComb.count + 'x' : '—';
        document.getElementById('kpi-top-comb').textContent   = topComb ? topComb.gpon : '—';
        document.getElementById('kpi-indice-reinc').textContent = t.indice_reincidencia != null
          ? t.indice_reincidencia.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}) + '%'
          : '—';
        document.getElementById('kpi-indice-sub').textContent =
          (t.comb_reincidentes || 0).toLocaleString('pt-BR') + ' combinações • ' +
          (t.oc_reincidentes   || 0).toLocaleString('pt-BR') + ' ocorrências';

        document.getElementById('kpi-tmr-val').textContent = t.tmr != null ? A.fmtAging(t.tmr) : '—';
        document.getElementById('kpi-tmr-sub').textContent = t.tmr_count > 0
          ? (t.tmr_count).toLocaleString('pt-BR') + ' OC' + (t.tmr_count !== 1 ? 's' : '') + ' encerradas no período'
          : 'sem OCs encerradas no período';

        document.getElementById('cnt-gpon').textContent = (d.gpon_ranking || []).length;
        document.getElementById('cnt-comb').textContent = (d.comb_ranking || []).length;
        document.getElementById('cnt-last').textContent = (d.last_seen    || []).length;

        A.renderHeatmap(d.heatmap   || null);
        A.renderTimeline(d.timeline || []);
        A.renderCausas(d.causas     || []);
        A.renderMttr(d.mttr         || null);

        A._periodDays  = A.getPeriodDays();
        A._resumoCache = {}; A._resumoPending = {};
        (d.gpon_ranking || []).forEach(function(g) {
          if (g.tendencia) {
            A._resumoCache[g.gpon] = {
              gpon: g.gpon, tendencia: g.tendencia, tendencia_pct: g.tendencia_pct,
              total: g.total_anual, media: g.media_anual, n_meses: g.n_meses,
              melhor_mes: g.melhor_mes, pior_mes: g.pior_mes,
              grupo_ant: g.grupo_ant, grupo_ult: g.grupo_ult,
            };
          }
        });
        A.initGponTable(d.gpon_ranking || []);
        if (A.activeTab === 'comb') A.initCombTable(d.comb_ranking || []);
        if (A.activeTab === 'last') A.initLastTable(d.last_seen    || []);
      })
      .catch(function(e) {
        if (e.name !== 'AbortError') console.error('Erro ao carregar análise:', e);
      });
  };

  document.addEventListener('DOMContentLoaded', A.loadAnalise);
  document.getElementById('btn-reload-page')?.addEventListener('click', function() { location.reload(); });

  // ── Polling: Última Atualização ─────────────────────────────────
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

})(window.AnaliseApp = window.AnaliseApp || {});
