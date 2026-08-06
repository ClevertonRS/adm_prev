/* preventivo-detalhe.js — página de detalhe/execução de uma Preventiva de Rede */
(function () {
  'use strict';

  var ID          = window.GPON_PREVENTIVA_ID;
  var USER_ID     = window.GPON_USER_ID;
  var USER_NIVEL  = window.GPON_USER_NIVEL;
  var CHECKLIST   = window.GPON_CHECKLIST_ITEMS || {};
  var CAN_MANAGE  = ['admin', 'supervisor', 'backoffice'].indexOf(USER_NIVEL) !== -1;

  var STATUS_LABELS     = PreventivoCommon.STATUS_LABELS;
  var STATUS_COLORS     = PreventivoCommon.STATUS_COLORS;
  var PRIORIDADE_COLORS = PreventivoCommon.PRIORIDADE_COLORS;
  var esc     = PreventivoCommon.esc;
  var fmtDate = PreventivoCommon.fmtDate;

  function badgeHtml(label, colors) {
    return PreventivoCommon.badge(label, colors, { padding: '3px 10px', fontSize: '12px' });
  }

  function flash(msg, type) {
    var bar = document.getElementById('flash-bar');
    if (!bar) return;
    var el = document.createElement('div');
    el.className = 'flash-msg ' + (type || 'info');
    el.textContent = msg;
    bar.appendChild(el);
    setTimeout(function () { el.remove(); }, 4000);
  }

  var current = null; // último payload carregado (preventiva/execucao/arquivos/historico)

  function apiUrl(suffix) {
    return BASE_PATH + '/api/preventiva/' + ID + (suffix || '');
  }

  function load() {
    fetch(apiUrl(), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.ok) {
          document.getElementById('loading-state').innerHTML =
            '<i class="bi bi-exclamation-triangle" style="color:#dc2626"></i> ' + esc(res.message || 'Não foi possível carregar a preventiva.');
          return;
        }
        current = res.data;
        render();
        document.getElementById('loading-state').style.display = 'none';
        document.getElementById('prev-content').style.display = '';
      })
      .catch(function () {
        document.getElementById('loading-state').innerHTML = '<i class="bi bi-exclamation-triangle" style="color:#dc2626"></i> Falha ao conectar ao servidor.';
      });
  }

  function loadUsuarios(callback) {
    fetch(BASE_PATH + '/api/preventiva/usuarios', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) { callback(res.ok ? (res.data || []) : []); })
      .catch(function () { callback([]); });
  }

  function render() {
    var p = current.preventiva;
    var canManage = CAN_MANAGE;

    document.getElementById('hdr-gpon').textContent      = p.gpon;
    document.getElementById('hdr-splitter').textContent  = p.splitter;
    document.getElementById('hdr-badges').innerHTML =
      badgeHtml(STATUS_LABELS[p.status] || p.status, STATUS_COLORS[p.status]) +
      badgeHtml((p.prioridade || 'media').replace(/^./, function (c) { return c.toUpperCase(); }), PRIORIDADE_COLORS[p.prioridade]);

    document.getElementById('info-localidade').textContent   = p.localidade || p.uf || '—';
    document.getElementById('info-ocorrencias').textContent  = p.origem_total_ocorrencias || 0;
    document.getElementById('info-supervisor').textContent   = p.supervisor_nome || '—';
    document.getElementById('info-tecnico').textContent      = p.tecnico_nome || '—';
    document.getElementById('info-criador').textContent      = p.criado_por_nome || '—';
    document.getElementById('info-criado-em').textContent    = fmtDate(p.criado_em);
    document.getElementById('info-concluido-em').textContent = p.concluido_em ? fmtDate(p.concluido_em) : '—';
    document.getElementById('info-observacao').textContent   = p.observacao_abertura || '—';

    // Ações do cabeçalho
    var hdrActions = document.getElementById('hdr-actions');
    hdrActions.innerHTML = '';
    if (canManage && ['concluida', 'cancelada'].indexOf(p.status) === -1) {
      var btnCancel = document.createElement('button');
      btnCancel.type = 'button';
      btnCancel.className = 'btn btn-sm btn-outline-danger';
      btnCancel.innerHTML = '<i class="bi bi-x-circle"></i> Cancelar preventiva';
      btnCancel.addEventListener('click', function () {
        if (!confirm('Cancelar esta preventiva? Esta ação não poderá ser desfeita.')) return;
        updateStatus('cancelada', null);
      });
      hdrActions.appendChild(btnCancel);
    }

    // ── Triagem ──────────────────────────────────────────────
    var cardTriagem = document.getElementById('card-triagem');
    if (canManage && ['concluida', 'cancelada'].indexOf(p.status) === -1) {
      cardTriagem.style.display = '';
      document.getElementById('triagem-prioridade').value = p.prioridade || 'media';
      loadUsuarios(function (users) {
        var supSel = document.getElementById('triagem-supervisor');
        var tecSel = document.getElementById('triagem-tecnico');
        supSel.innerHTML = '<option value="">-- selecionar --</option>';
        tecSel.innerHTML = '<option value="">-- selecionar --</option>';
        users.forEach(function (u) {
          var opt = '<option value="' + u.id + '">' + esc(u.nome) + ' (' + esc(u.nivel) + ')</option>';
          if (u.nivel === 'supervisor' || u.nivel === 'admin') supSel.insertAdjacentHTML('beforeend', opt);
          if (u.nivel === 'tecnico' || u.nivel === 'supervisor' || u.nivel === 'admin') tecSel.insertAdjacentHTML('beforeend', opt);
        });
        supSel.value = p.supervisor_id || '';
        tecSel.value = p.tecnico_id || '';
      });
    } else {
      cardTriagem.style.display = 'none';
    }

    // ── Validação ────────────────────────────────────────────
    var cardValidacao = document.getElementById('card-validacao');
    cardValidacao.style.display = (canManage && p.status === 'em_revisao') ? '' : 'none';

    // ── Histórico ────────────────────────────────────────────
    var histList = document.getElementById('historico-list');
    var historico = current.historico || [];
    if (!historico.length) {
      histList.innerHTML = '<span class="text-muted" style="font-size:12px">Sem eventos.</span>';
    } else {
      histList.innerHTML = historico.map(function (h) {
        var transicao = h.status_origem
          ? (STATUS_LABELS[h.status_origem] || h.status_origem) + ' → ' + (STATUS_LABELS[h.status_destino] || h.status_destino)
          : 'Criada como ' + (STATUS_LABELS[h.status_destino] || h.status_destino);
        return '<div class="prev-hist-item">' +
          '<div style="white-space:nowrap;color:var(--gpon-muted)">' + fmtDate(h.criado_em) + '</div>' +
          '<div><strong>' + esc(h.usuario_nome || 'Sistema') + '</strong> — ' + esc(transicao) +
          (h.observacao ? '<div style="color:var(--gpon-muted)">' + esc(h.observacao) + '</div>' : '') +
          '</div></div>';
      }).join('');
    }
  }

  function updateStatus(status, observacao, extra) {
    var payload = Object.assign({ status: status }, extra || {});
    if (observacao) payload.observacao = observacao;
    fetch(apiUrl(), {
      method: 'PUT', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.ok) { flash(res.message || 'Atualizado com sucesso.', 'success'); load(); }
        else flash(res.message || 'Erro ao atualizar.', 'error');
      })
      .catch(function () { flash('Falha ao conectar ao servidor.', 'error'); });
  }

  document.getElementById('btn-salvar-triagem').addEventListener('click', function () {
    var payload = {
      prioridade: document.getElementById('triagem-prioridade').value,
      supervisor_id: document.getElementById('triagem-supervisor').value || null,
      tecnico_id: document.getElementById('triagem-tecnico').value || null,
    };
    fetch(apiUrl(), {
      method: 'PUT', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.ok) { flash(res.message || 'Triagem atualizada.', 'success'); load(); }
        else flash(res.message || 'Erro ao salvar triagem.', 'error');
      })
      .catch(function () { flash('Falha ao conectar ao servidor.', 'error'); });
  });

  document.getElementById('btn-aprovar').addEventListener('click', function () {
    if (!confirm('Aprovar e concluir esta preventiva? A combinação passará a ser marcada como preventivada.')) return;
    updateStatus('concluida', 'Aprovada pelo supervisor.');
  });

  document.getElementById('btn-devolver').addEventListener('click', function () {
    var obs = prompt('Descreva a pendência para o técnico corrigir:');
    if (obs === null) return;
    updateStatus('em_execucao', obs || 'Devolvida com pendência.');
  });

  load();
})();
