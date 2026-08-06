/* assets/js/shared/analise-heatmap.js
 * Renderização do Heatmap (Mapa Interativo de Ocorrências). Extraído de
 * A.renderHeatmap em assets/js/analise-dashboard.js para ser reaproveitado
 * tanto em /analise (showAcao=false, comportamento idêntico ao atual)
 * quanto no painel "Análise Preventiva" de /preventivo (showAcao=true,
 * acrescenta a coluna "Ação" logo após "Total").
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
    return p[2] + '/' + p[1];
  }

  function render(h) {
    if (!containerEl) return;
    if (!h || !h.combos || !h.combos.length || !h.dates || !h.dates.length) {
      containerEl.innerHTML = '<div class="analise-empty"><i class="bi bi-grid"></i>Sem dados suficientes para o mapa de calor</div>';
      return;
    }

    var html = '<div class="heatmap-scroll"><table class="heatmap-tbl"><thead><tr>';
    html += '<th style="text-align:left;position:sticky;left:0;background:#f8f7ff;min-width:150px">GPON + Splitter</th>';
    h.dates.forEach(function (d) { html += '<th class="hm-date-th">' + fmtDate(d) + '</th>'; });
    html += '<th class="hm-total">Total</th>';
    if (showAcao) html += '<th class="hm-acao-th">Ação</th>';
    html += '</tr></thead><tbody>';

    h.combos.forEach(function (combo) {
      html += '<tr>';
      html += '<td class="heatmap-gpon-label">' + esc(combo.gpon) + ' - ' + esc(combo.sp) + '</td>';
      h.dates.forEach(function (d) {
        var v   = combo.days[d] || 0;
        var cls = 'hm-' + dayCrit(v);
        var tip = v
          ? esc(combo.gpon) + ' - ' + esc(combo.sp) + ' • ' + v + (v === 1 ? ' ocorrência' : ' ocorrências') + ' — clique para ver detalhes'
          : esc(combo.gpon) + ' - ' + esc(combo.sp);
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
      if (showAcao) {
        var acaoHtml = window.PreventivaModal
          ? window.PreventivaModal.renderAcaoHtml({
              gpon: combo.gpon, sp: combo.sp, count: combo.total,
              preventiva_status: combo.preventiva_status || null,
              preventiva_id: combo.preventiva_id || null,
              preventiva_concluido_em: combo.preventiva_concluido_em || null,
            })
          : '';
        html += '<td class="hm-acao-td">' + acaoHtml + '</td>';
      }
      html += '</tr>';
    });

    html += '</tbody></table></div>';
    containerEl.innerHTML = html;
  }

  function init(opts) {
    opts = opts || {};
    containerEl = document.getElementById(opts.containerId || 'heatmap-wrap');
    showAcao = !!opts.showAcao;
    onDayClick = typeof opts.onDayClick === 'function' ? opts.onDayClick : function () {};
    onTotalClick = typeof opts.onTotalClick === 'function' ? opts.onTotalClick : function () {};

    if (!containerEl) return; // partial não presente nesta página

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
