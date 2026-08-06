/* assets/js/preventivo/analise-preventiva.js
 * Painel "Análise Preventiva" de /preventivo — reaproveita a barra de
 * filtros e o Heatmap compartilhados (assets/js/shared/analise-filter-bar.js
 * e assets/js/shared/analise-heatmap.js) e o modal de criação
 * (assets/js/shared/preventiva-modal.js), com a coluna "Ação" habilitada.
 *
 * Segue o mesmo padrão de módulo de assets/js/preventivo/dashboard.js:
 * window.PreventivoAnalisePreventiva = (IIFE) com init/onActivate.
 *
 * Os cliques nas células de dia/total do Heatmap não abrem os modais de
 * drill-down aqui (esses modais vivem em assets/js/analise-modais.js, não
 * carregado nesta página, e o pedido original só cobria a coluna "Ação") —
 * ficam sem ação por enquanto.
 */
window.PreventivoAnalisePreventiva = (function () {
  'use strict';

  var loaded = false;

  function _load() {
    var qs = AnaliseFilterBar.buildParams();
    fetch(BASE_PATH + '/api/analise' + (qs ? '?' + qs : ''), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.error) throw new Error(d.message || 'Erro ao carregar dados.');
        AnaliseFilterBar.updatePeriodoBadge();
        AnaliseHeatmap.render(d.heatmap || null);
      })
      .catch(function () {
        var el = document.getElementById('heatmap-wrap');
        if (el) el.innerHTML = '<div class="analise-empty"><i class="bi bi-exclamation-triangle"></i>Falha ao carregar o mapa de calor.</div>';
      });
  }

  function init() {
    AnaliseFilterBar.init({ onChange: _load });
    AnaliseHeatmap.init({ containerId: 'heatmap-wrap', showAcao: true, groupGpon: true, listMode: true });
    PreventivaModal.init({ basePath: BASE_PATH, onCreated: _load });
  }

  // Chamado por activatePanel() em preventivo.php na primeira vez que o
  // painel "Análise Preventiva" é aberto — evita buscar /api/analise em
  // todo carregamento de /preventivo para quem nunca visita este painel.
  function onActivate() {
    if (loaded) return;
    loaded = true;
    init();
    _load();
  }

  return { onActivate: onActivate };
})();
