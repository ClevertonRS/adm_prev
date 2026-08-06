/* assets/js/preventivo/dashboard.js — KPIs e "criadas recentemente" da Visão
   Geral. Consome o mesmo dataset já buscado pelo controlador da página
   (nenhuma requisição adicional). */
window.PreventivoDashboard = (function () {
  'use strict';

  var esc = PreventivoCommon.esc;
  var fmtDate = PreventivoCommon.fmtDate;
  var STATUS_LABELS = PreventivoCommon.STATUS_LABELS;
  var STATUS_COLORS = PreventivoCommon.STATUS_COLORS;
  function badge(label, colors) {
    return PreventivoCommon.badge(label, colors, { padding: '2px 8px', fontSize: '11px' });
  }

  function countBy(rows, status) {
    return rows.filter(function (r) { return r.status === status; }).length;
  }

  function setText(id, value) {
    var el = document.getElementById(id);
    if (el) el.textContent = value;
  }

  function renderRecentes(rows) {
    var list = document.getElementById('dash-recentes');
    if (!list) return;
    if (!rows.length) {
      list.innerHTML = '<span class="text-muted" style="font-size:12px">Nenhuma preventiva registrada.</span>';
      return;
    }
    var recentes = rows.slice()
      .sort(function (a, b) { return new Date(b.criado_em) - new Date(a.criado_em); })
      .slice(0, 8);

    list.innerHTML = recentes.map(function (r) {
      return '<a href="' + BASE_PATH + '/preventivo/' + r.id + '" class="prev-recent-item">' +
        '<span><strong class="mono">' + esc(r.gpon) + '</strong> <i class="bi bi-arrow-right" style="color:var(--gpon-muted)"></i> <span class="mono">' + esc(r.splitter) + '</span></span>' +
        '<span class="prev-recent-meta">' + badge(STATUS_LABELS[r.status] || r.status, STATUS_COLORS[r.status]) + '<span style="color:var(--gpon-muted)">' + fmtDate(r.criado_em) + '</span></span>' +
        '</a>';
    }).join('');
  }

  function render(rows) {
    setText('kpi-total', rows.length);
    setText('kpi-aberta', countBy(rows, 'aberta'));
    setText('kpi-triagem', countBy(rows, 'triagem'));
    setText('kpi-em_execucao', countBy(rows, 'em_execucao'));
    setText('kpi-em_revisao', countBy(rows, 'em_revisao'));
    setText('kpi-concluida', countBy(rows, 'concluida'));
    setText('kpi-cancelada', countBy(rows, 'cancelada'));
    renderRecentes(rows);
  }

  return { render: render };
})();
