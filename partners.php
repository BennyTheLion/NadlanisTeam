<?php
require __DIR__ . '/includes/config.php';

$settings = get_settings();
$filters = [
    'category' => $_GET['category'] ?? '',
    'region' => $_GET['region'] ?? '',
    'q' => $_GET['q'] ?? '',
];
$hasFilters = $filters['category'] !== '' || $filters['region'] !== '' || $filters['q'] !== '';

$categories = partner_categories();
$regions = partner_regions_in_use();
$allPartners = all_partners(true);
$results = filter_partners($allPartners, $filters);

$categoryLabel = $filters['category'] !== '' ? partner_category_label($filters['category']) : '';
$pageTitle = ($categoryLabel ? $categoryLabel . ' — ' : '') . 'השותפים שלנו — ' . $settings['agency_name'];
$pageDescription = 'אנחנו משתפים פעולה עם אנשי מקצוע וחברות מובילים בתחומי הנדל״ן, המשפט, המימון והבנייה, כדי להעניק ללקוחות שלנו מעטפת מקצועית לאורך כל הדרך.';
require __DIR__ . '/includes/header.php';

$featured = $hasFilters ? [] : array_values(array_filter($allPartners, fn($p) => !empty($p['featured'])));

function partners_qs(array $overrides = []): string
{
    $params = array_filter(array_merge($_GET, $overrides), fn($v) => $v !== '' && $v !== null);
    return http_build_query($params);
}
?>

<section class="partners-hero">
  <div class="container">
    <p class="eyebrow eyebrow-light">השותפים שלנו</p>
    <h1>כל מה שצריך לעסקת הנדל״ן – במקום אחד</h1>
    <p>אנו משתפים פעולה עם אנשי מקצוע וחברות מובילים בתחומי הנדל״ן, המשפט, המימון והבנייה,
    כדי להעניק ללקוחות שלנו מעטפת מקצועית לאורך כל הדרך.</p>
  </div>
</section>

<div class="container section">
  <div class="category-chip-row" role="group" aria-label="סינון לפי קטגוריה">
    <a class="category-chip <?= $filters['category'] === '' ? 'is-active' : '' ?>" href="?<?= e(partners_qs(['category' => null])) ?>">הכול</a>
    <?php foreach ($categories as $key => $cat): ?>
      <a class="category-chip <?= $filters['category'] === $key ? 'is-active' : '' ?>" href="?<?= e(partners_qs(['category' => $key])) ?>">
        <span aria-hidden="true"><?= $cat['icon'] ?></span> <?= e($cat['label']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <form method="get" action="<?= e(url('partners.php')) ?>" class="filter-card">
    <div class="filter-row" style="grid-template-columns: 1fr auto;">
      <div class="field" style="margin-bottom:0;">
        <label for="p-q">חיפוש לפי שם / שירות / עיר</label>
        <input class="input" type="text" id="p-q" name="q" value="<?= e($filters['q']) ?>" placeholder="🔍 לדוגמה: עורך דין, נתניה...">
      </div>
      <button type="submit" class="btn btn-primary">חיפוש</button>
    </div>
    <?php if ($filters['category'] !== ''): ?><input type="hidden" name="category" value="<?= e($filters['category']) ?>"><?php endif; ?>
    <?php if ($filters['region'] !== ''): ?><input type="hidden" name="region" value="<?= e($filters['region']) ?>"><?php endif; ?>
  </form>

  <div class="finder-card" id="finder">
    <h2 class="section-title" style="font-size:1.3rem; margin-bottom:4px;">צריכים בעל מקצוע לעסקת הנדל״ן?</h2>
    <p class="lede" style="margin-bottom: var(--space-3);">ספרו לנו מה אתם צריכים ונציג את השותפים המתאימים ביותר.</p>
    <form method="get" action="<?= e(url('partners.php')) ?>" id="finderForm">
      <div class="finder-step" data-finder-step="1">
        <span class="finder-step-label">שלב 1: מה אתם צריכים?</span>
        <div class="finder-options" role="radiogroup" aria-label="קטגוריה">
          <?php foreach ($categories as $key => $cat): ?>
            <label class="finder-option">
              <input type="radio" name="category" value="<?= e($key) ?>" <?= $filters['category'] === $key ? 'checked' : '' ?> style="position:absolute; opacity:0;">
              <span class="icon" aria-hidden="true"><?= $cat['icon'] ?></span>
              <span><?= e($cat['label']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="finder-step" data-finder-step="2">
        <label class="finder-step-label" for="finder-region">שלב 2: באיזה אזור?</label>
        <select class="input" id="finder-region" name="region">
          <option value="">כל האזורים</option>
          <?php foreach ($regions as $r): ?>
            <option value="<?= e($r) ?>" <?= $filters['region'] === $r ? 'selected' : '' ?>><?= e($r) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-block" data-finder-submit style="margin-top: var(--space-2);">הצג שותפים מתאימים</button>
    </form>
  </div>

  <?php if ($featured): ?>
    <div class="roof-rule" aria-hidden="true"><svg viewBox="0 0 1000 260"><use href="#roofline-path"></use></svg></div>
    <p class="eyebrow">⭐ שותפים מומלצים</p>
    <h2 class="section-title">מובילים בתחומם</h2>
    <div class="partner-grid">
      <?php foreach ($featured as $partner): include __DIR__ . '/includes/partner-card.php'; endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="results-bar" style="margin-top: var(--space-4);">
    <p class="results-count">נמצאו <?= e((string) count($results)) ?> שותפים<?= $categoryLabel ? ' ב' . e($categoryLabel) : '' ?></p>
  </div>

  <?php if ($results): ?>
    <div class="partner-grid">
      <?php foreach ($results as $partner): include __DIR__ . '/includes/partner-card.php'; endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <svg class="roofline-mark" viewBox="0 0 1000 260" aria-hidden="true"><use href="#roofline-path"></use></svg>
      <p>לא נמצאו שותפים שתואמים לחיפוש</p>
      <a href="<?= e(url('partners.php')) ?>" class="btn btn-outline">נקו את הסינון</a>
    </div>
  <?php endif; ?>
</div>

<script src="<?= e(asset_url('assets/js/partners.js')) ?>" defer></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
