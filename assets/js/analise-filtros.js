/* analise-filtros.js — bootstrap da barra de filtros compartilhada
 * (assets/js/shared/analise-filter-bar.js) para a página Análise.
 * A lógica em si foi extraída para lá e é reaproveitada também pelo
 * painel "Análise Preventiva" de /preventivo. */
(function(A) {
  'use strict';

  AnaliseFilterBar.init({ onChange: function() { A.loadAnalise(); } });

  // Mantém o contrato público já usado por analise-core.js e
  // analise-modais.js.
  A.buildParams = function() { return AnaliseFilterBar.buildParams(); };
  A.updatePeriodoBadge = function() { AnaliseFilterBar.updatePeriodoBadge(); };

})(window.AnaliseApp = window.AnaliseApp || {});
