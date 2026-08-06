<?php
// ── API: Gerenciamento de Mapeamento GPON ──────────────────────

/**
 * GET /api/gpon-empresas
 * Retorna lista paginada de mapeamentos GPON -> Empresa
 */
function gpon_api_gpon_empresas_list(PDO $pdo): void
{
    gpon_require_login();

    $page    = max(1, (int)($_GET['page']   ?? 1));
    $search  = trim($_GET['search'] ?? '');
    $uf      = strtoupper(trim($_GET['uf']  ?? ''));
    $perPage = 20;
    $offset  = ($page - 1) * $perPage;

    $where  = [];
    $params = [];

    if ($search !== '') {
        $wildcard = '%' . $search . '%';
        $where[]  = "(gpon LIKE ? OR empresa LIKE ?)";
        $params   = [$wildcard, $wildcard];
    }

    if ($uf !== '' && $uf !== 'TODOS') {
        $where[]  = "gpon LIKE ?";
        $params[] = $uf . '%';
    }

    $whereClause = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
    $sql   = "SELECT * FROM gpon_empresas"          . $whereClause;
    $sqlCt = "SELECT COUNT(*) FROM gpon_empresas"   . $whereClause;

    try {
        // Contadores por UF (sempre sobre o total, sem filtros de busca/UF)
        $ufCounts = [];
        $ufRows   = $pdo->query("SELECT LEFT(gpon,2) AS uf, COUNT(*) AS cnt FROM gpon_empresas GROUP BY LEFT(gpon,2) ORDER BY uf")->fetchAll();
        foreach ($ufRows as $r) {
            $ufCounts[$r['uf']] = (int)$r['cnt'];
        }

        $cntStmt = $pdo->prepare($sqlCt);
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $sql .= " ORDER BY gpon ASC LIMIT ? OFFSET ?";
        $stmt = $pdo->prepare($sql);
        $pos  = 1;
        foreach ($params as $val) {
            $stmt->bindValue($pos++, $val);
        }
        $stmt->bindValue($pos++, $perPage, PDO::PARAM_INT);
        $stmt->bindValue($pos,   $offset,  PDO::PARAM_INT);
        $stmt->execute();

        gpon_json([
            'ok'        => true,
            'items'     => $stmt->fetchAll(),
            'total'     => $total,
            'page'      => $page,
            'pages'     => (int)ceil($total / $perPage),
            'uf_counts' => $ufCounts,
        ]);
    } catch (\PDOException $e) {
        gpon_json(['ok' => false, 'message' => 'Tabela gpon_empresas não encontrada. Recarregue a página para criá-la.'], 500);
    }
}

/**
 * POST /api/gpon-empresas
 * Cria novo mapeamento GPON -> Empresa
 */
function gpon_api_gpon_empresas_create(PDO $pdo, array $user): void
{
    gpon_require_login();
    if ($user['nivel'] !== 'admin') {
        gpon_json(['ok' => false, 'message' => 'Apenas administradores'], 403);
    }

    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $gpon   = strtoupper(trim($body['gpon'] ?? ''));
    $empresa = trim($body['empresa'] ?? '');

    if (!$gpon)    gpon_json(['ok' => false, 'message' => 'GPON é obrigatório']);
    if (!$empresa) gpon_json(['ok' => false, 'message' => 'Empresa é obrigatória']);

    if (!preg_match('/^[A-Z0-9_]+$/', $gpon)) {
        gpon_json(['ok' => false, 'message' => 'GPON inválido (apenas letras, números e _)']);
    }

    if (!in_array($empresa, ['ABILITY', 'ONDACOM', 'VIVO'], true)) {
        gpon_json(['ok' => false, 'message' => 'Empresa deve ser ABILITY, ONDACOM ou VIVO']);
    }

    try {
        $pdo->prepare("INSERT INTO gpon_empresas (gpon, empresa) VALUES (?, ?)")
            ->execute([$gpon, $empresa]);
        gpon_json(['ok' => true, 'message' => 'GPON cadastrado com sucesso']);
    } catch (\PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            gpon_json(['ok' => false, 'message' => 'GPON já cadastrado.']);
        }
        gpon_json(['ok' => false, 'message' => 'Erro ao cadastrar']);
    }
}

/**
 * PUT /api/gpon-empresas/:id
 * Atualiza mapeamento GPON -> Empresa
 */
function gpon_api_gpon_empresas_update(PDO $pdo, int $id, array $user): void
{
    gpon_require_login();
    if ($user['nivel'] !== 'admin') {
        gpon_json(['ok' => false, 'message' => 'Apenas administradores'], 403);
    }

    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $empresa = trim($body['empresa'] ?? '');

    if (!$empresa) gpon_json(['ok' => false, 'message' => 'Empresa é obrigatória']);

    if (!in_array($empresa, ['ABILITY', 'ONDACOM', 'VIVO'], true)) {
        gpon_json(['ok' => false, 'message' => 'Empresa deve ser ABILITY, ONDACOM ou VIVO']);
    }

    $stmt = $pdo->prepare("SELECT id FROM gpon_empresas WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        gpon_json(['ok' => false, 'message' => 'Mapeamento não encontrado'], 404);
    }

    $pdo->prepare("UPDATE gpon_empresas SET empresa = ? WHERE id = ?")
        ->execute([$empresa, $id]);

    gpon_json(['ok' => true, 'message' => 'Mapeamento atualizado']);
}

/**
 * DELETE /api/gpon-empresas/:id
 * Remove mapeamento GPON -> Empresa
 */
function gpon_api_gpon_empresas_delete(PDO $pdo, int $id, array $user): void
{
    gpon_require_login();
    if ($user['nivel'] !== 'admin') {
        gpon_json(['ok' => false, 'message' => 'Apenas administradores'], 403);
    }

    $stmt = $pdo->prepare("SELECT id FROM gpon_empresas WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        gpon_json(['ok' => false, 'message' => 'Mapeamento não encontrado'], 404);
    }

    $pdo->prepare("DELETE FROM gpon_empresas WHERE id = ?")
        ->execute([$id]);

    gpon_json(['ok' => true, 'message' => 'Mapeamento removido']);
}

/**
 * GET /api/gpon-nao-mapeados
 * Retorna list de GPONs encontrados nas ocorrências que não possuem mapeamento
 */
function gpon_api_gpon_nao_mapeados(PDO $pdo): void
{
    gpon_require_login();

    try {
        $stmt = $pdo->query("
            SELECT DISTINCT o.gpon
            FROM ocorrencias o
            LEFT JOIN gpon_empresas g ON o.gpon = g.gpon
            WHERE o.gpon IS NOT NULL
              AND o.gpon <> ''
              AND g.id IS NULL
            ORDER BY o.gpon ASC
        ");

        $gpons = array_map(fn($row) => $row['gpon'], $stmt->fetchAll());

        gpon_json(['ok' => true, 'gpons' => $gpons, 'total' => count($gpons)]);
    } catch (\PDOException $e) {
        gpon_json(['ok' => false, 'message' => 'Tabela gpon_empresas não encontrada. Recarregue a página.'], 500);
    }
}
