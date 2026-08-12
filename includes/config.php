<?php
/**
 * שכבת נתונים ופונקציות עזר — הקובץ היחיד שיודע איך לקרוא/לכתוב ל-data.json.
 * כל שאר האתר (ציבורי ומנהל) עובר דרך הפונקציות כאן ולא נוגע בקובץ ה-JSON ישירות.
 */

session_start();

error_reporting(E_ALL);
ini_set('display_errors', '0');

define('APP_ROOT', dirname(__DIR__));
define('DATA_FILE', APP_ROOT . '/data/data.json');
define('UPLOADS_DIR', APP_ROOT . '/uploads');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// ---------------------------------------------------------------------------
// נתיבים / URL
// ---------------------------------------------------------------------------

/** בסיס האפליקציה, כדי שהאתר יעבוד תחת /nadlanisteam/ ולא רק בשורש הדומיין */
function app_base(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    // עמודי admin/* ו-agent-portal/* צריכים לחזור תיקייה אחת אחורה כדי להגיע לשורש האתר
    if (in_array(basename($scriptDir), ['admin', 'agent-portal'], true)) {
        $scriptDir = dirname($scriptDir);
    }
    $base = rtrim($scriptDir, '/');
    return $base;
}

/** בונה URL יחסי לשורש האתר, למשל url('properties.php') */
function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $base = app_base();
    return ($base === '' ? '' : $base) . '/' . $path;
}

/** מוודא שנתיב הוא יחסי לאתר עצמו בלבד (מונע open redirect); מחזיר $fallback אחרת */
function safe_internal_path(?string $path, string $fallback): string
{
    if (!$path || $path[0] !== '/' || str_starts_with($path, '//') || preg_match('#^https?://#i', $path)) {
        return $fallback;
    }
    return $path;
}

/** כמו url(), אבל מוסיף ?v=<mtime> לקבצי css/js כדי שדפדפנים יטענו גרסה עדכנית אחרי כל שינוי */
function asset_url(string $path): string
{
    $full = APP_ROOT . '/' . ltrim($path, '/');
    $v = is_file($full) ? filemtime($full) : time();
    return url($path) . '?v=' . $v;
}

// ---------------------------------------------------------------------------
// אבטחה / פלט
// ---------------------------------------------------------------------------

/** בריחת HTML לכל ערך שמודפס לעמוד — יש להשתמש בזה תמיד */
function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_check(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent) && hash_equals($_SESSION['csrf_token'] ?? '', $sent);
}

/** הודעת פלאש חד-פעמית דרך הסשן */
function flash(?string $msg = null, string $type = 'success')
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

// ---------------------------------------------------------------------------
// עיצוב ערכים
// ---------------------------------------------------------------------------

function money(float $n): string
{
    return '₪' . number_format($n, 0, '.', ',');
}

function money_short(float $n): string
{
    if ($n >= 1000000) {
        $v = $n / 1000000;
        $formatted = (floor($v * 100) / 100 == floor($v)) ? number_format($v, 0) : rtrim(rtrim(number_format($v, 2), '0'), '.');
        return '₪' . $formatted . ' מ׳';
    }
    if ($n >= 1000) {
        return '₪' . number_format(round($n / 1000)) . ' אלף';
    }
    return money($n);
}

function media_url(?string $file): string
{
    if (empty($file)) {
        return url('assets/img/placeholder.svg');
    }
    if (preg_match('#^https?://#i', $file)) {
        return $file;
    }
    $path = UPLOADS_DIR . '/' . $file;
    if (!is_file($path)) {
        return url('assets/img/placeholder.svg');
    }
    return url('uploads/' . $file);
}

function wa_link(string $number, string $text = ''): string
{
    $digits = preg_replace('/\D+/', '', $number);
    if (str_starts_with($digits, '0')) {
        $digits = '972' . substr($digits, 1);
    }
    $url = 'https://wa.me/' . $digits;
    if ($text !== '') {
        $url .= '?text=' . rawurlencode($text);
    }
    return $url;
}

function tel_link(string $number): string
{
    return 'tel:' . preg_replace('/\D+/', '', $number);
}

// ---------------------------------------------------------------------------
// שכבת נתונים (JSON)
// ---------------------------------------------------------------------------

function default_data(): array
{
    return [
        'settings' => [
            'agency_name' => 'נדלניס טים',
            'tagline' => 'תיווך • שיווק • השקעות נדל״ן',
            'phone' => '052-529-9482',
            'whatsapp' => '972525299482',
            'email' => 'info@nadlanisteam.co.il',
            'address' => 'נתניה',
            'facebook' => '',
            'instagram' => '',
            'hero_title' => 'מכירים כל רחוב בנתניה',
            'hero_sub' => 'צוות נדלניס טים מלווה אתכם בקנייה, במכירה ובהשקעה בנדל״ן — באמינות, במקצועיות ובלב פתוח.',
            'about_text' => "נדלניס טים פועל בנתניה עם היכרות מעמיקה עם השכונות, המחירים והאנשים.\nכל עסקה מתחילה בהקשבה: מה חשוב לכם, מה התקציב, ולאן אתם רוצים להגיע.\nמשם, אנחנו כבר דואגים לשאר.",
            'stat_years' => 12,
            'stat_deals' => 340,
            'stat_clients' => 200,
            'admin_user' => '',
            'admin_hash' => '',
        ],
        'agents' => [],
        'properties' => [],
        'partners' => [],
        'leads' => [],
        'testimonials' => [],
        'counters' => ['property' => 1, 'agent' => 1, 'partner' => 1, 'lead' => 1, 'testimonial' => 1],
    ];
}

/** מטמון משותף בין load_data ו-save_data לכל אורך הבקשה */
function &data_cache_ref()
{
    static $cache = null;
    return $cache;
}

function load_data(): array
{
    $cache = &data_cache_ref();
    if ($cache !== null) {
        return $cache;
    }
    if (!is_file(DATA_FILE)) {
        $cache = default_data();
        save_data($cache);
        return $cache;
    }
    $raw = file_get_contents(DATA_FILE);
    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        $cache = default_data();
        save_data($cache);
        return $cache;
    }
    $cache = array_replace_recursive(default_data(), $data);
    return $cache;
}

function save_data(array $data): bool
{
    $dir = dirname(DATA_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $ok = file_put_contents(DATA_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    if ($ok !== false) {
        $cache = &data_cache_ref();
        $cache = $data;
    }
    return $ok !== false;
}

function next_id(string $counter): int
{
    $data = load_data();
    $id = $data['counters'][$counter] ?? 1;
    $data['counters'][$counter] = $id + 1;
    save_data($data);
    return $id;
}

// ---------------------------------------------------------------------------
// אוצר מילים מבוקר
// ---------------------------------------------------------------------------

function property_types(): array
{
    return ['דירה', 'דירת גן', 'פנטהאוז', 'דופלקס', 'בית פרטי', 'קוטג׳', 'מגרש', 'מסחרי'];
}

function deal_types(): array
{
    return ['sale' => 'למכירה', 'rent' => 'להשכרה'];
}

function status_labels(): array
{
    return [
        'available' => 'פנוי',
        'under_contract' => 'בתהליך',
        'sold' => 'נמכר',
        'draft' => 'טיוטה',
    ];
}

// ---------------------------------------------------------------------------
// שאילתות נכסים / סוכנים
// ---------------------------------------------------------------------------

function all_properties(bool $publishedOnly = true): array
{
    $props = load_data()['properties'];
    if ($publishedOnly) {
        $props = array_values(array_filter($props, fn($p) => ($p['status'] ?? '') !== 'draft'));
    }
    usort($props, function ($a, $b) {
        $fa = !empty($a['featured']);
        $fb = !empty($b['featured']);
        if ($fa !== $fb) {
            return $fa ? -1 : 1;
        }
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });
    return $props;
}

function find_property(int $id): ?array
{
    foreach (load_data()['properties'] as $p) {
        if ((int) $p['id'] === $id) {
            return $p;
        }
    }
    return null;
}

function find_agent(int $id): ?array
{
    foreach (load_data()['agents'] as $a) {
        if ((int) $a['id'] === $id) {
            return $a;
        }
    }
    return null;
}

function all_agents(bool $activeOnly = true): array
{
    $agents = load_data()['agents'];
    if ($activeOnly) {
        $agents = array_values(array_filter($agents, fn($a) => !empty($a['active'])));
    }
    usort($agents, fn($a, $b) => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));
    return $agents;
}

function agent_properties(int $agentId, bool $publishedOnly = true): array
{
    return array_values(array_filter(all_properties($publishedOnly), fn($p) => (int) ($p['agent_id'] ?? 0) === $agentId));
}

function agent_listing_count(int $agentId): int
{
    return count(agent_properties($agentId, true));
}

function find_agent_by_username(string $username): ?array
{
    if ($username === '') {
        return null;
    }
    foreach (load_data()['agents'] as $a) {
        if (($a['username'] ?? '') !== '' && $a['username'] === $username) {
            return $a;
        }
    }
    return null;
}

function filtered_leads(int $filterAgent, int $filterProperty, int $filterPartner): array
{
    $leads = load_data()['leads'];
    if ($filterAgent > 0) {
        $leads = array_values(array_filter($leads, fn($l) => (int) ($l['agent_id'] ?? 0) === $filterAgent));
    }
    if ($filterProperty > 0) {
        $leads = array_values(array_filter($leads, fn($l) => (int) ($l['property_id'] ?? 0) === $filterProperty));
    }
    if ($filterPartner > 0) {
        $leads = array_values(array_filter($leads, fn($l) => (int) ($l['partner_id'] ?? 0) === $filterPartner));
    }
    usort($leads, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    return $leads;
}

function filter_properties(array $props, array $filters): array
{
    $deal = $filters['deal'] ?? '';
    $city = trim($filters['city'] ?? '');
    $type = $filters['type'] ?? '';
    $agentId = (int) ($filters['agent'] ?? 0);
    $status = $filters['status'] ?? '';
    $roomsMin = $filters['rooms_min'] ?? '';
    $roomsMax = $filters['rooms_max'] ?? '';
    $priceMin = $filters['price_min'] ?? '';
    $priceMax = $filters['price_max'] ?? '';
    $q = trim($filters['q'] ?? '');

    return array_values(array_filter($props, function ($p) use (
        $deal, $city, $type, $agentId, $status, $roomsMin, $roomsMax, $priceMin, $priceMax, $q
    ) {
        if ($deal !== '' && ($p['deal'] ?? '') !== $deal) return false;
        if ($city !== '' && ($p['city'] ?? '') !== $city) return false;
        if ($type !== '' && ($p['type'] ?? '') !== $type) return false;
        if ($agentId > 0 && (int) ($p['agent_id'] ?? 0) !== $agentId) return false;
        if ($status !== '' && ($p['status'] ?? '') !== $status) return false;
        if ($roomsMin !== '' && (float) ($p['rooms'] ?? 0) < (float) $roomsMin) return false;
        if ($roomsMax !== '' && (float) ($p['rooms'] ?? 0) > (float) $roomsMax) return false;
        if ($priceMin !== '' && (float) ($p['price'] ?? 0) < (float) $priceMin) return false;
        if ($priceMax !== '' && (float) ($p['price'] ?? 0) > (float) $priceMax) return false;
        if ($q !== '') {
            $hay = ($p['title'] ?? '') . ' ' . ($p['neighborhood'] ?? '') . ' ' . ($p['address'] ?? '') . ' ' . ($p['description'] ?? '');
            if (mb_stripos($hay, $q) === false) return false;
        }
        return true;
    }));
}

function sort_properties(array $props, string $sort): array
{
    switch ($sort) {
        case 'price_asc':
            usort($props, fn($a, $b) => ($a['price'] ?? 0) <=> ($b['price'] ?? 0));
            break;
        case 'price_desc':
            usort($props, fn($a, $b) => ($b['price'] ?? 0) <=> ($a['price'] ?? 0));
            break;
        case 'rooms_desc':
            usort($props, fn($a, $b) => ($b['rooms'] ?? 0) <=> ($a['rooms'] ?? 0));
            break;
        case 'size_desc':
            usort($props, fn($a, $b) => ($b['size'] ?? 0) <=> ($a['size'] ?? 0));
            break;
        case 'newest':
        default:
            usort($props, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    }
    return $props;
}

function cities_in_use(): array
{
    $cities = [];
    foreach (all_properties(true) as $p) {
        if (!empty($p['city'])) {
            $cities[$p['city']] = true;
        }
    }
    $list = array_keys($cities);
    sort($list, SORT_STRING | SORT_FLAG_CASE);
    return $list;
}

// ---------------------------------------------------------------------------
// שותפים ואנשי מקצוע (Partners)
// ---------------------------------------------------------------------------

/** קטגוריות שותפים — מפתח => [label, icon]. הוספת קטגוריה = שורה אחת כאן, לא שינוי סכימה. */
function partner_categories(): array
{
    return [
        'lawyer' => ['label' => 'עורכי דין מקרקעין', 'icon' => '⚖️'],
        'mortgage_advisor' => ['label' => 'יועצי משכנתאות', 'icon' => '🏦'],
        'developer' => ['label' => 'יזמים', 'icon' => '🏗️'],
        'contractor' => ['label' => 'קבלנים וחברות בנייה', 'icon' => '🏢'],
        'appraiser' => ['label' => 'שמאי מקרקעין', 'icon' => '📐'],
        'architect' => ['label' => 'אדריכלים ומעצבי פנים', 'icon' => '🏠'],
        'financial_advisor' => ['label' => 'יועצים פיננסיים', 'icon' => '💰'],
        'property_management' => ['label' => 'חברות ניהול ואחזקה', 'icon' => '🔑'],
        'tax_advisor' => ['label' => 'יועצי מיסוי מקרקעין', 'icon' => '🧾'],
        'brokerage' => ['label' => 'חברות תיווך', 'icon' => '🏡'],
        'other' => ['label' => 'אחר', 'icon' => '🔗'],
    ];
}

function partner_category_label(string $key): string
{
    return partner_categories()[$key]['label'] ?? $key;
}

function all_partners(bool $activeOnly = true): array
{
    $partners = load_data()['partners'];
    if ($activeOnly) {
        $partners = array_values(array_filter($partners, fn($p) => !empty($p['active'])));
    }
    usort($partners, function ($a, $b) {
        $fa = !empty($a['featured']);
        $fb = !empty($b['featured']);
        if ($fa !== $fb) {
            return $fa ? -1 : 1;
        }
        return ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0);
    });
    return $partners;
}

function find_partner(int $id): ?array
{
    foreach (load_data()['partners'] as $p) {
        if ((int) $p['id'] === $id) {
            return $p;
        }
    }
    return null;
}

function filter_partners(array $partners, array $filters): array
{
    $category = $filters['category'] ?? '';
    $region = trim($filters['region'] ?? '');
    $q = trim($filters['q'] ?? '');
    $featuredOnly = !empty($filters['featured']);

    return array_values(array_filter($partners, function ($p) use ($category, $region, $q, $featuredOnly) {
        if ($category !== '' && ($p['category'] ?? '') !== $category) return false;
        if ($region !== '' && !in_array($region, $p['regions'] ?? [], true)) return false;
        if ($featuredOnly && empty($p['featured'])) return false;
        if ($q !== '') {
            $hay = ($p['name'] ?? '') . ' ' . ($p['business_type'] ?? '') . ' '
                . implode(' ', $p['services'] ?? []) . ' ' . implode(' ', $p['regions'] ?? []);
            if (mb_stripos($hay, $q) === false) return false;
        }
        return true;
    }));
}

/** רשימת אזורי פעילות בפועל, נגזרת מהנתונים — כמו cities_in_use(), לא הארדקוד */
function partner_regions_in_use(): array
{
    $regions = [];
    foreach (all_partners(true) as $p) {
        foreach ($p['regions'] ?? [] as $r) {
            if ($r !== '') {
                $regions[$r] = true;
            }
        }
    }
    $list = array_keys($regions);
    sort($list, SORT_STRING | SORT_FLAG_CASE);
    return $list;
}

/** שותפים שנותנים שירות באזור נתון — לשימוש ב-CTA ההקשרי בעמוד נכס */
function partners_serving_region(string $region, string $category = ''): array
{
    return filter_partners(all_partners(true), ['region' => $region, 'category' => $category]);
}

// ---------------------------------------------------------------------------
// אימות ועזרי טפסים
// ---------------------------------------------------------------------------

function valid_il_phone(string $phone): bool
{
    $digits = preg_replace('/\D+/', '', $phone);
    return (bool) preg_match('/^0(5\d|[2-4,8-9])\d{7}$/', $digits);
}
