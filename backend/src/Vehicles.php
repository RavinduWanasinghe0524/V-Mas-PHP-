<?php
declare(strict_types=1);

const VEHICLE_FIELDS = ['registration_no', 'chassis_no', 'engine_no', 'manufacturer', 'model', 'year', 'current_mileage_km', 'initial_mileage_km', 'status', 'fuel_type', 'vehicle_type', 'fuel_capacity', 'insurance_expiry_date', 'license_expiry_date', 'vehicle_image', 'deactivation_reason'];

function vehicleDto(array $vehicle): array {
    $driver = null;
    if (!empty($vehicle['driver_id'])) $driver = scalar('SELECT user_name FROM users WHERE id = ?', [$vehicle['driver_id']]);
    if (!$driver) $driver = scalar("SELECT driver_username FROM trips WHERE vehicle_reg_number = ? AND status = 'STARTED' AND is_deleted = 0 ORDER BY id DESC LIMIT 1", [$vehicle['registration_no']]);
    unset($vehicle['driver_id'], $vehicle['vehicle_name']);
    $result = dto($vehicle, ['chassis_no' => 'chassisNumber', 'engine_no' => 'engineNumber', 'is_deleted' => 'deleted'], ['is_deleted']);
    $result['driverUsername'] = $driver ?: null;
    return $result;
}
function vehicleByReg(string $reg): array { return row('SELECT * FROM vehicles WHERE registration_no = ? AND is_deleted = 0', [$reg]) ?? fail('Vehicle not found', 404); }

route('GET', '/api/vehicles/my-vehicle', function () {
    $user = actor();
    $vehicle = row('SELECT * FROM vehicles WHERE driver_id = ? AND is_deleted = 0 LIMIT 1', [$user['id']]);
    if (!$vehicle) $vehicle = row("SELECT v.* FROM vehicles v JOIN trips t ON t.vehicle_reg_number = v.registration_no WHERE t.driver_username = ? AND t.status IN ('ASSIGNED', 'STARTED') AND t.is_deleted = 0 AND v.is_deleted = 0 ORDER BY t.id DESC LIMIT 1", [$user['user_name']]);
    if (!$vehicle) fail('No vehicle is currently assigned to you', 404);
    reply(vehicleDto($vehicle));
});
route('GET', '/api/vehicles/deleted', function () { requireRole('ADMIN', 'CONTROLLER'); reply(array_map('vehicleDto', allRows('vehicles', true))); });
route('GET', '/api/vehicles/registration/{registrationNo}', function ($p) { actor(); reply(vehicleDto(vehicleByReg($p['registrationNo']))); });
route('GET', '/api/vehicles', function () { actor(); reply(array_map('vehicleDto', allRows('vehicles'))); });
route('GET', '/api/vehicles/{id}', function ($p) { actor(); reply(vehicleDto(byId('vehicles', (int)$p['id']))); });

route('POST', '/api/vehicles', function () {
    requireRole('ADMIN', 'CONTROLLER'); $data = body();
    $reg = strtoupper(trim((string)requireField($data, 'registrationNo')));
    if (row('SELECT id FROM vehicles WHERE registration_no = ?', [$reg])) fail('Registration number already exists', 409);
    $values = fields($data, VEHICLE_FIELDS, ['chassis_no' => 'chassisNumber', 'engine_no' => 'engineNumber']);
    $values['registration_no'] = $reg;
    $values['vehicle_name'] = trim(($values['manufacturer'] ?? '') . ' ' . ($values['model'] ?? '')) ?: 'Unknown';
    $values['initial_mileage_km'] ??= $values['current_mileage_km'] ?? 0;
    $values['status'] ??= 'AVAILABLE'; $values['vehicle_type'] ??= 'CAR';
    $id = insertRow('vehicles', $values);
    reply(vehicleDto(byId('vehicles', $id)), 'Vehicle created', 201);
});
route('PUT', '/api/vehicles/{id}', function ($p) {
    $user = requireRole('ADMIN', 'CONTROLLER'); $id = (int)$p['id']; byId('vehicles', $id);
    $changes = fields(body(), VEHICLE_FIELDS, ['chassis_no' => 'chassisNumber', 'engine_no' => 'engineNumber']);
    if (isset($changes['registration_no'])) $changes['registration_no'] = strtoupper(trim($changes['registration_no']));
    if (isset($changes['manufacturer']) || isset($changes['model'])) {
        $old = byId('vehicles', $id);
        $changes['vehicle_name'] = trim(($changes['manufacturer'] ?? $old['manufacturer'] ?? '') . ' ' . ($changes['model'] ?? $old['model'] ?? '')) ?: 'Unknown';
    }
    $changes['updated_at'] = now(); $changes['updated_by'] = $user['user_name'];
    updateRow('vehicles', $id, $changes);
    reply(vehicleDto(byId('vehicles', $id)), 'Vehicle updated');
});
route('DELETE', '/api/vehicles/{id}', function ($p) {
    $user = requireRole('ADMIN', 'CONTROLLER'); $id = (int)$p['id']; byId('vehicles', $id);
    updateRow('vehicles', $id, ['is_deleted' => 1, 'deleted_by' => $user['user_name'], 'deleted_at' => now()]);
    reply(null, 'Vehicle deleted');
});
route('PATCH', '/api/vehicles/{id}/restore', function ($p) {
    requireRole('ADMIN', 'CONTROLLER'); $id = (int)$p['id']; byId('vehicles', $id, true);
    updateRow('vehicles', $id, ['is_deleted' => 0, 'deleted_by' => null, 'deleted_at' => null]);
    reply(vehicleDto(byId('vehicles', $id)), 'Vehicle restored');
});
route('POST', '/api/vehicles/bulk-mileage', function () {
    $user = requireRole('ADMIN', 'CONTROLLER'); $updates = body();
    if (!array_is_list($updates)) fail('Expected an array of mileage updates');
    db()->beginTransaction();
    try {
        foreach ($updates as $item) {
            $id = (int)requireField($item, 'id');
            $vehicle = byId('vehicles', $id);
            $mileage = (int)requireField($item, 'currentMileageKm');
            if ($mileage < (int)$vehicle['current_mileage_km']) fail('Mileage cannot decrease');
            updateRow('vehicles', $id, ['current_mileage_km' => $mileage, 'updated_at' => now(), 'updated_by' => $user['user_name']]);
        }
        db()->commit();
    } catch (Throwable $e) { db()->rollBack(); throw $e; }
    reply(null, 'Mileage updated');
});
route('PATCH', '/api/vehicles/{id}/assign-driver', function ($p) {
    requireRole('ADMIN', 'CONTROLLER'); $id = (int)$p['id']; byId('vehicles', $id);
    $name = body()['driverUsername'] ?? null; $driverId = null;
    if ($name !== null && $name !== '') {
        $driver = row('SELECT * FROM users WHERE user_name = ? AND role = ? AND account_status = ? AND is_deleted = 0', [$name, 'DRIVER', 'ACTIVE']);
        if (!$driver) fail('Active driver not found', 404);
        if (row('SELECT id FROM vehicles WHERE driver_id = ? AND id <> ? AND is_deleted = 0', [$driver['id'], $id])) fail('Driver is already assigned to a vehicle');
        $driverId = (int)$driver['id'];
    }
    updateRow('vehicles', $id, ['driver_id' => $driverId]);
    reply(vehicleDto(byId('vehicles', $id)), 'Driver assignment updated');
});

function vehicleDocumentColumn(string $type): string {
    return match (strtolower($type)) { 'insurance' => 'insurance_document_path', 'license' => 'license_document_path', 'registration' => 'registration_book_path', default => fail('Invalid document type') };
}
route('POST', '/api/vehicles/{id}/document/{docType}', function ($p) {
    requireRole('ADMIN', 'CONTROLLER'); $id = (int)$p['id']; byId('vehicles', $id);
    $column = vehicleDocumentColumn($p['docType']); $path = saveUpload('vehicles', (string)$id);
    $changes = [$column => $path];
    if ($column === 'insurance_document_path' && !empty($_POST['expiryDate'])) $changes['insurance_expiry_date'] = $_POST['expiryDate'];
    if ($column === 'license_document_path' && !empty($_POST['expiryDate'])) $changes['license_expiry_date'] = $_POST['expiryDate'];
    updateRow('vehicles', $id, $changes);
    reply(vehicleDto(byId('vehicles', $id)), 'Document uploaded');
});
route('GET', '/api/vehicles/{id}/document/{docType}', function ($p) {
    actor(); $vehicle = byId('vehicles', (int)$p['id']);
    streamUpload((string)$vehicle[vehicleDocumentColumn($p['docType'])]);
});
