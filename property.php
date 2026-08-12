<?php
require __DIR__ . '/includes/config.php';

$id = (int) ($_GET['id'] ?? 0);
$p = $id ? find_property($id) : null;

if (!$p || ($p['status'] ?? '') === 'draft') {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$agent = find_agent((int) $p['agent_id']);
$dealLabels = deal_types();
$statusLabels = status_labels();
$images = $p['images'] ?: [];

$pageTitle = $p['title'] . ' — ' . $p['type'] . ' ' . rtrim(rtrim(number_format((float) $p['rooms'], 1), '0'), '.') . ' חדרים ב' . ($p['neighborhood'] ?: $p['city']) . ', ' . $p['city'] . ' | נדלניס טים';
$pageDescription = mb_substr($p['description'], 0, 155);
$ogImage = media_url($images[0] ?? null);
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'RealEstateListing',
    'name' => $p['title'],
    'description' => $p['description'],
    'url' => (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . $_SERVER['REQUEST_URI'],
    'image' => $ogImage,
];
$actionBarProperty = $p;
require __DIR__ . '/includes/header.php';

$similar = array_values(array_filter(all_properties(true), function ($x) use ($p) {
    return (int) $x['id'] !== (int) $p['id']
        && $x['city'] === $p['city']
        && $x['deal'] === $p['deal']
        && abs((float) $x['price'] - (float) $p['price']) <= (float) $p['price'] * 0.3;
}));
$similar = array_slice($similar, 0, 3);

$featureList = [
    'balcony' => 'מרפסת', 'elevator' => 'מעלית', 'mamad' => 'ממ״ד', 'storage' => 'מחסן',
    'renovated' => 'משופצת', 'accessible' => 'נגישות', 'furnished' => 'מרוהטת',
];
$backUrl = safe_internal_path($_GET['back'] ?? null, url('properties.php'));
?>

<div class="container" style="padding-top: var(--space-3);">
  <p class="breadcrumbs"><a href="<?= e(url('index.php')) ?>">בית</a> / <a href="<?= e($backUrl) ?>">נכסים</a> / <?= e($p['title']) ?></p>
</div>

<div class="container property-layout">
  <div>
    <div class="gallery-main">
      <img id="galleryMain" src="<?= e(media_url($images[0] ?? null)) ?>" alt="<?= e($p['title']) ?>" width="900" height="675">
    </div>
    <?php if (count($images) > 1): ?>
      <div class="gallery-thumbs">
        <?php foreach ($images as $i => $img): ?>
          <button type="button" data-thumb data-full="<?= e(media_url($img)) ?>" data-alt="<?= e($p['title']) ?>" aria-current="<?= $i === 0 ? 'true' : 'false' ?>">
            <img src="<?= e(media_url($img)) ?>" alt="">
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="property-title-row">
      <div class="chip-row" style="margin-bottom:10px;">
        <span class="badge"><?= e($dealLabels[$p['deal']]) ?></span>
        <?php if ($p['status'] !== 'available'): ?>
          <span class="badge badge-status" data-status="<?= e($p['status']) ?>"><?= e($statusLabels[$p['status']]) ?></span>
        <?php endif; ?>
      </div>
      <h1><?= e($p['title']) ?></h1>
      <p class="property-address"><?= e($p['city']) ?><?= $p['neighborhood'] ? ' · ' . e($p['neighborhood']) : '' ?><?= $p['address'] ? ' · ' . e($p['address']) : '' ?></p>
      <p class="property-price"><?= e($p['deal'] === 'rent' ? money((float) $p['price']) . ' לחודש' : money((float) $p['price'])) ?></p>
      <?php if ($p['deal'] === 'sale'): ?>
        <a href="<?= e(url('mortgage-calculator.php?price=' . (int) $p['price'])) ?>" class="link-arrow">חשבו החזר משכנתא חודשי משוער <span aria-hidden="true">&larr;</span></a>
      <?php endif; ?>
    </div>

    <div class="spec-grid">
      <div class="spec-tile"><div class="spec-value"><?= e(rtrim(rtrim(number_format((float) $p['rooms'], 1), '0'), '.')) ?></div><div class="spec-label">חדרים</div></div>
      <div class="spec-tile"><div class="spec-value"><?= e((string) $p['size']) ?></div><div class="spec-label">מ״ר</div></div>
      <?php if (!empty($p['floor']) || !empty($p['total_floors'])): ?>
        <div class="spec-tile"><div class="spec-value"><?= e((string) $p['floor']) ?><?= $p['total_floors'] ? ' מתוך ' . e((string) $p['total_floors']) : '' ?></div><div class="spec-label">קומה</div></div>
      <?php endif; ?>
      <div class="spec-tile"><div class="spec-value"><?= e((string) $p['parking']) ?></div><div class="spec-label">חניות</div></div>
      <?php if (!empty($p['plot_size'])): ?>
        <div class="spec-tile"><div class="spec-value"><?= e((string) $p['plot_size']) ?></div><div class="spec-label">מ״ר מגרש</div></div>
      <?php endif; ?>
      <?php if (!empty($p['entry_date'])): ?>
        <div class="spec-tile"><div class="spec-value"><?= e($p['entry_date']) ?></div><div class="spec-label">תאריך כניסה</div></div>
      <?php endif; ?>
    </div>

    <?php
    $activeFeatures = array_filter($featureList, fn($label, $key) => !empty($p[$key]), ARRAY_FILTER_USE_BOTH);
    if ($activeFeatures):
    ?>
      <div class="chip-row">
        <?php foreach ($activeFeatures as $label): ?>
          <span class="feature-pill"><?= e($label) ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <p class="property-desc"><?= e($p['description']) ?></p>

    <?php
    $partnerCtas = [
        'lawyer' => 'צריכים עורך דין לעסקה?',
        'mortgage_advisor' => 'צריכים ייעוץ משכנתא?',
        'appraiser' => 'רוצים הערכת שווי לנכס?',
    ];
    $relevantCtas = array_filter($partnerCtas, fn($cat) => partners_serving_region($p['city'], $cat), ARRAY_FILTER_USE_KEY);
    ?>
    <?php if ($relevantCtas): ?>
      <div class="property-partners-cta">
        <h2 class="section-title" style="font-size:1.1rem;">מעטפת מקצועית לעסקה</h2>
        <div class="chip-row">
          <?php foreach ($relevantCtas as $cat => $label): ?>
            <a class="category-chip" href="<?= e(url('partners.php') . '?' . http_build_query(['category' => $cat, 'region' => $p['city']])) ?>"><?= e($label) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php
    $shareUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . $_SERVER['REQUEST_URI'];
    $shareText = 'תראו את הנכס הזה: ' . $p['title'] . ' — ' . $shareUrl;
    ?>
    <div class="share-row">
      <a class="btn btn-outline btn-sm" href="https://wa.me/?text=<?= rawurlencode($shareText) ?>" target="_blank" rel="noopener">שיתוף הנכס בוואטסאפ</a>
    </div>
  </div>

  <?php if ($agent): ?>
  <aside class="agent-sidebar">
    <div class="agent-sidebar-head">
      <?php if (!empty($agent['photo'])): ?>
        <img class="avatar" style="width:56px;height:56px;" src="<?= e(media_url($agent['photo'])) ?>" alt="">
      <?php else: ?>
        <span class="avatar-fallback" style="width:56px;height:56px;font-size:1.2rem;" aria-hidden="true"><?= e(mb_substr($agent['name'], 0, 1)) ?></span>
      <?php endif; ?>
      <div>
        <h3 style="margin-bottom:2px;"><?= e($agent['name']) ?></h3>
        <p class="small" style="color:var(--ink-3);"><?= e($agent['role']) ?></p>
      </div>
    </div>
    <div class="agent-sidebar-actions">
      <a class="btn btn-primary btn-block" href="<?= e(tel_link($agent['phone'])) ?>">התקשרו ל<?= e($agent['name']) ?></a>
      <a class="btn btn-whatsapp btn-block" href="<?= e(wa_link($agent['whatsapp'], 'שלום ' . $agent['name'] . ', אשמח לפרטים על הנכס: ' . $p['title'] . ' (#' . $p['id'] . ')')) ?>" target="_blank" rel="noopener">וואטסאפ</a>
      <a class="btn btn-outline btn-block" href="<?= e(url('agent.php?id=' . $agent['id'])) ?>">כל הנכסים של <?= e($agent['name']) ?></a>
    </div>
    <?php
      $leadSource = 'property';
      $leadPropertyId = $p['id'];
      $leadAgentId = $agent['id'];
      $leadHeading = 'מתעניינים בנכס הזה?';
      include __DIR__ . '/includes/lead-form.php';
    ?>
  </aside>
  <?php endif; ?>
</div>

<?php if ($similar): ?>
<section class="section section-alt">
  <div class="container">
    <div class="roof-rule" aria-hidden="true"><svg viewBox="0 0 1000 260"><use href="#roofline-path"></use></svg></div>
    <p class="eyebrow">נכסים דומים</p>
    <h2 class="section-title">אולי גם זה יעניין אתכם</h2>
    <div class="property-grid">
      <?php foreach ($similar as $sp): $p_backup = $p; $p = $sp; include __DIR__ . '/includes/property-card.php'; $p = $p_backup; endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
