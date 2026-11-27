<?php
declare(strict_types=1);

require __DIR__ . '/../src/Core.php';
require __DIR__ . '/../src/PassFaculties.php';
require __DIR__ . '/../src/VehiclePasses.php';
require __DIR__ . '/../src/Vehicles.php';
putenv('JWT_SECRET=smoke-test-secret-that-is-long-enough-for-hs256');

function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$token = jwtIssue(['id' => 7, 'user_name' => 'tester', 'role' => 'DRIVER']);
$claims = jwtRead($token);
check($claims !== null && $claims['uid'] === 7 && $claims['role'] === 'DRIVER', 'JWT round trip failed');
check(jwtRead($token . 'x') === null, 'JWT tamper check failed');
check(camel('vehicle_reg_number') === 'vehicleRegNumber', 'Field conversion failed');
check(fields(['currentMileageKm' => 123], ['current_mileage_km'])['current_mileage_km'] === 123, 'Field mapping failed');
check(dto(['is_deleted' => 0], ['is_deleted' => 'deleted'], ['is_deleted'])['deleted'] === false, 'Boolean mapping failed');
check(vehicleEnum('status', 3) === 'AVAILABLE', 'Legacy vehicle status mapping failed');
check(vehicleEnum('fuel_type', 'SUPER_DIESEL', true) === 1, 'Legacy fuel type write mapping failed');
try { vehicleEnum('status', 'INVALID'); throw new RuntimeException('Invalid vehicle status accepted'); }
catch (ApiError $error) { check($error->status === 400, 'Invalid vehicle status response failed'); }
check(count(PASS_FACULTIES) === 8, 'Expected eight faculties');
foreach (PASS_FACULTIES as $faculty => $departments) {
    check($faculty !== '' && count($departments) > 0 && count($departments) === count(array_unique($departments)), 'Invalid faculty departments');
}
putenv('PASS_HOD_EMAILS_JSON={"Computing|Software Engineering":"hod@example.edu"}');
putenv('PASS_DEAN_EMAILS_JSON={"Computing":"dean@example.edu"}');
check(passApproverEmail('HOD', 'Computing', 'Software Engineering') === 'hod@example.edu', 'HOD routing failed');
check(passApproverEmail('DEAN', 'Computing', 'Software Engineering') === 'dean@example.edu', 'Dean routing failed');
echo "Core smoke checks passed\n";
