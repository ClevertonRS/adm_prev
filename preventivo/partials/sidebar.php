<?php
/**
 * Menu lateral do módulo de Preventiva de Rede.
 * Requerido por preventivo/views/preventivo.php. Variável disponível: $base.
 */
?>
<nav class="admin-nav" aria-label="Menu de Preventiva">

  <div class="admin-nav-section">
    <span class="admin-nav-section-title">Preventivo</span>

    <a href="#dashboard" class="admin-nav-item" data-panel="dashboard">
      <i class="bi bi-speedometer2"></i>
      <span>Visão Geral</span>
    </a>

    <a href="#analise-preventiva" class="admin-nav-item" data-panel="analise-preventiva">
      <i class="bi bi-grid-3x3-gap-fill"></i>
      <span>Análise Preventiva</span>
    </a>

    <a href="#lista-andamento" class="admin-nav-item" data-panel="lista" data-filter="andamento">
      <i class="bi bi-hourglass-split"></i>
      <span>Triagem</span>
    </a>

    <a href="#lista-em_execucao" class="admin-nav-item" data-panel="lista" data-filter="em_execucao">
      <i class="bi bi-tools"></i>
      <span>Em Execução</span>
    </a>

    <a href="#lista-em_revisao" class="admin-nav-item" data-panel="lista" data-filter="em_revisao">
      <i class="bi bi-clipboard-check"></i>
      <span>Em Revisão</span>
    </a>

    <a href="#lista-concluida" class="admin-nav-item" data-panel="lista" data-filter="concluida">
      <i class="bi bi-check-circle"></i>
      <span>Concluídas</span>
    </a>

    <a href="#lista-" class="admin-nav-item" data-panel="lista" data-filter="">
      <i class="bi bi-clock-history"></i>
      <span>Histórico</span>
    </a>
  </div>

  <div class="admin-nav-section">
    <span class="admin-nav-section-title">Gestão</span>

    <a href="<?= $base ?>/analise" class="admin-nav-item" title="Criar preventiva a partir de uma combinação na Análise">
      <i class="bi bi-plus-circle"></i>
      <span>Nova Preventiva</span>
    </a>

    <span class="admin-nav-item disabled">
      <i class="bi bi-people"></i>
      <span>Responsáveis</span>
      <span class="soon-tag">Em breve</span>
    </span>

    <span class="admin-nav-item disabled">
      <i class="bi bi-bar-chart"></i>
      <span>Relatórios</span>
      <span class="soon-tag">Em breve</span>
    </span>
  </div>

</nav>
