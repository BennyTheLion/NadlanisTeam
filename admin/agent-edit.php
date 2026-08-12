<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/upload.php';

$id = (int) ($_GET['id'] ?? 0);
$existing = $id ? find_agent($id) : null;
if ($id && !$existing) {
    header('Location: ' . url('admin/agents.php'));
    exit;
}

$errors = [];
$values = $existing ?? [
    'name' => '', 'role' => 'סוכן', 'phone' => '', 'whatsapp' => '', 'email' => '',
    'photo' => '', 'bio' => '', 'areas' => [], 'languages' => [], 'active' => true, 'sort' => 10,
];
$existingUser = $existing ? agent_user_row($id) : null;
$prefillUsername = $existingUser['username'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors['_general'] = 'הפעולה פגה, נסו שוב.';
    } else {
        $values['name'] = trim($_POST['name'] ?? '');
        $values['role'] = trim($_POST['role'] ?? '');
        $values['phone'] = trim($_POST['phone'] ?? '');
        $values['whatsapp'] = trim($_POST['whatsapp'] ?? '');
        $values['email'] = trim($_POST['email'] ?? '');
        $values['bio'] = trim($_POST['bio'] ?? '');
        $values['areas'] = array_values(array_filter(array_map('trim', explode(',', $_POST['areas'] ?? ''))));
        $values['languages'] = array_values(array_filter(array_map('trim', explode(',', $_POST['languages'] ?? ''))));
        $values['active'] = isset($_POST['active']);
        $values['sort'] = (int) ($_POST['sort'] ?? 10);

        if ($values['name'] === '') {
            $errors['name'] = 'יש להזין שם.';
        }
        if ($values['phone'] === '' || !valid_il_phone($values['phone'])) {
            $errors['phone'] = 'יש להזין מספר טלפון ישראלי תקין.';
        }

        $newUsername = trim($_POST['username'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $newPasswordConfirm = (string) ($_POST['new_password_confirm'] ?? '');
        $existingHash = $existingUser['password_hash'] ?? '';

        if ($newUsername !== '' && username_taken($newUsername, $existingUser['id'] ?? 0)) {
            $errors['username'] = 'שם המשתמש הזה כבר בשימוש על ידי משתמש אחר.';
        }
        if ($newPassword !== '' || $newPasswordConfirm !== '') {
            if (mb_strlen($newPassword) < 8) {
                $errors['new_password'] = 'הסיסמה חייבת להכיל לפחות 8 תווים.';
            } elseif ($newPassword !== $newPasswordConfirm) {
                $errors['new_password_confirm'] = 'אימות הסיסמה אינו תואם.';
            }
        }
        if ($newUsername !== '' && $existingHash === '' && $newPassword === '' && !isset($errors['new_password'])) {
            $errors['new_password'] = 'יש להגדיר סיסמה לסוכן עם שם משתמש.';
        }

        if (!empty($_POST['remove_photo'])) {
            delete_uploaded_image($values['photo']);
            $values['photo'] = '';
        }

        if (!empty($_FILES['photo']['name'])) {
            $upload = handle_image_upload($_FILES['photo']);
            if ($upload['ok']) {
                delete_uploaded_image($values['photo']);
                $values['photo'] = $upload['filename'];
            } else {
                $errors['photo'] = $upload['error'];
            }
        }

        if (!$errors) {
            $targetId = $id;
            if ($existing) {
                update_agent($id, $values);
            } else {
                $targetId = insert_agent($values);
            }

            if ($newUsername === '') {
                clear_agent_credentials($targetId);
            } else {
                $newHash = $newPassword !== '' ? password_hash($newPassword, PASSWORD_DEFAULT) : null;
                set_agent_credentials($targetId, $newUsername, $newHash);
            }

            header('Location: ' . url('admin/agents.php'));
            exit;
        }
    }
}

$adminTitle = $existing ? 'עריכת סוכן' : 'סוכן חדש';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-main-head">
  <h1><?= $existing ? 'עריכת סוכן' : 'סוכן חדש' ?></h1>
  <a href="<?= e(url('admin/agents.php')) ?>" class="btn btn-outline btn-sm">חזרה לרשימה</a>
</div>

<?php if (!empty($errors['_general'])): ?><p class="alert-box alert-error"><?= e($errors['_general']) ?></p><?php endif; ?>

<div class="admin-card">
  <form method="post" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>

    <fieldset class="admin-fieldset">
      <legend>פרטים בסיסיים</legend>
      <div class="admin-form-grid cols-2">
        <div class="field <?= isset($errors['name']) ? 'has-error' : '' ?>">
          <label for="name">שם מלא *</label>
          <input class="input" type="text" id="name" name="name" value="<?= e($values['name']) ?>" required>
          <?php if (isset($errors['name'])): ?><span class="error"><?= e($errors['name']) ?></span><?php endif; ?>
        </div>
        <div class="field">
          <label for="role">תפקיד</label>
          <input class="input" type="text" id="role" name="role" value="<?= e($values['role']) ?>">
        </div>
      </div>
    </fieldset>

    <fieldset class="admin-fieldset">
      <legend>יצירת קשר</legend>
      <div class="admin-form-grid cols-2">
        <div class="field <?= isset($errors['phone']) ? 'has-error' : '' ?>">
          <label for="phone">טלפון *</label>
          <input class="input" type="tel" id="phone" name="phone" value="<?= e($values['phone']) ?>" required>
          <?php if (isset($errors['phone'])): ?><span class="error"><?= e($errors['phone']) ?></span><?php endif; ?>
        </div>
        <div class="field">
          <label for="whatsapp">וואטסאפ (בפורמט בינלאומי, לדוגמה 972521001001)</label>
          <input class="input" type="text" id="whatsapp" name="whatsapp" value="<?= e($values['whatsapp']) ?>">
        </div>
        <div class="field">
          <label for="email">אימייל</label>
          <input class="input" type="email" id="email" name="email" value="<?= e($values['email']) ?>">
        </div>
      </div>
    </fieldset>

    <fieldset class="admin-fieldset">
      <legend>פרופיל</legend>
      <div class="field">
        <label for="bio">תיאור קצר</label>
        <textarea class="input" id="bio" name="bio"><?= e($values['bio']) ?></textarea>
      </div>
      <div class="admin-form-grid cols-2">
        <div class="field">
          <label for="areas">אזורי פעילות (מופרדים בפסיק)</label>
          <input class="input" type="text" id="areas" name="areas" value="<?= e(implode(', ', $values['areas'])) ?>" placeholder="עיר ימים, קריית נורדאו">
        </div>
        <div class="field">
          <label for="languages">שפות (מופרדות בפסיק)</label>
          <input class="input" type="text" id="languages" name="languages" value="<?= e(implode(', ', $values['languages'])) ?>" placeholder="עברית, אנגלית">
        </div>
      </div>
    </fieldset>

    <fieldset class="admin-fieldset">
      <legend>תמונה</legend>
      <?php if (!empty($values['photo'])): ?>
        <img class="admin-thumb" style="width:80px; height:80px; border-radius:50%; margin-bottom:8px;" src="<?= e(media_url($values['photo'])) ?>" alt="">
        <label class="checkbox-row" style="margin-bottom: var(--space-2);">
          <input type="checkbox" name="remove_photo" value="1">
          <span>הסרת התמונה הנוכחית</span>
        </label>
      <?php endif; ?>
      <div class="field <?= isset($errors['photo']) ? 'has-error' : '' ?>">
        <label for="photo">העלאת תמונה (jpg/png/webp, עד 8MB)</label>
        <input class="input" type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png,.webp">
        <?php if (isset($errors['photo'])): ?><span class="error"><?= e($errors['photo']) ?></span><?php endif; ?>
      </div>
    </fieldset>

    <fieldset class="admin-fieldset">
      <legend>הגדרות תצוגה</legend>
      <div class="admin-form-grid cols-2">
        <label class="checkbox-row">
          <input type="checkbox" name="active" <?= $values['active'] ? 'checked' : '' ?>>
          <span>פעיל (מוצג באתר)</span>
        </label>
        <div class="field" style="margin-bottom:0;">
          <label for="sort">סדר הצגה</label>
          <input class="input" type="number" id="sort" name="sort" value="<?= e((string) $values['sort']) ?>">
        </div>
      </div>
    </fieldset>

    <fieldset class="admin-fieldset">
      <legend>פרטי כניסה לדשבורד</legend>
      <p class="lede" style="margin-bottom: var(--space-2);">מאפשר לסוכן להתחבר לדשבורד האישי שלו ולנהל את הנכסים והפניות שלו. השאירו את שם המשתמש ריק כדי לשלול גישה.</p>
      <div class="admin-form-grid cols-2">
        <div class="field <?= isset($errors['username']) ? 'has-error' : '' ?>">
          <label for="username">שם משתמש</label>
          <input class="input" type="text" id="username" name="username" value="<?= e($_POST['username'] ?? $prefillUsername) ?>" autocomplete="off">
          <?php if (isset($errors['username'])): ?><span class="error"><?= e($errors['username']) ?></span><?php endif; ?>
        </div>
        <?php if (!empty($existingUser['last_login_at'])): ?>
          <div class="field" style="margin-bottom:0;">
            <label>כניסה אחרונה</label>
            <p style="margin:0; padding-top:10px; color: var(--ink-3);"><?= e($existingUser['last_login_at']) ?></p>
          </div>
        <?php endif; ?>
      </div>
      <div class="admin-form-grid cols-2">
        <div class="field <?= isset($errors['new_password']) ? 'has-error' : '' ?>">
          <label for="new_password">סיסמה חדשה (השאירו ריק כדי לשמור את הסיסמה הקיימת)</label>
          <input class="input" type="password" id="new_password" name="new_password" autocomplete="new-password">
          <?php if (isset($errors['new_password'])): ?><span class="error"><?= e($errors['new_password']) ?></span><?php endif; ?>
        </div>
        <div class="field <?= isset($errors['new_password_confirm']) ? 'has-error' : '' ?>">
          <label for="new_password_confirm">אימות סיסמה חדשה</label>
          <input class="input" type="password" id="new_password_confirm" name="new_password_confirm" autocomplete="new-password">
          <?php if (isset($errors['new_password_confirm'])): ?><span class="error"><?= e($errors['new_password_confirm']) ?></span><?php endif; ?>
        </div>
      </div>
    </fieldset>

    <button type="submit" class="btn btn-primary"><?= $existing ? 'שמירת שינויים' : 'הוספת סוכן' ?></button>
  </form>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
