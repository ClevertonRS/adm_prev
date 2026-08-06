<?php
/**
 * Barra de filtros da Análise (período, UF, GPON, Splitter, Baixa Causa,
 * Ocultar Improcedentes/FIBRASIL). Compartilhada entre views/analise.php e
 * preventivo/views/preventivo.php (painel "Análise Preventiva").
 *
 * IDs são estáveis (sem prefixo) porque /analise e /preventivo são rotas de
 * página inteira — nunca coexistem no mesmo DOM, então não há risco real de
 * colisão. Lógica de leitura/aplicação dos filtros vive em
 * assets/js/shared/analise-filter-bar.js (window.AnaliseFilterBar).
 */
?>
<div class="analise-filter-bar">
  <span class="analise-filter-label"><i class="bi bi-funnel"></i> Filtros</span>
  <i class="bi bi-calendar3" style="font-size:13px;color:var(--gpon-muted)"></i>
  <select id="sel-periodo" class="analise-sel">
    <option value="">Histórico completo</option>
    <option value="24h">Últimas 24h</option>
    <option value="hoje">Hoje</option>
    <option value="ontem">Ontem</option>
    <option value="7d">Últimos 7 dias</option>
    <option value="15d">Últimos 15 dias</option>
    <option value="30d" selected>Últimos 30 dias</option>
    <option value="custom">Personalizado…</option>
  </select>
  <div id="custom-dates" style="display:none;align-items:center;gap:6px">
    <input type="date" id="inp-inicio" class="analise-date">
    <span style="color:var(--gpon-muted);font-size:12px">até</span>
    <input type="date" id="inp-fim" class="analise-date">
    <button id="btn-aplicar" class="analise-btn-apply">Aplicar</button>
  </div>
  <span class="analise-filter-sep">|</span>
  <span class="analise-filter-label"><i class="bi bi-geo-alt"></i> UF</span>
  <div class="uf-btn-group">
    <button type="button" class="uf-btn active" id="uf-todos" data-uf="">Todos</button>
    <button type="button" class="uf-btn" id="uf-mt" data-uf="MT">MT</button>
    <button type="button" class="uf-btn" id="uf-ms" data-uf="MS">MS</button>
    <button type="button" class="uf-btn" id="uf-df" data-uf="DF">DF</button>
    <button type="button" class="uf-btn" id="uf-go" data-uf="GO">GO</button>
  </div>
  <span class="analise-filter-sep">|</span>
  <div class="filter-pill">
    <i class="bi bi-search"></i>
    <input type="text" id="inp-gpon"  class="analise-inp" placeholder="GPON…"        title="Filtrar por GPON exato">
    <button class="filter-clear" id="clear-inp-gpon"  title="Limpar GPON"><i class="bi bi-x-lg"></i></button>
  </div>
  <div class="filter-pill">
    <i class="bi bi-search"></i>
    <input type="text" id="inp-sp"    class="analise-inp" placeholder="Nº do Splitter…" title="Filtrar por número do Splitter (ex: 25, 142)">
    <button class="filter-clear" id="clear-inp-sp"    title="Limpar Splitter"><i class="bi bi-x-lg"></i></button>
  </div>
  <div class="filter-pill">
    <i class="bi bi-search"></i>
    <input type="text" id="inp-causa" class="analise-inp" placeholder="Baixa Causa…" title="Filtrar por Baixa Causa (parcial)">
    <button class="filter-clear" id="clear-inp-causa" title="Limpar Baixa Causa"><i class="bi bi-x-lg"></i></button>
  </div>
  <span class="analise-filter-sep">|</span>
  <div class="form-check form-switch mb-0" title="Ocultar ocorrências improcedentes e normalizadas sem intervenção" style="display:flex;align-items:center;gap:6px">
    <input class="form-check-input" type="checkbox" role="switch" id="chk-validos" style="cursor:pointer;margin-top:0" checked>
    <label class="form-check-label" for="chk-validos" style="font-size:12px;cursor:pointer;user-select:none;color:var(--gpon-text)">Ocultar Improcedentes</label>
  </div>
  <span class="analise-filter-sep">|</span>
  <div class="form-check form-switch mb-0" title="Ocultar ocorrências da empresa FIBRASIL" style="display:flex;align-items:center;gap:6px">
    <input class="form-check-input" type="checkbox" role="switch" id="chk-ocultar-fibrasil" style="cursor:pointer;margin-top:0" checked>
    <label class="form-check-label" for="chk-ocultar-fibrasil" style="font-size:12px;cursor:pointer;user-select:none;color:var(--gpon-text)">Ocultar FIBRASIL</label>
  </div>
  <span id="periodo-badge" class="analise-periodo-badge" style="display:none"></span>
</div>
