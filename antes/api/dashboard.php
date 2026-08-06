<?php
// ── API: Dashboard (data, stats, filters, reinc-counts) ────────

// ── Helper: status_prazo → condição SQL ────────────────────────
// Usa datetime do PHP (fuso America/Cuiaba) para evitar divergência com NOW() do MySQL
function gpon_status_prazo_sql(array $vals): string
{
    $slaM  = GPON_SLA_HORAS         * 60;
    $proxM = GPON_SLA_PROXIMO_HORAS * 60;
    $now   = date('Y-m-d H:i:s'); // horário Brasília (mesmo fuso dos dados)
    $parts = [];
    foreach ($vals as $v) {
        if ($v === 'Fora do Prazo') {
            $parts[] = "TIMESTAMPDIFF(MINUTE, data_criacao, '$now') >= $slaM";
        } elseif ($v === 'Atenção') {
            $parts[] = "(TIMESTAMPDIFF(MINUTE, data_criacao, '$now') >= $proxM AND TIMESTAMPDIFF(MINUTE, data_criacao, '$now') < $slaM)";
        } elseif ($v === 'Dentro do Prazo') {
            $parts[] = "TIMESTAMPDIFF(MINUTE, data_criacao, '$now') < $proxM";
        }
    }
    return empty($parts) ? '' : '(' . implode(' OR ', $parts) . ')';
}

// ── Contagem GPON+Splitter para reincidência ──────────────────
function gpon_comb_counts(PDO $pdo): array
{
    return gpon_comb_counts_period($pdo, '');
}

function gpon_comb_counts_period(PDO $pdo, string $period, bool $ocultarFibrasil = false): array
{
    if ($period === '7d')       $extra = " AND data_criacao >= NOW() - INTERVAL 7 DAY";
    elseif ($period === '15d')  $extra = " AND data_criacao >= NOW() - INTERVAL 15 DAY";
    elseif ($period === '30d')  $extra = " AND data_criacao >= NOW() - INTERVAL 30 DAY";
    else                        $extra = '';

    if ($ocultarFibrasil) $extra .= " AND empresa != 'FIBRASIL'";

    $rows = $pdo->query(
        "SELECT gpon, splitters FROM ocorrencias
         WHERE gpon IS NOT NULL AND gpon <> ''
           AND splitters IS NOT NULL AND splitters <> ''
           AND splitters REGEXP 'SP[0-9]'" . $extra
    )->fetchAll(\PDO::FETCH_NUM);

    $counts = [];
    foreach ($rows as [$gpon, $splitters]) {
        $gpon = trim($gpon);
        foreach (preg_split('/\s*,\s*/', trim($splitters)) as $rawSp) {
            $rawSp = trim($rawSp);
            if ($rawSp === '') continue;
            $sp = gpon_extract_main_sp($rawSp);
            if ($sp !== '') {
                $key = $gpon . '|||' . $sp;
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }
    }
    return $counts;
}

function gpon_api_reinc_counts(PDO $pdo): void
{
    gpon_require_login();
    $period = trim($_GET['period'] ?? '');
    if (!in_array($period, ['', '7d', '15d', '30d'], true)) $period = '';
    $ocultarFibrasil = !empty($_GET['ocultar_fibrasil']);
    gpon_json(['ok' => true, 'counts' => gpon_comb_counts_period($pdo, $period, $ocultarFibrasil)]);
}

// ── Reincidência recente: retorna mapa id → ['tipo','horas'] ──
// Para cada OC ativa, verifica se há OC encerrada do mesmo GPON+SP
// fechada até 72h antes da abertura desta OC.
function gpon_reincidencia_recente_map(PDO $pdo, array $activeRows): array
{
    if (empty($activeRows)) return [];

    // Coleta GPONs únicos e janela de tempo mínima
    $gpons = [];
    $minCriacao = null;
    foreach ($activeRows as $r) {
        $g = trim($r['gpon'] ?? '');
        if ($g !== '') $gpons[$g] = true;
        $dc = $r['data_criacao'] ?? null;
        if ($dc !== null && ($minCriacao === null || $dc < $minCriacao)) $minCriacao = $dc;
    }
    if (empty($gpons) || $minCriacao === null) return [];

    // Busca OCs encerradas dos mesmos GPONs, fechadas até 72h antes da OC ativa mais antiga
    $inPh   = implode(',', array_fill(0, count($gpons), '?'));
    $cutoff = date('Y-m-d H:i:s', strtotime($minCriacao) - 72 * 3600);
    $params = array_values(array_keys($gpons));
    $params[] = $cutoff;

    $stmt = $pdo->prepare(
        "SELECT gpon, splitters, data_encerramento
         FROM ocorrencias
         WHERE gpon IN ($inPh)
         AND data_encerramento IS NOT NULL
         AND data_encerramento >= ?
         AND (status != 'Ativo')"
    );
    $stmt->execute($params);
    $closedRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Indexa encerradas por GPON → lista de [mainSps[], data_encerramento]
    $closedByGpon = [];
    foreach ($closedRows as $cr) {
        $g = trim($cr['gpon'] ?? '');
        if ($g === '') continue;
        $mainSps = [];
        foreach (preg_split('/\s*,\s*/', trim($cr['splitters'] ?? '')) as $raw) {
            $raw = trim($raw);
            if ($raw === '') continue;
            $sp = gpon_extract_main_sp($raw);
            if ($sp !== '') $mainSps[] = $sp;
        }
        if (!empty($mainSps)) {
            $closedByGpon[$g][] = [
                'sps'  => array_unique($mainSps),
                'enc'  => $cr['data_encerramento'],
            ];
        }
    }

    // Para cada OC ativa, encontra a reincidência mais crítica
    $result = [];
    foreach ($activeRows as $r) {
        $id   = $r['id'] ?? null;
        if ($id === null) continue;
        $g    = trim($r['gpon']      ?? '');
        $sRaw = trim($r['splitters'] ?? '');
        $dc   = $r['data_criacao']   ?? null;
        if ($g === '' || $sRaw === '' || $dc === null) continue;

        $activeSps = [];
        foreach (preg_split('/\s*,\s*/', $sRaw) as $raw) {
            $raw = trim($raw);
            if ($raw === '') continue;
            $sp = gpon_extract_main_sp($raw);
            if ($sp !== '') $activeSps[] = $sp;
        }
        $activeSps = array_unique($activeSps);

        $bestHoras = null;
        foreach ($closedByGpon[$g] ?? [] as $closed) {
            // Verifica sobreposição de SPs
            if (empty(array_intersect($activeSps, $closed['sps']))) continue;
            $enc = $closed['enc'];
            if ($enc >= $dc) continue; // encerramento APÓS abertura: ignorar
            $horas = (int)round((strtotime($dc) - strtotime($enc)) / 3600);
            if ($horas > 72) continue;
            if ($bestHoras === null || $horas < $bestHoras) $bestHoras = $horas;
        }

        if ($bestHoras !== null) {
            $result[$id] = [
                'tipo'  => $bestHoras < 24 ? 'critica' : 'recente',
                'horas' => $bestHoras,
            ];
        }
    }
    return $result;
}

// ── API: Dados (DataTables) ────────────────────────────────────
function gpon_api_data(PDO $pdo): void
{
    gpon_require_login();

    // Tela principal: Ativos por padrão; opcionalmente inclui Fechados
    $inclEnc = !empty($_GET['incluir_encerradas']);
    $where   = [$inclEnc ? "status IN ('Ativo','Fechado')" : "status = 'Ativo'"];
    $params  = [];

    if (!empty($_GET['ocultar_fibrasil'])) {
        $where[] = "empresa != 'FIBRASIL'";
    }

    $filterFields = ['empresa', 'localidade', 'gpon', 'uf'];
    foreach ($filterFields as $f) {
        $raw = $_GET[$f] ?? '';
        if ($raw === '') continue;
        $vals = array_filter(explode('|||', $raw), fn($v) => $v !== '');
        if (empty($vals)) continue;
        $ph = implode(',', array_fill(0, count($vals), '?'));
        $where[]  = "`$f` IN ($ph)";
        $params   = array_merge($params, array_values($vals));
    }

    // status_prazo: campo computado — traduz para TIMESTAMPDIFF
    $spRaw = $_GET['status_prazo'] ?? '';
    if ($spRaw !== '') {
        $spVals = array_filter(explode('|||', $spRaw), fn($v) => $v !== '');
        $spSql  = gpon_status_prazo_sql(array_values($spVals));
        if ($spSql !== '') $where[] = $spSql;
    }

    $sql = "SELECT o.*, COALESCE(hc.cnt, 0) AS tem_comentarios
            FROM ocorrencias o
            LEFT JOIN (
                SELECT ocorrencia_id, COUNT(*) AS cnt
                FROM historico_ocorrencias
                WHERE tipo = 'comentario'
                GROUP BY ocorrencia_id
            ) hc ON hc.ocorrencia_id = o.id
            WHERE " . implode(' AND ', $where);
    $sql .= " ORDER BY o.data_criacao DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $combCounts   = gpon_comb_counts_period($pdo, '', !empty($_GET['ocultar_fibrasil']));
    $recenteMap   = gpon_reincidencia_recente_map($pdo, $rows);

    foreach ($rows as &$row) {
        $row = gpon_enrich_row($row);

        $gpon      = trim($row['gpon']      ?? '');
        $splitters = trim($row['splitters'] ?? '');
        $detail    = [];
        $maxCount  = 0;

        if ($gpon !== '' && $splitters !== '') {
            foreach (preg_split('/\s*,\s*/', $splitters) as $rawSp) {
                $rawSp = trim($rawSp);
                if ($rawSp === '') continue;
                $sp = gpon_extract_main_sp($rawSp);
                if ($sp === '') continue;
                $cnt      = $combCounts[$gpon . '|||' . $sp] ?? 0;
                $detail[] = ['sp' => $sp, 'cnt' => $cnt];
                if ($cnt > $maxCount) $maxCount = $cnt;
            }
        }

        $row['reincidencia_max']     = $maxCount;
        $row['reincidencia_detail']  = $detail;
        $row['reincidencia_recente'] = $recenteMap[$row['id']] ?? null;
    }
    unset($row);

    gpon_json(['ok' => true, 'rows' => $rows, 'total' => count($rows)]);
}

// ── API: Estatísticas (KPIs) ───────────────────────────────────
function gpon_api_stats(PDO $pdo): void
{
    gpon_require_login();

    // Tela principal: somente registros Ativos
    $where  = ["status = 'Ativo'"];
    $params = [];

    if (!empty($_GET['ocultar_fibrasil'])) {
        $where[] = "empresa != 'FIBRASIL'";
    }

    $filterFields = ['empresa', 'localidade', 'gpon', 'uf'];
    foreach ($filterFields as $f) {
        $raw = $_GET[$f] ?? '';
        if ($raw === '') continue;
        $vals = array_filter(explode('|||', $raw), fn($v) => $v !== '');
        if (empty($vals)) continue;
        $ph = implode(',', array_fill(0, count($vals), '?'));
        $where[]  = "`$f` IN ($ph)";
        $params   = array_merge($params, array_values($vals));
    }

    // status_prazo: campo computado — traduz para TIMESTAMPDIFF
    $spRaw = $_GET['status_prazo'] ?? '';
    if ($spRaw !== '') {
        $spVals = array_filter(explode('|||', $spRaw), fn($v) => $v !== '');
        $spSql  = gpon_status_prazo_sql(array_values($spVals));
        if ($spSql !== '') $where[] = $spSql;
    }

    $whereSql = ' WHERE ' . implode(' AND ', $where);

    $stmt = $pdo->prepare("SELECT status, data_criacao, data_encerramento, aging_encerrados, repetida FROM ocorrencias$whereSql");
    $stmt->execute($params);
    $allRows = $stmt->fetchAll();

    $abertas = $encerradas = $dentroPrazo = $proximoPrazo = $foraPrazo = $repetidas = 0;

    foreach ($allRows as $r) {
        $enc  = gpon_is_encerrada($r['status']);
        $aging_a = gpon_calc_aging_horas($r['data_criacao']);
        $aging_e = $r['aging_encerrados'] ?? gpon_calc_aging_enc($r['data_criacao'], $r['data_encerramento']);
        $sp   = gpon_status_prazo($r['status'], $aging_a, $aging_e);

        if ($enc) $encerradas++;
        else       $abertas++;

        if ($sp === 'Dentro do Prazo') $dentroPrazo++;
        elseif ($sp === 'Atenção')     $proximoPrazo++;
        elseif ($sp === 'Fora do Prazo') $foraPrazo++;

        if ($r['repetida']) $repetidas++;
    }

    // Breakdown por empresa (respeita os mesmos filtros)
    $empWhere = array_merge($where, ["empresa IS NOT NULL", "empresa <> ''"]);
    $empSql   = "SELECT empresa, COUNT(*) AS count FROM ocorrencias WHERE " . implode(' AND ', $empWhere) . " GROUP BY empresa ORDER BY count DESC";
    $empStmt  = $pdo->prepare($empSql);
    $empStmt->execute($params);
    $empresas = $empStmt->fetchAll();

    // Reincidência:
    // - contagem GLOBAL (igual a gpon_comb_counts, mesma lógica da coluna "Repetida")
    // - mas só considera combos presentes no contexto filtrado atual
    // Assim: identifica o pior combo do filtro + mostra o mesmo número que a tabela

    // Passo 1: contagens e datas globais (todos os registros, sem filtro de período/empresa ativo)
    $fibExclusao  = !empty($_GET['ocultar_fibrasil']) ? " AND empresa != 'FIBRASIL'" : '';
    $globalRows   = $pdo->query(
        "SELECT gpon, splitters, data_criacao FROM ocorrencias
         WHERE gpon IS NOT NULL AND gpon <> ''
           AND splitters IS NOT NULL AND splitters <> ''
           AND splitters REGEXP 'SP[0-9]'" . $fibExclusao
    )->fetchAll(PDO::FETCH_ASSOC);
    $globalCounts = [];
    $globalDates  = [];
    foreach ($globalRows as $row) {
        $g = trim($row['gpon']);
        if ($g === '') continue;
        foreach (preg_split('/\s*,\s*/', trim($row['splitters'])) as $rawSp) {
            $rawSp = trim($rawSp);
            if ($rawSp === '') continue;
            $sp = gpon_extract_main_sp($rawSp);
            if ($sp === '') continue;
            $key = $g . '|||' . $sp;
            $globalCounts[$key] = ($globalCounts[$key] ?? 0) + 1;
            if ($row['data_criacao']) $globalDates[$key][] = $row['data_criacao'];
        }
    }
    unset($globalRows);

    // Passo 2: combos presentes no contexto filtrado (status='Ativo' + filtros do usuário)
    $splWhere = array_merge($where, ["gpon IS NOT NULL", "gpon <> ''", "splitters IS NOT NULL", "splitters <> ''", "splitters REGEXP 'SP[0-9]'"]);
    $filtStmt = $pdo->prepare("SELECT gpon, splitters, data_criacao FROM ocorrencias WHERE " . implode(' AND ', $splWhere));
    $filtStmt->execute($params);
    $filtCombos = []; // key => [data_criacao, ...]
    foreach ($filtStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $g = trim($row['gpon'] ?? '');
        if ($g === '') continue;
        foreach (preg_split('/\s*,\s*/', trim($row['splitters'] ?? '')) as $rawSp) {
            $rawSp = trim($rawSp);
            if ($rawSp === '') continue;
            $sp = gpon_extract_main_sp($rawSp);
            if ($sp === '') continue;
            if ($row['data_criacao'] ?? null) {
                $filtCombos[$g . '|||' . $sp][] = $row['data_criacao'];
            } else {
                $filtCombos[$g . '|||' . $sp] = $filtCombos[$g . '|||' . $sp] ?? [];
            }
        }
    }

    // Passo 3: dentre os combos filtrados, qual tem maior contagem global?
    $reincidencia = null;
    if ($filtCombos) {
        $topKey    = null;
        $topGlobal = 0;
        foreach ($filtCombos as $key => $_) {
            $cnt = $globalCounts[$key] ?? 0;
            if ($cnt > $topGlobal) { $topGlobal = $cnt; $topKey = $key; }
        }
        if ($topKey !== null) {
            [$topGpon, $topSp] = explode('|||', $topKey, 2);
            $datas = $globalDates[$topKey] ?? [];
            rsort($datas);
            $reincidencia = [
                'gpon'                 => $topGpon,
                'sp'                   => $topSp,
                'total'                => $topGlobal,
                'criticidade'          => $topGlobal >= 10 ? 'alta' : ($topGlobal >= 5 ? 'media' : 'baixa'),
                'penultima_ocorrencia' => $datas[1] ?? null,
            ];
        }
    }

    gpon_json([
        'ok'    => true,
        'stats' => [
            'total'         => $abertas + $encerradas,
            'abertas'       => $abertas,
            'encerradas'    => $encerradas,
            'dentro_prazo'  => $dentroPrazo,
            'proximo_prazo' => $proximoPrazo,
            'fora_prazo'    => $foraPrazo,
            'repetidas'     => $repetidas,
            'empresas'      => $empresas,
            'reincidencia'  => $reincidencia,
        ],
    ]);
}

// ── API: Filtros ───────────────────────────────────────────────
function gpon_api_filters(PDO $pdo): void
{
    gpon_require_login();

    // Parse filtros ativos (mesmo formato que gpon_api_data)
    $activeFilters = [];
    foreach (['empresa', 'localidade', 'gpon', 'uf'] as $f) {
        $raw = $_GET[$f] ?? '';
        if ($raw === '') continue;
        $vals = array_values(array_filter(explode('|||', $raw), fn($v) => $v !== ''));
        if (!empty($vals)) $activeFilters[$f] = $vals;
    }
    $spVals = [];
    $spRaw  = $_GET['status_prazo'] ?? '';
    if ($spRaw !== '') {
        $spVals = array_values(array_filter(explode('|||', $spRaw), fn($v) => $v !== ''));
    }

    $ocultarFibrasil = !empty($_GET['ocultar_fibrasil']);

    // Monta WHERE + params excluindo a dimensão $excludeKey (cross-filter)
    $buildWhere = function(string $excludeKey) use ($activeFilters, $spVals, $ocultarFibrasil): array {
        $where  = ["status = 'Ativo'"];
        $params = [];
        if ($ocultarFibrasil) {
            $where[] = "empresa != 'FIBRASIL'";
        }
        foreach ($activeFilters as $key => $vals) {
            if ($key === $excludeKey) continue;
            $ph      = implode(',', array_fill(0, count($vals), '?'));
            $where[] = "`$key` IN ($ph)";
            $params  = array_merge($params, $vals);
        }
        if ($excludeKey !== 'status_prazo' && !empty($spVals)) {
            $spSql = gpon_status_prazo_sql($spVals);
            if ($spSql !== '') $where[] = $spSql;
        }
        return [$where, $params];
    };

    $slaM  = GPON_SLA_HORAS         * 60;
    $proxM = GPON_SLA_PROXIMO_HORAS * 60;
    $now   = date('Y-m-d H:i:s');
    $out   = [];

    // empresa, localidade, gpon — contagem sem o próprio filtro da dimensão
    foreach (['empresa', 'localidade', 'gpon'] as $f) {
        [$where, $params] = $buildWhere($f);
        $wStr  = implode(' AND ', $where) . " AND `$f` IS NOT NULL AND `$f` <> ''";
        $stmt  = $pdo->prepare("SELECT `$f` AS value, COUNT(*) AS count FROM ocorrencias WHERE $wStr GROUP BY `$f` ORDER BY count DESC");
        $stmt->execute($params);
        $out[$f] = $stmt->fetchAll();
    }

    // UF
    [$where, $params] = $buildWhere('uf');
    $wStr = implode(' AND ', $where) . " AND uf IN ('MT','MS','DF','GO')";
    $stmt = $pdo->prepare("SELECT uf AS value, COUNT(*) AS count FROM ocorrencias WHERE $wStr GROUP BY uf ORDER BY uf ASC");
    $stmt->execute($params);
    $out['uf'] = $stmt->fetchAll();

    // Status Prazo (campo computado)
    [$where, $params] = $buildWhere('status_prazo');
    $wStr = implode(' AND ', $where);
    $stmt = $pdo->prepare("
        SELECT
            CASE
                WHEN TIMESTAMPDIFF(MINUTE, data_criacao, '$now') >= $slaM  THEN 'Fora do Prazo'
                WHEN TIMESTAMPDIFF(MINUTE, data_criacao, '$now') >= $proxM THEN 'Atenção'
                ELSE 'Dentro do Prazo'
            END AS value,
            COUNT(*) AS count
        FROM ocorrencias
        WHERE $wStr
        GROUP BY value
        ORDER BY FIELD(value, 'Fora do Prazo', 'Atenção', 'Dentro do Prazo')
    ");
    $stmt->execute($params);
    $out['status_prazo'] = $stmt->fetchAll();

    $out['ok'] = true;
    gpon_json($out);
}
