<?php
require __DIR__ . '/includes/config.php';

$id = (int) ($_GET['id'] ?? 0);
$partner = $id ? find_partner($id) : null;

if (!$partner || empty($partner['active'])) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$cat = partner_categories()[$partner['category']] ?? ['label' => $partner['category'], 'icon' => '🔗'];
$initials = mb_substr($partner['name'], 0, 1);

$pageTitle = $partner['name'] . ' — ' . $cat['label'] . ' | ' . load_data()['settings']['agency_name'];
$pageDescription = $partner['description_short'] ?: ($partner['name'] . ', ' . $cat['label']);
$ogImage = media_url($partner['logo'] ?? null);
require __DIR__ . '/includes/header.php';

$backUrl = safe_internal_path($_GET['back'] ?? null, url('partners.php'));
?>

<div class="container" style="padding-top: var(--space-3);">
  <p class="breadcrumbs"><a href="<?= e(url('index.php')) ?>">בית</a> / <a href="<?= e($backUrl) ?>">שותפים</a> / <?= e($partner['name']) ?></p>
</div>

<div class="container property-layout">
  <div>
    <div class="partner-profile-head">
      <?php if (!empty($partner['logo'])): ?>
        <img class="partner-logo" style="width:88px;height:88px;" src="<?= e(media_url($partner['logo'])) ?>" alt="">
      <?php else: ?>
        <span class="partner-logo-fallback" style="width:88px;height:88px;font-size:2rem;" aria-hidden="true"><?= e($initials) ?></span>
      <?php endif; ?>
      <div>
        <?php if (!empty($partner['featured'])): ?><span class="partner-badge-featured" style="position:static; display:inline-flex; margin-bottom:6px;">✓ שותף מומלץ</span><?php endif; ?>
        <h1><?= e($partner['name']) ?></h1>
        <p class="lede" style="margin:0;"><?= $cat['icon'] ?> <?= e($cat['label']) ?><?= $partner['business_type'] ? ' · ' . e($partner['business_type']) : '' ?></p>
        <div class="partner-card-meta" style="justify-content:flex-start; margin-top:8px;">
          <?php if (!empty($partner['regions'])): ?><span>📍 <?= e(implode(', ', $partner['regions'])) ?></span><?php endif; ?>
          <?php if (!empty($partner['rating'])): ?><span>★ <?= e(number_format((float) $partner['rating'], 1)) ?></span><?php endif; ?>
          <?php if (!empty($partner['years_experience'])): ?><span>🏆 <?= e((string) $partner['years_experience']) ?> שנות ניסיון</span><?php endif; ?>
          <?php if (!empty($partner['verified'])): ?><span class="partner-verified">✓ פרופיל מאומת</span><?php endif; ?>
        </div>
      </div>
    </div>

    <?php if (!empty($partner['description_full'])): ?>
      <p class="property-desc"><?= e($partner['description_full']) ?></p>
    <?php endif; ?>

    <?php if (!empty($partner['services'])): ?>
      <h2 class="section-title" style="font-size:1.15rem;">שירותים עיקריים</h2>
      <div class="chip-row" style="margin-bottom: var(--space-3);">
        <?php foreach ($partner['services'] as $service): ?>
          <span class="feature-pill"><?= e($service) ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($partner['gallery'])): ?>
      <h2 class="section-title" style="font-size:1.15rem;">גלריה</h2>
      <div class="gallery-thumbs" style="position:static; height:auto;">
        <?php foreach ($partner['gallery'] as $img): ?>
          <img src="<?= e(media_url($img)) ?>" alt="" style="width:110px; height:80px; object-fit:cover; border-radius:var(--radius-sm);">
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <aside class="agent-sidebar" id="contact">
    <h3 style="margin-bottom: var(--space-2);">פרטי קשר</h3>
    <div class="agent-sidebar-actions">
      <?php if (!empty($partner['phone'])): ?>
        <a class="btn btn-primary btn-block" href="<?= e(tel_link($partner['phone'])) ?>">התקשרו ל<?= e($partner['name']) ?></a>
      <?php endif; ?>
      <?php if (!empty($partner['whatsapp'])): ?>
        <a class="btn btn-whatsapp btn-block" href="<?= e(wa_link($partner['whatsapp'], 'שלום ' . $partner['name'] . ', מצאתי אתכם באתר ' . load_data()['settings']['agency_name'])) ?>" target="_blank" rel="noopener">וואטסאפ</a>
      <?php endif; ?>
      <?php if (!empty($partner['website'])): ?>
        <a class="btn btn-outline btn-block" href="<?= e($partner['website']) ?>" target="_blank" rel="noopener">אתר אינטרנט</a>
      <?php endif; ?>
    </div>

    <?php
      $leadSource = 'partner';
      $leadPartnerId = $partner['id'];
      $leadServiceOptions = $partner['services'] ?? [];
      $leadHeading = 'מעוניינים לקבל פרטים נוספים?';
      include __DIR__ . '/includes/lead-form.php';
    ?>
  </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
