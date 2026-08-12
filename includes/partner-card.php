<?php
/** partial — מצפה למשתנה $partner (מערך שותף) */
$cat = partner_categories()[$partner['category']] ?? ['label' => $partner['category'], 'icon' => '🔗'];
$initials = mb_substr($partner['name'], 0, 1);
$regionsShown = array_slice($partner['regions'] ?? [], 0, 2);
$regionsExtra = count($partner['regions'] ?? []) - count($regionsShown);
?>
<article class="partner-card">
  <?php if (!empty($partner['featured'])): ?>
    <span class="partner-badge-featured">✓ שותף מומלץ</span>
  <?php endif; ?>

  <div class="partner-card-logo-wrap">
    <?php if (!empty($partner['logo'])): ?>
      <img class="partner-logo" src="<?= e(media_url($partner['logo'])) ?>" alt="">
    <?php else: ?>
      <span class="partner-logo-fallback" aria-hidden="true"><?= e($initials) ?></span>
    <?php endif; ?>
  </div>

  <h3 class="partner-card-name"><a href="<?= e(url('partner.php?id=' . $partner['id'])) ?>"><?= e($partner['name']) ?></a></h3>
  <p class="partner-card-category"><?= $cat['icon'] ?> <?= e($cat['label']) ?></p>

  <div class="partner-card-meta">
    <?php if ($regionsShown): ?>
      <span>📍 <?= e(implode(', ', $regionsShown)) ?><?= $regionsExtra > 0 ? ' ועוד' : '' ?></span>
    <?php endif; ?>
    <?php if (!empty($partner['rating'])): ?>
      <span>★ <?= e(number_format((float) $partner['rating'], 1)) ?></span>
    <?php endif; ?>
    <?php if (!empty($partner['verified'])): ?>
      <span class="partner-verified">✓ מאומת</span>
    <?php endif; ?>
  </div>

  <?php if (!empty($partner['description_short'])): ?>
    <p class="partner-card-desc"><?= e($partner['description_short']) ?></p>
  <?php endif; ?>

  <div class="partner-card-actions">
    <a class="btn btn-outline btn-sm" href="<?= e(url('partner.php?id=' . $partner['id'])) ?>">לפרופיל</a>
    <a class="btn btn-primary btn-sm" href="<?= e(url('partner.php?id=' . $partner['id'])) ?>#contact">צור קשר</a>
  </div>
</article>
