/* assets/js/shared/analise-filter-bar.js
 * Barra de filtros compartilhada (período, UF, GPON, Splitter, Baixa Causa,
 * Ocultar Improcedentes/FIBRASIL). Extraído de assets/js/analise-filtros.js
 * para ser reaproveitado tanto por /analise quanto pelo painel
 * "Análise Preventiva" de /preventivo, sem duplicar a lógica.
 *
 * Uso: AnaliseFilterBar.init({ onChange: function() { ... } });
 *      AnaliseFilterBar.buildParams() -> querystring (sem "?")
 *
 * Segue o padrão de módulo já usado em assets/js/preventivo/common.js:
 * IIFE + objeto público em window, sem estado mutável exposto para fora
 * (diferente do padrão antigo window.AnaliseApp).
 */
window.AnaliseFilterBar = (function () {
  'use strict';

  var onChange = function () {};
  var activeUf = '';
  var initialized = false;

  var selPeriodo, customDates, inpInicio, inpFim, btnAplicar, periodoBadge,
      inpGpon, inpSp, inpCausa, chkValidos, chkFibrasil, ufBtns;

  var SWITCH_STORE_KEY = 'gpon_analise_switches';
  var PERIODO_LABEL = {
    '24h': 'Últimas 24h', 'hoje': 'Hoje', 'ontem': 'Ontem',
    '7d': 'Últimos 7 dias', '15d': 'Últimos 15 dias', '30d': 'Últimos 30 dias',
  };

  function _saveSwitches() {
    try {
      localStorage.setItem(SWITCH_STORE_KEY, JSON.stringify({
        improcedentes: chkValidos ? chkValidos.checked : true,
        fibrasil:      chkFibrasil ? chkFibrasil.checked : true,
      }));
    } catch (e) {}
  }

  function _restoreSwitches() {
    try {
      var saved = JSON.parse(localStorage.getItem(SWITCH_STORE_KEY) || 'null');
      if (saved && typeof saved === 'object') {
        if (chkValidos) chkValidos.checked = saved.improcedentes !== false;
        if (chkFibrasil) chkFibrasil.checked = saved.fibrasil !== false;
      }
    } catch (e) {}
  }

  var debTimer = null;
  function debounceLoad() { clearTimeout(debTimer); debTimer = setTimeout(function () { onChange(); }, 600); }

  function buildParams() {
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
    if (activeUf) parts.push('uf=' + encodeURIComponent(activeUf));
    if (gv) parts.push('gpon='        + encodeURIComponent(gv));
    if (sv) parts.push('sp='          + encodeURIComponent(sv));
    if (cv) parts.push('baixa_causa=' + encodeURIComponent(cv));
    if (chkValidos.checked) parts.push('ocultar_improcedentes=1');
    if (chkFibrasil && chkFibrasil.checked) parts.push('ocultar_fibrasil=1');
    return parts.join('&');
  }

  function updatePeriodoBadge() {
    var v = selPeriodo.value;
    if (!v) { periodoBadge.style.display = 'none'; return; }
    var label = v === 'custom'
      ? (inpInicio.value || '?') + ' → ' + (inpFim.value || '?')
      : (PERIODO_LABEL[v] || v);
    periodoBadge.textContent = '📅 ' + label;
    periodoBadge.style.display = 'inline-flex';
  }

  function init(opts) {
    opts = opts || {};
    onChange = typeof opts.onChange === 'function' ? opts.onChange : function () {};

    selPeriodo   = document.getElementById('sel-periodo');
    customDates  = document.getElementById('custom-dates');
    inpInicio    = document.getElementById('inp-inicio');
    inpFim       = document.getElementById('inp-fim');
    btnAplicar   = document.getElementById('btn-aplicar');
    periodoBadge = document.getElementById('periodo-badge');
    inpGpon      = document.getElementById('inp-gpon');
    inpSp        = document.getElementById('inp-sp');
    inpCausa     = document.getElementById('inp-causa');
    chkValidos   = document.getElementById('chk-validos');
    chkFibrasil  = document.getElementById('chk-ocultar-fibrasil');
    ufBtns       = document.querySelectorAll('.uf-btn');

    if (!selPeriodo) return; // partial não presente nesta página — nada a inicializar

    activeUf = '';
    _restoreSwitches();

    selPeriodo.addEventListener('change', function () {
      customDates.style.display = (this.value === 'custom') ? 'flex' : 'none';
      if (this.value !== 'custom') onChange();
    });
    if (btnAplicar) btnAplicar.addEventListener('click', function () { if (inpInicio.value && inpFim.value) onChange(); });
    inpGpon.addEventListener('input', debounceLoad);
    inpSp.addEventListener('input', debounceLoad);
    inpCausa.addEventListener('input', debounceLoad);
    chkValidos.addEventListener('change', function () { _saveSwitches(); onChange(); });
    if (chkFibrasil) chkFibrasil.addEventListener('change', function () { _saveSwitches(); onChange(); });

    var btnClearGpon  = document.getElementById('clear-inp-gpon');
    var btnClearSp    = document.getElementById('clear-inp-sp');
    var btnClearCausa = document.getElementById('clear-inp-causa');

    function _updateClearBtns() {
      if (btnClearGpon)  btnClearGpon.style.display  = inpGpon.value.trim()  ? 'flex' : 'none';
      if (btnClearSp)    btnClearSp.style.display    = inpSp.value.trim()    ? 'flex' : 'none';
      if (btnClearCausa) btnClearCausa.style.display = inpCausa.value.trim() ? 'flex' : 'none';
    }

    if (btnClearGpon)  btnClearGpon.addEventListener('click',  function () { inpGpon.value  = ''; _updateClearBtns(); onChange(); });
    if (btnClearSp)    btnClearSp.addEventListener('click',    function () { inpSp.value    = ''; _updateClearBtns(); onChange(); });
    if (btnClearCausa) btnClearCausa.addEventListener('click', function () { inpCausa.value = ''; _updateClearBtns(); onChange(); });

    inpGpon.addEventListener('input', _updateClearBtns);
    inpSp.addEventListener('input', _updateClearBtns);
    inpCausa.addEventListener('input', _updateClearBtns);

    ufBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        activeUf = this.dataset.uf;
        ufBtns.forEach(function (b) { b.classList.remove('active', 'active-mt', 'active-ms', 'active-df', 'active-go'); });
        this.classList.add('active');
        if (activeUf === 'MT') this.classList.add('active-mt');
        if (activeUf === 'MS') this.classList.add('active-ms');
        if (activeUf === 'DF') this.classList.add('active-df');
        if (activeUf === 'GO') this.classList.add('active-go');
        onChange();
      });
    });

    initialized = true;
  }

  return {
    init: init,
    buildParams: function () { return initialized ? buildParams() : ''; },
    updatePeriodoBadge: function () { if (initialized) updatePeriodoBadge(); },
    getActiveUf: function () { return activeUf; },
  };
})();
