<?php
// ── HTML: Análise de Reincidência ─────────────────────────────
function gpon_render_analise(PDO $pdo, array $user): void
{
    $user    = gpon_require_admin_or_backoffice();
    $base    = GPON_BASE_PATH;
    $isAdmin = gpon_is_admin();
    // Data loaded via AJAX — no PHP processing on page render
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Radar GPON</title>
  <link rel="icon" type="image/svg+xml" href="<?= $base ?>/assets/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/gpon.css?v=<?= filemtime(__DIR__ . '/../assets/css/gpon.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/shared/analise-heatmap.css?v=<?= filemtime(__DIR__ . '/../assets/css/shared/analise-heatmap.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/analise.css?v=<?= filemtime(__DIR__ . '/../assets/css/analise.css') ?>">
  <script>const BASE_PATH = '<?= $base ?>';</script>
</head>
<body>
<div id="flash-bar" class="flash-bar"></div>

<!-- TOPBAR -->
<header class="gpon-topbar">
  <div class="topbar-left">
    <div class="topbar-logo-icon" id="btn-reload-page" title="Recarregar página" style="cursor:pointer">📡</div>
    <div class="topbar-brand">
      <span class="brand-title">Radar GPON</span>
      <span class="brand-sub">Análise de Reincidência</span>
    </div>
  </div>
  <div class="topbar-actions">
    <?php $ua = gpon_ultima_atualizacao($pdo); ?>
    <div class="topbar-last-update" id="topbar-last-update" title="Última importação de dados">
      <i class="bi bi-clock-history"></i>
      <span id="topbar-ua-text"><?= $ua ? 'Atualizado: ' . $ua : 'Sem importações' ?></span>
    </div>

    <a href="<?= $base ?>/preventivo" class="tbtn" title="Preventivas de Rede">
      <i class="bi bi-shield-check"></i><span>Preventivas</span>
    </a>

    <a href="<?= $base ?>/" class="tbtn" title="Voltar para Página Inicial">
      <i class="bi bi-house-door"></i><span>Início</span>
    </a>

    <div class="user-badge">
      <i class="bi bi-person-circle"></i>
      <span><?= htmlspecialchars($user['nome'], ENT_QUOTES, 'UTF-8') ?></span>
      <?php if ($isAdmin): ?>
        <a href="<?= $base ?>/admin" class="user-badge-admin" title="Admin"><i class="bi bi-gear-fill"></i></a>
      <?php endif; ?>
      <a href="<?= $base ?>/logout" class="logout-btn" title="Sair"><i class="bi bi-box-arrow-right"></i></a>
    </div>
  </div>
</header>

<div style="margin-top:var(--gpon-header-h); padding:20px;">

  <!-- FILTROS -->
  <?php include __DIR__ . '/partials/analise-filter-bar.php'; ?>

  <!-- KPI CARDS — skeleton enquanto carrega -->
  <div class="kpi-grid" style="margin-bottom:20px">
    <div class="kpi-card akpi" data-type="a-ocorrencias">
      <i class="bi bi-list-ol kpi-icon"></i>
      <span class="kpi-value" id="kpi-analisados"><span class="skeleton kpi-skel">&nbsp;</span></span>
      <span class="kpi-label">Ocorrências</span>
      <span class="kpi-sub" id="kpi-analisados-sub">GPONs • SPs</span>
    </div>
    <div class="kpi-card akpi" data-type="a-ultima-reinc">
      <i class="bi bi-exclamation-triangle kpi-icon"></i>
      <span class="kpi-value" id="kpi-ultima-reinc-val"><span class="skeleton kpi-skel">&nbsp;</span></span>
      <span class="kpi-label">Última Reincidência Crítica</span>
      <span class="kpi-sub" id="kpi-ultima-reinc-gpon">—</span>
      <span class="kpi-sub2" id="kpi-ultima-reinc-data">—</span>
    </div>
    <div class="kpi-card akpi" data-type="a-reincidencias">
      <i class="bi bi-arrow-repeat kpi-icon"></i>
      <span class="kpi-value" id="kpi-reincidentes"><span class="skeleton kpi-skel">&nbsp;</span></span>
      <span class="kpi-label">Reincidências Detectadas</span>
      <span class="kpi-sub" id="kpi-reinc-sub">combinações GPON + Splitter reincidentes</span>
    </div>
    <div class="kpi-card akpi" data-type="a-maior-reinc">
      <i class="bi bi-graph-up-arrow kpi-icon"></i>
      <span class="kpi-value" id="kpi-taxa-reinc"><span class="skeleton kpi-skel">&nbsp;</span></span>
      <span class="kpi-label">Maior Reincidência</span>
      <span class="kpi-sub" id="kpi-top-comb">—</span>
    </div>
    <div class="kpi-card akpi" data-type="a-indice">
      <i class="bi bi-percent kpi-icon"></i>
      <span class="kpi-value" id="kpi-indice-reinc"><span class="skeleton kpi-skel">&nbsp;</span></span>
      <span class="kpi-label">Índice Reincidente</span>
      <span class="kpi-sub" id="kpi-indice-sub">carregando…</span>
    </div>
    <div class="kpi-card akpi" data-type="a-tmr">
      <i class="bi bi-stopwatch kpi-icon"></i>
      <span class="kpi-value" id="kpi-tmr-val"><span class="skeleton kpi-skel">&nbsp;</span></span>
      <span class="kpi-label">Tempo Médio de Resolução</span>
      <span class="kpi-sub" id="kpi-tmr-sub">OCs encerradas no período</span>
    </div>
  </div>

  <!-- HEATMAP -->
  <?php include __DIR__ . '/partials/analise-heatmap.php'; ?>

  <!-- TIMELINE + CAUSAS -->
  <div class="analise-2col">
    <div class="analise-card" style="margin-bottom:0">
      <div class="analise-card-header">
        <i class="bi bi-activity" style="color:#0284c7"></i>
        Linha do Tempo de Reincidência
        <span class="analise-card-sub">combinações mais impactadas no período</span>
      </div>
      <div id="timeline-wrap"><div class="analise-empty"><i class="bi bi-hourglass-split"></i>Carregando…</div></div>
    </div>
    <div class="analise-card" style="margin-bottom:0">
      <div class="analise-card-header">
        <i class="bi bi-bar-chart-fill" style="color:#d97706"></i>
        Causas Operacionais
        <span class="analise-card-sub">causa + reparo aplicados</span>
      </div>
      <div id="causas-wrap"><div class="analise-empty"><i class="bi bi-hourglass-split"></i>Carregando…</div></div>
    </div>
  </div>

  <!-- MTTR POR CIDADE -->
  <div class="analise-card">
    <div class="analise-card-header">
      <i class="bi bi-stopwatch" style="color:#16a34a"></i>
      TMR por Cidade
      <span id="mttr-geral-badge" class="analise-periodo-badge" style="display:none;margin-left:8px"></span>
      <span class="analise-card-sub">tempo médio de resolução (aging_encerrados)</span>
    </div>
    <div id="mttr-wrap"><div class="analise-empty"><i class="bi bi-hourglass-split"></i>Carregando…</div></div>
  </div>

  <!-- RANKING TABS -->
  <div class="table-card">
    <div class="tab-nav" id="tab-nav">
      <button class="tab-btn active" data-tab="gpon">
        <i class="bi bi-hdd-network"></i> Ranking GPON
        <span class="tab-count" id="cnt-gpon">…</span>
      </button>
      <button class="tab-btn" data-tab="comb">
        <i class="bi bi-link-45deg"></i> GPON + Splitter
        <span class="tab-count" id="cnt-comb">…</span>
      </button>
      <button class="tab-btn" data-tab="last">
        <i class="bi bi-clock-history"></i> Última Reincidência
        <span class="tab-count" id="cnt-last">…</span>
      </button>
    </div>

    <!-- TAB: GPON -->
    <div id="tab-gpon" class="tab-content">
      <table id="tbl-gpon" class="display" style="width:100%">
        <thead>
          <tr>
            <th>#</th><th>GPON</th><th id="th-ocorr">OC / 30 Dias</th>
            <th>OCs/Dia</th><th>Criticidade</th><th>TMR</th>
            <th>Tendência</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>

    <!-- TAB: Combinado -->
    <div id="tab-comb" class="tab-content" style="display:none">
      <table id="tbl-comb" class="display" style="width:100%">
        <thead>
          <tr>
            <th>GPON</th><th>Splitter</th><th>Ocorrências</th>
            <th>% do Total</th><th>Criticidade</th><th>Recorrência</th>
            <th>Ação</th><th style="display:none"></th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>

    <!-- TAB: Última Reincidência -->
    <div id="tab-last" class="tab-content" style="display:none">
      <table id="tbl-last" class="display" style="width:100%">
        <thead>
          <tr>
            <th>GPON</th><th>Splitter</th><th>Última OC</th>
            <th>Última Falha</th><th>Total Reincidências</th><th>Criticidade</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

</div><!-- /main -->

<!-- ── MODAL: Histórico GPON + SP ─────────────────────────────── -->
<div id="modal-historico" class="hist-modal-overlay" style="display:none" role="dialog" aria-modal="true" aria-labelledby="hist-modal-title">
  <div class="hist-modal-box">
    <div class="hist-modal-header">
      <div>
        <div id="hist-modal-title" class="hist-modal-title">Histórico de Ocorrências</div>
        <div id="hist-modal-sub" class="hist-modal-sub"></div>
      </div>
      <button class="hist-modal-close" id="hist-modal-close" title="Fechar">&times;</button>
    </div>
    <div class="hist-modal-body">
      <div class="hist-period-filter">
        <span class="hist-period-label">Período:</span>
        <button class="hist-period-btn active" data-periodo="">Todos</button>
        <button class="hist-period-btn" data-periodo="7d">7d</button>
        <button class="hist-period-btn" data-periodo="15d">15d</button>
        <button class="hist-period-btn" data-periodo="30d">30d</button>
      </div>
      <div id="hist-loading" class="hist-loading"><i class="bi bi-hourglass-split"></i> Carregando…</div>
      <div id="hist-table-wrap" style="display:none">
        <table id="tbl-historico" class="display" style="width:100%">
          <thead>
            <tr>
              <th>OC</th><th>Splitter</th><th>Status</th><th>Abertura</th><th>Encerramento</th>
              <th>SLA</th><th>Cidade</th><th>Empresa</th><th>Baixa Causa</th>
              <th>Baixa Reparo</th><th>Tempo</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ── MODAL: Heatmap — ocorrências do dia ─────────────────────── -->
<div id="modal-heatmap-historico" class="hist-modal-overlay" style="display:none" role="dialog" aria-modal="true" aria-labelledby="hm-hist-modal-title">
  <div class="hist-modal-box">
    <div class="hist-modal-header">
      <div>
        <div id="hm-hist-modal-title" class="hist-modal-title">Ocorrências do Dia</div>
        <div id="hm-hist-modal-sub" class="hist-modal-sub"></div>
      </div>
      <button class="hist-modal-close" id="hm-hist-modal-close" title="Fechar">&times;</button>
    </div>
    <div class="hist-modal-body">
      <div id="hm-hist-loading" class="hist-loading"><i class="bi bi-hourglass-split"></i> Carregando…</div>
      <div id="hm-hist-table-wrap" style="display:none">
        <table id="tbl-hm-historico" class="display" style="width:100%">
          <thead>
            <tr>
              <th>OC</th><th>Splitter</th><th>Status</th><th>Abertura</th><th>Encerramento</th>
              <th>SLA</th><th>Cidade</th><th>Empresa</th><th>Baixa Causa</th>
              <th>Baixa Reparo</th><th>Tempo</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ── MODAL: Histórico Analítico da GPON ──────────────────────── -->
<div id="modal-analitico" class="hist-modal-overlay" style="display:none" role="dialog" aria-modal="true" aria-labelledby="ana-modal-title">
  <div class="hist-modal-box ana-modal-box">
    <div class="hist-modal-header ana-modal-header">
      <div>
        <div id="ana-modal-title" class="hist-modal-title"><i class="bi bi-graph-up-arrow"></i> Histórico Analítico do GPON</div>
        <div id="ana-modal-sub" class="hist-modal-sub"></div>
      </div>
      <button class="hist-modal-close" id="ana-modal-close" title="Fechar">&times;</button>
    </div>
    <div class="hist-modal-body">
      <div id="ana-loading" class="hist-loading"><i class="bi bi-hourglass-split"></i> Carregando…</div>
      <div id="ana-content" style="display:none">
        <div id="ana-resumo" class="ana-resumo-block" style="display:none"></div>
        <div id="ana-kpis" class="ana-kpi-row"></div>
        <div class="ana-section">
          <div id="ana-evolucao-title" class="ana-section-title"><i class="bi bi-bar-chart"></i> Evolução Mensal</div>
          <div id="ana-chart" class="ana-chart"></div>
          <div class="ana-chart-legend">
            <span><span class="ana-leg-dot" style="background:#dc2626"></span> Maior volume <span class="ana-leg-hint">— mais ocorrências do período ▲</span></span>
            <span><span class="ana-leg-dot" style="background:#7c3aed;opacity:.85"></span> Dentro do padrão <span class="ana-leg-hint">— demais meses</span></span>
            <span><span class="ana-leg-dot" style="background:#16a34a"></span> Menor volume <span class="ana-leg-hint">— menos ocorrências do período ▼</span></span>
            <span><span class="ana-leg-dash" style="background:#f59e0b"></span> Tendência <span class="ana-leg-hint">— regressão linear</span></span>
            <span><span class="ana-leg-dash" style="background:#a78bfa"></span> Média mensal</span>
          </div>
        </div>
        <div class="ana-section">
          <div id="ana-mttr-title" class="ana-section-title"><i class="bi bi-stopwatch"></i> TMR Médio por Mês</div>
          <div id="ana-mttr" class="ana-chart"></div>
          <div class="ana-chart-legend">
            <span><span class="ana-leg-dot" style="background:#dc2626"></span> Maior TMR <span class="ana-leg-hint">— pior tempo de reparo ▲</span></span>
            <span><span class="ana-leg-dot" style="background:#7c3aed"></span> Dentro do padrão <span class="ana-leg-hint">— demais meses</span></span>
            <span><span class="ana-leg-dot" style="background:#16a34a"></span> Menor TMR <span class="ana-leg-hint">— melhor tempo de reparo ▼</span></span>
          </div>
        </div>
        <div class="ana-2col">
          <div class="ana-section" style="margin-bottom:0">
            <div class="ana-section-title"><i class="bi bi-bar-chart-steps"></i> Principais Causas</div>
            <div id="ana-causas"></div>
          </div>
          <div class="ana-section" style="margin-bottom:0">
            <div class="ana-section-title"><i class="bi bi-diagram-3"></i> Splitters com Maior Reincidência</div>
            <div id="ana-splitters"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/modals/preventiva-criacao.php'; ?>

<script>window.GPON_BASE_PATH = <?= json_encode($base) ?>;</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="<?= $base ?>/assets/js/shared/analise-filter-bar.js?v=<?= filemtime(__DIR__ . '/../assets/js/shared/analise-filter-bar.js') ?>"></script>
<script src="<?= $base ?>/assets/js/shared/analise-heatmap.js?v=<?= filemtime(__DIR__ . '/../assets/js/shared/analise-heatmap.js') ?>"></script>
<script src="<?= $base ?>/assets/js/shared/preventiva-modal.js?v=<?= filemtime(__DIR__ . '/../assets/js/shared/preventiva-modal.js') ?>"></script>
<script src="<?= $base ?>/assets/js/analise-core.js?v=<?= filemtime(__DIR__ . '/../assets/js/analise-core.js') ?>"></script>
<script src="<?= $base ?>/assets/js/analise-filtros.js?v=<?= filemtime(__DIR__ . '/../assets/js/analise-filtros.js') ?>"></script>
<script src="<?= $base ?>/assets/js/analise-dashboard.js?v=<?= filemtime(__DIR__ . '/../assets/js/analise-dashboard.js') ?>"></script>
<script src="<?= $base ?>/assets/js/analise-rankings.js?v=<?= filemtime(__DIR__ . '/../assets/js/analise-rankings.js') ?>"></script>
<script src="<?= $base ?>/assets/js/analise-modais.js?v=<?= filemtime(__DIR__ . '/../assets/js/analise-modais.js') ?>"></script>
<script src="<?= $base ?>/assets/js/analise-analitico.js?v=<?= filemtime(__DIR__ . '/../assets/js/analise-analitico.js') ?>"></script>
</body>
</html>
    <?php exit;
}
