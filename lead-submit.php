<?php
require __DIR__ . '/includes/config.php';

$redirect = $_POST['redirect'] ?? url('contact.php');
// מונע open redirect — מקבל רק נתיב יחסי לאתר עצמו
if (!preg_match('#^/#', $redirect) || preg_match('#^https?://#i', $redirect) || str_contains($redirect, '//')) {
    $redirect = url('contact.php');
}

/** בונה URL חזרה תוך שימור פרמטרי ה-query המקוריים (כגון ?id=), עם תוספות אופציונליות */
function redirect_url(string $redirect, array $extraParams = []): string
{
    $parts = parse_url($redirect);
    $path = $parts['path'] ?? '/';
    parse_str($parts['query'] ?? '', $params);
    $params = array_merge($params, $extraParams);
    $qs = http_build_query($params);
    return $path . ($qs ? '?' . $qs : '');
}

function back_with_error(string $redirect, array $errors, array $prefill): never
{
    $_SESSION['lead_errors'] = $errors;
    $_SESSION['lead_prefill'] = $prefill;
    header('Location: ' . redirect_url($redirect));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('contact.php'));
    exit;
}

if (!csrf_check()) {
    back_with_error($redirect, ['_general' => 'הפנייה פגה, נסו שוב.'], []);
}

// honeypot — בוט מילא שדה שאמור להישאר ריק: מתעלמים בשקט ומעמידים פנים שהצליח
if (!empty($_POST['website'])) {
    header('Location: ' . redirect_url($redirect, ['sent' => '1']));
    exit;
}

// הגבלת קצב: עד 5 פניות לשעה לכל סשן
$now = time();
$_SESSION['lead_submits'] = array_values(array_filter($_SESSION['lead_submits'] ?? [], fn($t) => $t > $now - 3600));
if (count($_SESSION['lead_submits']) >= 5) {
    back_with_error($redirect, ['_general' => 'נשלחו יותר מדי פניות. נסו שוב מאוחר יותר או התקשרו אלינו ישירות.'], $_POST);
}

$name = trim((string) ($_POST['name'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));
$consent = !empty($_POST['consent']);
$source = $_POST['source'] ?? 'contact';
$propertyId = !empty($_POST['property_id']) ? (int) $_POST['property_id'] : null;
$agentId = !empty($_POST['agent_id']) ? (int) $_POST['agent_id'] : null;
$partnerId = !empty($_POST['partner_id']) ? (int) $_POST['partner_id'] : null;
$service = trim((string) ($_POST['service'] ?? ''));

$errors = [];
if (mb_strlen($name) < 2) {
    $errors['name'] = 'נא להזין שם מלא (לפחות 2 תווים).';
}
if (!valid_il_phone($phone)) {
    $errors['phone'] = 'נא להזין מספר טלפון ישראלי תקין.';
}
if (!$consent) {
    $errors['_general'] = 'יש לאשר יצירת קשר כדי לשלוח את הפנייה.';
}

if ($errors) {
    back_with_error($redirect, $errors, $_POST);
}

$leadId = next_id('lead');
$data = load_data();
$data['leads'][] = [
    'id' => $leadId,
    'name' => $name,
    'phone' => $phone,
    'email' => $email,
    'message' => $message,
    'property_id' => $propertyId,
    'agent_id' => $agentId,
    'partner_id' => $partnerId,
    'service' => $service,
    'source' => in_array($source, ['property', 'agent', 'partner', 'contact', 'home'], true) ? $source : 'contact',
    'created_at' => date('Y-m-d H:i:s'),
    'read' => false,
];
save_data($data);

$_SESSION['lead_submits'][] = $now;

header('Location: ' . redirect_url($redirect, ['sent' => '1']));
exit;
