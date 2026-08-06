<?php
// ── API: Admin — Usuários ───────────────────────────────────────

// ── API: Admin — Usuários ──────────────────────────────────────
function gpon_api_usuarios_list(PDO $pdo): void
{
    gpon_require_admin();
    $rows = $pdo->query("SELECT id, nome, usuario, nivel, status, ultimo_acesso FROM usuarios ORDER BY nome")->fetchAll();
    gpon_json(['ok' => true, 'users' => $rows]);
}

function gpon_api_usuario_get(PDO $pdo, int $id): void
{
    gpon_require_admin();
    $stmt = $pdo->prepare("SELECT id, nome, usuario, nivel, status FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    if (!$u) gpon_json(['ok' => false, 'message' => 'Não encontrado'], 404);
    gpon_json(['ok' => true, 'user' => $u]);
}

function gpon_api_usuario_create(PDO $pdo): void
{
    gpon_require_admin();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $nome    = trim($body['nome']    ?? '');
    $usuario = trim($body['usuario'] ?? '');
    $senha   = $body['senha']   ?? '';
    $nivel   = in_array($body['nivel'] ?? '', ['admin','operador','backoffice'], true) ? $body['nivel'] : 'operador';
    $status  = isset($body['status']) ? (int)$body['status'] : 1;

    if (!$nome || !$usuario || !$senha) gpon_json(['ok' => false, 'message' => 'Nome, usuário e senha são obrigatórios']);

    try {
        $pdo->prepare("INSERT INTO usuarios (nome, usuario, senha, nivel, status) VALUES (?,?,?,?,?)")
            ->execute([$nome, $usuario, password_hash($senha, PASSWORD_DEFAULT), $nivel, $status]);
        gpon_json(['ok' => true]);
    } catch (\PDOException $e) {
        if ($e->getCode() === '23000') gpon_json(['ok' => false, 'message' => 'Usuário já existe']);
        gpon_json(['ok' => false, 'message' => $e->getMessage()]);
    }
}

function gpon_api_usuario_update(PDO $pdo, int $id): void
{
    gpon_require_admin();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $nome    = trim($body['nome']    ?? '');
    $usuario = trim($body['usuario'] ?? '');
    $nivel   = in_array($body['nivel'] ?? '', ['admin','operador','backoffice'], true) ? $body['nivel'] : 'operador';
    $status  = isset($body['status']) ? (int)$body['status'] : 1;
    $senha   = $body['senha'] ?? '';

    if (!$nome || !$usuario) gpon_json(['ok' => false, 'message' => 'Nome e usuário são obrigatórios']);

    if ($senha) {
        $pdo->prepare("UPDATE usuarios SET nome=?, usuario=?, nivel=?, status=?, senha=? WHERE id=?")
            ->execute([$nome, $usuario, $nivel, $status, password_hash($senha, PASSWORD_DEFAULT), $id]);
    } else {
        $pdo->prepare("UPDATE usuarios SET nome=?, usuario=?, nivel=?, status=? WHERE id=?")
            ->execute([$nome, $usuario, $nivel, $status, $id]);
    }
    gpon_json(['ok' => true]);
}

function gpon_api_usuario_delete(PDO $pdo, int $id, array $user): void
{
    gpon_require_admin();
    if ($id === $user['id']) gpon_json(['ok' => false, 'message' => 'Não é possível excluir o próprio usuário']);
    $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);
    gpon_json(['ok' => true]);
}
