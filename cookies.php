<?php
require __DIR__ . '/includes/config.php';

$settings = get_settings();
$pageTitle = 'מדיניות עוגיות — ' . $settings['agency_name'];
$pageDescription = 'מדיניות העוגיות (Cookies) של ' . $settings['agency_name'] . ' — אילו עוגיות בשימוש באתר ולמה.';
$robotsMeta = 'noindex, follow';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div class="container">
    <p class="breadcrumbs"><a href="<?= e(url('index.php')) ?>">בית</a> / מדיניות עוגיות</p>
    <h1>מדיניות עוגיות</h1>
  </div>
</div>

<div class="container section">
  <div style="max-width:70ch;">
    <p class="alert-box" style="background:var(--blue-tint); color:var(--blue-deep); margin-bottom: var(--space-3);">
      זהו נוסח מדיניות עוגיות גנרי לצורכי הדגמה. יש להחליפו בנוסח שנבדק ואושר על ידי עורך/ת דין
      לפני שימוש באתר פעיל, כך שישקף במדויק את העוגיות שבפועל בשימוש באתר.
    </p>

    <p style="color:var(--ink-3); margin-bottom: var(--space-3);">עודכן לאחרונה: <?= date('d.m.Y') ?></p>

    <h2 class="section-title" style="font-size:1.3rem;">מהן עוגיות</h2>
    <p>עוגיה (Cookie) היא קובץ טקסט קטן שנשמר בדפדפן שלכם בעת הגלישה באתר, ומאפשר לאתר "לזכור" מידע
    לגבי הביקור שלכם — לדוגמה, שאתם מחוברים לחשבון, או פרטי טופס ששלחתם.</p>

    <h2 class="section-title" style="font-size:1.3rem;">אילו עוגיות אנו משתמשים בהן</h2>
    <p><?= e($settings['agency_name']) ?> משתמשת אך ורק בעוגיות טכניות ההכרחיות לתפעול תקין של האתר.
    איננו משתמשים בעוגיות פרסום, מעקב או ניתוח שימוש (אנליטיקס) של צדדים שלישיים.</p>
    <table class="hours-table" style="width:100%; margin-top: var(--space-2);">
      <tbody>
        <tr>
          <td style="font-weight:700; white-space:nowrap; vertical-align:top;">עוגיית סשן (Session)</td>
          <td>מזהה אתכם כמחוברים באזור הניהול או פורטל הסוכנים לאורך הביקור. נמחקת אוטומטית עם סגירת הדפדפן.</td>
        </tr>
        <tr>
          <td style="font-weight:700; white-space:nowrap; vertical-align:top;">אימות טופס (CSRF)</td>
          <td>עוגיית אבטחה טכנית המוודאת שטפסים באתר (כגון יצירת קשר או עדכון נכס) נשלחים מהאתר עצמו ולא ממקור זדוני.</td>
        </tr>
        <tr>
          <td style="font-weight:700; white-space:nowrap; vertical-align:top;">אישור הודעת עוגיות</td>
          <td>נשמר באחסון המקומי של הדפדפן (localStorage) לאחר שאישרתם את הודעת העוגיות, כדי שההודעה לא תוצג בכל ביקור.</td>
        </tr>
      </tbody>
    </table>

    <h2 class="section-title" style="font-size:1.3rem;">ניהול עוגיות</h2>
    <p>ניתן לחסום או למחוק עוגיות דרך הגדרות הדפדפן שלכם בכל עת. שימו לב שחסימת העוגיות הטכניות
    המפורטות לעיל עלולה למנוע פעולות מסוימות באתר, כגון התחברות לאזור הניהול או פורטל הסוכנים.</p>

    <h2 class="section-title" style="font-size:1.3rem;">מידע נוסף</h2>
    <p>למידע על האופן שבו אנו מטפלים במידע אישי שנמסר לנו, ראו את
    <a href="<?= e(url('privacy.php')) ?>">מדיניות הפרטיות</a> שלנו. לשאלות בנוגע לעוגיות באתר:</p>
    <p>
      <a href="mailto:<?= e($settings['email']) ?>"><?= e($settings['email']) ?></a><br>
      <a href="<?= e(tel_link($settings['phone'])) ?>"><?= e($settings['phone']) ?></a>
    </p>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
