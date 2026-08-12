<?php
require __DIR__ . '/includes/auth.php';

$errors = [];
$editId = (int) ($_GET['edit'] ?? 0);
$editing = null;
if ($editId) {
    foreach (all_testimonials() as $t) {
        if ((int) $t['id'] === $editId) {
            $editing = $t;
            break;
        }
    }
}
$values = $editing ?? ['name' => '', 'city' => '', 'text' => '', 'rating' => 5];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        delete_testimonial($id);
        header('Location: ' . url('admin/testimonials.php'));
        exit;
    }

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $values['name'] = trim($_POST['name'] ?? '');
        $values['city'] = trim($_POST['city'] ?? '');
        $values['text'] = trim($_POST['text'] ?? '');
        $values['rating'] = max(1, min(5, (int) ($_POST['rating'] ?? 5)));

        if ($values['name'] === '') {
            $errors['name'] = 'יש להזין שם.';
        }
        if ($values['text'] === '') {
            $errors['text'] = 'יש להזין תוכן המלצה.';
        }

        if (!$errors) {
            if ($id) {
                update_testimonial($id, $values);
            } else {
                insert_testimonial($values);
            }
            header('Location: ' . url('admin/testimonials.php'));
            exit;
        }
        $editId = $id;
    }
}

$testimonials = all_testimonials();

$adminTitle = 'המלצות';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-main-head">
  <h1>המלצות</h1>
</div>

<div class="admin-card">
  <h2><?= $editId ? 'עריכת המלצה' : 'המלצה חדשה' ?></h2>
  <?php if (!empty($errors['_general'])): ?><p class="alert-box alert-error"><?= e($errors['_general']) ?></p><?php endif; ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= e((string) $editId) ?>">
    <div class="admin-form-grid cols-2">
      <div class="field <?= isset($errors['name']) ? 'has-error' : '' ?>">
        <label for="name">שם *</label>
        <input class="input" type="text" id="name" name="name" value="<?= e($values['name']) ?>" required>
        <?php if (isset($errors['name'])): ?><span class="error"><?= e($errors['name']) ?></span><?php endif; ?>
      </div>
      <div class="field">
        <label for="city">עיר</label>
        <input class="input" type="text" id="city" name="city" value="<?= e($values['city']) ?>">
      </div>
      <div class="field" style="grid-column: 1 / -1;">
        <label for="text">תוכן ההמלצה *</label>
        <textarea class="input" id="text" name="text" required><?= e($values['text']) ?></textarea>
        <?php if (isset($errors['text'])): ?><span class="error"><?= e($errors['text']) ?></span><?php endif; ?>
      </div>
      <div class="field">
        <label for="rating">דירוג</label>
        <select class="input" id="rating" name="rating">
          <?php for ($r = 5; $r >= 1; $r--): ?>
            <option value="<?= $r ?>" <?= (int) $values['rating'] === $r ? 'selected' : '' ?>><?= str_repeat('★', $r) ?></option>
          <?php endfor; ?>
        </select>
      </div>
    </div>
    <button type="submit" class="btn btn-primary"><?= $editId ? 'שמירת שינויים' : 'הוספת המלצה' ?></button>
    <?php if ($editId): ?><a href="<?= e(url('admin/testimonials.php')) ?>" class="btn btn-outline">ביטול</a><?php endif; ?>
  </form>
</div>

<div class="admin-card">
  <h2>כל ההמלצות</h2>
  <?php if ($testimonials): ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr><th>שם</th><th>עיר</th><th>דירוג</th><th>תוכן</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($testimonials as $t): ?>
            <tr>
              <td><?= e($t['name']) ?></td>
              <td><?= e($t['city']) ?></td>
              <td><?= str_repeat('★', (int) $t['rating']) ?></td>
              <td style="white-space:normal; max-width:360px;"><?= e(mb_strimwidth($t['text'], 0, 80, '…')) ?></td>
              <td>
                <div class="admin-row-actions">
                  <a href="<?= e(url('admin/testimonials.php?edit=' . $t['id'])) ?>" class="btn btn-outline btn-sm">עריכה</a>
                  <form method="post" onsubmit="return confirm('למחוק את ההמלצה?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= e((string) $t['id']) ?>">
                    <button type="submit" class="btn btn-danger btn-sm">מחיקה</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="empty-note">עדיין אין המלצות.</p>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
