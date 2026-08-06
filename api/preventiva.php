<?php

// ── Autorização granular do módulo Preventivo (Fase 2 da auditoria) ─────
// Papéis com autoridade de gestão sobre o fluxo (triagem, atribuição, aprovação,
// devolução, cancelamento). O parâmetro $preventiva é aceito para permitir, no
// futuro, escopo por equipe/supervisor_id (risco #7 da auditoria) — hoje ainda
// concede acesso irrestrito a todas as preventivas; decisão de Fase 3.
function gpon_preventiva_can_manage(array $user, ?array $preventiva = null): bool
{
    return gpon_user_has_role($user, ['admin', 'supervisor', 'backoffice']);
}

function gpon_preventiva_can_view(array $user, array $preventiva): bool
{
    if (gpon_preventiva_can_manage($user, $preventiva)) return true;
    if (($preventiva['tecnico_id'] ?? null) == ($user['id'] ?? null)) return true;
    if (($preventiva['supervisor_id'] ?? null) == ($user['id'] ?? null)) return true;
    if (($preventiva['criado_por'] ?? null) == ($user['id'] ?? null)) return true;
    return false;
}

// Usuário "tecnico" responsável especificamente por ESTA preventiva (não qualquer técnico).
function gpon_preventiva_is_tecnico_responsavel(array $user, array $preventiva): bool
{
    $tecnicoId = (int)($preventiva['tecnico_id'] ?? 0);
    return ($user['nivel'] ?? null) === 'tecnico'
        && $tecnicoId > 0
        && $tecnicoId === (int)($user['id'] ?? 0);
}

// Execução de campo: checklist, causa raiz, materiais, observação técnica, envio de fotos.
function gpon_preventiva_can_execute(array $user, array $preventiva): bool
{
    return gpon_preventiva_can_manage($user, $preventiva) || gpon_preventiva_is_tecnico_responsavel($user, $preventiva);
}

// Upload de evidências segue o mesmo escopo de quem executa a preventiva.
function gpon_preventiva_can_upload(array $user, array $preventiva): bool
{
    return gpon_preventiva_can_execute($user, $preventiva);
}

// Prioridade só é definida por quem gerencia o fluxo — nunca pelo técnico ou pelo criador comum.
function gpon_preventiva_can_change_priority(array $user, array $preventiva): bool
{
    return gpon_preventiva_can_manage($user, $preventiva);
}

// Atribuir/trocar supervisor_id ou tecnico_id.
function gpon_preventiva_can_assign(array $user, array $preventiva): bool
{
    return gpon_preventiva_can_manage($user, $preventiva);
}

function gpon_preventiva_can_approve(array $user, array $preventiva): bool
{
    return gpon_preventiva_can_manage($user, $preventiva);
}

function gpon_preventiva_can_return(array $user, array $preventiva): bool
{
    return gpon_preventiva_can_manage($user, $preventiva);
}

function gpon_preventiva_can_cancel(array $user, array $preventiva): bool
{
    return gpon_preventiva_can_manage($user, $preventiva);
}

// Criação: papéis operacionais podem solicitar uma preventiva; técnico de campo não cria.
function gpon_preventiva_can_create(array $user): bool
{
    return gpon_user_has_role($user, ['admin', 'backoffice', 'supervisor', 'operador']);
}

// Listagem de usuários internos (selects de atribuição de supervisor/técnico).
function gpon_preventiva_can_list_users(array $user): bool
{
    return gpon_preventiva_can_manage($user);
}

// ── Máquina de estados ───────────────────────────────────────────
// Mapa fromStatus => [toStatus => papéis extras, além de can_manage, que podem
// executar a transição]. A única transição aberta a um papel fora de
// admin/backoffice/supervisor é em_execucao → em_revisao para o "tecnico"
// responsável — e mesmo assim só verificado junto com gpon_preventiva_is_tecnico_responsavel().
function gpon_preventiva_transition_map(): array
{
    return [
        'aberta'      => ['triagem' => [], 'cancelada' => []],
        'triagem'     => ['em_execucao' => [], 'cancelada' => []],
        'em_execucao' => ['em_revisao' => ['tecnico'], 'cancelada' => []],
        'em_revisao'  => ['concluida' => [], 'em_execucao' => [], 'cancelada' => []],
        'concluida'   => [],
        'cancelada'   => [],
    ];
}

// Retorna null se a transição não existir na máquina de estados (estrutural — 422).
// Retorna a lista de papéis extras permitidos (além de can_manage) caso a transição exista.
function gpon_preventiva_transition_extra_roles(string $from, string $to): ?array
{
    $map = gpon_preventiva_transition_map();
    if (!array_key_exists($from, $map) || !array_key_exists($to, $map[$from])) {
        return null;
    }
    return $map[$from][$to];
}

// Verifica se o usuário pode executar especificamente esta transição de status
// (assume que a transição já foi validada estruturalmente por transition_extra_roles).
function gpon_preventiva_status_transition_allowed(array $user, array $preventiva, string $from, string $to): bool
{
    if (gpon_preventiva_can_manage($user, $preventiva)) return true;
    $extraRoles = gpon_preventiva_transition_extra_roles($from, $to);
    if ($extraRoles && in_array('tecnico', $extraRoles, true)) {
        return gpon_preventiva_is_tecnico_responsavel($user, $preventiva);
    }
    return false;
}

function gpon_preventiva_parse_payload(): array
{
    $input = file_get_contents('php://input');
    $data = [];
    if ($input !== false && $input !== '') {
        $decoded = json_decode($input, true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }
    if (empty($data)) {
        $data = $_POST;
    }
    return $data;
}

function gpon_preventiva_insert_history(PDO $pdo, int $preventivaId, ?string $origem, string $destino, array $user, ?string $observacao = null): void
{
    $stmt = $pdo->prepare("INSERT INTO preventivas_historico (preventiva_id, status_origem, status_destino, usuario_id, usuario_nome, observacao) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$preventivaId, $origem, $destino, $user['id'] ?? null, $user['nome'] ?? '', $observacao]);
}

function gpon_preventiva_list_users(PDO $pdo, array $user): void
{
    // Antes desta correção, qualquer usuário logado podia enumerar todos os
    // usuários internos (admin/supervisor/tecnico/backoffice) sem checagem alguma.
    if (!gpon_preventiva_can_list_users($user)) {
        gpon_json(['ok' => false, 'message' => 'Acesso negado.'], 403);
    }
    $stmt = $pdo->query("SELECT id, nome, usuario, nivel FROM usuarios WHERE status = 1 AND nivel IN ('admin','supervisor','tecnico','backoffice') ORDER BY nome ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    gpon_json(['ok' => true, 'data' => $rows]);
}

function gpon_preventiva_create(PDO $pdo, array $user): void
{
    // Antes desta correção, qualquer usuário logado podia criar preventivas e
    // definir livremente status/supervisor_id/tecnico_id na criação.
    if (!gpon_preventiva_can_create($user)) {
        gpon_json(['ok' => false, 'message' => 'Sem permissão para criar preventiva.'], 403);
    }

    $data = gpon_preventiva_parse_payload();
    $gpon = trim((string)($data['gpon'] ?? ''));
    $splitter = trim((string)($data['splitter'] ?? ''));
    if ($gpon === '' || $splitter === '') {
        gpon_json(['ok' => false, 'message' => 'GPON e Splitter são obrigatórios.'], 400);
    }

    $chave = strtoupper($gpon) . '|' . strtoupper($splitter);
    $activeStatus = ['aberta', 'triagem', 'em_execucao', 'em_revisao'];
    $stmt = $pdo->prepare("SELECT id, status FROM preventivas_rede WHERE chave_combinacao = ? AND status IN ('" . implode("','", $activeStatus) . "') ORDER BY id DESC LIMIT 1");
    $stmt->execute([$chave]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        gpon_json(['ok' => true, 'message' => 'Já existe uma preventiva ativa para esta combinação.', 'data' => ['preventiva_id' => (int)$existing['id'], 'status' => $existing['status']]]);
    }

    // Somente quem gerencia o fluxo (admin/backoffice/supervisor) pode definir o
    // status inicial ou já nascer com supervisor/técnico atribuídos. Um operador
    // que solicita a preventiva sempre entra em 'triagem', sem atribuições.
    $isGestor = gpon_preventiva_can_manage($user);

    $status = 'triagem';
    if ($isGestor && !empty($data['status'])) {
        $statusSolicitado = trim((string)$data['status']);
        if (!in_array($statusSolicitado, ['aberta', 'triagem'], true)) {
            gpon_json(['ok' => false, 'message' => 'Status inicial inválido. Use "aberta" ou "triagem".'], 422);
        }
        $status = $statusSolicitado;
    }

    $prioridade = trim((string)($data['prioridade'] ?? 'media')) ?: 'media';
    $obs = trim((string)($data['observacao_abertura'] ?? ''));
    $supervisorId = $isGestor && isset($data['supervisor_id']) && $data['supervisor_id'] !== '' ? (int)$data['supervisor_id'] : null;
    $tecnicoId    = $isGestor && isset($data['tecnico_id']) && $data['tecnico_id'] !== '' ? (int)$data['tecnico_id'] : null;

    $ins = $pdo->prepare("INSERT INTO preventivas_rede (
        gpon, splitter, chave_combinacao, uf, localidade, status, prioridade, origem_periodo, origem_total_ocorrencias, observacao_abertura, criado_por, supervisor_id, tecnico_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $ins->execute([
        $gpon,
        $splitter,
        $chave,
        trim((string)($data['uf'] ?? '')) ?: null,
        trim((string)($data['localidade'] ?? '')) ?: null,
        $status,
        $prioridade,
        trim((string)($data['origem_periodo'] ?? '')) ?: null,
        isset($data['origem_total_ocorrencias']) ? (int)$data['origem_total_ocorrencias'] : 0,
        $obs !== '' ? $obs : null,
        $user['id'] ?? null,
        $supervisorId,
        $tecnicoId,
    ]);

    $id = (int)$pdo->lastInsertId();
    gpon_preventiva_insert_history($pdo, $id, null, $status, $user, $obs !== '' ? $obs : 'Preventiva criada pela análise');

    gpon_json(['ok' => true, 'message' => 'Preventiva criada com sucesso.', 'data' => ['preventiva_id' => $id, 'status' => $status, 'gpon' => $gpon, 'splitter' => $splitter]]);
}

function gpon_preventiva_select_base(): string
{
    return "SELECT p.*, s.nome AS supervisor_nome, t.nome AS tecnico_nome, c.nome AS criado_por_nome
            FROM preventivas_rede p
            LEFT JOIN usuarios s ON s.id = p.supervisor_id
            LEFT JOIN usuarios t ON t.id = p.tecnico_id
            LEFT JOIN usuarios c ON c.id = p.criado_por";
}

function gpon_preventiva_list(PDO $pdo, array $user): void
{
    $base = gpon_preventiva_select_base();
    if (!gpon_preventiva_can_manage($user) && ($user['nivel'] ?? 'operador') !== 'tecnico') {
        $stmt = $pdo->prepare("{$base} WHERE p.criado_por = ? OR p.supervisor_id = ? OR p.tecnico_id = ? ORDER BY p.criado_em DESC");
        $stmt->execute([$user['id'] ?? 0, $user['id'] ?? 0, $user['id'] ?? 0]);
    } elseif ($user['nivel'] === 'tecnico') {
        $stmt = $pdo->prepare("{$base} WHERE p.tecnico_id = ? ORDER BY p.criado_em DESC");
        $stmt->execute([$user['id'] ?? 0]);
    } else {
        $stmt = $pdo->query("{$base} ORDER BY p.criado_em DESC");
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    gpon_json(['ok' => true, 'data' => $rows]);
}

function gpon_preventiva_get(PDO $pdo, int $id, array $user): void
{
    $stmt = $pdo->prepare(gpon_preventiva_select_base() . " WHERE p.id = ? LIMIT 1");
    $stmt->execute([$id]);
    $preventiva = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$preventiva) {
        gpon_json(['ok' => false, 'message' => 'Preventiva não encontrada.'], 404);
    }
    if (!gpon_preventiva_can_view($user, $preventiva)) {
        // 404 em vez de 403: não revela a existência da preventiva a quem não tem nenhum vínculo com ela.
        gpon_json(['ok' => false, 'message' => 'Preventiva não encontrada.'], 404);
    }
    $execStmt = $pdo->prepare("SELECT * FROM preventivas_execucao WHERE preventiva_id = ? LIMIT 1");
    $execStmt->execute([$id]);
    $execucao = $execStmt->fetch(PDO::FETCH_ASSOC);

    $filesStmt = $pdo->prepare("SELECT * FROM preventivas_arquivos WHERE preventiva_id = ? ORDER BY criado_em DESC");
    $filesStmt->execute([$id]);
    $arquivos = $filesStmt->fetchAll(PDO::FETCH_ASSOC);

    $histStmt = $pdo->prepare("SELECT h.*, u.nome AS usuario_nome_atual FROM preventivas_historico h LEFT JOIN usuarios u ON u.id = h.usuario_id WHERE h.preventiva_id = ? ORDER BY h.id DESC");
    $histStmt->execute([$id]);
    $historico = $histStmt->fetchAll(PDO::FETCH_ASSOC);

    gpon_json(['ok' => true, 'data' => [
        'preventiva' => $preventiva,
        'execucao'   => $execucao ?: null,
        'arquivos'   => $arquivos,
        'historico'  => $historico,
    ]]);
}

function gpon_preventiva_update(PDO $pdo, int $id, array $user): void
{
    // Antes desta correção, este único endpoint cobria triagem, prioridade,
    // atribuição de supervisor/técnico e TODAS as transições de status (aprovar,
    // devolver, cancelar, concluir) usando apenas can_view — ou seja, o próprio
    // criador ou o técnico responsável (que podem não ser gestores) conseguiam
    // aprovar/cancelar/reatribuir. Agora cada campo tem sua própria checagem, e
    // toda mudança de status passa pela máquina de estados.
    $data = gpon_preventiva_parse_payload();
    $stmt = $pdo->prepare("SELECT * FROM preventivas_rede WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $preventiva = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$preventiva) {
        gpon_json(['ok' => false, 'message' => 'Preventiva não encontrada.'], 404);
    }
    if (!gpon_preventiva_can_view($user, $preventiva)) {
        gpon_json(['ok' => false, 'message' => 'Preventiva não encontrada.'], 404);
    }

    $set = [];
    $vals = [];
    $historicoEntries = [];
    $statusAtual = (string)($preventiva['status'] ?? '');

    if (array_key_exists('prioridade', $data)) {
        if (!gpon_preventiva_can_change_priority($user, $preventiva)) {
            gpon_json(['ok' => false, 'message' => 'Sem permissão para alterar a prioridade.'], 403);
        }
        $novaPrioridade = trim((string)$data['prioridade']);
        if ($novaPrioridade !== (string)($preventiva['prioridade'] ?? '')) {
            $set[]  = 'prioridade = ?';
            $vals[] = $novaPrioridade;
            $historicoEntries[] = sprintf('Prioridade alterada de "%s" para "%s".', $preventiva['prioridade'] ?? '—', $novaPrioridade);
        }
    }

    if (array_key_exists('supervisor_id', $data)) {
        if (!gpon_preventiva_can_assign($user, $preventiva)) {
            gpon_json(['ok' => false, 'message' => 'Sem permissão para atribuir supervisor.'], 403);
        }
        $novoSupervisor  = ($data['supervisor_id'] !== '' && $data['supervisor_id'] !== null) ? (int)$data['supervisor_id'] : null;
        $supervisorAtual = $preventiva['supervisor_id'] !== null ? (int)$preventiva['supervisor_id'] : null;
        if ($novoSupervisor !== $supervisorAtual) {
            $set[]  = 'supervisor_id = ?';
            $vals[] = $novoSupervisor;
            $historicoEntries[] = sprintf('Supervisor alterado (usuário id %s → %s).', $supervisorAtual ?? '—', $novoSupervisor ?? '—');
        }
    }

    if (array_key_exists('tecnico_id', $data)) {
        if (!gpon_preventiva_can_assign($user, $preventiva)) {
            gpon_json(['ok' => false, 'message' => 'Sem permissão para atribuir técnico responsável.'], 403);
        }
        $novoTecnico  = ($data['tecnico_id'] !== '' && $data['tecnico_id'] !== null) ? (int)$data['tecnico_id'] : null;
        $tecnicoAtual = $preventiva['tecnico_id'] !== null ? (int)$preventiva['tecnico_id'] : null;
        if ($novoTecnico !== $tecnicoAtual) {
            $set[]  = 'tecnico_id = ?';
            $vals[] = $novoTecnico;
            $historicoEntries[] = sprintf('Técnico responsável alterado (usuário id %s → %s).', $tecnicoAtual ?? '—', $novoTecnico ?? '—');
        }
    }

    if (array_key_exists('observacao_abertura', $data)) {
        if (!gpon_preventiva_can_change_priority($user, $preventiva)) {
            gpon_json(['ok' => false, 'message' => 'Sem permissão para editar a observação de abertura.'], 403);
        }
        $novaObs = trim((string)$data['observacao_abertura']);
        if ($novaObs !== (string)($preventiva['observacao_abertura'] ?? '')) {
            $set[]  = 'observacao_abertura = ?';
            $vals[] = $novaObs;
            $historicoEntries[] = 'Observação de abertura editada.';
        }
    }

    $novoStatus = null;
    if (array_key_exists('status', $data) && trim((string)$data['status']) !== '' && trim((string)$data['status']) !== $statusAtual) {
        $novoStatus = trim((string)$data['status']);
        $extraRoles = gpon_preventiva_transition_extra_roles($statusAtual, $novoStatus);
        if ($extraRoles === null) {
            gpon_json(['ok' => false, 'message' => "Transição de status inválida: \"{$statusAtual}\" → \"{$novoStatus}\"."], 422);
        }
        if (!gpon_preventiva_status_transition_allowed($user, $preventiva, $statusAtual, $novoStatus)) {
            gpon_json(['ok' => false, 'message' => 'Sem permissão para esta transição de status.'], 403);
        }
        $set[]  = 'status = ?';
        $vals[] = $novoStatus;
        if ($novoStatus === 'concluida') {
            $set[]  = 'concluido_em = NOW()';
            $set[]  = 'concluido_por = ?';
            $vals[] = $user['id'] ?? null;
        }
    }

    if (empty($set)) {
        gpon_json(['ok' => true, 'message' => 'Nada para atualizar.']);
    }

    $vals[] = $id;
    $pdo->prepare("UPDATE preventivas_rede SET " . implode(', ', $set) . " WHERE id = ?")->execute($vals);

    // Histórico: registra tanto a transição de status quanto qualquer alteração de
    // campo sensível (prioridade/supervisor/técnico/observação), mesmo quando o
    // status permanece o mesmo — antes desta correção, mudanças sem transição de
    // status não deixavam nenhum rastro.
    if ($novoStatus !== null) {
        gpon_preventiva_insert_history($pdo, $id, $statusAtual, $novoStatus, $user, $data['observacao'] ?? null);
    }
    $statusRef = $novoStatus ?? $statusAtual;
    foreach ($historicoEntries as $texto) {
        gpon_preventiva_insert_history($pdo, $id, $statusRef, $statusRef, $user, $texto);
    }

    gpon_json(['ok' => true, 'message' => 'Preventiva atualizada.']);
}

function gpon_preventiva_execucao(PDO $pdo, int $id, array $user): void
{
    // Antes desta correção, este endpoint aceitava um campo "status" livre (não
    // apenas em_execucao/em_revisao), permitindo que quem só deveria executar a
    // preventiva (ex.: o próprio técnico) empurrasse qualquer status, inclusive
    // concluida/cancelada, por aqui.
    $data = gpon_preventiva_parse_payload();
    $stmt = $pdo->prepare("SELECT * FROM preventivas_rede WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $preventiva = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$preventiva) {
        gpon_json(['ok' => false, 'message' => 'Preventiva não encontrada.'], 404);
    }
    if (!gpon_preventiva_can_view($user, $preventiva)) {
        gpon_json(['ok' => false, 'message' => 'Preventiva não encontrada.'], 404);
    }
    if (!gpon_preventiva_can_execute($user, $preventiva)) {
        gpon_json(['ok' => false, 'message' => 'Sem permissão para registrar execução desta preventiva.'], 403);
    }

    $statusAtual = (string)($preventiva['status'] ?? '');
    if ($statusAtual !== 'em_execucao') {
        gpon_json(['ok' => false, 'message' => "Preventiva não está em execução (status atual: \"{$statusAtual}\")."], 422);
    }

    $novoStatus = trim((string)($data['status'] ?? 'em_execucao')) ?: 'em_execucao';
    if (!in_array($novoStatus, ['em_execucao', 'em_revisao'], true)) {
        gpon_json(['ok' => false, 'message' => 'Este endpoint só permite salvar rascunho (em_execucao) ou enviar para revisão (em_revisao).'], 422);
    }
    if ($novoStatus !== $statusAtual && !gpon_preventiva_status_transition_allowed($user, $preventiva, $statusAtual, $novoStatus)) {
        gpon_json(['ok' => false, 'message' => 'Sem permissão para enviar esta preventiva para revisão.'], 403);
    }

    $checklist = $data['checklist'] ?? [];
    $existingExec = $pdo->prepare("SELECT id FROM preventivas_execucao WHERE preventiva_id = ? LIMIT 1");
    $existingExec->execute([$id]);
    $execRow = $existingExec->fetch(PDO::FETCH_ASSOC);

    // enviado_revisao_em agora é sempre calculado no servidor a partir de $novoStatus
    // (validado acima) — antes, o cliente podia enviar esse timestamp livremente.
    $enviadoRevisaoEm = $novoStatus === 'em_revisao' ? date('Y-m-d H:i:s') : null;

    if ($execRow) {
        $stmt = $pdo->prepare("UPDATE preventivas_execucao SET causa_raiz = ?, acoes_realizadas = ?, itens_substituidos = ?, consumo_material = ?, observacao_tecnico = ?, checklist_json = ?, enviado_revisao_em = ? WHERE preventiva_id = ?");
        $stmt->execute([
            trim((string)($data['causa_raiz'] ?? '')) ?: null,
            trim((string)($data['acoes_realizadas'] ?? '')) ?: null,
            trim((string)($data['itens_substituidos'] ?? '')) ?: null,
            trim((string)($data['consumo_material'] ?? '')) ?: null,
            trim((string)($data['observacao_tecnico'] ?? '')) ?: null,
            $checklist ? json_encode($checklist, JSON_UNESCAPED_UNICODE) : null,
            $enviadoRevisaoEm,
            $id,
        ]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO preventivas_execucao (preventiva_id, causa_raiz, acoes_realizadas, itens_substituidos, consumo_material, observacao_tecnico, checklist_json, enviado_revisao_em) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $id,
            trim((string)($data['causa_raiz'] ?? '')) ?: null,
            trim((string)($data['acoes_realizadas'] ?? '')) ?: null,
            trim((string)($data['itens_substituidos'] ?? '')) ?: null,
            trim((string)($data['consumo_material'] ?? '')) ?: null,
            trim((string)($data['observacao_tecnico'] ?? '')) ?: null,
            $checklist ? json_encode($checklist, JSON_UNESCAPED_UNICODE) : null,
            $enviadoRevisaoEm,
        ]);
    }

    $pdo->prepare("UPDATE preventivas_rede SET status = ?, enviado_execucao_em = COALESCE(enviado_execucao_em, NOW()), enviado_revisao_em = ? WHERE id = ?")
        ->execute([$novoStatus, $enviadoRevisaoEm, $id]);

    if ($novoStatus !== $statusAtual) {
        gpon_preventiva_insert_history($pdo, $id, $statusAtual, $novoStatus, $user, $data['observacao'] ?? null);
    }
    gpon_json(['ok' => true, 'message' => 'Execução registrada.']);
}

function gpon_preventiva_upload_arquivo(PDO $pdo, int $id, array $user): void
{
    $stmt = $pdo->prepare("SELECT * FROM preventivas_rede WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $preventiva = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$preventiva) {
        gpon_json(['ok' => false, 'message' => 'Preventiva não encontrada.'], 404);
    }
    if (!gpon_preventiva_can_view($user, $preventiva)) {
        gpon_json(['ok' => false, 'message' => 'Preventiva não encontrada.'], 404);
    }
    if (!gpon_preventiva_can_upload($user, $preventiva)) {
        gpon_json(['ok' => false, 'message' => 'Sem permissão para enviar arquivos para esta preventiva.'], 403);
    }
    if (in_array($preventiva['status'], ['concluida', 'cancelada'], true)) {
        gpon_json(['ok' => false, 'message' => 'Preventiva já finalizada; não é possível enviar novos arquivos.'], 422);
    }

    if (empty($_FILES['arquivo']['tmp_name']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
        gpon_json(['ok' => false, 'message' => 'Nenhum arquivo enviado.'], 400);
    }

    $file = $_FILES['arquivo'];
    if ($file['size'] > 8 * 1024 * 1024) {
        gpon_json(['ok' => false, 'message' => 'Arquivo excede o limite de 8MB.'], 400);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        gpon_json(['ok' => false, 'message' => 'Somente imagens JPG, PNG ou WEBP são aceitas.'], 400);
    }
    if (@getimagesize($file['tmp_name']) === false) {
        gpon_json(['ok' => false, 'message' => 'Arquivo inválido: conteúdo não reconhecido como imagem.'], 400);
    }

    $tipo = trim((string)($_POST['tipo'] ?? 'evidencia'));
    if (!in_array($tipo, ['antes', 'depois', 'evidencia'], true)) $tipo = 'evidencia';

    $dir = __DIR__ . '/../uploads/preventivas/' . $id;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $filename = bin2hex(random_bytes(8)) . '.' . $ext;
    move_uploaded_file($file['tmp_name'], $dir . '/' . $filename);

    $caminho = 'uploads/preventivas/' . $id . '/' . $filename;
    $ins = $pdo->prepare("INSERT INTO preventivas_arquivos (preventiva_id, tipo, caminho_arquivo, nome_original, enviado_por) VALUES (?, ?, ?, ?, ?)");
    $ins->execute([$id, $tipo, $caminho, $file['name'], $user['id'] ?? null]);

    gpon_json(['ok' => true, 'message' => 'Arquivo enviado.', 'data' => [
        'id'            => (int)$pdo->lastInsertId(),
        'tipo'          => $tipo,
        'caminho_arquivo' => $caminho,
        'nome_original' => $file['name'],
    ]]);
}

function gpon_preventiva_delete_arquivo(PDO $pdo, int $id, int $fileId, array $user): void
{
    if (!gpon_preventiva_can_manage($user)) {
        gpon_json(['ok' => false, 'message' => 'Acesso negado.'], 403);
    }
    $stmt = $pdo->prepare("SELECT * FROM preventivas_arquivos WHERE id = ? AND preventiva_id = ? LIMIT 1");
    $stmt->execute([$fileId, $id]);
    $arquivo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$arquivo) {
        gpon_json(['ok' => false, 'message' => 'Arquivo não encontrado.'], 404);
    }
    $full = __DIR__ . '/../' . $arquivo['caminho_arquivo'];
    if (is_file($full)) @unlink($full);
    $pdo->prepare("DELETE FROM preventivas_arquivos WHERE id = ?")->execute([$fileId]);
    gpon_json(['ok' => true, 'message' => 'Arquivo removido.']);
}

function gpon_preventiva_routes(PDO $pdo, array $user, string $path, string $method): bool
{
    if ($path === '/api/preventiva/usuarios' && $method === 'GET') {
        gpon_preventiva_list_users($pdo, $user);
        return true;
    }

    if ($path === '/api/preventiva' && $method === 'GET') {
        gpon_preventiva_list($pdo, $user);
        return true;
    }

    if ($path === '/api/preventiva' && $method === 'POST') {
        gpon_preventiva_create($pdo, $user);
        return true;
    }

    if (preg_match('#^/api/preventiva/(\\d+)$#', $path, $m) && $method === 'GET') {
        gpon_preventiva_get($pdo, (int)$m[1], $user);
        return true;
    }

    if (preg_match('#^/api/preventiva/(\\d+)$#', $path, $m) && $method === 'PUT') {
        gpon_preventiva_update($pdo, (int)$m[1], $user);
        return true;
    }

    if (preg_match('#^/api/preventiva/(\\d+)/execucao$#', $path, $m) && $method === 'POST') {
        gpon_preventiva_execucao($pdo, (int)$m[1], $user);
        return true;
    }

    if (preg_match('#^/api/preventiva/(\\d+)/arquivos$#', $path, $m) && $method === 'POST') {
        gpon_preventiva_upload_arquivo($pdo, (int)$m[1], $user);
        return true;
    }

    if (preg_match('#^/api/preventiva/(\\d+)/arquivos/(\\d+)$#', $path, $m) && $method === 'DELETE') {
        gpon_preventiva_delete_arquivo($pdo, (int)$m[1], (int)$m[2], $user);
        return true;
    }

    return false;
}
