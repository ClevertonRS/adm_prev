<?php
// ── Handler: Exportar Excel + Última Atualização ────────────────

// ── Handler: Exportar Excel ────────────────────────────────────
function gpon_handle_exportar(PDO $pdo): void
{
    gpon_require_login();

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        http_response_code(500);
        echo 'PhpSpreadsheet não encontrado. Execute composer install na raiz do projeto.';
        exit;
    }
    require_once $autoload;

    $tzSys  = new \DateTimeZone('America/Sao_Paulo'); // mesmo fuso dos dados armazenados

    // ── Mesmos filtros de gpon_api_data ────────────────────────
    $inclEnc = !empty($_GET['incluir_encerradas']);
    $where   = [$inclEnc ? "status IN ('Ativo','Fechado')" : "status = 'Ativo'"];
    $params  = [];

    if (!empty($_GET['ocultar_fibrasil'])) {
        $where[] = "empresa != 'FIBRASIL'";
    }

    foreach (['empresa', 'localidade', 'gpon', 'uf'] as $f) {
        $raw = $_GET[$f] ?? '';
        if ($raw === '') continue;
        $vals = array_filter(explode('|||', $raw), fn($v) => $v !== '');
        if (empty($vals)) continue;
        $ph = implode(',', array_fill(0, count($vals), '?'));
        $where[]  = "`$f` IN ($ph)";
        $params   = array_merge($params, array_values($vals));
    }

    $spRaw = $_GET['status_prazo'] ?? '';
    if ($spRaw !== '') {
        $spVals = array_filter(explode('|||', $spRaw), fn($v) => $v !== '');
        $spSql  = gpon_status_prazo_sql(array_values($spVals));
        if ($spSql !== '') $where[] = $spSql;
    }

    $sql  = "SELECT o.* FROM ocorrencias o WHERE " . implode(' AND ', $where) . " ORDER BY o.data_criacao DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Buscar histórico de todas as OCs em um único SELECT ────
    $historicos = [];
    if (!empty($rows)) {
        $ocIds = array_column($rows, 'id');
        $ph    = implode(',', array_fill(0, count($ocIds), '?'));
        $hStmt = $pdo->prepare(
            "SELECT ocorrencia_id, texto, usuario_nome, created_at
             FROM historico_ocorrencias
             WHERE ocorrencia_id IN ($ph)
               AND tipo != 'importacao'
               AND (texto IS NOT NULL AND texto != '')
             ORDER BY ocorrencia_id ASC, created_at DESC"
        );
        $hStmt->execute($ocIds);
        foreach ($hStmt->fetchAll(PDO::FETCH_ASSOC) as $h) {
            $historicos[(int)$h['ocorrencia_id']][] = $h;
        }
    }

    // ── Criar Spreadsheet ─────────────────────────────────────
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Ocorrências');

    $headers  = ['OC', 'TA', 'Abertura', 'GPON', 'Splitter', 'Afetação', 'SLA', 'Prazo', 'Tempo', 'Empresa', 'Cidade', 'Previsão'];
    $colCount = count($headers);
    $prevColIdx = $colCount; // índice da coluna Previsão (1-based)

    // Cabeçalho — azul petróleo uniforme em todas as colunas
    foreach ($headers as $i => $h) {
        $sheet->setCellValueByColumnAndRow($i + 1, 1, $h);
    }
    $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);

    $sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray([
        'font' => [
            'bold'  => true,
            'color' => ['argb' => 'FFFFFFFF'],
            'size'  => 11,
        ],
        'fill' => [
            'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['argb' => 'FF1A5276'],
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
            'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            'wrapText'   => true,
        ],
    ]);

    $sheet->getRowDimension(1)->setRowHeight(22);

    // Helper: formatar minutos como "2h 15m"
    $fmtMins = function(?int $m): string {
        if ($m === null) return '—';
        if ($m < 0)     $m = abs($m); // não deveria ocorrer aqui, mas por segurança
        if ($m < 60)    return "{$m}m";
        if ($m < 1440)  { $h = intdiv($m, 60); $r = $m % 60; return $r > 0 ? "{$h}h {$r}m" : "{$h}h"; }
        $d = intdiv($m, 1440); $rh = intdiv($m % 1440, 60);
        return $rh > 0 ? "{$d}d {$rh}h" : "{$d}d";
    };

    $rowNum = 2;
    foreach ($rows as $r) {
        $r = gpon_enrich_row($r);

        // Abertura: exibe exatamente o que está gravado (sem conversão de fuso)
        $abertura = '—';
        if ($r['data_criacao']) {
            $dtAb = \DateTime::createFromFormat('Y-m-d H:i:s', $r['data_criacao']);
            if ($dtAb !== false) $abertura = $dtAb->format('d/m/Y H:i');
        }

        // Prazo
        $prazoMins = $r['prazo_abertas'];
        if ($prazoMins === null) {
            $prazoStr = '—';
        } elseif ($prazoMins >= 0) {
            $prazoStr = 'Restam ' . $fmtMins($prazoMins);
        } else {
            $prazoStr = 'Excedido ' . $fmtMins(abs($prazoMins));
        }

        // Tempo (aging_abertos em minutos)
        $agingStr = $r['aging_abertos'] !== null ? $fmtMins((int)$r['aging_abertos']) : '—';

        // Histórico Operacional (coluna Previsão)
        $histItems = $historicos[$r['id']] ?? [];
        $previsaoLines = [];
        foreach ($histItems as $h) {
            // created_at é TIMESTAMP → MySQL retorna em Brasília (SET time_zone='-03:00')
            $dtH   = new \DateTime($h['created_at'], $tzSys);
            $dtStr = $dtH->format('d/m - H:i');
            $nome   = $h['usuario_nome'] ?? 'Sistema';
            $texto  = trim($h['texto'] ?? '');
            if ($texto !== '') {
                $previsaoLines[] = "[{$dtStr}] ({$nome}) {$texto}";
            }
        }
        $previsaoStr = implode("\n", $previsaoLines);

        $values = [
            $r['oc']          ?? '—',
            $r['ta']          ?? '—',
            $abertura,
            $r['gpon']        ?? '—',
            $r['splitters']   ?? '—',
            $r['afetacao']    ?? '—',
            $r['status_prazo'] ?? '—',
            $prazoStr,
            $agingStr,
            $r['empresa']     ?? '—',
            $r['localidade']  ?? '—',
            $previsaoStr,
        ];

        foreach ($values as $i => $v) {
            $sheet->setCellValueByColumnAndRow($i + 1, $rowNum, ($v !== null && $v !== '') ? $v : '—');
        }

        // Estilo geral da linha: esquerda, vertical ao meio, wrapText (sem preenchimento)
        $sheet->getStyle("A{$rowNum}:{$lastColLetter}{$rowNum}")->applyFromArray([
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
        ]);

        // Altura proporcional ao número de entradas na coluna Previsão.
        // PhpSpreadsheet não calcula altura automática para células wrapText —
        // é preciso definir explicitamente. Cada linha de histórico ≈ 15pt.
        $lineCount  = $previsaoStr !== '' ? (substr_count($previsaoStr, "\n") + 1) : 1;
        $sheet->getRowDimension($rowNum)->setRowHeight(max(18, $lineCount * 15));

        $rowNum++;
    }

    // Larguras fixas por coluna (evita setAutoSize que lê todas as células)
    $colWidths = [14, 14, 16, 12, 22, 18, 12, 16, 12, 20, 22, 55];
    for ($c = 1; $c <= $colCount; $c++) {
        $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
        $sheet->getColumnDimension($letter)->setWidth($colWidths[$c - 1] ?? 18);
    }

    // ── Nome do arquivo com data/hora atual ───────────────────
    $nowDt   = new \DateTime('now', $tzSys);
    $filename = 'Ocorrências ' . $nowDt->format('d-m') . ' às ' . $nowDt->format('H-i') . '.xlsx';

    // ── Enviar ao browser ─────────────────────────────────────
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;
}

// ── Última Atualização ─────────────────────────────────────────
function gpon_ultima_atualizacao(PDO $pdo): string
{
    try {
        $row = $pdo->query(
            "SELECT created_at FROM importacoes ORDER BY created_at DESC LIMIT 1"
        )->fetch(\PDO::FETCH_ASSOC);
        $ts = $row['created_at'] ?? null;
        if (!$ts) return '';
        // created_at é TIMESTAMP → MySQL retorna em Brasília (SET time_zone='-03:00')
        $dtU = new \DateTime($ts, new \DateTimeZone('America/Sao_Paulo'));
        return $dtU->format('d/m - H:i');
    } catch (\Throwable $e) {
        return '';
    }
}

function gpon_api_ultima_atualizacao(PDO $pdo): void
{
    gpon_require_login();
    try {
        $row = $pdo->query(
            "SELECT created_at FROM importacoes ORDER BY created_at DESC LIMIT 1"
        )->fetch(\PDO::FETCH_ASSOC);
        $ts = $row['created_at'] ?? null;
        if ($ts) {
            // created_at é TIMESTAMP → MySQL retorna em Brasília (SET time_zone='-03:00')
            $dt = new \DateTime($ts, new \DateTimeZone('America/Sao_Paulo'));
            gpon_json(['ultima_atualizacao' => $dt->format('d/m - H:i'), 'ts' => $dt->getTimestamp()]);
        } else {
            gpon_json(['ultima_atualizacao' => '', 'ts' => 0]);
        }
    } catch (\Throwable $e) {
        gpon_json(['ultima_atualizacao' => '', 'ts' => 0]);
    }
}
