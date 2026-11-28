<?php
declare(strict_types=1);

require __DIR__ . '/../src/Core.php';
loadEnv(__DIR__ . '/../.env');
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

try {
    $database = db();
    foreach (['users' => [
        'token_version' => 'INT UNSIGNED NOT NULL DEFAULT 0',
    ], 'vehicle_passes' => [
        'is_deleted' => 'BOOLEAN NOT NULL DEFAULT FALSE',
        'deleted_by' => 'VARCHAR(100) NULL',
        'deleted_at' => 'DATETIME NULL',
    ]] as $table => $columns) {
        foreach ($columns as $name => $definition) {
            $exists = scalar('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $name]);
            if (!$exists) $database->exec("ALTER TABLE `$table` ADD COLUMN `$name` $definition");
        }
    }
    $database->exec('CREATE TABLE IF NOT EXISTS request_limits (limit_key CHAR(64) PRIMARY KEY, hits INT UNSIGNED NOT NULL DEFAULT 0, expires_at DATETIME NOT NULL, INDEX idx_request_limits_expiry (expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    echo "Audit migrations applied. Run check-existing-db.php to verify.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Migration failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
