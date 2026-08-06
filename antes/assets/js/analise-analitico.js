/* analise-analitico.js — modal analítico, SVG charts, resumo, _fetchResumo */
(function(A) {
  'use strict';

  // ── Estado ───────────────────────────────────────────────────────
  A._resumoCache   = {};
  A._resumoPending = {};

  var modalAna   = document.getElementById('modal-analitico');
  var anaLoading = document.getElementById('ana-loading');
  var anaContent = document.getElementById('ana-content');
  var _anaAbort  = null;

  // ── Params para a API analítico/resumo ───────────────────────────
  function _buildAnaliticoParams() {
    var parts  = [];
    var chkImp = document.getElementById('chk-validos');
    var chkFib = document.getElementById('chk-ocultar-fibrasil');
    if (chkImp && chkImp.checked) parts.push('ocultar_improcedentes=1');
    if (chkFib && chkFib.checked) parts.push('ocultar_fibrasil=1');
    return parts.join('&');
  }

  // ── Cálculo de status operacional ───────────────────────────────
  function _calcStatus(d) {
    if (!d || !d.tendencia) return { label: '—',         cls: 'nd',         icon: 'bi-graph-up-arrow'   };
    if (d.tendencia === 'reducao')
      return { label: 'Em Redução', cls: 'melhorando', icon: 'bi-graph-down-arrow' };
    if (d.tendencia === 'estavel')
      return { label: 'Estável',    cls: 'estavel',    icon: 'bi-dash-lg'          };
    return Math.abs(d.tendencia_pct) <= 50
      ? { label: 'Em Atenção', cls: 'atencao', icon: 'bi-graph-up-arrow' }
      : { label: 'Crítica',    cls: 'critica', icon: 'bi-graph-up-arrow' };
  }

  function _buildResumoBullets(d) {
    var b   = [];
    var med = parseFloat(d.media_mensal || d.media || 0);
    var n   = parseInt(d.n_meses || (d.meses && d.meses.length) || 0, 10);
    if (d.tendencia === 'reducao') {
      b.push('Ocorrências em <strong>queda de ' + Math.abs(d.tendencia_pct) + '%</strong> no período recente'
        + ((d.grupo_ant && d.grupo_ult) ? ' (' + A.esc(d.grupo_ult) + ' vs ' + A.esc(d.grupo_ant) + ').' : '.'));
    } else if (d.tendencia === 'crescimento') {
      b.push('Ocorrências em <strong>alta de ' + Math.abs(d.tendencia_pct) + '%</strong> no período recente'
        + ((d.grupo_ant && d.grupo_ult) ? ' (' + A.esc(d.grupo_ult) + ' vs ' + A.esc(d.grupo_ant) + ').' : '.'));
    } else {
      b.push('<strong>Padrão estável</strong> nas ocorrências no período analisado.');
    }
    if (d.pior_mes)   b.push('Maior volume: <strong>' + A.esc(d.pior_mes.label)   + '</strong> (' + d.pior_mes.total   + ' ocorrências).');
    if (d.melhor_mes) b.push('Menor volume: <strong>' + A.esc(d.melhor_mes.label) + '</strong> (' + d.melhor_mes.total + ' ocorrências).');
    b.push('Média mensal de <strong>' + Math.round(med) + ' ocorrências</strong>' + (n > 1 ? ' (base: ' + n + ' meses).' : '.'));
    if (d.tendencia === 'reducao')
      b.push('Tendência geral de <strong>redução</strong> das ocorrências.');
    else if (d.tendencia === 'crescimento')
      b.push('Tendência geral de <strong>crescimento</strong> — requer atenção.');
    else
      b.push('Série histórica <strong>estável</strong> no período analisado.');
    return b;
  }

  function _renderResumo(d) {
    var el = document.getElementById('ana-resumo');
    if (!el) return;
    var st = _calcStatus(d);
    el.innerHTML =
      '<div class="ana-resumo-header"><i class="bi bi-clipboard2-pulse"></i> Diagnóstico Operacional '
        + '<span class="ana-ev-badge ana-ev-' + st.cls + '">' + st.label + '</span>'
      + '</div>'
      + '<ul class="ana-resumo-list">'
        + _buildResumoBullets(d).map(function(b) { return '<li>' + b + '</li>'; }).join('')
      + '</ul>';
    el.style.display = '';
  }

  function _renderTendCell(d) {
    if (!d || !d.tendencia) return '<span class="gpon-td-nd">—</span>';
    var icon  = d.tendencia === 'reducao' ? '📉' : d.tendencia === 'crescimento' ? '📈' : '➡️';
    var cls   = d.tendencia === 'reducao' ? 'gpon-tend-down' : d.tendencia === 'crescimento' ? 'gpon-tend-up' : 'gpon-tend-stable';
    var label = d.tendencia === 'reducao' ? 'Queda' : d.tendencia === 'crescimento' ? 'Alta' : 'Estável';
    var pct   = d.tendencia !== 'estavel' ? ' ' + Math.abs(d.tendencia_pct) + '%' : '';
    return '<span class="gpon-tend ' + cls + '">' + icon + ' ' + label + pct + '</span>';
  }

  function _renderStatusCell(d, st) {
    if (!d || !st || st.cls === 'nd') return '<span class="gpon-td-nd">—</span>';
    var emoji = { melhorando: '🟢', estavel: '🟡', atencao: '🟠', critica: '🔴' }[st.cls] || '—';
    return '<span class="gpon-status-badge gpon-status-' + st.cls + '">' + emoji + ' ' + st.label + '</span>';
  }

  // ── Atualiza célula de tendência e tooltip do badge GPON ─────────
  A._updateBtnEvolucao = function(gpon) {
    var d = A._resumoCache[gpon];
    if (!d) return;
    var st  = _calcStatus(d);
    var tip = 'Histórico Analítico do ' + gpon + '\n'
            + 'Status: ' + st.label + '\n'
            + 'Tendência: ' + (d.tendencia !== 'estavel'
                ? (d.tendencia === 'reducao' ? 'Queda' : 'Alta') + ' de ' + Math.abs(d.tendencia_pct) + '%'
                : 'Estável') + '\n'
            + (d.pior_mes   ? 'Maior volume: ' + d.pior_mes.label   + ' (' + d.pior_mes.total   + ')\n' : '')
            + (d.melhor_mes ? 'Menor volume: ' + d.melhor_mes.label + ' (' + d.melhor_mes.total  + ')\n' : '')
            + 'Clique para abrir a análise completa.';
    $('#tbl-gpon .btn-evolucao[data-gpon="' + A.esc(gpon) + '"]')
      .attr('title', tip).attr('data-ev', st.cls)
      .html('<i class="bi ' + st.icon + '"></i>');
    $('#tbl-gpon .gpon-link-analitico[data-gpon="' + A.esc(gpon) + '"]').attr('title', tip);
    var tendEl = document.querySelector('#tbl-gpon .gpon-tend-cell[data-gpon="' + gpon + '"]');
    var statEl = document.querySelector('#tbl-gpon .gpon-status-cell[data-gpon="' + gpon + '"]');
    if (tendEl) tendEl.innerHTML = _renderTendCell(d);
    if (statEl) statEl.innerHTML = _renderStatusCell(d, st);
    if (tendEl) {
      var row = tendEl.closest('tr');
      if (row) {
        row.classList.remove('gpon-row-atencao', 'gpon-row-critica');
        if (st.cls === 'atencao') row.classList.add('gpon-row-atencao');
        else if (st.cls === 'critica') row.classList.add('gpon-row-critica');
      }
    }
  };

  // ── Lazy-load de resumo por GPON (Tendência cell) ────────────────
  A._fetchResumo = function(gpon) {
    if (A._resumoCache[gpon]) { A._updateBtnEvolucao(gpon); return; }
    if (A._resumoPending[gpon]) return;
    A._resumoPending[gpon] = true;
    var qs = 'gpon=' + encodeURIComponent(gpon) + '&' + _buildAnaliticoParams();
    fetch(BASE_PATH + '/api/analise/resumo?' + qs)
      .then(function(r) { return r.json(); })
      .then(function(d) {
        delete A._resumoPending[gpon];
        if (!d.error) { A._resumoCache[gpon] = d; A._updateBtnEvolucao(gpon); }
      })
      .catch(function() { delete A._resumoPending[gpon]; });
  };

  // ── Modal Analítico: close ───────────────────────────────────────
  A.closeAnaliticoModal = function() {
    modalAna.style.display = 'none';
    document.body.style.overflow = '';
  };

  document.getElementById('ana-modal-close').addEventListener('click', A.closeAnaliticoModal);
  modalAna.addEventListener('click', function(e) { if (e.target === modalAna) A.closeAnaliticoModal(); });
  document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && modalAna.style.display !== 'none') A.closeAnaliticoModal(); });

  // ── Helpers de gráfico SVG ────────────────────────────────────────
  function _linReg(values) {
    var n = values.length, sx = 0, sy = 0, sxy = 0, sx2 = 0;
    values.forEach(function(v, i) { sx += i; sy += v; sxy += i * v; sx2 += i * i; });
    var den = n * sx2 - sx * sx;
    if (!den) return { slope: 0, intercept: n ? sy / n : 0 };
    var m = (n * sxy - sx * sy) / den;
    return { slope: m, intercept: (sy - m * sx) / n };
  }

  function _svgTip(container, svgEl) {
    var tip = container.querySelector('.ana-svg-tip');
    if (!tip || !svgEl) return;
    svgEl.addEventListener('mousemove', function(e) {
      var hit = e.target;
      if (!hit.classList.contains('ana-hit')) hit = hit.closest ? hit.closest('.ana-hit') : null;
      if (!hit) { tip.style.display = 'none'; return; }
      var v = hit.getAttribute('data-v'), delta = hit.getAttribute('data-d'), l = hit.getAttribute('data-l'), unit = hit.getAttribute('data-u') || '';
      var dc = delta && delta.charAt(0) === '+' ? '#f87171' : delta && delta.charAt(0) === '-' ? '#6ee7b7' : '#94a3b8';
      var isMttr = unit === 'h';
      tip.innerHTML = '<span style="font-family:monospace;color:#c4b5fd;font-weight:700">' + A.esc(l) + '</span><br>'
        + (isMttr ? 'MTTR' : 'Ocorrências') + ': <strong>' + v + (isMttr ? '' : ' oc.') + '</strong><br>'
        + 'vs mês anterior: <span style="color:' + dc + '">' + (delta || '—') + (isMttr || !delta || delta === '—' ? '' : ' oc.') + '</span>';
      var r = container.getBoundingClientRect();
      var lx = e.clientX - r.left + 12;
      if (lx + 168 > r.width) lx -= 188;
      tip.style.left = lx + 'px';
      tip.style.top  = (e.clientY - r.top - 44) + 'px';
      tip.style.display = '';
    });
    svgEl.addEventListener('mouseleave', function() { tip.style.display = 'none'; });
  }

  function _buildBarSvg(container, meses, avg, partialMonth) {
    var allBars = partialMonth ? meses.concat([partialMonth]) : meses;
    var n       = allBars.length;
    var nC      = meses.length;
    if (!n) { container.innerHTML = '<p style="color:#94a3b8;font-size:11px;text-align:center;padding:32px 0">Sem dados para o período</p>'; return; }
    var W = 640, H = 180, pt = 28, pr = 28, pb = 30, pl = 46;
    var pW = W - pl - pr, pH = H - pt - pb;
    var vals    = meses.map(function(m) { return m.total || 0; });
    var allVals = allBars.map(function(m) { return m.total || 0; });
    var maxRaw  = Math.max.apply(null, allVals.concat(avg > 0 ? [avg] : []));
    var maxV    = (isFinite(maxRaw) && maxRaw > 0) ? maxRaw : 1;
    var nonZ    = vals.filter(function(v) { return v > 0; });
    var minV    = nonZ.length ? Math.min.apply(null, nonZ) : 0;
    var maxClosed = vals.length ? Math.max.apply(null, vals) : 0;
    var maxI    = maxClosed > 0 ? vals.indexOf(maxClosed) : -1;
    var minI    = minV > 0 ? vals.indexOf(minV) : -1;
    var bGap = pW / n, bW = Math.max(8, Math.floor(bGap * 0.58));
    function bx(i)  { return pl + i * bGap + (bGap - bW) / 2; }
    function bh(v)  { return pH * v / maxV; }
    function by(v)  { return pt + pH - bh(v); }
    function mx(i)  { return bx(i) + bW / 2; }
    var lr = _linReg(vals);
    function ty(i)  { return pt + pH - pH * Math.max(0, Math.min(maxV, lr.slope * i + lr.intercept)) / maxV; }

    var s = [];
    s.push('<svg viewBox="0 0 ' + W + ' ' + H + '" style="width:100%;height:188px;display:block;overflow:visible" preserveAspectRatio="none" role="img" aria-label="Ocorrências por mês">');
    s.push('<title>Ocorrências por mês: ' + allBars.map(function(m) { return m.label + ' ' + (m.total || 0) + ' ocorrências'; }).join('; ') + '</title>');

    var yCx = (pt + pH / 2).toFixed(1);
    s.push('<text x="8" y="' + yCx + '" text-anchor="middle" fill="#c4b5fd" font-size="8" font-weight="600" font-family="Plus Jakarta Sans,sans-serif" transform="rotate(-90 8 ' + yCx + ')">Ocorrências</text>');

    [0.25, 0.5, 0.75, 1].forEach(function(r) {
      var gy = (pt + pH * (1 - r)).toFixed(1);
      s.push('<line x1="' + pl + '" y1="' + gy + '" x2="' + (W - pr) + '" y2="' + gy + '" stroke="#ede9fe" stroke-width="1"/>');
      s.push('<text x="' + (pl - 4) + '" y="' + (parseFloat(gy) + 3) + '" text-anchor="end" fill="#c4b5fd" font-size="8">' + Math.round(maxV * r) + '</text>');
    });

    if (avg > 0 && avg <= maxV) {
      var avgy = (pt + pH - pH * avg / maxV).toFixed(1);
      s.push('<line x1="' + pl + '" y1="' + avgy + '" x2="' + (W - pr) + '" y2="' + avgy + '" stroke="#a78bfa" stroke-width="1" stroke-dasharray="5 3" opacity="0.8"/>');
      s.push('<text x="' + (W - pr - 2) + '" y="' + (parseFloat(avgy) + 3).toFixed(1) + '" fill="#7c3aed" font-size="8" font-weight="600" font-family="IBM Plex Mono,monospace" text-anchor="end" opacity="0.9">Média: ' + Math.round(avg) + ' oc.</text>');
    }

    // Linha de tendência apenas sobre meses fechados
    if (nC > 1) {
      s.push('<line x1="' + mx(0).toFixed(1) + '" y1="' + ty(0).toFixed(1) + '" x2="' + mx(nC - 1).toFixed(1) + '" y2="' + ty(nC - 1).toFixed(1) + '" stroke="#f59e0b" stroke-width="2" stroke-dasharray="7 3" opacity="0.85"/>');
    }

    allBars.forEach(function(m, i) {
      var isPartial = (i >= nC);
      var v = allVals[i];
      var x = bx(i).toFixed(1), bH = bh(v).toFixed(1), bY = by(v).toFixed(1), cx = mx(i).toFixed(1);
      var isMax = !isPartial && (i === maxI && v > 0);
      var isMin = !isPartial && (i === minI && v > 0);
      var fill  = isPartial ? '#94a3b8' : isMax ? '#dc2626' : isMin ? '#16a34a' : '#7c3aed';
      var prev  = i > 0 ? allVals[i - 1] : null;
      var dv    = prev !== null ? v - prev : null;
      var ds    = dv !== null ? (dv > 0 ? '+' + dv : String(dv)) : '—';
      var rx    = Math.min(4, bW / 5);
      var hitTitle = A.esc(m.label) + ': ' + v + ' ocorrência' + (v !== 1 ? 's' : '')
        + (isPartial ? ' — Mês em andamento (parcial)' : isMax ? ' — Máximo do período' : isMin ? ' — Mínimo do período' : '');
      s.push('<rect class="ana-hit" data-i="' + i + '" data-l="' + A.esc(m.label) + '" data-v="' + v + '" data-d="' + ds + '" x="' + bx(i).toFixed(1) + '" y="' + pt + '" width="' + bW.toFixed(1) + '" height="' + pH + '" fill="transparent" cursor="crosshair"><title>' + hitTitle + '</title></rect>');
      var barOpacity = isPartial ? '0.52' : '0.88';
      var barStroke  = isPartial
        ? ' stroke="#64748b" stroke-width="1.5" stroke-dasharray="4 3" stroke-opacity="0.65"'
        : ((isMax || isMin) ? ' stroke="' + fill + '" stroke-width="1.5" stroke-opacity="0.45"' : '');
      s.push('<rect class="ana-bar-a" data-fy="' + bY + '" data-fh="' + bH + '" x="' + x + '" y="' + (pt + pH) + '" width="' + bW.toFixed(1) + '" height="0" fill="' + fill + '" rx="' + rx + '" opacity="' + barOpacity + '"' + barStroke + '/>');
      if (v > 0) {
        var fhNum = parseFloat(bH), bYNum = parseFloat(bY);
        var lblInside = fhNum >= 22;
        var lblY      = lblInside ? (bYNum + Math.min(fhNum - 5, 16)).toFixed(1) : Math.max(pt - 2, bYNum - 5).toFixed(1);
        var lblFill   = lblInside ? '#fff' : fill;
        var badge     = isMax ? ' ↑' : isMin ? ' ↓' : '';
        var valTxt    = lblInside ? (v + badge) : (v + ' oc.' + badge);
        s.push('<text class="ana-vlbl" x="' + cx + '" y="' + lblY + '" text-anchor="middle" fill="' + lblFill + '" font-size="9" font-weight="700" font-family="IBM Plex Mono,monospace" opacity="0">' + valTxt + '</text>');
      }
      if (!isPartial) {
        if (isMax && v > 0) s.push('<text x="' + cx + '" y="' + (pt + pH + 12) + '" text-anchor="middle" fill="#dc2626" font-size="7.5" font-weight="800" font-family="Plus Jakarta Sans,sans-serif" aria-hidden="true">▲ MÁX</text>');
        if (isMin && v > 0) s.push('<text x="' + cx + '" y="' + (pt + pH + 12) + '" text-anchor="middle" fill="#16a34a" font-size="7.5" font-weight="800" font-family="Plus Jakarta Sans,sans-serif" aria-hidden="true">▼ MÍN</text>');
      }
      if (isPartial) {
        s.push('<text x="' + cx + '" y="' + (H - 3) + '" text-anchor="middle" fill="#64748b" font-size="9" font-style="italic" font-family="Plus Jakarta Sans,sans-serif">' + A.esc(m.label) + '</text>');
      } else {
        s.push('<text x="' + cx + '" y="' + (H - 3) + '" text-anchor="middle" fill="#94a3b8" font-size="9" font-family="Plus Jakarta Sans,sans-serif">' + A.esc(m.label) + '</text>');
      }
    });

    s.push('</svg>');
    s.push('<div class="ana-svg-tip"></div>');
    if (partialMonth) {
      s.push('<p style="margin:5px 0 0;font-size:10px;color:#94a3b8;font-style:italic;text-align:right;padding-right:2px">'
        + '&#9632; Mês parcial (' + A.esc(partialMonth.label) + ') — exibido apenas para acompanhamento, não considerado na análise estatística.</p>');
    }
    container.style.position = 'relative';
    container.innerHTML = s.join('');

    var bars = container.querySelectorAll('.ana-bar-a');
    var base = pt + pH;
    bars.forEach(function(r, i) {
      var fh = parseFloat(r.getAttribute('data-fh'));
      function ease(t) { return 1 - Math.pow(1 - t, 3); }
      var t0 = null;
      setTimeout(function() {
        requestAnimationFrame(function frame(ts) {
          if (!t0) t0 = ts;
          var p = ease(Math.min((ts - t0) / 480, 1));
          r.setAttribute('y', (base - fh * p).toFixed(1));
          r.setAttribute('height', (fh * p).toFixed(1));
          if (p < 1) requestAnimationFrame(frame);
        });
      }, i * 48);
    });
    setTimeout(function() {
      container.querySelectorAll('.ana-vlbl').forEach(function(el) { el.setAttribute('opacity', '1'); });
    }, bars.length * 48 + 520);

    _svgTip(container, container.querySelector('svg'));
  }

  function _buildLineSvg(container, meses, partialMonth) {
    var allBars = partialMonth ? meses.concat([partialMonth]) : meses;
    var n       = allBars.length;
    var nC      = meses.length;
    if (!n) { container.innerHTML = '<p style="color:#94a3b8;font-size:11px;text-align:center;padding:32px 0">Sem dados</p>'; return; }
    var W = 640, H = 180, pt = 28, pr = 28, pb = 30, pl = 32;
    var pW = W - pl - pr, pH = H - pt - pb;
    var vals    = meses.map(function(m) { return m.mttr_avg != null ? m.mttr_avg : null; });
    var allVals = allBars.map(function(m) { return m.mttr_avg != null ? m.mttr_avg : null; });
    var nonN    = vals.filter(function(v) { return v !== null; });
    var maxV    = nonN.length ? Math.max.apply(null, nonN) : 0; if (!maxV) maxV = 1;
    var minV    = nonN.length ? Math.min.apply(null, nonN) : 0;
    var maxI    = nonN.length ? vals.indexOf(Math.max.apply(null, nonN)) : -1;
    var minI    = nonN.length ? vals.indexOf(minV) : -1;
    var bGap = pW / n;
    function px(i) { return pl + i * bGap + bGap / 2; }
    function py(v) { return pt + pH - pH * v / maxV; }

    var s = [];
    s.push('<svg viewBox="0 0 ' + W + ' ' + H + '" style="width:100%;height:188px;display:block;overflow:visible" preserveAspectRatio="none" role="img" aria-label="Gráfico de evolução do MTTR por mês">');
    s.push('<title>MTTR mensal: ' + allBars.map(function(m) { return m.label + ' ' + (m.mttr_avg != null ? m.mttr_avg + 'h' : '—'); }).join('; ') + '</title>');

    [0.25, 0.5, 0.75, 1].forEach(function(r) {
      var gy = (pt + pH * (1 - r)).toFixed(1);
      s.push('<line x1="' + pl + '" y1="' + gy + '" x2="' + (W - pr) + '" y2="' + gy + '" stroke="#ede9fe" stroke-width="1"/>');
      s.push('<text x="' + (pl - 4) + '" y="' + (parseFloat(gy) + 3) + '" text-anchor="end" fill="#c4b5fd" font-size="8">' + Math.round(maxV * r) + 'h</text>');
    });

    // Linha principal apenas sobre meses fechados
    var pts = [];
    vals.forEach(function(v, i) { if (v !== null) pts.push([px(i).toFixed(1), py(v).toFixed(1)]); });
    if (pts.length > 1) {
      var lineD = 'M ' + pts[0].join(',') + ' L ' + pts.slice(1).map(function(p) { return p.join(','); }).join(' L ');
      var areaD = lineD + ' L ' + pts[pts.length - 1][0] + ',' + (pt + pH) + ' L ' + pts[0][0] + ',' + (pt + pH) + ' Z';
      s.push('<path d="' + areaD + '" fill="#7c3aed" opacity="0.07"/>');
      s.push('<path class="ana-line-path" d="' + lineD + '" fill="none" stroke="#7c3aed" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>');
    }

    // Segmento tracejado conectando o último mês fechado ao mês parcial (se ambos têm dado)
    if (partialMonth && partialMonth.mttr_avg != null) {
      var lastWithVal = -1;
      for (var ci = vals.length - 1; ci >= 0; ci--) { if (vals[ci] !== null) { lastWithVal = ci; break; } }
      if (lastWithVal >= 0) {
        s.push('<line x1="' + px(lastWithVal).toFixed(1) + '" y1="' + py(vals[lastWithVal]).toFixed(1)
          + '" x2="' + px(nC).toFixed(1) + '" y2="' + py(partialMonth.mttr_avg).toFixed(1)
          + '" stroke="#94a3b8" stroke-width="1.5" stroke-dasharray="4 3" opacity="0.55"/>');
      }
    }

    allBars.forEach(function(m, i) {
      var isPartial = (i >= nC);
      var v  = allVals[i];
      var cx = px(i).toFixed(1);
      var prev  = i > 0 ? allVals[i - 1] : null;
      var dv    = (v !== null && prev !== null) ? (v - prev).toFixed(1) : null;
      var ds    = dv !== null ? (parseFloat(dv) > 0 ? '+' + dv + 'h' : dv + 'h') : '—';
      var isMaxL = !isPartial && (i === maxI);
      var isMinL = !isPartial && (i === minI);
      var hitLbl = A.esc(m.label) + ': MTTR ' + (v !== null ? v + 'h' : '—')
        + (isPartial ? ' — Mês em andamento (parcial)' : isMaxL ? ' — Pior MTTR do período' : isMinL ? ' — Melhor MTTR do período' : '');
      s.push('<rect class="ana-hit" data-i="' + i + '" data-l="' + A.esc(m.label) + '" data-v="' + (v !== null ? v + 'h' : '—') + '" data-d="' + ds + '" data-u="h" x="' + (px(i) - bGap / 2).toFixed(1) + '" y="' + pt + '" width="' + bGap.toFixed(1) + '" height="' + pH + '" fill="transparent" cursor="crosshair"><title>' + hitLbl + '</title></rect>');
      if (v !== null) {
        var fill = isPartial ? '#94a3b8' : isMaxL ? '#dc2626' : isMinL ? '#16a34a' : '#7c3aed';
        var cy   = py(v).toFixed(1);
        var r    = (!isPartial && (isMaxL || isMinL)) ? 6 : 4;
        s.push('<circle class="ana-dot" cx="' + cx + '" cy="' + cy + '" r="' + r + '" fill="' + fill + '" stroke="#fff" stroke-width="1.5" opacity="0"/>');
        s.push('<text class="ana-vlbl" x="' + cx + '" y="' + (parseFloat(cy) - 9).toFixed(1) + '" text-anchor="middle" fill="' + fill + '" font-size="9" font-weight="700" font-family="IBM Plex Mono,monospace" opacity="0">' + v + 'h</text>');
      }
      if (!isPartial) {
        if (isMaxL && v !== null) s.push('<text x="' + cx + '" y="' + (pt + pH + 12) + '" text-anchor="middle" fill="#dc2626" font-size="7.5" font-weight="800" font-family="Plus Jakarta Sans,sans-serif" aria-hidden="true">▲ PIOR</text>');
        if (isMinL && v !== null) s.push('<text x="' + cx + '" y="' + (pt + pH + 12) + '" text-anchor="middle" fill="#16a34a" font-size="7.5" font-weight="800" font-family="Plus Jakarta Sans,sans-serif" aria-hidden="true">▼ MELHOR</text>');
      }
      if (isPartial) {
        s.push('<text x="' + cx + '" y="' + (H - 3) + '" text-anchor="middle" fill="#64748b" font-size="9" font-style="italic" font-family="Plus Jakarta Sans,sans-serif">' + A.esc(m.label) + '</text>');
      } else {
        s.push('<text x="' + cx + '" y="' + (H - 3) + '" text-anchor="middle" fill="#94a3b8" font-size="9" font-family="Plus Jakarta Sans,sans-serif">' + A.esc(m.label) + '</text>');
      }
    });

    s.push('</svg>');
    s.push('<div class="ana-svg-tip"></div>');
    container.style.position = 'relative';
    container.innerHTML = s.join('');

    var lp = container.querySelector('.ana-line-path');
    if (lp) {
      requestAnimationFrame(function() {
        try {
          var len = lp.getTotalLength();
          lp.style.strokeDasharray  = len;
          lp.style.strokeDashoffset = len;
          requestAnimationFrame(function() {
            lp.style.transition       = 'stroke-dashoffset 1s ease';
            lp.style.strokeDashoffset = '0';
          });
        } catch(e) {}
      });
    }
    setTimeout(function() {
      container.querySelectorAll('.ana-dot, .ana-vlbl').forEach(function(el) { el.setAttribute('opacity', '1'); });
    }, 1100);

    _svgTip(container, container.querySelector('svg'));
  }

  // ── _renderAnalitico ─────────────────────────────────────────────
  function _renderAnalitico(d) {
    var meses        = d.meses || [];
    var partialMonth = d.mes_parcial || null;
    var n     = meses.length;
    var media = d.media_mensal || 0;
    var ano   = new Date().getFullYear();
    var _mes  = function(lbl) { return lbl ? lbl.split('/')[0] : lbl; };
    var periodoLabel = n === 0 ? String(ano)
      : n === 1 ? (_mes(meses[0].label) + '/' + ano)
      : (_mes(meses[0].label) + '–' + _mes(meses[n - 1].label) + '/' + ano);
    if (partialMonth) {
      periodoLabel += ' · <span style="color:#94a3b8;font-size:10.5px;font-weight:500">'
        + A.esc(partialMonth.label)
        + ' <em style="font-size:9px;font-style:italic;opacity:.75">parcial</em></span>';
    }

    var evolTitle = document.getElementById('ana-evolucao-title');
    if (evolTitle) evolTitle.innerHTML = '<i class="bi bi-bar-chart-fill"></i> Ocorrências por Mês — ' + periodoLabel;
    var mttrTitle = document.getElementById('ana-mttr-title');
    if (mttrTitle) mttrTitle.innerHTML = '<i class="bi bi-stopwatch"></i> TMR Médio por Mês — ' + periodoLabel;
    var anaTitle = document.getElementById('ana-modal-title');
    if (anaTitle) anaTitle.innerHTML = '<i class="bi bi-graph-up-arrow"></i> Histórico Analítico do GPON';

    var tendIcon  = d.tendencia === 'crescimento' ? '📈' : d.tendencia === 'reducao' ? '📉' : '➡️';
    var tendColor = d.tendencia === 'crescimento' ? '#dc2626' : d.tendencia === 'reducao' ? '#16a34a' : '#d97706';
    var tendLabel = d.tendencia === 'crescimento' ? 'Alta' : d.tendencia === 'reducao' ? 'Queda' : 'Estável';
    var tendPct   = d.tendencia !== 'estavel' ? ' de ' + Math.abs(d.tendencia_pct) + '%' : '';
    var tendTip   = (d.grupo_ult && d.grupo_ant)
      ? 'Soma de ' + d.grupo_ult + ' comparada com ' + d.grupo_ant + '. Variação de ' + Math.abs(d.tendencia_pct) + '%.'
      : 'Comparação entre a soma do período mais recente e do período imediatamente anterior.';
    var st        = _calcStatus(d);
    var tendLine1 = tendIcon + ' ' + tendLabel + tendPct + ' nas ocorrências';
    var tendLine2 = (d.grupo_ult && d.grupo_ant) ? d.grupo_ult + ' comparado a ' + d.grupo_ant : '';
    document.getElementById('ana-modal-sub').innerHTML =
      '<span class="mono">' + A.esc(d.gpon) + '</span>'
      + ' · <span class="ana-ev-badge ana-ev-' + st.cls + '">' + st.label + '</span>'
      + ' · ' + periodoLabel + '<br>'
      + '<span style="font-weight:700;color:' + tendColor + '">' + tendLine1 + '</span>'
      + (tendLine2 ? ' <span style="color:#94a3b8;font-weight:400;font-size:10px">' + tendLine2 + '</span>' : '')
      + ' <i class="bi bi-info-circle" title="' + A.esc(tendTip) + '" style="cursor:help;color:#a78bfa;font-size:11px;vertical-align:middle"></i>';

    document.getElementById('ana-kpis').innerHTML =
      '<div class="ana-kpi">'
        + '<div class="ana-kpi-val">' + (d.total_12m || 0) + '</div>'
        + '<div class="ana-kpi-label">Total no Ano</div>'
        + '<div class="ana-kpi-sub">' + (partialMonth ? 'incl. ' + A.esc(partialMonth.label) + ' parcial' : 'ocorrências') + '</div>'
      + '</div>'
      + '<div class="ana-kpi" title="Média calculada somente com meses fechados. O mês atual em andamento não é considerado para evitar distorções.">'
        + '<div class="ana-kpi-val">' + media.toLocaleString('pt-BR', {minimumFractionDigits:1, maximumFractionDigits:1}) + '</div>'
        + '<div class="ana-kpi-label">Média Mensal</div>'
        + '<div class="ana-kpi-sub">' + (d.total_fechados != null ? d.total_fechados : (d.total_12m || 0)) + ' ÷ ' + n + ' mes' + (n !== 1 ? 'es' : '') + ' fech. <i class="bi bi-info-circle" style="font-size:9px;vertical-align:middle;opacity:.7"></i></div>'
      + '</div>'
      + '<div class="ana-kpi" style="border-color:#dcfce7" title="Mês com a menor quantidade de ocorrências do período analisado.">'
        + '<div class="ana-kpi-val" style="color:#16a34a">' + (d.melhor_mes ? d.melhor_mes.total : '—') + '</div>'
        + '<div class="ana-kpi-label">Menor Volume ↓</div>'
        + '<div class="ana-kpi-sub">' + (d.melhor_mes ? A.esc(d.melhor_mes.label) : 'sem dados') + '</div>'
        + (d.melhor_mes ? '<div class="ana-kpi-sub" style="opacity:.72">Menor volume do período</div>' : '')
      + '</div>'
      + '<div class="ana-kpi" style="border-color:#fee2e2" title="Mês com a maior quantidade de ocorrências do período analisado.">'
        + '<div class="ana-kpi-val" style="color:#dc2626">' + (d.pior_mes ? d.pior_mes.total : '—') + '</div>'
        + '<div class="ana-kpi-label">Maior Volume ↑</div>'
        + '<div class="ana-kpi-sub">' + (d.pior_mes ? A.esc(d.pior_mes.label) : 'sem dados') + '</div>'
        + (d.pior_mes ? '<div class="ana-kpi-sub" style="opacity:.72">Maior volume do período</div>' : '')
      + '</div>'
      + (partialMonth
          ? '<div style="flex:0 0 100%;margin-top:6px;padding:8px 12px;background:rgba(100,116,139,0.06);border-left:3px solid #c4b5fd;border-radius:0 6px 6px 0;font-size:10.5px;color:#94a3b8;line-height:1.5">'
            + '<i class="bi bi-info-circle-fill" style="color:#a78bfa;margin-right:5px;vertical-align:middle"></i>'
            + '<strong style="color:#c4b5fd">' + A.esc(partialMonth.label) + '</strong>'
            + ' é um mês em andamento e não participa dos cálculos de tendência, média mensal, maior volume ou menor volume.'
            + '</div>'
          : '');

    _buildBarSvg(document.getElementById('ana-chart'), meses, media, partialMonth);
    _buildLineSvg(document.getElementById('ana-mttr'), meses, partialMonth);

    var causas    = d.causas || [];
    var maxCausa  = causas.length ? causas[0].total : 1;
    var causasHtml = causas.length ? '' : '<span style="color:#94a3b8;font-size:11px">Sem dados</span>';
    causas.forEach(function(c) {
      var pct = Math.round(c.total / maxCausa * 100);
      causasHtml += '<div class="ana-causa-row">'
        + '<div class="ana-causa-name" title="' + A.esc(c.causa) + '">' + A.esc(c.causa || '—') + '</div>'
        + '<div class="ana-causa-bar-wrap"><div class="ana-causa-bar" style="width:' + pct + '%"></div></div>'
        + '<div class="ana-causa-val">' + c.total + ' <span style="color:#c4b5fd">(' + c.pct + '%)</span></div>'
        + '</div>';
    });
    document.getElementById('ana-causas').innerHTML = causasHtml;

    var splitters = d.splitters || [];
    var maxSp     = splitters.length ? splitters[0].total : 1;
    var spHtml    = splitters.length ? '' : '<span style="color:#94a3b8;font-size:11px">Sem dados</span>';
    splitters.forEach(function(sp) {
      var pct = Math.round(sp.total / maxSp * 100);
      spHtml += '<div class="ana-causa-row">'
        + '<div class="ana-causa-name" style="min-width:72px;max-width:80px;font-family:monospace;font-weight:600;color:#5b21b6">' + A.esc(sp.sp) + '</div>'
        + '<div class="ana-causa-bar-wrap"><div class="ana-causa-bar" style="width:' + pct + '%;background:#a78bfa"></div></div>'
        + '<div class="ana-causa-val">' + sp.total + '</div>'
        + '</div>';
    });
    document.getElementById('ana-splitters').innerHTML = spHtml;

    _renderResumo(d);
  }

  // ── openAnaliticoModal ───────────────────────────────────────────
  A.openAnaliticoModal = function(gpon) {
    document.getElementById('ana-modal-title').innerHTML = '<i class="bi bi-graph-up-arrow"></i> Histórico Analítico da OLT';
    document.getElementById('ana-modal-sub').textContent = gpon + ' · carregando…';
    modalAna.style.display       = 'flex';
    document.body.style.overflow = 'hidden';

    // Resultado completo já em cache (contém meses, causas, splitters)
    var cached = A._resumoCache[gpon];
    if (cached && cached.meses) {
      anaLoading.style.display = 'none';
      anaContent.style.display = '';
      _renderAnalitico(cached);
      return;
    }

    anaLoading.style.display = '';
    anaContent.style.display = 'none';
    anaLoading.innerHTML = '<i class="bi bi-hourglass-split"></i> Carregando…';

    if (_anaAbort) { _anaAbort.abort(); }
    _anaAbort = new AbortController();

    var qs  = 'gpon=' + encodeURIComponent(gpon) + '&' + _buildAnaliticoParams();
    fetch(BASE_PATH + '/api/analise/analitico?' + qs, { signal: _anaAbort.signal })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        _anaAbort = null;
        anaLoading.style.display = 'none';
        if (!d.error) { A._resumoCache[gpon] = d; A._updateBtnEvolucao(gpon); }
        _renderAnalitico(d);
        anaContent.style.display = '';
      })
      .catch(function(e) {
        if (e.name === 'AbortError') return;
        anaLoading.innerHTML = '<i class="bi bi-exclamation-triangle" style="color:#dc2626"></i> Erro ao carregar dados.';
        console.error('analitico error', e);
      });
  };

  // ── Click handlers do modal analítico ────────────────────────────
  $(document).on('click', '#tbl-gpon .btn-evolucao', function(e) {
    e.stopPropagation();
    A.openAnaliticoModal($(this).data('gpon'));
  });

  $(document).on('click', '#tbl-gpon .gpon-link-analitico', function(e) {
    e.stopPropagation();
    A.openAnaliticoModal($(this).data('gpon'));
  });

})(window.AnaliseApp = window.AnaliseApp || {});
