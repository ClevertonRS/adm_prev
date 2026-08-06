<?php
// ── HTML: Dashboard Principal ──────────────────────────────────
function gpon_render_dashboard(PDO $pdo, array $user): void
{
    // Requer autenticação — redireciona para login se não estiver logado
    $user = gpon_require_login();
    
    $base               = GPON_BASE_PATH;
    $isAdmin            = gpon_is_admin();
    $canAnalise         = gpon_is_admin_or_backoffice();
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
  <link rel="stylesheet" href="https://cdn.datatables.net/fixedcolumns/4.3.0/css/fixedColumns.dataTables.min.css">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/gpon.css?v=<?= filemtime(__DIR__ . '/../assets/css/gpon.css') ?>">
  <script>const BASE_PATH = '<?= $base ?>';</script>
</head>
<body>

<!-- ── FLASH BAR ───────────────────────────────────────────── -->
<div id="flash-bar" class="flash-bar"></div>

<!-- ── TOPBAR ──────────────────────────────────────────────── -->
<header class="gpon-topbar">
  <div class="topbar-left">
    <button class="sidebar-toggle" id="btn-sidebar-toggle" title="Alternar filtros">
      <i class="bi bi-list"></i>
    </button>
    <div class="topbar-logo-icon" onclick="location.href='<?= $base ?>'" title="Painel GPON">📡</div>
    <div class="topbar-brand">
      <span class="brand-title">Radar GPON</span>
      <span class="brand-sub">Gestão de Ocorrências</span>
    </div>
  </div>

  <div class="topbar-actions">
    <?php $ua = gpon_ultima_atualizacao($pdo); ?>
    <div class="topbar-last-update" id="topbar-last-update" title="Última importação de dados">
      <i class="bi bi-clock-history"></i>
      <span id="topbar-ua-text"><?= $ua ? 'Atualizado: ' . $ua : 'Sem importações' ?></span>
    </div>

    <?php if ($canAnalise): ?>
    <a href="<?= $base ?>/analise" class="tbtn" title="Análise de Reincidência de Splitters">
      <i class="bi bi-graph-up-arrow"></i><span>Análise</span>
    </a>
    <?php endif; ?>

    <a href="<?= $base ?>/preventivo" class="tbtn" title="Preventivas de Rede">
      <i class="bi bi-shield-check"></i><span>Preventivas</span>
    </a>

    <button class="tbtn" data-bs-toggle="modal" data-bs-target="#modalUpload" title="Importar planilha .xlsx">
      <i class="bi bi-upload"></i><span>Importar</span>
    </button>


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

<!-- ── LAYOUT ──────────────────────────────────────────────── -->
<div class="gpon-layout">

  <!-- SIDEBAR / FILTROS -->
  <aside class="gpon-sidebar" id="gpon-sidebar">

    <div class="sf-header">
      <span class="sf-header-title"><i class="bi bi-funnel"></i> Filtros</span>
      <button class="sf-clear-all" id="sf-clear-all" title="Limpar todos os filtros">
        <i class="bi bi-x-circle"></i>
      </button>
    </div>

    <div class="sf-cards">

      <div class="sf-card" data-sf-key="uf">
        <button class="sf-card-header" type="button">
          <i class="bi bi-map sf-card-icon"></i>
          <span class="sf-card-title">UF</span>
          <span class="sf-badge" id="sf-badge-uf"></span>
          <i class="bi bi-chevron-down sf-chevron"></i>
        </button>
        <div class="sf-card-body" id="sf-body-uf">
          <div class="sf-list" id="sf-list-uf"></div>
        </div>
      </div>

      <div class="sf-card" data-sf-key="empresa">
        <button class="sf-card-header" type="button">
          <i class="bi bi-building sf-card-icon"></i>
          <span class="sf-card-title">Empresa</span>
          <span class="sf-badge" id="sf-badge-empresa"></span>
          <i class="bi bi-chevron-down sf-chevron"></i>
        </button>
        <div class="sf-card-body" id="sf-body-empresa">
          <div class="sf-search-wrap">
            <i class="bi bi-search"></i>
            <input class="sf-search" type="text" placeholder="Pesquisar empresa…" data-sf-list="sf-list-empresa" autocomplete="off">
          </div>
          <div class="sf-list" id="sf-list-empresa"></div>
        </div>
      </div>

      <div class="sf-card" data-sf-key="localidade">
        <button class="sf-card-header" type="button">
          <i class="bi bi-geo-alt sf-card-icon"></i>
          <span class="sf-card-title">Localidade</span>
          <span class="sf-badge" id="sf-badge-localidade"></span>
          <i class="bi bi-chevron-down sf-chevron"></i>
        </button>
        <div class="sf-card-body" id="sf-body-localidade">
          <div class="sf-search-wrap">
            <i class="bi bi-search"></i>
            <input class="sf-search" type="text" placeholder="Pesquisar cidade…" data-sf-list="sf-list-localidade" autocomplete="off">
          </div>
          <div class="sf-list" id="sf-list-localidade"></div>
        </div>
      </div>

      <div class="sf-card" data-sf-key="gpon">
        <button class="sf-card-header" type="button">
          <i class="bi bi-hdd-network sf-card-icon"></i>
          <span class="sf-card-title">OLTs</span>
          <span class="sf-badge" id="sf-badge-gpon"></span>
          <i class="bi bi-chevron-down sf-chevron"></i>
        </button>
        <div class="sf-card-body" id="sf-body-gpon">
          <div class="sf-search-wrap">
            <i class="bi bi-search"></i>
            <input class="sf-search" type="text" placeholder="Pesquisar OLTs…" data-sf-list="sf-list-gpon" autocomplete="off">
          </div>
          <div class="sf-list" id="sf-list-gpon"></div>
        </div>
      </div>

      <div class="sf-card" data-sf-key="status_prazo">
        <button class="sf-card-header" type="button">
          <i class="bi bi-clock-history sf-card-icon"></i>
          <span class="sf-card-title">Status Prazo</span>
          <span class="sf-badge" id="sf-badge-sp"></span>
          <i class="bi bi-chevron-down sf-chevron"></i>
        </button>
        <div class="sf-card-body" id="sf-body-sp">
          <div class="sf-list" id="sf-list-sp"></div>
        </div>
      </div>

    </div><!-- /sf-cards -->

    <div class="sf-footer">
      <div class="sf-active-info" id="sf-active-info">Nenhum filtro ativo</div>
    </div>

  </aside><!-- /sidebar -->

  <!-- MAIN -->
  <main class="gpon-main">

    <!-- KPI CARDS -->
    <div class="kpi-grid">
      <div class="kpi-card" data-type="abertas">
        <i class="bi bi-exclamation-circle kpi-icon"></i>
        <span class="kpi-label">Abertas</span>
        <span class="kpi-value" id="kpi-abertas">—</span>
        <span class="kpi-sub">Ocorrências em aberto</span>
      </div>
      <div class="kpi-card" data-type="empresas">
        <i class="bi bi-building kpi-icon"></i>
        <span class="kpi-label">Empresas</span>
        <span class="kpi-value" id="kpi-empresas-total">—</span>
        <div id="kpi-empresas" class="kpi-empresa-list"><span class="kpi-empresa-loading">—</span></div>
      </div>
      <div class="kpi-card kpi-card-filterable" data-type="atraso" data-sla-filter="Fora do Prazo" title="Filtrar por Fora do Prazo">
        <i class="bi bi-x-circle kpi-icon"></i>
        <span class="kpi-label">Fora do Prazo</span>
        <span class="kpi-value" id="kpi-atraso">—</span>
        <span class="kpi-sub">SLA excedido</span>
      </div>
      <div class="kpi-card kpi-card-filterable" data-type="prazo" data-sla-filter="Dentro do Prazo" title="Filtrar por Dentro do Prazo">
        <i class="bi bi-clock-history kpi-icon"></i>
        <span class="kpi-label">Dentro do Prazo</span>
        <span class="kpi-value" id="kpi-prazo">—</span>
        <span class="kpi-sub">Operação estável</span>
      </div>
      <div class="kpi-card kpi-card-filterable" data-type="proximo" data-sla-filter="Atenção" title="Filtrar por Atenção">
        <i class="bi bi-hourglass-split kpi-icon"></i>
        <span class="kpi-label">Atenção</span>
        <span class="kpi-value" id="kpi-proximo">—</span>
        <span class="kpi-sub">Próximo do vencimento</span>
      </div>
      <div class="kpi-card" data-type="repetidas">
        <i class="bi bi-arrow-repeat kpi-icon"></i>
        <div id="kpi-reinc" class="kpi-reinc-body"><span class="kpi-reinc-loading">—</span></div>
      </div>
    </div>

    <!-- TABELA PRINCIPAL -->
    <div class="table-card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px">
        <div class="table-card-title">
          <i class="bi bi-table"></i> Ocorrências GPON
          <span class="title-uf-badge" id="table-uf-badge" style="display:none"></span>
        </div>
        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
          <span style="font-size:10px;color:var(--gpon-muted);font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-right:2px">Status Prazo:</span>
          <span class="badge-status badge-dentro-prazo"><i class="bi bi-circle-fill" style="font-size:6px"></i> Dentro do Prazo</span>
          <span class="badge-status badge-proximo-prazo"><i class="bi bi-circle-fill" style="font-size:6px"></i> Atenção</span>
          <span class="badge-status badge-fora-prazo"><i class="bi bi-circle-fill" style="font-size:6px"></i> Fora do Prazo</span>
        </div>
      </div>
      <div id="reinc-period-toolbar" class="reinc-period-toolbar">
        <span class="reinc-period-label"><i class="bi bi-repeat"></i> Reinc.:</span>
        <button class="reinc-period-btn" data-period="">Todos</button>
        <button class="reinc-period-btn" data-period="7d">7d</button>
        <button class="reinc-period-btn" data-period="15d">15d</button>
        <button class="reinc-period-btn active" data-period="30d">30d</button>
        <i class="bi bi-arrow-repeat reinc-period-spin" id="reinc-period-spin" aria-hidden="true"></i>
      </div>

      <div style="overflow-x:auto">
        <table id="gpon-table" class="display" style="width:100%"></table>
      </div>
    </div>

  </main>
</div><!-- /layout -->

<!-- ── POPUP: RESUMO DAS OCORRENCIAS EPS ────────────────────────────── -->
<div id="emp-popup-backdrop" style="display:none"></div>
<div id="popup-empresas" class="emp-popup" style="display:none" role="dialog" aria-modal="true" aria-labelledby="emp-popup-title">
  <div class="emp-popup-header">
    <span class="emp-popup-title" id="emp-popup-title">
      <i class="bi bi-building"></i> RESUMO DAS OCORRÊNCIAS EPS
    </span>
    <div class="emp-popup-actions">
      <button class="emp-popup-btn" id="emp-popup-copy" title="Copiar conteúdo">
        <i class="bi bi-clipboard"></i> Copiar
      </button>
      <button class="emp-popup-btn emp-popup-close-btn" id="emp-popup-close" title="Fechar">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
  </div>
  <div class="emp-popup-body" id="emp-popup-body"></div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: VISUALIZAR
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalView" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-eye"></i> Detalhes da Ocorrência</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="detail-grid three" style="margin-bottom:14px">
          <div class="detail-item">
            <span class="modal-label">OC</span>
            <span class="modal-value mono fw-700" id="view-oc">—</span>
          </div>
          <div class="detail-item">
            <span class="modal-label">TA</span>
            <span class="modal-value mono" id="view-ta">—</span>
          </div>
          <div class="detail-item">
            <span class="modal-label">Status</span>
            <span id="view-status">—</span>
          </div>
        </div>
        <div class="detail-grid three" style="margin-bottom:14px">
          <div class="detail-item">
            <span class="modal-label">Status Prazo</span>
            <span id="view-sp">—</span>
          </div>
          <div class="detail-item">
            <span class="modal-label">Repetida</span>
            <span id="view-repetida">—</span>
          </div>
          <div class="detail-item">
            <span class="modal-label">Gpon</span>
            <span class="modal-value mono" id="view-gpon">—</span>
          </div>
        </div>
        <div class="detail-grid four" style="margin-bottom:14px">
          <div class="detail-item">
            <span class="modal-label">Data Criação</span>
            <span class="modal-value" id="view-criacao">—</span>
          </div>
          <div class="detail-item">
            <span class="modal-label">Tempo</span>
            <span id="view-aging-a">—</span>
          </div>
          <div class="detail-item">
            <span class="modal-label">Splitters</span>
            <span class="modal-value mono" id="view-splitters">—</span>
          </div>
          <div class="detail-item">
            <span class="modal-label">Afetação</span>
            <span class="modal-value" id="view-afetacao">—</span>
          </div>
        </div>
        <div class="detail-grid three" style="margin-bottom:0">
          <div class="detail-item">
            <span class="modal-label">Empresa</span>
            <span class="modal-value" id="view-empresa">—</span>
          </div>
          <div class="detail-item">
            <span class="modal-label">Localidade</span>
            <span class="modal-value" id="view-local">—</span>
          </div>
          <div class="detail-item">
            <span class="modal-label">Observações Operacionais</span>
            <span class="modal-value" id="view-obs">—</span>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
        <button type="button" class="btn btn-warning btn-sm" id="btn-view-edit">
          <i class="bi bi-pencil"></i> Editar
        </button>
        <button type="button" class="btn btn-danger btn-sm" id="btn-view-delete">
          <i class="bi bi-trash"></i> Excluir
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: EDITAR
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar Ocorrência</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="form-edit">
          <input type="hidden" id="edit-id">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="modal-label">OC (somente leitura)</label>
              <input type="text" id="edit-oc" class="form-control form-control-sm" readonly style="background:#f5f3ff;font-family:monospace">
            </div>
            <div class="col-md-6">
              <label class="modal-label">TA</label>
              <input type="text" id="edit-ta" class="form-control form-control-sm">
            </div>
            <div class="col-md-6">
              <label class="modal-label">Status</label>
              <select id="edit-status" class="form-select form-select-sm">
                <option value="Ativo">Ativo</option>
                <option value="Fechado">Fechado</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="modal-label">Gpon</label>
              <input type="text" id="edit-gpon" class="form-control form-control-sm" style="font-family:monospace">
            </div>
            <div class="col-md-6">
              <label class="modal-label">Splitters</label>
              <input type="text" id="edit-splitters" class="form-control form-control-sm" style="font-family:monospace">
            </div>
            <div class="col-md-6">
              <label class="modal-label">Empresa</label>
              <select id="edit-empresa" class="form-select form-select-sm">
                <option value="ABILITY">ABILITY</option>
                <option value="ONDACOM">ONDACOM</option>
                <option value="VIVO">VIVO</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="modal-label">Localidade</label>
              <input type="text" id="edit-localidade" class="form-control form-control-sm">
            </div>
            <div class="col-md-6">
              <label class="modal-label">Afetação</label>
              <input type="text" id="edit-afetacao" class="form-control form-control-sm">
            </div>
            <div class="col-md-6">
              <label class="modal-label">Data Encerramento</label>
              <input type="datetime-local" id="edit-data-enc" class="form-control form-control-sm">
            </div>
            <div class="col-12">
              <label class="modal-label">Observações Operacionais</label>
              <textarea id="edit-obs" class="form-control form-control-sm" rows="2"></textarea>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary btn-sm" id="btn-save-edit">
          <i class="bi bi-save"></i> Salvar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: HISTÓRICO / COMENTÁRIOS
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalHistory" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content mh-modal">

      <!-- ── Cabeçalho NOC ──────────────────────────────────── -->
      <div class="mh-header">
        <div class="mh-header-left">
          <div class="mh-header-icon"><i class="bi bi-clock-history"></i></div>
          <div>
            <div class="mh-header-title">
              <span id="hist-ta"></span>
              <span class="mh-sep">|</span>
              <span id="hist-splitter"></span>
              <span class="mh-sep">|</span>
              <span id="hist-localidade" style="text-transform:uppercase"></span>
            </div>
            <div class="mh-header-sub">Histórico Operacional · Comentários · Previsão de Finalização</div>
          </div>
        </div>
        <button type="button" class="mh-close-btn" data-bs-dismiss="modal" aria-label="Fechar">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <!-- ── Corpo 2 colunas ────────────────────────────────── -->
      <div class="mh-body">

        <!-- Coluna esquerda: Timeline -->
        <div class="mh-left">
          <div class="mh-section-hdr"><i class="bi bi-activity"></i> Histórico Operacional</div>
          <div id="history-timeline" class="mh-timeline-wrap"></div>
        </div>

        <!-- Coluna direita: Previsão + Comentário -->
        <div class="mh-right">

          <!-- Card Previsão de Finalização -->
          <div class="mh-prev-card">
            <div class="mh-card-hdr"><i class="bi bi-calendar-clock"></i> Previsão de Finalização</div>
            <div id="mh-prev-date-display" class="mh-prev-date-display">—</div>
            <div id="previsao-status-badge" class="mh-prev-status-wrap"></div>
            <div id="previsao-sla-info" class="previsao-sla-info"></div>
            <div class="mh-prev-fields">
              <input type="datetime-local" id="previsao-input" class="previsao-input form-control form-control-sm">
              <div class="mh-prev-btns">
                <button class="btn btn-sm btn-outline-secondary previsao-btn-clear" id="btn-previsao-clear" title="Remover previsão">
                  <i class="bi bi-x-lg"></i>
                </button>
                <button class="btn btn-sm btn-primary previsao-btn-save" id="btn-previsao-save">
                  <i class="bi bi-check-lg"></i> Salvar
                </button>
              </div>
            </div>
          </div>

          <!-- Card Comentário -->
          <div class="mh-comment-card">
            <div class="mh-card-hdr"><i class="bi bi-chat-left-dots"></i> Novo Comentário</div>
            <textarea id="new-comment" class="form-control mh-comment-area" rows="5"
              placeholder="Observação operacional…"></textarea>
            <div class="mh-comment-actions">
              <button class="btn btn-primary btn-sm" id="btn-add-comment">
                <i class="bi bi-send"></i> Enviar
              </button>
            </div>
          </div>

        </div>
      </div>

    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: CONFIRMAR EXCLUSÃO
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalDelete" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#7f1d1d,#dc2626)">
        <h5 class="modal-title" style="color:#fff"><i class="bi bi-trash"></i> Confirmar Exclusão</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1)"></button>
      </div>
      <div class="modal-body text-center" style="padding:24px">
        <i class="bi bi-exclamation-triangle-fill" style="font-size:36px;color:#dc2626;display:block;margin-bottom:12px"></i>
        <p>Tem certeza que deseja excluir a ocorrência</p>
        <p><strong id="del-oc-label" class="mono"></strong>?</p>
        <p style="font-size:12px;color:var(--gpon-muted)">Esta ação não pode ser desfeita.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger btn-sm" id="btn-exec-delete">
          <i class="bi bi-trash"></i> Excluir
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: CONFIRMAR EXCLUSÃO DE COMENTÁRIO
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalDeleteComment" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#7f1d1d,#dc2626)">
        <h5 class="modal-title" style="color:#fff"><i class="bi bi-chat-left-text"></i> Excluir Comentário</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center" style="padding:24px">
        <i class="bi bi-exclamation-triangle-fill" style="font-size:36px;color:#dc2626;display:block;margin-bottom:12px"></i>
        <p style="margin-bottom:4px">Tem certeza que deseja excluir este comentário?</p>
        <p style="font-size:12px;color:var(--gpon-muted)">Esta ação não pode ser desfeita.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger btn-sm" id="btn-exec-delete-comment">
          <i class="bi bi-trash"></i> Excluir
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: UPLOAD EXCEL
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalUpload" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-file-earmark-excel"></i> Importar Planilha Excel</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="upload-area" id="upload-area">
          <i class="bi bi-cloud-upload"></i>
          <div class="upload-label" id="upload-filename">Clique ou arraste um arquivo .xlsx aqui</div>
          <div class="upload-sub">Somente arquivos Excel (.xlsx, .xls)</div>
          <input type="file" id="upload-file" accept=".xlsx,.xls" style="display:none">
        </div>
        <div class="upload-progress" id="upload-progress">
          <div style="background:#e5e7eb;border-radius:3px;height:6px;margin-top:12px">
            <div class="progress-bar-gpon" id="upload-bar" style="width:0%"></div>
          </div>
          <div style="font-size:12px;color:var(--gpon-muted);margin-top:6px;text-align:center">Processando...</div>
        </div>
        <div class="import-result" id="upload-result"></div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-success btn-sm" id="btn-do-import" disabled>
          <i class="bi bi-upload"></i> Importar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     OVERLAY: PROCESSAMENTO DE IMPORTAÇÃO
═══════════════════════════════════════════════════════════ -->
<div id="upload-overlay" role="status" aria-label="Processando importação" aria-hidden="true">
  <div class="upload-loading-box">
    <div class="upload-spinner">
      <svg viewBox="0 0 50 50" class="upload-spinner-svg" aria-hidden="true">
        <circle cx="25" cy="25" r="20" fill="none" stroke-width="3" class="upload-spinner-track"/>
        <circle cx="25" cy="25" r="20" fill="none" stroke-width="3" class="upload-spinner-arc"/>
      </svg>
    </div>
    <div class="loading-title" id="overlay-title">Processando planilha</div>
    <div class="loading-sub"  id="overlay-sub">Importando ocorrências GPON…</div>
    <div class="overlay-steps">
      <div class="overlay-step" id="ovstep-upload">
        <span class="step-icon"><i class="bi bi-cloud-upload"></i></span>
        <span class="step-label">Upload</span>
      </div>
      <div class="overlay-step" id="ovstep-process">
        <span class="step-icon"><i class="bi bi-cpu"></i></span>
        <span class="step-label">Leitura</span>
      </div>
      <div class="overlay-step" id="ovstep-db">
        <span class="step-icon"><i class="bi bi-database"></i></span>
        <span class="step-label">Banco</span>
      </div>
      <div class="overlay-step" id="ovstep-done">
        <span class="step-icon"><i class="bi bi-check-circle"></i></span>
        <span class="step-label">Concluído</span>
      </div>
    </div>
    <div class="overlay-bar-wrap">
      <div class="overlay-bar" id="overlay-bar"></div>
    </div>
    <div class="overlay-pct" id="overlay-pct">0%</div>
  </div>
</div>

<!-- ── MODAL: Histórico GPON + SP ────────────────────────────── -->
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

<!-- ── SCRIPTS ──────────────────────────────────────────────── -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>
<script src="https://cdn.datatables.net/fixedcolumns/4.3.0/js/dataTables.fixedColumns.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.mini.min.js"></script>
<script src="<?= $base ?>/assets/js/gpon.js?v=<?= filemtime(__DIR__ . '/../assets/js/gpon.js') ?>"></script>
</body>
</html>
    <?php exit;
}
