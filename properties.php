<?php
require __DIR__ . '/includes/config.php';

$filters = [
    'deal' => $_GET['deal'] ?? '',
    'city' => $_GET['city'] ?? '',
    'type' => $_GET['type'] ?? '',
    'q' => $_GET['q'] ?? '',
    'rooms_min' => $_GET['rooms_min'] ?? '',
    'rooms_max' => $_GET['rooms_max'] ?? '',
    'price_min' => $_GET['price_min'] ?? '',
    'price_max' => $_GET['price_max'] ?? '',
    'agent' => $_GET['agent'] ?? '',
    'status' => $_GET['status'] ?? '',
];
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;

$results = filter_properties(all_properties(true), $filters);
$results = sort_properties($results, $sort);
$total = count($results);
$totalPages = max(1, (int) ceil($total / $perPage));
$page = min($page, $totalPages);
$pageResults = array_slice($results, ($page - 1) * $perPage, $perPage);

function qs(array $overrides = []): string
{
    $params = array_filter(array_merge($_GET, $overrides), fn($v) => $v !== '' && $v !== null);
    return http_build_query($params);
}

$dealLabels = deal_types();
$cities = cities_in_use();
$agentsList = all_agents(true);

$dealLabel = $filters['deal'] ? $dealLabels[$filters['deal']] : '';
$h1 = 'נכסים' . ($dealLabel ? ' ' . $dealLabel : '') . ($filters['city'] ? ' ב' . $filters['city'] : ' בנתניה');

$pageTitle = $h1 . ' — נדלניס טים';
$pageDescription = 'חיפוש נכסים ' . ($dealLabel ?: 'למכירה ולהשכרה') . ' בנתניה — דירות, בתים פרטיים והשקעות נדל״ן.';

$mapProperties = [];
foreach ($results as $p) {
    if (!isset($p['lat'], $p['lng']) || $p['lat'] === '' || $p['lng'] === '') {
        continue;
    }
    $mapProperties[] = [
        'id' => $p['id'],
        'title' => $p['title'],
        'price' => (float) $p['price'],
        'priceLabel' => $p['deal'] === 'rent' ? money((float) $p['price']) . ' / חודש' : money((float) $p['price']),
        'priceShort' => money_short((float) $p['price']),
        'deal' => $p['deal'],
        'dealLabel' => $dealLabels[$p['deal']] ?? '',
        'type' => $p['type'],
        'rooms' => $p['rooms'],
        'size' => $p['size'],
        'city' => $p['city'],
        'neighborhood' => $p['neighborhood'],
        'lat' => (float) $p['lat'],
        'lng' => (float) $p['lng'],
        'image' => media_url($p['images'][0] ?? null),
        'url' => url('property.php?id=' . $p['id']),
    ];
}

if ($pageResults) {
    $listItems = [];
    foreach ($pageResults as $i => $p) {
        $listItems[] = [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'url' => absolute_url('property.php?id=' . $p['id']),
            'name' => $p['title'],
        ];
    }
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'itemListElement' => $listItems,
    ];
}

require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">

<div class="page-head">
  <div class="container">
    <p class="breadcrumbs"><a href="<?= e(url('index.php')) ?>">בית</a> / נכסים</p>
    <h1><?= e($h1) ?></h1>
  </div>
</div>

<div class="container">
  <form class="filter-card" method="get" action="<?= e(url('properties.php')) ?>">
    <div class="search-tabs" role="radiogroup" aria-label="סוג עסקה">
      <input class="search-tab-input" type="radio" id="f-deal-all" name="deal" value="" <?= $filters['deal'] === '' ? 'checked' : '' ?>>
      <label class="search-tab" for="f-deal-all">הכל</label>
      <input class="search-tab-input" type="radio" id="f-deal-sale" name="deal" value="sale" <?= $filters['deal'] === 'sale' ? 'checked' : '' ?>>
      <label class="search-tab" for="f-deal-sale">למכירה</label>
      <input class="search-tab-input" type="radio" id="f-deal-rent" name="deal" value="rent" <?= $filters['deal'] === 'rent' ? 'checked' : '' ?>>
      <label class="search-tab" for="f-deal-rent">להשכרה</label>
    </div>

    <div class="filter-row">
      <div class="field" style="margin-bottom:0;">
        <label for="f-city">עיר / שכונה</label>
        <select class="input" id="f-city" name="city">
          <option value="">כל האזורים</option>
          <?php foreach ($cities as $c): ?>
            <option value="<?= e($c) ?>" <?= $filters['city'] === $c ? 'selected' : '' ?>>נתניה — <?= e($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="margin-bottom:0;">
        <label for="f-type">סוג נכס</label>
        <select class="input" id="f-type" name="type">
          <option value="">כל הסוגים</option>
          <?php foreach (property_types() as $t): ?>
            <option value="<?= e($t) ?>" <?= $filters['type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="margin-bottom:0;">
        <label for="f-q">חיפוש חופשי</label>
        <input class="input" type="text" id="f-q" name="q" value="<?= e($filters['q']) ?>" placeholder="שכונה, כתובת...">
      </div>
      <button type="submit" class="btn btn-primary">חיפוש</button>
    </div>

    <button type="button" class="filter-toggle" data-filter-toggle aria-expanded="false">סינון מתקדם</button>
    <div class="filter-advanced" data-filter-advanced>
      <div class="field" style="margin-bottom:0;">
        <label for="f-rooms-min">חדרים (מ־)</label>
        <select class="input" id="f-rooms-min" name="rooms_min">
          <option value="">הכל</option>
          <?php foreach ([1, 2, 3, 4, 5, 6] as $r): ?>
            <option value="<?= $r ?>" <?= $filters['rooms_min'] == $r ? 'selected' : '' ?>><?= $r ?>+</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="margin-bottom:0;">
        <label for="f-rooms-max">חדרים (עד)</label>
        <select class="input" id="f-rooms-max" name="rooms_max">
          <option value="">הכל</option>
          <?php foreach ([2, 3, 4, 5, 6, 7] as $r): ?>
            <option value="<?= $r ?>" <?= $filters['rooms_max'] == $r ? 'selected' : '' ?>><?= $r ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="margin-bottom:0;">
        <label for="f-price-min">מחיר מ־ (₪)</label>
        <input class="input" type="number" min="0" step="1000" id="f-price-min" name="price_min" value="<?= e($filters['price_min']) ?>">
      </div>
      <div class="field" style="margin-bottom:0;">
        <label for="f-price-max">מחיר עד (₪)</label>
        <input class="input" type="number" min="0" step="1000" id="f-price-max" name="price_max" value="<?= e($filters['price_max']) ?>">
      </div>
      <div class="field" style="margin-bottom:0;">
        <label for="f-agent">סוכן</label>
        <select class="input" id="f-agent" name="agent">
          <option value="">כל הסוכנים</option>
          <?php foreach ($agentsList as $a): ?>
            <option value="<?= e((string) $a['id']) ?>" <?= (string) $filters['agent'] === (string) $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="margin-bottom:0;">
        <label for="f-status">סטטוס</label>
        <select class="input" id="f-status" name="status">
          <option value="">הכל</option>
          <?php foreach (['available', 'under_contract', 'sold'] as $s): ?>
            <option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(status_labels()[$s]) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </form>

  <div class="results-bar">
    <p class="results-count">נמצאו <?= e((string) $total) ?> נכסים</p>
    <form method="get" action="<?= e(url('properties.php')) ?>">
      <?php foreach ($_GET as $k => $v): if ($k === 'sort' || $k === 'page') continue; ?>
        <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
      <?php endforeach; ?>
      <label for="f-sort" class="small">מיון:</label>
      <select class="input" id="f-sort" name="sort" data-sort-submit style="display:inline-block;width:auto;min-height:38px;">
        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>החדשים ביותר</option>
        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>מחיר: מהנמוך לגבוה</option>
        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>מחיר: מהגבוה לנמוך</option>
        <option value="rooms_desc" <?= $sort === 'rooms_desc' ? 'selected' : '' ?>>הכי הרבה חדרים</option>
        <option value="size_desc" <?= $sort === 'size_desc' ? 'selected' : '' ?>>הכי גדולים (מ״ר)</option>
      </select>
    </form>
  </div>

  <div class="view-toggle" data-view-toggle role="group" aria-label="תצוגת תוצאות">
    <button type="button" data-view-btn="list" aria-pressed="true">רשימה</button>
    <button type="button" data-view-btn="map" aria-pressed="false">מפה</button>
  </div>

  <?php if ($pageResults): ?>
    <div data-view-panel="list">
      <div class="property-grid">
        <?php foreach ($pageResults as $p): include __DIR__ . '/includes/property-card.php'; endforeach; ?>
      </div>

      <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="ניווט עמודים">
          <?php if ($page > 1): ?><a href="?<?= e(qs(['page' => $page - 1])) ?>" aria-label="עמוד קודם">‹</a><?php endif; ?>
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i === $page): ?>
              <span class="is-current" aria-current="page"><?= $i ?></span>
            <?php else: ?>
              <a href="?<?= e(qs(['page' => $i])) ?>"><?= $i ?></a>
            <?php endif; ?>
          <?php endfor; ?>
          <?php if ($page < $totalPages): ?><a href="?<?= e(qs(['page' => $page + 1])) ?>" aria-label="עמוד הבא">›</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <svg class="roofline-mark" viewBox="0 0 1000 260" aria-hidden="true"><use href="#roofline-path"></use></svg>
      <p>לא נמצאו נכסים שתואמים לחיפוש</p>
      <a href="<?= e(url('properties.php')) ?>" class="btn btn-outline">נקו את הסינון</a>
    </div>
  <?php endif; ?>

  <div class="map-view" data-view-panel="map" hidden>
    <div class="map-view-list" id="mapPropertyList">
      <?php foreach ($mapProperties as $mp): ?>
        <button type="button" class="map-list-item" data-property-id="<?= e((string) $mp['id']) ?>">
          <img src="<?= e($mp['image']) ?>" alt="" loading="lazy">
          <span class="map-list-item-body">
            <h3><?= e($mp['title']) ?></h3>
            <p><?= e($mp['city']) ?><?= $mp['neighborhood'] ? ' · ' . e($mp['neighborhood']) : '' ?></p>
            <span class="map-list-price"><?= e($mp['priceLabel']) ?></span>
          </span>
        </button>
      <?php endforeach; ?>
      <?php if (!$mapProperties): ?>
        <p class="empty-note">לאף אחד מהנכסים בתוצאה זו אין עדיין קואורדינטות להצגה במפה.</p>
      <?php endif; ?>
    </div>
    <div class="map-view-map" id="propertyMap" aria-label="מפת נכסים"></div>
  </div>
</div>

<script type="application/json" id="propertiesMapData"><?= json_encode($mapProperties, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script src="<?= e(asset_url('assets/js/properties-map.js')) ?>" defer></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
