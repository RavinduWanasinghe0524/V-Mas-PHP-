<?php
declare(strict_types=1);

function loadEnv(string $file): void {
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        if (getenv($key) === false) putenv($key . '=' . trim($value, " \t\n\r\0\x0B\"'"));
    }
}

function envv(string $key, ?string $default = null): ?string {
    $value = getenv($key);
    return $value === false ? $default : $value;
}

function db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $host = envv('DB_HOST', '127.0.0.1');
    $port = envv('DB_PORT', '3306');
    $name = envv('DB_NAME', 'vmas_db');
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", envv('DB_USER', 'root'), envv('DB_PASSWORD', ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function query(string $sql, array $params = []): PDOStatement {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function rows(string $sql, array $params = []): array { return query($sql, $params)->fetchAll(); }
function row(string $sql, array $params = []): ?array { $value = query($sql, $params)->fetch(); return $value === false ? null : $value; }
function scalar(string $sql, array $params = []): mixed { return query($sql, $params)->fetchColumn(); }

final class ApiError extends RuntimeException {
    public function __construct(string $message, public int $status = 400) { parent::__construct($message); }
}

function fail(string $message, int $status = 400): never { throw new ApiError($message, $status); }
function requireField(array $data, string $name): mixed {
    $value = $data[$name] ?? null;
    if ($value === null || (is_string($value) && trim($value) === '')) fail("$name is required");
    return $value;
}

function body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return $_POST;
    $data = json_decode($raw, true);
    if (!is_array($data)) fail('Invalid JSON body');
    return $data;
}

function reply(mixed $data = null, string $message = 'Success', int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'message' => $message, 'data' => $data], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function errorReply(string $message, int $status): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $message, 'data' => null]);
    exit;
}

function now(): string { return date('Y-m-d H:i:s'); }
function camel(string $name): string { return preg_replace_callback('/_([a-z])/', fn($m) => strtoupper($m[1]), $name); }
function snake(string $name): string { return strtolower(preg_replace('/[A-Z]/', '_$0', $name)); }

function dto(array $record, array $aliases = [], array $bools = []): array {
    $result = [];
    foreach ($record as $key => $value) {
        $target = $aliases[$key] ?? camel($key);
        $result[$target] = in_array($key, $bools, true) ? (bool)$value : $value;
    }
    return $result;
}

function fields(array $input, array $allowed, array $aliases = []): array {
    $out = [];
    foreach ($allowed as $key) {
        $inputKey = $aliases[$key] ?? camel($key);
        if (array_key_exists($inputKey, $input)) $out[$key] = $input[$inputKey];
    }
    return $out;
}

function insertRow(string $table, array $values): int {
    if (!$values) fail('No fields supplied');
    $columns = array_keys($values);
    $names = implode(', ', array_map(fn($s) => "`$s`", $columns));
    $marks = implode(', ', array_fill(0, count($columns), '?'));
    query("INSERT INTO `$table` ($names) VALUES ($marks)", array_values($values));
    return (int)db()->lastInsertId();
}

function updateRow(string $table, int $id, array $values): void {
    if (!$values) return;
    $sets = implode(', ', array_map(fn($s) => "`$s` = ?", array_keys($values)));
    query("UPDATE `$table` SET $sets WHERE id = ?", [...array_values($values), $id]);
}

function byId(string $table, int $id, bool $includeDeleted = false): array {
    $soft = ['users', 'vehicles', 'trips', 'service_records'];
    $where = (!$includeDeleted && in_array($table, $soft, true)) ? ' AND is_deleted = 0' : '';
    if (!$includeDeleted && $table === 'fuel_logs') $where = ' AND (is_deleted = 0 OR is_deleted IS NULL)';
    return row("SELECT * FROM `$table` WHERE id = ?$where", [$id]) ?? fail('Record not found', 404);
}

function allRows(string $table, bool $deleted = false): array {
    $column = $table === 'fuel_logs' ? 'is_deleted' : 'is_deleted';
    return rows("SELECT * FROM `$table` WHERE COALESCE(`$column`, 0) = ? ORDER BY id DESC", [(int)$deleted]);
}

function b64url(string $bytes): string { return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '='); }
function jwtSecret(): string {
    $secret = envv('JWT_SECRET', '');
    if (strlen((string)$secret) < 32 || str_starts_with((string)$secret, 'replace-')) throw new RuntimeException('JWT_SECRET must be configured with at least 32 random bytes');
    return $secret;
}
function jwtIssue(array $user): string {
    $header = b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = b64url(json_encode(['sub' => $user['user_name'], 'uid' => (int)$user['id'], 'role' => $user['role'], 'iat' => time(), 'exp' => time() + (int)envv('JWT_TTL', '86400')]));
    $signature = b64url(hash_hmac('sha256', "$header.$payload", jwtSecret(), true));
    return "$header.$payload.$signature";
}
function jwtRead(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$h, $p, $s] = $parts;
    $header = json_decode(base64_decode(strtr($h, '-_', '+/')), true);
    if (($header['alg'] ?? '') !== 'HS256') return null;
    $expected = b64url(hash_hmac('sha256', "$h.$p", jwtSecret(), true));
    if (!hash_equals($expected, $s)) return null;
    $payload = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
    return is_array($payload) && ($payload['exp'] ?? 0) > time() ? $payload : null;
}

function actor(): array {
    static $current;
    if ($current !== null) return $current;
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) fail('Authentication required', 401);
    $claims = jwtRead($m[1]);
    if (!$claims) fail('Invalid or expired token', 401);
    $current = row('SELECT * FROM users WHERE id = ? AND is_deleted = 0 AND account_status = ?', [(int)($claims['uid'] ?? 0), 'ACTIVE']);
    if (!$current) fail('Account is unavailable', 401);
    return $current;
}
function requireRole(string ...$roles): array {
    $user = actor();
    if (!in_array($user['role'], $roles, true)) fail('Access denied', 403);
    return $user;
}

function route(string $method, string $pattern, callable $handler): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) return;
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $regex = '#^' . preg_replace('/\{([a-zA-Z][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $pattern) . '/?$#D';
    if (!preg_match($regex, $path, $matches)) return;
    $params = [];
    foreach ($matches as $key => $value) if (is_string($key)) $params[$key] = rawurldecode($value);
    $handler($params);
    exit;
}

function notify(string $recipient, string $message, string $type): void {
    query('INSERT INTO notifications (vehicle_reg_number, message, type, created_at, is_read) VALUES (?, ?, ?, ?, 0)', [$recipient, $message, $type, now()]);
}

function streamUpload(string $path): never {
    if (envv('UPLOAD_DISK', 'local') === 's3') {
        if ($path === '') fail('File not found', 404);
        $content = s3Request('GET', $path);
        header('Content-Type: ' . (match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) { 'pdf' => 'application/pdf', 'jpg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', default => 'application/octet-stream' }));
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }
    $root = realpath(__DIR__ . '/../storage');
    $resolved = realpath(__DIR__ . '/../storage/' . ltrim($path, '/'));
    if (!$root || !$resolved || !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR) || !is_file($resolved)) fail('File not found', 404);
    header('Content-Type: ' . (mime_content_type($resolved) ?: 'application/octet-stream'));
    header('Content-Length: ' . filesize($resolved));
    readfile($resolved);
    exit;
}

function saveUpload(string $scope, string $id): string {
    $file = $_FILES['file'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) fail('File upload failed');
    if ($file['size'] > 10 * 1024 * 1024) fail('File exceeds 10 MB');
    $mime = mime_content_type($file['tmp_name']) ?: '';
    $extensions = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) fail('Only PDF and image files are allowed');
    $relative = "$scope/$id/" . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
    if (envv('UPLOAD_DISK', 'local') === 's3') {
        s3Request('PUT', $relative, (string)file_get_contents($file['tmp_name']), $mime);
        return $relative;
    }
    $target = __DIR__ . '/../storage/' . $relative;
    if (!is_dir(dirname($target))) mkdir(dirname($target), 0770, true);
    if (!move_uploaded_file($file['tmp_name'], $target)) fail('Unable to save upload', 500);
    return $relative;
}

function s3Request(string $method, string $key, string $content = '', string $mime = 'application/octet-stream'): string {
    $bucket = envv('AWS_S3_BUCKET', ''); $region = envv('AWS_REGION', 'ap-southeast-1');
    $access = envv('AWS_ACCESS_KEY_ID', ''); $secret = envv('AWS_SECRET_ACCESS_KEY', '');
    if (!$bucket || !$access || !$secret) fail('S3 storage is not configured', 500);
    $host = "$bucket.s3.$region.amazonaws.com";
    $uri = '/' . implode('/', array_map('rawurlencode', explode('/', ltrim($key, '/'))));
    $date = gmdate('Ymd'); $timestamp = gmdate('Ymd\THis\Z');
    $payloadHash = hash('sha256', $content);
    $session = envv('AWS_SESSION_TOKEN', '');
    $headers = ['host' => $host, 'x-amz-content-sha256' => $payloadHash, 'x-amz-date' => $timestamp];
    if ($session) $headers['x-amz-security-token'] = $session;
    ksort($headers);
    $canonicalHeaders = '';
    foreach ($headers as $name => $value) $canonicalHeaders .= "$name:$value\n";
    $signedHeaders = implode(';', array_keys($headers));
    $canonical = "$method\n$uri\n\n$canonicalHeaders\n$signedHeaders\n$payloadHash";
    $scope = "$date/$region/s3/aws4_request";
    $stringToSign = "AWS4-HMAC-SHA256\n$timestamp\n$scope\n" . hash('sha256', $canonical);
    $kDate = hash_hmac('sha256', $date, 'AWS4' . $secret, true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', 's3', $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);
    $requestHeaders = ['Authorization: AWS4-HMAC-SHA256 Credential=' . $access . '/' . $scope . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature, 'Content-Type: ' . $mime];
    foreach ($headers as $name => $value) $requestHeaders[] = "$name: $value";
    $handle = curl_init("https://$host$uri");
    curl_setopt_array($handle, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $requestHeaders, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
    if ($method === 'PUT') curl_setopt($handle, CURLOPT_POSTFIELDS, $content);
    $response = curl_exec($handle); $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    if ($response === false || $status < 200 || $status >= 300) {
        error_log('S3 request failed: ' . curl_error($handle) . " (HTTP $status)");
        curl_close($handle); fail('Storage request failed', 500);
    }
    curl_close($handle);
    return $response;
}

function sendEmail(string $to, string $subject, string $html): void {
    $key = envv('SENDGRID_API_KEY', '');
    if (!$key) return;
    $payload = json_encode(['personalizations' => [['to' => [['email' => $to]]]], 'from' => ['email' => envv('MAIL_FROM_EMAIL', ''), 'name' => envv('MAIL_FROM_NAME', 'V-MAS')], 'subject' => $subject, 'content' => [['type' => 'text/html', 'value' => $html]]]);
    $handle = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    curl_exec($handle);
    curl_close($handle);
}

function sendSms(string $to, string $message): void {
    $sid = envv('TWILIO_ACCOUNT_SID', ''); $token = envv('TWILIO_AUTH_TOKEN', ''); $from = envv('TWILIO_PHONE_NUMBER', '');
    if (!$sid || !$token || !$from || !$to) return;
    $handle = curl_init("https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json");
    curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['To' => $to, 'From' => $from, 'Body' => $message]), CURLOPT_USERPWD => "$sid:$token", CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    curl_exec($handle);
    curl_close($handle);
}
