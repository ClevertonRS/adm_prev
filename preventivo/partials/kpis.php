<?php
/**
 * KPIs e "criadas recentemente" da Visão Geral do módulo de Preventiva.
 * Requerido por preventivo/views/preventivo.php. Valores preenchidos por
 * assets/js/preventivo/dashboard.js a partir de GET /api/preventiva (já
 * carregado uma única vez pelo controlador da página).
 */
?>
<div class="kpi-grid">
  <div class="kpi-card" data-status="total" onclick="PreventivoGoToLista('')" style="cursor:pointer">
    <i class="bi bi-shield-check kpi-icon"></i>
    <span class="kpi-label">Total</span>
    <span class="kpi-value" id="kpi-total">—</span>
    <span class="kpi-sub">Todas as preventivas</span>
  </div>
  <div class="kpi-card kpi-hide" data-status="aberta" onclick="PreventivoGoToLista('aberta')" style="cursor:pointer">
    <i class="bi bi-folder2-open kpi-icon"></i>
    <span class="kpi-label">Aberta</span>
    <span class="kpi-value" id="kpi-aberta">—</span>
    <span class="kpi-sub">Aguardando triagem</span>
  </div>
  <div class="kpi-card" data-status="triagem" onclick="PreventivoGoToLista('triagem')" style="cursor:pointer">
    <i class="bi bi-sliders kpi-icon"></i>
    <span class="kpi-label">Triagem</span>
    <span class="kpi-value" id="kpi-triagem">—</span>
    <span class="kpi-sub">Com o supervisor</span>
  </div>
  <div class="kpi-card" data-status="em_execucao" onclick="PreventivoGoToLista('em_execucao')" style="cursor:pointer">
    <i class="bi bi-tools kpi-icon"></i>
    <span class="kpi-label">Em Execução</span>
    <span class="kpi-value" id="kpi-em_execucao">—</span>
    <span class="kpi-sub">Com o técnico</span>
  </div>
  <div class="kpi-card" data-status="em_revisao" onclick="PreventivoGoToLista('em_revisao')" style="cursor:pointer">
    <i class="bi bi-clipboard-check kpi-icon"></i>
    <span class="kpi-label">Em Revisão</span>
    <span class="kpi-value" id="kpi-em_revisao">—</span>
    <span class="kpi-sub">Aguardando validação</span>
  </div>
  <div class="kpi-card" data-status="concluida" onclick="PreventivoGoToLista('concluida')" style="cursor:pointer">
    <i class="bi bi-check-circle kpi-icon"></i>
    <span class="kpi-label">Concluída</span>
    <span class="kpi-value" id="kpi-concluida">—</span>
    <span class="kpi-sub">Encerradas com sucesso</span>
  </div>
  <div class="kpi-card kpi-hide" data-status="cancelada" onclick="PreventivoGoToLista('cancelada')" style="cursor:pointer">
    <i class="bi bi-x-circle kpi-icon"></i>
    <span class="kpi-label">Cancelada</span>
    <span class="kpi-value" id="kpi-cancelada">—</span>
    <span class="kpi-sub">Encerradas sem execução</span>
  </div>
</div>

<div class="admin-card">
  <div class="admin-card-header">
    <span class="admin-card-title"><i class="bi bi-clock-history"></i> Criadas recentemente</span>
  </div>
  <div id="dash-recentes">
    <span class="text-muted" style="font-size:12px">Carregando…</span>
  </div>
</div>
