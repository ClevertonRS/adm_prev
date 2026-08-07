<?php
/**
 * Detalhe de uma Preventiva Concluída (somente-leitura).
 * Mostra a análise e a conclusão do atendimento (tabela atendimentos) e as
 * imagens de evidência agrupadas por tipo, com cores distintas para
 * diferenciar a análise da conclusão.
 * Requerido por index.php na rota /concluida/{id}. Variáveis: $pdo, $user, $path.
 */
$base = GPON_BASE_PATH;
preg_match('#^/concluida/(\d+)$#', $path, $mm);
$previewId = (int)($mm[1] ?? 0);
if ($previewId <= 0) {
    http_response_code(404);
    echo 'Registro inválido.';
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Conclusão #<?= $previewId ?> — Radar GPON</title>
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
    window.GPON_PREVENTIVA_ID = <?= $previewId ?>;
  </script>
  <style>
    .prev-card { background:#fff; border:1px solid #e2d9f5; border-radius:14px; padding:20px 22px; margin-bottom:16px; box-shadow:0 8px 24px rgba(76,29,149,.08); }
    .prev-card h6 { font-weight:700; color:var(--gpon-text); margin-bottom:14px; display:flex; align-items:center; gap:8px; font-size:15px; }
    .prev-card h6 i { color:var(--gpon-primary); }

    /* Linha com dois cards lado a lado */
    .prev-card-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; align-items:stretch; margin-bottom:16px; }
    .prev-card-row .prev-card { margin-bottom:0; height:100%; display:flex; flex-direction:column; }
    .prev-card-row .prev-card .prev-photo-thumb { flex-shrink:0; }
    @media (max-width: 860px) { .prev-card-row { grid-template-columns:1fr; } }

    /* Hero — card flutuante com borda colorida (mesmo padrão das demais páginas) */
    .prev-hero {
      background:#ffffff;
      border:1px solid #d8d0f0; border-top:4px solid #10b981;
      border-radius:16px; padding:24px 26px; margin-bottom:45px;
      box-shadow:0 10px 24px rgba(6,95,70,.10);
      position:relative; overflow:hidden;
    }
    .prev-hero::before {
      content:''; position:absolute; right:-40px; top:-40px; width:220px; height:220px;
      background:radial-gradient(circle,rgba(16,185,129,.10),transparent 70%);
    }
    .prev-hero .ph-title { font-size:22px; font-weight:800; letter-spacing:.2px; display:flex; align-items:center; gap:8px; z-index:1; position:relative; color:var(--gpon-text); flex-wrap:wrap; }
    .prev-hero .ph-title i { color:#10b981; }
    .prev-hero .ph-title .mono { font-family:'IBM Plex Mono',monospace; }
    .prev-hero .ph-badges { margin-top:12px; display:flex; gap:6px; flex-wrap:wrap; position:relative; z-index:1; }
    .prev-hero .ph-grid {
      display:grid; grid-template-columns:repeat(auto-fill,minmax(155px,1fr));
      gap:10px; margin-top:18px; position:relative; z-index:1;
    }
    .prev-hero .ph-item { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:9px 12px; }
    .prev-hero .ph-item .lbl { font-size:9px; text-transform:uppercase; letter-spacing:.08em; color:var(--gpon-muted); font-weight:700; }
    .prev-hero .ph-item .val { font-size:14px; font-weight:700; margin-top:2px; color:var(--gpon-text); }

    /* Card Análise — borda âmbar */
    .card-analise { border:1px solid #fde68a !important; border-top:4px solid #f59e0b !important; }
    .card-analise h6 i { color:#d97706; }

    /* Card Conclusão — borda verde esmeralda */
    .card-conclusao { border:1px solid #a7f3d0 !important; border-top:4px solid #10b981 !important; }
    .card-conclusao h6 i { color:#059669; }

    .desc-rotulo { font-size:10px; text-transform:uppercase; letter-spacing:.06em; color:var(--gpon-muted); font-weight:700; margin-bottom:6px; }
    .descricao { font-size:14px; color:var(--gpon-text); white-space:pre-wrap; background:#fffdf5; border:1px solid #fde68a; border-radius:10px; padding:14px 16px; }
    .descricao-concluida { background:#f0fdf4; border-color:#bbf7d0; }

    /* Fotos — grupos dentro de cada card */
    .foto-grupo { margin-bottom:18px; }
    .foto-grupo:last-child { margin-bottom:0; }
    .foto-grupo .fg-titulo { display:flex; align-items:center; gap:8px; font-weight:700; font-size:13px; margin-bottom:10px; }
    .foto-grupo .fg-titulo i { font-size:14px; }
    .foto-grupo .fg-count { font-size:10px; color:var(--gpon-muted); font-weight:600; }
    .fotos-grid { display:flex; gap:12px; flex-wrap:wrap; }
    .prev-photo-thumb { position:relative; width:120px; height:120px; border-radius:10px; overflow:hidden; border:1px solid var(--gpon-border); box-shadow:0 2px 6px rgba(76,29,149,.08); }
    .prev-photo-thumb img { width:100%; height:100%; object-fit:cover; cursor:pointer; display:block; }
    .foto-tag { position:absolute; bottom:0; left:0; right:0; background:rgba(0,0,0,.6); color:#fff; font-size:9px; text-align:center; padding:2px 0; text-transform:uppercase; }

    /* Ações finais — enviar para revisão */
    .prev-card-conclusao-acoes { display:flex; justify-content:flex-end; gap:8px; margin-top:4px; }
    .btn-enviar-revisao {
      background:linear-gradient(135deg,#8a1fb0,#6d0fa5);
      color:#fff; font-weight:700; letter-spacing:.02em;
      padding:10px 22px; border:none; border-radius:10px; font-size:13px;
      display:inline-flex; align-items:center; gap:8px;
      box-shadow:0 4px 14px rgba(109,15,165,.30);
      transition:transform .15s ease, box-shadow .15s ease, filter .15s ease;
    }
    .btn-enviar-revisao:hover { filter:brightness(1.08); transform:translateY(-1px); box-shadow:0 6px 18px rgba(109,15,165,.38); }
    .btn-enviar-revisao:active { transform:translateY(0); }
  </style>
</head>
<body>
<div id="flash-bar" class="flash-bar"></div>

<header class="gpon-topbar">
  <div class="topbar-left">
    <button class="sidebar-toggle" id="btn-sidebar-toggle" title="Alternar menu lateral">
      <i class="bi bi-list"></i>
    </button>
    <div class="topbar-logo-icon" onclick="location.href='<?= $base ?>/concluida'" title="Radar GPON" style="cursor:pointer">📡</div>
    <div class="topbar-brand">
      <span class="brand-title">Radar GPON</span>
      <span class="brand-sub">Conclusão #<?= $previewId ?></span>
    </div>
  </div>
  <div class="topbar-actions">
    <a href="<?= $base ?>/preventivo#lista-concluida" class="tbtn" title="Voltar para Concluídas">
      <i class="bi bi-arrow-left"></i><span>Concluídas</span>
    </a>
    <div class="user-badge">
      <i class="bi bi-person-circle"></i>
      <span><?= htmlspecialchars($user['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
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

        <!-- Hero: dados da preventiva concluída -->
        <div class="prev-hero">
          <div class="ph-title"><i class="bi bi-check-circle-fill"></i> <span id="hdr-gpon" class="mono"></span> <i class="bi bi-arrow-right" style="font-size:15px;opacity:.6"></i> <span id="hdr-splitter" class="mono"></span></div>
          <div class="ph-badges" id="hdr-badges"></div>
          <div class="ph-grid">
            <div class="ph-item"><div class="lbl">Localidade</div><div class="val" id="info-localidade">—</div></div>
            <div class="ph-item"><div class="lbl">Ocorrências</div><div class="val" id="info-ocorrencias">—</div></div>
            <div class="ph-item"><div class="lbl">Técnico da análise</div><div class="val" id="info-tecnico">—</div></div>
            <div class="ph-item"><div class="lbl">Iniciado em</div><div class="val" id="info-iniciado">—</div></div>
            <div class="ph-item"><div class="lbl">Concluído em</div><div class="val" id="info-concluido">—</div></div>
            <div class="ph-item"><div class="lbl">Status atendimento</div><div class="val" id="info-status-atend">—</div></div>
          </div>
        </div>

        <!-- Linha: Análise (descrição + imagens, lado a lado) -->
        <div class="prev-card-row">
          <div class="prev-card card-analise">
            <h6><i class="bi bi-journal-text"></i> Análise do técnico</h6>
            <div class="desc-rotulo">Descrição da análise</div>
            <div class="descricao" id="analise-descricao"><span class="text-muted">Sem descrição de análise.</span></div>
          </div>

          <div class="prev-card card-analise">
            <h6><i class="bi bi-camera"></i> Imagens da análise</h6>
            <div id="fotos-analise-grupos"><span class="text-muted" style="font-size:12px">Nenhuma imagem de análise registrada.</span></div>
          </div>
        </div>

        <!-- Linha: Conclusão (descrição + imagens lado a lado) -->
        <div class="prev-card-row">
          <div class="prev-card card-conclusao">
            <h6><i class="bi bi-check2-square"></i> Conclusão do técnico</h6>
            <div class="desc-rotulo">Descrição da conclusão</div>
            <div class="descricao descricao-concluida" id="conclusao-descricao"><span class="text-muted">Sem descrição de conclusão.</span></div>
          </div>

          <div class="prev-card card-conclusao">
            <h6><i class="bi bi-camera"></i> Imagens da conclusão</h6>
            <div id="fotos-conclusao-grupos"><span class="text-muted" style="font-size:12px">Nenhuma imagem de conclusão registrada.</span></div>
          </div>
        </div>

        <!-- Enviar para Revisão -->
        <div class="prev-card-conclusao-acoes">
          <button type="button" class="btn-enviar-revisao" id="btn-enviar-revisao">
            <i class="bi bi-send"></i> Enviar para Revisão
          </button>
        </div>

      </div>
    </div>
  </main>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= $base ?>/assets/js/preventivo/common.js?v=<?= filemtime(__DIR__ . '/../../assets/js/preventivo/common.js') ?>"></script>
<script src="<?= $base ?>/assets/js/preventivo/conclusao-detalhe.js?v=<?= filemtime(__DIR__ . '/../../assets/js/preventivo/conclusao-detalhe.js') ?>"></script>
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

  // Na página de Conclusão os painéis ficam em /preventivo: qualquer item do
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