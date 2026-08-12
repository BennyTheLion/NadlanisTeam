<?php
require __DIR__ . '/includes/auth.php';

$myProperties = agent_properties($agentId, false);
$activeCount = count(array_filter($myProperties, fn($p) => ($p['status'] ?? '') === 'available'));
$soldCount = count(array_filter($myProperties, fn($p) => ($p['status'] ?? '') === 'sold'));
$draftCount = count(array_filter($myProperties, fn($p) => ($p['status'] ?? '') === 'draft'));

$myLeads = filtered_leads($agentId, 0, 0);
$newLeadsCount = count(array_filter($myLeads, fn($l) => empty($l['read'])));
$recentLeads = array_slice($myLeads, 0, 5);

$sourceLabels = ['property' => 'נכס', 'agent' => 'סוכן', 'partner' => 'שותף', 'contact' => 'צור קשר', 'home' => 'דף הבית'];

$portalTitle = 'דשבורד';
require __DIR__ . '/includes/agent-header.php';
?>

<div class="admin-main-head">
  <h1>שלום, <?= e($currentAgent['name']) ?></h1>
  <a href="<?= e(url('agent-portal/property-edit.php')) ?>" class="btn btn-primary btn-sm">+ נכס חדש</a>
</div>

<div class="admin-stat-grid">
  <div class="admin-stat-tile">
    <div class="admin-stat-num"><?= e((string) $activeCount) ?></div>
    <div class="admin-stat-label">נכסים פעילים</div>
  </div>
  <div class="admin-stat-tile">
    <div class="admin-stat-num"><?= e((string) $draftCount) ?></div>
    <div class="admin-stat-label">טיוטות</div>
  </div>
  <div class="admin-stat-tile">
    <div class="admin-stat-num"><?= e((string) $soldCount) ?></div>
    <div class="admin-stat-label">נמכרו</div>
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
              <td><?= e($sourceLabels[$l['source']] ?? $l['source']) ?></td>
              <td><?= e($l['created_at']) ?></td>
              <td><a href="<?= e(url('agent-portal/leads.php')) ?>" class="btn btn-outline btn-sm">לפניות</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="empty-note">עדיין אין פניות.</p>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/agent-footer.php'; ?>
