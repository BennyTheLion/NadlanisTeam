<?php
require __DIR__ . '/../includes/config.php';

$settings = get_settings();

if (admin_exists()) {
    header('Location: ' . url('admin/login.php'));
    exit;
}

$errors = [];
$prefillUser = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors['_general'] = 'הפעולה פגה, נסו שוב.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        $prefillUser = $username;

        if ($username === '') {
            $errors['username'] = 'יש להזין שם משתמש.';
        }
        if (mb_strlen($password) < 8) {
            $errors['password'] = 'הסיסמה חייבת להכיל לפחות 8 תווים.';
        } elseif ($password !== $confirm) {
            $errors['password_confirm'] = 'אימות הסיסמה אינו תואם.';
        }

        if (!$errors) {
            $userId = create_admin_user($username, password_hash($password, PASSWORD_DEFAULT));

            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_role'] = 'admin';
            $_SESSION['user_name'] = $username;

            header('Location: ' . url('admin/index.php'));
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>הגדרה ראשונית — ניהול <?= e($settings['agency_name']) ?></title>
<meta name="robots" content="noindex, nofollow">
<link href="https://fonts.googleapis.com/css2?family=Frank+Ruhl+Libre:wght@400;500;700;900&family=Heebo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('admin/assets/admin.css')) ?>">
</head>
<body class="admin-body">
<main class="admin-login-wrap">
  <div class="admin-login-card">
    <p class="admin-login-logo" dir="ltr">Nadlanis<span style="color:var(--blue);">Team</span></p>
    <h1>הגדרה ראשונית</h1>
    <p class="lede" style="text-align:center; margin-bottom: var(--space-3);">זו הפעם הראשונה שהמערכת עולה — צרו את חשבון המנהל.</p>

    <?php if (!empty($errors['_general'])): ?><p class="alert-box alert-error"><?= e($errors['_general']) ?></p><?php endif; ?>

    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="field <?= isset($errors['username']) ? 'has-error' : '' ?>">
        <label for="username">שם משתמש</label>
        <input class="input" type="text" id="username" name="username" value="<?= e($prefillUser) ?>" required autofocus>
        <?php if (isset($errors['username'])): ?><span class="error"><?= e($errors['username']) ?></span><?php endif; ?>
      </div>
      <div class="field <?= isset($errors['password']) ? 'has-error' : '' ?>">
        <label for="password">סיסמה (לפחות 8 תווים)</label>
        <input class="input" type="password" id="password" name="password" required minlength="8">
        <?php if (isset($errors['password'])): ?><span class="error"><?= e($errors['password']) ?></span><?php endif; ?>
      </div>
      <div class="field <?= isset($errors['password_confirm']) ? 'has-error' : '' ?>">
        <label for="password_confirm">אימות סיסמה</label>
        <input class="input" type="password" id="password_confirm" name="password_confirm" required minlength="8">
        <?php if (isset($errors['password_confirm'])): ?><span class="error"><?= e($errors['password_confirm']) ?></span><?php endif; ?>
      </div>
      <button type="submit" class="btn btn-primary btn-block">יצירת חשבון מנהל</button>
    </form>
  </div>
</main>
</body>
</html>
