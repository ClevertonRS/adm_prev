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
    .prev-card { background:#fff; border:1px solid #e2d9f5; border-radius:14px; padding:20px 22px; margin-bottom:16px; box-shadow:0 8px 24px rgba(76,29,149,.08); }
    .prev-card h6 { font-weight:700; color:var(--gpon-text); margin-bottom:14px; display:flex; align-items:center; gap:8px; font-size:15px; }
    .prev-card h6 i { color:var(--gpon-primary); }
    .prev-info-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:12px; }
    .prev-info-item { background:#faf8ff; border:1px solid #eee9fb; border-radius:10px; padding:10px 12px; }
    .prev-info-item .lbl { font-size:10px; text-transform:uppercase; letter-spacing:.06em; color:var(--gpon-muted); margin-bottom:3px; }
    .prev-info-item .val { font-size:14px; color:var(--gpon-text); font-weight:600; }
    .prev-checklist-item { display:flex; align-items:center; gap:8px; padding:6px 0; border-bottom:1px solid #f1f5f9; font-size:13px; }
    .prev-photo-thumb { position:relative; width:110px; height:110px; border-radius:8px; overflow:hidden; border:1px solid var(--gpon-border); }
    .prev-photo-thumb img { width:100%; height:100%; object-fit:cover; cursor:pointer; }
    .prev-photo-thumb .tipo-tag { position:absolute; bottom:0; left:0; right:0; background:rgba(0,0,0,.6); color:#fff; font-size:9px; text-align:center; padding:2px 0; text-transform:uppercase; }
    .prev-photo-thumb .del-btn { position:absolute; top:3px; right:3px; background:rgba(220,38,38,.85); color:#fff; border:none; border-radius:4px; width:20px; height:20px; font-size:11px; line-height:1; }
    .prev-hist-item { display:flex; gap:10px; padding:8px 0; border-bottom:1px solid #f1f5f9; font-size:12px; }
    .prev-hist-item:last-child { border-bottom:none; }

    /* Hero do cabeçalho — card flutuante com borda colorida (sem fundo gradiente) */
    .prev-hero {
      background:#ffffff;
      border:1px solid #d8d0f0; border-top:4px solid #8b5cf6;
      border-radius:16px; padding:24px 26px; margin-bottom:16px;
      box-shadow:0 10px 24px rgba(76,29,149,.10);
      position:relative; overflow:hidden;
    }
    .prev-hero::before {
      content:''; position:absolute; right:-40px; top:-40px; width:220px; height:220px;
      background:radial-gradient(circle,rgba(139,92,246,.10),transparent 70%);
    }
    .prev-hero .ph-title { font-size:22px; font-weight:800; letter-spacing:.2px; display:flex; align-items:center; gap:8px; z-index:1; position:relative; color:var(--gpon-text); }
    .prev-hero .ph-title i { color:#8b5cf6; }
    .prev-hero .ph-title .mono { font-family:'IBM Plex Mono',monospace; }
    .prev-hero .ph-sub { margin-top:6px; font-size:12px; opacity:.85; display:flex; gap:6px; align-items:center; }
    .prev-hero .ph-badges { margin-top:12px; display:flex; gap:6px; flex-wrap:wrap; position:relative; z-index:1; }
    .prev-hero .ph-actions { margin-top:14px; display:flex; gap:8px; flex-wrap:wrap; position:relative; z-index:1; }
    .prev-hero .btn { border-radius:9px; font-weight:600; box-shadow:0 2px 6px rgba(0,0,0,.12); }
    .prev-hero .btn-outline-danger { background:#fff; border-color:#fca5a5; color:#b91c1c; }
    .prev-hero .btn-outline-danger:hover { background:#fee2e2; border-color:#fecaca; }

    /* Info do hero */
    .prev-hero .ph-grid {
      display:grid; grid-template-columns:repeat(auto-fill,minmax(155px,1fr));
      gap:10px; margin-top:18px; position:relative; z-index:1;
    }
    .prev-hero .ph-item { background:#faf8ff; border:1px solid #eee9fb; border-radius:10px; padding:9px 12px; }
    .prev-hero .ph-item .lbl { font-size:9px; text-transform:uppercase; letter-spacing:.08em; color:var(--gpon-muted); font-weight:700; }
    .prev-hero .ph-item .val { font-size:14px; font-weight:700; margin-top:2px; color:var(--gpon-text); }
    .prev-hero .ph-obs { margin-top:16px; background:#faf8ff; border:1px solid #eee9fb; border-radius:10px; padding:12px 14px; font-size:13px; color:var(--gpon-text); position:relative; z-index:1; }
    .prev-hero .ph-obs strong { color:var(--gpon-muted); }

    /* Cartões coloridos de status — fundo branco, só borda colorida */
    .card-accent-triagem  { border:1px solid #fde68a !important; border-top:4px solid #f59e0b !important; }
    .card-accent-validacao { border:1px solid #a7f3d0 !important; border-top:4px solid #10b981 !important; }
    .card-accent-validacao h6 i { color:#059669; }
    /* Histórico — borda lavanda, flutuante */
    .prev-hist-card { border:1px solid #e0d7f4 !important; border-top:4px solid #a78bfa !important; }

    /* Botão Salvar alterações (Triagem) — moderno, gradiente âmbar */
    .btn-save-triagem {
      background:linear-gradient(135deg,#f59e0b,#d97706);
      border:none; color:#fff; font-weight:700; letter-spacing:.02em;
      padding:8px 18px; border-radius:10px; font-size:12px;
      display:inline-flex; align-items:center; gap:7px;
      box-shadow:0 4px 12px rgba(217,119,6,.28);
      transition:transform .15s ease, box-shadow .15s ease, filter .15s ease;
    }
    .btn-save-triagem:hover {
      filter:brightness(1.05); transform:translateY(-1px);
      box-shadow:0 6px 16px rgba(217,119,6,.35);
    }
    .btn-save-triagem:active { transform:translateY(0); }
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

    <!-- Cabeçalho (hero) -->
    <div class="prev-hero">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;position:relative;z-index:1">
        <div>
          <div class="ph-title"><i class="bi bi-shield-check"></i> <span id="hdr-gpon" class="mono"></span> <i class="bi bi-arrow-right" style="font-size:15px;opacity:.7"></i> <span id="hdr-splitter" class="mono"></span></div>
          <div class="ph-badges" id="hdr-badges"></div>
        </div>
        <div class="ph-actions" id="hdr-actions"></div>
      </div>
      <div class="ph-grid">
        <div class="ph-item"><div class="lbl">Localidade</div><div class="val" id="info-localidade">—</div></div>
        <div class="ph-item"><div class="lbl">Ocorrências</div><div class="val" id="info-ocorrencias">—</div></div>
        <div class="ph-item"><div class="lbl">Supervisor</div><div class="val" id="info-supervisor">—</div></div>
        <div class="ph-item"><div class="lbl">Técnico</div><div class="val" id="info-tecnico">—</div></div>
        <div class="ph-item"><div class="lbl">Criado por</div><div class="val" id="info-criador">—</div></div>
        <div class="ph-item"><div class="lbl">Criado em</div><div class="val" id="info-criado-em">—</div></div>
        <div class="ph-item"><div class="lbl">Concluído em</div><div class="val" id="info-concluido-em">—</div></div>
      </div>
      <div class="ph-obs"><strong style="font-size:10px;letter-spacing:.08em;text-transform:uppercase;opacity:.8">Observação inicial</strong><div id="info-observacao" style="margin-top:4px">—</div></div>
    </div>

    <!-- Triagem (supervisor/admin/backoffice) -->
    <div class="prev-card card-accent-triagem" id="card-triagem" style="display:none">
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
          <button type="button" class="btn btn-sm btn-primary btn-save-triagem" id="btn-salvar-triagem"><i class="bi bi-save"></i> Salvar alterações</button>
      </div>
      <style>
        /* Campos do formulário — aparência discreta flutuante */
        .card-accent-triagem .form-label {
          font-size:10px; text-transform:uppercase; letter-spacing:.06em;
          color:var(--gpon-muted); font-weight:700; margin-bottom:4px;
        }
        .card-accent-triagem .form-select {
          border:1px solid #e9e2f7; border-radius:10px; padding:8px 12px;
          font-size:13px; color:var(--gpon-text); background:#fbfaff;
          box-shadow:0 2px 6px rgba(76,29,149,.06);
          transition:border-color .15s ease, box-shadow .15s ease, background .15s ease;
        }
        .card-accent-triagem .form-select:hover { border-color:#c8b8ec; }
        .card-accent-triagem .form-select:focus {
          border-color:#a78bfa; box-shadow:0 0 0 3px rgba(167,139,250,.18);
          background:#fff; outline:none;
        }
      </style>
        </div>
      </div>
    </div>

    <!-- Validação (supervisor/admin/backoffice) -->
    <div class="prev-card card-accent-validacao" id="card-validacao" style="display:none">
      <h6><i class="bi bi-clipboard-check"></i> Validação</h6>
      <p style="font-size:13px;color:var(--gpon-muted)">Revise o checklist, materiais e fotos antes de aprovar ou devolver.</p>
      <div class="d-flex gap-2 justify-content-end">
        <button type="button" class="btn btn-sm btn-outline-danger" id="btn-devolver"><i class="bi bi-arrow-counterclockwise"></i> Devolver com pendência</button>
        <button type="button" class="btn btn-sm btn-success" id="btn-aprovar"><i class="bi bi-check-circle"></i> Aprovar e concluir</button>
      </div>
    </div>

    <!-- Histórico -->
    <div class="prev-card prev-hist-card">
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
