<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/upload.php';

$id = (int) ($_GET['id'] ?? 0);
$existing = $id ? find_property($id) : null;
if ($id && !$existing) {
    header('Location: ' . url('admin/properties.php'));
    exit;
}

$errors = [];

/** מיון פעולות תמונה מיידיות (מזיזות/מוחקות/קובעות ראשית) — פועלות רק על נכס קיים */
if ($existing && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check() && isset($_POST['img_action'])) {
    $images = $existing['images'] ?? [];
    $idx = (int) ($_POST['img_index'] ?? -1);
    switch ($_POST['img_action']) {
        case 'up':
            if ($idx > 0 && $idx < count($images)) {
                [$images[$idx - 1], $images[$idx]] = [$images[$idx], $images[$idx - 1]];
            }
            break;
        case 'down':
            if ($idx >= 0 && $idx < count($images) - 1) {
                [$images[$idx + 1], $images[$idx]] = [$images[$idx], $images[$idx + 1]];
            }
            break;
        case 'cover':
            if ($idx > 0 && $idx < count($images)) {
                $cover = $images[$idx];
                unset($images[$idx]);
                array_unshift($images, $cover);
                $images = array_values($images);
            }
            break;
        case 'delete':
            if (isset($images[$idx])) {
                delete_uploaded_image($images[$idx]);
                unset($images[$idx]);
                $images = array_values($images);
            }
            break;
    }
    update_property_images($id, $images);
    header('Location: ' . url('admin/property-edit.php?id=' . $id));
    exit;
}

$values = $existing ?? [
    'title' => '', 'deal' => 'sale', 'type' => property_types()[0], 'status' => 'available',
    'city' => 'נתניה', 'neighborhood' => '', 'address' => '', 'lat' => '', 'lng' => '',
    'price' => '', 'rooms' => '', 'size' => '', 'plot_size' => '', 'floor' => '', 'total_floors' => '', 'parking' => 0,
    'balcony' => false, 'elevator' => false, 'mamad' => false, 'storage' => false,
    'renovated' => false, 'accessible' => false, 'furnished' => false,
    'entry_date' => '', 'description' => '', 'images' => [], 'agent_id' => 0, 'featured' => false,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['img_action'])) {
    if (!csrf_check()) {
        $errors['_general'] = 'הפעולה פגה, נסו שוב.';
    } else {
        $values['title'] = trim($_POST['title'] ?? '');
        $values['deal'] = in_array($_POST['deal'] ?? '', ['sale', 'rent'], true) ? $_POST['deal'] : 'sale';
        $values['type'] = in_array($_POST['type'] ?? '', property_types(), true) ? $_POST['type'] : property_types()[0];
        $values['status'] = isset(status_labels()[$_POST['status'] ?? '']) ? $_POST['status'] : 'available';
        $values['city'] = trim($_POST['city'] ?? '') ?: 'נתניה';
        $values['neighborhood'] = trim($_POST['neighborhood'] ?? '');
        $values['address'] = trim($_POST['address'] ?? '');
        $values['lat'] = ($_POST['lat'] ?? '') !== '' ? (float) $_POST['lat'] : '';
        $values['lng'] = ($_POST['lng'] ?? '') !== '' ? (float) $_POST['lng'] : '';
        $values['price'] = (float) ($_POST['price'] ?? 0);
        $values['rooms'] = (float) ($_POST['rooms'] ?? 0);
        $values['size'] = (float) ($_POST['size'] ?? 0);
        $values['plot_size'] = (float) ($_POST['plot_size'] ?? 0);
        $values['floor'] = (int) ($_POST['floor'] ?? 0);
        $values['total_floors'] = (int) ($_POST['total_floors'] ?? 0);
        $values['parking'] = (int) ($_POST['parking'] ?? 0);
        foreach (['balcony', 'elevator', 'mamad', 'storage', 'renovated', 'accessible', 'furnished', 'featured'] as $flag) {
            $values[$flag] = isset($_POST[$flag]);
        }
        $values['entry_date'] = trim($_POST['entry_date'] ?? '');
        $values['description'] = trim($_POST['description'] ?? '');
        $values['agent_id'] = (int) ($_POST['agent_id'] ?? 0);

        if ($values['title'] === '') {
            $errors['title'] = 'יש להזין כותרת.';
        }
        if ($values['price'] <= 0) {
            $errors['price'] = 'יש להזין מחיר תקין.';
        }
        if ($values['agent_id'] <= 0 || !find_agent($values['agent_id'])) {
            $errors['agent_id'] = 'יש לבחור סוכן.';
        }
        if (($values['lat'] === '') !== ($values['lng'] === '')) {
            $errors['lat'] = 'יש למלא גם קו רוחב וגם קו אורך, או להשאיר את שניהם ריקים.';
        }

        $newImages = $values['images'] ?? [];
        if (!empty($_FILES['images']['name'][0])) {
            $count = count($_FILES['images']['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['images']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $file = [
                    'name' => $_FILES['images']['name'][$i],
                    'type' => $_FILES['images']['type'][$i],
                    'tmp_name' => $_FILES['images']['tmp_name'][$i],
                    'error' => $_FILES['images']['error'][$i],
                    'size' => $_FILES['images']['size'][$i],
                ];
                $upload = handle_image_upload($file);
                if ($upload['ok']) {
                    $newImages[] = $upload['filename'];
                } else {
                    $errors['images'] = ($errors['images'] ?? '') . $file['name'] . ': ' . $upload['error'] . ' ';
                }
            }
        }
        $values['images'] = $newImages;

        if (!$errors) {
            if ($existing) {
                update_property($id, $values);
                header('Location: ' . url('admin/property-edit.php?id=' . $id));
            } else {
                $newId = insert_property($values);
                header('Location: ' . url('admin/property-edit.php?id=' . $newId));
            }
            exit;
        }
    }
}

$agents = all_agents(false);

$featureFlags = [
    'balcony' => 'מרפסת', 'elevator' => 'מעלית', 'mamad' => 'ממ״ד', 'storage' => 'מחסן',
    'renovated' => 'משופצת', 'accessible' => 'נגישות', 'furnished' => 'מרוהטת',
];

$adminTitle = $existing ? 'עריכת נכס' : 'נכס חדש';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-main-head">
  <h1><?= $existing ? 'עריכת נכס' : 'נכס חדש' ?></h1>
  <a href="<?= e(url('admin/properties.php')) ?>" class="btn btn-outline btn-sm">חזרה לרשימה</a>
</div>

<?php if (!empty($errors['_general'])): ?><p class="alert-box alert-error"><?= e($errors['_general']) ?></p><?php endif; ?>
<?php if (!$existing): ?><p class="alert-box" style="background:var(--blue-tint); color:var(--blue-deep);">שמרו את הנכס תחילה כדי לנהל סדר תמונות וקביעת תמונה ראשית.</p><?php endif; ?>

<div class="admin-card">
  <form method="post" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>

    <fieldset class="admin-fieldset">
      <legend>פרטים בסיסיים</legend>
      <div class="admin-form-grid cols-2">
        <div class="field <?= isset($errors['title']) ? 'has-error' : '' ?>" style="grid-column: 1 / -1;">
          <label for="title">כותרת *</label>
          <input class="input" type="text" id="title" name="title" value="<?= e($values['title']) ?>" required>
          <?php if (isset($errors['title'])): ?><span class="error"><?= e($errors['title']) ?></span><?php endif; ?>
        </div>
        <div class="field">
          <label for="deal">סוג עסקה</label>
          <select class="input" id="deal" name="deal">
            <?php foreach (deal_types() as $key => $label): ?>
              <option value="<?= e($key) ?>" <?= $values['deal'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="type">סוג נכס</label>
          <select class="input" id="type" name="type">
            <?php foreach (property_types() as $t): ?>
              <option value="<?= e($t) ?>" <?= $values['type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="status">סטטוס</label>
          <select class="input" id="status" name="status">
            <?php foreach (status_labels() as $key => $label): ?>
              <option value="<?= e($key) ?>" <?= $values['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <label class="checkbox-row">
          <input type="checkbox" name="featured" <?= $values['featured'] ? 'checked' : '' ?>>
          <span>נכס מומלץ (מוצג בעמוד הבית)</span>
        </label>
      </div>
    </fieldset>

    <fieldset class="admin-fieldset">
      <legend>מיקום</legend>
      <div class="admin-form-grid cols-2">
        <div class="field">
          <label for="city">עיר</label>
          <input class="input" type="text" id="city" name="city" value="<?= e($values['city']) ?>">
        </div>
        <div class="field">
          <label for="neighborhood">שכונה</label>
          <input class="input" type="text" id="neighborhood" name="neighborhood" value="<?= e($values['neighborhood']) ?>">
        </div>
        <div class="field" style="grid-column: 1 / -1;">
          <label for="address">כתובת</label>
          <input class="input" type="text" id="address" name="address" value="<?= e($values['address']) ?>">
        </div>
        <div class="field <?= isset($errors['lat']) ? 'has-error' : '' ?>">
          <label for="lat">קו רוחב (Latitude)</label>
          <input class="input" type="number" step="0.000001" id="lat" name="lat" value="<?= e((string) $values['lat']) ?>" placeholder="32.3215">
          <?php if (isset($errors['lat'])): ?><span class="error"><?= e($errors['lat']) ?></span><?php endif; ?>
        </div>
        <div class="field">
          <label for="lng">קו אורך (Longitude)</label>
          <input class="input" type="number" step="0.000001" id="lng" name="lng" value="<?= e((string) $values['lng']) ?>" placeholder="34.8532">
        </div>
        <p style="grid-column: 1 / -1; color:var(--ink-3); font-size:0.85rem; margin-top:-8px;">
          משמש להצגת הנכס במפה בעמוד "נכסים". לאיתור קואורדינטות: חפשו את הכתובת ב
          <a href="https://www.google.com/maps" target="_blank" rel="noopener">Google Maps</a>,
          לחצו לחיצה ימנית על המיקום המדויק ובחרו במספרים שמופיעים למעלה כדי להעתיק אותם.
          נכס ללא קואורדינטות פשוט לא יופיע בתצוגת המפה.
        </p>
      </div>
    </fieldset>

    <fieldset class="admin-fieldset">
      <legend>מאפיינים</legend>
      <div class="admin-form-grid cols-2">
        <div class="field <?= isset($errors['price']) ? 'has-error' : '' ?>">
          <label for="price">מחיר (₪) *</label>
          <input class="input" type="number" id="price" name="price" min="0" step="1" value="<?= e((string) $values['price']) ?>" required>
          <?php if (isset($errors['price'])): ?><span class="error"><?= e($errors['price']) ?></span><?php endif; ?>
        </div>
        <div class="field">
          <label for="rooms">חדרים</label>
          <input class="input" type="number" id="rooms" name="rooms" min="0" step="0.5" value="<?= e((string) $values['rooms']) ?>">
        </div>
        <div class="field">
          <label for="size">מ״ר</label>
          <input class="input" type="number" id="size" name="size" min="0" value="<?= e((string) $values['size']) ?>">
        </div>
        <div class="field">
          <label for="plot_size">מ״ר מגרש (אם רלוונטי)</label>
          <input class="input" type="number" id="plot_size" name="plot_size" min="0" value="<?= e((string) $values['plot_size']) ?>">
        </div>
        <div class="field">
          <label for="floor">קומה</label>
          <input class="input" type="number" id="floor" name="floor" value="<?= e((string) $values['floor']) ?>">
        </div>
        <div class="field">
          <label for="total_floors">מתוך קומות</label>
          <input class="input" type="number" id="total_floors" name="total_floors" min="0" value="<?= e((string) $values['total_floors']) ?>">
        </div>
        <div class="field">
          <label for="parking">חניות</label>
          <input class="input" type="number" id="parking" name="parking" min="0" value="<?= e((string) $values['parking']) ?>">
        </div>
        <div class="field">
          <label for="entry_date">תאריך כניסה</label>
          <input class="input" type="text" id="entry_date" name="entry_date" value="<?= e($values['entry_date']) ?>" placeholder="מיידי">
        </div>
      </div>
      <div class="admin-check-grid" style="margin-top: var(--space-2);">
        <?php foreach ($featureFlags as $key => $label): ?>
          <label class="admin-check-row">
            <input type="checkbox" name="<?= e($key) ?>" <?= !empty($values[$key]) ? 'checked' : '' ?>>
            <span><?= e($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <fieldset class="admin-fieldset">
      <legend>תמונות</legend>
      <?php if (!empty($values['images'])): ?>
        <div class="admin-image-grid">
          <?php foreach ($values['images'] as $i => $img): ?>
            <div class="admin-image-tile <?= $i === 0 ? 'is-cover' : '' ?>">
              <?php if ($i === 0): ?><span class="admin-image-cover-badge">ראשית</span><?php endif; ?>
              <img src="<?= e(media_url($img)) ?>" alt="">
              <div class="admin-image-actions">
                <?php if ($existing): ?>
                  <?php if ($i > 0): ?>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="img_action" value="up"><input type="hidden" name="img_index" value="<?= $i ?>"><button type="submit" title="הזזה למעלה">↑</button></form>
                  <?php endif; ?>
                  <?php if ($i < count($values['images']) - 1): ?>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="img_action" value="down"><input type="hidden" name="img_index" value="<?= $i ?>"><button type="submit" title="הזזה למטה">↓</button></form>
                  <?php endif; ?>
                  <?php if ($i > 0): ?>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="img_action" value="cover"><input type="hidden" name="img_index" value="<?= $i ?>"><button type="submit" title="הגדרה כתמונה ראשית">ראשית</button></form>
                  <?php endif; ?>
                  <form method="post" onsubmit="return confirm('למחוק את התמונה?');"><?= csrf_field() ?><input type="hidden" name="img_action" value="delete"><input type="hidden" name="img_index" value="<?= $i ?>"><button type="submit">מחיקה</button></form>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="field <?= isset($errors['images']) ? 'has-error' : '' ?>">
        <label for="images">הוספת תמונות (ניתן לבחור כמה, jpg/png/webp, עד 8MB לתמונה)</label>
        <input class="input" type="file" id="images" name="images[]" accept=".jpg,.jpeg,.png,.webp" multiple>
        <?php if (isset($errors['images'])): ?><span class="error"><?= e($errors['images']) ?></span><?php endif; ?>
      </div>
    </fieldset>

    <fieldset class="admin-fieldset">
      <legend>תיאור</legend>
      <div class="field">
        <label for="description">תיאור הנכס</label>
        <textarea class="input" id="description" name="description" style="min-height:160px;"><?= e($values['description']) ?></textarea>
      </div>
    </fieldset>

    <fieldset class="admin-fieldset">
      <legend>שיוך סוכן</legend>
      <div class="field <?= isset($errors['agent_id']) ? 'has-error' : '' ?>">
        <label for="agent_id">סוכן אחראי *</label>
        <select class="input" id="agent_id" name="agent_id" required>
          <option value="">בחרו סוכן</option>
          <?php foreach ($agents as $a): ?>
            <option value="<?= e((string) $a['id']) ?>" <?= (int) $values['agent_id'] === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errors['agent_id'])): ?><span class="error"><?= e($errors['agent_id']) ?></span><?php endif; ?>
      </div>
    </fieldset>

    <button type="submit" class="btn btn-primary"><?= $existing ? 'שמירת שינויים' : 'הוספת נכס' ?></button>
  </form>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
