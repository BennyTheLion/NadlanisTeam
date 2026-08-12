<?php
require __DIR__ . '/includes/auth.php';

$data = load_data();
$properties = $data['properties'];
$agents = $data['agents'];
$leads = $data['leads'];

$activeCount = count(array_filter($properties, fn($p) => ($p['status'] ?? '') === 'available'));
$soldCount = count(array_filter($properties, fn($p) => ($p['status'] ?? '') === 'sold'));
$agentCount = count($agents);
$newLeadsCount = count(array_filter($leads, fn($l) => empty($l['read'])));

$agentsById = [];
foreach ($agents as $a) {
    $agentsById[(int) $a['id']] = $a;
}

$recentLeads = $leads;
usort($recentLeads, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
$recentLeads = array_slice($recentLeads, 0, 5);

$adminTitle = 'דשבורד';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-main-head">
  <h1>דשבורד</h1>
  <div class="admin-row-actions">
    <a href="<?= e(url('admin/property-edit.php')) ?>" class="btn btn-primary btn-sm">+ נכס חדש</a>
    <a href="<?= e(url('admin/agent-edit.php')) ?>" class="btn btn-outline btn-sm">+ סוכן חדש</a>
  </div>
</div>

<div class="admin-stat-grid">
  <div class="admin-stat-tile">
    <div class="admin-stat-num"><?= e((string) $activeCount) ?></div>
    <div class="admin-stat-label">נכסים פעילים</div>
  </div>
  <div class="admin-stat-tile">
    <div class="admin-stat-num"><?= e((string) $soldCount) ?></div>
    <div class="admin-stat-label">נמכרו</div>
  </div>
  <div class="admin-stat-tile">
    <div class="admin-stat-num"><?= e((string) $agentCount) ?></div>
    <div class="admin-stat-label">סוכנים</div>
  </div>
  <div class="admin-stat-tile">
    <div class="admin-stat-num"><?= e((string) $newLeadsCount) ?></div>
    <div class="admin-stat-label">פניות חדשות</div>
  </div>
</div>

<div class="admin-card">
  <h2>פניות אחרונות</h2>
  <?php if ($recentLeads): ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>שם</th><th>טלפון</th><th>מקור</th><th>תאריך</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($recentLeads as $l): ?>
            <tr class="<?= empty($l['read']) ? 'is-unread' : '' ?>">
              <td><?= e($l['name']) ?></td>
              <td dir="ltr"><?= e($l['phone']) ?></td>
              <td><?= e(['property' => 'נכס', 'agent' => 'סוכן', 'contact' => 'צור קשר', 'home' => 'דף הבית'][$l['source']] ?? $l['source']) ?></td>
              <td><?= e($l['created_at']) ?></td>
              <td><a href="<?= e(url('admin/leads.php')) ?>" class="btn btn-outline btn-sm">לפניות</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="empty-note">עדיין אין פניות.</p>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
