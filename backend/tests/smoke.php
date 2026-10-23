<?php
declare(strict_types=1);

require __DIR__ . '/../src/Core.php';
putenv('JWT_SECRET=smoke-test-secret-that-is-long-enough-for-hs256');

function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$token = jwtIssue(['id' => 7, 'user_name' => 'tester', 'role' => 'DRIVER']);
$claims = jwtRead($token);
check($claims !== null && $claims['uid'] === 7 && $claims['role'] === 'DRIVER', 'JWT round trip failed');
check(jwtRead($token . 'x') === null, 'JWT tamper check failed');
check(camel('vehicle_reg_number') === 'vehicleRegNumber', 'Field conversion failed');
check(fields(['currentMileageKm' => 123], ['current_mileage_km'])['current_mileage_km'] === 123, 'Field mapping failed');
check(dto(['is_deleted' => 0], ['is_deleted' => 'deleted'], ['is_deleted'])['deleted'] === false, 'Boolean mapping failed');
echo "Core smoke checks passed\n";
