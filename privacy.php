<?php
require __DIR__ . '/includes/config.php';

$settings = load_data()['settings'];
$pageTitle = 'מדיניות פרטיות — ' . $settings['agency_name'];
$pageDescription = 'מדיניות הפרטיות של ' . $settings['agency_name'] . ' — כיצד אנו אוספים, משתמשים ושומרים על המידע שלכם.';
$robotsMeta = 'noindex, follow';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div class="container">
    <p class="breadcrumbs"><a href="<?= e(url('index.php')) ?>">בית</a> / מדיניות פרטיות</p>
    <h1>מדיניות פרטיות</h1>
  </div>
</div>

<div class="container section">
  <div style="max-width:70ch;">
    <p class="alert-box" style="background:var(--blue-tint); color:var(--blue-deep); margin-bottom: var(--space-3);">
      זהו נוסח מדיניות פרטיות גנרי לצורכי הדגמה. יש להחליפו בנוסח שנבדק ואושר על ידי עורך/ת דין
      לפני שימוש באתר פעיל, כך שישקף במדויק את האופן שבו <?= e($settings['agency_name']) ?> אוספת ומשתמשת במידע.
    </p>

    <p style="color:var(--ink-3); margin-bottom: var(--space-3);">עודכן לאחרונה: <?= date('d.m.Y') ?></p>

    <h2 class="section-title" style="font-size:1.3rem;">כללי</h2>
    <p><?= e($settings['agency_name']) ?> ("החברה", "אנחנו") מכבדת את פרטיות המשתמשים באתר. מדיניות זו מסבירה אילו נתונים
    אנו אוספים, כיצד אנו משתמשים בהם, ואילו זכויות עומדות לכם ביחס אליהם.</p>

    <h2 class="section-title" style="font-size:1.3rem;">מידע שאנו אוספים</h2>
    <p>כאשר אתם משאירים פרטים בטופס יצירת קשר (בעמוד נכס, עמוד סוכן, עמוד "צור קשר" או כל טופס דומה באתר),
    אנו אוספים את הפרטים שמסרתם: שם מלא, מספר טלפון, כתובת אימייל (אם סופקה) ותוכן ההודעה. איננו אוספים
    מידע זה באופן אוטומטי — הוא נמסר מרצונכם החופשי לצורך יצירת קשר.</p>

    <h2 class="section-title" style="font-size:1.3rem;">כיצד אנו משתמשים במידע</h2>
    <p>המידע שנמסר משמש אותנו ואת הסוכנים/השותפים הרלוונטיים באתר ליצירת קשר עמכם בנוגע לפנייתכם בלבד —
    לדוגמה, מענה לגבי נכס או שירות ספציפי שביקשתם מידע עליו. איננו מוכרים או משכירים את פרטיכם לצדדים
    שלישיים למטרות שיווק.</p>

    <h2 class="section-title" style="font-size:1.3rem;">שמירת מידע</h2>
    <p>פניות שנשלחות דרך האתר נשמרות במערכת הפנימית שלנו לצורך מעקב וטיפול בפנייה. תוכלו לבקש בכל עת
    את מחיקת המידע שנמסר על ידכם, בפנייה ישירה אלינו בפרטי הקשר המופיעים בתחתית עמוד זה.</p>

    <h2 class="section-title" style="font-size:1.3rem;">עוגיות (Cookies)</h2>
    <p>האתר עשוי להשתמש בעוגיות טכניות הנדרשות לתפעולו התקין (לדוגמה, שמירת מצב סינון או הודעת שליחה
    מוצלחת). איננו משתמשים בעוגיות למעקב פרסומי.</p>

    <h2 class="section-title" style="font-size:1.3rem;">יצירת קשר בנוגע לפרטיות</h2>
    <p>לשאלות או בקשות בנוגע למדיניות זו ולמידע שנמסר על ידכם, ניתן לפנות אלינו:</p>
    <p>
      <a href="mailto:<?= e($settings['email']) ?>"><?= e($settings['email']) ?></a><br>
      <a href="<?= e(tel_link($settings['phone'])) ?>"><?= e($settings['phone']) ?></a>
    </p>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
