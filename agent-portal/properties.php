<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/../admin/includes/upload.php';

$flashMsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $data = load_data();

    $ownsTarget = false;
    foreach ($data['properties'] as $p) {
        if ((int) $p['id'] === $id && (int) ($p['agent_id'] ?? 0) === $agentId) {
            $ownsTarget = true;
            break;
        }
    }

    if ($ownsTarget && $action === 'toggle_featured') {
        foreach ($data['properties'] as &$p) {
            if ((int) $p['id'] === $id) {
                $p['featured'] = empty($p['featured']);
            }
        }
        unset($p);
        save_data($data);
    } elseif ($ownsTarget && $action === 'duplicate') {
        $source = null;
        foreach ($data['properties'] as $p) {
            if ((int) $p['id'] === $id) {
                $source = $p;
                break;
            }
        }
        if ($source) {
            $newId = next_id('property');
            $data = load_data();
            $source['id'] = $newId;
            $source['title'] .= ' (עותק)';
            $source['status'] = 'draft';
            $source['featured'] = false;
            $source['created_at'] = date('Y-m-d H:i:s');
            $source['agent_id'] = $agentId;
            $source['images'] = array_map('duplicate_uploaded_image', $source['images'] ?? []);
            $data['properties'][] = $source;
            save_data($data);
            $flashMsg = ['type' => 'success', 'text' => 'הנכס שוכפל כטיוטה.'];
        }
    } elseif ($ownsTarget && $action === 'delete') {
        $target = null;
        foreach ($data['properties'] as $p) {
            if ((int) $p['id'] === $id) {
                $target = $p;
                break;
            }
        }
        if ($target) {
            foreach ($target['images'] ?? [] as $img) {
                delete_uploaded_image($img);
            }
            $data['properties'] = array_values(array_filter($data['properties'], fn($p) => (int) $p['id'] !== $id));
            save_data($data);
            $flashMsg = ['type' => 'success', 'text' => 'הנכס נמחק.'];
        }
    }
}

$filterStatus = $_GET['status'] ?? '';
$q = trim($_GET['q'] ?? '');

$properties = agent_properties($agentId, false);
usort($properties, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

if ($filterStatus !== '') {
    $properties = array_values(array_filter($properties, fn($p) => ($p['status'] ?? '') === $filterStatus));
}
if ($q !== '') {
    $properties = array_values(array_filter($properties, fn($p) => mb_stripos($p['title'] ?? '', $q) !== false));
}

$portalTitle = 'הנכסים שלי';
require __DIR__ . '/includes/agent-header.php';
?>

<div class="admin-main-head">
  <h1>הנכסים שלי</h1>
  <a href="<?= e(url('agent-portal/property-edit.php')) ?>" class="btn btn-primary btn-sm">+ נכס חדש</a>
</div>

<?php if ($flashMsg): ?>
  <p class="alert-box <?= $flashMsg['type'] === 'error' ? 'alert-error' : 'alert-success' ?>"><?= e($flashMsg['text']) ?></p>
<?php endif; ?>

<form method="get" class="admin-card admin-form-grid cols-2" style="align-items:end;">
  <div class="field" style="margin-bottom:0;">
    <label for="f-q">חיפוש לפי כותרת</label>
    <input class="input" type="text" id="f-q" name="q" value="<?= e($q) ?>">
  </div>
  <div class="field" style="margin-bottom:0;">
    <label for="f-status">סטטוס</label>
    <select class="input" id="f-status" name="status">
      <option value="">הכל</option>
      <?php foreach (status_labels() as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $filterStatus === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn btn-outline">סינון</button>
</form>

<div class="admin-card">
  <?php if ($properties): ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th></th><th>כותרת</th><th>עיר</th><th>מחיר</th><th>סטטוס</th><th>מומלץ</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($properties as $p): ?>
            <tr>
              <td><img class="admin-thumb" src="<?= e(media_url($p['images'][0] ?? null)) ?>" alt=""></td>
              <td><?= e($p['title']) ?></td>
              <td><?= e($p['city'] ?? '') ?><?= !empty($p['neighborhood']) ? ' · ' . e($p['neighborhood']) : '' ?></td>
              <td><?= e(money((float) $p['price'])) ?><?= $p['deal'] === 'rent' ? ' / חודש' : '' ?></td>
              <td>
                <?php
                  $statusClass = ['available' => 'badge-success', 'sold' => 'badge-alert', 'draft' => 'badge-muted'][$p['status'] ?? ''] ?? 'badge';
                ?>
                <span class="badge <?= $statusClass ?>"><?= e(status_labels()[$p['status']] ?? $p['status']) ?></span>
              </td>
              <td>
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle_featured">
                  <input type="hidden" name="id" value="<?= e((string) $p['id']) ?>">
                  <button type="submit" class="badge <?= !empty($p['featured']) ? 'badge-success' : 'badge-muted' ?>" style="border:0; cursor:pointer;">
                    <?= !empty($p['featured']) ? 'כן' : 'לא' ?>
                  </button>
                </form>
              </td>
              <td>
                <div class="admin-row-actions">
                  <a href="<?= e(url('agent-portal/property-edit.php?id=' . $p['id'])) ?>" class="btn btn-outline btn-sm">עריכה</a>
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="duplicate">
                    <input type="hidden" name="id" value="<?= e((string) $p['id']) ?>">
                    <button type="submit" class="btn btn-outline btn-sm">שכפול</button>
                  </form>
                  <form method="post" onsubmit="return confirm('למחוק את הנכס &quot;<?= e($p['title']) ?>&quot;?');">
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
    <p class="empty-note">לא נמצאו נכסים תואמים. <a href="<?= e(url('agent-portal/property-edit.php')) ?>">הוסיפו נכס ראשון</a>.</p>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/agent-footer.php'; ?>
