# Fluxo funcional — Preventiva de Rede

## Objetivo

Transformar uma combinação GPON + Splitter identificada na página de análise em uma tarefa operacional de preventiva de rede, com aprovação de supervisor e execução de técnico.

## Fluxo operacional

1. **Identificação**
   - O usuário da análise clica no botão da linha do mapa/interatividade.
   - O sistema cria ou abre uma preventiva para aquela combinação.

2. **Triagem do supervisor**
   - A tarefa é criada com status `aberta` ou `triagem`.
   - O supervisor define prioridade, prazo, técnico e observação inicial.

3. **Execução do técnico**
   - O técnico recebe a tarefa.
   - Preenche checklist, material, itens substituídos, causa raiz, ação aplicada, observações de campo.
   - Sobe fotos de antes/depois/evidência.

4. **Envio para revisão**
   - O técnico clica em `Enviar para revisão`.
   - A tarefa muda para `em_revisao`.

5. **Validação do supervisor**
   - O supervisor revisa o conteúdo, aprova ou devolve com pendência.
   - Se aprovar, a tarefa vai para `concluida`.

6. **Marcação operacional**
   - A combinação passa a aparecer como preventivada.
   - O mapa exibe a data de conclusão.

## Campos principais da tarefa

- GPON
- Splitter
- UF/Localidade
- Total de ocorrências no período
- Última reincidência
- Status atual
- Prioridade
- Prazo
- Responsável (supervisor/técnico)
- Observação inicial

## Checklist sugerido

- Inspeção visual realizada
- Organização de caixa/bandejamento
- Limpeza/recomposição
- Correção de conectorização
- Substituição de splitter
- Substituição de cordão / cabo drop / patch cord
- Adequação de acomodação / identificação
- Correção de vedação / proteção
- Foto antes
- Foto depois
- Teste final executado

## Campos extras

- Itens substituídos
- Consumo de material
- Causa raiz encontrada
- Ação corretiva/preventiva aplicada
- Observação do técnico
- Observação do supervisor

## Regras de negócio

- Uma combinação pode ter mais de uma preventiva ao longo do tempo, mas só uma ativa por vez.
- Se já existir preventiva ativa, o botão muda de `Enviar preventiva` para `Ver preventiva`.
- A marcação visual de `preventivado` deve considerar a última preventiva concluída, com data de conclusão.
- A tarefa deve ser vinculada à combinação e também ao contexto da análise (período, filtro, total).
