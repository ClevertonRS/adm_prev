<?php
// ── Autenticação ───────────────────────────────────────────────
function gpon_session_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name('GPON_SESS');
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();
    }
}

function gpon_current_user(): ?array
{
    gpon_session_start();
    if (empty($_SESSION['gpon_user_id'])) return null;
    return [
        'id'      => $_SESSION['gpon_user_id'],
        'nome'    => $_SESSION['gpon_nome']    ?? 'Usuário',
        'usuario' => $_SESSION['gpon_usuario'] ?? '',
        'nivel'   => $_SESSION['gpon_nivel']   ?? 'operador',
    ];
}

function gpon_require_login(): array
{
    $user = gpon_current_user();
    if ($user === null) {
        header('Location: ' . GPON_BASE_PATH . '/login');
        exit;
    }
    return $user;
}

function gpon_require_admin(): array
{
    $user = gpon_require_login();
    if ($user['nivel'] !== 'admin') {
        http_response_code(403);
        die(json_encode(['ok' => false, 'message' => 'Acesso negado']));
    }
    return $user;
}

function gpon_is_admin(): bool
{
    $u = gpon_current_user();
    return $u !== null && $u['nivel'] === 'admin';
}

function gpon_is_backoffice(): bool
{
    $u = gpon_current_user();
    return $u !== null && $u['nivel'] === 'backoffice';
}

function gpon_is_admin_or_backoffice(): bool
{
    $u = gpon_current_user();
    return $u !== null && in_array($u['nivel'], ['admin', 'backoffice'], true);
}

function gpon_require_admin_or_backoffice(): array
{
    $user = gpon_require_login();
    if (!in_array($user['nivel'], ['admin', 'backoffice'], true)) {
        header('Location: ' . GPON_BASE_PATH . '/');
        exit;
    }
    return $user;
}

function gpon_require_admin_or_backoffice_api(): array
{
    $user = gpon_require_login();
    if (!in_array($user['nivel'], ['admin', 'backoffice'], true)) {
        http_response_code(403);
        die(json_encode(['ok' => false, 'message' => 'Acesso negado']));
    }
    return $user;
}

// Checagem genérica de papel, usada por múltiplos módulos (ocorrências, histórico,
// preventivo) para evitar comparações de string repetidas e divergentes entre arquivos.
function gpon_user_has_role(array $user, array $roles): bool
{
    return in_array($user['nivel'] ?? 'operador', $roles, true);
}

function gpon_handle_login(PDO $pdo): void
{
    gpon_session_start();
    $usuario = trim($_POST['usuario'] ?? '');
    $senha   = $_POST['senha'] ?? '';

    if (!$usuario || !$senha) {
        $_SESSION['gpon_flash_error'] = 'Preencha usuário e senha.';
        header('Location: ' . GPON_BASE_PATH . '/login');
        exit;
    }

    // Rate limiting: bloqueia após 5 tentativas incorretas por 5 minutos
    $lockKey   = 'gpon_login_fails_' . md5($usuario);
    $lockUntil = 'gpon_login_lock_' . md5($usuario);
    if (!empty($_SESSION[$lockUntil]) && time() < $_SESSION[$lockUntil]) {
        $wait = ceil(($_SESSION[$lockUntil] - time()) / 60);
        $_SESSION['gpon_flash_error'] = "Muitas tentativas. Aguarde {$wait} min.";
        header('Location: ' . GPON_BASE_PATH . '/login');
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ? AND status = 1 LIMIT 1");
    $stmt->execute([$usuario]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($senha, $row['senha'])) {
        $_SESSION[$lockKey] = ($_SESSION[$lockKey] ?? 0) + 1;
        if ($_SESSION[$lockKey] >= 5) {
            $_SESSION[$lockUntil] = time() + 300;
            $_SESSION[$lockKey]   = 0;
        }
        $_SESSION['gpon_flash_error'] = 'Usuário ou senha inválidos.';
        header('Location: ' . GPON_BASE_PATH . '/login');
        exit;
    }

    // Login bem-sucedido: limpar contadores
    unset($_SESSION[$lockKey], $_SESSION[$lockUntil]);

    // Somente admin ou supervisor podem acessar o sistema.
    if (!in_array($row['nivel'], ['admin', 'supervisor'], true)) {
        $_SESSION['gpon_flash_error'] = 'Acesso restrito a administradores e supervisores.';
        header('Location: ' . GPON_BASE_PATH . '/login');
        exit;
    }

    $_SESSION['gpon_user_id'] = $row['id'];
    $_SESSION['gpon_nome']    = $row['nome'];
    $_SESSION['gpon_usuario'] = $row['usuario'];
    $_SESSION['gpon_nivel']   = $row['nivel'];

    $pdo->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?")->execute([$row['id']]);

    header('Location: ' . GPON_BASE_PATH . '/');
    exit;
}

function gpon_handle_logout(): void
{
    gpon_session_start();
    session_unset();
    session_destroy();
    header('Location: ' . GPON_BASE_PATH . '/login');
    exit;
}
