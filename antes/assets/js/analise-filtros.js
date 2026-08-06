/* analise-filtros.js — estado de filtros, buildParams, listeners, switches */
(function(A) {
  'use strict';

  // ── DOM refs (privados a este módulo) ───────────────────────────
  var selPeriodo   = document.getElementById('sel-periodo');
  var customDates  = document.getElementById('custom-dates');
  var inpInicio    = document.getElementById('inp-inicio');
  var inpFim       = document.getElementById('inp-fim');
  var btnAplicar   = document.getElementById('btn-aplicar');
  var periodoBadge = document.getElementById('periodo-badge');
  var inpGpon      = document.getElementById('inp-gpon');
  var inpSp        = document.getElementById('inp-sp');
  var inpCausa     = document.getElementById('inp-causa');
  var chkValidos   = document.getElementById('chk-validos');
  var ufBtns       = document.querySelectorAll('.uf-btn');

  A.activeUf = '';

  // ── Switches: estado padrão ON + persistência localStorage ──────
  var SWITCH_STORE_KEY = 'gpon_analise_switches';
  function _saveSwitches() {
    var chkFib = document.getElementById('chk-ocultar-fibrasil');
    try {
      localStorage.setItem(SWITCH_STORE_KEY, JSON.stringify({
        improcedentes: chkValidos ? chkValidos.checked : true,
        fibrasil:      chkFib    ? chkFib.checked     : true,
      }));
    } catch(e) {}
  }
  (function _restoreSwitches() {
    try {
      var saved = JSON.parse(localStorage.getItem(SWITCH_STORE_KEY) || 'null');
      if (saved && typeof saved === 'object') {
        if (chkValidos) chkValidos.checked = saved.improcedentes !== false;
        var chkFib = document.getElementById('chk-ocultar-fibrasil');
        if (chkFib) chkFib.checked = saved.fibrasil !== false;
      }
    } catch(e) {}
  })();

  // ── Labels ──────────────────────────────────────────────────────
  var PERIODO_LABEL = {
    '24h':'Últimas 24h','hoje':'Hoje','ontem':'Ontem',
    '7d':'Últimos 7 dias','15d':'Últimos 15 dias','30d':'Últimos 30 dias',
  };

  // ── Debounce ────────────────────────────────────────────────────
  var debTimer = null;
  function debounceLoad() { clearTimeout(debTimer); debTimer = setTimeout(A.loadAnalise, 600); }

  // ── buildParams ─────────────────────────────────────────────────
  A.buildParams = function() {
    var parts = [];
    var p = selPeriodo.value;
    if (p) {
      parts.push('periodo=' + p);
      if (p === 'custom') {
        if (inpInicio.value) parts.push('inicio=' + inpInicio.value);
        if (inpFim.value)    parts.push('fim='    + inpFim.value);
      }
    }
    var gv = inpGpon.value.trim(), sv = inpSp.value.trim().replace(/\D/g, ''), cv = inpCausa.value.trim();
    if (A.activeUf) parts.push('uf=' + encodeURIComponent(A.activeUf));
    if (gv) parts.push('gpon='        + encodeURIComponent(gv));
    if (sv) parts.push('sp='          + encodeURIComponent(sv));
    if (cv) parts.push('baixa_causa=' + encodeURIComponent(cv));
    if (chkValidos.checked) parts.push('ocultar_improcedentes=1');
    var chkFibrasil = document.getElementById('chk-ocultar-fibrasil');
    if (chkFibrasil && chkFibrasil.checked) parts.push('ocultar_fibrasil=1');
    return parts.join('&');
  };

  // ── updatePeriodoBadge ──────────────────────────────────────────
  A.updatePeriodoBadge = function() {
    var v = selPeriodo.value;
    if (!v) { periodoBadge.style.display = 'none'; return; }
    var label = v === 'custom'
      ? (inpInicio.value || '?') + ' → ' + (inpFim.value || '?')
      : (PERIODO_LABEL[v] || v);
    periodoBadge.textContent = '📅 ' + label;
    periodoBadge.style.display = 'inline-flex';
  };

  // ── Event listeners ─────────────────────────────────────────────
  selPeriodo.addEventListener('change', function() {
    customDates.style.display = (this.value === 'custom') ? 'flex' : 'none';
    if (this.value !== 'custom') A.loadAnalise();
  });
  btnAplicar.addEventListener('click', function() { if (inpInicio.value && inpFim.value) A.loadAnalise(); });
  inpGpon.addEventListener('input', debounceLoad);
  inpSp.addEventListener('input', debounceLoad);
  inpCausa.addEventListener('input', debounceLoad);
  chkValidos.addEventListener('change', function() { _saveSwitches(); A.loadAnalise(); });

  var chkFibrasisEl = document.getElementById('chk-ocultar-fibrasil');
  if (chkFibrasisEl) chkFibrasisEl.addEventListener('change', function() { _saveSwitches(); A.loadAnalise(); });

  // ── Botões X por campo ──────────────────────────────────────────
  var btnClearGpon  = document.getElementById('clear-inp-gpon');
  var btnClearSp    = document.getElementById('clear-inp-sp');
  var btnClearCausa = document.getElementById('clear-inp-causa');

  function _updateClearBtns() {
    if (btnClearGpon)  btnClearGpon.style.display  = inpGpon.value.trim()  ? 'flex' : 'none';
    if (btnClearSp)    btnClearSp.style.display    = inpSp.value.trim()    ? 'flex' : 'none';
    if (btnClearCausa) btnClearCausa.style.display = inpCausa.value.trim() ? 'flex' : 'none';
  }

  if (btnClearGpon)  btnClearGpon.addEventListener('click',  function() { inpGpon.value  = ''; _updateClearBtns(); A.loadAnalise(); });
  if (btnClearSp)    btnClearSp.addEventListener('click',    function() { inpSp.value    = ''; _updateClearBtns(); A.loadAnalise(); });
  if (btnClearCausa) btnClearCausa.addEventListener('click', function() { inpCausa.value = ''; _updateClearBtns(); A.loadAnalise(); });

  inpGpon.addEventListener('input', _updateClearBtns);
  inpSp.addEventListener('input', _updateClearBtns);
  inpCausa.addEventListener('input', _updateClearBtns);

  // ── Botões de UF ────────────────────────────────────────────────
  ufBtns.forEach(function(btn) {
    btn.addEventListener('click', function() {
      A.activeUf = this.dataset.uf;
      ufBtns.forEach(function(b) { b.classList.remove('active', 'active-mt', 'active-ms', 'active-df', 'active-go'); });
      this.classList.add('active');
      if (A.activeUf === 'MT') this.classList.add('active-mt');
      if (A.activeUf === 'MS') this.classList.add('active-ms');
      if (A.activeUf === 'DF') this.classList.add('active-df');
      if (A.activeUf === 'GO') this.classList.add('active-go');
      A.loadAnalise();
    });
  });

})(window.AnaliseApp = window.AnaliseApp || {});
