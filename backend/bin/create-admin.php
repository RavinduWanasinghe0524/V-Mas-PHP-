<?php
declare(strict_types=1);

require __DIR__ . '/../src/Core.php';
loadEnv(__DIR__ . '/../.env');
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

function prompt(string $label): string { fwrite(STDOUT, $label); return trim((string)fgets(STDIN)); }

$name = prompt('Admin username: ');
$email = prompt('Admin email: ');
$password = prompt('Admin password (at least 8 characters): ');
if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
    fwrite(STDERR, "Invalid input.\n"); exit(1);
}
if (row('SELECT id FROM users WHERE user_name = ? OR email = ?', [$name, $email])) {
    fwrite(STDERR, "Username or email already exists.\n"); exit(1);
}
insertRow('users', ['user_name' => $name, 'email' => $email, 'password' => password_hash($password, PASSWORD_BCRYPT), 'role' => 'ADMIN', 'account_status' => 'ACTIVE']);
fwrite(STDOUT, "Administrator created.\n");

