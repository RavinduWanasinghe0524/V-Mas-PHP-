<?php
declare(strict_types=1);

const VEHICLE_PASS_FIELDS = [
    'form_type', 'applicant_name', 'phone_number', 'email', 'nic_number',
    'vehicle_reg_number', 'vehicle_type', 'vehicle_model',
    'pass_type', 'start_date', 'end_date', 'destination', 'purpose', 'form_data'
];

function vehiclePassDto(array $row): array {
    $dto = dto($row);
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
        'status' => 'PENDING',
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
    reply(vehiclePassDto($created), 'Vehicle pass application submitted successfully', 201);
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

    if ($status && in_array(strtoupper($status), ['PENDING', 'APPROVED', 'REJECTED'], true)) {
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
