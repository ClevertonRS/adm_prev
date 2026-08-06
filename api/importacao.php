<?php
// ── Handler: Upload Excel ──────────────────────────────────────

// ── Handler: Upload Excel ──────────────────────────────────────
function gpon_handle_upload(PDO $pdo): void
{
    $user = gpon_require_login();

    if (empty($_FILES['file']['tmp_name'])) {
        gpon_json(['ok' => false, 'message' => 'Nenhum arquivo enviado']);
    }

    $file = $_FILES['file'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls'], true)) {
        gpon_json(['ok' => false, 'message' => 'Somente arquivos .xlsx e .xls são aceitos']);
    }

    // Validar magic bytes: XLSX = ZIP (PK\x03\x04); XLS = OLE2 (\xD0\xCF\x11\xE0)
    $fp    = fopen($file['tmp_name'], 'rb');
    $magic = fread($fp, 4);
    fclose($fp);
    $isXlsx = (substr($magic, 0, 2) === "\x50\x4B");          // ZIP/PK
    $isXls  = ($magic === "\xD0\xCF\x11\xE0");                 // OLE2
    if (!$isXlsx && !$isXls) {
        gpon_json(['ok' => false, 'message' => 'Arquivo inválido: conteúdo não reconhecido como planilha Excel']);
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        gpon_json(['ok' => false, 'message' => 'PhpSpreadsheet não encontrado. Execute composer install na raiz do projeto.']);
    }
    require_once $autoload;

    $tmpPath = sys_get_temp_dir() . '/gpon_' . uniqid() . '.' . $ext;
    move_uploaded_file($file['tmp_name'], $tmpPath);

    try {
        $reader    = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmpPath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($tmpPath);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray(null, true, true, false);
    } catch (\Throwable $e) {
        @unlink($tmpPath);
        gpon_json(['ok' => false, 'message' => 'Erro ao ler planilha: ' . $e->getMessage()]);
    }

    @unlink($tmpPath);

    if (empty($rows)) {
        gpon_json(['ok' => false, 'message' => 'Planilha vazia']);
    }

    // Mapear cabeçalhos
    $header = array_shift($rows);
    $colMap = [];
    foreach ($header as $idx => $col) {
        if ($col === null) continue;
        $dbField = gpon_map_header((string)$col);
        if ($dbField) $colMap[$idx] = $dbField;
    }

    $cols = array_values($colMap);
    if (!in_array('oc', $cols, true) && !in_array('oc_alt', $cols, true)) {
        gpon_json(['ok' => false, 'message' => 'Nenhuma coluna de identificação encontrada ("Tíquete Referência" ou "Docum. Interconectada")']);
    }

    $inseridos  = 0;
    $atualizados = 0;
    $erros      = 0;
    $ignorados  = 0;
    $total      = 0;

    $stmt = $pdo->prepare("
        INSERT INTO ocorrencias
            (oc, ta, status, data_criacao, data_encerramento, gpon, afetacao,
             splitters, splitters_nivel2, baixa_reparo, baixa_causa,
             codigo_baixa_componente, codigo_baixa_defeito, empresa, localidade, uf, aging_encerrados)
        VALUES
            (:oc,:ta,:status,:data_criacao,:data_encerramento,:gpon,:afetacao,
             :splitters,:splitters_nivel2,:baixa_reparo,:baixa_causa,
             :codigo_baixa_componente,:codigo_baixa_defeito,:empresa,:localidade,:uf,:aging_enc)
        ON DUPLICATE KEY UPDATE
            ta                      = COALESCE(VALUES(ta), ta),
            status                  = COALESCE(VALUES(status), status),
            data_criacao            = COALESCE(VALUES(data_criacao), data_criacao),
            data_encerramento       = COALESCE(VALUES(data_encerramento), data_encerramento),
            gpon                    = COALESCE(VALUES(gpon), gpon),
            afetacao                = COALESCE(VALUES(afetacao), afetacao),
            splitters               = COALESCE(VALUES(splitters), splitters),
            splitters_nivel2        = COALESCE(VALUES(splitters_nivel2), splitters_nivel2),
            baixa_reparo            = COALESCE(VALUES(baixa_reparo), baixa_reparo),
            baixa_causa             = COALESCE(VALUES(baixa_causa), baixa_causa),
            codigo_baixa_componente = COALESCE(VALUES(codigo_baixa_componente), codigo_baixa_componente),
            codigo_baixa_defeito    = COALESCE(VALUES(codigo_baixa_defeito), codigo_baixa_defeito),
            empresa                 = COALESCE(VALUES(empresa), empresa),
            localidade              = COALESCE(VALUES(localidade), localidade),
            uf                      = COALESCE(VALUES(uf), uf),
            aging_encerrados        = COALESCE(VALUES(aging_encerrados), aging_encerrados),
            updated_at              = NOW()
    ");

    $histStmt = $pdo->prepare("
        INSERT INTO historico_ocorrencias (ocorrencia_id, oc, tipo, texto, usuario_id, usuario_nome)
        VALUES (?, ?, 'importacao', ?, ?, ?)
    ");

    // Preparar SELECT fora do loop para evitar re-prepare a cada linha
    $chkStmt = $pdo->prepare("SELECT id FROM ocorrencias WHERE oc = ?");

    // Pré-carregar mapa gpon→empresa para evitar 1 query por linha importada
    $gpEmpMap = [];
    foreach ($pdo->query("SELECT gpon, empresa FROM gpon_empresas")->fetchAll(\PDO::FETCH_ASSOC) as $ge) {
        $gpEmpMap[strtoupper(trim($ge['gpon']))] = $ge['empresa'];
    }

    foreach ($rows as $rowIdx => $row) {
        if (empty(array_filter($row, fn($v) => $v !== null && $v !== ''))) continue;
        $total++;

        $data = [
            'oc'                      => null,
            'oc_alt'                  => null,
            'ta'                      => null,
            'status'                  => null,
            'data_criacao'            => null,
            'data_encerramento'       => null,
            'gpon'                    => null,
            'afetacao'                => null,
            'splitters'               => null,
            'splitters_nivel2'        => null,
            'baixa_reparo'            => null,
            'baixa_causa'             => null,
            'codigo_baixa_componente' => null,
            'codigo_baixa_defeito'    => null,
            'empresa'                 => null,
            'localidade'              => null,
            'uf'                      => null,
        ];

        foreach ($colMap as $idx => $field) {
            $val = $row[$idx] ?? null;
            if (in_array($field, ['data_criacao', 'data_encerramento'], true)) {
                $data[$field] = gpon_planilha_para_utc($val); // lê horário exato da planilha, sem conversão
            } elseif (in_array($field, ['splitters', 'splitters_nivel2'], true)) {
                $raw = ($val !== null && $val !== '') ? trim((string)$val) : null;
                $data[$field] = gpon_format_splitters($raw);
            } else {
                $data[$field] = ($val !== null && $val !== '') ? trim((string)$val) : null;
            }
        }

        // OC: Tíquete Referência (normaliza para dígitos) → Docum. Interconectada (preserva formato) → '-'
        $ocRaw = trim((string)($data['oc'] ?? ''));
        if ($ocRaw !== '') {
            $data['oc'] = preg_replace('/\D/', '', $ocRaw) ?: '-';
        } else {
            $ocAlt = trim((string)($data['oc_alt'] ?? ''));
            $data['oc'] = $ocAlt !== '' ? $ocAlt : '-';
        }
        unset($data['oc_alt']);

        // Derivar UF a partir da localidade
        $data['uf'] = gpon_localidade_uf($data['localidade']);

        // Rejeitar registros cuja localidade não existe no mapeamento oficial
        if ($data['uf'] === null) {
            $ignorados++;
            continue;
        }

        // Empresa: (1) regra fixa por localidade → (2) mapa gpon pré-carregado → (3) '-'
        if (empty($data['empresa'])) {
            $emp = gpon_empresa_por_localidade($data['localidade']);
            if ($emp === null && !empty($data['gpon'])) {
                $emp = $gpEmpMap[strtoupper(trim($data['gpon']))] ?? null;
            }
            $data['empresa'] = $emp ?? '-';
        }

        // Calcular aging_encerrados se possível
        $agingEnc = gpon_calc_aging_enc($data['data_criacao'], $data['data_encerramento']);

        try {
            $chkStmt->execute([$data['oc']]);
            $existingId = $chkStmt->fetchColumn();

            $stmt->execute([
                ':oc'                      => $data['oc'],
                ':ta'                      => $data['ta'],
                ':status'                  => $data['status'],
                ':data_criacao'            => $data['data_criacao'],
                ':data_encerramento'       => $data['data_encerramento'],
                ':gpon'                    => $data['gpon'],
                ':afetacao'                => $data['afetacao'],
                ':splitters'               => $data['splitters'],
                ':splitters_nivel2'        => $data['splitters_nivel2'],
                ':baixa_reparo'            => $data['baixa_reparo'],
                ':baixa_causa'             => $data['baixa_causa'],
                ':codigo_baixa_componente' => $data['codigo_baixa_componente'],
                ':codigo_baixa_defeito'    => $data['codigo_baixa_defeito'],
                ':empresa'                 => $data['empresa'],
                ':localidade'              => $data['localidade'],
                ':uf'                      => $data['uf'],
                ':aging_enc'               => $agingEnc,
            ]);

            $newId = (int)$pdo->lastInsertId();
            $dtLocal = (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('d/m/Y H:i');
            if ($existingId) {
                $atualizados++;
                $histStmt->execute([$existingId, $data['oc'], 'Atualizado via importação (' . $dtLocal . ')', $user['id'], $user['nome']]);
            } else {
                $inseridos++;
                $histStmt->execute([$newId, $data['oc'], 'Inserido via importação (' . $dtLocal . ')', $user['id'], $user['nome']]);
            }
        } catch (\Throwable $e) {
            $erros++;
        }
    }

    // Recalcular repetidas
    gpon_update_repetidas($pdo);

    // Registrar importação
    $pdo->prepare("INSERT INTO importacoes (arquivo, total_linhas, inseridos, atualizados, erros, usuario_id, usuario_nome) VALUES (?,?,?,?,?,?,?)")
        ->execute([$file['name'], $total, $inseridos, $atualizados, $erros, $user['id'], $user['nome']]);

    gpon_json([
        'ok'          => true,
        'total'       => $total,
        'inseridos'   => $inseridos,
        'atualizados' => $atualizados,
        'erros'       => $erros,
        'ignorados'   => $ignorados,
        'updated_at'  => gpon_ultima_atualizacao($pdo),
    ]);
}
