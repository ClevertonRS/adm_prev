<?php
/**
 * Card do Heatmap de ocorrências (Mapa Interativo). Compartilhado entre
 * views/analise.php e preventivo/views/preventivo.php.
 *
 * A tabela em si (linhas/colunas, incl. a coluna extra "Ação") é montada
 * inteiramente em JS por assets/js/shared/analise-heatmap.js
 * (window.AnaliseHeatmap.render), que decide se mostra "Ação" via a opção
 * showAcao — nenhum parâmetro PHP é necessário aqui.
 */
?>
<div class="analise-card">
  <div class="analise-card-header">
    <i class="bi bi-grid-3x3-gap-fill" style="color:var(--gpon-primary)"></i>
    Mapa Interativo de Ocorrências
    <span class="analise-card-sub">concentração de falhas por combinação</span>
  </div>
  <div id="heatmap-wrap"><div class="analise-empty"><i class="bi bi-hourglass-split"></i>Carregando…</div></div>
  <div class="heatmap-legend">
    <span class="hm-leg"><span class="hm-leg-dot" style="background:#fee2e2;border:1px solid #fca5a5"></span> Alta</span>
    <span class="hm-leg"><span class="hm-leg-dot" style="background:#fef9c3;border:1px solid #fde68a"></span> Média</span>
    <span class="hm-leg"><span class="hm-leg-dot" style="background:#dcfce7;border:1px solid #86efac"></span> Baixa</span>
    <span class="hm-leg"><span class="hm-leg-dot" style="background:#f9fafb;border:1px solid #e5e7eb"></span> Sem ocorrência</span>
  </div>
</div>
