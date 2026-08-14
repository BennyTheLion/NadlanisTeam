<?php
require __DIR__ . '/../includes/config.php';

$settings = get_settings();

if (!admin_exists()) {
    header('Location: ' . url('admin/setup.php'));
    exit;
}

if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    header('Location: ' . url('admin/index.php'));
    exit;
}

$error = '';
$prefillUser = '';
$lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);
$isLocked = $lockedUntil > time();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isLocked) {
        $minutes = (int) ceil(($lockedUntil - time()) / 60);
        $error = 'יותר מדי ניסיונות כושלים. נסו שוב בעוד ' . $minutes . ' דקות.';
    } elseif (!csrf_check()) {
        $error = 'הפעולה פגה, נסו שוב.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $prefillUser = $username;

        $user = verify_login($username, $password);
        $ok = $user && $user['role'] === 'admin';

        if ($ok) {
            unset($_SESSION['login_fail_count'], $_SESSION['login_locked_until']);
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = 'admin';
            $_SESSION['user_name'] = $user['username'];
            set_user_last_login($user['id']);

            if (!empty($_POST['remember'])) {
                $lifetime = 60 * 60 * 24 * 30;
                $params = session_get_cookie_params();
                setcookie(session_name(), session_id(), [
                    'expires' => time() + $lifetime,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?: 'Lax',
                ]);
            }

            $redirect = $_POST['redirect'] ?? '';
            $target = (is_string($redirect) && $redirect !== '' && $redirect[0] === '/' && !str_starts_with($redirect, '//'))
                ? $redirect
                : url('admin/index.php');
            header('Location: ' . $target);
            exit;
        }

        $fails = (int) ($_SESSION['login_fail_count'] ?? 0) + 1;
        $_SESSION['login_fail_count'] = $fails;
        if ($fails >= 5) {
            $_SESSION['login_locked_until'] = time() + 600;
            $_SESSION['login_fail_count'] = 0;
            $error = 'יותר מדי ניסיונות כושלים. נסו שוב בעוד 10 דקות.';
        } else {
            $error = 'שם משתמש או סיסמה שגויים.';
        }
    }
}
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>כניסת מנהל — ניהול <?= e($settings['agency_name']) ?></title>
<meta name="robots" content="noindex, nofollow">
<link href="https://fonts.googleapis.com/css2?family=Frank+Ruhl+Libre:wght@400;500;700;900&family=Heebo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('admin/assets/admin.css')) ?>">
</head>
<body class="admin-body">
<main class="admin-login-wrap">
  <div class="admin-login-card">
    <p class="admin-login-logo" dir="ltr">Nadlanis<span style="color:var(--blue);">Team</span></p>
    <h1>כניסת מנהל</h1>

    <?php if ($error): ?><p class="alert-box alert-error"><?= e($error) ?></p><?php endif; ?>

    <form method="post" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="redirect" value="<?= e($_GET['redirect'] ?? '') ?>">
      <div class="field">
        <label for="username">שם משתמש</label>
        <input class="input" type="text" id="username" name="username" value="<?= e($prefillUser) ?>" required autofocus>
      </div>
      <div class="field">
        <label for="password">סיסמה</label>
        <div class="password-field">
          <input class="input" type="password" id="password" name="password" required>
          <button type="button" class="password-toggle" data-target="password" aria-label="הצג סיסמה" aria-pressed="false">👁</button>
        </div>
      </div>
      <label class="checkbox-row" style="margin-bottom: var(--space-2);">
        <input type="checkbox" name="remember" value="1">
        <span>זכור אותי</span>
      </label>
      <button type="submit" class="btn btn-primary btn-block" <?= $isLocked ? 'disabled' : '' ?>>כניסה</button>
    </form>
  </div>
</main>
<script>
document.querySelectorAll('.password-toggle').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var input = document.getElementById(btn.dataset.target);
    var showing = input.type === 'text';
    input.type = showing ? 'password' : 'text';
    btn.setAttribute('aria-pressed', showing ? 'false' : 'true');
    btn.textContent = showing ? '👁' : '🙈';
  });
});
</script>
</body>
</html>
