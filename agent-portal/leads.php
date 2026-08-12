<?php
require __DIR__ . '/includes/auth.php';

$filterProperty = (int) ($_GET['property'] ?? 0);

$sourceLabels = ['property' => 'נכס', 'agent' => 'סוכן', 'partner' => 'שותף', 'contact' => 'צור קשר', 'home' => 'דף הבית'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $data = load_data();

    $ownsTarget = false;
    foreach ($data['leads'] as $l) {
        if ((int) $l['id'] === $id && (int) ($l['agent_id'] ?? 0) === $agentId) {
            $ownsTarget = true;
            break;
        }
    }

    if ($ownsTarget && $action === 'mark_read') {
        foreach ($data['leads'] as &$l) {
            if ((int) $l['id'] === $id) {
                $l['read'] = true;
            }
        }
        unset($l);
        save_data($data);
    } elseif ($ownsTarget && $action === 'delete') {
        $data['leads'] = array_values(array_filter($data['leads'], fn($l) => (int) $l['id'] !== $id));
        save_data($data);
    }

    $qs = http_build_query(['property' => $filterProperty ?: null]);
    header('Location: ' . url('agent-portal/leads.php') . ($qs ? '?' . $qs : ''));
    exit;
}

$leads = filtered_leads($agentId, $filterProperty, 0);

$myProperties = agent_properties($agentId, false);
$propsById = [];
foreach ($myProperties as $p) {
    $propsById[(int) $p['id']] = $p['title'];
}

$portalTitle = 'הפניות שלי';
require __DIR__ . '/includes/agent-header.php';
?>

<div class="admin-main-head">
  <h1>הפניות שלי</h1>
</div>

<form method="get" class="admin-card admin-form-grid cols-2" style="align-items:end;">
  <div class="field" style="margin-bottom:0;">
    <label for="f-property">נכס</label>
    <select class="input" id="f-property" name="property">
      <option value="">כל הנכסים</option>
      <?php foreach ($myProperties as $p): ?>
        <option value="<?= e((string) $p['id']) ?>" <?= $filterProperty === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['title']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn btn-outline">סינון</button>
</form>

<div class="admin-card">
  <?php if ($leads): ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>שם</th><th>טלפון</th><th>מקור</th><th>נכס</th><th>תאריך</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($leads as $l): ?>
            <tr class="<?= empty($l['read']) ? 'is-unread' : '' ?>">
              <td><?= e($l['name']) ?></td>
              <td dir="ltr">
                <a href="<?= e(tel_link($l['phone'])) ?>" title="התקשרות">☎</a>
                <a href="<?= e(wa_link($l['phone'], 'שלום ' . $l['name'] . ', מגיבים לפנייתך בנדלניס טים')) ?>" target="_blank" rel="noopener" title="וואטסאפ">💬</a>
                <?= e($l['phone']) ?>
              </td>
              <td><?= e($sourceLabels[$l['source'] ?? ''] ?? ($l['source'] ?? '')) ?></td>
              <td><?= e($propsById[(int) ($l['property_id'] ?? 0)] ?? '') ?></td>
              <td><?= e($l['created_at']) ?></td>
              <td>
                <div class="admin-row-actions">
                  <?php if (empty($l['read'])): ?>
                    <form method="post">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="mark_read">
                      <input type="hidden" name="id" value="<?= e((string) $l['id']) ?>">
                      <button type="submit" class="btn btn-outline btn-sm">סימון כנקרא</button>
                    </form>
                  <?php endif; ?>
                  <form method="post" onsubmit="return confirm('למחוק את הפנייה?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= e((string) $l['id']) ?>">
                    <button type="submit" class="btn btn-danger btn-sm">מחיקה</button>
                  </form>
                </div>
              </td>
            </tr>
            <?php if (!empty($l['message'])): ?>
              <tr>
                <td></td>
                <td colspan="5" style="color:var(--ink-3); white-space:pre-line;"><?= e($l['message']) ?></td>
              </tr>
            <?php endif; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="empty-note">אין פניות תואמות.</p>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/agent-footer.php'; ?>
