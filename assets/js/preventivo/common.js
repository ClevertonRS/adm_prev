/* assets/js/preventivo/common.js — helpers compartilhados do módulo de Preventiva de Rede */
(function (global) {
  'use strict';

  var STATUS_LABELS = {
    aberta: 'Aberta', triagem: 'Triagem', em_execucao: 'Em Execução',
    em_revisao: 'Em Revisão', concluida: 'Concluída', cancelada: 'Cancelada',
  };

  var STATUS_COLORS = {
    aberta:       { bg: '#dbeafe', fg: '#1d4ed8' },
    triagem:      { bg: '#fef3c7', fg: '#92400e' },
    em_execucao:  { bg: '#fef9c3', fg: '#854d0e' },
    em_revisao:   { bg: '#ede9fe', fg: '#5b21b6' },
    concluida:    { bg: '#dcfce7', fg: '#15803d' },
    cancelada:    { bg: '#f1f5f9', fg: '#64748b' },
  };

  var PRIORIDADE_COLORS = {
    baixa: { bg: '#f1f5f9', fg: '#475569' },
    media: { bg: '#dbeafe', fg: '#1d4ed8' },
    alta:  { bg: '#ffedd5', fg: '#9a3412' },
    urgente: { bg: '#fee2e2', fg: '#991b1b' },
  };

  function esc(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function fmtDate(iso) {
    if (!iso) return '—';
    var m = String(iso).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    return m ? m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] + ':' + m[5] : iso;
  }

  // padding/fontSize parametrizáveis para preservar o tamanho de badge usado em cada página
  function badge(label, colors, opts) {
    var c = colors || { bg: '#f1f5f9', fg: '#475569' };
    var o = opts || {};
    var padding  = o.padding  || '2px 8px';
    var fontSize = o.fontSize || '11px';
    return '<span style="display:inline-block;padding:' + padding + ';border-radius:10px;font-size:' + fontSize + ';font-weight:700;background:' + c.bg + ';color:' + c.fg + '">' + esc(label) + '</span>';
  }

  global.PreventivoCommon = {
    STATUS_LABELS: STATUS_LABELS,
    STATUS_COLORS: STATUS_COLORS,
    PRIORIDADE_COLORS: PRIORIDADE_COLORS,
    esc: esc,
    fmtDate: fmtDate,
    badge: badge,
  };
})(window);
