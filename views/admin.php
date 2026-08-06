<?php
// ── HTML: Admin Panel ──────────────────────────────────────────
function gpon_render_admin(PDO $pdo, array $user): void
{
    $user = gpon_require_admin();
    $base = GPON_BASE_PATH;
    $imps = $pdo->query("SELECT * FROM importacoes ORDER BY created_at DESC LIMIT 10")->fetchAll();
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Radar GPON — Administração</title>
  <link rel="icon" type="image/svg+xml" href="<?= $base ?>/assets/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/gpon.css?v=<?= filemtime(__DIR__ . '/../assets/css/gpon.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
  <script>const BASE_PATH = '<?= $base ?>';</script>
</head>
<body>

<div id="flash-bar" class="flash-bar"></div>

<!-- ── TOPBAR ──────────────────────────────────────────────── -->
<header class="gpon-topbar">
  <div class="topbar-left">
    <button class="sidebar-toggle" id="btn-sidebar-toggle" title="Alternar menu lateral">
      <i class="bi bi-list"></i>
    </button>
    <div class="topbar-logo-icon" onclick="location.href='<?= $base ?>'" title="Radar GPON">📡</div>
    <div class="topbar-brand">
      <span class="brand-title">Radar GPON</span>
      <span class="brand-sub">Painel Administrativo</span>
    </div>
  </div>
  <div class="topbar-actions">
    <a href="<?= $base ?>/" class="tbtn" title="Voltar à Operação">
      <i class="bi bi-arrow-left"></i><span>Operação</span>
    </a>
    <div class="user-badge">
      <i class="bi bi-person-circle"></i>
      <span><?= htmlspecialchars($user['nome'], ENT_QUOTES, 'UTF-8') ?></span>
      <a href="<?= $base ?>/logout" class="logout-btn" title="Sair"><i class="bi bi-box-arrow-right"></i></a>
    </div>
  </div>
</header>

<!-- ── LAYOUT: sidebar + conteúdo ──────────────────────────── -->
<div class="gpon-layout">

  <!-- ── SIDEBAR: navegação administrativa ──────────────────── -->
  <aside class="gpon-sidebar admin-sidebar" id="admin-sidebar">
    <nav class="admin-nav" aria-label="Menu Administrativo">

      <div class="admin-nav-section">
        <span class="admin-nav-section-title">Administração</span>

        <a href="#mapeamento" class="admin-nav-item" data-panel="mapeamento">
          <i class="bi bi-diagram-3"></i>
          <span>Mapeamento OLTs</span>
        </a>

        <a href="#usuarios" class="admin-nav-item" data-panel="usuarios">
          <i class="bi bi-people"></i>
          <span>Gerenciar Usuários</span>
        </a>

        <a href="#importacoes" class="admin-nav-item" data-panel="importacoes">
          <i class="bi bi-upload"></i>
          <span>Últimas Importações</span>
        </a>
      </div>

      <div class="admin-nav-section admin-nav-soon">
        <span class="admin-nav-section-title">Em Breve</span>

        <span class="admin-nav-item disabled">
          <i class="bi bi-sliders"></i>
          <span>Configurações</span>
        </span>
        <span class="admin-nav-item disabled">
          <i class="bi bi-journal-text"></i>
          <span>Logs</span>
        </span>
        <span class="admin-nav-item disabled">
          <i class="bi bi-shield-check"></i>
          <span>Auditoria</span>
        </span>
      </div>

    </nav>
  </aside>

  <!-- ── CONTEÚDO PRINCIPAL ──────────────────────────────────── -->
  <main class="admin-main">

    <!-- ── PAINEL: Mapeamento GPON → Parceiras ──────────────── -->
    <div class="admin-panel" id="panel-mapeamento">

      <div class="admin-panel-header">
        <h5 class="admin-panel-title">
          <i class="bi bi-diagram-3"></i> Mapeamento OLTs → Parceiras
        </h5>
      </div>

      <div class="admin-card">
        <!-- Tabs: Mapeados / Não Mapeados -->
        <ul class="nav nav-tabs" style="font-size:13px;border-bottom:2px solid var(--gpon-border)">
          <li class="nav-item">
            <a class="nav-link active" href="#tab-gpon-mapeados" role="tab" data-bs-toggle="tab"
               style="color:var(--gpon-primary);font-weight:600">
              <i class="bi bi-check-circle"></i> Mapeados
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#tab-gpon-nao-mapeados" role="tab" data-bs-toggle="tab"
               style="color:var(--gpon-warning);font-weight:600">
              <i class="bi bi-exclamation-circle"></i> Não Mapeados
            </a>
          </li>
        </ul>

        <div class="tab-content" style="padding:16px 0">

          <!-- Tab: Mapeados -->
          <div role="tabpanel" class="tab-pane fade show active" id="tab-gpon-mapeados">

            <div id="uf-filter-bar" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px"></div>

            <div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
              <input type="text" id="gpon-search" class="form-control form-control-sm"
                     placeholder="Buscar OLTs..." style="max-width:220px">
              <button class="btn btn-primary btn-sm" id="btn-new-gpon">
                <i class="bi bi-plus"></i> Nova OLT
              </button>
            </div>

            <div style="overflow-x:auto">
              <table class="table table-sm" style="font-size:13px">
                <thead style="background:#f5f3ff">
                  <tr>
                    <th style="width:46px">UF</th>
                    <th>GPON</th>
                    <th>Empresa</th>
                    <th>Data</th>
                    <th style="width:100px">Ações</th>
                  </tr>
                </thead>
                <tbody id="gpon-list-tbody">
                  <tr><td colspan="5" class="text-center text-muted py-3">Carregando...</td></tr>
                </tbody>
              </table>
            </div>
            <nav style="margin-top:16px">
              <ul class="pagination pagination-sm" id="gpon-pagination" style="justify-content:center"></ul>
            </nav>
          </div>

          <!-- Tab: Não Mapeados -->
          <div role="tabpanel" class="tab-pane fade" id="tab-gpon-nao-mapeados">

            <div id="unmapped-kpi" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px"></div>

            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap">
              <button class="btn btn-sm btn-outline-secondary" id="btn-refresh-nao-mapeados">
                <i class="bi bi-arrow-clockwise"></i> Atualizar
              </button>
              <span id="unmapped-count" style="font-size:12px;color:var(--gpon-muted)"></span>
            </div>

            <div style="overflow-x:auto">
              <table class="table table-sm" style="font-size:13px">
                <thead style="background:#f5f3ff">
                  <tr>
                    <th style="width:46px">UF</th>
                    <th>GPON</th>
                    <th style="width:100px">Ação</th>
                  </tr>
                </thead>
                <tbody id="gpon-unmapped-tbody">
                  <tr><td colspan="3" class="text-center text-muted py-3">Carregando...</td></tr>
                </tbody>
              </table>
            </div>
            <nav style="margin-top:16px">
              <ul class="pagination pagination-sm" id="unmapped-pagination" style="justify-content:center"></ul>
            </nav>
          </div>

        </div><!-- /tab-content -->
      </div><!-- /admin-card -->

    </div><!-- /panel-mapeamento -->


    <!-- ── PAINEL: Gerenciar Usuários ────────────────────────── -->
    <div class="admin-panel d-none" id="panel-usuarios">

      <div class="admin-panel-header">
        <h5 class="admin-panel-title">
          <i class="bi bi-people"></i> Gerenciar Usuários
        </h5>
        <button class="tbtn primary" id="btn-new-user" style="padding:7px 14px;border-radius:6px">
          <i class="bi bi-plus"></i> Novo Usuário
        </button>
      </div>

      <div class="admin-card">
        <div style="overflow-x:auto">
          <table class="table table-sm" style="font-size:13px">
            <thead style="background:#f5f3ff">
              <tr>
                <th>Nome</th>
                <th>Usuário</th>
                <th>Nível</th>
                <th>Status</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody id="users-tbody">
              <tr><td colspan="5" class="text-center text-muted py-3">Carregando...</td></tr>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- /panel-usuarios -->


    <!-- ── PAINEL: Últimas Importações ──────────────────────── -->
    <div class="admin-panel d-none" id="panel-importacoes">

      <div class="admin-panel-header">
        <h5 class="admin-panel-title">
          <i class="bi bi-upload"></i> Últimas Importações
        </h5>
      </div>

      <div class="admin-card">
        <?php if (empty($imps)): ?>
          <div class="empty-state">
            <i class="bi bi-inbox"></i>
            <p>Nenhuma importação registrada</p>
          </div>
        <?php else: ?>
        <div style="overflow-x:auto">
          <table class="table table-sm" style="font-size:12px">
            <thead style="background:#f5f3ff">
              <tr>
                <th>Data</th>
                <th>Arquivo</th>
                <th>Total</th>
                <th>Inseridos</th>
                <th>Atualizados</th>
                <th>Erros</th>
                <th>Usuário</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($imps as $imp): ?>
              <tr>
                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($imp['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($imp['arquivo'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= $imp['total_linhas'] ?></td>
                <td><span class="badge-status badge-dentro-prazo"><?= $imp['inseridos'] ?></span></td>
                <td><span class="badge-status badge-andamento"><?= $imp['atualizados'] ?></span></td>
                <td><?= $imp['erros'] > 0 ? '<span class="badge-status badge-fora-prazo">' . $imp['erros'] . '</span>' : '0' ?></td>
                <td><?= htmlspecialchars($imp['usuario_nome'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

    </div><!-- /panel-importacoes -->

  </main><!-- /admin-main -->

</div><!-- /gpon-layout -->


<!-- ── MODAL: Usuário ──────────────────────────────────────── -->
<div class="modal fade" id="modalUser" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="user-modal-title"><i class="bi bi-person-plus"></i> Usuário</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="form-user">
          <div class="row g-3">
            <div class="col-12">
              <label class="modal-label">Nome completo</label>
              <input type="text" id="uform-nome" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-6">
              <label class="modal-label">Login (usuário)</label>
              <input type="text" id="uform-usuario" class="form-control form-control-sm" required autocomplete="off">
            </div>
            <div class="col-md-6">
              <label class="modal-label">Nível</label>
              <select id="uform-nivel" class="form-select form-select-sm">
                <option value="operador">Operador</option>
                <option value="backoffice">Backoffice</option>
                <option value="supervisor">Supervisor</option>
                <option value="tecnico">Técnico</option>
                <option value="admin">Administrador</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="modal-label">Status</label>
              <select id="uform-status" class="form-select form-select-sm">
                <option value="1">Ativo</option>
                <option value="0">Inativo</option>
              </select>
            </div>
            <div class="col-12" id="user-senha-row">
              <label class="modal-label">Senha <span style="color:var(--gpon-muted)">(deixe vazio para manter)</span></label>
              <input type="password" id="uform-senha" class="form-control form-control-sm" autocomplete="new-password">
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary btn-sm" id="btn-save-user">
          <i class="bi bi-save"></i> Salvar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ── MODAL: Novo / Editar GPON ───────────────────────────── -->
<div class="modal fade" id="modalGponForm" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#5b21b6,#7c3aed)">
        <h5 class="modal-title" style="color:#fff" id="gpon-modal-title">Novo ARD</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="gpon-form">
          <div class="mb-3">
            <label class="form-label">GPON</label>
            <input type="text" id="gpon-input" class="form-control form-control-sm"
                   placeholder="Ex: DFBSA_G1M09" style="text-transform:uppercase" maxlength="100">
            <div id="gpon-error" style="font-size:12px;color:#dc2626;margin-top:4px"></div>
          </div>
          <div class="mb-3">
            <label class="form-label">Empresa</label>
            <select id="gpon-empresa" class="form-select form-select-sm">
              <option value="">-- Selecione --</option>
              <option value="ABILITY">ABILITY</option>
              <option value="ONDACOM">ONDACOM</option>
              <option value="VIVO">VIVO</option>
            </select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary btn-sm" id="btn-save-gpon">
          <i class="bi bi-save"></i> Salvar
        </button>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= $base ?>/assets/js/gpon.js?v=<?= filemtime(__DIR__ . '/../assets/js/gpon.js') ?>"></script>

<script>
// ── Admin: sidebar + navegação entre painéis ────────────────
(function () {
  const sidebar = document.getElementById('admin-sidebar');
  const btnToggle = document.getElementById('btn-sidebar-toggle');

  if (btnToggle && sidebar) {
    let saved = null;
    try { saved = localStorage.getItem('gpon_admin_sidebar'); } catch (e) {}
    if (saved === 'collapsed') sidebar.classList.add('collapsed');

    btnToggle.addEventListener('click', () => {
      sidebar.classList.toggle('collapsed');
      try {
        localStorage.setItem('gpon_admin_sidebar',
          sidebar.classList.contains('collapsed') ? 'collapsed' : 'open');
      } catch (e) {}
    });
  }

  function activatePanel(key) {
    document.querySelectorAll('.admin-panel').forEach(p => p.classList.add('d-none'));
    const panel = document.getElementById('panel-' + key);
    if (panel) panel.classList.remove('d-none');

    document.querySelectorAll('.admin-nav-item[data-panel]').forEach(item => {
      item.classList.toggle('active', item.dataset.panel === key);
    });

    try { history.replaceState(null, '', '#' + key); } catch (e) {}
  }

  document.querySelectorAll('.admin-nav-item[data-panel]').forEach(item => {
    item.addEventListener('click', e => {
      e.preventDefault();
      activatePanel(item.dataset.panel);
    });
  });

  const initial = location.hash.replace('#', '') || 'mapeamento';
  const validPanels = ['mapeamento', 'usuarios', 'importacoes'];
  activatePanel(validPanels.includes(initial) ? initial : 'mapeamento');
})();

// ── Admin: Mapeamento GPON ──────────────────────────────────
(function () {
  const flash = (...a) => GPON.flash(...a);

  let gponEditId  = null;
  let gponPage    = 1;
  let gponUf      = '';

  let unmappedAll  = [];
  let unmappedPage = 1;
  const UNMAPPED_PER_PAGE = 20;

  const UF_LIST = ['MT', 'MS', 'DF', 'GO'];

  function gponToUf(gpon) {
    return (gpon || '').substring(0, 2).toUpperCase();
  }

  const UF_COLORS = {
    MT: '#d1fae5:#065f46', MS: '#dbeafe:#1e40af',
    DF: '#fef3c7:#92400e', GO: '#ede9fe:#5b21b6',
  };
  function ufBadge(uf) {
    const [bg, color] = (UF_COLORS[uf] || '#f3f4f6:#374151').split(':');
    return `<span style="display:inline-block;padding:1px 7px;border-radius:4px;font-size:11px;font-weight:700;background:${bg};color:${color}">${uf}</span>`;
  }

  function renderPagination(containerId, totalPages, currentPage, onPage) {
    const el = document.getElementById(containerId);
    if (!el) return;
    el.innerHTML = '';
    if (totalPages <= 1) return;
    for (let p = 1; p <= totalPages; p++) {
      const li = document.createElement('li');
      li.className = 'page-item' + (p === currentPage ? ' active' : '');
      const a = document.createElement('a');
      a.className = 'page-link';
      a.href = '#';
      a.textContent = p;
      a.addEventListener('click', (e) => { e.preventDefault(); onPage(p); });
      li.appendChild(a);
      el.appendChild(li);
    }
  }

  function renderUfFilters(ufCounts) {
    const bar = document.getElementById('uf-filter-bar');
    if (!bar) return;
    const total = Object.values(ufCounts).reduce((s, v) => s + v, 0);
    const items = [
      { key: '', label: 'TODOS', count: total },
      ...UF_LIST.map(u => ({ key: u, label: u, count: ufCounts[u] || 0 })),
    ];
    bar.innerHTML = '';
    items.forEach(({ key, label, count }) => {
      const btn = document.createElement('button');
      const active = gponUf === key;
      btn.className = 'btn btn-sm ' + (active ? 'btn-primary' : 'btn-outline-secondary');
      btn.style.cssText = 'display:flex;align-items:center;gap:5px;font-size:12px';
      btn.innerHTML = `${label}<span style="background:${active ? 'rgba(255,255,255,.25)' : '#e5e7eb'};color:${active ? '#fff' : '#374151'};padding:0 5px;border-radius:3px;font-size:11px;font-weight:700">${count}</span>`;
      btn.addEventListener('click', () => { gponUf = key; loadGponList(1); });
      bar.appendChild(btn);
    });
  }

  async function loadGponList(page = 1) {
    const search = document.getElementById('gpon-search')?.value || '';
    try {
      const url  = `${BASE_PATH}/api/admin/gpon-empresas?page=${page}&search=${encodeURIComponent(search)}&uf=${encodeURIComponent(gponUf)}`;
      const res  = await fetch(url);
      const data = await res.json();
      if (!data.ok) throw new Error(data.message);

      if (data.uf_counts) renderUfFilters(data.uf_counts);

      const empty = gponUf
        ? `Nenhum GPON encontrado para <strong>${gponUf}</strong>`
        : 'Nenhum GPON cadastrado';

      document.getElementById('gpon-list-tbody').innerHTML = data.items.length
        ? data.items.map(item => {
            const uf = gponToUf(item.gpon);
            return `
            <tr>
              <td>${ufBadge(uf)}</td>
              <td><strong>${item.gpon}</strong></td>
              <td>${item.empresa}</td>
              <td style="font-size:11px;color:var(--gpon-muted)">${new Date(item.criado_em).toLocaleDateString('pt-BR')}</td>
              <td>
                <button class="btn btn-sm btn-outline-primary" onclick="editGpon(${item.id},'${item.gpon}','${item.empresa}')"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-outline-danger"  onclick="deleteGpon(${item.id},'${item.gpon}','${item.empresa}')"><i class="bi bi-trash"></i></button>
              </td>
            </tr>`;
          }).join('')
        : `<tr><td colspan="5" class="text-center text-muted py-3">${empty}</td></tr>`;

      renderPagination('gpon-pagination', data.pages, page, (p) => loadGponList(p));
      gponPage = page;
    } catch (e) {
      flash('Erro ao carregar lista: ' + e.message, 'error');
    }
  }

  window.editGpon = function (id, gpon, empresa) {
    gponEditId = id;
    document.getElementById('gpon-modal-title').textContent = 'Editar GPON';
    document.getElementById('gpon-input').value    = gpon;
    document.getElementById('gpon-input').disabled = true;
    document.getElementById('gpon-empresa').value  = empresa;
    new bootstrap.Modal(document.getElementById('modalGponForm')).show();
  };

  window.deleteGpon = function (id, gpon, empresa) {
    Swal.fire({
      title: 'Excluir GPON',
      html: `
        <p style="margin:0 0 10px;font-size:14px;text-align:left;line-height:1.9">
          <strong>GPON:</strong>&nbsp;<code style="font-size:13px;background:#f3f4f6;padding:1px 6px;border-radius:4px">${gpon}</code><br>
          <strong>Empresa:</strong>&nbsp;${empresa}
        </p>
        <p style="margin:0;font-size:14px;text-align:left;color:#374151">
          Deseja realmente remover este mapeamento?
        </p>`,
      footer: '<span style="font-size:12px;color:#9ca3af">Esta ação não poderá ser desfeita.</span>',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Excluir',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#dc2626',
      cancelButtonColor: '#6b7280',
      reverseButtons: true,
      focusCancel: true,
    }).then(result => {
      if (!result.isConfirmed) return;
      fetch(`${BASE_PATH}/api/admin/gpon-empresas/${id}`, { method: 'DELETE' })
        .then(r => r.json())
        .then(d => {
          if (d.ok) { flash('Mapeamento removido com sucesso', 'success'); loadGponList(gponPage); loadUnmappedGpons(); }
          else flash('Erro: ' + d.message, 'error');
        })
        .catch(e => flash('Erro ao remover: ' + e.message, 'error'));
    });
  };

  let unmappedUf = '';

  function renderUnmappedKpi() {
    const bar = document.getElementById('unmapped-kpi');
    if (!bar) return;
    bar.innerHTML = '';

    const counts = {};
    unmappedAll.forEach(g => {
      const u = gponToUf(g);
      counts[u] = (counts[u] || 0) + 1;
    });

    const ufsComPendencia = UF_LIST.filter(u => (counts[u] || 0) > 0);
    const outras          = Object.keys(counts).filter(u => !UF_LIST.includes(u));
    const todasUfs        = [...ufsComPendencia, ...outras];
    const total           = unmappedAll.length;

    if (total === 0) {
      bar.innerHTML = `
        <i class="bi bi-check-circle-fill" style="color:#059669;font-size:15px"></i>
        <span style="font-size:13px;color:#059669;font-weight:600">Todos os GPONs estão mapeados!</span>`;
      return;
    }

    function urgencyStyle(n) {
      if (n <= 10) return 'background:#fde047;color:#854d0e';
      if (n <= 20) return 'background:#fb923c;color:#7c2d12';
      return 'background:#fca5a5;color:#991b1b';
    }

    function makeFilterBtn(label, count, isActive, onClick) {
      const btn        = document.createElement('button');
      const badgeStyle = isActive ? 'background:rgba(255,255,255,.25);color:#fff' : urgencyStyle(count);
      btn.className    = 'btn btn-sm ' + (isActive ? 'btn-primary' : 'btn-outline-secondary');
      btn.style.cssText = 'display:flex;align-items:center;gap:5px;font-size:12px';
      btn.innerHTML    = `${label}<span style="${badgeStyle};padding:0 5px;border-radius:3px;font-size:11px;font-weight:700">${count}</span>`;
      btn.addEventListener('click', onClick);
      return btn;
    }

    bar.appendChild(makeFilterBtn('TODOS', total, unmappedUf === '', () => filterUnmapped('')));
    todasUfs.forEach(u => bar.appendChild(makeFilterBtn(u, counts[u] || 0, unmappedUf === u, () => filterUnmapped(u))));
  }

  window.filterUnmapped = function (uf) {
    unmappedUf   = uf;
    unmappedPage = 1;
    renderUnmappedKpi();
    renderUnmappedPage(1);
  };

  async function loadUnmappedGpons() {
    const tbody = document.getElementById('gpon-unmapped-tbody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-2">Carregando...</td></tr>';
    try {
      const res  = await fetch(`${BASE_PATH}/api/gpon-nao-mapeados`);
      const data = await res.json();
      if (!data.ok) throw new Error(data.message);
      unmappedAll  = data.gpons || [];
      unmappedUf   = '';
      unmappedPage = 1;
      renderUnmappedKpi();
      renderUnmappedPage(1);
    } catch (e) {
      flash('Erro ao carregar não mapeados: ' + e.message, 'error');
    }
  }

  function renderUnmappedPage(page) {
    const filtered  = unmappedUf
      ? unmappedAll.filter(g => gponToUf(g) === unmappedUf)
      : unmappedAll;

    const start    = (page - 1) * UNMAPPED_PER_PAGE;
    const slice    = filtered.slice(start, start + UNMAPPED_PER_PAGE);
    const totalPgs = Math.ceil(filtered.length / UNMAPPED_PER_PAGE);
    const tbody    = document.getElementById('gpon-unmapped-tbody');
    const countEl  = document.getElementById('unmapped-count');

    if (countEl) {
      const n   = filtered.length;
      const sfx = unmappedUf ? ` em ${unmappedUf}` : '';
      countEl.textContent = n
        ? `${n} GPON${n > 1 ? 's' : ''} sem mapeamento${sfx}`
        : '';
    }

    tbody.innerHTML = slice.length
      ? slice.map(gpon => `
        <tr>
          <td>${ufBadge(gponToUf(gpon))}</td>
          <td><strong>${gpon}</strong></td>
          <td>
            <button class="btn btn-sm btn-primary" onclick="mapGpon('${gpon}')">
              <i class="bi bi-plus-circle"></i> Mapear
            </button>
          </td>
        </tr>`).join('')
      : '<tr><td colspan="3" class="text-center text-muted py-3">Nenhuma pendência nesta UF.</td></tr>';

    renderPagination('unmapped-pagination', totalPgs, page, (p) => {
      unmappedPage = p;
      renderUnmappedPage(p);
    });
    unmappedPage = page;
  }

  window.mapGpon = function (gpon) {
    gponEditId = null;
    document.getElementById('gpon-modal-title').textContent = 'Mapear OLTs';
    document.getElementById('gpon-input').value    = gpon;
    document.getElementById('gpon-input').disabled = true;
    document.getElementById('gpon-empresa').value  = '';
    document.getElementById('gpon-error').textContent = '';
    new bootstrap.Modal(document.getElementById('modalGponForm')).show();
  };

  document.getElementById('btn-new-gpon')?.addEventListener('click', () => {
    gponEditId = null;
    document.getElementById('gpon-modal-title').textContent = 'Nova OLT';
    document.getElementById('gpon-input').value    = '';
    document.getElementById('gpon-input').disabled = false;
    document.getElementById('gpon-empresa').value  = '';
    document.getElementById('gpon-error').textContent = '';
    new bootstrap.Modal(document.getElementById('modalGponForm')).show();
  });

  document.getElementById('btn-save-gpon')?.addEventListener('click', async () => {
    const gpon    = document.getElementById('gpon-input').value.toUpperCase().trim();
    const empresa = document.getElementById('gpon-empresa').value;
    const errorEl = document.getElementById('gpon-error');
    errorEl.textContent = '';

    if (!gpon || !empresa) { errorEl.textContent = 'Preencha todos os campos'; return; }

    const method  = gponEditId ? 'PUT'  : 'POST';
    const url     = gponEditId
      ? `${BASE_PATH}/api/admin/gpon-empresas/${gponEditId}`
      : `${BASE_PATH}/api/admin/gpon-empresas`;
    const payload = gponEditId ? { empresa } : { gpon, empresa };

    try {
      const res  = await fetch(url, { method, body: JSON.stringify(payload), headers: { 'Content-Type': 'application/json' } });
      const data = await res.json();
      if (data.ok) {
        flash(data.message, 'success');
        bootstrap.Modal.getInstance(document.getElementById('modalGponForm')).hide();
        loadGponList(gponPage);
        loadUnmappedGpons();
      } else {
        errorEl.textContent = data.message;
      }
    } catch (e) {
      errorEl.textContent = 'Erro: ' + e.message;
    }
  });

  document.getElementById('gpon-search')
    ?.addEventListener('keyup', () => loadGponList(1));

  document.getElementById('btn-refresh-nao-mapeados')
    ?.addEventListener('click', () => loadUnmappedGpons());

  loadGponList(1);
  loadUnmappedGpons();
})();
</script>
</body>
</html>
    <?php
}
