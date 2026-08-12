<?php
require __DIR__ . '/includes/auth.php';

$filterAgent = (int) ($_GET['agent'] ?? 0);
$filterProperty = (int) ($_GET['property'] ?? 0);
$filterPartner = (int) ($_GET['partner'] ?? 0);

$sourceLabels = ['property' => 'נכס', 'agent' => 'סוכן', 'partner' => 'שותף', 'contact' => 'צור קשר', 'home' => 'דף הבית'];

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $leads = filtered_leads($filterAgent, $filterProperty, $filterPartner);
    $propsById = [];
    foreach (load_data()['properties'] as $p) {
        $propsById[(int) $p['id']] = $p['title'];
    }
    $agentsById = [];
    foreach (load_data()['agents'] as $a) {
        $agentsById[(int) $a['id']] = $a['name'];
    }
    $partnersById = [];
    foreach (load_data()['partners'] as $p) {
        $partnersById[(int) $p['id']] = $p['name'];
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="leads.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['תאריך', 'שם', 'טלפון', 'אימייל', 'הודעה', 'מקור', 'נכס', 'סוכן', 'שותף', 'שירות', 'נקרא']);
    foreach ($leads as $l) {
        fputcsv($out, [
            $l['created_at'] ?? '',
            $l['name'] ?? '',
            $l['phone'] ?? '',
            $l['email'] ?? '',
            $l['message'] ?? '',
            $sourceLabels[$l['source'] ?? ''] ?? ($l['source'] ?? ''),
            $propsById[(int) ($l['property_id'] ?? 0)] ?? '',
            $agentsById[(int) ($l['agent_id'] ?? 0)] ?? '',
            $partnersById[(int) ($l['partner_id'] ?? 0)] ?? '',
            $l['service'] ?? '',
            !empty($l['read']) ? 'כן' : 'לא',
        ]);
    }
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $data = load_data();

    if ($action === 'mark_read') {
        foreach ($data['leads'] as &$l) {
            if ((int) $l['id'] === $id) {
                $l['read'] = true;
            }
        }
        unset($l);
        save_data($data);
    } elseif ($action === 'delete') {
        $data['leads'] = array_values(array_filter($data['leads'], fn($l) => (int) $l['id'] !== $id));
        save_data($data);
    }

    $qs = http_build_query(['agent' => $filterAgent ?: null, 'property' => $filterProperty ?: null, 'partner' => $filterPartner ?: null]);
    header('Location: ' . url('admin/leads.php') . ($qs ? '?' . $qs : ''));
    exit;
}

$leads = filtered_leads($filterAgent, $filterProperty, $filterPartner);

$agents = all_agents(false);
$agentsById = [];
foreach ($agents as $a) {
    $agentsById[(int) $a['id']] = $a['name'];
}
$properties = load_data()['properties'];
$propsById = [];
foreach ($properties as $p) {
    $propsById[(int) $p['id']] = $p['title'];
}
$partners = all_partners(false);
$partnersById = [];
foreach ($partners as $p) {
    $partnersById[(int) $p['id']] = $p['name'];
}

$adminTitle = 'פניות';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-main-head">
  <h1>פניות</h1>
  <a href="<?= e(url('admin/leads.php')) ?>?export=csv&agent=<?= e((string) $filterAgent) ?>&property=<?= e((string) $filterProperty) ?>&partner=<?= e((string) $filterPartner) ?>" class="btn btn-outline btn-sm">ייצוא ל-CSV</a>
</div>

<form method="get" class="admin-card admin-form-grid cols-2" style="align-items:end;">
  <div class="field" style="margin-bottom:0;">
    <label for="f-agent">סוכן</label>
    <select class="input" id="f-agent" name="agent">
      <option value="">כל הסוכנים</option>
      <?php foreach ($agents as $a): ?>
        <option value="<?= e((string) $a['id']) ?>" <?= $filterAgent === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field" style="margin-bottom:0;">
    <label for="f-property">נכס</label>
    <select class="input" id="f-property" name="property">
      <option value="">כל הנכסים</option>
      <?php foreach ($properties as $p): ?>
        <option value="<?= e((string) $p['id']) ?>" <?= $filterProperty === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['title']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field" style="margin-bottom:0;">
    <label for="f-partner">שותף</label>
    <select class="input" id="f-partner" name="partner">
      <option value="">כל השותפים</option>
      <?php foreach ($partners as $p): ?>
        <option value="<?= e((string) $p['id']) ?>" <?= $filterPartner === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
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
          <tr><th>שם</th><th>טלפון</th><th>מקור</th><th>נכס / סוכן / שותף</th><th>תאריך</th><th></th></tr>
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
              <td>
                <?= e($propsById[(int) ($l['property_id'] ?? 0)] ?? '') ?>
                <?= e($agentsById[(int) ($l['agent_id'] ?? 0)] ?? '') ?>
                <?= e($partnersById[(int) ($l['partner_id'] ?? 0)] ?? '') ?>
                <?= !empty($l['service']) ? ' · ' . e($l['service']) : '' ?>
              </td>
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

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
