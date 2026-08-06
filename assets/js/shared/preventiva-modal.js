/* assets/js/shared/preventiva-modal.js
 * Modal "Nova Preventiva de Rede" + renderização do botão/badge de Ação
 * (Criar Preventiva / Ver preventiva / Preventivado em dd/mm/yyyy).
 *
 * Extraído de assets/js/analise-rankings.js (A.openPreventivaModal, o
 * handler de #preventiva-submit, e _renderPreventivaAcao) para ser
 * reaproveitado tanto pela tabela #tbl-comb da Análise quanto pela coluna
 * "Ação" do Heatmap (Análise e painel "Análise Preventiva" do Preventivo).
 *
 * Depende apenas do HTML de includes/modals/preventiva-criacao.php estar
 * presente na página (via #modal-preventiva) e de window.GPON_BASE_PATH ou
 * do basePath passado a init().
 */
window.PreventivaModal = (function () {
  'use strict';

  var basePath = '';
  var onCreated = function () {};
  var PREVENTIVA_STATUS_ATIVOS = ['aberta', 'triagem', 'em_execucao', 'em_revisao'];

  function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function fmtData(iso) {
    if (!iso) return '';
    var m = String(iso).match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? m[3] + '/' + m[2] + '/' + m[1] : iso;
  }

  // row: { gpon, sp, count, preventiva_status, preventiva_id, preventiva_concluido_em }
  function renderAcaoHtml(row) {
    if (row.preventiva_status && PREVENTIVA_STATUS_ATIVOS.indexOf(row.preventiva_status) !== -1) {
      return '<a href="' + basePath + '/preventivo/' + row.preventiva_id + '" class="btn btn-sm btn-outline-success btn-ver-preventiva"><i class="bi bi-eye"></i> Ver preventiva</a>';
    }
    var criarBtn = '<button type="button" class="btn btn-sm btn-outline-primary btn-preventiva" data-gpon="' + esc(row.gpon) + '" data-sp="' + esc(row.sp || '') + '" data-count="' + (row.count || 0) + '"><i class="bi bi-shield-check"></i> Preventiva</button>';
    if (row.preventiva_status === 'concluida') {
      return '<div style="display:flex;flex-direction:column;gap:4px;align-items:flex-start">'
        + '<span class="badge" style="background:#dcfce7;color:#15803d;font-size:10px;font-weight:600;white-space:nowrap"><i class="bi bi-check-circle"></i> Preventivado em ' + fmtData(row.preventiva_concluido_em) + '</span>'
        + criarBtn.replace('Preventiva</button>', 'Nova preventiva</button>')
        + '</div>';
    }
    return criarBtn;
  }

  function open(gpon, sp, count) {
    var modalEl = document.getElementById('modal-preventiva');
    if (!modalEl) return;
    var modal = new bootstrap.Modal(modalEl);
    var gponInput = document.getElementById('preventiva-gpon');
    var spInput = document.getElementById('preventiva-splitter');
    var sub = document.getElementById('preventiva-modal-sub');
    var alertEl = document.getElementById('preventiva-alert');
    var supervisorSelect = document.getElementById('preventiva-supervisor');
    var tecnicoSelect = document.getElementById('preventiva-tecnico');

    if (gponInput) gponInput.value = gpon || '';
    if (spInput) spInput.value = sp || '';
    if (sub) sub.textContent = 'Combinação ' + (gpon || '—') + ' / ' + (sp || '—') + ' • ' + (count || 0) + ' ocorrências no período';
    if (alertEl) {
      alertEl.style.display = 'none';
      alertEl.className = 'alert alert-info';
      alertEl.textContent = '';
    }
    if (supervisorSelect) {
      supervisorSelect.innerHTML = '<option value="">-- selecionar --</option>';
      tecnicoSelect.innerHTML = '<option value="">-- selecionar --</option>';
    }

    fetch(basePath + '/api/preventiva/usuarios', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.ok) return;
        var users = res.data || [];
        users.forEach(function (user) {
          var opt = document.createElement('option');
          opt.value = user.id;
          opt.textContent = user.nome + ' (' + user.nivel + ')';
          if (user.nivel === 'supervisor' && supervisorSelect) supervisorSelect.appendChild(opt.cloneNode(true));
          if ((user.nivel === 'tecnico' || user.nivel === 'supervisor' || user.nivel === 'admin') && tecnicoSelect) tecnicoSelect.appendChild(opt.cloneNode(true));
        });
      })
      .catch(function () {});

    modal.show();
  }

  function _wireSubmit() {
    var btn = document.getElementById('preventiva-submit');
    if (!btn || btn.dataset.preventivaModalWired) return; // evita registrar o listener 2x
    btn.dataset.preventivaModalWired = '1';
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var gpon = document.getElementById('preventiva-gpon').value.trim();
      var sp = document.getElementById('preventiva-splitter').value.trim();
      var prioridade = document.getElementById('preventiva-prioridade').value;
      var status = document.getElementById('preventiva-status').value;
      var supervisor = document.getElementById('preventiva-supervisor').value;
      var tecnico = document.getElementById('preventiva-tecnico').value;
      var observacao = document.getElementById('preventiva-observacao').value.trim();
      var alertEl = document.getElementById('preventiva-alert');

      if (!gpon || !sp) {
        if (alertEl) {
          alertEl.style.display = 'block';
          alertEl.className = 'alert alert-danger';
          alertEl.textContent = 'Informe GPON e Splitter antes de criar a preventiva.';
        }
        return;
      }

      if (alertEl) {
        alertEl.style.display = 'block';
        alertEl.className = 'alert alert-info';
        alertEl.textContent = 'Criando preventiva...';
      }

      fetch(basePath + '/api/preventiva', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ gpon: gpon, splitter: sp, prioridade: prioridade, status: status, supervisor_id: supervisor || null, tecnico_id: tecnico || null, observacao_abertura: observacao })
      })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (!res.ok) {
            if (alertEl) {
              alertEl.className = 'alert alert-danger';
              alertEl.textContent = res.message || 'Não foi possível criar a preventiva.';
            }
            return;
          }
          var prevId = res.data && res.data.preventiva_id;
          if (alertEl) {
            alertEl.className = 'alert alert-success';
            alertEl.innerHTML = (res.message || 'Preventiva criada com sucesso.') +
              (prevId ? ' <a href="' + basePath + '/preventivo/' + prevId + '" class="alert-link"><i class="bi bi-arrow-right-circle"></i> Abrir preventiva e continuar</a>' : '');
          }
          onCreated();
        })
        .catch(function () {
          if (alertEl) {
            alertEl.className = 'alert alert-danger';
            alertEl.textContent = 'Falha ao conectar ao servidor.';
          }
        });
    });
  }

  function init(opts) {
    opts = opts || {};
    basePath = opts.basePath || window.GPON_BASE_PATH || '';
    onCreated = typeof opts.onCreated === 'function' ? opts.onCreated : function () {};
    _wireSubmit();

    // Delegação de clique nos botões "Preventiva"/"Nova preventiva" e no
    // link "Ver preventiva" — via jQuery (não addEventListener nativo), de
    // propósito: tabelas como #tbl-comb têm um handler de clique na própria
    // linha (abre o modal de histórico) também delegado via jQuery em
    // document. stopPropagation() só impede handlers jQuery subsequentes no
    // mesmo dispatch quando registrado pelo próprio jQuery — um listener
    // nativo em paralelo não teria esse efeito sobre o dispatch do jQuery.
    if (!document.body.dataset.preventivaModalDelegated) {
      document.body.dataset.preventivaModalDelegated = '1';
      $(document).on('click', '.btn-preventiva', function (e) {
        e.stopPropagation();
        open($(this).data('gpon'), $(this).data('sp'), $(this).data('count'));
      });
      $(document).on('click', '.btn-ver-preventiva', function (e) {
        e.stopPropagation();
      });
    }
  }

  return {
    init: init,
    open: open,
    renderAcaoHtml: renderAcaoHtml,
  };
})();
