<?php
declare(strict_types=1);

const SERVICE_FIELDS = ['vehicle_reg_number', 'service_type', 'service_type_detail', 'service_date', 'current_mileage_km', 'service_cost', 'technician_workshop', 'next_service_due', 'next_service_mileage_km', 'description', 'parts_replaced', 'service_classification', 'trip_id'];

function serviceDto(array $service): array { return dto($service, ['is_deleted' => 'deleted'], ['is_deleted']); }
function serviceById(int $id): array { return byId('service_records', $id); }
function serviceVisible(array $record, array $user): bool {
    if ($user['role'] !== 'DRIVER') return true;
    if ($record['created_by'] === $user['user_name']) return true;
    return (bool)row('SELECT id FROM vehicles WHERE registration_no = ? AND driver_id = ? AND is_deleted = 0', [$record['vehicle_reg_number'], $user['id']]);
}
function serviceAudit(int $id, string $name, array $before, array $after): void {
    $changes = [];
    foreach ($after as $key => $value) if (($before[$key] ?? null) != $value) $changes[] = ['field' => camel($key), 'from' => (string)($before[$key] ?? ''), 'to' => (string)($value ?? '')];
    if ($changes) query('INSERT INTO service_record_audits (service_record_id, changed_by, changed_at, changed_fields) VALUES (?, ?, ?, ?)', [$id, $name, now(), json_encode($changes)]);
}
function serviceList(array $user): array {
    if ($user['role'] !== 'DRIVER') return allRows('service_records');
    return rows('SELECT s.* FROM service_records s WHERE s.is_deleted = 0 AND (s.created_by = ? OR EXISTS (SELECT 1 FROM vehicles v WHERE v.registration_no = s.vehicle_reg_number AND v.driver_id = ? AND v.is_deleted = 0)) ORDER BY s.id DESC', [$user['user_name'], $user['id']]);
}

route('GET', '/api/services/intervals', function () { actor(); reply(array_map('dto', rows('SELECT * FROM service_intervals ORDER BY vehicle_type, service_type'))); });
route('GET', '/api/services/intervals/vehicle-type/{type}', function ($p) { actor(); reply(array_map('dto', rows('SELECT * FROM service_intervals WHERE vehicle_type = ? ORDER BY service_type', [$p['type']]))); });
route('PUT', '/api/services/intervals/{id}', function ($p) {
    requireRole('ADMIN', 'CONTROLLER'); $id = (int)$p['id']; byId('service_intervals', $id, true);
    $km = (int)requireField(body(), 'intervalKm'); if ($km <= 0) fail('Interval must be positive');
    updateRow('service_intervals', $id, ['interval_km' => $km]); reply(dto(byId('service_intervals', $id, true)));
});
route('PUT', '/api/services/intervals', function () {
    requireRole('ADMIN', 'CONTROLLER'); $items = body(); if (!array_is_list($items)) fail('Expected an array'); $result = [];
    db()->beginTransaction();
    try {
        foreach ($items as $item) {
            $km = (int)requireField($item, 'intervalKm'); if ($km <= 0) fail('Interval must be positive');
            if (!empty($item['id'])) { $id = (int)$item['id']; byId('service_intervals', $id, true); updateRow('service_intervals', $id, ['interval_km' => $km]); }
            else {
                $type = requireField($item, 'vehicleType'); $service = requireField($item, 'serviceType');
                $old = row('SELECT id FROM service_intervals WHERE vehicle_type = ? AND service_type = ?', [$type, $service]);
                if ($old) { $id = (int)$old['id']; updateRow('service_intervals', $id, ['interval_km' => $km]); }
                else $id = insertRow('service_intervals', ['vehicle_type' => $type, 'service_type' => $service, 'interval_km' => $km]);
            }
            $result[] = dto(byId('service_intervals', $id, true));
        }
        db()->commit();
    } catch (Throwable $e) { db()->rollBack(); throw $e; }
    reply($result, 'Intervals updated');
});
route('GET', '/api/services/stats', function () {
    $user = actor(); $items = serviceList($user); $counts = []; $cost = 0.0;
    foreach ($items as $item) { $type = $item['service_type']; $counts[$type] = ($counts[$type] ?? 0) + 1; $cost += (float)$item['service_cost']; }
    reply(['totalServiceRecords' => count($items), 'totalServiceCost' => $cost, 'servicesByType' => $counts]);
});
route('GET', '/api/services/upcoming', function () {
    $user = actor(); $items = array_filter(serviceList($user), fn($s) => $s['next_service_due'] && $s['next_service_due'] >= date('Y-m-d') && $s['next_service_due'] <= date('Y-m-d', strtotime('+30 days')));
    usort($items, fn($a, $b) => strcmp($a['next_service_due'], $b['next_service_due'])); reply(array_map('serviceDto', array_values($items)));
});
route('GET', '/api/services/recent', function () {
    $user = actor(); $items = serviceList($user); usort($items, fn($a, $b) => strcmp($b['service_date'], $a['service_date']));
    reply(array_map('serviceDto', array_slice($items, 0, 5)));
});
route('GET', '/api/services/deleted', function () { requireRole('ADMIN', 'CONTROLLER'); reply(array_map('serviceDto', allRows('service_records', true))); });
route('POST', '/api/services/filter', function () {
    requireRole('ADMIN', 'CONTROLLER'); $f = body(); $sql = 'SELECT * FROM service_records WHERE is_deleted = 0'; $params = [];
    foreach (['vehicleRegNumber' => 'vehicle_reg_number', 'serviceType' => 'service_type'] as $input => $column) if (!empty($f[$input])) { $sql .= " AND $column = ?"; $params[] = $f[$input]; }
    if (!empty($f['fromDate'])) { $sql .= ' AND service_date >= ?'; $params[] = $f['fromDate']; }
    if (!empty($f['toDate'])) { $sql .= ' AND service_date <= ?'; $params[] = $f['toDate']; }
    reply(array_map('serviceDto', rows($sql . ' ORDER BY service_date DESC', $params)));
});
route('GET', '/api/services/vehicle/{vehicleRegNumber}', function ($p) {
    $user = actor(); $items = rows('SELECT * FROM service_records WHERE vehicle_reg_number = ? AND is_deleted = 0 ORDER BY service_date DESC', [$p['vehicleRegNumber']]);
    reply(array_map('serviceDto', array_values(array_filter($items, fn($r) => serviceVisible($r, $user)))));
});
route('GET', '/api/services/{id}/history', function ($p) {
    $user = actor(); $record = serviceById((int)$p['id']); if (!serviceVisible($record, $user)) fail('Access denied', 403);
    reply(array_map('dto', rows('SELECT * FROM service_record_audits WHERE service_record_id = ? ORDER BY changed_at DESC, id DESC', [(int)$p['id']])));
});
route('GET', '/api/services/{id}/attachment', function ($p) {
    $user = actor(); $record = serviceById((int)$p['id']); if (!serviceVisible($record, $user)) fail('Access denied', 403);
    streamUpload((string)$record['attachment_path']);
});
route('POST', '/api/services/{id}/attachment', function ($p) {
    $user = actor(); $id = (int)$p['id']; $record = serviceById($id); if (!serviceVisible($record, $user)) fail('Access denied', 403);
    $path = saveUpload('services', (string)$id); updateRow('service_records', $id, ['attachment_path' => $path]);
    reply(serviceDto(serviceById($id)), 'Attachment uploaded');
});
route('GET', '/api/services', function () { reply(array_map('serviceDto', serviceList(actor()))); });
route('GET', '/api/services/{id}', function ($p) {
    $user = actor(); $record = serviceById((int)$p['id']); if (!serviceVisible($record, $user)) fail('Access denied', 403);
    reply(serviceDto($record));
});
route('POST', '/api/services', function () {
    $user = actor(); $data = body(); $reg = (string)requireField($data, 'vehicleRegNumber'); $vehicle = vehicleByReg($reg);
    foreach (['serviceType', 'serviceDate', 'currentMileageKm', 'serviceCost'] as $field) requireField($data, $field);
    if (($data['serviceType'] ?? '') === 'OTHER' && empty(trim((string)($data['serviceTypeDetail'] ?? '')))) fail('Service type detail is required for OTHER');
    $values = fields($data, SERVICE_FIELDS); $values['created_by'] = $user['user_name']; $values['created_at'] = now();
    $values['status'] = $user['role'] === 'DRIVER' ? 'PENDING' : 'APPROVED'; $values['service_classification'] ??= 'ROUTINE';
    if (empty($values['next_service_mileage_km'])) {
        $interval = scalar('SELECT interval_km FROM service_intervals WHERE vehicle_type = ? AND service_type = ?', [$vehicle['vehicle_type'], $values['service_type']]);
        if ($interval !== false) $values['next_service_mileage_km'] = (int)$values['current_mileage_km'] + (int)$interval;
    }
    if ((int)$values['current_mileage_km'] > (int)$vehicle['current_mileage_km']) updateRow('vehicles', (int)$vehicle['id'], ['current_mileage_km' => (int)$values['current_mileage_km']]);
    $id = insertRow('service_records', $values); reply(serviceDto(serviceById($id)), 'Service record created', 201);
});
route('PUT', '/api/services/{id}', function ($p) {
    $user = actor(); $id = (int)$p['id']; $old = serviceById($id); if (!serviceVisible($old, $user)) fail('Access denied', 403);
    if ($user['role'] === 'DRIVER' && $old['created_by'] !== $user['user_name']) fail('You can only edit records you created', 403);
    $changes = fields(body(), SERVICE_FIELDS); if (isset($changes['vehicle_reg_number'])) vehicleByReg($changes['vehicle_reg_number']);
    if (($changes['service_type'] ?? $old['service_type']) === 'OTHER' && empty(trim((string)($changes['service_type_detail'] ?? $old['service_type_detail'] ?? '')))) fail('Service type detail is required for OTHER');
    if ($user['role'] === 'DRIVER') $changes['status'] = 'PENDING';
    serviceAudit($id, $user['user_name'], $old, $changes); updateRow('service_records', $id, $changes);
    reply(serviceDto(serviceById($id)), 'Service record updated');
});
route('DELETE', '/api/services/{id}', function ($p) {
    $user = requireRole('ADMIN', 'CONTROLLER'); $id = (int)$p['id']; serviceById($id);
    updateRow('service_records', $id, ['is_deleted' => 1, 'deleted_by' => $user['user_name'], 'deleted_at' => now()]); reply(null, 'Service record deleted');
});
route('PATCH', '/api/services/{id}/restore', function ($p) {
    requireRole('ADMIN', 'CONTROLLER'); $id = (int)$p['id']; byId('service_records', $id, true);
    updateRow('service_records', $id, ['is_deleted' => 0, 'deleted_by' => null, 'deleted_at' => null]); reply(serviceDto(serviceById($id)), 'Service record restored');
});
foreach (['approve' => 'APPROVED', 'reject' => 'REJECTED'] as $action => $status) {
    route('PATCH', "/api/services/{id}/$action", function ($p) use ($status) {
        requireRole('ADMIN', 'CONTROLLER'); $id = (int)$p['id']; $record = serviceById($id);
        updateRow('service_records', $id, ['status' => $status]);
        if ($record['created_by']) notify('USER-' . $record['created_by'], 'Your service record for vehicle ' . $record['vehicle_reg_number'] . ' was ' . strtolower($status), 'SERVICE_' . $status);
        reply(serviceDto(serviceById($id)), 'Service record status updated');
    });
}
