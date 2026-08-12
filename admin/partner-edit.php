<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/upload.php';

$id = (int) ($_GET['id'] ?? 0);
$existing = $id ? find_partner($id) : null;
if ($id && !$existing) {
    header('Location: ' . url('admin/partners.php'));
    exit;
}

$errors = [];

/** מחיקת תמונה בודדת מהגלריה — פועל רק על שותף קיים */
if ($existing && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check() && isset($_POST['gallery_action'])) {
    $data = load_data();
    foreach ($data['partners'] as &$p) {
        if ((int) $p['id'] !== $id) {
            continue;
        }
        $gallery = $p['gallery'] ?? [];
        $idx = (int) ($_POST['gallery_index'] ?? -1);
        if ($_POST['gallery_action'] === 'delete' && isset($gallery[$idx])) {
            delete_uploaded_image($gallery[$idx]);
            unset($gallery[$idx]);
            $gallery = array_values($gallery);
        }
        $p['gallery'] = $gallery;
    }
    unset($p);
    save_data($data);
    header('Location: ' . url('admin/partner-edit.php?id=' . $id));
    exit;
}

$values = $existing ?? [
    'name' => '', 'category' => array_key_first(partner_categories()), 'business_type' => '',
    'regions' => [], 'description_short' => '', 'description_full' => '', 'services' => [],
    'phone' => '', 'whatsapp' => '', 'email' => '', 'website' => '',
    'logo' => '', 'gallery' => [],
    'years_experience' => '', 'rating' => '', 'verified' => false, 'featured' => false,
    'active' => true, 'sort' => 10,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['gallery_action'])) {
    if (!csrf_check()) {
        $errors['_general'] = 'הפעולה פגה, נסו שוב.';
    } else {
        $values['name'] = trim($_POST['name'] ?? '');
        $values['category'] = isset(partner_categories()[$_POST['category'] ?? '']) ? $_POST['category'] : array_key_first(partner_categories());
        $values['business_type'] = trim($_POST['business_type'] ?? '');
        $values['regions'] = array_values(array_filter(array_map('trim', explode(',', $_POST['regions'] ?? ''))));
        $values['description_short'] = trim($_POST['description_short'] ?? '');
        $values['description_full'] = trim($_POST['description_full'] ?? '');
        $values['services'] = array_values(array_filter(array_map('trim', explode(',', $_POST['services'] ?? ''))));
        $values['phone'] = trim($_POST['phone'] ?? '');
        $values['whatsapp'] = trim($_POST['whatsapp'] ?? '');
        $values['email'] = trim($_POST['email'] ?? '');
        $values['website'] = trim($_POST['website'] ?? '');
        $values['years_experience'] = $_POST['years_experience'] !== '' ? (int) $_POST['years_experience'] : null;
        $values['rating'] = $_POST['rating'] !== '' ? (float) $_POST['rating'] : null;
        $values['verified'] = isset($_POST['verified']);
        $values['featured'] = isset($_POST['featured']);
        $values['active'] = isset($_POST['active']);
        $values['sort'] = (int) ($_POST['sort'] ?? 10);

        if ($values['name'] === '') {
            $errors['name'] = 'יש להזין שם.';
        }
        if ($values['phone'] !== '' && !valid_il_phone($values['phone'])) {
            $errors['phone'] = 'מספר הטלפון אינו תקין.';
        }
        if ($values['rating'] !== null && ($values['rating'] < 1 || $values['rating'] > 5)) {
            $errors['rating'] = 'דירוג חייב להיות בין 1 ל-5, או ריק.';
        }

        if (!empty($_POST['remove_logo'])) {
            delete_uploaded_image($values['logo']);
            $values['logo'] = '';
        }
        if (!empty($_FILES['logo']['name'])) {
            $upload = handle_image_upload($_FILES['logo']);
            if ($upload['ok']) {
                delete_uploaded_image($values['logo']);
                $values['logo'] = $upload['filename'];
            } else {
                $errors['logo'] = $upload['error'];
            }
        }

        $newGallery = $values['gallery'] ?? [];
        if (!empty($_FILES['gallery']['name'][0])) {
            $count = count($_FILES['gallery']['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['gallery']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $file = [
                    'name' => $_FILES['gallery']['name'][$i],
                    'type' => $_FILES['gallery']['type'][$i],
                    'tmp_name' => $_FILES['gallery']['tmp_name'][$i],
                    'error' => $_FILES['gallery']['error'][$i],
                    'size' => $_FILES['gallery']['size'][$i],
                ];
                $upload = handle_image_upload($file);
                if ($upload['ok']) {
                    $newGallery[] = $upload['filename'];
                } else {
                    $errors['gallery'] = ($errors['gallery'] ?? '') . $file['name'] . ': ' . $upload['error'] . ' ';
                }
            }
        }
        $values['gallery'] = $newGallery;

        if (!$errors) {
            $data = load_data();
            if ($existing) {
                foreach ($data['partners'] as &$p) {
                    if ((int) $p['id'] === $id) {
                        $p = array_merge($p, $values, ['id' => $id]);
                    }
                }
                unset($p);
                save_data($data);
                header('Location: ' . url('admin/partner-edit.php?id=' . $id));
            } else {
                $newId = next_id('partner');
                $data = load_data();
                $values['id'] = $newId;
                $values['created_at'] = date('Y-m-d H:i:s');
                $data['partners'][] = $values;
                save_data($data);
                header('Location: ' . url('admin/partner-edit.php?id=' . $newId));
            }
            exit;
        }
    }
}

$categories = partner_categories();

$adminTitle = $existing ? 'עריכת שותף' : 'שותף חדש';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-main-head">
  <h1><?= $existing ? 'עריכת שותף' : 'שותף חדש' ?></h1>
  <a href="<?= e(url('admin/partners.php')) ?>" class="btn btn-outline btn-sm">חזרה לרשימה</a>
</div>

<?php if (!empty($errors['_general'])): ?><p class="alert-box alert-error"><?= e($errors['_general']) ?></p><?php endif; ?>

<div class="admin-card">
  <form method="post" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>

    <fieldset class="admin-fieldset">
      <legend>פרטים בסיסיים</legend>
      <div class="admin-form-grid cols-2">
        <div class="field <?= isset($errors['name']) ? 'has-error' : '' ?>">
          <label for="name">שם *</label>
          <input class="input" type="text" id="name" name="name" value="<?= e($values['name']) ?>" required>
          <?php if (isset($errors['name'])): ?><span class="error"><?= e($errors['name']) ?></span><?php endif; ?>
        </div>
        <div class="field">
          <label for="category">קטגוריה</label>
          <select class="input" id="category" name="category">
            <?php foreach ($categories as $key => $cat): ?>
              <option value="<?= e($key) ?>" <?= $values['category'] === $key ? 'selected' : '' ?>><?= $cat['icon'] ?> <?= e($cat['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" style="grid-column: 1 / -1;">
          <label for="business_type">סוג העסק / המקצוע</label>
          <input class="input" type="text" id="business_type" name="business_type" value="<?= e($values['business_type']) ?>" placeholder="לדוגמה: משרד עורכי דין">
        </div>
        <div class="field" style="grid-column: 1 / -1;">
          <label for="regions">אזורי פעילות (מופרדים בפסיק)</label>
          <input class="input" type="text" id="regions" name="regions" value="<?= e(implode(', ', $values['regions'])) ?>" placeholder="תל אביב, נתניה, הרצליה">
        </div>
      </div>
    </fieldset>

    <fieldset class="admin-fieldset">
      <legend>תיאור ושירותים</legend>
      <div class="field">
        <label for="description_short">תיאור קצר (לכרטיס)</label>
        <input class="input" type="text" id="description_short" name="description_short" value="<?= e($values['description_short']) ?>">
      </div>
      <div class="field">
        <label for="description_full">תיאור מלא (לפרופיל)</label>
        <textarea class="input" id="description_full" name="description_full" style="min-height:120px;"><?= e($values['description_full']) ?></textarea>
      </div>
      <div class="field">
        <label for="services">שירותים עיקריים (מופרדים בפסיק)</label>
        <input class="input" type="text" id="services" name="services" value="<?= e(implode(', ', $values['services'])) ?>" placeholder="חוזי מכר, בדיקות טאבו">
      </div>
    </fieldset>

    <fieldset class="admin-fieldset">
      <legend>יצירת קשר</legend>
      <div class="admin-form-grid cols-2">
        <div class="field <?= isset($errors['phone']) ? 'has-error' : '' ?>">
          <label for="phone">טלפון</label>
          <input class="input" type="tel" id="phone" name="phone" value="<?= e($values['phone']) ?>">
          <?php if (isset($errors['phone'])): ?><span class="error"><?= e($errors['phone']) ?></span><?php endif; ?>
        </div>
        <div class="field">
          <label for="whatsapp">וואטסאפ (בפורמט בינלאומי)</label>
          <input class="input" type="text" id="whatsapp" name="whatsapp" value="<?= e($values['whatsapp']) ?>">
        </div>
        <div class="field">
          <label for="email">אימייל</label>
          <input class="input" type="email" id="email" name="email" value="<?= e($values['email']) ?>">
        </div>
        <div class="field">
          <label for="website">אתר אינטרנט</label>
          <input class="input" type="url" id="website" name="website" value="<?= e($values['website']) ?>" placeholder="https://">
        </div>
      </div>
    </fieldset>

    <fieldset class="admin-fieldset">
      <legend>לוגו / תמונה</legend>
      <?php if (!empty($values['logo'])): ?>
        <img class="admin-thumb" style="width:80px; height:80px; border-radius:50%; margin-bottom:8px;" src="<?= e(media_url($values['logo'])) ?>" alt="">
        <label class="checkbox-row" style="margin-bottom: var(--space-2);">
          <input type="checkbox" name="remove_logo" value="1">
          <span>הסרת התמונה הנוכחית</span>
        </label>
      <?php endif; ?>
      <div class="field <?= isset($errors['logo']) ? 'has-error' : '' ?>">
        <label for="logo">העלאת לוגו (jpg/png/webp, עד 8MB)</label>
        <input class="input" type="file" id="logo" name="logo" accept=".jpg,.jpeg,.png,.webp">
        <?php if (isset($errors['logo'])): ?><span class="error"><?= e($errors['logo']) ?></span><?php endif; ?>
      </div>
    </fieldset>

    <fieldset class="admin-fieldset">
      <legend>גלריה (אופציונלי)</legend>
      <?php if (!empty($values['gallery'])): ?>
        <div class="admin-image-grid">
          <?php foreach ($values['gallery'] as $i => $img): ?>
            <div class="admin-image-tile">
              <img src="<?= e(media_url($img)) ?>" alt="">
              <?php if ($existing): ?>
                <div class="admin-image-actions">
                  <form method="post" onsubmit="return confirm('למחוק את התמונה?');"><?= csrf_field() ?><input type="hidden" name="gallery_action" value="delete"><input type="hidden" name="gallery_index" value="<?= $i ?>"><button type="submit">מחיקה</button></form>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="field <?= isset($errors['gallery']) ? 'has-error' : '' ?>">
        <label for="gallery">הוספת תמונות לגלריה (ניתן לבחור כמה)</label>
        <input class="input" type="file" id="gallery" name="gallery[]" accept=".jpg,.jpeg,.png,.webp" multiple>
        <?php if (isset($errors['gallery'])): ?><span class="error"><?= e($errors['gallery']) ?></span><?php endif; ?>
      </div>
    </fieldset>

    <fieldset class="admin-fieldset">
      <legend>אמינות ותצוגה</legend>
      <p style="color:var(--ink-3); font-size:0.85rem; margin-top:-8px; margin-bottom: var(--space-2);">
        שנות ניסיון ודירוג מוצגים באתר רק אם מולאו כאן — לא ממציאים ערכים.
      </p>
      <div class="admin-form-grid cols-2">
        <div class="field">
          <label for="years_experience">שנות ניסיון (ריק = לא מוצג)</label>
          <input class="input" type="number" id="years_experience" name="years_experience" min="0" value="<?= e((string) $values['years_experience']) ?>">
        </div>
        <div class="field <?= isset($errors['rating']) ? 'has-error' : '' ?>">
          <label for="rating">דירוג 1–5 (ריק = לא מוצג)</label>
          <input class="input" type="number" id="rating" name="rating" min="1" max="5" step="0.1" value="<?= e((string) $values['rating']) ?>">
          <?php if (isset($errors['rating'])): ?><span class="error"><?= e($errors['rating']) ?></span><?php endif; ?>
        </div>
      </div>
      <div class="admin-form-grid cols-2" style="margin-top: var(--space-2);">
        <label class="checkbox-row">
          <input type="checkbox" name="verified" <?= $values['verified'] ? 'checked' : '' ?>>
          <span>✓ פרופיל מאומת</span>
        </label>
        <label class="checkbox-row">
          <input type="checkbox" name="featured" <?= $values['featured'] ? 'checked' : '' ?>>
          <span>⭐ שותף מומלץ</span>
        </label>
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

    <button type="submit" class="btn btn-primary"><?= $existing ? 'שמירת שינויים' : 'הוספת שותף' ?></button>
  </form>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
