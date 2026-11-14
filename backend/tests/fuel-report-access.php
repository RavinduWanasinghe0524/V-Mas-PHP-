<?php
declare(strict_types=1);

require __DIR__ . '/../src/Core.php';
loadEnv(__DIR__ . '/../.env');
require __DIR__ . '/../src/Fuel.php';

if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }

$suffix = bin2hex(random_bytes(5));
$firstName = '__fuel_test_a_' . $suffix;
$secondName = '__fuel_test_b_' . $suffix;
$firstReg = 'FUEL-TEST-A-' . $suffix;
$secondReg = 'FUEL-TEST-B-' . $suffix;

db()->beginTransaction();
try {
    $firstId = insertRow('users', ['user_name' => $firstName, 'email' => $firstName . '@example.invalid', 'password' => 'test', 'role' => 'DRIVER', 'account_status' => 'ACTIVE']);
    $secondId = insertRow('users', ['user_name' => $secondName, 'email' => $secondName . '@example.invalid', 'password' => 'test', 'role' => 'DRIVER', 'account_status' => 'ACTIVE']);
    insertRow('vehicles', ['registration_no' => $firstReg, 'driver_id' => $firstId]);
    insertRow('vehicles', ['registration_no' => $secondReg, 'driver_id' => $secondId]);
    $base = ['vehicle_reg_number' => $firstReg, 'fuel_type' => 'Diesel', 'liters' => 10, 'cost_per_liter' => 100, 'total_cost' => 1000, 'mileage' => 100, 'date' => '2026-01-01', 'driver_username' => $firstName];
    insertRow('fuel_logs', $base + ['status' => 'APPROVED']);
    insertRow('fuel_logs', array_replace($base, ['mileage' => 200, 'date' => '2026-01-02', 'status' => 'APPROVED']));
    insertRow('fuel_logs', array_replace($base, ['mileage' => 300, 'date' => '2026-01-03', 'status' => 'PENDING']));
    insertRow('fuel_logs', array_replace($base, ['mileage' => 400, 'date' => '2026-01-04', 'status' => 'REJECTED']));
    $otherLogId = insertRow('fuel_logs', array_replace($base, ['vehicle_reg_number' => $secondReg, 'driver_username' => $secondName, 'status' => 'APPROVED']));

    $firstDriver = ['id' => $firstId, 'user_name' => $firstName, 'role' => 'DRIVER'];
    $otherLog = byId('fuel_logs', $otherLogId);
    if (fuelOwned($otherLog, $firstDriver)) throw new RuntimeException('Driver can access another driver\'s fuel log');
    if (!fuelOwned(byId('fuel_logs', $otherLogId), ['id' => $secondId, 'user_name' => $secondName, 'role' => 'DRIVER'])) throw new RuntimeException('Driver cannot access own fuel log');
    [$scope, $params] = fuelReportScope($firstDriver);
    $visible = rows("SELECT vehicle_reg_number FROM fuel_logs WHERE status = 'APPROVED' AND COALESCE(is_deleted, 0) = 0" . $scope, $params);
    if (count($visible) !== 2 || array_filter($visible, fn($log) => $log['vehicle_reg_number'] !== $firstReg)) throw new RuntimeException('Driver report scope includes another vehicle');
    $report = vehicleFuelReport($firstReg, $firstDriver);
    if ($report['totalLiters'] !== 20.0 || $report['totalCost'] !== 2000.0 || count($report['fillUps']) !== 2) throw new RuntimeException('Unapproved fuel was counted in report');

    db()->rollBack();
    echo "Fuel ownership and approved-report checks passed; test records rolled back.\n";
} catch (Throwable $error) {
    db()->rollBack();
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
