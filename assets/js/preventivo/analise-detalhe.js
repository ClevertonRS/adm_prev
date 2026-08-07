(function () {
  'use strict';
  var C = window.PreventivoCommon;

  var id = Number(window.GPON_PREVENTIVA_ID) || 0;
  var base = window.BASE_PATH || '/admin';
  var content = document.getElementById('prev-content');
  var loading = document.getElementById('loading-state');

  function el(sel) { return document.getElementById(sel); }

  function renderHero(p) {
    el('hdr-gpon').textContent = p.gpon;
    el('hdr-splitter').textContent = p.splitter;

    var statusBadge;
    if (p.atendimento_status === 'analise') {
      statusBadge = C.badge('Análise', { bg: '#fef3c7', fg: '#92400e' });
    } else {
      statusBadge = C.badge((C.STATUS_LABELS[p.status] || p.status), C.STATUS_COLORS[p.status]);
    }

    var prioridade = C.badge((p.prioridade || 'media').charAt(0).toUpperCase() + (p.prioridade || 'media').slice(1), C.PRIORIDADE_COLORS[p.prioridade]);
    var uf = p.uf ? C.badge(p.uf, { bg: '#ede9fe', fg: '#5b21b6' }) : '';
    el('hdr-badges').innerHTML = statusBadge + prioridade + uf;

    el('info-localidade').textContent = p.localidade || '—';
    el('info-ocorrencias').textContent = (p.origem_total_ocorrencias ?? '—');
    el('info-tecnico').textContent = p.atendimento_tecnico_analise_nome || p.tecnico_nome || '—';
    el('info-iniciado').textContent = C.fmtDate(p.atendimento_iniciado_em);
    el('info-concluido').textContent = C.fmtDate(p.atendimento_concluido_em);
    el('info-status-atend').textContent = p.atendimento_status || '—';
  }

  function renderAnalise(p) {
    var desc = p.atendimento_descricao_analise;
    if (desc) {
      el('analise-descricao').innerHTML = C.esc(desc).replace(/\n/g, '<br>');
    } else {
      el('analise-descricao').innerHTML = '<span class="text-muted">Sem descrição de análise.</span>';
    }
  }

  function renderFotos(arquivos) {
    var box = el('fotos-grid');
    box.classList.add('fotos-grid');
    var list = arquivos || [];
    if (!list.length) {
      box.innerHTML = '<span class="text-muted" style="font-size:12px">Nenhuma imagem registrada.</span>';
      return;
    }
    box.innerHTML = list.map(function (a) {
      var tag = (a.tipo || 'evidencia').replace(/_/g, ' ');
      // caminho_arquivo fica um nível abaixo da raiz; prefixa ../ para subir
      var src = base + '/../' + a.caminho_arquivo;
      return '<div class="prev-photo-thumb" title="' + C.esc(a.nome_original || a.caminho_arquivo) + '">' +
        '<img src="' + C.esc(src) + '" loading="lazy" onclick="window.open(this.src)">' +
        '<span class="foto-tag">' + C.esc(tag) + '</span>' +
        '</div>';
    }).join('');
  }

  fetch(base + '/api/preventiva/' + id)
    .then(function (r) { return r.json(); })
    .then(function (res) {
      loading.style.display = 'none';
      if (!res || !res.ok || !res.data || !res.data.preventiva) {
        alert('Não foi possível carregar a análise.');
        return;
      }
      var d = res.data;
      renderHero(d.preventiva);
      renderAnalise(d.preventiva);
      renderFotos(d.arquivos);
      content.style.display = 'block';
    })
    .catch(function () {
      loading.style.display = 'none';
      alert('Erro ao carregar a análise.');
    });
})();