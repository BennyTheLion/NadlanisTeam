<?php
require __DIR__ . '/includes/auth.php';

$flashMsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'toggle_active') {
        toggle_agent_active($id);
    } elseif ($action === 'delete') {
        if (property_count_for_agent($id) > 0) {
            $flashMsg = ['type' => 'error', 'text' => 'לא ניתן למחוק סוכן עם נכסים משויכים. שייכו את הנכסים שלו לסוכן אחר קודם.'];
        } else {
            delete_agent($id);
            $flashMsg = ['type' => 'success', 'text' => 'הסוכן נמחק.'];
        }
    }
}

$agents = all_agents(false);

$adminTitle = 'סוכנים';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-main-head">
  <h1>סוכנים</h1>
  <a href="<?= e(url('admin/agent-edit.php')) ?>" class="btn btn-primary btn-sm">+ סוכן חדש</a>
</div>

<?php if ($flashMsg): ?>
  <p class="alert-box <?= $flashMsg['type'] === 'error' ? 'alert-error' : 'alert-success' ?>"><?= e($flashMsg['text']) ?></p>
<?php endif; ?>

<div class="admin-card">
  <?php if ($agents): ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th></th><th>שם</th><th>תפקיד</th><th>אזורים</th><th>נכסים</th><th>סדר</th><th>גישה לדשבורד</th><th>כניסה אחרונה</th><th>סטטוס</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($agents as $a): ?>
            <tr>
              <td><img class="admin-thumb" style="border-radius:50%;" src="<?= e(media_url($a['photo'] ?? null)) ?>" alt=""></td>
              <td><?= e($a['name']) ?></td>
              <td><?= e($a['role']) ?></td>
              <td><?= e(implode(', ', $a['areas'] ?? [])) ?></td>
              <td><?= e((string) agent_listing_count((int) $a['id'])) ?></td>
              <td><?= e((string) ($a['sort'] ?? 0)) ?></td>
              <?php $agentUser = agent_user_row((int) $a['id']); ?>
              <td>
                <span class="badge <?= $agentUser ? 'badge-success' : 'badge-muted' ?>">
                  <?= $agentUser ? 'יש גישה' : 'אין גישה' ?>
                </span>
              </td>
              <td><?= e($agentUser['last_login_at'] ?? '—') ?></td>
              <td>
                <form method="post" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle_active">
                  <input type="hidden" name="id" value="<?= e((string) $a['id']) ?>">
                  <button type="submit" class="badge <?= !empty($a['active']) ? 'badge-success' : 'badge-muted' ?>" style="border:0; cursor:pointer;">
                    <?= !empty($a['active']) ? 'פעיל' : 'מוסתר' ?>
                  </button>
                </form>
              </td>
              <td>
                <div class="admin-row-actions">
                  <a href="<?= e(url('admin/agent-edit.php?id=' . $a['id'])) ?>" class="btn btn-outline btn-sm">עריכה</a>
                  <form method="post" onsubmit="return confirm('למחוק את הסוכן <?= e($a['name']) ?>?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= e((string) $a['id']) ?>">
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
    <p class="empty-note">עדיין אין סוכנים. <a href="<?= e(url('admin/agent-edit.php')) ?>">הוסיפו סוכן ראשון</a>.</p>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
