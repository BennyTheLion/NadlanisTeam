<?php
/**
 * partial — טופס פנייה. משתנים אופציונליים לפני ה-include:
 * $leadSource ('property'|'agent'|'partner'|'contact'|'home'), $leadPropertyId, $leadAgentId,
 * $leadPartnerId, $leadServiceOptions (מערך — אם קיים, מרנדר select "סוג השירות"), $leadHeading
 */
$leadSource = $leadSource ?? 'contact';
$leadPropertyId = $leadPropertyId ?? null;
$leadAgentId = $leadAgentId ?? null;
$leadPartnerId = $leadPartnerId ?? null;
$leadServiceOptions = $leadServiceOptions ?? [];
$leadHeading = $leadHeading ?? 'השאירו פרטים ונחזור אליכם';

$prefill = $_SESSION['lead_prefill'] ?? [];
$errors = $_SESSION['lead_errors'] ?? [];
unset($_SESSION['lead_prefill'], $_SESSION['lead_errors']);
$justSent = isset($_GET['sent']) && $_GET['sent'] === '1';
?>
<div class="lead-form">
  <?php if ($justSent): ?>
    <p class="alert-box alert-success">תודה! קיבלנו את הפנייה ונחזור אליכם עוד היום.</p>
  <?php else: ?>
    <?php if (!empty($errors['_general'])): ?>
      <p class="alert-box alert-error"><?= e($errors['_general']) ?></p>
    <?php endif; ?>
    <h3><?= e($leadHeading) ?></h3>
    <form method="post" action="<?= e(url('lead-submit.php')) ?>" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI'] ?? url('contact.php')) ?>">
      <input type="hidden" name="source" value="<?= e($leadSource) ?>">
      <?php if ($leadPropertyId): ?><input type="hidden" name="property_id" value="<?= e((string) $leadPropertyId) ?>"><?php endif; ?>
      <?php if ($leadAgentId): ?><input type="hidden" name="agent_id" value="<?= e((string) $leadAgentId) ?>"><?php endif; ?>
      <?php if ($leadPartnerId): ?><input type="hidden" name="partner_id" value="<?= e((string) $leadPartnerId) ?>"><?php endif; ?>
      <div class="honeypot" aria-hidden="true">
        <label for="website_<?= e($leadSource) ?>">אל תמלאו שדה זה</label>
        <input type="text" id="website_<?= e($leadSource) ?>" name="website" tabindex="-1" autocomplete="off">
      </div>

      <div class="field <?= isset($errors['name']) ? 'has-error' : '' ?>">
        <label for="name_<?= e($leadSource) ?>">שם מלא *</label>
        <input class="input" type="text" id="name_<?= e($leadSource) ?>" name="name" required value="<?= e($prefill['name'] ?? '') ?>">
        <?php if (isset($errors['name'])): ?><span class="error"><?= e($errors['name']) ?></span><?php endif; ?>
      </div>

      <div class="field <?= isset($errors['phone']) ? 'has-error' : '' ?>">
        <label for="phone_<?= e($leadSource) ?>">טלפון *</label>
        <input class="input" type="tel" id="phone_<?= e($leadSource) ?>" name="phone" required value="<?= e($prefill['phone'] ?? '') ?>">
        <?php if (isset($errors['phone'])): ?><span class="error"><?= e($errors['phone']) ?></span><?php endif; ?>
      </div>

      <div class="field">
        <label for="email_<?= e($leadSource) ?>">אימייל</label>
        <input class="input" type="email" id="email_<?= e($leadSource) ?>" name="email" value="<?= e($prefill['email'] ?? '') ?>">
      </div>

      <?php if ($leadServiceOptions): ?>
        <div class="field">
          <label for="service_<?= e($leadSource) ?>">סוג השירות</label>
          <select class="input" id="service_<?= e($leadSource) ?>" name="service">
            <option value="">בחרו שירות (אופציונלי)</option>
            <?php foreach ($leadServiceOptions as $service): ?>
              <option value="<?= e($service) ?>" <?= ($prefill['service'] ?? '') === $service ? 'selected' : '' ?>><?= e($service) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <div class="field">
        <label for="message_<?= e($leadSource) ?>">הודעה</label>
        <textarea class="input" id="message_<?= e($leadSource) ?>" name="message"><?= e($prefill['message'] ?? '') ?></textarea>
      </div>

      <label class="checkbox-row">
        <input type="checkbox" name="consent" required>
        <span>אני מאשר/ת יצירת קשר</span>
      </label>

      <p style="font-size:0.85rem; color:var(--ink-3); margin-top:6px;">
        מסירת הפרטים אינה חובה שבדין והיא לצורך יצירת קשר בעניין פנייה זו בלבד — ראו
        <a href="<?= e(url('privacy.php') . '#section-11') ?>">מדיניות הפרטיות</a>.
      </p>

      <button type="submit" class="btn btn-primary btn-block" style="margin-top:16px;">שליחת פנייה</button>
    </form>
  <?php endif; ?>
</div>
