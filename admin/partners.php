<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/upload.php';

$flashMsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'toggle_active') {
        toggle_partner_active($id);
    } elseif ($action === 'toggle_featured') {
        toggle_partner_featured($id);
    } elseif ($action === 'delete') {
        $target = find_partner($id);
        if ($target) {
            delete_uploaded_image($target['logo'] ?? null);
            foreach ($target['gallery'] ?? [] as $img) {
                delete_uploaded_image($img);
            }
            delete_partner($id);
            $flashMsg = ['type' => 'success', 'text' => 'השותף נמחק.'];
        }
    }
}

$filterCategory = $_GET['category'] ?? '';
$partners = all_partners(false);
if ($filterCategory !== '') {
    $partners = array_values(array_filter($partners, fn($p) => ($p['category'] ?? '') === $filterCategory));
}
usort($partners, fn($a, $b) => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));

$categories = partner_categories();

$adminTitle = 'שותפים';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-main-head">
  <h1>שותפים ואנשי מקצוע</h1>
  <a href="<?= e(url('admin/partner-edit.php')) ?>" class="btn btn-primary btn-sm">+ שותף חדש</a>
</div>

<?php if ($flashMsg): ?>
  <p class="alert-box <?= $flashMsg['type'] === 'error' ? 'alert-error' : 'alert-success' ?>"><?= e($flashMsg['text']) ?></p>
<?php endif; ?>

<form method="get" class="admin-card admin-form-grid cols-2" style="align-items:end;">
  <div class="field" style="margin-bottom:0;">
    <label for="f-category">קטגוריה</label>
    <select class="input" id="f-category" name="category">
      <option value="">כל הקטגוריות</option>
      <?php foreach ($categories as $key => $cat): ?>
        <option value="<?= e($key) ?>" <?= $filterCategory === $key ? 'selected' : '' ?>><?= $cat['icon'] ?> <?= e($cat['label']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn btn-outline">סינון</button>
</form>

<div class="admin-card">
  <?php if ($partners): ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th></th><th>שם</th><th>קטגוריה</th><th>אזורים</th><th>סדר</th><th>מומלץ</th><th>סטטוס</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($partners as $p): ?>
            <tr>
              <td><img class="admin-thumb" style="border-radius:50%;" src="<?= e(media_url($p['logo'] ?? null)) ?>" alt=""></td>
              <td><?= e($p['name']) ?></td>
              <td><?= $categories[$p['category']]['icon'] ?? '' ?> <?= e(partner_category_label($p['category'])) ?></td>
              <td><?= e(implode(', ', $p['regions'] ?? [])) ?></td>
              <td><?= e((string) ($p['sort'] ?? 0)) ?></td>
              <td>
                <form method="post" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle_featured">
                  <input type="hidden" name="id" value="<?= e((string) $p['id']) ?>">
                  <button type="submit" class="badge <?= !empty($p['featured']) ? 'badge-success' : 'badge-muted' ?>" style="border:0; cursor:pointer;">
                    <?= !empty($p['featured']) ? 'כן' : 'לא' ?>
                  </button>
                </form>
              </td>
              <td>
                <form method="post" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle_active">
                  <input type="hidden" name="id" value="<?= e((string) $p['id']) ?>">
                  <button type="submit" class="badge <?= !empty($p['active']) ? 'badge-success' : 'badge-muted' ?>" style="border:0; cursor:pointer;">
                    <?= !empty($p['active']) ? 'פעיל' : 'מוסתר' ?>
                  </button>
                </form>
              </td>
              <td>
                <div class="admin-row-actions">
                  <a href="<?= e(url('admin/partner-edit.php?id=' . $p['id'])) ?>" class="btn btn-outline btn-sm">עריכה</a>
                  <form method="post" onsubmit="return confirm('למחוק את השותף <?= e($p['name']) ?>?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= e((string) $p['id']) ?>">
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
    <p class="empty-note">עדיין אין שותפים. <a href="<?= e(url('admin/partner-edit.php')) ?>">הוסיפו שותף ראשון</a>.</p>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
