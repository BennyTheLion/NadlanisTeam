<?php
/**
 * מצפה למשתנים (כולם אופציונליים):
 * $pageTitle, $pageDescription, $canonicalPath, $ogImage, $bodyClass, $jsonLd
 */
$settings = load_data()['settings'];
$pageTitle = $pageTitle ?? $settings['agency_name'] . ' — ' . $settings['tagline'];
$pageDescription = $pageDescription ?? 'נדלניס טים — תיווך, שיווק והשקעות נדל״ן בנתניה. ליווי אישי בקנייה, מכירה והשקעה.';
$canonicalPath = $canonicalPath ?? ($_SERVER['REQUEST_URI'] ?? '/');
$ogImage = $ogImage ?? url('assets/img/logo-header.png');
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');

$navLinks = [
    'index.php' => 'בית',
    'properties.php' => 'נכסים',
    'mortgage-calculator.php' => 'מחשבון משכנתא',
    'agents.php' => 'הצוות',
    'partners.php' => 'שותפים',
    'about.php' => 'אודות',
    'contact.php' => 'צור קשר',
];
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDescription) ?>">
<?php if (!empty($robotsMeta)): ?><meta name="robots" content="<?= e($robotsMeta) ?>"><?php endif; ?>
<link rel="canonical" href="<?= e((str_starts_with($canonicalPath, 'http') ? '' : ((isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? ''))) . $canonicalPath) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDescription) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta property="og:locale" content="he_IL">
<link rel="icon" href="<?= e(url('assets/img/logo-icon.png')) ?>" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Frank+Ruhl+Libre:wght@400;500;700;900&family=Heebo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>">
<?php if (!empty($jsonLd)): ?>
<script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endif; ?>
</head>
<body class="<?= e($bodyClass ?? '') ?>">

<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
  <symbol id="roofline-path" viewBox="0 0 1000 260">
    <path d="M0,230 L420,30 L560,155 L610,155 L610,75 L695,75 L695,165 L1000,230"/>
  </symbol>
</svg>

<a class="skip-link" href="#main">דלגו לתוכן הראשי</a>

<header class="site-header">
  <div class="container header-inner">
    <a href="<?= e(url('index.php')) ?>" class="logo-link" aria-label="<?= e($settings['agency_name']) ?> — לראש העמוד">
      <?php if (is_file(APP_ROOT . '/assets/img/logo-header.png')): ?>
        <img src="<?= e(url('assets/img/logo-header.png')) ?>" alt="<?= e($settings['agency_name']) ?>" class="logo-img" width="220" height="116">
      <?php else: ?>
        <span>
          <span class="logo-wordmark" dir="ltr"><span class="lw-ink">Nadlanis</span><span class="lw-blue">Team</span></span>
          <span class="logo-tagline"><?= e($settings['tagline']) ?></span>
        </span>
      <?php endif; ?>
    </a>

    <nav class="nav" id="siteNav" data-open="false">
      <?php foreach ($navLinks as $file => $label): ?>
        <a href="<?= e(url($file)) ?>" <?= $currentScript === $file ? 'class="is-active" aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="header-actions">
      <?php $agentLoggedIn = !empty($_SESSION['agent_logged_in']); ?>
      <?php if ($agentLoggedIn): ?>
        <a href="<?= e(url('agent-portal/index.php')) ?>" class="header-icon-link" title="הדשבורד שלי" aria-label="הדשבורד שלי">
          <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
        </a>
        <a href="<?= e(url('agent-portal/logout.php')) ?>" class="header-icon-link" title="התנתקות" aria-label="התנתקות">
          <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M16 13v-2H7V8l-5 4 5 4v-3zM20 3h-9c-1.1 0-2 .9-2 2v4h2V5h9v14h-9v-4H9v4c0 1.1.9 2 2 2h9c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
        </a>
      <?php else: ?>
        <a href="<?= e(url('agent-portal/login.php')) ?>" class="header-icon-link" title="כניסת סוכנים" aria-label="כניסת סוכנים">
          <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
        </a>
      <?php endif; ?>
      <a href="<?= e(tel_link($settings['phone'])) ?>" class="btn btn-primary btn-sm">
        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.5.6.6 0 1 .4 1 1V20.5c0 .6-.4 1-1 1C10.5 21.5 2.5 13.5 2.5 3.9c0-.6.4-1 1-1H7c.6 0 1 .4 1 1 0 1.2.2 2.4.6 3.5.1.4 0 .8-.3 1.1l-2.2 2.2z"/></svg>
        <span>התקשרו עכשיו</span>
      </a>
      <button class="nav-toggle" id="navToggle" type="button" aria-expanded="false" aria-controls="siteNav" aria-label="פתיחת תפריט ניווט">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>

<main id="main">
