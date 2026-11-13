<?php
declare(strict_types=1);

require __DIR__ . '/../src/Core.php';
loadEnv(__DIR__ . '/../.env');

if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }

try {
    $sql = file_get_contents(__DIR__ . '/../database/create-notification-reads.sql');
    if ($sql === false) throw new RuntimeException('Notification schema file is missing');
    db()->exec($sql);
    echo "notification_reads is ready in " . scalar('SELECT DATABASE()') . ".\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Notification setup failed: ' . $error->getMessage() . "\n");
    exit(1);
}
