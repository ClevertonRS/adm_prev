# Módulo de Preventiva de Rede

Este diretório concentra a especificação e o scaffold inicial do fluxo de preventiva para combinações GPON + Splitter.

## Objetivo

Permitir que uma combinação identificada na análise do mapa interativo seja transformada em uma tarefa operacional de preventiva de rede, com fluxo de:

1. criação da tarefa a partir de uma combinação GPON + Splitter;
2. triagem e atribuição pelo supervisor;
3. execução pelo técnico em campo;
4. envio para revisão;
5. aprovação/conclusão pelo supervisor;
6. marcação visual no mapa como preventivada, com data de conclusão.

## Status

- Documentação funcional: pronta
- Modelo de dados: em produção (tabelas criadas via `gpon_init_db()` em `includes/functions.php`)
- Backend: implementado em `api/preventiva.php` (criação, listagem, triagem, execução, upload de fotos, conclusão)
- Views: implementadas em `views/lista.php` e `views/detalhe.php`
- Integração com a análise: botão por combinação em `views/analise.php` / `assets/js/analise-rankings.js`, incluindo marcação "Preventivado em dd/mm/yyyy" no ranking

## Fluxo proposto

### Estados
- `aberta`
- `triagem`
- `em_execucao`
- `em_revisao`
- `concluida`
- `cancelada`

### Responsáveis
- `analista/operador`: cria a tarefa a partir da análise
- `supervisor`: prioriza, atribui técnico e valida
- `tecnico`: executa o trabalho de campo, preenche checklist, fotos e materiais
- `admin`: acompanha tudo e pode ajustar permissões

## Regras de negócio principais

- A unidade operacional é a combinação `GPON + Splitter`.
- Uma combinação pode ter várias preventivas ao longo do tempo, mas somente uma ativa por vez.
- Se já existir uma preventiva aberta ou em execução, a ação na análise deve virar `Ver preventiva`.
- A conclusão do supervisor marca a combinação como preventivada no mapa e registra a data de conclusão.

## Estrutura de arquivos

- `README.md`: visão geral
- `fluxo_preventiva.md`: fluxo funcional detalhado (fonte da verdade do processo)
- `views/lista.php`: listagem real das preventivas (consome `GET /api/preventiva`)
- `views/detalhe.php` + `assets/js/preventivo-detalhe.js`: triagem, execução (checklist/fotos) e validação (consome `/api/preventiva/{id}` e subrotas)

A estrutura de tabelas (`preventivas_rede`, `preventivas_execucao`, `preventivas_arquivos`, `preventivas_historico`) é criada e mantida por `gpon_init_db()` em `includes/functions.php` — não há mais `schema.sql` neste diretório.
