<?php
if (!function_exists('e')) {
    require __DIR__ . '/includes/config.php';
}
http_response_code(404);
$pageTitle = 'הדף לא נמצא — נדלניס טים';
$pageDescription = 'הדף המבוקש לא נמצא באתר נדלניס טים. חזרו לעמוד הבית או המשיכו לצפות בנכסים.';
$robotsMeta = 'noindex, follow';
require __DIR__ . '/includes/header.php';
?>
<div class="container error-page">
  <svg class="roofline-mark" viewBox="0 0 1000 260" aria-hidden="true"><use href="#roofline-path"></use></svg>
  <h1>הדף שחיפשתם לא נמצא</h1>
  <p class="lede" style="margin-inline:auto;">ייתכן שהקישור שגוי או שהנכס כבר לא זמין.</p>
  <div class="hero-cta" style="justify-content:center; margin-top:var(--space-3);">
    <a href="<?= e(url('index.php')) ?>" class="btn btn-primary">חזרה לעמוד הבית</a>
    <a href="<?= e(url('properties.php')) ?>" class="btn btn-outline">כל הנכסים</a>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
