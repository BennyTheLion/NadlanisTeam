<?php
/** partial — מצפה למשתנה $p (מערך נכס) */
$agent = find_agent((int) ($p['agent_id'] ?? 0));
$cover = $p['images'][0] ?? '';
$dealLabels = deal_types();
$statusLabels = status_labels();
$initials = $agent ? mb_substr($agent['name'], 0, 1) : '?';
$cardBackPath = safe_internal_path($_SERVER['REQUEST_URI'] ?? null, url('properties.php'));
?>
<a class="property-card" href="<?= e(url('property.php?id=' . $p['id']) . '&back=' . rawurlencode($cardBackPath)) ?>">
  <div class="property-card-media">
    <img class="property-card-img" src="<?= e(media_url($cover)) ?>" alt="<?= e($p['title']) ?>" loading="lazy" width="400" height="300">
    <div class="property-card-badges">
      <span class="badge"><?= e($dealLabels[$p['deal']] ?? '') ?></span>
      <?php if (($p['status'] ?? 'available') !== 'available'): ?>
        <span class="badge badge-status" data-status="<?= e($p['status']) ?>"><?= e($statusLabels[$p['status']] ?? '') ?></span>
      <?php endif; ?>
    </div>
    <div class="property-card-price">
      <?= e($p['deal'] === 'rent' ? money((float) $p['price']) . ' / חודש' : money_short((float) $p['price'])) ?>
    </div>
  </div>
  <div class="property-card-body">
    <p class="property-card-title"><?= e($p['title']) ?></p>
    <p class="property-card-loc">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.6 7-12a7 7 0 1 0-14 0c0 5.4 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/></svg>
      <?= e($p['city']) ?><?= $p['neighborhood'] ? ' · ' . e($p['neighborhood']) : '' ?>
    </p>
    <div class="chip-row property-card-chips">
      <span class="chip"><?= e(rtrim(rtrim(number_format((float) $p['rooms'], 1), '0'), '.')) ?> חדרים</span>
      <span class="chip"><?= e((string) $p['size']) ?> מ״ר</span>
      <?php if (!empty($p['floor'])): ?><span class="chip">קומה <?= e((string) $p['floor']) ?></span><?php endif; ?>
    </div>
    <?php if ($agent): ?>
    <div class="property-card-footer">
      <?php if (!empty($agent['photo'])): ?>
        <img class="avatar" src="<?= e(media_url($agent['photo'])) ?>" alt="">
      <?php else: ?>
        <span class="avatar-fallback" aria-hidden="true"><?= e($initials) ?></span>
      <?php endif; ?>
      <span class="property-card-agent"><?= e($agent['name']) ?></span>
    </div>
    <?php endif; ?>
  </div>
</a>
