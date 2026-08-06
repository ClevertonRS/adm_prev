<?php
/**
 * Página principal do módulo de Preventiva de Rede (menu lateral, padrão Admin).
 * Requerido diretamente por index.php nas rotas /preventivo e /preventiva.
 * Variáveis disponíveis no escopo: $pdo, $user (definidas em index.php).
 * /preventivo/{id} continua abrindo preventivo/views/detalhe.php separadamente.
 */
$base = GPON_BASE_PATH;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Preventivas de Rede — Radar GPON</title>
  <link rel="icon" type="image/svg+xml" href="<?= $base ?>/assets/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/gpon.css?v=<?= filemtime(__DIR__ . '/../../assets/css/gpon.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../../assets/css/admin.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/preventivo.css?v=<?= filemtime(__DIR__ . '/../../assets/css/preventivo.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/shared/analise-heatmap.css?v=<?= filemtime(__DIR__ . '/../../assets/css/shared/analise-heatmap.css') ?>">
  <script>const BASE_PATH = '<?= $base ?>';</script>
</head>
<body>
<div id="flash-bar" class="flash-bar"></div>

<header class="gpon-topbar">
  <div class="topbar-left">
    <button class="sidebar-toggle" id="btn-sidebar-toggle" title="Alternar menu lateral">
      <i class="bi bi-list"></i>
    </button>
    <div class="topbar-logo-icon" onclick="location.href='<?= $base ?>/analise'" title="Radar GPON" style="cursor:pointer">📡</div>
    <div class="topbar-brand">
      <span class="brand-title">Radar GPON</span>
      <span class="brand-sub">Preventivas de Rede</span>
    </div>
  </div>
  <div class="topbar-actions">
    <a href="<?= $base ?>/analise" class="tbtn" title="Voltar para Análise">
      <i class="bi bi-arrow-left"></i><span>Análise</span>
    </a>
    <div class="user-badge">
      <i class="bi bi-person-circle"></i>
      <span><?= htmlspecialchars($user['nome'], ENT_QUOTES, 'UTF-8') ?></span>
      <a href="<?= $base ?>/logout" class="logout-btn" title="Sair"><i class="bi bi-box-arrow-right"></i></a>
    </div>
  </div>
</header>

<div class="gpon-layout">

  <aside class="gpon-sidebar admin-sidebar" id="preventivo-sidebar">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>
  </aside>

  <main class="admin-main">

    <!-- ── PAINEL: Visão Geral ─────────────────────────────── -->
    <div class="admin-panel" id="panel-dashboard">
      <div class="admin-panel-header">
        <h5 class="admin-panel-title"><i class="bi bi-speedometer2"></i> Visão Geral</h5>
      </div>
      <?php require __DIR__ . '/../partials/kpis.php'; ?>
    </div>

    <!-- ── PAINEL: Análise Preventiva ──────────────────────────
         Barra de filtros + Heatmap reaproveitados de /analise (ver
         views/partials/analise-filter-bar.php e analise-heatmap.php),
         com a coluna "Ação" habilitada (assets/js/preventivo/analise-preventiva.js). -->
    <div class="admin-panel d-none" id="panel-analise-preventiva">
      <div class="admin-panel-header">
        <h5 class="admin-panel-title"><i class="bi bi-grid-3x3-gap-fill"></i> Análise Preventiva</h5>
      </div>
      <?php include __DIR__ . '/../../views/partials/analise-filter-bar.php'; ?>
      <?php include __DIR__ . '/../../views/partials/analise-heatmap.php'; ?>
    </div>

    <!-- ── PAINEL: Lista filtrada por status ──────────────────
         Reaproveitado por Em Andamento / Triagem / Em Execução /
         Em Revisão / Concluídas / Histórico — mesma tabela, filtro
         diferente aplicado via assets/js/preventivo/lista.js. -->
    <div class="admin-panel d-none" id="panel-lista">
      <div class="admin-panel-header">
        <h5 class="admin-panel-title">
          <i class="bi bi-hourglass-split" id="lista-panel-icon"></i>
          <span id="lista-panel-title">Em Andamento</span>
        </h5>
      </div>
      <?php require __DIR__ . '/../partials/lista.php'; ?>
    </div>

  </main>

</div>

<?php include __DIR__ . '/../../includes/modals/preventiva-criacao.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="<?= $base ?>/assets/js/shared/analise-filter-bar.js?v=<?= filemtime(__DIR__ . '/../../assets/js/shared/analise-filter-bar.js') ?>"></script>
<script src="<?= $base ?>/assets/js/shared/analise-heatmap.js?v=<?= filemtime(__DIR__ . '/../../assets/js/shared/analise-heatmap.js') ?>"></script>
<script src="<?= $base ?>/assets/js/shared/preventiva-modal.js?v=<?= filemtime(__DIR__ . '/../../assets/js/shared/preventiva-modal.js') ?>"></script>
<script src="<?= $base ?>/assets/js/preventivo/common.js?v=<?= filemtime(__DIR__ . '/../../assets/js/preventivo/common.js') ?>"></script>
<script src="<?= $base ?>/assets/js/preventivo/dashboard.js?v=<?= filemtime(__DIR__ . '/../../assets/js/preventivo/dashboard.js') ?>"></script>
<script src="<?= $base ?>/assets/js/preventivo/lista.js?v=<?= filemtime(__DIR__ . '/../../assets/js/preventivo/lista.js') ?>"></script>
<script src="<?= $base ?>/assets/js/preventivo/analise-preventiva.js?v=<?= filemtime(__DIR__ . '/../../assets/js/preventivo/analise-preventiva.js') ?>"></script>
<script>
(function () {
  'use strict';

  // ── Toggle do menu lateral (mesmo padrão de views/admin.php) ─────────
  var sidebar = document.getElementById('preventivo-sidebar');
  var btnToggle = document.getElementById('btn-sidebar-toggle');

  if (btnToggle && sidebar) {
    var saved = null;
    try { saved = localStorage.getItem('gpon_preventivo_sidebar'); } catch (e) {}
    if (saved === 'collapsed') sidebar.classList.add('collapsed');

    btnToggle.addEventListener('click', function () {
      sidebar.classList.toggle('collapsed');
      try {
        localStorage.setItem('gpon_preventivo_sidebar', sidebar.classList.contains('collapsed') ? 'collapsed' : 'open');
      } catch (e) {}
    });
  }

  // ── Navegação entre painéis ────────────────────────────────────────
  var PANEL_META = {
    andamento:   { title: 'Em Andamento', icon: 'bi-hourglass-split' },
    triagem:     { title: 'Triagem', icon: 'bi-sliders' },
    em_execucao: { title: 'Em Execução', icon: 'bi-tools' },
    em_revisao:  { title: 'Em Revisão', icon: 'bi-clipboard-check' },
    concluida:   { title: 'Concluídas', icon: 'bi-check-circle' },
    cancelada:   { title: 'Canceladas', icon: 'bi-x-circle' },
    aberta:      { title: 'Abertas', icon: 'bi-folder2-open' },
    '':          { title: 'Histórico', icon: 'bi-clock-history' },
  };

  function activatePanel(panel, filterKey) {
    document.querySelectorAll('.admin-panel').forEach(function (p) { p.classList.add('d-none'); });
    var el = document.getElementById('panel-' + panel);
    if (el) el.classList.remove('d-none');

    document.querySelectorAll('.admin-nav-item[data-panel]').forEach(function (item) {
      var active = item.dataset.panel === panel && (panel !== 'lista' || item.dataset.filter === filterKey);
      item.classList.toggle('active', active);
    });

    if (panel === 'lista') {
      var meta = PANEL_META[filterKey] || { title: (PreventivoCommon.STATUS_LABELS[filterKey] || 'Preventivas'), icon: 'bi-shield-check' };
      document.getElementById('lista-panel-title').textContent = meta.title;
      document.getElementById('lista-panel-icon').className = 'bi ' + meta.icon;
      PreventivoLista.applyFilter(filterKey);
    } else if (panel === 'analise-preventiva') {
      // Busca /api/analise só na primeira vez que o painel é aberto.
      PreventivoAnalisePreventiva.onActivate();
    }

    try { history.replaceState(null, '', '#' + (panel === 'lista' ? 'lista-' + filterKey : panel)); } catch (e) {}
  }

  window.PreventivoGoToLista = function (filterKey) { activatePanel('lista', filterKey); };

  document.querySelectorAll('.admin-nav-item[data-panel]').forEach(function (item) {
    item.addEventListener('click', function (e) {
      e.preventDefault();
      activatePanel(item.dataset.panel, item.dataset.filter || '');
    });
  });

  // ── Carga única dos dados (dashboard + lista consomem o mesmo fetch) ──
  fetch(BASE_PATH + '/api/preventiva', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      var rows = res.ok ? (res.data || []) : [];
      PreventivoDashboard.render(rows);
      PreventivoLista.init(rows);

      var hash = location.hash.replace('#', '');
      if (hash.indexOf('lista-') === 0) {
        activatePanel('lista', hash.replace('lista-', ''));
      } else {
        activatePanel('dashboard', '');
      }
    })
    .catch(function () {
      document.getElementById('panel-dashboard').innerHTML =
        '<div class="text-center text-muted py-5"><i class="bi bi-exclamation-triangle" style="color:#dc2626"></i> Falha ao carregar dados de preventivas.</div>';
    });
})();
</script>
</body>
</html>
