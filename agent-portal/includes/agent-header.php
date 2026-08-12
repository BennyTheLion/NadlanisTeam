<?php
/**
 * מצפה ש-auth.php כבר נטען (מגדיר $currentAgent, $agentId ומוודא סשן מחובר).
 * משתנה אופציונלי: $portalTitle
 */
$settings = $settings ?? load_data()['settings'];
$portalTitle = $portalTitle ?? 'איזור סוכנים';
$currentPortalScript = basename($_SERVER['SCRIPT_NAME'] ?? '');

$portalNavLinks = [
    'index.php' => 'דשבורד',
    'properties.php' => 'הנכסים שלי',
    'leads.php' => 'הפניות שלי',
];
$portalNavParents = [
    'property-edit.php' => 'properties.php',
];
$activePortalNav = $portalNavParents[$currentPortalScript] ?? $currentPortalScript;
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($portalTitle) ?> — איזור סוכנים <?= e($settings['agency_name']) ?></title>
<meta name="robots" content="noindex, nofollow">
<link href="https://fonts.googleapis.com/css2?family=Frank+Ruhl+Libre:wght@400;500;700;900&family=Heebo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('admin/assets/admin.css')) ?>">
</head>
<body class="admin-body">
<div class="admin-shell">
  <div class="admin-topbar">
    <span class="admin-brand" dir="ltr">Nadlanis<span>Team</span></span>
    <button class="admin-menu-toggle" id="adminMenuToggle" type="button" aria-expanded="false" aria-controls="adminSidebar">תפריט</button>
  </div>

  <nav class="admin-sidebar" id="adminSidebar" data-open="false">
    <p style="padding: 0 6px 8px; color: var(--ink-3); font-size: 0.85rem;">שלום, <?= e($currentAgent['name']) ?></p>
    <?php foreach ($portalNavLinks as $file => $label): ?>
      <a href="<?= e(url('agent-portal/' . $file)) ?>" <?= $activePortalNav === $file ? 'class="is-active"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
    <div class="admin-sidebar-foot">
      <a href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">↗ צפייה באתר</a>
      <a href="<?= e(url('agent-portal/logout.php')) ?>">התנתקות</a>
    </div>
  </nav>

  <main class="admin-main">
