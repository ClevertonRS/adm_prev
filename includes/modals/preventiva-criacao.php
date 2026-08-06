<?php
/**
 * Modal "Nova Preventiva de Rede". Compartilhado entre views/analise.php
 * (botão "Preventiva" do ranking) e preventivo/views/preventivo.php (botão
 * "Criar Preventiva" da coluna Ação do Heatmap, painel "Análise Preventiva").
 *
 * Aberto/preenchido/submetido por assets/js/shared/preventiva-modal.js
 * (window.PreventivaModal.open(gpon, sp, count)). Nenhuma lógica de negócio
 * vive aqui — só o HTML do formulário.
 */
?>
<!-- ── MODAL: Preventiva de Rede ─────────────────────────────── -->
<div class="modal fade" id="modal-preventiva" tabindex="-1" aria-labelledby="preventiva-modal-title" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="preventiva-modal-title"><i class="bi bi-shield-check"></i> Nova Preventiva de Rede</h5>
          <div class="analise-card-sub" id="preventiva-modal-sub">Crie uma tarefa operacional para o GPON + Splitter selecionado</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div id="preventiva-alert" class="alert alert-info" role="alert" style="display:none"></div>
        <form id="form-preventiva">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">GPON</label>
              <input type="text" class="form-control" id="preventiva-gpon" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Splitter</label>
              <input type="text" class="form-control" id="preventiva-splitter" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Prioridade</label>
              <select class="form-select" id="preventiva-prioridade">
                <option value="baixa">Baixa</option>
                <option value="media" selected>Média</option>
                <option value="alta">Alta</option>
                <option value="urgente">Urgente</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Status inicial</label>
              <select class="form-select" id="preventiva-status">
                <option value="triagem" selected>Triagem</option>
                <option value="aberta">Aberta</option>
                <option value="em_execucao">Em execução</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Supervisor</label>
              <select class="form-select" id="preventiva-supervisor"></select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Técnico</label>
              <select class="form-select" id="preventiva-tecnico"></select>
            </div>
            <div class="col-12">
              <label class="form-label">Observação inicial</label>
              <textarea class="form-control" id="preventiva-observacao" rows="3" placeholder="Descreva a necessidade, contexto da reincidência e ação inicial..."></textarea>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="preventiva-submit">Criar Preventiva</button>
      </div>
    </div>
  </div>
</div>
