<?php
declare(strict_types=1);

require __DIR__ . '/../src/Core.php';
loadEnv(__DIR__ . '/../.env');

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$admin = row("SELECT id, user_name, role FROM users WHERE role = 'ADMIN' AND account_status = 'ACTIVE' AND is_deleted = 0 LIMIT 1");
if (!$admin) throw new RuntimeException('No active administrator is available for the read-only audit');
$token = jwtIssue($admin);
$base = rtrim($argv[1] ?? 'http://localhost:8000', '/');

$paths = [
    '/api/users/me', '/api/users/pending', '/api/users/drivers', '/api/users/deleted', '/api/users',
    '/api/vehicles/deleted', '/api/vehicles', '/api/trips/deleted', '/api/trips',
    '/api/fuel/summary', '/api/fuel/chart', '/api/fuel/stats', '/api/fuel/efficiency', '/api/fuel/controller/deleted', '/api/fuel/all',
    '/api/services/intervals', '/api/services/stats', '/api/services/upcoming', '/api/services/recent', '/api/services/deleted', '/api/services',
    '/api/alerts/dashboard', '/api/notifications/unread', '/api/notifications', '/api/employees',
    '/api/vehicle-passes/faculties', '/api/vehicle-passes',
];

foreach ([
    ['users', ['/api/users/%d']],
    ['vehicles', ['/api/vehicles/%d', '/api/vehicles/registration/%s', '/api/fuel/vehicle/%s', '/api/services/vehicle/%s'], 'registration_no'],
    ['trips', ['/api/trips/%d']],
    ['fuel_logs', ['/api/fuel/log/%d', '/api/fuel/controller/search/%d']],
    ['service_records', ['/api/services/%d', '/api/services/%d/history']],
    ['vehicle_passes', ['/api/vehicle-passes/%d']],
    ['employees', ['/api/employees/%d']],
] as $entry) {
    [$table, $templates] = $entry;
    $extra = $entry[2] ?? null;
    $active = in_array($table, ['users', 'vehicles', 'trips', 'fuel_logs', 'service_records'], true) ? 'WHERE COALESCE(is_deleted, 0) = 0' : '';
    $record = row("SELECT * FROM `$table` $active ORDER BY id LIMIT 1");
    if (!$record) continue;
    foreach ($templates as $template) {
        $paths[] = sprintf($template, str_contains($template, '%s') ? rawurlencode((string)$record[$extra]) : (int)$record['id']);
    }
}

$failures = [];
foreach ($paths as $path) {
    $request = curl_init($base . $path);
    curl_setopt_array($request, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($request);
    $status = curl_getinfo($request, CURLINFO_RESPONSE_CODE);
    $error = curl_error($request);
    curl_close($request);
    $json = is_string($body) ? json_decode($body, true) : null;
    if ($status !== 200 || !is_array($json) || ($json['success'] ?? false) !== true || !array_key_exists('data', $json)) {
        $failures[] = "$path: HTTP $status" . ($error ? " ($error)" : '') . (is_array($json) ? ' ' . ($json['message'] ?? '') : '');
    }
}

echo count($paths) . ' admin GET endpoints checked; ' . count($failures) . ' failed.' . PHP_EOL;
foreach ($failures as $failure) fwrite(STDERR, $failure . PHP_EOL);
if ($failures) exit(1);
