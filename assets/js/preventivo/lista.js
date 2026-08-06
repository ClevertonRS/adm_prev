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

  var dt = null;
  var allRows = [];

  function matches(row, filterKey) {
    if (!filterKey) return true; // '' = todas (Histórico)
    if (STATUS_GROUPS[filterKey]) return STATUS_GROUPS[filterKey].indexOf(row.status) !== -1;
    return row.status === filterKey;
  }

  function init(rows) {
    allRows = rows || [];
    if (dt) return; // já inicializada — nunca recriar

    dt = $('#tbl-preventivas').DataTable({
      data: [], pageLength: 25, order: [[8, 'desc']],
      language: { url: BASE_PATH + '/assets/js/pt-BR.json' },
      columns: [
        { data: 'gpon', render: function (d) { return '<span class="mono fw-700" style="font-size:12px">' + esc(d) + '</span>'; } },
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
  }

  function applyFilter(filterKey) {
    if (!dt) return;
    var rows = allRows.filter(function (r) { return matches(r, filterKey); });
    dt.clear().rows.add(rows).draw();
  }

  return { init: init, applyFilter: applyFilter, getRows: function () { return allRows; } };
})();
