<?php
declare(strict_types=1);

const USER_FIELDS = ['user_name', 'email', 'role', 'account_status', 'profile_picture', 'phone_number', 'gender', 'nic', 'date_of_birth', 'license_number', 'license_expiry_date', 'license_document_path', 'date_joined', 'experience', 'address'];

function userDto(array $user): array {
    unset($user['password']);
    return dto($user, ['is_deleted' => 'deleted'], ['is_deleted']);
}

function createUser(array $data, bool $pending): array {
    $name = trim((string)requireField($data, 'userName'));
    $email = trim((string)requireField($data, 'email'));
    $password = (string)requireField($data, 'password');
    $role = $data['role'] ?? 'DRIVER';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Invalid email');
    if (strlen($password) < 8) fail('Password must have at least 8 characters');
    if (!in_array($role, ['ADMIN', 'CONTROLLER', 'DRIVER'], true)) fail('Invalid role');
    if (row('SELECT id FROM users WHERE user_name = ? OR email = ?', [$name, $email])) fail('Username or email already exists', 409);
    $values = fields($data, USER_FIELDS);
    $values['user_name'] = $name;
    $values['email'] = $email;
    $values['role'] = $role;
    $values['password'] = password_hash($password, PASSWORD_BCRYPT);
    $values['account_status'] = $pending ? 'PENDING' : 'ACTIVE';
    $id = insertRow('users', $values);
    return byId('users', $id);
}

route('POST', '/api/auth/register', function () {
    createUser(body(), true);
    reply(['token' => null, 'user' => null], 'Registration submitted for approval', 201);
});

route('POST', '/api/auth/login', function () {
    $data = body();
    $login = (string)($data['userName'] ?? $data['username'] ?? '');
    $user = row('SELECT * FROM users WHERE user_name = ? OR email = ?', [$login, $login]);
    if (!$user || !password_verify((string)($data['password'] ?? ''), $user['password'])) fail('Invalid credentials', 401);
    if ((int)$user['is_deleted'] || $user['account_status'] !== 'ACTIVE') fail('Account is not active', 403);
    reply(['token' => jwtIssue($user), 'user' => userDto($user)], 'Login successful');
});

route('POST', '/api/auth/logout', fn() => reply(null, 'Logged out'));

route('POST', '/api/auth/forgot-password', function () {
    $email = trim((string)(body()['email'] ?? ''));
    $user = row('SELECT * FROM users WHERE email = ? AND is_deleted = 0', [$email]);
    if ($user) {
        $token = bin2hex(random_bytes(32));
        query('INSERT INTO password_reset_tokens (token, user_id, expires_at, used) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE), 0)', [hash('sha256', $token), $user['id']]);
        $link = rtrim((string)envv('FRONTEND_URL', 'http://localhost:3000'), '/') . '/reset-password?token=' . urlencode($token);
        sendEmail($email, 'Reset your V-MAS password', '<p>Use this link to reset your password:</p><p><a href="' . htmlspecialchars($link, ENT_QUOTES) . '">Reset password</a></p>');
    }
    reply(null, 'If the email exists, a password reset link has been sent');
});

route('POST', '/api/auth/reset-password', function () {
    $data = body();
    $token = (string)requireField($data, 'token');
    $password = (string)requireField($data, 'newPassword');
    if (strlen($password) < 8) fail('Password must have at least 8 characters');
    $record = row('SELECT * FROM password_reset_tokens WHERE token = ? AND used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1', [hash('sha256', $token)]);
    if (!$record) fail('Reset link is invalid or expired');
    db()->beginTransaction();
    try {
        updateRow('users', (int)$record['user_id'], ['password' => password_hash($password, PASSWORD_BCRYPT)]);
        updateRow('password_reset_tokens', (int)$record['id'], ['used' => 1]);
        db()->commit();
    } catch (Throwable $e) { db()->rollBack(); throw $e; }
    reply(null, 'Password reset successful');
});

route('GET', '/api/users/me', fn() => reply(userDto(actor())));
route('PUT', '/api/users/me', function () {
    $user = actor();
    $data = body();
    $allowed = array_values(array_diff(USER_FIELDS, ['user_name', 'role', 'account_status', 'license_document_path']));
    $changes = fields($data, $allowed);
    if (isset($changes['email']) && !filter_var($changes['email'], FILTER_VALIDATE_EMAIL)) fail('Invalid email');
    updateRow('users', (int)$user['id'], $changes);
    reply(userDto(byId('users', (int)$user['id'])), 'Profile updated');
});
route('PUT', '/api/users/me/password', function () {
    $user = actor(); $data = body();
    if (!password_verify((string)($data['currentPassword'] ?? ''), $user['password'])) fail('Current password is incorrect');
    $password = (string)requireField($data, 'newPassword');
    if (strlen($password) < 6 || $password !== ($data['confirmPassword'] ?? null)) fail('New passwords must match and have at least 6 characters');
    updateRow('users', (int)$user['id'], ['password' => password_hash($password, PASSWORD_BCRYPT)]);
    reply(null, 'Password changed');
});

route('GET', '/api/users/pending', function () {
    requireRole('ADMIN', 'CONTROLLER');
    reply(array_map('userDto', rows("SELECT * FROM users WHERE account_status = 'PENDING' AND is_deleted = 0 ORDER BY id DESC")));
});
route('GET', '/api/users/drivers', function () {
    requireRole('ADMIN', 'CONTROLLER');
    reply(array_map('userDto', rows("SELECT * FROM users WHERE role = 'DRIVER' AND account_status = 'ACTIVE' AND is_deleted = 0 ORDER BY user_name")));
});
route('GET', '/api/users/deleted', function () { requireRole('ADMIN', 'CONTROLLER'); reply(array_map('userDto', allRows('users', true))); });
route('GET', '/api/users', function () { requireRole('ADMIN', 'CONTROLLER'); reply(array_map('userDto', allRows('users'))); });
route('GET', '/api/users/{id}', function ($p) { requireRole('ADMIN', 'CONTROLLER'); reply(userDto(byId('users', (int)$p['id']))); });
route('POST', '/api/users', function () {
    $actor = requireRole('ADMIN', 'CONTROLLER'); $data = body();
    if ($actor['role'] !== 'ADMIN' && ($data['role'] ?? '') === 'ADMIN') fail('Only administrators can create administrators', 403);
    reply(userDto(createUser($data, false)), 'User created', 201);
});
route('PUT', '/api/users/{id}', function ($p) {
    $actor = requireRole('ADMIN', 'CONTROLLER');
    $user = byId('users', (int)$p['id']);
    $changes = fields(body(), USER_FIELDS);
    if ($actor['role'] !== 'ADMIN' && (($changes['role'] ?? $user['role']) === 'ADMIN' || $user['role'] === 'ADMIN')) fail('Only administrators can manage administrators', 403);
    updateRow('users', (int)$p['id'], $changes);
    reply(userDto(byId('users', (int)$p['id'])), 'User updated');
});
route('DELETE', '/api/users/{id}', function ($p) {
    $actor = requireRole('ADMIN', 'CONTROLLER'); $target = byId('users', (int)$p['id']);
    if ($actor['role'] !== 'ADMIN' && $target['role'] === 'ADMIN') fail('Only administrators can manage administrators', 403);
    if ((int)$actor['id'] === (int)$p['id']) fail('You cannot delete your own account');
    updateRow('users', (int)$p['id'], ['is_deleted' => 1, 'deleted_by' => $actor['user_name'], 'deleted_at' => now()]);
    reply(null, 'User deleted');
});
route('PATCH', '/api/users/{id}/restore', function ($p) {
    $actor = requireRole('ADMIN', 'CONTROLLER'); $target = byId('users', (int)$p['id'], true);
    if ($actor['role'] !== 'ADMIN' && $target['role'] === 'ADMIN') fail('Only administrators can manage administrators', 403);
    updateRow('users', (int)$p['id'], ['is_deleted' => 0, 'deleted_by' => null, 'deleted_at' => null]);
    reply(userDto(byId('users', (int)$p['id'])), 'User restored');
});
foreach (['approve' => 'ACTIVE', 'reject' => 'INACTIVE'] as $action => $status) {
    route('PATCH', "/api/users/{id}/$action", function ($p) use ($status) {
        $actor = requireRole('ADMIN', 'CONTROLLER'); $target = byId('users', (int)$p['id']);
        if ($actor['role'] !== 'ADMIN' && $target['role'] === 'ADMIN') fail('Only administrators can approve administrators', 403);
        updateRow('users', (int)$p['id'], ['account_status' => $status]);
        reply(userDto(byId('users', (int)$p['id'])), 'User status updated');
    });
}

route('POST', '/api/users/{id}/document/{docType}', function ($p) {
    $actor = actor(); $id = (int)$p['id'];
    if ($actor['role'] === 'DRIVER' && (int)$actor['id'] !== $id) fail('Access denied', 403);
    if ($p['docType'] !== 'license') fail('Unsupported document type');
    byId('users', $id);
    $path = saveUpload('users', (string)$id);
    $changes = ['license_document_path' => $path];
    if (!empty($_POST['expiryDate'])) $changes['license_expiry_date'] = $_POST['expiryDate'];
    updateRow('users', $id, $changes);
    reply(userDto(byId('users', $id)), 'Document uploaded');
});
route('GET', '/api/users/{id}/document/{docType}', function ($p) {
    $actor = actor(); $id = (int)$p['id'];
    if ($actor['role'] === 'DRIVER' && (int)$actor['id'] !== $id) fail('Access denied', 403);
    if ($p['docType'] !== 'license') fail('Unsupported document type');
    $user = byId('users', $id);
    streamUpload((string)$user['license_document_path']);
});
