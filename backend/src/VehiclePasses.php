<?php
declare(strict_types=1);

const VEHICLE_PASS_FIELDS = [
    'applicant_name', 'phone_number', 'email', 'nic_number',
    'vehicle_reg_number', 'vehicle_type', 'vehicle_model',
    'pass_type', 'start_date', 'end_date', 'destination', 'purpose'
];

function vehiclePassDto(array $row): array {
    return dto($row);
}

// ── Public: Submit a Vehicle Pass Application ──────────────────────────────────
route('POST', '/api/vehicle-passes/apply', function () {
    $data = body();

    $name = trim((string)requireField($data, 'applicantName'));
    $phone = trim((string)requireField($data, 'phoneNumber'));
    $regNo = strtoupper(trim((string)requireField($data, 'vehicleRegNumber')));
    $purpose = trim((string)requireField($data, 'purpose'));
    $startDate = trim((string)requireField($data, 'startDate'));

    if ($name === '') fail('Applicant name is required');
    if ($phone === '') fail('Phone number is required');
    if ($regNo === '') fail('Vehicle registration number is required');
    if ($startDate === '') fail('Start date is required');

    // Generate unique human-readable Pass Code (e.g. VP-2026-X8Y9)
    $unique = false;
    $passCode = '';
    for ($i = 0; $i < 5; $i++) {
        $candidate = 'VP-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        if (!row('SELECT id FROM vehicle_passes WHERE pass_code = ?', [$candidate])) {
            $passCode = $candidate;
            $unique = true;
            break;
        }
    }
    if (!$unique) {
        $passCode = 'VP-' . time();
    }

    $values = fields($data, VEHICLE_PASS_FIELDS);
    $values['pass_code'] = $passCode;
    $values['applicant_name'] = $name;
    $values['phone_number'] = $phone;
    $values['vehicle_reg_number'] = $regNo;
    $values['vehicle_type'] = $data['vehicleType'] ?? 'CAR';
    $values['vehicle_model'] = $data['vehicleModel'] ?? null;
    $values['pass_type'] = $data['passType'] ?? 'VISITOR';
    $values['start_date'] = $startDate;
    $values['end_date'] = !empty($data['endDate']) ? $data['endDate'] : null;
    $values['destination'] = $data['destination'] ?? null;
    $values['purpose'] = $purpose;
    $values['status'] = 'PENDING';
    $values['created_at'] = now();

    $id = insertRow('vehicle_passes', $values);

    // Notify administrators of the new application
    try {
        notify($regNo, "New vehicle pass request ($passCode) submitted by $name ($phone) for $regNo", 'VEHICLE_PASS');
    } catch (Throwable $e) {
        // Notification failure should not block application submission
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

    if ($status && in_array(strtoupper($status), ['PENDING', 'APPROVED', 'REJECTED'], true)) {
        $passes = rows('SELECT * FROM vehicle_passes WHERE status = ? ORDER BY id DESC', [strtoupper($status)]);
    } else {
        $passes = rows('SELECT * FROM vehicle_passes ORDER BY id DESC');
    }

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
