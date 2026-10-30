<?php
declare(strict_types=1);
require_once __DIR__ . '/PassFaculties.php';

route('GET', '/api/vehicle-passes/faculties', fn() => reply(PASS_FACULTIES));

function passApproverEmail(string $stage, string $faculty, string $department): string {
    $key = $stage === 'HOD' ? 'PASS_HOD_EMAILS_JSON' : 'PASS_DEAN_EMAILS_JSON';
    $map = json_decode((string)envv($key, '{}'), true);
    return is_array($map) ? (string)($map[$stage === 'HOD' ? "$faculty|$department" : $faculty] ?? '') : '';
}

function emailPassApprover(array $pass, string $stage): bool {
    $email = passApproverEmail($stage, $pass['faculty'], $pass['department']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !envv('SENDGRID_API_KEY', '')) return false;
    $token = bin2hex(random_bytes(32));
    updateRow('vehicle_passes', (int)$pass['id'], [
        'approval_token_hash' => hash('sha256', $token),
        'approval_token_expires_at' => date('Y-m-d H:i:s', time() + 14 * 86400),
    ]);
    $url = rtrim((string)envv('FRONTEND_URL', 'http://localhost:3000'), '/') . '/approve-pass?token=' . $token;
    $label = $stage === 'HOD' ? 'Head of Department' : 'Dean';
    return sendEmail($email, "Vehicle pass {$pass['pass_code']} needs $label approval", '<p>A vehicle pass application needs your review.</p><p>Faculty: ' . htmlspecialchars($pass['faculty'], ENT_QUOTES) . '<br>Department: ' . htmlspecialchars($pass['department'], ENT_QUOTES) . '</p><p><a href="' . htmlspecialchars($url, ENT_QUOTES) . '">Review application</a></p>');
}

route('GET', '/api/vehicle-passes/approval/{token}', function ($p) {
    $pass = row('SELECT * FROM vehicle_passes WHERE approval_token_hash = ? AND approval_token_expires_at > ? AND status IN (?, ?)', [hash('sha256', $p['token']), now(), 'PENDING_HOD', 'PENDING_DEAN']);
    if (!$pass) fail('Approval link is invalid or expired', 404);
    reply(['passCode' => $pass['pass_code'], 'applicantName' => $pass['applicant_name'], 'faculty' => $pass['faculty'], 'department' => $pass['department'], 'vehicleRegNumber' => $pass['vehicle_reg_number'], 'status' => $pass['status']]);
});

route('POST', '/api/vehicle-passes/approval/{token}', function ($p) {
    $decision = strtoupper((string)(body()['decision'] ?? ''));
    if (!in_array($decision, ['APPROVE', 'REJECT'], true)) fail('Invalid decision');
    $hash = hash('sha256', $p['token']);
    $pass = row('SELECT * FROM vehicle_passes WHERE approval_token_hash = ? AND approval_token_expires_at > ? AND status IN (?, ?)', [$hash, now(), 'PENDING_HOD', 'PENDING_DEAN']);
    if (!$pass) fail('Approval link is invalid or expired', 404);
    $isHod = $pass['status'] === 'PENDING_HOD';
    $next = $decision === 'REJECT' ? 'REJECTED' : ($isHod ? 'PENDING_DEAN' : 'APPROVED');
    $stmt = db()->prepare('UPDATE vehicle_passes SET status = ?, approval_token_hash = NULL, approval_token_expires_at = NULL, updated_at = ?, ' . ($decision === 'APPROVE' ? ($isHod ? 'hod_approved_at' : 'dean_approved_at') . ' = ?' : 'rejection_reason = ?') . ' WHERE id = ? AND approval_token_hash = ? AND status = ?');
    $stmt->execute([$next, now(), $decision === 'APPROVE' ? now() : 'Declined by approver', $pass['id'], $hash, $pass['status']]);
    if ($stmt->rowCount() !== 1) fail('This approval has already been used', 409);
    $emailSent = $next === 'PENDING_DEAN' ? emailPassApprover(byId('vehicle_passes', (int)$pass['id'], true), 'DEAN') : null;
    reply(['status' => $next, 'emailSent' => $emailSent], $emailSent === false ? 'Decision recorded; Dean email could not be sent' : 'Decision recorded');
});

route('POST', '/api/vehicle-passes/{id}/resend-approval', function ($p) {
    requireRole('ADMIN', 'CONTROLLER');
    $pass = byId('vehicle_passes', (int)$p['id'], true);
    $stage = match ($pass['status']) { 'PENDING_HOD' => 'HOD', 'PENDING_DEAN' => 'DEAN', default => null };
    if (!$stage) fail('This application is not awaiting approval', 409);
    if (!envv('SENDGRID_API_KEY', '') || !filter_var(passApproverEmail($stage, $pass['faculty'], $pass['department']), FILTER_VALIDATE_EMAIL)) fail('Approver email or SendGrid is not configured', 409);
    if (!emailPassApprover($pass, $stage)) fail('Approval email could not be sent', 502);
    reply(null, 'Approval email sent');
});

const VEHICLE_PASS_FIELDS = [
    'form_type', 'applicant_name', 'phone_number', 'email', 'nic_number',
    'vehicle_reg_number', 'vehicle_type', 'vehicle_model',
    'pass_type', 'start_date', 'end_date', 'destination', 'purpose', 'form_data'
];

function vehiclePassDto(array $row): array {
    $dto = dto($row);
    unset($dto['approvalTokenHash'], $dto['approvalTokenExpiresAt']);
    if (!empty($row['form_data']) && is_string($row['form_data'])) {
        $decoded = json_decode($row['form_data'], true);
        if (is_array($decoded)) {
            $dto['formData'] = $decoded;
        }
    }
    return $dto;
}

// ── Public: Submit a Vehicle Pass Application ──────────────────────────────────
route('POST', '/api/vehicle-passes/apply', function () {
    $data = body();
    $faculty = trim((string)($data['faculty'] ?? ''));
    $department = trim((string)($data['dept'] ?? ''));
    if (!isset(PASS_FACULTIES[$faculty]) || !in_array($department, PASS_FACULTIES[$faculty], true)) fail('Select a valid faculty and department');

    $formType = trim((string)($data['formType'] ?? 'SUSL/GA/TD/03'));

    // Extract fields depending on form type
    if ($formType === 'SUSL/GA/TD/01') {
        // Form 01: Outstation Travel Gate Pass
        $name = trim((string)($data['name'] ?? $data['applicantName'] ?? ''));
        $phone = trim((string)($data['tel'] ?? $data['phoneNumber'] ?? ''));
        $regNo = strtoupper(trim((string)($data['vno'] ?? $data['vehicleRegNumber'] ?? 'OUTSTATION')));
        if ($regNo === '') $regNo = 'OUTSTATION';
        $vehicleType = trim((string)($data['vt'] ?? $data['vehicleType'] ?? 'CAR'));
        $destination = trim((string)($data['place'] ?? $data['destination'] ?? ''));
        $purpose = trim((string)($data['reason'] ?? $data['purpose'] ?? 'Official Outstation Travel'));
        $startDate = trim((string)($data['dep'] ?? $data['due'] ?? $data['startDate'] ?? date('Y-m-d')));
        $endDate = trim((string)($data['ret'] ?? $data['endDate'] ?? ''));
        $nic = trim((string)($data['nic'] ?? $data['nicNumber'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $passType = 'OUTSTATION';
    } else {
        // Form 03: Staff Vehicle Identification Application (Default)
        $formType = 'SUSL/GA/TD/03';
        $name = trim((string)($data['name'] ?? $data['applicantName'] ?? ''));
        $phone = trim((string)($data['mob'] ?? $data['phoneNumber'] ?? ''));
        $regNo = strtoupper(trim((string)($data['reg'] ?? $data['vehicleRegNumber'] ?? '')));
        $vehicleType = trim((string)($data['vtype'] ?? $data['vehicleType'] ?? 'CAR'));
        $nic = trim((string)($data['nic'] ?? $data['nicNumber'] ?? ''));
        $desig = trim((string)($data['desig'] ?? ''));
        $dept = trim((string)($data['dept'] ?? ''));
        $status = trim((string)($data['status'] ?? 'perm'));
        $purpose = "Staff Vehicle ID - " . ($dept ?: 'University') . ($desig ? " ($desig)" : "");
        $destination = $dept ?: 'University Premises';
        $startDate = trim((string)($data['d1'] ?? $data['startDate'] ?? date('Y-m-d')));
        $endDate = null;
        $email = trim((string)($data['email'] ?? ''));
        $passType = $status === 'temp' ? 'STAFF_TEMPORARY' : 'STAFF_PERMANENT';
    }

    if ($name === '') fail('Applicant name is required');
    if ($phone === '') fail('Phone number is required');
    if ($regNo === '') fail('Vehicle registration number is required');
    if ($startDate === '') $startDate = date('Y-m-d');

    // Generate unique human-readable Pass Code (e.g. VP-2026-X8Y9)
    $unique = false;
    $passCode = '';
    $prefix = ($formType === 'SUSL/GA/TD/01') ? 'VP-OUT-' : 'VP-STF-';
    for ($i = 0; $i < 5; $i++) {
        $candidate = $prefix . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        if (!row('SELECT id FROM vehicle_passes WHERE pass_code = ?', [$candidate])) {
            $passCode = $candidate;
            $unique = true;
            break;
        }
    }
    if (!$unique) {
        $passCode = $prefix . time();
    }

    $values = [
        'form_type' => $formType,
        'faculty' => $faculty,
        'department' => $department,
        'pass_code' => $passCode,
        'applicant_name' => $name,
        'phone_number' => $phone,
        'email' => $email ?: null,
        'nic_number' => $nic ?: null,
        'vehicle_reg_number' => $regNo,
        'vehicle_type' => $vehicleType ?: 'CAR',
        'vehicle_model' => $data['vehicleModel'] ?? $data['owner'] ?? null,
        'pass_type' => $passType,
        'start_date' => $startDate,
        'end_date' => $endDate ?: null,
        'destination' => $destination ?: null,
        'purpose' => $purpose,
        'form_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'status' => 'PENDING_HOD',
        'created_at' => now(),
    ];

    $id = insertRow('vehicle_passes', $values);

    // Notify administrators of the new application
    try {
        notify($regNo, "New $formType pass application ($passCode) submitted by $name ($phone)", 'VEHICLE_PASS');
    } catch (Throwable $e) {
        error_log("Failed to create pass notification: " . $e->getMessage());
    }

    $created = byId('vehicle_passes', $id, true);
    $emailSent = emailPassApprover($created, 'HOD');
    reply(vehiclePassDto($created), $emailSent ? 'Vehicle pass application submitted and Head of Department notified' : 'Vehicle pass application saved; Head of Department email is not configured or could not be sent', 201);
});

// ── Public: Check Application Status by Pass Code or Phone ───────────────────────
route('GET', '/api/vehicle-passes/check/{code}', function ($p) {
    $code = trim($p['code'] ?? '');
    if ($code === '') fail('Pass code or phone number is required');

    $pass = row('SELECT * FROM vehicle_passes WHERE pass_code = ? OR phone_number = ? ORDER BY id DESC LIMIT 1', [strtoupper($code), $code]);
    if (!$pass) fail('Vehicle pass application not found', 404);

    reply(vehiclePassDto($pass));
});

// ── Admin/Controller: List all Vehicle Passes ──────────────────────────────────
route('GET', '/api/vehicle-passes', function () {
    requireRole('ADMIN', 'CONTROLLER');
    $status = $_GET['status'] ?? null;
    $type = $_GET['formType'] ?? null;

    $conditions = [];
    $params = [];

    if ($status && in_array(strtoupper($status), ['PENDING', 'PENDING_HOD', 'PENDING_DEAN', 'APPROVED', 'REJECTED'], true)) {
        $conditions[] = 'status = ?';
        $params[] = strtoupper($status);
    }

    if ($type) {
        $conditions[] = 'form_type = ?';
        $params[] = $type;
    }

    $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
    $passes = rows("SELECT * FROM vehicle_passes $where ORDER BY id DESC", $params);

    reply(array_map('vehiclePassDto', $passes));
});

// ── Admin/Controller: Get single Vehicle Pass ──────────────────────────────────
route('GET', '/api/vehicle-passes/{id}', function ($p) {
    requireRole('ADMIN', 'CONTROLLER');
    $id = (int)$p['id'];
    $pass = byId('vehicle_passes', $id, true);
    reply(vehiclePassDto($pass));
});

// ── Admin/Controller: Update Pass Status (Approve / Reject) ────────────────────
route('PATCH', '/api/vehicle-passes/{id}/status', function ($p) {
    $user = requireRole('ADMIN', 'CONTROLLER');
    $id = (int)$p['id'];
    $pass = byId('vehicle_passes', $id, true);

    $data = body();
    $status = strtoupper((string)requireField($data, 'status'));
    if (!in_array($status, ['APPROVED', 'REJECTED', 'PENDING'], true)) {
        fail('Invalid status. Allowed: APPROVED, REJECTED, PENDING');
    }
    if ($status === 'APPROVED' && !$pass['dean_approved_at']) fail('Dean approval is required before issuing this pass', 409);

    $updates = [
        'status' => $status,
        'approved_by' => $user['user_name'],
        'updated_at' => now(),
    ];

    if ($status === 'REJECTED' && !empty($data['rejectionReason'])) {
        $updates['rejection_reason'] = (string)$data['rejectionReason'];
    }

    updateRow('vehicle_passes', $id, $updates);
    $updated = byId('vehicle_passes', $id, true);

    reply(vehiclePassDto($updated), "Vehicle pass status updated to $status");
});

// ── Admin: Delete Pass ──────────────────────────────────────────────────────────
route('DELETE', '/api/vehicle-passes/{id}', function ($p) {
    requireRole('ADMIN');
    $id = (int)$p['id'];
    byId('vehicle_passes', $id, true);
    query('DELETE FROM vehicle_passes WHERE id = ?', [$id]);
    reply(null, 'Vehicle pass application deleted');
});
