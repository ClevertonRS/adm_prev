<?php
// ── HTML: Login ────────────────────────────────────────────────
function gpon_render_login(): void
{
    gpon_session_start();
    $err = $_SESSION['gpon_flash_error'] ?? '';
    unset($_SESSION['gpon_flash_error']);
    $base = GPON_BASE_PATH;
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Radar GPON — Login</title>
  <link rel="icon" type="image/svg+xml" href="<?= $base ?>/assets/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/gpon.css?v=<?= filemtime(__DIR__ . '/../assets/css/gpon.css') ?>">
</head>
<body>
<div class="login-wrapper">
  <div class="login-card">
    <div class="login-header">
      <div class="login-logo">📡</div>
      <h1 class="login-title">Radar GPON</h1>
      <p class="login-sub">Gestão de Ocorrências GPON</p>
    </div>
    <div class="login-body">
      <?php if ($err): ?>
        <div class="login-alert error"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
      <form method="POST" action="<?= $base ?>/login">
        <div class="login-group">
          <label class="login-label">Usuário</label>
          <div class="login-input-wrap">
            <i class="bi bi-person"></i>
            <input type="text" name="usuario" class="login-input" placeholder="seu.usuario" required autocomplete="username">
          </div>
        </div>
        <div class="login-group">
          <label class="login-label">Senha</label>
          <div class="login-input-wrap">
            <i class="bi bi-lock"></i>
            <input type="password" name="senha" class="login-input" placeholder="••••••••" required autocomplete="current-password">
          </div>
        </div>
        <button type="submit" class="login-btn"><i class="bi bi-box-arrow-in-right"></i> Entrar</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
    <?php exit;
}
