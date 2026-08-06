/* assets/js/shared/analise-heatmap.js
 * Renderização do Heatmap (Mapa Interativo de Ocorrências). Extraído de
 * A.renderHeatmap em assets/js/analise-dashboard.js para ser reaproveitado
 * tanto em /analise (showAcao=false, comportamento idêntico ao atual)
 * quanto no painel "Análise Preventiva" de /preventivo (showAcao=true).
 *
* No contexto Preventivo (showAcao + groupGpon + listMode):
 *  - a coluna "Ação" vem logo após a coluna fixa do GPON (sticky, sempre
 *    visível sem scroll horizontal — antes ficava no fim, após os dias);
 *  - as linhas são agrupadas por GPON (cabeçalho de grupo + splitters),
 *    eliminando o nome do GPON repetido em cada linha;
 *  - as colunas de dias são substituídas por dados das ocorrências
 *    (Total, Dias, Última, Pico). Em /analise (chamada sem essas flags) o
 *    comportamento continua idêntico ao heatmap original.
 *
 * A coluna "Ação" reaproveita window.PreventivaModal.renderAcaoHtml() — a
 * mesma lógica de 3 estados (criar/ver/preventivado) já usada na tabela
 * #tbl-comb da Análise, sem duplicar regra de negócio.
 */
window.AnaliseHeatmap = (function () {
  'use strict';

  var onDayClick = function () {};
  var onTotalClick = function () {};
  var showAcao = false;
  var groupGpon = false;
  var listMode = false; // substitui as colunas de dias por dados das ocorrências
  var containerEl = null;

  function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function dayCrit(v) {
    if (v === 0) return 'zero';
    if (v >= 5)  return 'alta';
    if (v >= 2)  return 'media';
    return 'baixa';
  }

  function fmtDate(iso) {
    var p = iso.split('-');
    return p[2] + '/' + p[1]; // dd/mm
  }

  function renderAcao(combo) {
    if (!window.PreventivaModal) return '';
    return window.PreventivaModal.renderAcaoHtml({
      gpon: combo.gpon, sp: combo.sp, count: combo.total,
      preventiva_status: combo.preventiva_status || null,
      preventiva_id: combo.preventiva_id || null,
      preventiva_concluido_em: combo.preventiva_concluido_em || null,
    });
  }

  var CRIT = {
    alta:  { bar: '#dc2626', cls: 'crit-alta'  },
    media: { bar: '#f59e0b', cls: 'crit-media' },
    baixa: { bar: '#10b981', cls: 'crit-baixa' },
  };

  // Modo lista (Preventivo): tabela plana GPON | Splitter | Ocorrências | Ação
  function renderListHtml(combos) {
    var html = '<div class="heatmap-scroll"><table class="hp-list-tbl"><thead><tr>';
    html += '<th class="hp-col-gpon">GPON</th>';
    html += '<th class="hp-col-splitter">Splitter</th>';
    html += '<th class="hp-col-ocor">Ocorrências</th>';
    html += '<th class="hp-col-acao">Ação</th>';
    html += '</tr></thead><tbody>';

    combos.forEach(function (combo) {
      var crit   = CRIT[combo.crit] || CRIT.baixa;
      var titulo = combo.gpon + ' - ' + combo.sp + ' • ' + combo.total + ' ocorrência' + (combo.total === 1 ? '' : 's');
      html += '<tr class="hp-row ' + crit.cls + '" style="--hp-bar:' + crit.bar + '">';
      html += '<td class="hp-col-gpon"><span class="hp-chip hp-chip-gpon"><i class="bi bi-wifi"></i>' + esc(combo.gpon) + '</span></td>';
      html += '<td class="hp-col-splitter"><span class="hp-chip hp-chip-sp" title="' + esc(titulo) + '">' + esc(combo.sp) + '</span></td>';
      html += '<td class="hp-col-ocor"><span class="hp-ocor-badge" title="' + esc(titulo) + '">' + combo.total + '</span></td>';
      html += '<td class="hp-col-acao">' + renderAcao(combo) + '</td>';
      html += '</tr>';
    });

    html += '</tbody></table></div>';
    return html;
  }

  function render(h) {
    if (!containerEl) return;
    if (!h || !h.combos || !h.combos.length) {
      containerEl.innerHTML = '<div class="analise-empty"><i class="bi bi-grid"></i>Sem dados suficientes</div>';
      return;
    }

    if (listMode) {
      containerEl.innerHTML = renderListHtml(h.combos);
      return;
    }

    // ── Heatmap padrão (dias) ───────────────────────────────────────
    var nDates = h.dates ? h.dates.length : 0;
    var nCols  = 1 + (showAcao ? 1 : 0) + (nDates + 1);

    var html = '<div class="heatmap-scroll"><table class="heatmap-tbl' + (showAcao ? ' hm-tbl-acao' : '') + '"><thead><tr>';
    html += '<th class="heatmap-gpon-label">GPON + Splitter</th>';
    if (showAcao) html += '<th class="hm-acao-th">Ação</th>';
    h.dates.forEach(function (d) { html += '<th class="hm-date-th">' + fmtDate(d) + '</th>'; });
    html += '<th class="hm-total">Total</th>';
    html += '</tr></thead><tbody>';

    var groups;
    if (groupGpon) {
      var byGpon = {};
      h.combos.forEach(function (c) {
        if (!byGpon[c.gpon]) byGpon[c.gpon] = { gpon: c.gpon, combos: [], total: 0 };
        byGpon[c.gpon].combos.push(c);
        byGpon[c.gpon].total += c.total || 0;
      });
      groups = Object.keys(byGpon).map(function (k) { return byGpon[k]; })
        .sort(function (a, b) { return b.total - a.total; });
    } else {
      groups = [{ combos: h.combos, total: 0 }];
    }

    groups.forEach(function (grp) {
      if (groupGpon) {
        html += '<tr class="hm-gpon-group">';
        html += '<td class="heatmap-gpon-label hm-gpon-group-label" title="' + esc(grp.gpon) + '">' + esc(grp.gpon) + '</td>';
        html += '<td class="hm-gpon-group-rest" colspan="' + (nCols - 1) + '">'
              + grp.combos.length + ' splitter' + (grp.combos.length === 1 ? '' : 's') + ' • '
              + grp.total + ' ocorrência' + (grp.total === 1 ? '' : 's')
              + '</td>';
        html += '</tr>';
      }

      grp.combos.forEach(function (combo) {
        html += '<tr>';
        html += '<td class="heatmap-gpon-label" title="' + esc(combo.gpon) + ' - ' + esc(combo.sp) + '">'
              + esc(groupGpon ? combo.sp : combo.gpon + ' - ' + combo.sp) + '</td>';
        if (showAcao) html += '<td class="hm-acao-td">' + renderAcao(combo) + '</td>';
        h.dates.forEach(function (d) {
          var v   = combo.days[d] || 0;
          var cls = 'hm-' + dayCrit(v);
          if (v > 0) {
            html += '<td class="' + cls + ' hm-clickable" data-gpon="' + esc(combo.gpon) + '" data-sp="' + esc(combo.sp) + '" data-date="' + esc(d) + '" data-count="' + v + '" title="' + esc(combo.gpon) + ' - ' + esc(combo.sp) + ' · ' + v + ' ocorrência' + (v === 1 ? '' : 's') + '">' + v + '</td>';
          } else {
            html += '<td class="' + cls + '">·</td>';
          }
        });
        html += '<td class="hm-total' + (combo.total > 0 ? ' hm-total-clickable' : '') + '"'
              + (combo.total > 0 ? ' data-gpon="' + esc(combo.gpon) + '" data-sp="' + esc(combo.sp) + '" data-total="' + combo.total + '"' : '')
              + '>' + (combo.total > 0 ? combo.total : '·') + '</td>';
        html += '</tr>';
      });
    });

    html += '</tbody></table></div>';
    containerEl.innerHTML = html;
  }

  function init(opts) {
    opts = opts || {};
    listMode = !!opts.listMode; // substitui colunas de dias por dados das ocorrências
    containerEl = document.getElementById(opts.containerId || 'heatmap-wrap');
    showAcao = !!opts.showAcao;
    groupGpon = !!opts.groupGpon;
    onDayClick = typeof opts.onDayClick === 'function' ? opts.onDayClick : function () {};
    onTotalClick = typeof opts.onTotalClick === 'function' ? opts.onTotalClick : function () {};

    if (!containerEl) return; // partial não presente nesta página

    // No modo lista não há mapa de cores — esconde a legenda do heatmap.
    if (listMode) {
      var card = containerEl.closest('.analise-card');
      var leg = card && card.querySelector('.heatmap-legend');
      if (leg) leg.style.display = 'none';
    }

    containerEl.addEventListener('click', function (e) {
      var td = e.target.closest('.hm-clickable');
      if (td) { onDayClick(td.dataset.gpon, td.dataset.sp, td.dataset.date, parseInt(td.dataset.count, 10) || 0); return; }
      var ttd = e.target.closest('.hm-total-clickable');
      if (ttd) { onTotalClick(ttd.dataset.gpon, ttd.dataset.sp, parseInt(ttd.dataset.total, 10) || 0); }
    });
  }

  return {
    init: init,
    render: render,
  };
})();
