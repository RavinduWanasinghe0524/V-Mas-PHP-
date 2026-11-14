<?php
declare(strict_types=1);

require __DIR__ . '/../src/Core.php';
loadEnv(__DIR__ . '/../.env');

$errors = [];
$warnings = [];
if (envv('APP_ENV') !== 'production') $errors[] = 'APP_ENV must be production';
foreach (['APP_URL', 'FRONTEND_URL'] as $key) {
    $url = (string)envv($key, '');
    if (!filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') $errors[] = "$key must be an HTTPS URL";
}
$frontendOrigin = rtrim((string)envv('FRONTEND_URL', ''), '/');
$origins = array_filter(array_map('trim', explode(',', (string)envv('CORS_ALLOWED_ORIGINS', ''))));
if (!$origins || !in_array($frontendOrigin, $origins, true)) $errors[] = 'CORS_ALLOWED_ORIGINS must include FRONTEND_URL';
foreach ($origins as $origin) if (!filter_var($origin, FILTER_VALIDATE_URL) || parse_url($origin, PHP_URL_SCHEME) !== 'https') $errors[] = 'All CORS origins must be HTTPS URLs';
foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $key) if (trim((string)envv($key, '')) === '') $errors[] = "$key is required";
try { jwtSecret(); } catch (Throwable $error) { $errors[] = $error->getMessage(); }
$disk = envv('UPLOAD_DISK', 'local');
if (!in_array($disk, ['local', 's3'], true)) $errors[] = 'UPLOAD_DISK must be local or s3';
if ($disk === 's3') foreach (['AWS_REGION', 'AWS_S3_BUCKET', 'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY'] as $key) if (!envv($key, '')) $errors[] = "$key is required for S3";
if ($disk === 'local') $warnings[] = 'Local uploads need persistent storage and backups';
if (!envv('SENDGRID_API_KEY', '') || !filter_var(envv('MAIL_FROM_EMAIL', ''), FILTER_VALIDATE_EMAIL)) $warnings[] = 'Email delivery is not configured';
foreach (['PASS_HOD_EMAILS_JSON', 'PASS_DEAN_EMAILS_JSON'] as $key) {
    $map = json_decode((string)envv($key, '{}'), true);
    if (!is_array($map) || !$map) $warnings[] = "$key needs approver addresses";
    elseif (count(array_filter($map, fn($email) => !filter_var($email, FILTER_VALIDATE_EMAIL)))) $errors[] = "$key contains an invalid email address";
}
foreach ($errors as $message) fwrite(STDERR, "ERROR: $message\n");
foreach ($warnings as $message) fwrite(STDERR, "WARNING: $message\n");
if ($errors) exit(1);
echo "Production configuration passed.\n";
