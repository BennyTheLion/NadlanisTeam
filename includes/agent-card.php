<?php
/** partial — מצפה למשתנה $a (מערך סוכן) */
$count = agent_listing_count((int) $a['id']);
$initials = mb_substr($a['name'], 0, 1);
?>
<article class="agent-card">
  <?php if (!empty($a['photo'])): ?>
    <img class="avatar" src="<?= e(media_url($a['photo'])) ?>" alt="">
  <?php else: ?>
    <span class="avatar-fallback" aria-hidden="true"><?= e($initials) ?></span>
  <?php endif; ?>
  <h3><a href="<?= e(url('agent.php?id=' . $a['id'])) ?>"><?= e($a['name']) ?></a></h3>
  <p class="agent-role"><?= e($a['role']) ?></p>
  <p class="agent-count"><?= e((string) $count) ?> נכסים פעילים</p>
  <div class="agent-actions">
    <a class="btn btn-primary btn-sm" href="<?= e(tel_link($a['phone'])) ?>">התקשרו</a>
    <a class="btn btn-whatsapp btn-sm" href="<?= e(wa_link($a['whatsapp'], 'שלום ' . $a['name'] . ', אשמח לדבר')) ?>" target="_blank" rel="noopener">וואטסאפ</a>
  </div>
</article>
