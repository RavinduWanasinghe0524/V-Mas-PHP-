<?php
declare(strict_types=1);

require __DIR__ . '/../src/Core.php';
loadEnv(__DIR__ . '/../.env');
require __DIR__ . '/../src/Misc.php';

if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }

$driverA = ['id' => 900000001, 'user_name' => '__notification_test_a__', 'role' => 'DRIVER'];
$driverB = ['id' => 900000002, 'user_name' => '__notification_test_b__', 'role' => 'DRIVER'];
$admin = ['id' => 900000003, 'user_name' => '__notification_test_admin__', 'role' => 'ADMIN'];
$controller = ['id' => 900000004, 'user_name' => '__notification_test_controller__', 'role' => 'CONTROLLER'];

db()->beginTransaction();
try {
    query('INSERT INTO notifications (vehicle_reg_number, message, type, created_at, is_read) VALUES (?, ?, ?, ?, 0)', ['USER-' . $driverA['user_name'], 'Notification access test', 'TEST', now()]);
    $personalId = (int)db()->lastInsertId();
    query('INSERT INTO notifications (vehicle_reg_number, message, type, created_at, is_read) VALUES (?, ?, ?, ?, 0)', ['VEH-NOTIFICATION-TEST', 'Shared notification access test', 'TEST', now()]);
    $staffId = (int)db()->lastInsertId();

    if (count(notificationsForUser($driverA, false, $personalId)) !== 1 || notificationsForUser($driverB, false, $personalId) || notificationsForUser($admin, false, $personalId)) throw new RuntimeException('Personal notification visibility failed');
    if (count(notificationsForUser($admin, false, $staffId)) !== 1 || count(notificationsForUser($controller, false, $staffId)) !== 1 || notificationsForUser($driverA, false, $staffId)) throw new RuntimeException('Staff notification visibility failed');

    query('INSERT INTO notification_reads (notification_id, user_id) VALUES (?, ?)', [$staffId, $admin['id']]);
    $adminRead = notificationsForUser($admin, false, $staffId)[0]['is_read'];
    $controllerRead = notificationsForUser($controller, false, $staffId)[0]['is_read'];
    if ($adminRead != 1 || $controllerRead != 0) throw new RuntimeException('Per-user read state failed');
    db()->rollBack();
    echo "Notification access checks passed; test records rolled back.\n";
} catch (Throwable $error) {
    db()->rollBack();
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
