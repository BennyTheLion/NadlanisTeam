<?php
require __DIR__ . '/includes/auth.php';

$errors = [];
$successMsg = null;
$settings = get_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $s = [
            'agency_name' => trim($_POST['agency_name'] ?? '') ?: $settings['agency_name'],
            'tagline' => trim($_POST['tagline'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'whatsapp' => trim($_POST['whatsapp'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'facebook' => trim($_POST['facebook'] ?? ''),
            'instagram' => trim($_POST['instagram'] ?? ''),
            'hero_title' => trim($_POST['hero_title'] ?? ''),
            'hero_sub' => trim($_POST['hero_sub'] ?? ''),
            'about_text' => trim(str_replace("\r\n", "\n", $_POST['about_text'] ?? '')),
            'stat_years' => (int) ($_POST['stat_years'] ?? 0),
            'stat_deals' => (int) ($_POST['stat_deals'] ?? 0),
            'stat_clients' => (int) ($_POST['stat_clients'] ?? 0),
        ];
        update_settings($s);
        $successMsg = 'ההגדרות נשמרו.';
        $settings = array_merge($settings, $s);
    } elseif ($action === 'change_password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');
        $currentUser = find_user((int) $_SESSION['user_id']);

        if (!$currentUser || !password_verify($current, $currentUser['password_hash'])) {
            $errors['current_password'] = 'הסיסמה הנוכחית שגויה.';
        } elseif (mb_strlen($new) < 8) {
            $errors['new_password'] = 'הסיסמה החדשה חייבת להכיל לפחות 8 תווים.';
        } elseif ($new !== $confirm) {
            $errors['new_password_confirm'] = 'אימות הסיסמה אינו תואם.';
        } else {
            update_user_password((int) $_SESSION['user_id'], password_hash($new, PASSWORD_DEFAULT));
            $successMsg = 'הסיסמה עודכנה.';
        }
    } elseif ($action === 'reset_demo') {
        $seedPath = APP_ROOT . '/data/seed.json';
        if (is_file($seedPath)) {
            import_seed_into_db($seedPath);
            $successMsg = 'נתוני הדמו נטענו מחדש.';
        } else {
            $errors['_general'] = 'לא נמצא קובץ נתוני דמו לשחזור.';
        }
    } elseif ($action === 'wipe_demo') {
        import_seed_into_db(APP_ROOT . '/data/seed.json', wipeOnly: true);
        $successMsg = 'נתוני הדמו נמחקו.';
    }
}

$adminTitle = 'הגדרות';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-main-head">
  <h1>הגדרות</h1>
</div>

<?php if ($successMsg): ?><p class="alert-box alert-success"><?= e($successMsg) ?></p><?php endif; ?>
<?php if (!empty($errors['_general'])): ?><p class="alert-box alert-error"><?= e($errors['_general']) ?></p><?php endif; ?>

<div class="admin-card">
  <h2>פרטי הסוכנות</h2>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_settings">
    <div class="admin-form-grid cols-2">
      <div class="field">
        <label for="agency_name">שם הסוכנות</label>
        <input class="input" type="text" id="agency_name" name="agency_name" value="<?= e($settings['agency_name']) ?>">
      </div>
      <div class="field">
        <label for="tagline">משפט תדמית</label>
        <input class="input" type="text" id="tagline" name="tagline" value="<?= e($settings['tagline']) ?>">
      </div>
      <div class="field">
        <label for="phone">טלפון</label>
        <input class="input" type="text" id="phone" name="phone" value="<?= e($settings['phone']) ?>">
      </div>
      <div class="field">
        <label for="whatsapp">וואטסאפ (בפורמט בינלאומי)</label>
        <input class="input" type="text" id="whatsapp" name="whatsapp" value="<?= e($settings['whatsapp']) ?>">
      </div>
      <div class="field">
        <label for="email">אימייל</label>
        <input class="input" type="email" id="email" name="email" value="<?= e($settings['email']) ?>">
      </div>
      <div class="field">
        <label for="address">כתובת</label>
        <input class="input" type="text" id="address" name="address" value="<?= e($settings['address']) ?>">
      </div>
      <div class="field">
        <label for="facebook">קישור לפייסבוק</label>
        <input class="input" type="text" id="facebook" name="facebook" value="<?= e($settings['facebook']) ?>">
      </div>
      <div class="field">
        <label for="instagram">קישור לאינסטגרם</label>
        <input class="input" type="text" id="instagram" name="instagram" value="<?= e($settings['instagram']) ?>">
      </div>
    </div>

    <fieldset class="admin-fieldset" style="margin-top: var(--space-3);">
      <legend>עמוד הבית</legend>
      <div class="field">
        <label for="hero_title">כותרת ראשית</label>
        <input class="input" type="text" id="hero_title" name="hero_title" value="<?= e($settings['hero_title']) ?>">
      </div>
      <div class="field">
        <label for="hero_sub">תת-כותרת</label>
        <textarea class="input" id="hero_sub" name="hero_sub"><?= e($settings['hero_sub']) ?></textarea>
      </div>
      <div class="field">
        <label for="about_text">טקסט "מי אנחנו"</label>
        <textarea class="input" id="about_text" name="about_text" style="min-height:120px;"><?= e($settings['about_text']) ?></textarea>
      </div>
      <div class="admin-form-grid cols-2" style="grid-template-columns: repeat(3, 1fr);">
        <div class="field">
          <label for="stat_years">שנות ניסיון</label>
          <input class="input" type="number" id="stat_years" name="stat_years" value="<?= e((string) $settings['stat_years']) ?>">
        </div>
        <div class="field">
          <label for="stat_deals">עסקאות שנסגרו</label>
          <input class="input" type="number" id="stat_deals" name="stat_deals" value="<?= e((string) $settings['stat_deals']) ?>">
        </div>
        <div class="field">
          <label for="stat_clients">לקוחות ממליצים</label>
          <input class="input" type="number" id="stat_clients" name="stat_clients" value="<?= e((string) $settings['stat_clients']) ?>">
        </div>
      </div>
    </fieldset>

    <button type="submit" class="btn btn-primary">שמירת הגדרות</button>
  </form>
</div>

<div class="admin-card">
  <h2>החלפת סיסמה</h2>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="change_password">
    <div class="admin-form-grid cols-2">
      <div class="field <?= isset($errors['current_password']) ? 'has-error' : '' ?>">
        <label for="current_password">סיסמה נוכחית</label>
        <input class="input" type="password" id="current_password" name="current_password" required>
        <?php if (isset($errors['current_password'])): ?><span class="error"><?= e($errors['current_password']) ?></span><?php endif; ?>
      </div>
      <div></div>
      <div class="field <?= isset($errors['new_password']) ? 'has-error' : '' ?>">
        <label for="new_password">סיסמה חדשה</label>
        <input class="input" type="password" id="new_password" name="new_password" required minlength="8">
        <?php if (isset($errors['new_password'])): ?><span class="error"><?= e($errors['new_password']) ?></span><?php endif; ?>
      </div>
      <div class="field <?= isset($errors['new_password_confirm']) ? 'has-error' : '' ?>">
        <label for="new_password_confirm">אימות סיסמה חדשה</label>
        <input class="input" type="password" id="new_password_confirm" name="new_password_confirm" required minlength="8">
        <?php if (isset($errors['new_password_confirm'])): ?><span class="error"><?= e($errors['new_password_confirm']) ?></span><?php endif; ?>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">עדכון סיסמה</button>
  </form>
</div>

<div class="admin-card">
  <h2>נתוני דמו</h2>
  <p class="lede" style="margin-bottom: var(--space-2);">לצורכי הדגמה בלבד — פעולות אלה מחליפות את כל הסוכנים, הנכסים, השותפים וההמלצות באתר.</p>
  <div class="admin-row-actions">
    <form method="post" onsubmit="return confirm('לטעון מחדש את נתוני הדמו המקוריים? כל שינוי בנכסים/סוכנים/המלצות יימחק.');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reset_demo">
      <button type="submit" class="btn btn-outline">טעינת נתוני דמו מחדש</button>
    </form>
    <form method="post" onsubmit="return confirm('למחוק את כל נתוני הדמו (סוכנים, נכסים, המלצות, פניות)?');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="wipe_demo">
      <button type="submit" class="btn btn-danger">מחיקת נתוני דמו</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
