<?php
/**
 * Detalhe / execução de uma Preventiva de Rede.
 * Requerido diretamente por index.php na rota /preventivo/{id}.
 * Variáveis disponíveis no escopo: $pdo, $user, $path (definidas em index.php).
 */
$base = GPON_BASE_PATH;
preg_match('#^/preventivo/(\d+)$#', $path, $mm);
$previewId = (int)($mm[1] ?? 0);
if ($previewId <= 0) {
    http_response_code(404);
    echo 'Preventiva inválida.';
    exit;
}

$CHECKLIST_ITEMS = [
    'inspecao_visual'         => 'Inspeção visual realizada',
    'organizacao_caixa'       => 'Organização de caixa/bandejamento',
    'limpeza_recomposicao'    => 'Limpeza/recomposição',
    'correcao_conectorizacao' => 'Correção de conectorização',
    'substituicao_splitter'   => 'Substituição de splitter',
    'substituicao_cordao'     => 'Substituição de cordão / cabo drop / patch cord',
    'adequacao_acomodacao'    => 'Adequação de acomodação / identificação',
    'correcao_vedacao'        => 'Correção de vedação / proteção',
    'foto_antes'              => 'Foto antes',
    'foto_depois'             => 'Foto depois',
    'teste_final'             => 'Teste final executado',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Preventiva #<?= $previewId ?> — Radar GPON</title>
  <link rel="icon" type="image/svg+xml" href="<?= $base ?>/assets/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/gpon.css?v=<?= filemtime(__DIR__ . '/../../assets/css/gpon.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../../assets/css/admin.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/preventivo.css?v=<?= filemtime(__DIR__ . '/../../assets/css/preventivo.css') ?>">
  <script>
    const BASE_PATH = '<?= $base ?>';
    window.GPON_PREVENTIVA_ID    = <?= $previewId ?>;
    window.GPON_USER_ID          = <?= (int)($user['id'] ?? 0) ?>;
    window.GPON_USER_NIVEL       = <?= json_encode($user['nivel'] ?? 'operador') ?>;
    window.GPON_CHECKLIST_ITEMS  = <?= json_encode($CHECKLIST_ITEMS, JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <style>
    .prev-card { background:var(--gpon-surface); border:1px solid var(--gpon-border); border-radius:10px; padding:18px 20px; margin-bottom:16px; }
    .prev-card h6 { font-weight:700; color:var(--gpon-text); margin-bottom:14px; display:flex; align-items:center; gap:8px; }
    .prev-card h6 i { color:var(--gpon-primary); }
    .prev-info-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:14px; }
    .prev-info-item .lbl { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:var(--gpon-muted); margin-bottom:2px; }
    .prev-info-item .val { font-size:14px; color:var(--gpon-text); font-weight:600; }
    .prev-checklist-item { display:flex; align-items:center; gap:8px; padding:6px 0; border-bottom:1px solid #f1f5f9; font-size:13px; }
    .prev-photo-thumb { position:relative; width:110px; height:110px; border-radius:8px; overflow:hidden; border:1px solid var(--gpon-border); }
    .prev-photo-thumb img { width:100%; height:100%; object-fit:cover; cursor:pointer; }
    .prev-photo-thumb .tipo-tag { position:absolute; bottom:0; left:0; right:0; background:rgba(0,0,0,.6); color:#fff; font-size:9px; text-align:center; padding:2px 0; text-transform:uppercase; }
    .prev-photo-thumb .del-btn { position:absolute; top:3px; right:3px; background:rgba(220,38,38,.85); color:#fff; border:none; border-radius:4px; width:20px; height:20px; font-size:11px; line-height:1; }
    .prev-hist-item { display:flex; gap:10px; padding:8px 0; border-bottom:1px solid #f1f5f9; font-size:12px; }
    .prev-hist-item:last-child { border-bottom:none; }
  </style>
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
      <span class="brand-sub">Preventiva #<?= $previewId ?></span>
    </div>
  </div>
  <div class="topbar-actions">
    <a href="<?= $base ?>/preventivo" class="tbtn" title="Voltar para a lista">
      <i class="bi bi-arrow-left"></i><span>Preventivas</span>
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
    <div style="padding:20px; max-width:1000px; margin-left:auto; margin-right:auto">

  <div id="loading-state" class="text-center text-muted py-5"><i class="bi bi-hourglass-split"></i> Carregando…</div>

  <div id="prev-content" style="display:none">

    <!-- Cabeçalho -->
    <div class="prev-card">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
        <div>
          <div style="font-size:20px;font-weight:700;color:var(--gpon-text)">
            <span id="hdr-gpon" class="mono"></span> <i class="bi bi-arrow-right" style="font-size:14px;color:var(--gpon-muted)"></i> <span id="hdr-splitter" class="mono"></span>
          </div>
          <div id="hdr-badges" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap"></div>
        </div>
        <div id="hdr-actions" style="display:flex;gap:8px;flex-wrap:wrap"></div>
      </div>
      <div class="prev-info-grid" style="margin-top:18px">
        <div class="prev-info-item"><div class="lbl">Localidade</div><div class="val" id="info-localidade">—</div></div>
        <div class="prev-info-item"><div class="lbl">Ocorrências no período</div><div class="val" id="info-ocorrencias">—</div></div>
        <div class="prev-info-item"><div class="lbl">Supervisor</div><div class="val" id="info-supervisor">—</div></div>
        <div class="prev-info-item"><div class="lbl">Técnico</div><div class="val" id="info-tecnico">—</div></div>
        <div class="prev-info-item"><div class="lbl">Criado por</div><div class="val" id="info-criador">—</div></div>
        <div class="prev-info-item"><div class="lbl">Criado em</div><div class="val" id="info-criado-em">—</div></div>
        <div class="prev-info-item"><div class="lbl">Concluído em</div><div class="val" id="info-concluido-em">—</div></div>
      </div>
      <div style="margin-top:14px">
        <div class="lbl" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--gpon-muted)">Observação inicial</div>
        <div id="info-observacao" style="font-size:13px;margin-top:2px">—</div>
      </div>
    </div>

    <!-- Triagem (supervisor/admin/backoffice) -->
    <div class="prev-card" id="card-triagem" style="display:none">
      <h6><i class="bi bi-sliders"></i> Triagem</h6>
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Prioridade</label>
          <select class="form-select form-select-sm" id="triagem-prioridade">
            <option value="baixa">Baixa</option>
            <option value="media">Média</option>
            <option value="alta">Alta</option>
            <option value="urgente">Urgente</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Supervisor</label>
          <select class="form-select form-select-sm" id="triagem-supervisor"></select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Técnico</label>
          <select class="form-select form-select-sm" id="triagem-tecnico"></select>
        </div>
        <div class="col-12 d-flex justify-content-end">
          <button type="button" class="btn btn-sm btn-primary" id="btn-salvar-triagem"><i class="bi bi-save"></i> Salvar alterações</button>
        </div>
      </div>
    </div>

    <!-- Validação (supervisor/admin/backoffice) -->
    <div class="prev-card" id="card-validacao" style="display:none">
      <h6><i class="bi bi-clipboard-check"></i> Validação</h6>
      <p style="font-size:13px;color:var(--gpon-muted)">Revise o checklist, materiais e fotos antes de aprovar ou devolver.</p>
      <div class="d-flex gap-2 justify-content-end">
        <button type="button" class="btn btn-sm btn-outline-danger" id="btn-devolver"><i class="bi bi-arrow-counterclockwise"></i> Devolver com pendência</button>
        <button type="button" class="btn btn-sm btn-success" id="btn-aprovar"><i class="bi bi-check-circle"></i> Aprovar e concluir</button>
      </div>
    </div>

    <!-- Histórico -->
    <div class="prev-card">
      <h6><i class="bi bi-clock-history"></i> Histórico</h6>
      <div id="historico-list"><span class="text-muted" style="font-size:12px">Sem eventos.</span></div>
    </div>

  </div>
</div>

</main>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= $base ?>/assets/js/preventivo/common.js?v=<?= filemtime(__DIR__ . '/../../assets/js/preventivo/common.js') ?>"></script>
<script src="<?= $base ?>/assets/js/preventivo-detalhe.js?v=<?= filemtime(__DIR__ . '/../../assets/js/preventivo-detalhe.js') ?>"></script>
<script>
(function () {
  'use strict';
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

  // Na página de detalhe os painéis ficam em /preventivo: qualquer item do
  // menu com data-panel redireciona para lá, abrindo o painel via hash.
  document.querySelectorAll('#preventivo-sidebar .admin-nav-item[data-panel]').forEach(function (item) {
    item.addEventListener('click', function (e) {
      e.preventDefault();
      var hash = item.getAttribute('href').replace('#', '') || 'dashboard';
      if (item.dataset.panel === 'lista') hash = 'lista-' + (item.dataset.filter || '');
      window.location.href = BASE_PATH + '/preventivo#' + hash;
    });
  });
})();
</script>
</body>
</html>
