<?php
$settings = load_data()['settings'];
$showActionBar = $showActionBar ?? true;
$actionBarProperty = $actionBarProperty ?? null;
?>
</main>

<footer class="site-footer">
  <div class="container footer-inner">
    <div class="footer-brand">
      <p class="footer-wordmark" dir="ltr"><span class="fw-ink">Nadlanis</span><span class="fw-blue">Team</span></p>
      <p class="footer-tagline"><?= e($settings['tagline']) ?></p>
    </div>

    <nav class="footer-nav">
      <a href="<?= e(url('index.php')) ?>">בית</a>
      <a href="<?= e(url('properties.php')) ?>">נכסים</a>
      <a href="<?= e(url('mortgage-calculator.php')) ?>">מחשבון משכנתא</a>
      <a href="<?= e(url('agents.php')) ?>">הצוות</a>
      <a href="<?= e(url('partners.php')) ?>">שותפים</a>
      <a href="<?= e(url('about.php')) ?>">אודות</a>
      <a href="<?= e(url('contact.php')) ?>">צור קשר</a>
    </nav>

    <div class="footer-contact">
      <a href="<?= e(tel_link($settings['phone'])) ?>"><?= e($settings['phone']) ?></a>
      <a href="<?= e(wa_link($settings['whatsapp'])) ?>" target="_blank" rel="noopener">וואטסאפ</a>
      <a href="mailto:<?= e($settings['email']) ?>"><?= e($settings['email']) ?></a>
      <span><?= e($settings['address']) ?></span>
    </div>

    <?php if (!empty($settings['facebook']) || !empty($settings['instagram'])): ?>
    <div class="footer-social">
      <?php if (!empty($settings['instagram'])): ?>
        <a href="<?= e($settings['instagram']) ?>" target="_blank" rel="noopener" aria-label="אינסטגרם">
          <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg>
        </a>
      <?php endif; ?>
      <?php if (!empty($settings['facebook'])): ?>
        <a href="<?= e($settings['facebook']) ?>" target="_blank" rel="noopener" aria-label="פייסבוק">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 9h3V6h-3c-2 0-3.5 1.5-3.5 3.5V12H8v3h2.5v6h3v-6H16l.5-3h-3V9.8c0-.4.3-.8.5-.8Z"/></svg>
        </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <div class="footer-bottom container">
    <span>© <?= date('Y') ?> <?= e($settings['agency_name']) ?>. כל הזכויות שמורות.</span>
    <nav class="footer-legal">
      <a href="<?= e(url('privacy.php')) ?>">מדיניות פרטיות</a>
      <a href="<?= e(url('terms.php')) ?>">תנאי שימוש</a>
      <a href="<?= e(url('admin/login.php')) ?>">כניסת מנהל</a>
    </nav>
  </div>
</footer>

<?php if ($showActionBar): ?>
<div class="action-bar<?= $actionBarProperty ? ' has-price' : '' ?>">
  <?php if ($actionBarProperty): ?>
    <div class="action-bar-price">
      <small><?= $actionBarProperty['deal'] === 'rent' ? 'לחודש' : 'מחיר' ?></small>
      <?= e($actionBarProperty['deal'] === 'rent' ? money($actionBarProperty['price']) : money_short($actionBarProperty['price'])) ?>
    </div>
  <?php endif; ?>
  <a href="<?= e(tel_link($settings['phone'])) ?>">
    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true" style="width:18px;height:18px;fill:currentColor;"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.5.6.6 0 1 .4 1 1V20.5c0 .6-.4 1-1 1C10.5 21.5 2.5 13.5 2.5 3.9c0-.6.4-1 1-1H7c.6 0 1 .4 1 1 0 1.2.2 2.4.6 3.5.1.4 0 .8-.3 1.1l-2.2 2.2z"/></svg>
    <span>התקשרו</span>
  </a>
  <a class="ab-whatsapp" href="<?= e(wa_link($settings['whatsapp'], $actionBarProperty ? ('שלום, אשמח לפרטים על הנכס: ' . $actionBarProperty['title'] . ' (#' . $actionBarProperty['id'] . ')') : '')) ?>" target="_blank" rel="noopener">
    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true" style="width:18px;height:18px;fill:currentColor;"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5.1-1.3A10 10 0 1 0 12 2Zm5.7 14.2c-.2.7-1.4 1.3-2 1.4-.5.1-1.1.1-1.8-.1-.4-.1-1-.3-1.6-.6-2.9-1.3-4.8-4.2-5-4.4-.1-.2-1.2-1.6-1.2-3.1s.8-2.2 1.1-2.5c.3-.3.6-.4.8-.4h.6c.2 0 .4 0 .6.5.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.1.2-.1.3-.3.5l-.4.5c-.1.2-.3.4-.1.7.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.4 2.4 1.5.3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.5.7 1.8.8.3.1.5.2.5.3.1.3.1.7-.1 1.1Z"/></svg>
    <span>וואטסאפ</span>
  </a>
</div>
<?php endif; ?>

<script src="<?= e(asset_url('assets/js/main.js')) ?>"></script>
</body>
</html>
