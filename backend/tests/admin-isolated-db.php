<?php
declare(strict_types=1);
require __DIR__ . '/../src/Core.php';
loadEnv(__DIR__ . '/../.env');

$action = $argv[1] ?? '';
$name = $argv[2] ?? '';
if (!preg_match('/^vmas_codex_audit_[a-z0-9]+$/', $name)) throw new RuntimeException('Invalid isolated audit database name');
$pdo = new PDO('mysql:host=' . envv('DB_HOST', '127.0.0.1') . ';port=' . envv('DB_PORT', '3306') . ';charset=utf8mb4', envv('DB_USER', 'root'), envv('DB_PASSWORD', ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ($action === 'create') {
    $pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$name`");
    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $schema) as $sql) {
        if (trim($sql) !== '') $pdo->exec($sql);
    }
    $stmt = $pdo->prepare("INSERT INTO users (user_name, email, password, role, account_status) VALUES (?, ?, ?, ?, 'ACTIVE')");
    $stmt->execute(['auditadmin_' . $name, 'auditadmin_' . $name . '@example.invalid', password_hash('AuditOnly#2026', PASSWORD_BCRYPT), 'ADMIN']);
    echo "Created isolated audit database $name\n";
} elseif ($action === 'drop') {
    $pdo->exec("DROP DATABASE `$name`");
    echo "Removed isolated audit database $name\n";
} else {
    throw new RuntimeException('Expected create or drop');
}
