<?php
// ── API: CRUD Ocorrências ───────────────────────────────────────

// ── API: CRUD Ocorrência ───────────────────────────────────────
function gpon_api_ocorrencia_get(PDO $pdo, int $id): void
{
    gpon_require_login();
    $stmt = $pdo->prepare("SELECT * FROM ocorrencias WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) gpon_json(['ok' => false, 'message' => 'Não encontrado'], 404);
    gpon_json(['ok' => true, 'row' => gpon_enrich_row($row)]);
}

function gpon_api_ocorrencia_put(PDO $pdo, int $id, array $user): void
{
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $campos = ['ta','status','gpon','splitters','empresa','localidade','afetacao','observacoes_operacionais','data_encerramento'];
    $setParts = [];
    $params   = [];

    // Log de alterações
    $original = $pdo->prepare("SELECT * FROM ocorrencias WHERE id = ?");
    $original->execute([$id]);
    $orig = $original->fetch();
    if (!$orig) gpon_json(['ok' => false, 'message' => 'Não encontrado'], 404);

    $histStmt = $pdo->prepare("INSERT INTO historico_ocorrencias (ocorrencia_id, oc, campo, valor_anterior, valor_novo, tipo, texto, usuario_id, usuario_nome) VALUES (?,?,?,?,?,'edicao',?,?,?)");

    foreach ($campos as $campo) {
        if (!array_key_exists($campo, $body)) continue;
        $novoVal = $body[$campo] !== '' ? gpon_sanitize($body[$campo]) : null;

        if ($campo === 'data_encerramento' && $novoVal) {
            $novoVal = gpon_parse_datetime($novoVal) ?? $novoVal;
        }

        $setParts[] = "`$campo` = ?";
        $params[]   = $novoVal;

        $antigo = $orig[$campo] ?? null;
        if ((string)$antigo !== (string)($novoVal ?? '')) {
            $histStmt->execute([
                $id, $orig['oc'], $campo, $antigo, $novoVal,
                "Campo \"$campo\" alterado",
                $user['id'], $user['nome'],
            ]);
        }
    }

    if (!$setParts) gpon_json(['ok' => false, 'message' => 'Nenhum campo para atualizar']);

    // Recalcular uf se localidade foi alterada
    if (array_key_exists('localidade', $body)) {
        $novaLoc = $body['localidade'] !== '' ? gpon_sanitize($body['localidade']) : null;
        $novaUf  = gpon_localidade_uf($novaLoc);
        $setParts[] = '`uf` = ?';
        $params[]   = $novaUf;
    }

    // Recalcular aging_encerrados
    $newEnc = null;
    $newDataEnc = $body['data_encerramento'] ?? null;
    $dataCriacao = $orig['data_criacao'];
    if ($newDataEnc && $dataCriacao) {
        $newEnc = gpon_calc_aging_enc($dataCriacao, gpon_parse_datetime($newDataEnc));
    }
    if ($newEnc !== null) {
        $setParts[] = "`aging_encerrados` = ?";
        $params[]   = $newEnc;
    }

    $setParts[] = "`updated_at` = NOW()";
    $params[]   = $id;

    $pdo->prepare("UPDATE ocorrencias SET " . implode(', ', $setParts) . " WHERE id = ?")->execute($params);

    gpon_update_repetidas($pdo);
    gpon_json(['ok' => true]);
}

function gpon_api_ocorrencia_delete(PDO $pdo, int $id, array $user): void
{
    if (!in_array($user['nivel'], ['admin', 'backoffice'], true)) {
        gpon_json(['ok' => false, 'message' => 'Acesso negado'], 403);
    }

    $stmt = $pdo->prepare("SELECT oc FROM ocorrencias WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) gpon_json(['ok' => false, 'message' => 'Não encontrado'], 404);

    $pdo->prepare("INSERT INTO historico_ocorrencias (ocorrencia_id, oc, tipo, texto, usuario_id, usuario_nome) VALUES (?,?,'exclusao',?,?,?)")
        ->execute([$id, $row['oc'], 'Registro excluído por ' . $user['nome'], $user['id'], $user['nome']]);

    $pdo->prepare("DELETE FROM ocorrencias WHERE id = ?")->execute([$id]);

    gpon_json(['ok' => true]);
}
