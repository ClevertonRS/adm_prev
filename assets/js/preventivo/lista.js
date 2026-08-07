/* assets/js/preventivo/lista.js — tabela compartilhada dos painéis de status
   da página única /preventivo. Uma única instância de DataTable é criada;
   trocar de painel no menu lateral só reaplica um filtro sobre os dados já
   carregados (evita recriar #tbl-preventivas e conflitos de DataTables). */
window.PreventivoLista = (function () {
  'use strict';

  var STATUS_LABELS = PreventivoCommon.STATUS_LABELS;
  var STATUS_COLORS = PreventivoCommon.STATUS_COLORS;
  var PRIORIDADE_COLORS = PreventivoCommon.PRIORIDADE_COLORS;
  var esc = PreventivoCommon.esc;
  var fmtDate = PreventivoCommon.fmtDate;
  function badge(label, colors) {
    return PreventivoCommon.badge(label, colors, { padding: '2px 8px', fontSize: '11px' });
  }

  var STATUS_GROUPS = {
    andamento: ['aberta', 'triagem', 'em_execucao', 'em_revisao'],
  };

function chip(text, cls) {
    return '<span class="hp-chip ' + cls + '">' + esc(text) + '</span>';
  }

var dt = null;
  var allRows = [];
  var currentFilter = null;

function matches(row, filterKey) {
    if (!filterKey) return true; // '' = todas (Histórico)
    if (filterKey === 'em_execucao') {
      // Em Execução: exibe preventivas cujo atendimento (análise) foi
      // registrado com status 'analise' na tabela atendimentos.
      return row.atendimento_status === 'analise';
    }
    if (STATUS_GROUPS[filterKey]) return STATUS_GROUPS[filterKey].indexOf(row.status) !== -1;
    return row.status === filterKey;
  }

  function init(rows) {
    allRows = rows || [];
    if (dt) return; // já inicializada — nunca recriar

    dt = $('#tbl-preventivas').DataTable({
      data: [], pageLength: 25, order: [[6, 'desc']],
      language: { url: BASE_PATH + '/assets/js/pt-BR.json' },
      columns: [
        { data: 'gpon', render: function (d) { return chip(d, 'hp-chip-gpon'); } },
        { data: 'splitter', render: function (d) { return chip(d, 'hp-chip-splitter'); } },
        { data: 'status', render: function (d, type, row) {
            // Atendimento em análise (tabela atendimentos) → badge "Análise" amarelo
            if (row && row.atendimento_status === 'analise') {
              return PreventivoCommon.badge('Análise', { bg: '#fef3c7', fg: '#92400e' }, { padding: '2px 8px', fontSize: '11px' });
            }
            return badge(STATUS_LABELS[d] || d, STATUS_COLORS[d]);
          } },
        { data: 'prioridade', render: function (d) { return badge((d || 'media').charAt(0).toUpperCase() + (d || 'media').slice(1), PRIORIDADE_COLORS[d]); } },
        { data: 'supervisor_nome', render: function (d) { return d ? esc(d) : '<span class="text-muted">—</span>'; } },
        { data: 'tecnico_nome', render: function (d) { return d ? esc(d) : '<span class="text-muted">—</span>'; } },
        { data: 'criado_em', render: function (d) { return '<span style="font-size:11px">' + fmtDate(d) + '</span>'; } },
        { data: null, orderable: false, render: function (_, __, row) {
            // Aba Em Execução (que só lista atendimentos em 'analise') sempre abre a página de Análise;
            // demais agentes abrem o detalhe da preventiva.
            var emAnalise = (row && row.atendimento_status === 'analise') || currentFilter === 'em_execucao';
            var url = emAnalise
              ? BASE_PATH + '/analise/' + row.id
              : BASE_PATH + '/preventivo/' + row.id;
            return '<a href="' + url + '" class="btn btn-outline-primary"><i class="bi bi-arrow-right-circle"></i> Abrir</a>';
          }
        },
      ],
    });
  }

  function applyFilter(filterKey) {
    if (!dt) return;
    currentFilter = filterKey;
    var rows = allRows.filter(function (r) { return matches(r, filterKey); });
    dt.clear().rows.add(rows).draw();
  }

  // Recarrega os dados do backend (fica ampla também o dashboard) e reaplica o filtro atual
  function reload() {
    return fetch(BASE_PATH + '/api/preventiva', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        allRows = res.ok ? (res.data || []) : allRows;
        if (window.PreventivoDashboard && typeof window.PreventivoDashboard.render === 'function') {
          window.PreventivoDashboard.render(allRows);
        }
        if (dt && currentFilter !== null) applyFilter(currentFilter);
        return allRows;
      })
      .catch(function () { return allRows; });
  }

  return { init: init, applyFilter: applyFilter, getRows: function () { return allRows; }, reload: reload };
})();
