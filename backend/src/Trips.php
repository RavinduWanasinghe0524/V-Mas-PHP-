<?php
declare(strict_types=1);

const TRIP_FIELDS = ['driver_username', 'vehicle_reg_number', 'origin', 'destination', 'purpose', 'scheduled_date'];

function tripDto(array $trip): array {
    $result = dto($trip, ['is_deleted' => 'deleted'], ['is_deleted']);
    $result['driverName'] = $trip['driver_username'];
    return $result;
}
function tripById(int $id): array { return byId('trips', $id); }
function validateTripResources(array $data): void {
    $driver = (string)requireField($data, 'driverUsername');
    $vehicle = (string)requireField($data, 'vehicleRegNumber');
    requireField($data, 'destination'); requireField($data, 'scheduledDate');
    if (!row("SELECT id FROM users WHERE user_name = ? AND role = 'DRIVER' AND account_status = 'ACTIVE' AND is_deleted = 0", [$driver])) fail('Active driver not found');
    vehicleByReg($vehicle);
}

route('GET', '/api/trips/my', function () {
    $user = actor();
    reply(array_map('tripDto', rows('SELECT * FROM trips WHERE driver_username = ? AND is_deleted = 0 ORDER BY created_at DESC, id DESC', [$user['user_name']])));
});
route('GET', '/api/trips/deleted', function () { requireRole('ADMIN', 'CONTROLLER'); reply(array_map('tripDto', allRows('trips', true))); });
route('GET', '/api/trips', function () { requireRole('ADMIN', 'CONTROLLER'); reply(array_map('tripDto', allRows('trips'))); });
route('GET', '/api/trips/{id}', function ($p) {
    $user = actor(); $trip = tripById((int)$p['id']);
    if ($user['role'] === 'DRIVER' && $trip['driver_username'] !== $user['user_name']) fail('Access denied', 403);
    reply(tripDto($trip));
});
route('POST', '/api/trips', function () {
    $user = requireRole('ADMIN', 'CONTROLLER'); $data = body(); validateTripResources($data);
    $values = fields($data, TRIP_FIELDS);
    $values['status'] = 'ASSIGNED'; $values['assigned_by'] = $user['user_name']; $values['created_at'] = now();
    $id = insertRow('trips', $values); $trip = tripById($id);
    notify($trip['driver_username'], 'New trip assigned to ' . $trip['destination'] . ' with vehicle ' . $trip['vehicle_reg_number'], 'TRIP_ASSIGNED');
    $phone = scalar('SELECT phone_number FROM users WHERE user_name = ?', [$trip['driver_username']]);
    sendSms((string)$phone, 'V-MAS: New trip to ' . $trip['destination'] . ' on ' . $trip['scheduled_date'] . ', vehicle ' . $trip['vehicle_reg_number']);
    reply(tripDto($trip), 'Trip assigned', 201);
});
route('PUT', '/api/trips/{id}', function ($p) {
    requireRole('ADMIN', 'CONTROLLER'); $id = (int)$p['id']; $trip = tripById($id);
    if (in_array($trip['status'], ['COMPLETED', 'CANCELLED'], true)) fail('Completed or cancelled trip cannot be edited');
    $changes = fields(body(), $trip['status'] === 'STARTED' ? ['destination'] : TRIP_FIELDS);
    if (isset($changes['driver_username'])) {
        if (!row("SELECT id FROM users WHERE user_name = ? AND role = 'DRIVER' AND is_deleted = 0", [$changes['driver_username']])) fail('Driver not found');
    }
    if (isset($changes['vehicle_reg_number'])) vehicleByReg($changes['vehicle_reg_number']);
    $changes['updated_at'] = now(); updateRow('trips', $id, $changes);
    reply(tripDto(tripById($id)), 'Trip updated');
});
route('DELETE', '/api/trips/{id}/delete', function ($p) {
    $user = requireRole('ADMIN', 'CONTROLLER'); $id = (int)$p['id']; tripById($id);
    updateRow('trips', $id, ['is_deleted' => 1, 'deleted_by' => $user['user_name'], 'deleted_at' => now()]);
    reply(null, 'Trip deleted');
});
route('DELETE', '/api/trips/{id}', function ($p) {
    requireRole('ADMIN', 'CONTROLLER'); $id = (int)$p['id']; $trip = tripById($id);
    if ($trip['status'] !== 'ASSIGNED') fail('Only assigned trips can be cancelled');
    updateRow('trips', $id, ['status' => 'CANCELLED', 'updated_at' => now()]);
    notify($trip['driver_username'], 'Trip to ' . $trip['destination'] . ' was cancelled', 'TRIP_CANCELLED');
    reply(null, 'Trip cancelled');
});
route('PATCH', '/api/trips/{id}/restore', function ($p) {
    requireRole('ADMIN', 'CONTROLLER'); $id = (int)$p['id']; byId('trips', $id, true);
    updateRow('trips', $id, ['is_deleted' => 0, 'deleted_by' => null, 'deleted_at' => null]);
    reply(tripDto(tripById($id)), 'Trip restored');
});
foreach (['start', 'decline', 'complete'] as $action) {
    route('PATCH', "/api/trips/{id}/$action", function ($p) use ($action) {
        $user = requireRole('DRIVER'); $id = (int)$p['id']; $trip = tripById($id);
        if ($trip['driver_username'] !== $user['user_name']) fail('Trip is not assigned to you', 403);
        $now = now(); $changes = ['updated_at' => $now];
        if ($action === 'start') {
            if ($trip['status'] !== 'ASSIGNED') fail('Only an assigned trip can be started');
            if (row("SELECT id FROM trips WHERE driver_username = ? AND status = 'STARTED' AND is_deleted = 0 AND id <> ? LIMIT 1", [$user['user_name'], $id])) fail('Complete your active trip first');
            $changes += ['status' => 'STARTED', 'started_at' => $now, 'responded_at' => $now];
        } elseif ($action === 'decline') {
            if ($trip['status'] !== 'ASSIGNED') fail('Only an assigned trip can be declined');
            $changes += ['status' => 'DECLINED', 'decline_reason' => body()['reason'] ?? null, 'responded_at' => $now];
        } else {
            if (!in_array($trip['status'], ['ASSIGNED', 'STARTED'], true)) fail('Only an assigned or started trip can be completed');
            $changes += ['status' => 'COMPLETED', 'completed_at' => $now];
        }
        updateRow('trips', $id, $changes);
        notify($trip['assigned_by'] ?: 'CONTROLLER', 'Driver ' . $user['user_name'] . ' ' . $action . 'ed the trip to ' . $trip['destination'], 'TRIP_' . strtoupper($action));
        reply(tripDto(tripById($id)), 'Trip updated');
    });
}
