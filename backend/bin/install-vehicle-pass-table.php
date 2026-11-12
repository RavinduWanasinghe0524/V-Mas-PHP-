<?php
declare(strict_types=1);

require __DIR__ . '/../src/Core.php';
loadEnv(__DIR__ . '/../.env');

if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }

try {
    $exists = (int)scalar("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicle_passes'");
    if ($exists) {
        echo "vehicle_passes already exists; no changes made.\n";
        exit(0);
    }
    $sql = file_get_contents(__DIR__ . '/../database/create-vehicle-passes.sql');
    if ($sql === false) throw new RuntimeException('Vehicle pass schema file is missing');
    db()->exec($sql);
    echo "Created vehicle_passes in " . scalar('SELECT DATABASE()') . ".\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Vehicle pass table setup failed: ' . $error->getMessage() . "\n");
    exit(1);
}
