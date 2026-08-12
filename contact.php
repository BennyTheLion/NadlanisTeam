<?php
require __DIR__ . '/includes/config.php';

$settings = load_data()['settings'];
$pageTitle = 'צור קשר — ' . $settings['agency_name'];
$pageDescription = 'צרו קשר עם נדלניס טים — טלפון, וואטסאפ, אימייל וטופס פנייה. נחזור אליכם עוד היום.';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div class="container">
    <p class="breadcrumbs"><a href="<?= e(url('index.php')) ?>">בית</a> / צור קשר</p>
    <h1>צרו קשר</h1>
  </div>
</div>

<div class="container section">
  <div class="contact-grid">
    <div class="filter-card">
      <?php
        $leadSource = 'contact';
        $leadHeading = 'השאירו פרטים ונחזור אליכם';
        include __DIR__ . '/includes/lead-form.php';
      ?>
    </div>

    <div>
      <p class="lede" style="margin-bottom: var(--space-2);">מעדיפים לדבר ישר? אנחנו כאן — בטלפון, בוואטסאפ או באימייל. נשמח לענות על כל שאלה.</p>

      <div class="contact-methods-list">
        <a class="contact-method-row" href="<?= e(tel_link($settings['phone'])) ?>">
          <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.5.6.6 0 1 .4 1 1V20.5c0 .6-.4 1-1 1C10.5 21.5 2.5 13.5 2.5 3.9c0-.6.4-1 1-1H7c.6 0 1 .4 1 1 0 1.2.2 2.4.6 3.5.1.4 0 .8-.3 1.1l-2.2 2.2z"/></svg>
          <span><?= e($settings['phone']) ?></span>
        </a>
        <a class="contact-method-row" href="<?= e(wa_link($settings['whatsapp'])) ?>" target="_blank" rel="noopener">
          <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5.1-1.3A10 10 0 1 0 12 2Zm5.7 14.2c-.2.7-1.4 1.3-2 1.4-.5.1-1.1.1-1.8-.1-.4-.1-1-.3-1.6-.6-2.9-1.3-4.8-4.2-5-4.4-.1-.2-1.2-1.6-1.2-3.1s.8-2.2 1.1-2.5c.3-.3.6-.4.8-.4h.6c.2 0 .4 0 .6.5.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.1.2-.1.3-.3.5l-.4.5c-.1.2-.3.4-.1.7.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.4 2.4 1.5.3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.5.7 1.8.8.3.1.5.2.5.3.1.3.1.7-.1 1.1Z"/></svg>
          <span>וואטסאפ</span>
        </a>
        <a class="contact-method-row" href="mailto:<?= e($settings['email']) ?>">
          <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm1.4 2 7.1 6.1a1 1 0 0 0 1.3 0L20 7"/></svg>
          <span><?= e($settings['email']) ?></span>
        </a>
        <div class="contact-method-row" style="cursor:default;">
          <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5Z"/></svg>
          <span><?= e($settings['address']) ?></span>
        </div>
      </div>

      <table class="hours-table">
        <tr><td>א׳–ה׳</td><td>9:00–19:00</td></tr>
        <tr><td>ו׳</td><td>9:00–13:00</td></tr>
      </table>

      <div class="map-frame">
        <iframe src="https://www.google.com/maps?q=%D7%A0%D7%AA%D7%A0%D7%99%D7%94%2C%20%D7%9B%D7%99%D7%9B%D7%A8%20%D7%94%D7%A2%D7%A6%D7%9E%D7%90%D7%95%D7%AA&output=embed" loading="lazy" title="מיקום המשרד על המפה" aria-label="מפת גוגל, מרכז נתניה"></iframe>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
