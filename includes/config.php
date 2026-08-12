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
// שכבת נתונים (MySQL)
// ---------------------------------------------------------------------------

define('DB_HOST', 'localhost');
define('DB_NAME', 'nadlanisteam');
define('DB_USER', 'root');
define('DB_PASS', '');

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    return $pdo;
}

// --- הידרציה: ממירה שורת DB גולמית לצורת המערך שהתבניות מצפות לה ---------

function hydrate_agent(array $row): array
{
    $row['id'] = (int) $row['id'];
    $row['areas'] = json_decode($row['areas'] ?? '[]', true) ?: [];
    $row['languages'] = json_decode($row['languages'] ?? '[]', true) ?: [];
    $row['active'] = (bool) $row['active'];
    $row['sort'] = (int) $row['sort'];
    $row['username'] = $row['username'] ?? '';
    $row['password_hash'] = $row['password_hash'] ?? '';
    return $row;
}

function hydrate_property(array $row): array
{
    $row['id'] = (int) $row['id'];
    $row['images'] = json_decode($row['images'] ?? '[]', true) ?: [];
    $row['agent_id'] = (int) $row['agent_id'];
    foreach (['balcony', 'elevator', 'mamad', 'renovated', 'furnished', 'featured'] as $k) {
        $row[$k] = (bool) $row[$k];
    }
    $row['storage'] = (bool) $row['has_storage'];
    unset($row['has_storage']);
    $row['accessible'] = (bool) $row['is_accessible'];
    unset($row['is_accessible']);
    $row['price'] = (float) $row['price'];
    $row['rooms'] = (float) $row['rooms'];
    $row['size'] = (float) $row['size'];
    $row['plot_size'] = (float) $row['plot_size'];
    $row['floor'] = (int) $row['floor'];
    $row['total_floors'] = (int) $row['total_floors'];
    $row['parking'] = (int) $row['parking'];
    $row['lat'] = $row['lat'] !== null ? (float) $row['lat'] : '';
    $row['lng'] = $row['lng'] !== null ? (float) $row['lng'] : '';
    return $row;
}

function hydrate_partner(array $row): array
{
    $row['id'] = (int) $row['id'];
    $row['regions'] = json_decode($row['regions'] ?? '[]', true) ?: [];
    $row['services'] = json_decode($row['services'] ?? '[]', true) ?: [];
    $row['gallery'] = json_decode($row['gallery'] ?? '[]', true) ?: [];
    $row['verified'] = (bool) $row['verified'];
    $row['featured'] = (bool) $row['featured'];
    $row['active'] = (bool) $row['active'];
    $row['sort'] = (int) $row['sort'];
    $row['years_experience'] = $row['years_experience'] !== null ? (int) $row['years_experience'] : null;
    $row['rating'] = $row['rating'] !== null ? (float) $row['rating'] : null;
    return $row;
}

function hydrate_lead(array $row): array
{
    $row['id'] = (int) $row['id'];
    $row['property_id'] = $row['property_id'] !== null ? (int) $row['property_id'] : null;
    $row['agent_id'] = $row['agent_id'] !== null ? (int) $row['agent_id'] : null;
    $row['partner_id'] = $row['partner_id'] !== null ? (int) $row['partner_id'] : null;
    $row['read'] = (bool) $row['is_read'];
    unset($row['is_read']);
    return $row;
}

function hydrate_testimonial(array $row): array
{
    $row['id'] = (int) $row['id'];
    $row['rating'] = (int) $row['rating'];
    return $row;
}

// --- הגדרות (שורה יחידה) --------------------------------------------------

function get_settings(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $row = db()->query('SELECT * FROM settings WHERE id = 1')->fetch();
    $row['stat_years'] = (int) $row['stat_years'];
    $row['stat_deals'] = (int) $row['stat_deals'];
    $row['stat_clients'] = (int) $row['stat_clients'];
    unset($row['id']);
    $cache = $row;
    return $cache;
}

/** עדכון חלקי — רק העמודות שהועברו ב-$values */
function update_settings(array $values): void
{
    if (!$values) {
        return;
    }
    $sets = [];
    $params = [];
    foreach ($values as $col => $val) {
        $sets[] = "$col = ?";
        $params[] = $val;
    }
    $stmt = db()->prepare('UPDATE settings SET ' . implode(', ', $sets) . ' WHERE id = 1');
    $stmt->execute($params);
}

// --- ייבוא/איפוס נתוני דמו --------------------------------------------------

/**
 * מרוקן את כל הטבלאות (מלבד settings) ומייבא מחדש מקובץ JSON (data.json בהרצה
 * החד-פעמית של migrate.php, seed.json מ"טעינת דמו מחדש" באדמין). $wipeOnly=true
 * רק מרוקן, לא מייבא — משמש ל"מחיקת נתוני דמו".
 */
function import_seed_into_db(string $jsonPath, bool $wipeOnly = false): void
{
    $pdo = db();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['leads', 'properties', 'partners', 'agents', 'testimonials'] as $table) {
        $pdo->exec("TRUNCATE TABLE $table");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    if ($wipeOnly || !is_file($jsonPath)) {
        return;
    }

    $seed = json_decode((string) file_get_contents($jsonPath), true);
    if (!is_array($seed)) {
        return;
    }

    foreach ($seed['agents'] ?? [] as $a) {
        $stmt = $pdo->prepare('INSERT INTO agents
            (id, name, role, phone, whatsapp, email, photo, bio, areas, languages, active, sort, username, password_hash, last_login_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $a['id'], $a['name'], $a['role'] ?? '', $a['phone'] ?? '', $a['whatsapp'] ?? '', $a['email'] ?? '',
            $a['photo'] ?? '', $a['bio'] ?? '', json_encode($a['areas'] ?? [], JSON_UNESCAPED_UNICODE),
            json_encode($a['languages'] ?? [], JSON_UNESCAPED_UNICODE), !empty($a['active']) ? 1 : 0, $a['sort'] ?? 10,
            ($a['username'] ?? '') !== '' ? $a['username'] : null, ($a['password_hash'] ?? '') !== '' ? $a['password_hash'] : null,
            $a['last_login_at'] ?? null,
        ]);
    }

    foreach ($seed['properties'] ?? [] as $p) {
        $stmt = $pdo->prepare('INSERT INTO properties
            (id, title, deal, type, status, city, neighborhood, address, lat, lng, price, rooms, size, plot_size,
             floor, total_floors, parking, balcony, elevator, mamad, has_storage, renovated, is_accessible, furnished,
             entry_date, description, images, agent_id, featured, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $p['id'], $p['title'], $p['deal'] ?? 'sale', $p['type'] ?? '', $p['status'] ?? 'available',
            $p['city'] ?? '', $p['neighborhood'] ?? '', $p['address'] ?? '',
            ($p['lat'] ?? '') !== '' ? $p['lat'] : null, ($p['lng'] ?? '') !== '' ? $p['lng'] : null,
            $p['price'] ?? 0, $p['rooms'] ?? 0, $p['size'] ?? 0, $p['plot_size'] ?? 0,
            $p['floor'] ?? 0, $p['total_floors'] ?? 0, $p['parking'] ?? 0,
            !empty($p['balcony']) ? 1 : 0, !empty($p['elevator']) ? 1 : 0, !empty($p['mamad']) ? 1 : 0,
            !empty($p['storage']) ? 1 : 0, !empty($p['renovated']) ? 1 : 0, !empty($p['accessible']) ? 1 : 0,
            !empty($p['furnished']) ? 1 : 0, $p['entry_date'] ?? '', $p['description'] ?? '',
            json_encode($p['images'] ?? [], JSON_UNESCAPED_UNICODE), $p['agent_id'] ?? 0,
            !empty($p['featured']) ? 1 : 0, $p['created_at'] ?? date('Y-m-d H:i:s'),
        ]);
    }

    foreach ($seed['partners'] ?? [] as $p) {
        $stmt = $pdo->prepare('INSERT INTO partners
            (id, name, category, business_type, regions, description_short, description_full, services,
             phone, whatsapp, email, website, logo, gallery, years_experience, rating, verified, featured, active, sort, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $p['id'], $p['name'], $p['category'] ?? '', $p['business_type'] ?? '',
            json_encode($p['regions'] ?? [], JSON_UNESCAPED_UNICODE), $p['description_short'] ?? '', $p['description_full'] ?? '',
            json_encode($p['services'] ?? [], JSON_UNESCAPED_UNICODE), $p['phone'] ?? '', $p['whatsapp'] ?? '',
            $p['email'] ?? '', $p['website'] ?? '', $p['logo'] ?? '', json_encode($p['gallery'] ?? [], JSON_UNESCAPED_UNICODE),
            $p['years_experience'] ?? null, $p['rating'] ?? null, !empty($p['verified']) ? 1 : 0,
            !empty($p['featured']) ? 1 : 0, !empty($p['active']) ? 1 : 0, $p['sort'] ?? 10,
            $p['created_at'] ?? date('Y-m-d H:i:s'),
        ]);
    }

    foreach ($seed['leads'] ?? [] as $l) {
        $stmt = $pdo->prepare('INSERT INTO leads
            (id, name, phone, email, message, property_id, agent_id, partner_id, service, source, created_at, is_read)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $l['id'], $l['name'], $l['phone'] ?? '', $l['email'] ?? '', $l['message'] ?? '',
            $l['property_id'] ?? null, $l['agent_id'] ?? null, $l['partner_id'] ?? null, $l['service'] ?? '',
            $l['source'] ?? 'contact', $l['created_at'] ?? date('Y-m-d H:i:s'), !empty($l['read']) ? 1 : 0,
        ]);
    }

    foreach ($seed['testimonials'] ?? [] as $t) {
        $stmt = $pdo->prepare('INSERT INTO testimonials (id, name, city, text, rating) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$t['id'], $t['name'], $t['city'] ?? '', $t['text'], $t['rating'] ?? 5]);
    }

    if (!empty($seed['settings'])) {
        $s = $seed['settings'];
        update_settings([
            'agency_name' => $s['agency_name'] ?? get_settings()['agency_name'],
            'tagline' => $s['tagline'] ?? '', 'phone' => $s['phone'] ?? '', 'whatsapp' => $s['whatsapp'] ?? '',
            'email' => $s['email'] ?? '', 'address' => $s['address'] ?? '', 'facebook' => $s['facebook'] ?? '',
            'instagram' => $s['instagram'] ?? '', 'hero_title' => $s['hero_title'] ?? '', 'hero_sub' => $s['hero_sub'] ?? '',
            'about_text' => $s['about_text'] ?? '', 'stat_years' => $s['stat_years'] ?? 0, 'stat_deals' => $s['stat_deals'] ?? 0,
            'stat_clients' => $s['stat_clients'] ?? 0, 'admin_user' => $s['admin_user'] ?? '', 'admin_hash' => $s['admin_hash'] ?? '',
        ]);
    }

    foreach (['agents', 'properties', 'partners', 'leads', 'testimonials'] as $table) {
        $max = (int) $pdo->query("SELECT COALESCE(MAX(id), 0) FROM $table")->fetchColumn();
        $pdo->exec("ALTER TABLE $table AUTO_INCREMENT = " . ($max + 1));
    }
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
    $sql = 'SELECT * FROM properties' . ($publishedOnly ? " WHERE status != 'draft'" : '');
    $props = array_map('hydrate_property', db()->query($sql)->fetchAll());
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
    $stmt = db()->prepare('SELECT * FROM properties WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? hydrate_property($row) : null;
}

function find_agent(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM agents WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? hydrate_agent($row) : null;
}

function all_agents(bool $activeOnly = true): array
{
    $sql = 'SELECT * FROM agents' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY sort ASC';
    return array_map('hydrate_agent', db()->query($sql)->fetchAll());
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
    $stmt = db()->prepare('SELECT * FROM agents WHERE username = ?');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    return $row ? hydrate_agent($row) : null;
}

function filtered_leads(int $filterAgent, int $filterProperty, int $filterPartner): array
{
    $sql = 'SELECT * FROM leads WHERE 1=1';
    $params = [];
    if ($filterAgent > 0) {
        $sql .= ' AND agent_id = ?';
        $params[] = $filterAgent;
    }
    if ($filterProperty > 0) {
        $sql .= ' AND property_id = ?';
        $params[] = $filterProperty;
    }
    if ($filterPartner > 0) {
        $sql .= ' AND partner_id = ?';
        $params[] = $filterPartner;
    }
    $sql .= ' ORDER BY created_at DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return array_map('hydrate_lead', $stmt->fetchAll());
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
    $sql = 'SELECT * FROM partners' . ($activeOnly ? ' WHERE active = 1' : '');
    $partners = array_map('hydrate_partner', db()->query($sql)->fetchAll());
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
    $stmt = db()->prepare('SELECT * FROM partners WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? hydrate_partner($row) : null;
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
// המלצות
// ---------------------------------------------------------------------------

function all_testimonials(): array
{
    return array_map('hydrate_testimonial', db()->query('SELECT * FROM testimonials')->fetchAll());
}

function insert_testimonial(array $v): int
{
    $stmt = db()->prepare('INSERT INTO testimonials (name, city, text, rating) VALUES (?, ?, ?, ?)');
    $stmt->execute([$v['name'], $v['city'], $v['text'], $v['rating']]);
    return (int) db()->lastInsertId();
}

function update_testimonial(int $id, array $v): void
{
    $stmt = db()->prepare('UPDATE testimonials SET name=?, city=?, text=?, rating=? WHERE id=?');
    $stmt->execute([$v['name'], $v['city'], $v['text'], $v['rating'], $id]);
}

function delete_testimonial(int $id): void
{
    db()->prepare('DELETE FROM testimonials WHERE id = ?')->execute([$id]);
}

// ---------------------------------------------------------------------------
// כתיבה — נכסים
// ---------------------------------------------------------------------------

function insert_property(array $v): int
{
    $stmt = db()->prepare('INSERT INTO properties
        (title, deal, type, status, city, neighborhood, address, lat, lng, price, rooms, size, plot_size,
         floor, total_floors, parking, balcony, elevator, mamad, has_storage, renovated, is_accessible, furnished,
         entry_date, description, images, agent_id, featured, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $v['created_at'] = $v['created_at'] ?? date('Y-m-d H:i:s');
    $stmt->execute(property_bind_params($v, true));
    return (int) db()->lastInsertId();
}

/** פרמטרים לשאילתת INSERT/UPDATE של נכס, באותו סדר עמודות בשתי הפעולות */
function property_bind_params(array $v, bool $withCreatedAt = true): array
{
    $params = [
        $v['title'], $v['deal'], $v['type'], $v['status'], $v['city'], $v['neighborhood'], $v['address'],
        $v['lat'] !== '' ? $v['lat'] : null, $v['lng'] !== '' ? $v['lng'] : null,
        $v['price'], $v['rooms'], $v['size'], $v['plot_size'], $v['floor'], $v['total_floors'], $v['parking'],
        $v['balcony'] ? 1 : 0, $v['elevator'] ? 1 : 0, $v['mamad'] ? 1 : 0, $v['storage'] ? 1 : 0,
        $v['renovated'] ? 1 : 0, $v['accessible'] ? 1 : 0, $v['furnished'] ? 1 : 0,
        $v['entry_date'], $v['description'], json_encode($v['images'] ?? [], JSON_UNESCAPED_UNICODE),
        $v['agent_id'], $v['featured'] ? 1 : 0,
    ];
    if ($withCreatedAt) {
        $params[] = $v['created_at'] ?? date('Y-m-d H:i:s');
    }
    return $params;
}

function update_property(int $id, array $v): void
{
    $stmt = db()->prepare('UPDATE properties SET
        title=?, deal=?, type=?, status=?, city=?, neighborhood=?, address=?, lat=?, lng=?, price=?, rooms=?, size=?,
        plot_size=?, floor=?, total_floors=?, parking=?, balcony=?, elevator=?, mamad=?, has_storage=?, renovated=?,
        is_accessible=?, furnished=?, entry_date=?, description=?, images=?, agent_id=?, featured=?
        WHERE id=?');
    $stmt->execute([...property_bind_params($v, false), $id]);
}

function delete_property(int $id): void
{
    db()->prepare('DELETE FROM properties WHERE id = ?')->execute([$id]);
}

function toggle_property_featured(int $id): void
{
    db()->prepare('UPDATE properties SET featured = NOT featured WHERE id = ?')->execute([$id]);
}

function update_property_images(int $id, array $images): void
{
    $stmt = db()->prepare('UPDATE properties SET images = ? WHERE id = ?');
    $stmt->execute([json_encode($images, JSON_UNESCAPED_UNICODE), $id]);
}

/** משכפל נכס קיים כטיוטה (כותרת + "(עותק)", תמונות משוכפלות בפועל בדיסק) */
function duplicate_property(int $id): ?int
{
    $source = find_property($id);
    if (!$source) {
        return null;
    }
    $source['title'] .= ' (עותק)';
    $source['status'] = 'draft';
    $source['featured'] = false;
    $source['images'] = array_map('duplicate_uploaded_image', $source['images'] ?? []);
    return insert_property($source);
}

// ---------------------------------------------------------------------------
// כתיבה — סוכנים
// ---------------------------------------------------------------------------

function insert_agent(array $v): int
{
    $stmt = db()->prepare('INSERT INTO agents (name, role, phone, whatsapp, email, photo, bio, areas, languages, active, sort, username, password_hash)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute(agent_bind_params($v));
    return (int) db()->lastInsertId();
}

function agent_bind_params(array $v): array
{
    return [
        $v['name'], $v['role'], $v['phone'], $v['whatsapp'], $v['email'], $v['photo'], $v['bio'],
        json_encode($v['areas'] ?? [], JSON_UNESCAPED_UNICODE), json_encode($v['languages'] ?? [], JSON_UNESCAPED_UNICODE),
        $v['active'] ? 1 : 0, $v['sort'],
        ($v['username'] ?? '') !== '' ? $v['username'] : null,
        ($v['password_hash'] ?? '') !== '' ? $v['password_hash'] : null,
    ];
}

function update_agent(int $id, array $v): void
{
    $stmt = db()->prepare('UPDATE agents SET name=?, role=?, phone=?, whatsapp=?, email=?, photo=?, bio=?, areas=?,
        languages=?, active=?, sort=?, username=?, password_hash=? WHERE id=?');
    $stmt->execute([...agent_bind_params($v), $id]);
}

function delete_agent(int $id): void
{
    db()->prepare('DELETE FROM agents WHERE id = ?')->execute([$id]);
}

function toggle_agent_active(int $id): void
{
    db()->prepare('UPDATE agents SET active = NOT active WHERE id = ?')->execute([$id]);
}

function set_agent_last_login(int $id): void
{
    db()->prepare('UPDATE agents SET last_login_at = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $id]);
}

function agent_username_taken(string $username, int $excludeId = 0): bool
{
    if ($username === '') {
        return false;
    }
    $stmt = db()->prepare('SELECT id FROM agents WHERE username = ? AND id != ?');
    $stmt->execute([$username, $excludeId]);
    return (bool) $stmt->fetch();
}

/** כמה נכסים (כולל טיוטות) משויכים לסוכן — לבדיקת "לא ניתן למחוק" לפני מחיקה */
function property_count_for_agent(int $agentId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM properties WHERE agent_id = ?');
    $stmt->execute([$agentId]);
    return (int) $stmt->fetchColumn();
}

// ---------------------------------------------------------------------------
// כתיבה — שותפים
// ---------------------------------------------------------------------------

function insert_partner(array $v): int
{
    $stmt = db()->prepare('INSERT INTO partners
        (name, category, business_type, regions, description_short, description_full, services,
         phone, whatsapp, email, website, logo, gallery, years_experience, rating, verified, featured, active, sort, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([...partner_bind_params($v), $v['created_at'] ?? date('Y-m-d H:i:s')]);
    return (int) db()->lastInsertId();
}

function partner_bind_params(array $v): array
{
    return [
        $v['name'], $v['category'], $v['business_type'], json_encode($v['regions'] ?? [], JSON_UNESCAPED_UNICODE),
        $v['description_short'], $v['description_full'], json_encode($v['services'] ?? [], JSON_UNESCAPED_UNICODE),
        $v['phone'], $v['whatsapp'], $v['email'], $v['website'], $v['logo'],
        json_encode($v['gallery'] ?? [], JSON_UNESCAPED_UNICODE), $v['years_experience'], $v['rating'],
        $v['verified'] ? 1 : 0, $v['featured'] ? 1 : 0, $v['active'] ? 1 : 0, $v['sort'],
    ];
}

function update_partner(int $id, array $v): void
{
    $stmt = db()->prepare('UPDATE partners SET name=?, category=?, business_type=?, regions=?, description_short=?,
        description_full=?, services=?, phone=?, whatsapp=?, email=?, website=?, logo=?, gallery=?, years_experience=?,
        rating=?, verified=?, featured=?, active=?, sort=? WHERE id=?');
    $stmt->execute([...partner_bind_params($v), $id]);
}

function delete_partner(int $id): void
{
    db()->prepare('DELETE FROM partners WHERE id = ?')->execute([$id]);
}

function toggle_partner_active(int $id): void
{
    db()->prepare('UPDATE partners SET active = NOT active WHERE id = ?')->execute([$id]);
}

function toggle_partner_featured(int $id): void
{
    db()->prepare('UPDATE partners SET featured = NOT featured WHERE id = ?')->execute([$id]);
}

function update_partner_gallery(int $id, array $gallery): void
{
    $stmt = db()->prepare('UPDATE partners SET gallery = ? WHERE id = ?');
    $stmt->execute([json_encode($gallery, JSON_UNESCAPED_UNICODE), $id]);
}

// ---------------------------------------------------------------------------
// כתיבה — לידים
// ---------------------------------------------------------------------------

function insert_lead(array $v): int
{
    $stmt = db()->prepare('INSERT INTO leads (name, phone, email, message, property_id, agent_id, partner_id, service, source, created_at, is_read)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)');
    $stmt->execute([
        $v['name'], $v['phone'], $v['email'], $v['message'], $v['property_id'], $v['agent_id'], $v['partner_id'],
        $v['service'], $v['source'], $v['created_at'] ?? date('Y-m-d H:i:s'),
    ]);
    return (int) db()->lastInsertId();
}

function mark_lead_read(int $id): void
{
    db()->prepare('UPDATE leads SET is_read = 1 WHERE id = ?')->execute([$id]);
}

function delete_lead(int $id): void
{
    db()->prepare('DELETE FROM leads WHERE id = ?')->execute([$id]);
}

// ---------------------------------------------------------------------------
// אימות ועזרי טפסים
// ---------------------------------------------------------------------------

function valid_il_phone(string $phone): bool
{
    $digits = preg_replace('/\D+/', '', $phone);
    return (bool) preg_match('/^0(5\d|[2-4,8-9])\d{7}$/', $digits);
}
