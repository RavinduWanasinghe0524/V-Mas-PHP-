<?php
declare(strict_types=1);

require __DIR__ . '/../src/Core.php';
loadEnv(__DIR__ . '/../.env');
date_default_timezone_set('Asia/Colombo');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = array_filter(array_map('trim', explode(',', (string)envv('CORS_ALLOWED_ORIGINS', 'http://localhost:3000'))));
if ($origin && in_array($origin, $allowed, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

try {
    route('GET', '/api/health', fn() => reply(['status' => 'UP']));
    require __DIR__ . '/../src/AuthUsers.php';
    require __DIR__ . '/../src/Vehicles.php';
    require __DIR__ . '/../src/Trips.php';
    require __DIR__ . '/../src/Fuel.php';
    require __DIR__ . '/../src/Services.php';
    require __DIR__ . '/../src/Misc.php';
    require __DIR__ . '/../src/VehiclePasses.php';
    errorReply('Route not found', 404);
} catch (ApiError $e) {
    errorReply($e->getMessage(), $e->status);
} catch (PDOException $e) {
    error_log((string)$e);
    errorReply('Database operation failed', 500);
} catch (Throwable $e) {
    error_log((string)$e);
    errorReply('Internal server error', 500);
}
