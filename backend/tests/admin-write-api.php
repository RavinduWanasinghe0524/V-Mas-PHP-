<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../src/Core.php';
loadEnv(__DIR__ . '/../.env');
$database = (string)envv('DB_NAME', '');
if (!preg_match('/^vmas_codex_audit_[a-z0-9]+$/', $database)) {
    throw new RuntimeException('Admin write test requires an isolated vmas_codex_audit_* database');
}
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8010', '/');
$token = '';
$checked = 0;
function api(string $method, string $path, ?array $data = null, int $expected = 200): array {
    global $base, $token, $checked;
    $handle = curl_init($base . $path);
    $headers = ['Content-Type: application/json'];
    if ($token !== '') $headers[] = 'Authorization: Bearer ' . $token;
    curl_setopt_array($handle, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 10]);
    if ($data !== null) curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($data));
    $raw = curl_exec($handle);
    $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $error = curl_error($handle);
    curl_close($handle);
    $result = is_string($raw) ? json_decode($raw, true) : null;
    if ($status !== $expected || !is_array($result) || ($result['success'] ?? null) !== ($expected < 400)) {
        throw new RuntimeException("$method $path expected $expected, got $status: $error " . substr((string)$raw, 0, 400));
    }
    $checked++;
    return $result['data'] ?? [];
}
function equal(mixed $actual, mixed $expected, string $label): void {
    if ($actual !== $expected) throw new RuntimeException("$label: expected " . var_export($expected, true) . ', got ' . var_export($actual, true));
}

api('GET', '/api/health');
$login = api('POST', '/api/auth/login', ['userName' => 'auditadmin_' . $database, 'password' => 'AuditOnly#2026']);
$token = $login['token'];
$users = api('GET', '/api/users');
equal(count($users), 1, 'Isolated database user count');

$driver = api('POST', '/api/users', ['userName' => 'auditdriver', 'email' => 'auditdriver@example.invalid', 'password' => 'AuditOnly#2026', 'role' => 'DRIVER'], 201);
$uid = (int)$driver['id'];
equal($driver['role'], 'DRIVER', 'Created user role');
$driver = api('PUT', "/api/users/$uid", ['phoneNumber' => '1234567890']);
equal($driver['phoneNumber'], '1234567890', 'Edited user phone');
equal(api('PATCH', "/api/users/$uid/reject")['accountStatus'], 'INACTIVE', 'Rejected user');
equal(api('PATCH', "/api/users/$uid/approve")['accountStatus'], 'ACTIVE', 'Approved user');

$vehicle = api('POST', '/api/vehicles', ['registrationNo' => 'AUDIT-VEH-1', 'manufacturer' => 'Test', 'model' => 'Car', 'status' => 'SERVICE', 'fuelType' => 'DIESEL', 'currentMileageKm' => 50], 201);
$vid = (int)$vehicle['id'];
equal($vehicle['status'], 'SERVICE', 'Created vehicle status');
$vehicle = api('PUT', "/api/vehicles/$vid", ['status' => 'AVAILABLE', 'model' => 'Car Updated']);
equal($vehicle['status'], 'AVAILABLE', 'Edited vehicle status');
equal(api('PATCH', "/api/vehicles/$vid/assign-driver", ['driverUsername' => 'auditdriver'])['driverUsername'], 'auditdriver', 'Assigned driver');
api('POST', '/api/vehicles/bulk-mileage', [['id' => $vid, 'currentMileageKm' => 100]]);
equal((int)api('GET', "/api/vehicles/$vid")['currentMileageKm'], 100, 'Bulk mileage');

$service = api('POST', '/api/services', ['vehicleRegNumber' => 'AUDIT-VEH-1', 'serviceType' => 'TIRE_ROTATION', 'serviceDate' => date('Y-m-d'), 'currentMileageKm' => 110, 'serviceCost' => 1000], 201);
$sid = (int)$service['id'];
equal($service['status'], 'APPROVED', 'Created service status');
$service = api('PUT', "/api/services/$sid", ['serviceCost' => 1200]);
equal((float)$service['serviceCost'], 1200.0, 'Edited service cost');
equal(count(api('GET', "/api/services/$sid/history")), 1, 'Service edit history');
equal(count(api('POST', '/api/services/filter', ['vehicleRegNumber' => 'AUDIT-VEH-1'])), 1, 'Service filter');
api('DELETE', "/api/services/$sid");
equal(count(api('GET', '/api/services/deleted')), 1, 'Deleted service list');
equal(api('PATCH', "/api/services/$sid/restore")['deleted'], false, 'Restored service');

$fuel = api('POST', '/api/fuel/controller/add', ['vehicleRegNumber' => 'AUDIT-VEH-1', 'fuelType' => 'Diesel', 'liters' => 10, 'costPerLiter' => 100, 'mileage' => 120, 'date' => date('Y-m-d')], 201);
$fid = (int)$fuel['id'];
equal((float)$fuel['totalCost'], 1000.0, 'Fuel total');
equal((float)api('PUT', "/api/fuel/controller/$fid", ['liters' => 11])['totalCost'], 1100.0, 'Edited fuel total');
equal(api('PATCH', "/api/fuel/controller/$fid/reject")['status'], 'REJECTED', 'Rejected fuel');
equal(api('PATCH', "/api/fuel/controller/$fid/approve")['status'], 'APPROVED', 'Approved fuel');
api('DELETE', "/api/fuel/controller/$fid");
equal(count(api('GET', '/api/fuel/controller/deleted')), 1, 'Deleted fuel list');
equal(api('PATCH', "/api/fuel/controller/restore/$fid")['isDeleted'], false, 'Restored fuel');

$trip = api('POST', '/api/trips', ['driverUsername' => 'auditdriver', 'vehicleRegNumber' => 'AUDIT-VEH-1', 'destination' => 'Audit destination', 'scheduledDate' => date('Y-m-d')], 201);
$tid = (int)$trip['id'];
equal($trip['status'], 'ASSIGNED', 'Created trip');
equal(api('PUT', "/api/trips/$tid", ['destination' => 'Updated destination'])['destination'], 'Updated destination', 'Edited trip');
$driverLogin = api('POST', '/api/auth/login', ['userName' => 'auditdriver', 'password' => 'AuditOnly#2026']);
$adminToken = $token;
$token = $driverLogin['token'];
equal(api('PATCH', "/api/trips/$tid/start")['status'], 'STARTED', 'Started trip');
equal(api('PATCH', "/api/trips/$tid/complete")['status'], 'COMPLETED', 'Completed trip');
$token = $adminToken;
api('DELETE', "/api/trips/$tid/delete");
equal(count(api('GET', '/api/trips/deleted')), 1, 'Deleted trip list');
equal(api('PATCH', "/api/trips/$tid/restore")['deleted'], false, 'Restored trip');

$pass = api('POST', '/api/vehicle-passes/apply', ['formType' => 'SUSL/GA/TD/03', 'faculty' => 'Computing', 'dept' => 'Software Engineering', 'name' => 'API Pass Test', 'mob' => '0000000000', 'reg' => 'AUDIT-PASS-VEH', 'd1' => date('Y-m-d')], 201);
$pid = (int)$pass['id'];
equal($pass['status'], 'PENDING_HOD', 'New pass status');
equal(count(api('GET', '/api/vehicle-passes')), 1, 'Pass list');
api('PATCH', "/api/vehicle-passes/$pid/status", ['status' => 'APPROVED'], 409);
equal(api('PATCH', "/api/vehicle-passes/$pid/status", ['status' => 'REJECTED'])['status'], 'REJECTED', 'Admin pass rejection');
api('DELETE', "/api/vehicle-passes/$pid");
equal(count(api('GET', '/api/vehicle-passes')), 0, 'Pass deletion');

api('DELETE', "/api/vehicles/$vid");
equal(count(api('GET', '/api/vehicles/deleted')), 1, 'Deleted vehicle list');
equal(api('PATCH', "/api/vehicles/$vid/restore")['deleted'], false, 'Restored vehicle');
api('DELETE', "/api/users/$uid");
equal(count(api('GET', '/api/users/deleted')), 1, 'Deleted user list');
equal(api('PATCH', "/api/users/$uid/restore")['deleted'], false, 'Restored user');

echo "$checked isolated admin API operations passed.\n";
