<?php
require __DIR__ . '/../includes/config.php';

$settings = load_data()['settings'];

if (!empty($_SESSION['agent_logged_in'])) {
    header('Location: ' . url('agent-portal/index.php'));
    exit;
}

$error = '';
$prefillUser = '';
$lockedUntil = (int) ($_SESSION['agent_login_locked_until'] ?? 0);
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

        $agent = find_agent_by_username($username);
        $ok = $agent && !empty($agent['active']) && !empty($agent['password_hash'])
            && password_verify($password, $agent['password_hash']);

        if ($ok) {
            unset($_SESSION['agent_login_fail_count'], $_SESSION['agent_login_locked_until']);
            session_regenerate_id(true);
            $_SESSION['agent_logged_in'] = true;
            $_SESSION['agent_id'] = (int) $agent['id'];
            $_SESSION['agent_name'] = $agent['name'];

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
                ini_set('session.gc_maxlifetime', (string) $lifetime);
            }

            $data = load_data();
            foreach ($data['agents'] as &$a) {
                if ((int) $a['id'] === (int) $agent['id']) {
                    $a['last_login_at'] = date('Y-m-d H:i:s');
                }
            }
            unset($a);
            save_data($data);

            $target = safe_internal_path($_POST['redirect'] ?? null, url('agent-portal/index.php'));
            header('Location: ' . $target);
            exit;
        }

        $fails = (int) ($_SESSION['agent_login_fail_count'] ?? 0) + 1;
        $_SESSION['agent_login_fail_count'] = $fails;
        if ($fails >= 5) {
            $_SESSION['agent_login_locked_until'] = time() + 600;
            $_SESSION['agent_login_fail_count'] = 0;
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
<title>כניסת סוכנים — איזור סוכנים <?= e($settings['agency_name']) ?></title>
<meta name="robots" content="noindex, nofollow">
<link href="https://fonts.googleapis.com/css2?family=Frank+Ruhl+Libre:wght@400;500;700;900&family=Heebo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('admin/assets/admin.css')) ?>">
</head>
<body class="admin-body">
<div class="admin-login-wrap">
  <div class="admin-login-card">
    <p class="admin-login-logo" dir="ltr">Nadlanis<span style="color:var(--blue);">Team</span></p>
    <h1>כניסת סוכנים</h1>

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
</div>
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
