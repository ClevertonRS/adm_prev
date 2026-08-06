<?php
// ── API: Histórico / Comentários / Previsão ─────────────────────

// ── API: Histórico / Comentários ───────────────────────────────
function gpon_api_historico_get(PDO $pdo, int $id): void
{
    gpon_require_login();
    $stmt = $pdo->prepare("SELECT * FROM historico_ocorrencias WHERE ocorrencia_id = ? ORDER BY created_at DESC");
    $stmt->execute([$id]);
    $items = $stmt->fetchAll();
    foreach ($items as &$item) {
        $tipo  = trim((string)($item['tipo'] ?? ''));
        $texto = (string)($item['texto'] ?? '');
        // Normalizar tipos vazios/null
        if (!$tipo) $tipo = 'comentario';
        // Corrigir registros de previsão gravados como 'comentario' antes da migração do ENUM
        if ($tipo === 'comentario'
            && empty($item['campo'])
            && empty($item['valor_anterior'])
            && empty($item['valor_novo'])
            && (preg_match('/^Prev\. .+ \d{2}:\d{2}$/u', $texto)
                || $texto === 'Previsão removida')
        ) {
            $tipo = 'previsao';
        }
        $item['tipo'] = $tipo;
    }
    unset($item);
    gpon_json(['ok' => true, 'items' => $items]);
}

function gpon_api_historico_post(PDO $pdo, int $id, array $user): void
{
    // Comentário operacional sobre a ocorrência (e, opcionalmente, previsão de
    // finalização). Antes desta correção, qualquer usuário logado podia postar,
    // inclusive o perfil "tecnico", que deve ficar restrito à execução de preventivas.
    if (!gpon_user_has_role($user, ['admin', 'backoffice', 'supervisor', 'operador'])) {
        gpon_json(['ok' => false, 'message' => 'Acesso negado'], 403);
    }

    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $texto = trim($body['texto'] ?? '');
    if (!$texto) gpon_json(['ok' => false, 'message' => 'Comentário vazio']);

    // Previsão combinada (opcional): se enviada junto com o comentário
    $rawPrev = $body['previsao_finalizacao'] ?? null;
    $prevVal = null;
    if ($rawPrev !== null && $rawPrev !== '') {
        $prevVal = gpon_parse_datetime((string)$rawPrev);
        if (!$prevVal) gpon_json(['ok' => false, 'message' => 'Data/hora de previsão inválida'], 400);
    }

    $stmt = $pdo->prepare("SELECT oc, data_criacao FROM ocorrencias WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) gpon_json(['ok' => false, 'message' => 'Ocorrência não encontrada'], 404);

    $textoFinal = $texto;
    $status     = null;
    $slaLim     = null;

    if ($prevVal) {
        $pdo->prepare("UPDATE ocorrencias SET previsao_finalizacao = ? WHERE id = ?")
            ->execute([$prevVal, $id]);

        $textoFinal .= ' · Prev. às ' . substr($prevVal, 11, 5);

        if ($row['data_criacao']) {
            $criacaoTs = strtotime($row['data_criacao']);
            $slaLimTs  = $criacaoTs + (GPON_SLA_HORAS         * 3600);
            $atenLimTs = $criacaoTs + (GPON_SLA_PROXIMO_HORAS * 3600);
            $slaLim    = date('Y-m-d H:i:s', $slaLimTs);
            $prevTs    = strtotime($prevVal);
            if ($prevTs > $slaLimTs)       $status = 'critica';
            elseif ($prevTs >= $atenLimTs) $status = 'atencao';
            else                           $status = 'ok';
        }
    }

    $pdo->prepare("INSERT INTO historico_ocorrencias (ocorrencia_id, oc, tipo, texto, usuario_id, usuario_nome) VALUES (?,?,'comentario',?,?,?)")
        ->execute([$id, $row['oc'], $textoFinal, $user['id'], $user['nome']]);

    $resp = ['ok' => true];
    if ($prevVal) {
        $resp['previsao_finalizacao'] = $prevVal;
        $resp['previsao_status']      = $status;
        $resp['sla_limite']           = $slaLim;
    }
    gpon_json($resp);
}

function gpon_api_historico_item_put(PDO $pdo, int $itemId, array $user): void
{
    gpon_require_login();
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $texto = trim($body['texto'] ?? '');
    if (!$texto) gpon_json(['ok' => false, 'message' => 'Texto vazio']);

    $stmt = $pdo->prepare("SELECT id, tipo, usuario_id FROM historico_ocorrencias WHERE id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    if (!$item) gpon_json(['ok' => false, 'message' => 'Não encontrado'], 404);
    $tipo = trim((string)($item['tipo'] ?? 'comentario'));
    if (!$tipo) $tipo = 'comentario';
    if ($tipo !== 'comentario') gpon_json(['ok' => false, 'message' => 'Apenas comentários podem ser editados']);
    if ((int)$item['usuario_id'] !== (int)$user['id'] && !in_array($user['nivel'], ['admin', 'backoffice'], true)) {
        gpon_json(['ok' => false, 'message' => 'Sem permissão para editar este comentário'], 403);
    }

    $pdo->prepare("UPDATE historico_ocorrencias SET texto = ? WHERE id = ?")
        ->execute([$texto, $itemId]);

    gpon_json(['ok' => true]);
}

function gpon_api_historico_item_delete(PDO $pdo, int $itemId, array $user): void
{
    gpon_require_login();

    $stmt = $pdo->prepare("SELECT id, usuario_id FROM historico_ocorrencias WHERE id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    if (!$item) gpon_json(['ok' => false, 'message' => 'Não encontrado'], 404);
    if ((int)$item['usuario_id'] !== (int)$user['id'] && !in_array($user['nivel'], ['admin', 'backoffice'], true)) {
        gpon_json(['ok' => false, 'message' => 'Sem permissão para excluir este comentário'], 403);
    }

    $pdo->prepare("DELETE FROM historico_ocorrencias WHERE id = ?")
        ->execute([$itemId]);

    gpon_json(['ok' => true]);
}

// ── API: Previsão de Finalização ──────────────────────────────
function gpon_api_previsao_put(PDO $pdo, int $id, array $user): void
{
    // Previsão de finalização é um dado sensível a SLA; mesma regra de acesso do
    // comentário operacional (bloqueia o perfil "tecnico", restrito à execução).
    if (!gpon_user_has_role($user, ['admin', 'backoffice', 'supervisor', 'operador'])) {
        gpon_json(['ok' => false, 'message' => 'Acesso negado'], 403);
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $raw  = $body['previsao_finalizacao'] ?? '';

    $prevVal = null;
    if ($raw !== '' && $raw !== null) {
        $prevVal = gpon_parse_datetime((string)$raw);
        if (!$prevVal) gpon_json(['ok' => false, 'message' => 'Data/hora inválida'], 400);
    }

    $stmt = $pdo->prepare("SELECT id, oc, data_criacao, previsao_finalizacao AS prev_anterior FROM ocorrencias WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) gpon_json(['ok' => false, 'message' => 'Não encontrado'], 404);

    $pdo->prepare("UPDATE ocorrencias SET previsao_finalizacao = ? WHERE id = ?")
        ->execute([$prevVal, $id]);

    // Registrar evento de previsão na timeline
    if ($prevVal) {
        $hora  = substr($prevVal, 11, 5); // HH:MM
        $texto = $row['prev_anterior'] ? 'Prev. atualizada para ' . $hora : 'Prev. às ' . $hora;
    } else {
        $texto = 'Previsão removida';
    }
    $pdo->prepare("INSERT INTO historico_ocorrencias (ocorrencia_id, oc, tipo, texto, usuario_id, usuario_nome) VALUES (?,?,'previsao',?,?,?)")
        ->execute([$id, $row['oc'], $texto, $user['id'], $user['nome']]);

    // Calcular status para resposta
    $status = null;
    $slaLim = null;
    if ($prevVal && $row['data_criacao']) {
        $criacaoTs = strtotime($row['data_criacao']);
        $slaLimTs  = $criacaoTs + (GPON_SLA_HORAS * 3600);
        $atenLimTs = $criacaoTs + (GPON_SLA_PROXIMO_HORAS * 3600);
        $slaLim    = date('Y-m-d H:i:s', $slaLimTs);
        $prevTs    = strtotime($prevVal);
        if ($prevTs > $slaLimTs)       $status = 'critica';
        elseif ($prevTs >= $atenLimTs) $status = 'atencao';
        else                           $status = 'ok';
    } elseif ($row['data_criacao']) {
        $slaLim = date('Y-m-d H:i:s', strtotime($row['data_criacao']) + GPON_SLA_HORAS * 3600);
    }

    gpon_json([
        'ok'                   => true,
        'previsao_finalizacao' => $prevVal,
        'previsao_status'      => $status,
        'sla_limite'           => $slaLim,
    ]);
}
