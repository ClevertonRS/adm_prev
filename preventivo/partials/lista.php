<?php
/**
 * Tabela de preventivas (usada pelos painéis Em Andamento/Triagem/Em Execução/
 * Em Revisão/Concluídas/Histórico). Requerido por preventivo/views/preventivo.php.
 * Uma única instância de DataTable (#tbl-preventivas); o filtro por status é
 * aplicado em assets/js/preventivo/lista.js, sem recriar a tabela.
 */
?>
<div class="table-card">
  <table id="tbl-preventivas" class="display" style="width:100%">
    <thead>
      <tr>
        <th>GPON</th><th>Splitter</th><th>Localidade</th><th>Status</th>
        <th>Prioridade</th><th>Supervisor</th><th>Técnico</th>
        <th>Ocorrências</th><th>Criado em</th><th></th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>
</div>
