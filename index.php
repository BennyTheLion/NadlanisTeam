<?php
require __DIR__ . '/includes/config.php';

$settings = load_data()['settings'];
$pageTitle = $settings['agency_name'] . ' — ' . $settings['tagline'];
$pageDescription = 'נדלניס טים — תיווך, שיווק והשקעות נדל״ן בנתניה. חיפוש נכסים למכירה ולהשכרה, ליווי אישי מהתחלה ועד הסוף.';
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'RealEstateAgent',
    'name' => $settings['agency_name'],
    'telephone' => $settings['phone'],
    'email' => $settings['email'],
    'areaServed' => 'נתניה',
    'address' => ['@type' => 'PostalAddress', 'addressLocality' => $settings['address'], 'addressCountry' => 'IL'],
];
require __DIR__ . '/includes/header.php';

$allFeatured = array_values(array_filter(all_properties(true), fn($p) => !empty($p['featured'])));
$featured = array_slice($allFeatured, 0, 6);
if (count($featured) < 6) {
    foreach (all_properties(true) as $p) {
        if (count($featured) >= 6) break;
        if (!in_array($p, $featured, true)) $featured[] = $p;
    }
}
$agents = all_agents(true);
$testimonials = load_data()['testimonials'];
$cities = cities_in_use();
?>

<section class="hero">
  <div class="hero-media">
    <picture>
      <source type="image/avif" srcset="<?= e(url('assets/img/HeroImagec.avif')) ?>">
      <img class="hero-img"
        src="<?= e(url('assets/img/HeroImagec.webp')) ?>"
        alt="בית מודרני בשעת בין ערביים, חלונות מוארים" loading="eager">
    </picture>
    <div class="hero-overlay"></div>
  </div>
  <svg class="roofline-hero" viewBox="0 0 1000 260" preserveAspectRatio="none" aria-hidden="true"><use href="#roofline-path"></use></svg>

  <div class="container hero-content">
    <p class="eyebrow eyebrow-light"><?= e($settings['tagline']) ?></p>
    <h1 class="hero-title"><?= e($settings['hero_title']) ?></h1>
    <p class="hero-sub"><?= e($settings['hero_sub']) ?></p>
    <div class="hero-cta">
      <a href="<?= e(url('properties.php')) ?>" class="btn btn-primary btn-lg">לצפייה בנכסים</a>
      <a href="<?= e(url('contact.php')) ?>" class="btn btn-ghost btn-lg">שיחת ייעוץ חינם</a>
    </div>

    <form class="search-card" method="get" action="<?= e(url('properties.php')) ?>">
      <div class="search-tabs" role="radiogroup" aria-label="סוג עסקה">
        <input class="search-tab-input" type="radio" id="deal-sale" name="deal" value="sale" checked>
        <label class="search-tab" for="deal-sale">למכירה</label>
        <input class="search-tab-input" type="radio" id="deal-rent" name="deal" value="rent">
        <label class="search-tab" for="deal-rent">להשכרה</label>
      </div>
      <div class="search-grid">
        <div class="field" style="margin-bottom:0;">
          <label for="q-city">עיר / שכונה</label>
          <select class="input" id="q-city" name="city">
            <option value="">כל האזורים</option>
            <?php foreach ($cities as $c): ?>
              <option value="<?= e($c) ?>">נתניה — <?= e($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" style="margin-bottom:0;">
          <label for="q-type">סוג נכס</label>
          <select class="input" id="q-type" name="type">
            <option value="">כל הסוגים</option>
            <?php foreach (property_types() as $t): ?>
              <option value="<?= e($t) ?>"><?= e($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" style="margin-bottom:0;">
          <label for="q-rooms">חדרים (מינ׳)</label>
          <select class="input" id="q-rooms" name="rooms_min">
            <option value="">הכל</option>
            <?php foreach ([1, 2, 3, 4, 5] as $r): ?>
              <option value="<?= $r ?>"><?= $r ?>+</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" style="margin-bottom:0;">
          <label for="q-q">חיפוש חופשי</label>
          <input class="input" type="text" id="q-q" name="q" placeholder="שכונה, כתובת...">
        </div>
        <button type="submit" class="btn btn-primary">חיפוש נכסים</button>
      </div>
    </form>
  </div>
</section>

<section class="section" id="featured">
  <div class="container">
    <div class="roof-rule" aria-hidden="true"><svg viewBox="0 0 1000 260"><use href="#roofline-path"></use></svg></div>
    <p class="eyebrow">נכסים מובילים</p>
    <h2 class="section-title">נכסים נבחרים השבוע</h2>
    <?php if ($featured): ?>
      <div class="property-grid">
        <?php foreach ($featured as $p): include __DIR__ . '/includes/property-card.php'; endforeach; ?>
      </div>
      <div style="text-align:center; margin-top: var(--space-4);">
        <a href="<?= e(url('properties.php')) ?>" class="btn btn-outline">כל הנכסים</a>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <svg class="roofline-mark" viewBox="0 0 1000 260" aria-hidden="true"><use href="#roofline-path"></use></svg>
        <p>עדיין אין נכסים פעילים להצגה.</p>
      </div>
    <?php endif; ?>
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

<section class="section" id="team">
  <div class="container">
    <div class="roof-rule" aria-hidden="true"><svg viewBox="0 0 1000 260"><use href="#roofline-path"></use></svg></div>
    <p class="eyebrow">הצוות שלנו</p>
    <h2 class="section-title">האנשים שילוו אתכם</h2>
    <div class="agent-grid">
      <?php foreach ($agents as $a): include __DIR__ . '/includes/agent-card.php'; endforeach; ?>
    </div>
  </div>
</section>

<section class="section section-alt" id="about">
  <div class="container about-grid">
    <div class="about-media">
      <img src="https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1000&q=75" alt="סלון מודרני ומואר בבית פרטי" loading="lazy" width="1000" height="750">
    </div>
    <div class="about-content">
      <div class="roof-rule" aria-hidden="true"><svg viewBox="0 0 1000 260"><use href="#roofline-path"></use></svg></div>
      <p class="eyebrow">מי אנחנו</p>
      <h2 class="section-title">צוות מקומי, שמכיר את נתניה<br>כמו את הבית שלו</h2>
      <p style="white-space:pre-line;"><?= e($settings['about_text']) ?></p>
      <a href="<?= e(url('about.php')) ?>" class="link-arrow">קראו עוד עלינו <span aria-hidden="true">&larr;</span></a>
      <div class="stat-row">
        <div class="stat-tile"><div class="stat-num"><?= e((string) $settings['stat_years']) ?></div><div class="stat-label">שנות ניסיון</div></div>
        <div class="stat-tile"><div class="stat-num"><?= e((string) $settings['stat_deals']) ?></div><div class="stat-label">עסקאות שנסגרו</div></div>
        <div class="stat-tile"><div class="stat-num"><?= e((string) $settings['stat_clients']) ?></div><div class="stat-label">לקוחות ממליצים</div></div>
      </div>
    </div>
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

<section class="section contact-band" id="contact">
  <div class="container contact-band-inner">
    <p class="eyebrow eyebrow-light">בואו נדבר</p>
    <h2 class="section-title title-light">המפגש הראשון על חשבוננו</h2>
    <p class="contact-sub">ספרו לנו מה אתם מחפשים — בין אם זו דירה ראשונה, שדרוג, או הזדמנות השקעה. נחזור אליכם באותו היום.</p>
    <div class="contact-methods">
      <a class="contact-method" href="<?= e(tel_link($settings['phone'])) ?>">
        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.5.6.6 0 1 .4 1 1V20.5c0 .6-.4 1-1 1C10.5 21.5 2.5 13.5 2.5 3.9c0-.6.4-1 1-1H7c.6 0 1 .4 1 1 0 1.2.2 2.4.6 3.5.1.4 0 .8-.3 1.1l-2.2 2.2z"/></svg>
        <span><?= e($settings['phone']) ?></span>
      </a>
      <a class="contact-method" href="<?= e(wa_link($settings['whatsapp'])) ?>" target="_blank" rel="noopener">
        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5.1-1.3A10 10 0 1 0 12 2Zm5.7 14.2c-.2.7-1.4 1.3-2 1.4-.5.1-1.1.1-1.8-.1-.4-.1-1-.3-1.6-.6-2.9-1.3-4.8-4.2-5-4.4-.1-.2-1.2-1.6-1.2-3.1s.8-2.2 1.1-2.5c.3-.3.6-.4.8-.4h.6c.2 0 .4 0 .6.5.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.1.2-.1.3-.3.5l-.4.5c-.1.2-.3.4-.1.7.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.4 2.4 1.5.3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.5.7 1.8.8.3.1.5.2.5.3.1.3.1.7-.1 1.1Z"/></svg>
        <span>וואטסאפ</span>
      </a>
      <a class="contact-method" href="mailto:<?= e($settings['email']) ?>">
        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm1.4 2 7.1 6.1a1 1 0 0 0 1.3 0L20 7"/></svg>
        <span><?= e($settings['email']) ?></span>
      </a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
