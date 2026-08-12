<?php
/**
 * מצפה ש-auth.php כבר נטען (מגדיר $settings ומוודא סשן מחובר).
 * משתנה אופציונלי: $adminTitle
 */
$settings = $settings ?? get_settings();
$adminTitle = $adminTitle ?? 'ניהול';
$currentAdminScript = basename($_SERVER['SCRIPT_NAME'] ?? '');

$adminNavLinks = [
    'index.php' => 'דשבורד',
    'properties.php' => 'נכסים',
    'agents.php' => 'סוכנים',
    'partners.php' => 'שותפים',
    'leads.php' => 'פניות',
    'testimonials.php' => 'המלצות',
    'settings.php' => 'הגדרות',
];
$adminNavParents = [
    'property-edit.php' => 'properties.php',
    'agent-edit.php' => 'agents.php',
    'partner-edit.php' => 'partners.php',
];
$activeAdminNav = $adminNavParents[$currentAdminScript] ?? $currentAdminScript;
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($adminTitle) ?> — ניהול <?= e($settings['agency_name']) ?></title>
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
    <?php foreach ($adminNavLinks as $file => $label): ?>
      <a href="<?= e(url('admin/' . $file)) ?>" <?= $activeAdminNav === $file ? 'class="is-active"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
    <div class="admin-sidebar-foot">
      <a href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">↗ צפייה באתר</a>
      <a href="<?= e(url('admin/logout.php')) ?>">התנתקות</a>
    </div>
  </nav>

  <main class="admin-main">
