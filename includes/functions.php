<?php
// ── Funções do Radar GPON ─────────────────────────────────────
// Gerado automaticamente por refatoração — não editar o cabeçalho

function gpon_init_db(PDO $pdo): void
{
    // Versão do esquema esperada. Incrementar quando adicionar novas migrações.
    $targetVersion = 7;

    // Criar tabela de versão (operação barata, usa IF NOT EXISTS)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `schema_version` (
        `version`    INT UNSIGNED NOT NULL,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`version`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $currentVersion = (int)$pdo->query("SELECT MAX(version) FROM schema_version")->fetchColumn();
    if ($currentVersion >= $targetVersion) return;

    $pdo->exec("CREATE TABLE IF NOT EXISTS `usuarios` (
        `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `nome`         VARCHAR(200) NOT NULL,
        `usuario`      VARCHAR(100) NOT NULL UNIQUE,
        `senha`        VARCHAR(255) NOT NULL,
        `nivel`        ENUM('admin','operador','backoffice','supervisor','tecnico') NOT NULL DEFAULT 'operador',
        `status`       TINYINT(1)  NOT NULL DEFAULT 1,
        `ultimo_acesso` DATETIME DEFAULT NULL,
        `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `ocorrencias` (
        `id`                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `oc`                      VARCHAR(100) NOT NULL UNIQUE COMMENT 'Tíquete Referência',
        `ta`                      VARCHAR(100) DEFAULT NULL COMMENT 'VTA PK',
        `status`                  VARCHAR(100) DEFAULT NULL,
        `data_criacao`            DATETIME     DEFAULT NULL,
        `data_encerramento`       DATETIME     DEFAULT NULL,
        `gpon`                    VARCHAR(100) DEFAULT NULL COMMENT 'Sigla Site V2',
        `afetacao`                TEXT         DEFAULT NULL COMMENT 'Serviço FTTX',
        `splitters`               VARCHAR(200) DEFAULT NULL COMMENT 'Splitters Nível 1',
        `splitters_nivel2`        VARCHAR(200) DEFAULT NULL COMMENT 'Splitters Nível 2',
        `baixa_reparo`            TEXT         DEFAULT NULL,
        `baixa_causa`             TEXT         DEFAULT NULL,
        `codigo_baixa_componente` VARCHAR(200) DEFAULT NULL,
        `codigo_baixa_defeito`    VARCHAR(200) DEFAULT NULL,
        `empresa`                 VARCHAR(200) DEFAULT NULL COMMENT 'Empresa Manutenção',
        `localidade`              VARCHAR(200) DEFAULT NULL COMMENT 'Nome Localidade',
        `observacoes_operacionais` TEXT         DEFAULT NULL,
        `repetida`                TINYINT(1)   NOT NULL DEFAULT 0,
        `aging_encerrados`        INT          DEFAULT NULL COMMENT 'Em horas',
        `created_at`              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_gpon`       (`gpon`),
        INDEX `idx_status`     (`status`),
        INDEX `idx_empresa`    (`empresa`),
        INDEX `idx_localidade` (`localidade`(100)),
        INDEX `idx_criacao`    (`data_criacao`),
        INDEX `idx_splitters`  (`splitters`(100)),
        INDEX `idx_repetida`   (`repetida`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `historico_ocorrencias` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `ocorrencia_id` INT UNSIGNED NOT NULL,
        `oc`            VARCHAR(100) NOT NULL,
        `campo`         VARCHAR(100) DEFAULT NULL,
        `valor_anterior` TEXT        DEFAULT NULL,
        `valor_novo`    TEXT         DEFAULT NULL,
        `tipo`          ENUM('comentario','edicao','importacao','exclusao') NOT NULL DEFAULT 'comentario',
        `texto`         TEXT         DEFAULT NULL,
        `usuario_id`    INT UNSIGNED DEFAULT NULL,
        `usuario_nome`  VARCHAR(200) DEFAULT NULL,
        `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_oc_id` (`ocorrencia_id`),
        INDEX `idx_oc`    (`oc`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Normalizar registros antigos com tipo vazio (schema anterior ao ENUM)
    $pdo->exec("UPDATE historico_ocorrencias SET tipo = 'comentario' WHERE tipo = '' OR tipo IS NULL");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `importacoes` (
        `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `arquivo`      VARCHAR(255) DEFAULT NULL,
        `total_linhas` INT NOT NULL DEFAULT 0,
        `inseridos`    INT NOT NULL DEFAULT 0,
        `atualizados`  INT NOT NULL DEFAULT 0,
        `erros`        INT NOT NULL DEFAULT 0,
        `usuario_id`   INT UNSIGNED DEFAULT NULL,
        `usuario_nome` VARCHAR(200) DEFAULT NULL,
        `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Migração: adicionar 'backoffice' ao ENUM nivel se ainda não existir
    try {
        $col = $pdo->query("SHOW COLUMNS FROM `usuarios` LIKE 'nivel'")->fetch();
        if ($col && strpos($col['Type'], 'backoffice') === false) {
            $pdo->exec("ALTER TABLE `usuarios` MODIFY COLUMN `nivel` ENUM('admin','operador','backoffice') NOT NULL DEFAULT 'operador'");
        }
    } catch (\Exception $e) { /* silencia se coluna não existir ainda */ }

    // Migração: ampliar níveis de usuário para supervisor/técnico
    try {
        $col = $pdo->query("SHOW COLUMNS FROM `usuarios` LIKE 'nivel'")->fetch();
        if ($col && strpos($col['Type'], 'supervisor') === false) {
            $pdo->exec("ALTER TABLE `usuarios` MODIFY COLUMN `nivel` ENUM('admin','operador','backoffice','supervisor','tecnico') NOT NULL DEFAULT 'operador'");
        }
    } catch (\Exception $e) { /* silencia */ }

    // Cria admin padrão se não existir
    $chk = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    if ((int)$chk === 0) {
        $hash = password_hash('gpon@2024', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO usuarios (nome,usuario,senha,nivel) VALUES (?,?,?,?)")
            ->execute(['Administrador', 'admin', $hash, 'admin']);
    }

    // Migração: corrigir datas com ano de 2 dígitos importadas incorretamente (ex: 0026 → 2026)
    try {
        $pdo->exec("
            UPDATE ocorrencias
            SET data_criacao = DATE_ADD(data_criacao, INTERVAL 2000 YEAR)
            WHERE YEAR(data_criacao) < 100
              AND data_criacao IS NOT NULL
        ");
        $pdo->exec("
            UPDATE ocorrencias
            SET data_encerramento = DATE_ADD(data_encerramento, INTERVAL 2000 YEAR)
            WHERE YEAR(data_encerramento) < 100
              AND data_encerramento IS NOT NULL
        ");
    } catch (\Exception $e) { /* silencia se tabela ainda não existe */ }

    // Migração: preencher empresa vazia com base na localidade
    try {
        $rows = $pdo->query("SELECT id, localidade, gpon FROM ocorrencias WHERE (empresa IS NULL OR empresa = '') AND localidade IS NOT NULL AND localidade <> ''")->fetchAll(PDO::FETCH_NUM);
        if (!empty($rows)) {
            $upd = $pdo->prepare("UPDATE ocorrencias SET empresa = ? WHERE id = ?");
            foreach ($rows as [$id, $loc, $gpon]) {
                $emp = null;
                if ($gpon !== null && trim((string)$gpon) !== '') {
                    $emp = gpon_empresa_por_gpon($gpon);
                }
                if (empty($emp)) {
                    $emp = gpon_empresa_por_localidade($loc);
                }
                $upd->execute([$emp, $id]);
            }
            unset($rows);
        }
    } catch (\Exception $e) { /* silencia se tabela ainda não existe */ }

    // Migração: coluna uf (estado) derivada da localidade
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM ocorrencias LIKE 'uf'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE ocorrencias ADD COLUMN `uf` CHAR(2) NULL DEFAULT NULL AFTER `localidade`");
            $pdo->exec("ALTER TABLE ocorrencias ADD INDEX `idx_uf` (`uf`)");
            // Popula registros existentes
            $rows = $pdo->query("SELECT id, localidade FROM ocorrencias WHERE localidade IS NOT NULL AND localidade <> ''")->fetchAll(PDO::FETCH_NUM);
            $upd  = $pdo->prepare("UPDATE ocorrencias SET uf = ? WHERE id = ?");
            foreach ($rows as [$id, $loc]) {
                $uf = gpon_localidade_uf($loc);
                if ($uf !== null) $upd->execute([$uf, $id]);
            }
            unset($rows);
        }
    } catch (\Exception $e) { /* silencia */ }

    // Migração: previsão de finalização informada pelo técnico
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM ocorrencias LIKE 'previsao_finalizacao'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE ocorrencias ADD COLUMN `previsao_finalizacao` DATETIME NULL DEFAULT NULL AFTER `data_encerramento`");
        }
    } catch (\Exception $e) { /* silencia */ }

    // Migração: adicionar tipo 'previsao' ao ENUM de historico_ocorrencias
    try {
        $col = $pdo->query("SHOW COLUMNS FROM `historico_ocorrencias` LIKE 'tipo'")->fetch();
        if ($col && strpos($col['Type'], 'previsao') === false) {
            $pdo->exec("ALTER TABLE `historico_ocorrencias` MODIFY COLUMN `tipo` ENUM('comentario','edicao','importacao','exclusao','previsao') NOT NULL DEFAULT 'comentario'");
        }
    } catch (\Exception $e) { /* silencia */ }

    // Migração: corrigir registros de previsão gravados como 'comentario' antes do ENUM ter o tipo 'previsao'
    try {
        $pdo->exec("
            UPDATE historico_ocorrencias
            SET tipo = 'previsao'
            WHERE tipo = 'comentario'
              AND campo IS NULL AND valor_anterior IS NULL AND valor_novo IS NULL
              AND (
                texto LIKE 'Prev. _s __:__'
                OR texto LIKE 'Prev. atualizada para __:__'
                OR texto LIKE 'Previs_o removida'
              )
        ");
    } catch (\Exception $e) { /* silencia */ }

    // Migração: tabela de mapeamento GPON <-> Empresa (substituindo array PHP)
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `gpon_empresas` (
            `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `gpon`          VARCHAR(100) NOT NULL,
            `empresa`       VARCHAR(50) NOT NULL,
            `criado_em`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `idx_gpon` (`gpon`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Migrar dados do array PHP para a tabela (apenas na primeira vez)
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM gpon_empresas")->fetchColumn();
        if ($cnt === 0) {
            $mapFile = __DIR__ . '/../config/gpon_empresa_map.php';
            if (file_exists($mapFile)) {
                $map = include $mapFile;
                if (is_array($map) && !empty($map)) {
                    $ins = $pdo->prepare("INSERT INTO gpon_empresas (gpon, empresa) VALUES (?, ?)");
                    foreach ($map as $gpon => $empresa) {
                        try {
                            $ins->execute([strtoupper(trim((string)$gpon)), $empresa]);
                        } catch (\Exception $_) { /* ignora duplicatas */ }
                    }
                }
            }
        }
    } catch (\Exception $e) { /* silencia */ }

    // Índices de performance (versão 5)
    $indexes = [
        ['historico_ocorrencias', 'idx_oc_tipo',       "ALTER TABLE `historico_ocorrencias` ADD INDEX `idx_oc_tipo` (`ocorrencia_id`, `tipo`)"],
        ['importacoes',           'idx_created_at',     "ALTER TABLE `importacoes` ADD INDEX `idx_created_at` (`created_at`)"],
        ['ocorrencias',           'idx_gpon_splitters', "ALTER TABLE `ocorrencias` ADD INDEX `idx_gpon_splitters` (`gpon`, `splitters`(100))"],
        ['usuarios',              'idx_usuario_status', "ALTER TABLE `usuarios` ADD INDEX `idx_usuario_status` (`usuario`, `status`)"],
        ['ocorrencias',           'idx_uf_status',      "ALTER TABLE `ocorrencias` ADD INDEX `idx_uf_status` (`uf`, `status`)"],
        ['ocorrencias',           'idx_status_criacao', "ALTER TABLE `ocorrencias` ADD INDEX `idx_status_criacao` (`status`, `data_criacao`)"],
    ];
    foreach ($indexes as [$table, $idxName, $alterSql]) {
        try {
            $exists = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$idxName}'")->rowCount();
            if (!$exists) $pdo->exec($alterSql);
        } catch (\Exception $e) { /* silencia */ }
    }

    // Migração: criar tabelas do módulo de preventiva de rede
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `preventivas_rede` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `gpon` VARCHAR(100) NOT NULL,
            `splitter` VARCHAR(100) NOT NULL,
            `chave_combinacao` VARCHAR(255) NOT NULL,
            `uf` CHAR(2) DEFAULT NULL,
            `localidade` VARCHAR(200) DEFAULT NULL,
            `status` VARCHAR(30) NOT NULL DEFAULT 'aberta',
            `prioridade` VARCHAR(20) NOT NULL DEFAULT 'media',
            `origem_periodo` VARCHAR(50) DEFAULT NULL,
            `origem_total_ocorrencias` INT UNSIGNED DEFAULT 0,
            `origem_ultima_reincidencia_em` DATETIME DEFAULT NULL,
            `observacao_abertura` TEXT DEFAULT NULL,
            `criado_por` INT UNSIGNED DEFAULT NULL,
            `supervisor_id` INT UNSIGNED DEFAULT NULL,
            `tecnico_id` INT UNSIGNED DEFAULT NULL,
            `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `enviado_execucao_em` DATETIME DEFAULT NULL,
            `enviado_revisao_em` DATETIME DEFAULT NULL,
            `concluido_em` DATETIME DEFAULT NULL,
            `concluido_por` INT UNSIGNED DEFAULT NULL,
            INDEX `idx_preventiva_status` (`status`),
            INDEX `idx_preventiva_chave` (`chave_combinacao`),
            INDEX `idx_preventiva_gpon_splitter` (`gpon`, `splitter`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `preventivas_execucao` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `preventiva_id` INT UNSIGNED NOT NULL,
            `causa_raiz` TEXT DEFAULT NULL,
            `acoes_realizadas` TEXT DEFAULT NULL,
            `itens_substituidos` TEXT DEFAULT NULL,
            `consumo_material` TEXT DEFAULT NULL,
            `observacao_tecnico` TEXT DEFAULT NULL,
            `checklist_json` JSON DEFAULT NULL,
            `enviado_revisao_em` DATETIME DEFAULT NULL,
            `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_preventivas_execucao_preventiva` FOREIGN KEY (`preventiva_id`) REFERENCES `preventivas_rede` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `preventivas_arquivos` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `preventiva_id` INT UNSIGNED NOT NULL,
            `tipo` VARCHAR(30) NOT NULL DEFAULT 'evidencia',
            `caminho_arquivo` VARCHAR(500) NOT NULL,
            `nome_original` VARCHAR(255) DEFAULT NULL,
            `enviado_por` INT UNSIGNED DEFAULT NULL,
            `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_preventivas_arquivos_preventiva` FOREIGN KEY (`preventiva_id`) REFERENCES `preventivas_rede` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `preventivas_historico` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `preventiva_id` INT UNSIGNED NOT NULL,
            `status_origem` VARCHAR(30) DEFAULT NULL,
            `status_destino` VARCHAR(30) NOT NULL,
            `usuario_id` INT UNSIGNED DEFAULT NULL,
            `usuario_nome` VARCHAR(200) DEFAULT NULL,
            `observacao` TEXT DEFAULT NULL,
            `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_preventivas_historico_preventiva` FOREIGN KEY (`preventiva_id`) REFERENCES `preventivas_rede` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (\Exception $e) { /* silencia */ }

    // Registrar versão do esquema para evitar re-execução nas próximas requisições
    try {
        $pdo->exec("DELETE FROM schema_version");
        $pdo->prepare("INSERT INTO schema_version (version) VALUES (?)")->execute([$targetVersion]);
    } catch (\Exception $e) { /* silencia */ }
}


require_once __DIR__ . '/auth.php';


// ── Helpers de negócio ─────────────────────────────────────────
function gpon_normalize_col(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = str_replace(
        ['á','à','ã','â','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','õ','ô','ö','ú','ù','û','ü','ç','ñ'],
        ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n'],
        $s
    );
    return preg_replace('/\s+/', ' ', $s) ?: '';
}

const GPON_COL_MAP = [
    'tiquet referencia'        => 'oc',
    'tiquete referencia'       => 'oc',
    'ticket referencia'        => 'oc',
    'numero oc'                => 'oc',
    'docum. interconectada'    => 'oc_alt',
    'docum interconectada'     => 'oc_alt',
    'vta pk'                   => 'ta',
    'nome localidade'          => 'localidade',
    'localidade'               => 'localidade',
    'status'                   => 'status',
    'data criacao'             => 'data_criacao',
    'data de criacao'          => 'data_criacao',
    'data encerramento'        => 'data_encerramento',
    'data de encerramento'     => 'data_encerramento',
    'sigla site v2'            => 'gpon',
    'sigla site'               => 'gpon',
    'gpon'                     => 'gpon',
    'servico fttx'             => 'afetacao',
    'servico'                  => 'afetacao',
    'afetacao'                 => 'afetacao',
    'splitters nivel 1'        => 'splitters',
    'splitters nível 1'        => 'splitters',
    'splitters n1'             => 'splitters',
    'splitter nivel 1'         => 'splitters',
    'splitters nivel 2'        => 'splitters_nivel2',
    'splitters nível 2'        => 'splitters_nivel2',
    'splitter nivel 2'         => 'splitters_nivel2',
    'baixa reparo'             => 'baixa_reparo',
    'baixa causa'              => 'baixa_causa',
    'codigo baixa componente'  => 'codigo_baixa_componente',
    'codigo baixa defeito'     => 'codigo_baixa_defeito',
    'empresa manutencao'       => 'empresa',
    'empresa de manutencao'    => 'empresa',
    'empresa'                  => 'empresa',
];

function gpon_map_header(string $header): ?string
{
    $norm = gpon_normalize_col($header);
    return GPON_COL_MAP[$norm] ?? null;
}

// Mapeia UF → timezone IANA. MT e MS são UTC-4 sem DST desde 2019.
function gpon_uf_timezone(?string $uf): string
{
    static $map = [
        'MT' => 'America/Cuiaba',
        'MS' => 'America/Campo_Grande',
    ];
    return $map[strtoupper((string)$uf)] ?? 'America/Cuiaba';
}

// Retorna timezone IANA da localidade derivando via gpon_localidade_uf().
function gpon_localidade_timezone(?string $localidade): string
{
    return gpon_uf_timezone(gpon_localidade_uf($localidade));
}

// Lê um valor de data/hora de planilha (serial numérico ou texto) e retorna
// a string 'Y-m-d H:i:s' sem qualquer conversão de fuso horário.
function gpon_planilha_para_utc($val): ?string
{
    if ($val === null || $val === '') return null;

    if (is_numeric($val)) {
        try {
            // UTC explícito: a fórmula interna (serial-25569)*86400 produz o valor da célula
            // como UTC — qualquer outro fuso deslocaria o horário.
            $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$val, new \DateTimeZone('UTC'));
            return $dt->format('Y-m-d H:i:s'); // retorna exatamente o horário da célula
        } catch (\Throwable $_) {}
    }

    $s = trim((string)$val);
    if (!$s) return null;

    $tzUtc = new \DateTimeZone('UTC'); // garante que o valor lido não seja deslocado por fuso
    foreach ([
        'd/m/y H:i:s', 'd/m/y H:i', 'd/m/y',
        'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y',
        'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d',
        'd-m-y H:i:s', 'd-m-y H:i', 'd-m-y',
    ] as $fmt) {
        $dt = \DateTime::createFromFormat($fmt, $s, $tzUtc);
        if ($dt !== false) return $dt->format('Y-m-d H:i:s');
    }

    $dt = date_create($s, $tzUtc);
    return $dt !== false ? $dt->format('Y-m-d H:i:s') : null;
}

// Formata uma string UTC 'Y-m-d H:i:s' no timezone local informado.
function gpon_exibir_data_local(?string $utcDatetime, string $timezone = 'America/Cuiaba', string $format = 'd/m/Y H:i'): string
{
    if (!$utcDatetime) return '—';
    try {
        $dt = new \DateTime($utcDatetime, new \DateTimeZone('UTC'));
        $dt->setTimezone(new \DateTimeZone($timezone));
        return $dt->format($format);
    } catch (\Throwable $_) {
        return '—';
    }
}

// Interpreta entrada manual do usuário e retorna string de data/hora sem conversão de fuso.
function gpon_parse_datetime($val): ?string
{
    if ($val === null || $val === '') return null;

    $s = trim((string)$val);
    if (!$s) return null;

    foreach ([
        'd/m/y H:i:s', 'd/m/y H:i', 'd/m/y',
        'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y',
        'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d',
        'd-m-y H:i:s', 'd-m-y H:i', 'd-m-y',
    ] as $fmt) {
        $dt = \DateTime::createFromFormat($fmt, $s);
        if ($dt !== false) return $dt->format('Y-m-d H:i:s');
    }

    $ts = strtotime($s);
    return $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
}

function gpon_calc_aging_horas(?string $data_criacao): ?int
{
    if (!$data_criacao) return null;
    $c = strtotime($data_criacao); // PHP default = America/Sao_Paulo
    if ($c === false) return null;
    return (int) floor((time() - $c) / 3600);
}

// Retorna minutos decorridos desde data_criacao até agora (para exibição com precisão de minutos)
function gpon_calc_aging_mins(?string $data_criacao): ?int
{
    if (!$data_criacao) return null;
    $c = strtotime($data_criacao);
    if ($c === false) return null;
    return (int) max(0, floor((time() - $c) / 60));
}

function gpon_calc_aging_enc(?string $data_criacao, ?string $data_enc): ?int
{
    if (!$data_criacao || !$data_enc) return null;
    $c = strtotime($data_criacao);
    $e = strtotime($data_enc);
    if ($c === false || $e === false) return null;
    return (int) max(0, floor(($e - $c) / 3600));
}

// Retorna minutos entre criação e encerramento (para exibição com precisão de minutos)
function gpon_calc_enc_mins(?string $data_criacao, ?string $data_enc): ?int
{
    if (!$data_criacao || !$data_enc) return null;
    $c = strtotime($data_criacao);
    $e = strtotime($data_enc);
    if ($c === false || $e === false) return null;
    return (int) max(0, floor(($e - $c) / 60));
}

function gpon_is_encerrada(?string $status): bool
{
    if (!$status) return false;
    $s = mb_strtolower($status);
    return (strpos($s, 'encerrad') !== false) || (strpos($s, 'fechad') !== false) || (strpos($s, 'resolvid') !== false);
}

function gpon_status_prazo(?string $status, ?int $aging_horas, ?int $aging_enc): string
{
    if (gpon_is_encerrada($status)) {
        if ($aging_enc === null) return '—';
        return $aging_enc <= GPON_SLA_HORAS ? 'Dentro do Prazo' : 'Fora do Prazo';
    }
    if ($aging_horas === null) return '—';
    if ($aging_horas >= GPON_SLA_HORAS)        return 'Fora do Prazo';
    if ($aging_horas >= GPON_SLA_PROXIMO_HORAS) return 'Atenção';
    return 'Dentro do Prazo';
}

function gpon_prazo_abertas(?string $status, ?int $aging_mins): ?int
{
    if (gpon_is_encerrada($status)) return null;
    if ($aging_mins === null) return null;
    return (GPON_SLA_HORAS * 60) - $aging_mins; // positivo = restam, negativo = excedido
}

// Extrai todos os códigos SP\d+ encontrados no texto e retorna únicos separados por vírgula.
// Suporta: listas simples ("I03SP9,I03SP10"), cadeias técnicas ("SP57-SP58-SP59>...SP59<SPLITTER"),
//          textos longos com prefixo ("CAIXA...>...SP142<SPLITTER 1") e múltiplas linhas.
// Ex: "I03SP9, I03SP10" → "SP9,SP10" | "SP57-SP58-SP59>MTTGS-I01SP59<SPLITTER" → "SP57,SP58,SP59"
function gpon_format_splitters(?string $val): ?string
{
    if ($val === null || trim($val) === '') return $val;

    preg_match_all('/SP\d+/i', $val, $m);

    if (empty($m[0])) return null;

    $unique = array_values(array_unique(array_map('strtoupper', $m[0])));
    return implode(',', $unique);
}

function gpon_normalizar_localidade(string $s): string
{
    $s = strtoupper(trim($s));
    return strtr($s, [
        'Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A',
        'É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
        'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I',
        'Ó'=>'O','Ò'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O',
        'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U',
        'Ç'=>'C','Ñ'=>'N',
    ]);
}

function gpon_localidade_uf(?string $localidade): ?string
{
    if ($localidade === null || trim($localidade) === '') return null;

    $n = gpon_normalizar_localidade($localidade);

    // Array centralizado: localidade normalizada → UF geográfica original
    // Regra operacional: AC → MT | RO → MS
    static $map = [
        // MT
        'AGUA BOA'              => 'MT',
        'ALTA FLORESTA'         => 'MT',
        'ALTO ARAGUAIA'         => 'MT',
        'ALTO GARCAS'           => 'MT',
        'BARRA DO GARCAS'       => 'MT',
        'CACERES'               => 'MT',
        'CAMPINAPOLIS'          => 'MT',
        'CAMPO NOVO DO PARECIS' => 'MT',
        'CAMPO VERDE'           => 'MT',
        'CANABRAVA DO NORTE'    => 'MT',
        'CASTANHEIRA'           => 'MT',
        'CHAPADA DOS GUIMARAES' => 'MT',
        'CLAUDIA'               => 'MT',
        'COLIDER'               => 'MT',
        'CONFRESA'              => 'MT',
        "CONQUISTA D OESTE"     => 'MT',
        'COTRIGUASSU'           => 'MT',
        'CUIABA'                => 'MT',
        'DIAMANTINO'            => 'MT',
        'GUARANTA DO NORTE'     => 'MT',
        'ITIQUIRA'              => 'MT',
        'JACIARA'               => 'MT',
        'JUARA'                 => 'MT',
        'JUINA'                 => 'MT',
        'LUCAS DO RIO VERDE'    => 'MT',
        'MARCELANDIA'           => 'MT',
        'MATUPA'                => 'MT',
        "MIRASSOL D OESTE"      => 'MT',
        'NOVA BANDEIRANTE'      => 'MT',
        'NOVA BRASILANDIA'      => 'MT',
        'NOVA LACERDA'          => 'MT',
        'NOVA MUTUM'            => 'MT',
        'NOVA XAVANTINA'        => 'MT',
        'PARANAITA'             => 'MT',
        'PARANATINGA'           => 'MT',
        'PEDRA PRETA'           => 'MT',
        'PEIXOTO DE AZEVEDO'    => 'MT',
        'POCONE'                => 'MT',
        'PONTES E LACERDA'      => 'MT',
        'PRIMAVERA DO LESTE'    => 'MT',
        'QUERENCIA'             => 'MT',
        'RONDONOPOLIS'          => 'MT',
        'ROSARIO OESTE'         => 'MT',
        'S FELIX DO ARAGUAIA'   => 'MT',
        'S JOSE DO RIO CLARO'   => 'MT',
        'SAPEZAL'               => 'MT',
        'SINOP'                 => 'MT',
        'SORRISO'               => 'MT',
        'TABAPORA'              => 'MT',
        'TANGARA DA SERRA'      => 'MT',
        'TORIXOREU'             => 'MT',
        'VARZEA GRANDE'         => 'MT',
        // MS
        'AGUA CLARA'            => 'MS',
        'AMAMBAI'               => 'MS',
        'APARECIDA DO TABOADO'  => 'MS',
        'AQUIDAUANA'            => 'MS',
        'ARAL MOREIRA'          => 'MS',
        'BATAGUASSU'            => 'MS',
        'BODOQUENA'             => 'MS',
        'CAARAPO'               => 'MS',
        'CAMAPUA'               => 'MS',
        'CAMPO GRANDE'          => 'MS',
        'CASSILANDIA'           => 'MS',
        'CHAPADAO DO SUL'       => 'MS',
        'CORUMBA'               => 'MS',
        'COSTA RICA'            => 'MS',
        'COXIM'                 => 'MS',
        'DOURADOS'              => 'MS',
        'ELDORADO'              => 'MS',
        'IGUATEMI'              => 'MS',
        'INOCENCIA'             => 'MS',
        'ITAPORA'               => 'MS',
        'IVINHEMA'              => 'MS',
        'LADARIO'               => 'MS',
        'MARACAJU'              => 'MS',
        'MUNDO NOVO'            => 'MS',
        'NAVIRAI'               => 'MS',
        'NOVA ALVORADA DO SUL'  => 'MS',
        'NOVA ANDRADINA'        => 'MS',
        'PARANAIBA'             => 'MS',
        'PONTA PORA'            => 'MS',
        'PORTO MURTINHO'        => 'MS',
        'R BRILHANTE'           => 'MS',
        'R NEGRO'               => 'MS',
        'R VERDE MATO GROSSO'   => 'MS',
        'RIBAS DO RIO PARDO'    => 'MS',
        'S GABRIEL DO OESTE'    => 'MS',
        'SELVIRIA'              => 'MS',
        'SIDROLANDIA'           => 'MS',
        'SONORA'                => 'MS',
        'TRES LAGOAS'           => 'MS',
        // AC → agrupado como MT
        'CRUZEIRO DO SUL'       => 'AC',
        'R BRANCO'              => 'AC',
        // RO → agrupado como MS
        'CACOAL'                => 'RO',
        'CEREJEIRAS'            => 'RO',
        'GUAJARA-MIRIM'         => 'RO',
        'JARU'                  => 'RO',
        'JI-PARANA'             => 'RO',
        'PORTO VELHO'           => 'RO',
        'ROLIM DE MOURA'        => 'RO',
        'STA LUZIA D OESTE'     => 'RO',
        'VILHENA'               => 'RO',
        // DF
        'BRASILIA'              => 'DF',
        'PLANO PILOTO'          => 'DF',
        'TAGUATINGA'            => 'DF',
        'CEILANDIA'             => 'DF',
        'AGUAS CLARAS'          => 'DF',
        'SAMAMBAIA'             => 'DF',
        'GUARA'                 => 'DF',
        // GO
        'GOIANIA'               => 'GO',
        'APARECIDA DE GOIANIA'  => 'GO',
        'ANAPOLIS'              => 'GO',
        'RIO VERDE'             => 'GO',
        'VALPARAISO DE GOIAS'   => 'GO',
        'LUZIANIA'              => 'GO',
        'TRINDADE'              => 'GO',
        'FORMOSA'               => 'GO',
        'SENADOR CANEDO'        => 'GO',
        'ITUMBIARA'             => 'GO',
        'JATAI'                 => 'GO',
        'CATALAO'               => 'GO',
        'CALDAS NOVAS'          => 'GO',
        'MINEIROS'              => 'GO',
        'INHUMAS'               => 'GO',
        'JARAGUA'               => 'GO',
        'MORRINHOS'             => 'GO',
        'ITABERAI'              => 'GO',
    ];

    if (!isset($map[$n])) return null; // localidade não mapeada

    // Regra operacional de agrupamento
    $uf = $map[$n];
    if ($uf === 'AC') return 'MT';
    if ($uf === 'RO') return 'MS';
    return $uf;
}

/**
 * Retorna a empresa de manutenção a partir do código GPON (consulta banco de dados).
 * Retorna null se não houver mapeamento para o GPON.
 */
function gpon_empresa_por_gpon(?string $gpon, ?PDO $pdo = null): ?string
{
    if ($gpon === null || trim($gpon) === '') return null;
    $key = strtoupper(trim($gpon));

    // Se PDO não for passado, tentar obter do contexto global
    if ($pdo === null) {
        static $pdo_cached = null;
        if ($pdo_cached === null) {
            try {
                $pdo_cached = gpon_pdo();
            } catch (\Exception $_) {
                return null;
            }
        }
        $pdo = $pdo_cached;
    }

    try {
        $stmt = $pdo->prepare("SELECT empresa FROM gpon_empresas WHERE gpon = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['empresa'] : null;
    } catch (\Exception $_) {
        return null;
    }
}

function gpon_empresa_por_localidade(?string $localidade): ?string
{
    if ($localidade === null || trim($localidade) === '') return null;

    $n = gpon_normalizar_localidade($localidade);

    $map = [
        'VARZEA GRANDE' => 'ABILITY',
        'CUIABA'        => 'ABILITY',
        'RONDONOPOLIS'  => 'ONDACOM',
        'CAMPO GRANDE'  => 'ONDACOM',
    ];

    return $map[$n] ?? null;
}

function gpon_enrich_row(array $row): array
{
    // Horas: para comparação de SLA
    $aging_h  = gpon_calc_aging_horas($row['data_criacao']);
    $aging_eh = gpon_calc_aging_enc($row['data_criacao'], $row['data_encerramento']);

    // Minutos: para exibição com precisão de minutos
    $aging_mins  = gpon_calc_aging_mins($row['data_criacao']);
    $aging_emins = gpon_calc_enc_mins($row['data_criacao'], $row['data_encerramento']);

    $sp    = gpon_status_prazo($row['status'], $aging_h, $aging_eh);
    $prazo = gpon_prazo_abertas($row['status'], $aging_mins); // retorna minutos

    $row['aging_abertos']    = $aging_mins; // minutos para display "2h 15m"
    $row['aging_encerrados'] = $aging_emins ?? ($row['aging_encerrados'] !== null ? (int)$row['aging_encerrados'] * 60 : null);
    $row['status_prazo']     = $sp;
    $row['prazo_abertas']    = $prazo;    // minutos restantes (negativo = excedido)

    // Formata splitters ao exibir — corrige dados existentes sem alterar o banco
    $row['splitters']        = gpon_format_splitters($row['splitters'] ?? null);
    $row['splitters_nivel2'] = gpon_format_splitters($row['splitters_nivel2'] ?? null);

    // Previsão de finalização vs SLA (horários em Brasília, sem conversão de fuso)
    $prev = $row['previsao_finalizacao'] ?? null;
    if ($row['data_criacao']) {
        $criacaoTs = strtotime($row['data_criacao']); // PHP = America/Sao_Paulo
        $slaLimTs  = $criacaoTs + (GPON_SLA_HORAS         * 3600);
        $atenLimTs = $criacaoTs + (GPON_SLA_PROXIMO_HORAS * 3600);
        $row['sla_limite'] = date('Y-m-d H:i:s', $slaLimTs);
        if ($prev) {
            $prevTs = strtotime($prev);
            if ($prevTs > $slaLimTs)       $row['previsao_status'] = 'critica';
            elseif ($prevTs >= $atenLimTs) $row['previsao_status'] = 'atencao';
            else                           $row['previsao_status'] = 'ok';
        } else {
            $row['previsao_status'] = null;
        }
    } else {
        $row['sla_limite']      = null;
        $row['previsao_status'] = null;
    }

    return $row;
}

function gpon_update_repetidas(PDO $pdo): void
{
    // Reset tudo
    $pdo->exec("UPDATE ocorrencias SET repetida = 0");
    // Marca como repetida quando existe outro OC no mesmo GPON + Splitters + Localidade
    $pdo->exec("
        UPDATE ocorrencias o1
        INNER JOIN (
            SELECT gpon, splitters, localidade
            FROM ocorrencias
            WHERE gpon IS NOT NULL AND gpon <> ''
              AND splitters IS NOT NULL AND splitters <> ''
              AND splitters REGEXP 'SP[0-9]'
            GROUP BY gpon, splitters, localidade
            HAVING COUNT(*) > 1
        ) dupes ON o1.gpon = dupes.gpon
               AND o1.splitters = dupes.splitters
               AND (o1.localidade = dupes.localidade OR (o1.localidade IS NULL AND dupes.localidade IS NULL))
        SET o1.repetida = 1
    ");
}

// ── Análise de Reincidência ────────────────────────────────────
function gpon_criticidade(int $count, int $max = 0): string
{
    if ($count >= 5) return 'alta';
    if ($count >= 2) return 'media';
    return 'baixa';
}

// Extrai o splitter principal de um token bruto.
// Ex: "N03SP24SS1" → "SP24", "SP28" → "SP28"
function gpon_extract_main_sp(string $raw): string
{
    if (preg_match('/SP\d+/i', $raw, $m)) return strtoupper($m[0]);
    return '';
}

// Normaliza entrada do usuário para forma canônica: "20" / "020" / "sp20" → "SP20".
function gpon_normalize_sp(string $raw): string
{
    $raw = trim($raw);
    if (preg_match('/^(?:SP)?0*(\d+)$/i', $raw, $m)) {
        return 'SP' . $m[1];
    }
    return strtoupper($raw);
}

// Retorna padrão REGEXP MySQL para encontrar o splitter principal,
// evitando falsos positivos (SP24 ≠ SP248).
function gpon_sp_regexp(string $sp): string
{
    if (preg_match('/(\d+)$/', $sp, $m)) {
        return 'SP' . $m[1] . '([^0-9]|$)';
    }
    return preg_quote($sp, '/');
}

// ── Pipeline de filtros pós-SQL da página Análise ─────────────
//
// ARQUITETURA:
//   Filtros aplicáveis em SQL (uf, gpon, sp, baixa_causa, data_criacao)
//   são adicionados diretamente na cláusula WHERE das consultas.
//
//   Filtros que dependem de lógica PHP mais complexa (ex.: ocultar
//   improcedentes, que cruza dois campos com listas de valores) ficam
//   aqui — chamados UMA ÚNICA VEZ, por gpon_analise_data() e por
//   gpon_api_analise_historico(), sobre o $rows já retornado pelo SQL.
//
//   Para adicionar um novo filtro no futuro:
//     1. Receba o parâmetro no endpoint (gpon_api_analise / historico).
//     2. Adicione-o ao array $extra.
//     3. Implemente a função gpon_filtrar_xxx() abaixo.
//     4. Chame-a dentro de gpon_apply_analysis_filters().
//   Nenhum outro lugar precisa ser alterado.

// Remove ocorrências improcedentes ou normalizadas sem intervenção.
function gpon_filtrar_improcedentes(array $rows): array
{
    static $causasOcultar  = ['IMPROCEDENTE', 'SEM INTERVENÇÃO', 'SEM INTERVENCAO'];
    static $reparosOcultar = ['NORMALIZADO SEM INTERVENCAO', 'NAO SE APLICA'];

    return array_values(array_filter($rows, function (array $r) use ($causasOcultar, $reparosOcultar) {
        $causa  = strtoupper(trim((string)($r['baixa_causa']  ?? '')));
        $reparo = strtoupper(trim((string)($r['baixa_reparo'] ?? '')));
        return !in_array($causa, $causasOcultar, true)
            && !in_array($reparo, $reparosOcultar, true);
    }));
}

// Remove ocorrências da empresa FIBRASIL.
function gpon_filtrar_fibrasil(array $rows): array
{
    return array_values(array_filter($rows, function (array $r) {
        return strtoupper(trim((string)($r['empresa'] ?? ''))) !== 'FIBRASIL';
    }));
}

// Ponto único de entrada para todos os filtros pós-SQL da página Análise.
// Chamado por gpon_analise_data() e gpon_api_analise_historico() antes de
// qualquer agregação ou formatação dos dados.
function gpon_apply_analysis_filters(array $rows, array $extra): array
{
    if (!empty($extra['ocultar_improcedentes'])) {
        $rows = gpon_filtrar_improcedentes($rows);
    }
    if (!empty($extra['ocultar_fibrasil'])) {
        $rows = gpon_filtrar_fibrasil($rows);
    }

    return $rows;
}

// ── Preventiva: mapa chave_combinacao => última preventiva (ativa ou concluída) ─
function gpon_preventiva_map_por_chave(PDO $pdo, array $combKeys): array
{
    if (empty($combKeys)) return [];

    $chaves = [];
    foreach ($combKeys as $key) {
        [$gpon, $sp] = explode('|||', $key, 2);
        $chaves[] = strtoupper($gpon) . '|' . strtoupper($sp);
    }
    $chaves = array_values(array_unique($chaves));

    $map = [];
    $chunks = array_chunk($chaves, 500);
    foreach ($chunks as $chunk) {
        $ph = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare("SELECT id, chave_combinacao, status, concluido_em FROM preventivas_rede WHERE chave_combinacao IN ({$ph}) ORDER BY id DESC");
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            // ORDER BY id DESC: primeira ocorrência por chave já é a mais recente
            if (!isset($map[$row['chave_combinacao']])) {
                $map[$row['chave_combinacao']] = $row;
            }
        }
    }
    return $map;
}

function gpon_analise_data(PDO $pdo, ?string $inicio = null, ?string $fim = null, array $extra = []): array
{
    $tz  = new \DateTimeZone('America/Sao_Paulo');
    $now = new \DateTime('now', $tz);

    $todayStart = $now->format('Y-m-d') . ' 00:00:00'; // meia-noite Brasília (mesmo fuso dos dados)
    $ago7  = (clone $now)->modify('-7 days')->format('Y-m-d H:i:s');
    $ago15 = (clone $now)->modify('-15 days')->format('Y-m-d H:i:s');
    $ago30 = (clone $now)->modify('-30 days')->format('Y-m-d H:i:s');

    $where  = ["splitters IS NOT NULL", "splitters <> ''", "splitters REGEXP 'SP[0-9]'"];
    $params = [];
    $normSp = !empty($extra['sp']) ? gpon_normalize_sp($extra['sp']) : null;

    if ($inicio !== null && $fim !== null) {
        $where[]  = "data_criacao BETWEEN ? AND ?";
        $params[] = $inicio;
        $params[] = $fim;
    } elseif ($inicio !== null) {
        $where[]  = "data_criacao >= ?";
        $params[] = $inicio;
    }

    if (!empty($extra['uf'])) {
        $where[]  = "gpon LIKE ?";
        $params[] = $extra['uf'] . '%';
    }
    if (!empty($extra['gpon'])) {
        $where[]  = "gpon = ?";
        $params[] = $extra['gpon'];
    }
    if ($normSp !== null) {
        $where[]  = "splitters REGEXP ?";
        $params[] = gpon_sp_regexp($normSp);
    }
    if (!empty($extra['baixa_causa'])) {
        $where[]  = "baixa_causa LIKE ?";
        $params[] = '%' . $extra['baixa_causa'] . '%';
    }

    $stmt = $pdo->prepare(
        "SELECT oc, splitters, gpon, data_criacao, baixa_causa, baixa_reparo, localidade, empresa, aging_encerrados
         FROM ocorrencias WHERE " . implode(' AND ', $where)
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rows = gpon_apply_analysis_filters($rows, $extra);

    $totalReg = (int)$pdo->query("SELECT COUNT(*) FROM ocorrencias")->fetchColumn();

    $spCount     = [];
    $gponCount   = [];
    $gponMonthly = [];
    $combCount   = [];
    $spGpons     = [];

    $combDates   = [];
    $combLast    = [];
    $causaCount  = [];
    $causaReparo = [];
    $gponMttr    = [];
    $cidadeCount = [];
    $cidadeMttr  = [];
    $agingVals   = [];

    $filterSp = $normSp;

    foreach ($rows as $row) {
        $splitters = trim((string)($row['splitters']       ?? ''));
        $oc        = trim((string)($row['oc']              ?? ''));
        $gpon      = trim((string)($row['gpon']            ?? ''));
        $dt        = $row['data_criacao']                  ?? null;
        $causaRaw  = trim((string)($row['baixa_causa']  ?? ''));
        $causa     = $causaRaw !== '' ? trim(explode("\n", $causaRaw)[0]) : '';
        $reparoRaw = trim((string)($row['baixa_reparo'] ?? ''));
        $reparo    = $reparoRaw !== '' ? trim(explode("\n", $reparoRaw)[0]) : '';
        $cidade    = trim((string)($row['localidade']   ?? ''));
        $aging     = ($row['aging_encerrados'] !== null && $row['aging_encerrados'] !== '')
                     ? (int)$row['aging_encerrados'] : null;
        if ($aging !== null) $agingVals[] = $aging;

        if ($splitters === '') continue;

        if ($gpon !== '') {
            $gponCount[$gpon] = ($gponCount[$gpon] ?? 0) + 1;
            if ($dt !== null) {
                $ym = substr($dt, 0, 7);
                $gponMonthly[$gpon][$ym] = ($gponMonthly[$gpon][$ym] ?? 0) + 1;
            }
            if ($aging !== null && $aging > 0) {
                $gponMttr[$gpon][0] = ($gponMttr[$gpon][0] ?? 0) + $aging;
                $gponMttr[$gpon][1] = ($gponMttr[$gpon][1] ?? 0) + 1;
            }
        }
        if ($causa !== '') {
            $causaCount[$causa] = ($causaCount[$causa] ?? 0) + 1;
            if ($reparo !== '') {
                $causaReparo[$causa][$reparo] = ($causaReparo[$causa][$reparo] ?? 0) + 1;
            }
        }
        if ($cidade !== '') {
            $cidadeCount[$cidade] = ($cidadeCount[$cidade] ?? 0) + 1;
            if ($aging !== null) {
                $cidadeMttr[$cidade][0] = ($cidadeMttr[$cidade][0] ?? 0) + $aging;
                $cidadeMttr[$cidade][1] = ($cidadeMttr[$cidade][1] ?? 0) + 1;
            }
        }

        // Extrai splitters principais e deduplica dentro da mesma OC.
        // N03SP24SS1, N03SP24SS2 → apenas SP24 (conta 1 vez por OC).
        $mainSps = [];
        foreach (preg_split('/\s*,\s*/', $splitters) as $rawSp) {
            $rawSp = trim($rawSp);
            if ($rawSp === '') continue;
            $sp = gpon_extract_main_sp($rawSp);
            if ($sp !== '') $mainSps[] = $sp;
        }
        $mainSps = array_values(array_unique($mainSps));

        foreach ($mainSps as $sp) {
            if ($filterSp !== null && $sp !== $filterSp) continue;
            $spCount[$sp] = ($spCount[$sp] ?? 0) + 1;
            if ($gpon !== '') {
                $spGpons[$sp][$gpon] = true;
                $key = $gpon . '|||' . $sp;
                $combCount[$key] = ($combCount[$key] ?? 0) + 1;
                if ($dt !== null) {
                    $combDates[$key][] = $dt;
                    if (!isset($combLast[$key]) || $dt > $combLast[$key]['date']) {
                        $combLast[$key] = ['oc' => $oc, 'date' => $dt];
                    }
                }
            }
        }
    }
    unset($rows);

    arsort($spCount);
    arsort($gponCount);
    arsort($combCount);
    arsort($causaCount);

    $maxSp    = $spCount    ? max($spCount)    : 0;
    $maxGpon  = $gponCount  ? max($gponCount)  : 0;
    $maxComb  = $combCount  ? max($combCount)  : 0;
    $totSp    = array_sum($spCount);
    $totGpon  = array_sum($gponCount);

    $spRanking = [];
    $r = 1;
    foreach ($spCount as $sp => $cnt) {
        $spRanking[] = [
            'rank'        => $r++,
            'sp'          => $sp,
            'count'       => $cnt,
            'pct'         => $totSp > 0 ? round($cnt / $totSp * 100, 1) : 0,
            'criticidade' => gpon_criticidade($cnt, $maxSp),
            'gpon_count'  => count($spGpons[$sp] ?? []),
            'gpons'       => array_slice(array_keys($spGpons[$sp] ?? []), 0, 5),
        ];
    }
    unset($spGpons);

    // ── Batch: tendência anual para todos os GPONs do ranking (substitui N+1) ──
    $resumoMap = [];
    if (!empty($gponCount)) {
        $gponList  = array_keys($gponCount);
        $holders   = implode(',', array_fill(0, count($gponList), '?'));
        $inicioAno = $now->format('Y') . '-01-01 00:00:00';
        $rWhere    = ["gpon IN ($holders)", "data_criacao >= ?",
                      "splitters IS NOT NULL", "splitters <> ''", "splitters REGEXP 'SP[0-9]'"];
        $rParams   = array_values($gponList);
        $rParams[] = $inicioAno;
        if (!empty($extra['ocultar_improcedentes']))
            $rWhere[] = "LOWER(IFNULL(baixa_causa,'')) NOT LIKE '%improcedente%'";
        if (!empty($extra['ocultar_fibrasil']))
            $rWhere[] = "LOWER(IFNULL(empresa,'')) NOT LIKE '%fibrasil%'";

        $rStmt = $pdo->prepare(
            "SELECT gpon, DATE_FORMAT(data_criacao,'%Y-%m') AS mes, COUNT(*) AS total
             FROM ocorrencias WHERE " . implode(' AND ', $rWhere) .
            " GROUP BY gpon, mes ORDER BY gpon, mes"
        );
        $rStmt->execute($rParams);

        $byGpon    = [];
        $ptMonths  = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
        $curMonthN = (int)$now->format('n');
        $curYearS  = $now->format('Y');
        foreach ($rStmt->fetchAll(PDO::FETCH_ASSOC) as $rr) {
            $byGpon[$rr['gpon']][$rr['mes']] = (int)$rr['total'];
        }

        foreach ($gponList as $gpon) {
            $mesMap = $byGpon[$gpon] ?? [];
            // Meses fechados (< mês atual) para estatísticas; mês parcial entra apenas no total
            $meses = [];
            for ($i = 1; $i < $curMonthN; $i++) {
                $key    = $curYearS . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
                $meses[] = ['label' => $ptMonths[$i - 1], 'total' => $mesMap[$key] ?? 0];
            }
            $partKey  = $curYearS . '-' . str_pad($curMonthN, 2, '0', STR_PAD_LEFT);
            $partTot  = $mesMap[$partKey] ?? 0;

            $n      = count($meses);
            $totalF = array_sum(array_column($meses, 'total'));
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
                $g_ant = $half === 1 ? $meses[0]['label']          : $meses[0]['label']          . '–' . $meses[$half - 1]['label'];
                $g_ult = $half === 1 ? $meses[$n - $half]['label'] : $meses[$n - $half]['label'] . '–' . $meses[$n - 1]['label'];
            }
            $tend = 'estavel'; $tend_pct = 0;
            if ($ant > 0) {
                $tend_pct = round(($ult - $ant) / $ant * 100, 1);
                if ($tend_pct > 10)       $tend = 'crescimento';
                elseif ($tend_pct < -10)  $tend = 'reducao';
            } elseif ($ult > 0) {
                $tend = 'crescimento'; $tend_pct = 100;
            }
            $resumoMap[$gpon] = [
                'tendencia'     => $tend,
                'tendencia_pct' => $tend_pct,
                'total'         => $total,
                'media'         => $media,
                'n_meses'       => $n,
                'melhor_mes'    => $melhor,
                'pior_mes'      => $pior,
                'grupo_ant'     => $g_ant,
                'grupo_ult'     => $g_ult,
            ];
        }
    }

    $curYm  = $now->format('Y-m');
    $prevYm = (clone $now)->modify('-1 month')->format('Y-m');
    $gponRanking = [];
    $r = 1;
    foreach ($gponCount as $gpon => $cnt) {
        $tmrSum   = $gponMttr[$gpon][0] ?? 0;
        $tmrCnt   = $gponMttr[$gpon][1] ?? 0;
        $tmrHoras = $tmrCnt > 0 ? (float)round($tmrSum / $tmrCnt, 1) : null;
        $res = $resumoMap[$gpon] ?? null;
        $gponRanking[] = [
            'rank'          => $r++,
            'gpon'          => $gpon,
            'count'         => $cnt,
            'pct'           => $totGpon > 0 ? round($cnt / $totGpon * 100, 1) : 0,
            'criticidade'   => gpon_criticidade($cnt, $maxGpon),
            'tmr_horas'     => $tmrHoras,
            'tmr_count'     => $tmrCnt,
            'mes_ant'       => $gponMonthly[$gpon][$prevYm] ?? 0,
            'mes_atual'     => $gponMonthly[$gpon][$curYm]  ?? 0,
            'tendencia'     => $res['tendencia']     ?? 'estavel',
            'tendencia_pct' => $res['tendencia_pct'] ?? 0,
            'total_anual'   => $res['total']         ?? 0,
            'media_anual'   => $res['media']         ?? 0,
            'n_meses'       => $res['n_meses']       ?? 0,
            'melhor_mes'    => $res['melhor_mes']    ?? null,
            'pior_mes'      => $res['pior_mes']      ?? null,
            'grupo_ant'     => $res['grupo_ant']     ?? '',
            'grupo_ult'     => $res['grupo_ult']     ?? '',
        ];
    }

    $preventivaMap = gpon_preventiva_map_por_chave($pdo, array_keys($combCount));

    $combRanking = [];
    $r = 1;
    foreach ($combCount as $key => $cnt) {
        [$gpon, $sp] = explode('|||', $key, 2);
        $chave = strtoupper($gpon) . '|' . strtoupper($sp);
        $prev  = $preventivaMap[$chave] ?? null;
        $combRanking[] = [
            'rank'                    => $r++,
            'gpon'                    => $gpon,
            'sp'                      => $sp,
            'count'                   => $cnt,
            'pct'                     => $totSp > 0 ? round($cnt / $totSp * 100, 1) : 0,
            'criticidade'             => gpon_criticidade($cnt, $maxComb),
            'preventiva_id'           => $prev['id']           ?? null,
            'preventiva_status'       => $prev['status']       ?? null,
            'preventiva_concluido_em' => $prev['concluido_em'] ?? null,
        ];
    }

    $combUniq         = count($combCount);
    $combReincidentes = count(array_filter($combCount, fn($c) => $c > 1));
    $totalAnalisados  = array_sum($gponCount);
    $taxaReincidencia = $combReincidentes > 0
        ? round($totalAnalisados / $combReincidentes, 1) : 0;
    $ocReincidentes      = array_sum(array_filter($combCount, fn($c) => $c > 1));
    $indiceReincidencia  = $totalAnalisados > 0
        ? round($ocReincidentes / $totalAnalisados * 100, 2) : 0.0;

    // ── Heatmap (top 15 combos × dias do período) ─────────────
    $heatStart  = $inicio !== null
        ? new \DateTime(substr($inicio, 0, 10), $tz)
        : (clone $now)->modify('-29 days');
    $heatEnd    = $fim !== null
        ? new \DateTime(substr($fim, 0, 10), $tz)
        : clone $now;

    $heatDates  = [];
    $heatCursor = clone $heatStart;
    while ($heatCursor <= $heatEnd && count($heatDates) < 32) {
        $heatDates[] = $heatCursor->format('Y-m-d');
        $heatCursor->modify('+1 day');
    }

    $heatCombos = [];
    foreach (array_slice(array_keys($combCount), 0, 15) as $key) {
        [$g, $s] = explode('|||', $key, 2);
        $days    = array_fill_keys($heatDates, 0);
        foreach ($combDates[$key] ?? [] as $dt) {
            $d = substr($dt, 0, 10);
            if (isset($days[$d])) $days[$d]++;
        }
        // Reaproveita o mesmo $preventivaMap já calculado acima para o
        // ranking (comb_ranking) — sem consulta SQL adicional. Usado pela
        // coluna "Ação" do Heatmap no painel "Análise Preventiva" do
        // Preventivo; a página Análise ignora esses 3 campos extras.
        $prevChaveHm = strtoupper($g) . '|' . strtoupper($s);
        $prevHm      = $preventivaMap[$prevChaveHm] ?? null;
        $heatCombos[] = [
            'gpon'  => $g,
            'sp'    => $s,
            'total' => $combCount[$key],
            'crit'  => gpon_criticidade($combCount[$key], $maxComb),
            'days'  => $days,
            'preventiva_id'           => $prevHm['id']           ?? null,
            'preventiva_status'       => $prevHm['status']       ?? null,
            'preventiva_concluido_em' => $prevHm['concluido_em'] ?? null,
        ];
    }

    // ── Timeline (top 10 combos com contagens por período) ────
    $timeline = [];
    foreach (array_slice(array_keys($combCount), 0, 10) as $key) {
        [$g, $s] = explode('|||', $key, 2);
        $dts  = $combDates[$key] ?? [];
        $hoje = 0; $d7 = 0; $d15 = 0; $d30 = 0;
        foreach ($dts as $dt) {
            if ($dt >= $todayStart) $hoje++;
            if ($dt >= $ago7)       $d7++;
            if ($dt >= $ago15)      $d15++;
            if ($dt >= $ago30)      $d30++;
        }
        $timeline[] = [
            'gpon'  => $g, 'sp' => $s,
            'hoje'  => $hoje, '7d' => $d7, '15d' => $d15, '30d' => $d30,
            'crit'  => gpon_criticidade($combCount[$key], $maxComb),
        ];
    }

    // ── Causas ranking (top 15) ────────────────────────────────
    $causasRanking = [];
    $r = 1;
    foreach (array_slice($causaCount, 0, 15, true) as $causa => $cnt) {
        $reparos   = $causaReparo[$causa] ?? [];
        arsort($reparos);
        $topReparo = $reparos ? array_key_first($reparos) : null;
        $causasRanking[] = [
            'rank'       => $r++, 'causa' => $causa, 'count' => $cnt,
            'pct'        => $totalAnalisados > 0 ? round($cnt / $totalAnalisados * 100, 1) : 0,
            'top_reparo' => $topReparo,
        ];
    }

    // ── MTTR por cidade (top 10) ───────────────────────────────
    arsort($cidadeCount);
    $mttrSum = 0; $mttrCnt = 0;
    foreach ($cidadeMttr as $item) { $mttrSum += $item[0]; $mttrCnt += $item[1]; }
    $mttrGeral  = $mttrCnt > 0 ? round($mttrSum / $mttrCnt, 1) : null;
    $mttrCidade = [];
    foreach (array_slice($cidadeCount, 0, 10, true) as $cidade => $cnt) {
        $mttrVal = (isset($cidadeMttr[$cidade]) && $cidadeMttr[$cidade][1] > 0)
            ? round($cidadeMttr[$cidade][0] / $cidadeMttr[$cidade][1], 1) : null;
        $mttrCidade[] = ['cidade' => $cidade, 'count' => $cnt, 'mttr' => $mttrVal];
    }

    // ── Última Reincidência — OC mais recente por combo ───────────
    $lastSeen = [];
    foreach ($combLast as $key => $info) {
        [$g, $s] = explode('|||', $key, 2);
        $lastSeen[] = [
            'gpon'  => $g,
            'sp'    => $s,
            'oc'    => $info['oc'],
            'date'  => $info['date'],
            'count' => $combCount[$key],
            'crit'  => gpon_criticidade($combCount[$key], $maxComb),
        ];
    }
    usort($lastSeen, fn($a, $b) => strcmp($b['date'], $a['date']));

    // ── Filtro ocultar_improcedentes (pré-agregação já aplicado) ─

    $cidadeTop      = $cidadeCount ? array_key_first($cidadeCount) : '—';
    $cidadeTopCount = $cidadeCount ? $cidadeCount[$cidadeTop]       : 0;

    return [
        'sp_ranking'   => $spRanking,
        'gpon_ranking' => $gponRanking,
        'comb_ranking' => $combRanking,
        'chart_sp'     => array_slice($spRanking,   0, 10),
        'chart_gpon'   => array_slice($gponRanking, 0, 10),
        'heatmap'      => ['dates' => $heatDates, 'combos' => $heatCombos],
        'timeline'     => $timeline,
        'last_seen'    => $lastSeen,
        'causas'       => $causasRanking,
        'mttr'         => ['geral' => $mttrGeral, 'por_cidade' => $mttrCidade],
        'totals'       => [
            'registros'         => $totalReg,
            'analisados'        => $totalAnalisados,
            'gpon_uniq'         => count($gponCount),
            'comb_uniq'         => $combUniq,
            'comb_reincidentes'   => $combReincidentes,
            'taxa_reincidencia'   => $taxaReincidencia,
            'oc_reincidentes'     => $ocReincidentes,
            'indice_reincidencia' => $indiceReincidencia,
            'top_comb'          => $combRanking[0] ?? null,
            'cidade_top'        => $cidadeTop,
            'cidade_top_count'  => $cidadeTopCount,
            'mttr_geral'        => $mttrGeral,
            'sp_uniq'           => count($spCount),
            'mais_afetado'      => $spRanking[0]['sp']    ?? '—',
            'mais_ocorr'        => $spRanking[0]['count'] ?? 0,
            'tmr'               => count($agingVals) > 0 ? (int)round(array_sum($agingVals) / count($agingVals)) : null,
            'tmr_count'         => count($agingVals),
        ],
    ];
}

function gpon_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function gpon_sanitize($v): ?string
{
    if ($v === null || $v === '') return null;
    return trim((string)$v);
}


// Includes de API e views são carregados condicionalmente em index.php (M13)
