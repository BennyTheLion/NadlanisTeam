<?php
/**
 * סקריפט מיגרציה חד-פעמי: יוצר את מסד הנתונים nadlanisteam, מריץ את הסכימה
 * (data/schema.sql), ומייבא את הנתונים הקיימים מ-data/data.json.
 * הרצה: php migrate.php
 * הרצה חוזרת עם איפוס מלא: php migrate.php --force
 */

require __DIR__ . '/includes/config.php';

$force = in_array('--force', $argv, true);

echo "מתחבר ל-MySQL...\n";
$root = new PDO(
    'mysql:host=' . DB_HOST . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$root->exec('CREATE DATABASE IF NOT EXISTS ' . DB_NAME . ' CHARACTER SET utf8mb4');
echo "מסד הנתונים '" . DB_NAME . "' קיים.\n";

$pdo = db();

echo "מריץ סכימה (data/schema.sql)...\n";
$schemaSql = file_get_contents(APP_ROOT . '/data/schema.sql');
foreach (array_filter(array_map('trim', explode(';', $schemaSql))) as $statement) {
    $pdo->exec($statement);
}
echo "הסכימה מוכנה.\n";

$existingAgents = (int) $pdo->query('SELECT COUNT(*) FROM agents')->fetchColumn();
if ($existingAgents > 0 && !$force) {
    echo "כבר בוצעה מיגרציה (יש $existingAgents סוכנים בטבלה agents).\n";
    echo "להרצה חוזרת עם איפוס מלא: php migrate.php --force\n";
    exit(0);
}

$jsonPath = APP_ROOT . '/data/data.json';
if (!is_file($jsonPath)) {
    echo "שגיאה: לא נמצא data/data.json למיגרציה.\n";
    exit(1);
}

echo "מייבא נתונים מ-data/data.json...\n";
import_seed_into_db($jsonPath);

echo "\nסיכום:\n";
foreach (['agents', 'properties', 'partners', 'leads', 'testimonials'] as $table) {
    $count = (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    echo "  $table: $count שורות\n";
}

echo "\nהמיגרציה הושלמה בהצלחה.\n";
