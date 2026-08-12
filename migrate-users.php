<?php
/**
 * מיגרציה חד-פעמית: יוצרת טבלת users ומעבירה אליה את פרטי ההתחברות הקיימים
 * (settings.admin_user/admin_hash + agents.username/password_hash/last_login_at),
 * ואז מסירה את העמודות הישנות. הרצה: php migrate-users.php
 * הרצה חוזרת עם איפוס מלא של users: php migrate-users.php --force
 */

require __DIR__ . '/includes/config.php';

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetch();
}

$force = in_array('--force', $argv, true);
$pdo = db();

$usersTableExists = (bool) $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
if ($usersTableExists && !$force) {
    echo "טבלת users כבר קיימת — נראה שהמיגרציה הזו כבר בוצעה.\n";
    echo "להרצה חוזרת (מוחקת ובונה מחדש את users): php migrate-users.php --force\n";
    exit(0);
}

if ($usersTableExists && $force) {
    echo "מוחק טבלת users קיימת (--force)...\n";
    $pdo->exec('DROP TABLE users');
}

echo "יוצר טבלת users...\n";
$pdo->exec("
    CREATE TABLE users (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      username VARCHAR(100) NOT NULL,
      password_hash VARCHAR(255) NOT NULL,
      role ENUM('admin','agent') NOT NULL,
      agent_id INT UNSIGNED NULL,
      active TINYINT(1) NOT NULL DEFAULT 1,
      last_login_at DATETIME NULL,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY uniq_users_username (username),
      UNIQUE KEY uniq_users_agent (agent_id),
      CONSTRAINT fk_users_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$pdo->beginTransaction();
try {
    $adminCount = 0;
    if (column_exists($pdo, 'settings', 'admin_hash')) {
        $admin = $pdo->query('SELECT admin_user, admin_hash FROM settings WHERE id = 1')->fetch();
        if ($admin && !empty($admin['admin_hash'])) {
            $pdo->prepare("INSERT INTO users (username, password_hash, role, active, created_at) VALUES (?, ?, 'admin', 1, NOW())")
                ->execute([$admin['admin_user'], $admin['admin_hash']]);
            $adminCount = 1;
        }
    }

    $agentCount = 0;
    if (column_exists($pdo, 'agents', 'username')) {
        $agents = $pdo->query('SELECT id, username, password_hash, last_login_at FROM agents WHERE username IS NOT NULL')->fetchAll();
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, agent_id, active, last_login_at, created_at) VALUES (?, ?, 'agent', ?, 1, ?, NOW())");
        foreach ($agents as $a) {
            $stmt->execute([$a['username'], $a['password_hash'], $a['id'], $a['last_login_at']]);
            $agentCount++;
        }
    }

    $pdo->commit();
    echo "הועברו $adminCount משתמשי admin, $agentCount משתמשי agent.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    echo 'שגיאה במהלך ההעברה, בוטל: ' . $e->getMessage() . "\n";
    echo "טבלת users נשארה ריקה — עמודות ה-legacy לא הוסרו. ניתן להריץ שוב אחרי תיקון.\n";
    exit(1);
}

echo "מסיר עמודות התחברות ישנות...\n";
if (column_exists($pdo, 'agents', 'username')) {
    $pdo->exec('ALTER TABLE agents DROP COLUMN username, DROP COLUMN password_hash, DROP COLUMN last_login_at');
}
if (column_exists($pdo, 'settings', 'admin_hash')) {
    $pdo->exec('ALTER TABLE settings DROP COLUMN admin_user, DROP COLUMN admin_hash');
}

$total = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
echo "\nהמיגרציה הושלמה. סה\"כ $total משתמשים בטבלת users.\n";
