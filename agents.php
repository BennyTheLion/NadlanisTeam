<?php
require __DIR__ . '/includes/config.php';

$settings = load_data()['settings'];
$pageTitle = 'הצוות שלנו — ' . $settings['agency_name'];
$pageDescription = 'הכירו את סוכני נדלניס טים בנתניה — ליווי אישי בקנייה, מכירה והשקעות נדל״ן.';
require __DIR__ . '/includes/header.php';

$agents = all_agents(true);
?>

<div class="page-head">
  <div class="container">
    <p class="breadcrumbs"><a href="<?= e(url('index.php')) ?>">בית</a> / הצוות</p>
    <h1>הצוות שלנו</h1>
  </div>
</div>

<div class="container section">
  <p class="lede" style="margin-bottom: var(--space-3);">כל סוכן בצוות נדלניס טים מכיר לעומק את השכונות שהוא מלווה — מהמחירים ועד לקצב השוק בכל רחוב. תבחרו את מי שמתאים לכם, או השאירו פרטים ונתאים לכם סוכן בעצמנו.</p>

  <?php if ($agents): ?>
    <div class="agent-grid">
      <?php foreach ($agents as $a): include __DIR__ . '/includes/agent-card.php'; endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <svg class="roofline-mark" viewBox="0 0 1000 260" aria-hidden="true"><use href="#roofline-path"></use></svg>
      <p>עדיין אין סוכנים להצגה.</p>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
