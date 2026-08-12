<?php
require __DIR__ . '/includes/config.php';

$settings = get_settings();
$pageTitle = 'מחשבון משכנתא — ' . $settings['agency_name'];
$pageDescription = 'חשבו הערכת החזר חודשי למשכנתא לפי מחיר הנכס, הון עצמי, ריבית ותקופת ההלוואה.';
require __DIR__ . '/includes/header.php';

function mortgage_num($key, $default, $min, $max)
{
    if (!isset($_GET[$key]) || $_GET[$key] === '') {
        return $default;
    }
    $v = (float) $_GET[$key];
    if (!is_finite($v) || $v < $min || $v > $max) {
        return null;
    }
    return $v;
}

$hasQuery = isset($_GET['price']) || isset($_GET['down_payment']) || isset($_GET['rate']) || isset($_GET['years']);

$price = mortgage_num('price', 1800000, 100000, 50000000);
$downPayment = mortgage_num('down_payment', 450000, 0, 50000000);
$rate = mortgage_num('rate', 4.8, 0, 15);
$years = mortgage_num('years', 25, 4, 30);

$errors = [];
if ($price === null) {
    $errors['price'] = 'יש להזין מחיר נכס בין 100,000 ל-50,000,000 ₪.';
}
if ($downPayment === null) {
    $errors['down_payment'] = 'יש להזין הון עצמי תקין.';
} elseif ($price !== null && $downPayment >= $price) {
    $errors['down_payment'] = 'ההון העצמי חייב להיות נמוך ממחיר הנכס.';
}
if ($rate === null) {
    $errors['rate'] = 'יש להזין ריבית שנתית בין 0 ל-15 אחוזים.';
}
if ($years === null) {
    $errors['years'] = 'יש להזין תקופת משכנתא בין 4 ל-30 שנים.';
}

$result = null;
if (!$errors) {
    $loanAmount = $price - $downPayment;
    $months = (int) round($years * 12);
    $monthlyRate = $rate / 100 / 12;

    if ($monthlyRate > 0) {
        $factor = (1 + $monthlyRate) ** $months;
        $monthlyPayment = $loanAmount * $monthlyRate * $factor / ($factor - 1);
    } else {
        $monthlyPayment = $months > 0 ? $loanAmount / $months : 0;
    }

    $totalPayment = $monthlyPayment * $months;
    $totalInterest = $totalPayment - $loanAmount;

    $result = [
        'loan_amount' => $loanAmount,
        'monthly_payment' => $monthlyPayment,
        'total_payment' => $totalPayment,
        'total_interest' => $totalInterest,
    ];
}
?>

<div class="page-head">
  <div class="container">
    <p class="breadcrumbs"><a href="<?= e(url('index.php')) ?>">בית</a> / מחשבון משכנתא</p>
    <h1>מחשבון משכנתא</h1>
  </div>
</div>

<div class="container section">
  <p class="lede" style="max-width:64ch;">קבלו הערכה מהירה להחזר החודשי הצפוי, לפי מחיר הנכס, ההון העצמי, הריבית השנתית ותקופת ההלוואה. החישוב הוא כלי עזר ראשוני בלבד.</p>

  <?php if (!empty($errors['_general'])): ?><p class="alert-box alert-error"><?= e($errors['_general']) ?></p><?php endif; ?>

  <form method="get" class="filter-card" id="mortgageForm">
    <div class="mortgage-grid" id="mortgageGrid">
      <div class="field <?= isset($errors['price']) ? 'has-error' : '' ?>" style="margin-bottom:0;">
        <label for="m-price">מחיר הנכס (₪)</label>
        <input class="input" type="number" id="m-price" name="price" min="100000" max="50000000" step="10000" value="<?= e((string) ($price ?? ($_GET['price'] ?? ''))) ?>" required>
        <?php if (isset($errors['price'])): ?><span class="error"><?= e($errors['price']) ?></span><?php endif; ?>
      </div>
      <div class="field <?= isset($errors['down_payment']) ? 'has-error' : '' ?>" style="margin-bottom:0;">
        <label for="m-down">הון עצמי (₪)</label>
        <input class="input" type="number" id="m-down" name="down_payment" min="0" max="50000000" step="10000" value="<?= e((string) ($downPayment ?? ($_GET['down_payment'] ?? ''))) ?>" required>
        <?php if (isset($errors['down_payment'])): ?><span class="error"><?= e($errors['down_payment']) ?></span><?php endif; ?>
      </div>
      <div class="field <?= isset($errors['rate']) ? 'has-error' : '' ?>" style="margin-bottom:0;">
        <label for="m-rate">ריבית שנתית משוערת (%)</label>
        <input class="input" type="number" id="m-rate" name="rate" min="0" max="15" step="0.1" value="<?= e((string) ($rate ?? ($_GET['rate'] ?? ''))) ?>" required>
        <?php if (isset($errors['rate'])): ?><span class="error"><?= e($errors['rate']) ?></span><?php endif; ?>
      </div>
      <div class="field <?= isset($errors['years']) ? 'has-error' : '' ?>" style="margin-bottom:0;">
        <label for="m-years">תקופת המשכנתא (שנים)</label>
        <select class="input" id="m-years" name="years">
          <?php foreach ([10, 15, 20, 25, 30] as $y): ?>
            <option value="<?= $y ?>" <?= (int) ($years ?? 25) === $y ? 'selected' : '' ?>><?= $y ?> שנים</option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errors['years'])): ?><span class="error"><?= e($errors['years']) ?></span><?php endif; ?>
      </div>
    </div>
    <button type="submit" class="btn btn-primary" style="margin-top:16px;">חישוב</button>
  </form>

  <?php if ($result): ?>
    <div class="mortgage-result" id="mortgageResult">
      <h2>התוצאה המשוערת</h2>
      <div class="stat-row">
        <div class="stat-tile">
          <div class="stat-num" id="r-monthly"><?= e(money(round($result['monthly_payment']))) ?></div>
          <div class="stat-label">החזר חודשי משוער</div>
        </div>
        <div class="stat-tile">
          <div class="stat-num" id="r-loan"><?= e(money(round($result['loan_amount']))) ?></div>
          <div class="stat-label">סכום ההלוואה</div>
        </div>
        <div class="stat-tile">
          <div class="stat-num" id="r-interest"><?= e(money(round($result['total_interest']))) ?></div>
          <div class="stat-label">סה״כ ריבית לאורך התקופה</div>
        </div>
        <div class="stat-tile">
          <div class="stat-num" id="r-total"><?= e(money(round($result['total_payment']))) ?></div>
          <div class="stat-label">סה״כ החזר לאורך התקופה</div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <p style="color:var(--ink-3); font-size:0.85rem; margin-top:16px; max-width:70ch;">
    * החישוב להערכה כללית בלבד ואינו מהווה הצעה, ייעוץ פיננסי או התחייבות למתן משכנתא.
    התנאים בפועל, כולל אישור העסקה, גובה הריבית והמסלולים האפשריים, נקבעים על ידי הבנקים בהתאם למצבכם האישי.
  </p>

  <div style="margin-top:24px;">
    <a href="<?= e(url('contact.php')) ?>" class="link-arrow">רוצים לבדוק אפשרויות מימון מדויקות יותר? צרו קשר <span aria-hidden="true">&larr;</span></a>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
