<?php
require __DIR__ . '/includes/config.php';

$settings = get_settings();
$pageTitle = 'הצהרת נגישות — ' . $settings['agency_name'];
$pageDescription = 'הצהרת הנגישות של אתר ' . $settings['agency_name'] . '.';
$robotsMeta = 'noindex, follow';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div class="container">
    <p class="breadcrumbs"><a href="<?= e(url('index.php')) ?>">בית</a> / הצהרת נגישות</p>
    <h1>הצהרת נגישות</h1>
  </div>
</div>

<div class="container section">
  <div style="max-width:70ch;">
    <p class="alert-box" style="background:var(--blue-tint); color:var(--blue-deep); margin-bottom: var(--space-3);">
      זהו נוסח הצהרת נגישות גנרי לצורכי הדגמה. לפני שימוש באתר פעיל יש לבצע בדיקת נגישות בפועל
      (ידנית ובאמצעות כלים אוטומטיים) מול תקן 5568 הישראלי, לתקן ליקויים שנמצאו, ולעדכן נוסח זה
      בהתאם — לרבות פרטי בודק הנגישות ותאריך הבדיקה בפועל.
    </p>

    <p style="color:var(--ink-3); margin-bottom: var(--space-3);">עודכן לאחרונה: <?= date('d.m.Y') ?></p>

    <h2 class="section-title" style="font-size:1.3rem;">מחויבותנו לנגישות</h2>
    <p><?= e($settings['agency_name']) ?> רואה חשיבות רבה במתן שירות שוויוני ונגיש לכלל הגולשים, לרבות
    אנשים עם מוגבלות, ופועלת להנגשת האתר בהתאם לתקנות שוויון זכויות לאנשים עם מוגבלות (התאמות נגישות
    לשירות), התשע"ג-2013, ולתקן הישראלי (ת"י) 5568 להנגשת תכנים באינטרנט ברמה AA.</p>

    <h2 class="section-title" style="font-size:1.3rem;">התאמות הנגישות באתר</h2>
    <p>האתר בנוי בהתחשב, בין היתר, בעקרונות הנגישות הבאים:</p>
    <ul style="margin: 0 0 var(--space-3) 0; padding-inline-start: 1.4em; color: var(--ink-2);">
      <li>אפשרות ניווט וגלישה מלאה באמצעות מקלדת בלבד, כולל קישור "דלגו לתוכן הראשי".</li>
      <li>מבנה כותרות היררכי ותיוג סמנטי המאפשר קריאה נוחה בטכנולוגיות מסייעות (קוראי מסך).</li>
      <li>טקסט חלופי (alt) לתמונות משמעותיות באתר.</li>
      <li>ניגודיות צבעים נאותה בין טקסט לרקע.</li>
      <li>תמיכה בהגדלת טקסט על ידי הדפדפן ללא פגיעה בתפקוד האתר.</li>
    </ul>

    <h2 class="section-title" style="font-size:1.3rem;">הגבלות ידועות</h2>
    <p>חרף מאמצינו, ייתכן שיתגלו חלקים באתר שטרם הונגשו במלואם, לרבות תוכן חיצוני (כגון מפות Google
    Maps המוטמעות בעמודי הנכסים) שאינו בשליטתנו המלאה.</p>

    <h2 class="section-title" style="font-size:1.3rem;">פניות בנושא נגישות</h2>
    <p>נתקלתם בבעיית נגישות באתר, או זקוקים לסיוע בשירות מסוים עקב מוגבלות? נשמח שתפנו אלינו ונטפל
    בפנייתכם בהקדם:</p>
    <p>
      <a href="mailto:<?= e($settings['email']) ?>"><?= e($settings['email']) ?></a><br>
      <a href="<?= e(tel_link($settings['phone'])) ?>"><?= e($settings['phone']) ?></a>
    </p>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
