<?php
require __DIR__ . '/includes/config.php';

header('Content-Type: application/xml; charset=UTF-8');

$urls = [
    ['loc' => url('index.php'), 'priority' => '1.0'],
    ['loc' => url('properties.php'), 'priority' => '0.9'],
    ['loc' => url('mortgage-calculator.php'), 'priority' => '0.6'],
    ['loc' => url('agents.php'), 'priority' => '0.7'],
    ['loc' => url('partners.php'), 'priority' => '0.6'],
    ['loc' => url('about.php'), 'priority' => '0.5'],
    ['loc' => url('contact.php'), 'priority' => '0.5'],
];

foreach (all_properties(true) as $p) {
    $urls[] = [
        'loc' => url('property.php?id=' . $p['id']),
        'priority' => '0.8',
        'lastmod' => substr($p['created_at'] ?? '', 0, 10) ?: null,
    ];
}

foreach (all_agents(true) as $a) {
    $urls[] = ['loc' => url('agent.php?id=' . $a['id']), 'priority' => '0.6'];
}

foreach (all_partners(true) as $p) {
    $urls[] = ['loc' => url('partner.php?id=' . $p['id']), 'priority' => '0.5'];
}

$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? '';

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $u): ?>
  <url>
    <loc><?= e($scheme . $host . $u['loc']) ?></loc>
    <?php if (!empty($u['lastmod'])): ?><lastmod><?= e($u['lastmod']) ?></lastmod><?php endif; ?>
    <priority><?= e($u['priority']) ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
