<?php
require __DIR__ . '/includes/config.php';

$id = (int) ($_GET['id'] ?? 0);
$a = $id ? find_agent($id) : null;

if (!$a) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$pageTitle = $a['name'] . ' — סוכן נדל״ן בנתניה | נדלניס טים';
$pageDescription = $a['bio'] ?: ($a['name'] . ', ' . $a['role'] . ' בנדלניס טים.');
$ogImage = media_url($a['photo'] ?? null);
$jsonLd = array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'RealEstateAgent',
    'name' => $a['name'],
    'url' => absolute_url('agent.php?id=' . $a['id']),
    'jobTitle' => $a['role'],
    'description' => $a['bio'] ?: null,
    'telephone' => $a['phone'] ?? null,
    'email' => $a['email'] ?? null,
    'image' => $a['photo'] ? $ogImage : null,
    'areaServed' => $a['areas'] ?? [],
    'knowsLanguage' => $a['languages'] ?? [],
    'worksFor' => ['@type' => 'Organization', 'name' => get_settings()['agency_name']],
]);
require __DIR__ . '/includes/header.php';

$filters = ['deal' => $_GET['deal'] ?? '', 'type' => $_GET['type'] ?? ''];
$listings = filter_properties(agent_properties((int) $a['id']), $filters);
$listings = sort_properties($listings, 'newest');
$initials = mb_substr($a['name'], 0, 1);
?>

<div class="agent-header">
  <?php if (!empty($a['photo'])): ?>
    <img class="avatar" src="<?= e(media_url($a['photo'])) ?>" alt="">
  <?php else: ?>
    <span class="avatar-fallback" aria-hidden="true"><?= e($initials) ?></span>
  <?php endif; ?>
  <div>
    <h1><?= e($a['name']) ?></h1>
    <p class="lede" style="margin-inline:auto;"><?= e($a['role']) ?></p>
  </div>
  <?php if ($a['bio']): ?><p class="lede" style="margin-inline:auto; max-width:60ch;"><?= e($a['bio']) ?></p><?php endif; ?>
  <div class="agent-areas">
    <?php foreach ($a['areas'] as $area): ?><span class="chip"><?= e($area) ?></span><?php endforeach; ?>
    <?php foreach ($a['languages'] as $lang): ?><span class="chip"><?= e($lang) ?></span><?php endforeach; ?>
  </div>
  <div class="hero-cta" style="margin:0;">
    <a class="btn btn-primary" href="<?= e(tel_link($a['phone'])) ?>">התקשרו ל<?= e($a['name']) ?></a>
    <a class="btn btn-whatsapp" href="<?= e(wa_link($a['whatsapp'], 'שלום ' . $a['name'] . ', אשמח לדבר')) ?>" target="_blank" rel="noopener">וואטסאפ</a>
  </div>
</div>

<div class="container section">
  <div class="roof-rule" aria-hidden="true"><svg viewBox="0 0 1000 260"><use href="#roofline-path"></use></svg></div>
  <h2 class="section-title">הנכסים של <?= e($a['name']) ?></h2>

  <?php if (agent_properties((int) $a['id'])): ?>
    <form method="get" action="<?= e(url('agent.php')) ?>" class="filter-card">
      <input type="hidden" name="id" value="<?= e((string) $a['id']) ?>">
      <div class="search-tabs" role="radiogroup" aria-label="סוג עסקה">
        <input class="search-tab-input" type="radio" id="a-deal-all" name="deal" value="" <?= $filters['deal'] === '' ? 'checked' : '' ?>>
        <label class="search-tab" for="a-deal-all">הכל</label>
        <input class="search-tab-input" type="radio" id="a-deal-sale" name="deal" value="sale" <?= $filters['deal'] === 'sale' ? 'checked' : '' ?>>
        <label class="search-tab" for="a-deal-sale">למכירה</label>
        <input class="search-tab-input" type="radio" id="a-deal-rent" name="deal" value="rent" <?= $filters['deal'] === 'rent' ? 'checked' : '' ?>>
        <label class="search-tab" for="a-deal-rent">להשכרה</label>
      </div>
      <div class="filter-row" style="grid-template-columns: 1fr auto;">
        <div class="field" style="margin-bottom:0;">
          <label for="a-type">סוג נכס</label>
          <select class="input" id="a-type" name="type">
            <option value="">כל הסוגים</option>
            <?php foreach (property_types() as $t): ?>
              <option value="<?= e($t) ?>" <?= $filters['type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">סינון</button>
      </div>
    </form>
  <?php endif; ?>

  <?php if ($listings): ?>
    <div class="property-grid">
      <?php foreach ($listings as $p): include __DIR__ . '/includes/property-card.php'; endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <svg class="roofline-mark" viewBox="0 0 1000 260" aria-hidden="true"><use href="#roofline-path"></use></svg>
      <p><?= agent_properties((int) $a['id']) ? 'לא נמצאו נכסים תואמים לסינון אצל ' . e($a['name']) . '.' : 'אין כרגע נכסים פעילים אצל ' . e($a['name']) . '.' ?></p>
      <a href="<?= e(url('properties.php')) ?>" class="btn btn-outline">כל הנכסים</a>
    </div>
  <?php endif; ?>
</div>

<div class="container section section-alt">
  <div style="max-width:520px; margin-inline:auto;">
    <?php
      $leadSource = 'agent';
      $leadAgentId = $a['id'];
      $leadHeading = 'השאירו פרטים ו' . $a['name'] . ' יחזרו אליכם';
      include __DIR__ . '/includes/lead-form.php';
    ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
