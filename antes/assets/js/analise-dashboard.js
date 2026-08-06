/* analise-dashboard.js — renderHeatmap, renderTimeline, renderCausas, renderMttr */
(function(A) {
  'use strict';

  // ── Heatmap ─────────────────────────────────────────────────────
  A.renderHeatmap = function(h) {
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
      html += '<td class="heatmap-gpon-label">' + A.esc(combo.gpon) + ' - ' + A.esc(combo.sp) + '</td>';
      h.dates.forEach(function(d) {
        var v   = combo.days[d] || 0;
        var cls = 'hm-' + dayCrit(v);
        var tip = v
          ? A.esc(combo.gpon) + ' - ' + A.esc(combo.sp) + ' • ' + v + (v === 1 ? ' ocorrência' : ' ocorrências') + ' — clique para ver detalhes'
          : A.esc(combo.gpon) + ' - ' + A.esc(combo.sp);
        if (v > 0) {
          html += '<td class="' + cls + ' hm-clickable" title="' + tip + '" data-gpon="' + A.esc(combo.gpon) + '" data-sp="' + A.esc(combo.sp) + '" data-date="' + A.esc(d) + '" data-count="' + v + '">' + v + '</td>';
        } else {
          html += '<td class="' + cls + '" title="' + tip + '">·</td>';
        }
      });
      if (combo.total > 0) {
        html += '<td class="hm-total hm-total-clickable"'
              + ' title="Ver todas as ' + combo.total + ' ocorrência' + (combo.total === 1 ? '' : 's') + ' de ' + A.esc(combo.gpon) + ' — ' + A.esc(combo.sp) + '"'
              + ' data-gpon="' + A.esc(combo.gpon) + '"'
              + ' data-sp="'   + A.esc(combo.sp)   + '"'
              + ' data-total="' + combo.total + '">' + combo.total + '</td>';
      } else {
        html += '<td class="hm-total">·</td>';
      }
      html += '</tr>';
    });

    html += '</tbody></table></div>';
    el.innerHTML = html;
  };

  document.getElementById('heatmap-wrap').addEventListener('click', function(e) {
    var td = e.target.closest('.hm-clickable');
    if (td) { A.openHeatmapDayModal(td.dataset.gpon, td.dataset.sp, td.dataset.date, parseInt(td.dataset.count, 10) || 0); return; }
    var ttd = e.target.closest('.hm-total-clickable');
    if (ttd) { A.openHeatmapTotalModal(ttd.dataset.gpon, ttd.dataset.sp, parseInt(ttd.dataset.total, 10) || 0); }
  });

  // ── Timeline ─────────────────────────────────────────────────────
  var _tlPeriodoLabels = { hoje: 'Hoje', '7d': 'Últimos 7 dias', '15d': 'Últimos 15 dias', '30d': 'Últimos 30 dias' };

  document.getElementById('timeline-wrap').addEventListener('click', function(e) {
    var val = e.target.closest('.tl-period-val.tl-clickable');
    if (!val) return;
    A.openHeatmapPeriodModal(val.dataset.gpon, val.dataset.sp, val.dataset.periodo, _tlPeriodoLabels[val.dataset.periodo] || val.dataset.periodo);
  });

  A.renderTimeline = function(data) {
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
      var barColor = A.CRIT_COLOR[item.crit] || '#7c3aed';
      var pct      = maxTotal > 0 ? Math.round(item.total / maxTotal * 100) : 0;
      html += '<div class="' + cls + '">';
      html += '<div class="tl-combo">';
      html += '<span class="tl-gpon">' + A.esc(item.gpon) + '</span>';
      html += '<span class="badge-reinc ' + spCls + ' tl-sp">' + A.esc(item.sp) + '</span>';
      html += '</div>';
      var _g = A.esc(item.gpon), _s = A.esc(item.sp);
      var _tlv = function(periodo, cls) { return ' class="tl-period-val tl-clickable' + cls + '" data-gpon="' + _g + '" data-sp="' + _s + '" data-periodo="' + periodo + '"'; };
      html += '<div class="tl-periods">';
      html += '<div class="tl-period"><span class="tl-period-label">Hoje</span><span' + _tlv('hoje', '') + '>'  + item.hoje   + '</span></div>';
      html += '<div class="tl-period"><span class="tl-period-label">7d</span><span'   + _tlv('7d',   '') + '>'  + item['7d']  + '</span></div>';
      html += '<div class="tl-period"><span class="tl-period-label">15d</span><span'  + _tlv('15d',  '') + '>'  + item['15d'] + '</span></div>';
      html += '<div class="tl-period"><span class="tl-period-label">30d</span><span'  + _tlv('30d', ' hl') + '>' + item['30d'] + '</span></div>';
      html += '</div>';
      html += '<div class="tl-bar-wrap"><div class="tl-bar-fill" style="width:' + pct + '%;background:' + barColor + '"></div></div>';
      html += '</div>';
    });
    html += '</div>';
    el.innerHTML = html;
  };

  // ── Causas ───────────────────────────────────────────────────────
  document.getElementById('causas-wrap').addEventListener('click', function(e) {
    var cnt = e.target.closest('.causa-bar-link');
    if (cnt) A.openHeatmapCausaModal(cnt.dataset.causa, null);
  });

  A.renderCausas = function(data) {
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
      html += '<div class="causa-name" title="' + A.esc(item.causa) + '">' + A.esc(item.causa) + '</div>';
      html += '<div class="causa-bar-wrap">';
      html += '<div class="causa-bar causa-bar-link" data-causa="' + A.esc(item.causa) + '"><div class="causa-fill" style="width:' + pct + '%"></div></div>';
      html += '<span class="causa-count">' + item.count + ' OC' + (item.count !== 1 ? 's' : '') + '</span>';
      html += '</div>';
      if (item.top_reparo) {
        html += '<div class="causa-reparo"><span class="causa-reparo-label">Reparo: </span>' + A.esc(item.top_reparo) + '</div>';
      }
      html += '</div>';
    });
    html += '</div>';
    el.innerHTML = html;
  };

  // ── MTTR ─────────────────────────────────────────────────────────
  A.renderMttr = function(data) {
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
      var mttrStr  = item.mttr != null ? item.mttr.toLocaleString('pt-BR',{minimumFractionDigits:1,maximumFractionDigits:1}) + 'h' : null;
      var mttrData = item.mttr != null ? item.mttr : '';
      html += '<div class="mttr-item" style="cursor:pointer" title="Clique para ver ocorrências de ' + A.esc(item.cidade) + '"'
            + ' data-cidade="' + A.esc(item.cidade) + '" data-count="' + item.count + '" data-mttr="' + mttrData + '">';
      html += '<span class="mttr-cidade">' + A.esc(item.cidade) + '</span>';
      html += '<span class="mttr-count">'  + item.count.toLocaleString('pt-BR') + ' ocorrências</span>';
      html += mttrStr
        ? '<span class="mttr-val">' + mttrStr + '</span>'
        : '<span class="mttr-no-data">Sem encerramento</span>';
      html += '</div>';
    });
    html += '</div>';
    el.innerHTML = html;
  };

  // Click no card MTTR por Cidade
  $(document).on('click', '.mttr-item', function() {
    var cidade = this.dataset.cidade;
    var count  = parseInt(this.dataset.count, 10) || 0;
    var mttr   = this.dataset.mttr !== '' ? parseFloat(this.dataset.mttr) : null;
    if (!cidade) return;
    A.openHistModalCidade(cidade, count, mttr);
  });

})(window.AnaliseApp = window.AnaliseApp || {});
