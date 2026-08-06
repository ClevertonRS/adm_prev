<?php
// ── API: Análise de Reincidência ────────────────────────────────

// ── API: Análise de Reincidência ───────────────────────────────
function gpon_api_analise(PDO $pdo): void
{
    gpon_require_admin_or_backoffice_api();

    $periodo = trim($_GET['periodo'] ?? '');
    $inicio  = null;
    $fim     = null;
    $tz  = new \DateTimeZone('America/Sao_Paulo');
    $now = new \DateTime('now', $tz);

    switch ($periodo) {
        case '24h':
            $inicio = (clone $now)->modify('-24 hours')->format('Y-m-d H:i:s');
            $fim    = $now->format('Y-m-d H:i:s');
            break;
        case 'hoje':
            $inicio = $now->format('Y-m-d') . ' 00:00:00';
            $fim    = $now->format('Y-m-d') . ' 23:59:59';
            break;
        case 'ontem':
            $d      = (clone $now)->modify('-1 day');
            $inicio = $d->format('Y-m-d') . ' 00:00:00';
            $fim    = $d->format('Y-m-d') . ' 23:59:59';
            break;
        case '7d':
            $inicio = (clone $now)->modify('-7 days')->format('Y-m-d') . ' 00:00:00';
            $fim    = $now->format('Y-m-d') . ' 23:59:59';
            break;
        case '15d':
            $inicio = (clone $now)->modify('-15 days')->format('Y-m-d') . ' 00:00:00';
            $fim    = $now->format('Y-m-d') . ' 23:59:59';
            break;
        case '30d':
            $inicio = (clone $now)->modify('-30 days')->format('Y-m-d') . ' 00:00:00';
            $fim    = $now->format('Y-m-d') . ' 23:59:59';
            break;
        case 'custom':
            $dIni = trim($_GET['inicio'] ?? '');
            $dFim = trim($_GET['fim']    ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dIni)) $inicio = $dIni . ' 00:00:00';
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dFim))  $fim    = $dFim  . ' 23:59:59';
            break;
        // caso vazio: sem filtro de data
    }

    $extra = [];
    if (($v = strtoupper(trim($_GET['uf'] ?? ''))) !== '' && in_array($v, ['MT', 'MS', 'DF', 'GO'], true))
        $extra['uf'] = $v;
    if (($v = trim($_GET['gpon']               ?? '')) !== '') $extra['gpon']               = $v;
    if (($v = trim($_GET['sp']                 ?? '')) !== '') $extra['sp']                 = $v;
    if (($v = trim($_GET['baixa_causa']        ?? '')) !== '') $extra['baixa_causa']        = $v;
    if (!empty($_GET['ocultar_improcedentes']))                  $extra['ocultar_improcedentes'] = true;
    if (!empty($_GET['ocultar_fibrasil']))                       $extra['ocultar_fibrasil']       = true;

    gpon_json(gpon_analise_data($pdo, $inicio, $fim, $extra));
}

// ── API: Histórico por GPON + SP ───────────────────────────────
function gpon_api_analise_historico(PDO $pdo): void
{
    gpon_require_admin_or_backoffice_api();

    $gpon        = trim($_GET['gpon']        ?? '');
    $sp          = trim($_GET['sp']          ?? '');
    $cidade      = trim($_GET['cidade']      ?? '');
    $baixa_causa = trim($_GET['baixa_causa'] ?? '');
    $baixa_reparo= trim($_GET['baixa_reparo']?? '');

    if ($gpon === '' && $cidade === '' && $baixa_causa === '' && $baixa_reparo === '') {
        gpon_json(['error' => 'gpon, cidade, baixa_causa ou baixa_reparo é obrigatório']);
        return;
    }

    $tz  = new \DateTimeZone('America/Sao_Paulo');
    $now = new \DateTime('now', $tz);

    $where  = [];
    $params = [];

    if ($gpon !== '') {
        $where[]  = 'gpon = ?';
        $params[] = $gpon;
    }
    if ($sp !== '') {
        $where[]  = 'splitters REGEXP ?';
        $params[] = gpon_sp_regexp($sp);
    }
    if ($cidade !== '') {
        $where[]  = 'localidade = ?';
        $params[] = $cidade;
    }

    $periodo = trim($_GET['periodo'] ?? '');
    $inicio  = null;
    $fim     = null;
    switch ($periodo) {
        case '24h':
            $inicio = (clone $now)->modify('-24 hours')->format('Y-m-d H:i:s');
            $fim    = $now->format('Y-m-d H:i:s');
            break;
        case 'hoje':
            $inicio = $now->format('Y-m-d') . ' 00:00:00';
            $fim    = $now->format('Y-m-d') . ' 23:59:59';
            break;
        case 'ontem':
            $d      = (clone $now)->modify('-1 day');
            $inicio = $d->format('Y-m-d') . ' 00:00:00';
            $fim    = $d->format('Y-m-d') . ' 23:59:59';
            break;
        case '7d':
            $inicio = (clone $now)->modify('-7 days')->format('Y-m-d') . ' 00:00:00';
            $fim    = $now->format('Y-m-d') . ' 23:59:59';
            break;
        case '15d':
            $inicio = (clone $now)->modify('-15 days')->format('Y-m-d') . ' 00:00:00';
            $fim    = $now->format('Y-m-d') . ' 23:59:59';
            break;
        case '30d':
            $inicio = (clone $now)->modify('-30 days')->format('Y-m-d') . ' 00:00:00';
            $fim    = $now->format('Y-m-d') . ' 23:59:59';
            break;
        case 'custom':
            $dIni = trim($_GET['inicio'] ?? '');
            $dFim = trim($_GET['fim']    ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dIni)) $inicio = $dIni . ' 00:00:00';
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dFim))  $fim    = $dFim  . ' 23:59:59';
            break;
    }
    if ($inicio !== null) { $where[] = 'data_criacao >= ?'; $params[] = $inicio; }
    if ($fim    !== null) { $where[] = 'data_criacao <= ?'; $params[] = $fim;    }

    // Filtro por data exata (heatmap: dia específico)
    $data = trim($_GET['data'] ?? '');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        $where[]  = 'DATE(data_criacao) = ?';
        $params[] = $data;
    }

    // Filtro baixa_causa
    if ($baixa_causa !== '') {
        $where[]  = 'baixa_causa LIKE ?';
        $params[] = '%' . $baixa_causa . '%';
    }

    // Filtro baixa_reparo
    if ($baixa_reparo !== '') {
        $where[]  = 'baixa_reparo LIKE ?';
        $params[] = '%' . $baixa_reparo . '%';
    }

    // Coleta os parâmetros de filtro pós-SQL da mesma forma que gpon_api_analise(),
    // para que gpon_apply_analysis_filters() receba exatamente o mesmo $extra.
    $extra = [];
    if (!empty($_GET['ocultar_improcedentes'])) $extra['ocultar_improcedentes'] = true;
    if (!empty($_GET['ocultar_fibrasil']))       $extra['ocultar_fibrasil']       = true;

    $sql  = 'SELECT oc, gpon, splitters, status, data_criacao, data_encerramento, aging_encerrados, '
          . 'localidade, empresa, baixa_causa, baixa_reparo '
          . 'FROM ocorrencias WHERE ' . implode(' AND ', $where) . ' ORDER BY data_criacao DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $rows = gpon_apply_analysis_filters($rows, $extra);

    $sla = GPON_SLA_HORAS;
    $result = [];
    foreach ($rows as $r) {
        $aging   = $r['aging_encerrados'] !== null ? (int)$r['aging_encerrados'] : null;
        $slaSt   = null;
        if ($aging !== null) {
            if ($aging <= $sla)       $slaSt = 'ok';
            elseif ($aging <= $sla + 4) $slaSt = 'atencao';
            else                       $slaSt = 'violado';
        }
        $result[] = [
            'oc'             => $r['oc'],
            'gpon'           => $r['gpon'],
            'splitters'      => $r['splitters'],
            'status'         => $r['status'],
            'abertura'       => $r['data_criacao'],
            'encerramento'   => $r['data_encerramento'],
            'aging'          => $aging,
            'sla_status'     => $slaSt,
            'cidade'         => $r['localidade'],
            'empresa'        => $r['empresa'],
            'baixa_causa'    => $r['baixa_causa'],
            'baixa_reparo'   => $r['baixa_reparo'],
        ];
    }
    gpon_json(['gpon' => $gpon, 'sp' => $sp, 'total' => count($result), 'rows' => $result]);
}

// ── API: Evolução Analítica por GPON (últimos 12 meses) ────────
function gpon_api_analise_analitico(PDO $pdo): void
{
    gpon_require_admin_or_backoffice_api();

    $gpon = trim($_GET['gpon'] ?? '');
    if ($gpon === '') {
        gpon_json(['error' => 'gpon é obrigatório']);
        return;
    }

    $tz       = new \DateTimeZone('America/Sao_Paulo');
    $now      = new \DateTime('now', $tz);
    $inicioAno = $now->format('Y') . '-01-01 00:00:00';

    $extra = [];
    if (!empty($_GET['ocultar_improcedentes'])) $extra['ocultar_improcedentes'] = true;
    if (!empty($_GET['ocultar_fibrasil']))       $extra['ocultar_fibrasil']       = true;

    $sql  = 'SELECT oc, gpon, splitters, status, data_criacao, data_encerramento, '
          . 'aging_encerrados, baixa_causa, baixa_reparo '
          . 'FROM ocorrencias WHERE gpon = ? AND data_criacao >= ? ORDER BY data_criacao DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$gpon, $inicioAno]);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    $rows = gpon_apply_analysis_filters($rows, $extra);

    $meses_map  = [];
    $causas_map = [];
    $sp_map     = [];

    foreach ($rows as $r) {
        $mes = substr($r['data_criacao'] ?? '', 0, 7);
        if (!$mes) continue;
        if (!isset($meses_map[$mes])) {
            $meses_map[$mes] = ['total' => 0, 'mttr_sum' => 0, 'mttr_count' => 0];
        }
        $meses_map[$mes]['total']++;
        if ($r['aging_encerrados'] !== null) {
            $meses_map[$mes]['mttr_sum']   += (int)$r['aging_encerrados'];
            $meses_map[$mes]['mttr_count'] += 1;
        }
        $causa = trim($r['baixa_causa'] ?? '');
        if ($causa !== '') $causas_map[$causa] = ($causas_map[$causa] ?? 0) + 1;
        $fmtSp = gpon_format_splitters($r['splitters'] ?? null);
        if ($fmtSp !== null && $fmtSp !== '') {
            foreach (explode(',', $fmtSp) as $sp) {
                if ($sp !== '') $sp_map[$sp] = ($sp_map[$sp] ?? 0) + 1;
            }
        }
    }

    $ptMonths     = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
    $currentYear  = (int)$now->format('Y');
    $currentMonth = (int)$now->format('n');

    // Separa meses fechados (< mês atual) do mês parcial (em andamento)
    $closedMonths = [];
    $partialMonth = null;
    for ($i = 1; $i <= $currentMonth; $i++) {
        $dt    = new \DateTime($currentYear . '-' . str_pad($i, 2, '0', STR_PAD_LEFT) . '-01', $tz);
        $key   = $dt->format('Y-m');
        $label = $ptMonths[$i - 1] . '/' . $dt->format('y');
        $dm    = $meses_map[$key] ?? ['total' => 0, 'mttr_sum' => 0, 'mttr_count' => 0];
        $entry = [
            'mes'      => $key,
            'label'    => $label,
            'total'    => $dm['total'],
            'mttr_avg' => $dm['mttr_count'] > 0 ? round($dm['mttr_sum'] / $dm['mttr_count'], 1) : null,
        ];
        if ($i < $currentMonth) {
            $closedMonths[] = $entry;
        } else {
            $partialMonth = $entry;
        }
    }

    // Estatísticas calculadas apenas com meses fechados; total_12m inclui o parcial
    $nF        = count($closedMonths);
    $totalF    = array_sum(array_column($closedMonths, 'total'));
    $total_12m = $totalF + ($partialMonth['total'] ?? 0);
    $media     = $nF > 0 ? round($totalF / $nF, 1) : 0;

    $melhor = null; $pior = null;
    foreach ($closedMonths as $m) {
        if ($m['total'] === 0) continue;
        if ($melhor === null || $m['total'] < $melhor['total']) $melhor = $m;
        if ($pior   === null || $m['total'] > $pior['total'])   $pior   = $m;
    }

    // Tendência: metade mais recente vs metade mais antiga dos meses fechados
    $half  = (int)floor($nF / 2);
    $ult3  = $half > 0 ? array_sum(array_column(array_slice($closedMonths, $nF - $half), 'total')) : 0;
    $ant3  = $half > 0 ? array_sum(array_column(array_slice($closedMonths, 0, $half), 'total'))    : 0;
    $grupo_ant_label = '';
    $grupo_ult_label = '';
    if ($half > 0) {
        $a0 = $closedMonths[0]['label'];
        $a1 = $closedMonths[$half - 1]['label'];
        $u0 = $closedMonths[$nF - $half]['label'];
        $u1 = $closedMonths[$nF - 1]['label'];
        $grupo_ant_label = ($half === 1) ? $a0 : $a0 . '–' . $a1;
        $grupo_ult_label = ($half === 1) ? $u0 : $u0 . '–' . $u1;
    }
    $tendencia = 'estavel'; $tendencia_pct = 0;
    if ($ant3 > 0) {
        $tendencia_pct = round(($ult3 - $ant3) / $ant3 * 100, 1);
        if ($tendencia_pct > 10)      $tendencia = 'crescimento';
        elseif ($tendencia_pct < -10) $tendencia = 'reducao';
    } elseif ($ult3 > 0) {
        $tendencia = 'crescimento'; $tendencia_pct = 100;
    }

    arsort($causas_map);
    $causas = []; $total_causas = array_sum($causas_map);
    foreach (array_slice($causas_map, 0, 8, true) as $causa => $cnt) {
        $causas[] = ['causa' => $causa, 'total' => $cnt,
            'pct' => $total_causas > 0 ? round($cnt / $total_causas * 100, 1) : 0];
    }

    arsort($sp_map);
    $splitters = [];
    foreach (array_slice($sp_map, 0, 8, true) as $sp => $cnt) {
        $splitters[] = ['sp' => $sp, 'total' => $cnt];
    }

    $timeline = [];
    foreach (array_slice($rows, 0, 12) as $r) {
        $aging = $r['aging_encerrados'] !== null ? (int)$r['aging_encerrados'] : null;
        $timeline[] = [
            'oc'     => $r['oc'],
            'data'   => $r['data_criacao'],
            'status' => $r['status'],
            'causa'  => $r['baixa_causa'],
            'reparo' => $r['baixa_reparo'],
            'aging'  => $aging,
        ];
    }

    gpon_json([
        'gpon'           => $gpon,
        'total_12m'      => $total_12m,
        'total_fechados' => $totalF,
        'media_mensal'   => $media,
        'melhor_mes'     => $melhor,
        'pior_mes'       => $pior,
        'tendencia'      => $tendencia,
        'tendencia_pct'  => $tendencia_pct,
        'grupo_ant'      => $grupo_ant_label,
        'grupo_ult'      => $grupo_ult_label,
        'meses'          => $closedMonths,
        'mes_parcial'    => $partialMonth,
        'causas'         => $causas,
        'splitters'      => $splitters,
        'timeline'       => $timeline,
    ]);
}

// ── API: Resumo executivo leve (indicadores por GPON) ─────────
function gpon_api_analise_resumo(PDO $pdo): void
{
    gpon_require_admin_or_backoffice_api();

    $gpon = trim($_GET['gpon'] ?? '');
    if ($gpon === '') { gpon_json(['error' => 'gpon é obrigatório']); return; }

    $tz       = new \DateTimeZone('America/Sao_Paulo');
    $now      = new \DateTime('now', $tz);
    $inicioAno = $now->format('Y') . '-01-01 00:00:00';

    $where  = ["gpon = ?", "data_criacao >= ?", "splitters IS NOT NULL", "splitters <> ''", "splitters REGEXP 'SP[0-9]'"];
    $params = [$gpon, $inicioAno];

    if (!empty($_GET['ocultar_improcedentes'])) $where[] = "LOWER(IFNULL(baixa_causa,'')) NOT LIKE '%improcedente%'";
    if (!empty($_GET['ocultar_fibrasil']))       $where[] = "LOWER(IFNULL(empresa,'')) NOT LIKE '%fibrasil%'";

    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(data_criacao,'%Y-%m') AS mes, COUNT(*) AS total
         FROM ocorrencias WHERE " . implode(' AND ', $where) .
        " GROUP BY mes ORDER BY mes"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $nomes   = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
    $curKey  = $now->format('Y-m');
    $meses   = [];
    $partTot = 0;
    foreach ($rows as $r) {
        if ($r['mes'] === $curKey) { $partTot = (int)$r['total']; continue; }
        $m       = (int)explode('-', $r['mes'])[1];
        $meses[] = ['label' => $nomes[$m - 1], 'total' => (int)$r['total']];
    }

    $n      = count($meses);
    $totais = array_column($meses, 'total');
    $totalF = array_sum($totais);
    $total  = $totalF + $partTot;
    $media  = $n > 0 ? round($totalF / $n, 1) : 0;

    $melhor = null; $pior = null;
    foreach ($meses as $m) {
        if ($m['total'] === 0) continue;
        if ($melhor === null || $m['total'] < $melhor['total']) $melhor = $m;
        if ($pior   === null || $m['total'] > $pior['total'])   $pior   = $m;
    }

    $half  = (int)floor($n / 2);
    $ult   = $half > 0 ? array_sum(array_column(array_slice($meses, $n - $half), 'total')) : 0;
    $ant   = $half > 0 ? array_sum(array_column(array_slice($meses, 0, $half),   'total')) : 0;
    $g_ant = ''; $g_ult = '';
    if ($half > 0) {
        $g_ant = $half === 1 ? $meses[0]['label']           : $meses[0]['label']           . '–' . $meses[$half - 1]['label'];
        $g_ult = $half === 1 ? $meses[$n - $half]['label']  : $meses[$n - $half]['label']  . '–' . $meses[$n - 1]['label'];
    }
    $tendencia = 'estavel'; $tendencia_pct = 0;
    if ($ant > 0) {
        $tendencia_pct = round(($ult - $ant) / $ant * 100, 1);
        if ($tendencia_pct > 10)      $tendencia = 'crescimento';
        elseif ($tendencia_pct < -10) $tendencia = 'reducao';
    } elseif ($ult > 0) {
        $tendencia = 'crescimento'; $tendencia_pct = 100;
    }

    gpon_json([
        'gpon'          => $gpon,
        'tendencia'     => $tendencia,
        'tendencia_pct' => $tendencia_pct,
        'total'         => $total,
        'media'         => $media,
        'n_meses'       => $n,
        'melhor_mes'    => $melhor,
        'pior_mes'      => $pior,
        'grupo_ant'     => $g_ant,
        'grupo_ult'     => $g_ult,
    ]);
}
