<?php
/**
 * Listagem de Preventivas de Rede.
 * Requerido diretamente por index.php nas rotas /preventivo e /preventiva.
 * Variáveis disponíveis no escopo: $pdo, $user (definidas em index.php).
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
  <script>const BASE_PATH = '<?= $base ?>';</script>
</head>
<body>
<div id="flash-bar" class="flash-bar"></div>

<header class="gpon-topbar">
  <div class="topbar-left">
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

<div style="margin-top:var(--gpon-header-h); padding:20px">

  <div class="table-card" style="margin-bottom:16px;padding:14px 16px">
    <div id="status-filter-bar" style="display:flex;gap:6px;flex-wrap:wrap"></div>
  </div>

  <div class="table-card">
    <div class="table-card-header">
      <div class="table-card-title"><i class="bi bi-shield-check"></i> Preventivas de Rede</div>
    </div>
    <table id="tbl-preventivas" class="display" style="width:100%">
      <thead>
        <tr>
          <th>GPON</th><th>Splitter</th><th>Localidade</th><th>Status</th>
          <th>Prioridade</th><th>Supervisor</th><th>Técnico</th>
          <th>Ocorrências</th><th>Criado em</th><th></th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>

</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="<?= $base ?>/assets/js/preventivo/common.js?v=<?= filemtime(__DIR__ . '/../../assets/js/preventivo/common.js') ?>"></script>
<script>
(function () {
  'use strict';

  var STATUS_LABELS     = PreventivoCommon.STATUS_LABELS;
  var STATUS_COLORS     = PreventivoCommon.STATUS_COLORS;
  var PRIORIDADE_COLORS = PreventivoCommon.PRIORIDADE_COLORS;
  var esc     = PreventivoCommon.esc;
  var fmtDate = PreventivoCommon.fmtDate;

  function badge(label, colors) {
    return PreventivoCommon.badge(label, colors, { padding: '2px 8px', fontSize: '11px' });
  }

  var allRows = [];
  var activeStatus = '';
  var dt = null;

  function renderStatusFilters() {
    var bar = document.getElementById('status-filter-bar');
    var counts = {};
    allRows.forEach(function (r) { counts[r.status] = (counts[r.status] || 0) + 1; });
    var items = [{ key: '', label: 'Todas', count: allRows.length }].concat(
      Object.keys(STATUS_LABELS).map(function (k) { return { key: k, label: STATUS_LABELS[k], count: counts[k] || 0 }; })
    );
    bar.innerHTML = '';
    items.forEach(function (item) {
      var active = activeStatus === item.key;
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-sm ' + (active ? 'btn-primary' : 'btn-outline-secondary');
      btn.style.cssText = 'display:flex;align-items:center;gap:5px;font-size:12px';
      btn.innerHTML = esc(item.label) + '<span style="background:' + (active ? 'rgba(255,255,255,.25)' : '#e5e7eb') + ';color:' + (active ? '#fff' : '#374151') + ';padding:0 5px;border-radius:3px;font-size:11px;font-weight:700">' + item.count + '</span>';
      btn.addEventListener('click', function () { activeStatus = item.key; applyFilter(); renderStatusFilters(); });
      bar.appendChild(btn);
    });
  }

  function applyFilter() {
    var rows = activeStatus ? allRows.filter(function (r) { return r.status === activeStatus; }) : allRows;
    if (dt) { dt.clear().rows.add(rows).draw(); }
  }

  function loadPreventivas() {
    fetch(BASE_PATH + '/api/preventiva', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.ok) return;
        allRows = res.data || [];
        renderStatusFilters();

        dt = $('#tbl-preventivas').DataTable({
          data: allRows, pageLength: 25, order: [[8, 'desc']],
          language: { url: BASE_PATH + '/assets/js/pt-BR.json' },
          columns: [
            { data: 'gpon', render: function (d) { return '<span class="mono" style="font-size:12px">' + esc(d) + '</span>'; } },
            { data: 'splitter', render: function (d) { return '<span class="mono" style="font-size:12px">' + esc(d) + '</span>'; } },
            { data: null, render: function (_, __, row) { return esc(row.localidade || row.uf || '—'); } },
            { data: 'status', render: function (d) { return badge(STATUS_LABELS[d] || d, STATUS_COLORS[d]); } },
            { data: 'prioridade', render: function (d) { return badge((d || 'media').charAt(0).toUpperCase() + (d || 'media').slice(1), PRIORIDADE_COLORS[d]); } },
            { data: 'supervisor_nome', render: function (d) { return d ? esc(d) : '<span class="text-muted">—</span>'; } },
            { data: 'tecnico_nome', render: function (d) { return d ? esc(d) : '<span class="text-muted">—</span>'; } },
            { data: 'origem_total_ocorrencias', render: function (d) { return d || 0; } },
            { data: 'criado_em', render: function (d) { return '<span style="font-size:11px">' + fmtDate(d) + '</span>'; } },
            { data: null, orderable: false, render: function (_, __, row) {
                return '<a href="' + BASE_PATH + '/preventivo/' + row.id + '" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-right-circle"></i> Abrir</a>';
              }
            },
          ],
        });
      })
      .catch(function () {});
  }

  loadPreventivas();
})();
</script>
</body>
</html>
