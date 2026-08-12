<?php
require __DIR__ . '/includes/config.php';

$settings = get_settings();
$pageTitle = 'אודות — ' . $settings['agency_name'];
$pageDescription = 'הכירו את נדלניס טים — צוות תיווך, שיווק והשקעות נדל״ן שמכיר את נתניה לעומק, שכונה שכונה.';
require __DIR__ . '/includes/header.php';

$agents = all_agents(true);
$testimonials = all_testimonials();
$cities = cities_in_use();
?>

<div class="page-head">
  <div class="container">
    <p class="breadcrumbs"><a href="<?= e(url('index.php')) ?>">בית</a> / אודות</p>
    <h1>מי אנחנו</h1>
  </div>
</div>

<section class="section" id="story">
  <div class="container about-grid">
    <div class="about-media">
      <img src="https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1000&q=75" alt="סלון מודרני ומואר בבית פרטי" loading="lazy" width="1000" height="750">
    </div>
    <div class="about-content">
      <div class="roof-rule" aria-hidden="true"><svg viewBox="0 0 1000 260"><use href="#roofline-path"></use></svg></div>
      <p class="eyebrow">הסיפור שלנו</p>
      <h2 class="section-title">צוות מקומי, שמכיר את נתניה<br>כמו את הבית שלו</h2>
      <p style="white-space:pre-line;"><?= e($settings['about_text']) ?></p>
      <div class="stat-row">
        <div class="stat-tile"><div class="stat-num"><?= e((string) $settings['stat_years']) ?></div><div class="stat-label">שנות ניסיון</div></div>
        <div class="stat-tile"><div class="stat-num"><?= e((string) $settings['stat_deals']) ?></div><div class="stat-label">עסקאות שנסגרו</div></div>
        <div class="stat-tile"><div class="stat-num"><?= e((string) $settings['stat_clients']) ?></div><div class="stat-label">לקוחות ממליצים</div></div>
      </div>
    </div>
  </div>
</section>

<section class="section section-alt" id="services">
  <div class="container">
    <div class="roof-rule" aria-hidden="true"><svg viewBox="0 0 1000 260"><use href="#roofline-path"></use></svg></div>
    <p class="eyebrow">השירותים שלנו</p>
    <h2 class="section-title">כל מה שצריך, תחת קורת גג אחת</h2>
    <div class="services-grid">
      <article class="service-card">
        <div class="service-icon" aria-hidden="true">
          <svg viewBox="0 0 48 48"><path d="M6 24 24 8l18 16" /><path d="M12 22v16h24V22" /><path d="M20 38v-9h8v9" /></svg>
        </div>
        <h3>תיווך</h3>
        <p>ליווי אישי וצמוד בכל שלב — מהחיפוש או ההכנה למכירה, דרך הצגת הנכס והמשא ומתן, ועד החתימה על החוזה.</p>
      </article>
      <article class="service-card">
        <div class="service-icon" aria-hidden="true">
          <svg viewBox="0 0 48 48"><path d="M6 20v8l8 2V18l-8 2Z" /><path d="M14 18l24-8v28l-24-8" /><path d="M18 30v8a4 4 0 0 0 8 0v-6" /></svg>
        </div>
        <h3>שיווק</h3>
        <p>הנכס שלכם ראוי לחשיפה הטובה ביותר: צילום מקצועי, תיאור מדויק והפצה לקהל הרלוונטי — כדי להגיע לעסקה הנכונה, מהר.</p>
      </article>
      <article class="service-card">
        <div class="service-icon" aria-hidden="true">
          <svg viewBox="0 0 48 48"><path d="M6 38h36" /><path d="M12 38V26M22 38V16M32 38V22M42 38V10" /><path d="M32 10h10v10" /></svg>
        </div>
        <h3>השקעות נדל״ן</h3>
        <p>מכירים את הפוטנציאל של כל שכונה. עוזרים לכם לזהות נכסים עם פוטנציאל השבחה ותשואה אמיתית.</p>
      </article>
    </div>
  </div>
</section>

<?php if ($cities): ?>
<section class="section" id="areas">
  <div class="container">
    <div class="roof-rule" aria-hidden="true"><svg viewBox="0 0 1000 260"><use href="#roofline-path"></use></svg></div>
    <p class="eyebrow">איפה אנחנו פועלים</p>
    <h2 class="section-title">אזורי הפעילות שלנו</h2>
    <div class="areas-grid">
      <?php foreach ($cities as $c): ?>
        <article class="area-card">
          <img src="https://images.unsplash.com/photo-1677029101231-4d2691fd53f3?auto=format&fit=crop&w=700&q=75" alt="חוף הים ב<?= e($c) ?>" loading="lazy">
          <div class="area-card-body">
            <h3><?= e($c) ?></h3>
            <a href="<?= e(url('properties.php') . '?city=' . urlencode($c)) ?>" class="link-arrow" style="color:inherit;">צפו בנכסים <span aria-hidden="true">&larr;</span></a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section section-alt" id="team">
  <div class="container">
    <div class="roof-rule" aria-hidden="true"><svg viewBox="0 0 1000 260"><use href="#roofline-path"></use></svg></div>
    <p class="eyebrow">הצוות שלנו</p>
    <h2 class="section-title">האנשים שילוו אתכם</h2>
    <?php if ($agents): ?>
      <div class="agent-grid">
        <?php foreach ($agents as $a): include __DIR__ . '/includes/agent-card.php'; endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <svg class="roofline-mark" viewBox="0 0 1000 260" aria-hidden="true"><use href="#roofline-path"></use></svg>
        <p>עדיין אין סוכנים להצגה.</p>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($testimonials): ?>
<section class="section" id="testimonials">
  <div class="container">
    <div class="roof-rule" aria-hidden="true"><svg viewBox="0 0 1000 260"><use href="#roofline-path"></use></svg></div>
    <p class="eyebrow">המלצות</p>
    <h2 class="section-title">מה הלקוחות שלנו אומרים</h2>
    <div class="testimonial-grid">
      <?php foreach ($testimonials as $t): ?>
        <article class="testimonial-card">
          <div class="stars" aria-hidden="true"><?= str_repeat('★', max(0, min(5, (int) $t['rating']))) ?></div>
          <blockquote>"<?= e($t['text']) ?>"</blockquote>
          <cite><?= e($t['name']) ?><?= $t['city'] ? ' · ' . e($t['city']) : '' ?></cite>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section contact-band" id="cta">
  <div class="container contact-band-inner">
    <p class="eyebrow eyebrow-light">בואו נדבר</p>
    <h2 class="section-title title-light">רוצים לשמוע עוד? נשמח לפגוש אתכם</h2>
    <p class="contact-sub">בין אם אתם קונים, מוכרים או בודקים השקעה — נשמח לספר לכם איך אנחנו עובדים.</p>
    <div class="hero-cta" style="margin:0; justify-content:center;">
      <a href="<?= e(url('contact.php')) ?>" class="btn btn-primary btn-lg">צרו קשר</a>
      <a href="<?= e(tel_link($settings['phone'])) ?>" class="btn btn-ghost btn-lg">התקשרו עכשיו</a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
