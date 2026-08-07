<?php
// Compatibilidade PHP 7.4+
@ini_set('upload_max_filesize', '64M');
@ini_set('post_max_size', '68M');

/* ============================================================
   RADAR GPON — Módulo de Gestão de Ocorrências GPON
   Arquivo principal: router + handlers + renderers
   ============================================================ */

// ── Configurações ─────────────────────────────────────────────
define('GPON_BASE_PATH',           '/admin');
define('GPON_SLA_HORAS',           8);     // SLA padrão em horas
define('GPON_SLA_PROXIMO_HORAS',   6);     // A partir disso: "Atenção"
define('GPON_REPETIDA_JANELA',     90);    // Janela em dias para identificar repetidas
define('GPON_TIMEZONE',            'America/Cuiaba');

date_default_timezone_set('America/Sao_Paulo'); // Brasília: mesma referência das datas da planilha

// Debug via variável de ambiente GPON_DEBUG=1 (nunca via parâmetro GET em produção)
if (getenv('GPON_DEBUG') === '1') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';


// ══════════════════════════════════════════════════════════════
// ROUTER
// ══════════════════════════════════════════════════════════════
try {
    $pdo = gpon_pdo();
    gpon_init_db($pdo);
} catch (\PDOException $e) {
    error_log('GPON DB error: ' . $e->getMessage());
    http_response_code(503);
    echo '<h1 style="font-family:sans-serif;color:#dc2626">Erro de conexão com o banco de dados</h1>';
    echo '<p style="font-family:sans-serif">Serviço temporariamente indisponível. Contate o administrador.</p>';
    exit;
}

gpon_session_start();
$user = gpon_current_user();

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base   = GPON_BASE_PATH;
$path   = preg_replace('#^' . preg_quote($base, '#') . '#', '', $uri);
$path   = '/' . ltrim($path, '/');
$path   = rtrim($path, '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'];

// ── Includes condicionais por rota ─────────────────────────────
(static function (string $p): void {
    $a = __DIR__ . '/api/';
    $v = __DIR__ . '/views/';
    if ($p === '/login')                                                      { require_once $v . 'login.php'; return; }
    if ($p === '/logout')                                                     { return; }
    if ($p === '/upload')                                                     { require_once $a . 'importacao.php'; require_once $a . 'exportacao.php'; return; }
    if (in_array($p, ['/api/data','/api/reinc-counts','/api/stats','/api/filters'], true)) { require_once $a . 'dashboard.php'; return; }
    if (preg_match('#^/api/ocorrencia/#', $p))                                { require_once $a . 'ocorrencias.php'; return; }
    if (preg_match('#^/api/historico|^/api/previsao/#', $p))                  { require_once $a . 'historico.php'; return; }
    if (preg_match('#^/api/admin/usuario#', $p))                              { require_once $a . 'admin.php'; return; }
    if (preg_match('#^/api/admin/gpon-empresas#', $p) || $p === '/api/gpon-nao-mapeados') { require_once $a . 'gpon_empresas.php'; return; }
    if ($p === '/api/ultima-atualizacao' || $p === '/exportar')               { require_once $a . 'exportacao.php'; return; }
    if (strpos($p, '/api/analise') === 0)                                     { require_once $a . 'analise.php'; return; }
    if (preg_match('#^/api/preventiva#', $p) || preg_match('#^/preventivo#', $p)) { require_once $a . 'preventiva.php'; return; }
    if ($p === '/' || $p === '')     { require_once $a . 'dashboard.php'; require_once $a . 'exportacao.php'; require_once $v . 'dashboard.php'; return; }
    if ($p === '/analise')           { require_once $a . 'analise.php';   require_once $a . 'exportacao.php'; require_once $v . 'analise.php';   return; }
    if ($p === '/admin')             { require_once $a . 'admin.php';     require_once $v . 'admin.php';      return; }
})($path);

// Rotas públicas
if ($path === '/login') {
    if ($method === 'POST') gpon_handle_login($pdo);
    gpon_render_login();
    exit;
}

if ($path === '/logout') {
    gpon_handle_logout();
    exit;
}

// Todas as demais rotas requerem login
if (!$user) {
    header('Location: ' . GPON_BASE_PATH . '/login');
    exit;
}

// ── API routes ─────────────────────────────────────────────────
if ($path === '/upload' && $method === 'POST') {
    gpon_handle_upload($pdo);
}

if ($path === '/api/data') {
    gpon_api_data($pdo);
}

if ($path === '/api/reinc-counts') {
    gpon_api_reinc_counts($pdo);
}

if ($path === '/api/stats') {
    gpon_api_stats($pdo);
}

if ($path === '/api/filters') {
    gpon_api_filters($pdo);
}

// /api/ocorrencia/{id}
if (preg_match('#^/api/ocorrencia/(\d+)$#', $path, $m)) {
    $id = (int)$m[1];
    if ($method === 'GET')         gpon_api_ocorrencia_get($pdo, $id);
    elseif ($method === 'PUT')     gpon_api_ocorrencia_put($pdo, $id, $user);
    elseif ($method === 'DELETE')  gpon_api_ocorrencia_delete($pdo, $id, $user);
    else                           gpon_json(['ok' => false, 'message' => 'Método não suportado'], 405);
}

// /api/historico/{id}
if (preg_match('#^/api/historico/(\d+)$#', $path, $m)) {
    $id = (int)$m[1];
    if ($method === 'GET')  gpon_api_historico_get($pdo, $id);
    if ($method === 'POST') gpon_api_historico_post($pdo, $id, $user);
}

// /api/historico-item/{id}  (editar / excluir comentário individual)
if (preg_match('#^/api/historico-item/(\d+)$#', $path, $m)) {
    $id = (int)$m[1];
    if ($method === 'PUT')    gpon_api_historico_item_put($pdo, $id, $user);
    if ($method === 'DELETE') gpon_api_historico_item_delete($pdo, $id, $user);
}

// /api/previsao/{id}  (previsão de finalização da ocorrência)
if (preg_match('#^/api/previsao/(\d+)$#', $path, $m)) {
    $id = (int)$m[1];
    if ($method === 'PUT') gpon_api_previsao_put($pdo, $id, $user);
}

// /api/preventiva routes
if (function_exists('gpon_preventiva_routes') && gpon_preventiva_routes($pdo, $user, $path, $method)) {
    exit;
}

if ($path === '/preventivo' || $path === '/preventiva') {
    require_once __DIR__ . '/preventivo/views/preventivo.php';
    exit;
}

if (preg_match('#^/preventivo/(\d+)$#', $path, $m)) {
    require_once __DIR__ . '/preventivo/views/detalhe.php';
    exit;
}

if (preg_match('#^/analise/(\d+)$#', $path, $m)) {
    require_once __DIR__ . '/preventivo/views/analise-detalhe.php';
    exit;
}

if (preg_match('#^/concluida/(\d+)$#', $path, $m)) {
    require_once __DIR__ . '/preventivo/views/conclusao-detalhe.php';
    exit;
}

// Admin routes
if ($path === '/api/admin/usuarios') {
    if ($method === 'GET')  gpon_api_usuarios_list($pdo);
    if ($method === 'POST') gpon_api_usuario_create($pdo);
}

if (preg_match('#^/api/admin/usuario/(\d+)$#', $path, $m)) {
    $id = (int)$m[1];
    if ($method === 'GET')    gpon_api_usuario_get($pdo, $id);
    if ($method === 'PUT')    gpon_api_usuario_update($pdo, $id);
    if ($method === 'DELETE') gpon_api_usuario_delete($pdo, $id, $user);
}

// GPON Empresas routes
if ($path === '/api/admin/gpon-empresas') {
    if ($method === 'GET')  gpon_api_gpon_empresas_list($pdo);
    if ($method === 'POST') gpon_api_gpon_empresas_create($pdo, $user);
}

if (preg_match('#^/api/admin/gpon-empresas/(\d+)$#', $path, $m)) {
    $id = (int)$m[1];
    if ($method === 'PUT')    gpon_api_gpon_empresas_update($pdo, $id, $user);
    if ($method === 'DELETE') gpon_api_gpon_empresas_delete($pdo, $id, $user);
}

if ($path === '/api/gpon-nao-mapeados') {
    if ($method === 'GET') gpon_api_gpon_nao_mapeados($pdo);
}

// ── HTML page routes ───────────────────────────────────────────
if ($path === '/' || $path === '') {
    gpon_render_dashboard($pdo, $user);
    exit;
}

if ($path === '/api/ultima-atualizacao') {
    gpon_api_ultima_atualizacao($pdo);
    exit;
}

if ($path === '/exportar') {
    gpon_handle_exportar($pdo);
    exit;
}

if ($path === '/api/analise') {
    try { gpon_api_analise($pdo); } catch (\Throwable $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => true, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($path === '/api/analise/historico') {
    try { gpon_api_analise_historico($pdo); } catch (\Throwable $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => true, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($path === '/api/analise/analitico') {
    try { gpon_api_analise_analitico($pdo); } catch (\Throwable $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => true, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($path === '/api/analise/resumo') {
    try { gpon_api_analise_resumo($pdo); } catch (\Throwable $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => true, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($path === '/analise') {
    gpon_render_analise($pdo, $user);
    exit;
}

if ($path === '/admin') {
    if (!gpon_is_admin()) {
        header('Location: ' . GPON_BASE_PATH . '/');
        exit;
    }
    gpon_render_admin($pdo, $user);
    exit;
}

// 404
http_response_code(404);
echo '<!DOCTYPE html><html lang="pt-BR"><head><title>404</title>
<link rel="stylesheet" href="' . GPON_BASE_PATH . '/assets/css/gpon.css?v=' . filemtime(__DIR__ . '/assets/css/gpon.css') . '"></head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;background:var(--gpon-bg)">
<div style="text-align:center;color:var(--gpon-muted)">
  <i class="bi bi-question-circle" style="font-size:48px;display:block;margin-bottom:12px"></i>
  <h2>Página não encontrada</h2>
  <a href="' . GPON_BASE_PATH . '/" style="color:var(--gpon-primary)">Voltar ao Painel</a>
</div></body></html>';
