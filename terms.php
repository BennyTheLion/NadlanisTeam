<?php
require __DIR__ . '/includes/config.php';

$settings = load_data()['settings'];
$pageTitle = 'תנאי שימוש — ' . $settings['agency_name'];
$pageDescription = 'תנאי השימוש באתר ' . $settings['agency_name'] . '.';
$robotsMeta = 'noindex, follow';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div class="container">
    <p class="breadcrumbs"><a href="<?= e(url('index.php')) ?>">בית</a> / תנאי שימוש</p>
    <h1>תנאי שימוש</h1>
  </div>
</div>

<div class="container section">
  <div style="max-width:70ch;">
    <p class="alert-box" style="background:var(--blue-tint); color:var(--blue-deep); margin-bottom: var(--space-3);">
      זהו נוסח תנאי שימוש גנרי לצורכי הדגמה. יש להחליפו בנוסח שנבדק ואושר על ידי עורך/ת דין
      לפני שימוש באתר פעיל.
    </p>

    <p style="color:var(--ink-3); margin-bottom: var(--space-3);">עודכן לאחרונה: <?= date('d.m.Y') ?></p>

    <h2 class="section-title" style="font-size:1.3rem;">כללי</h2>
    <p>השימוש באתר <?= e($settings['agency_name']) ?> ("האתר") כפוף לתנאי שימוש אלה. גלישה או שימוש באתר
    מהווים הסכמה לתנאים המפורטים להלן.</p>

    <h2 class="section-title" style="font-size:1.3rem;">תוכן האתר</h2>
    <p>המידע על נכסים, מחירים, זמינות ומפרטים המוצג באתר הוא לצורכי מידע כללי בלבד ואינו מהווה הצעה
    מחייבת. פרטי נכסים עשויים להשתנות ללא הודעה מוקדמת. מומלץ לוודא פרטים מדויקים ועדכניים ישירות
    מול הסוכן הרלוונטי לפני קבלת החלטות.</p>

    <h2 class="section-title" style="font-size:1.3rem;">מחשבון המשכנתא</h2>
    <p>מחשבון המשכנתא באתר נועד להערכה כללית בלבד ואינו מהווה הצעה, ייעוץ פיננסי או התחייבות למתן
    משכנתא. התנאים בפועל נקבעים על ידי הבנקים בהתאם למצבכם האישי.</p>

    <h2 class="section-title" style="font-size:1.3rem;">אנשי מקצוע ושותפים</h2>
    <p>האתר עשוי להציג פרטי אנשי מקצוע ושותפים עסקיים (עורכי דין, יועצי משכנתאות, שמאים ואחרים) לנוחות
    המשתמשים. הצגתם באתר אינה מהווה המלצה משפטית או מקצועית מחייבת, ואחריות הבדיקה וההתקשרות עמם
    חלה על המשתמש בלבד.</p>

    <h2 class="section-title" style="font-size:1.3rem;">קניין רוחני</h2>
    <p>כל הזכויות בעיצוב האתר, בתכניו ובסימני המסחר המוצגים בו שמורות ל<?= e($settings['agency_name']) ?>,
    אלא אם צוין אחרת.</p>

    <h2 class="section-title" style="font-size:1.3rem;">הגבלת אחריות</h2>
    <p><?= e($settings['agency_name']) ?> אינה אחראית לנזק ישיר או עקיף שייגרם כתוצאה משימוש באתר או
    הסתמכות על המידע המוצג בו.</p>

    <h2 class="section-title" style="font-size:1.3rem;">יצירת קשר</h2>
    <p>לשאלות בנוגע לתנאי שימוש אלה:</p>
    <p>
      <a href="mailto:<?= e($settings['email']) ?>"><?= e($settings['email']) ?></a><br>
      <a href="<?= e(tel_link($settings['phone'])) ?>"><?= e($settings['phone']) ?></a>
    </p>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
