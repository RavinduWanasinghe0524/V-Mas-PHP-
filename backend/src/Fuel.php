<?php
declare(strict_types=1);

const FUEL_FIELDS = ['vehicle_reg_number', 'fuel_type', 'liters', 'cost_per_liter', 'total_cost', 'mileage', 'date', 'driver_username', 'trip_id'];

function fuelTypeGroup(string $type): ?string {
    $value = strtolower(str_replace([' ', '_'], '', $type));
    if (str_contains($value, 'diesel')) return 'Diesel';
    if (str_contains($value, 'petrol')) return 'Petrol';
    return null;
}
function efficiencyStatus(?float $value): string {
    if ($value === null) return 'Insufficient Data';
    if ($value >= 10) return 'Good';
    if ($value >= 5) return 'Moderate';
    return 'Low Efficiency';
}
function fuelDto(array $log): array {
    $dto = dto($log, [], ['is_updated', 'is_deleted']);
    $prev = row('SELECT mileage FROM fuel_logs WHERE vehicle_reg_number = ? AND (date < ? OR (date = ? AND id < ?)) AND COALESCE(is_deleted, 0) = 0 ORDER BY date DESC, id DESC LIMIT 1', [$log['vehicle_reg_number'], $log['date'], $log['date'], $log['id']]);
    $dto['fuelEfficiency'] = $prev && (float)$log['liters'] > 0 && (float)$log['mileage'] > (float)$prev['mileage'] ? round(((float)$log['mileage'] - (float)$prev['mileage']) / (float)$log['liters'], 2) : null;
    return $dto;
}
function fuelOwned(array $log, array $user): bool {
    if ($log['driver_username'] === $user['user_name']) return true;
    $reg = scalar('SELECT registration_no FROM vehicles WHERE driver_id = ?', [$user['id']]);
    return $reg && $log['vehicle_reg_number'] === $reg;
}
function fuelValues(array $data): array {
    $reg = (string)requireField($data, 'vehicleRegNumber'); vehicleByReg($reg);
    foreach (['fuelType', 'liters', 'costPerLiter', 'mileage'] as $field) requireField($data, $field);
    if (!is_numeric($data['liters']) || (float)$data['liters'] <= 0 || !is_numeric($data['costPerLiter']) || (float)$data['costPerLiter'] < 0 || !is_numeric($data['mileage']) || (float)$data['mileage'] < 0) fail('Invalid fuel quantities');
    $values = fields($data, FUEL_FIELDS);
    $values['total_cost'] = round((float)$values['liters'] * (float)$values['cost_per_liter'], 2);
    $values['date'] ??= date('Y-m-d');
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$values['date']);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $values['date']) fail('Invalid fuel date');
    $latest = row('SELECT mileage FROM fuel_logs WHERE vehicle_reg_number = ? AND COALESCE(is_deleted, 0) = 0 ORDER BY date DESC, id DESC LIMIT 1', [$reg]);
    if ($latest && (float)$values['mileage'] < (float)$latest['mileage']) fail('Mileage cannot be lower than the previous fuel log');
    return $values;
}
function vehicleFuelReport(string $reg): array {
    $logs = rows('SELECT * FROM fuel_logs WHERE vehicle_reg_number = ? AND COALESCE(is_deleted, 0) = 0 ORDER BY date, id', [$reg]);
    $fillUps = []; $sum = 0.0; $count = 0; $totalLiters = 0.0; $totalCost = 0.0; $totalKm = 0.0; $latest = null;
    foreach ($logs as $index => $log) {
        $km = null; $eff = null;
        if ($index > 0 && (float)$log['mileage'] > (float)$logs[$index - 1]['mileage'] && (float)$log['liters'] > 0) {
            $km = (float)$log['mileage'] - (float)$logs[$index - 1]['mileage'];
            $eff = round($km / (float)$log['liters'], 2); $sum += $eff; $count++; $totalKm += $km; $latest = $eff;
        }
        $totalLiters += (float)$log['liters']; $totalCost += (float)$log['total_cost'];
        $fillUps[] = ['date' => $log['date'], 'mileage' => (float)$log['mileage'], 'liters' => (float)$log['liters'], 'cost' => (float)$log['total_cost'], 'efficiencyKmPerLiter' => $eff, 'kmDriven' => $km];
    }
    $average = $count ? round($sum / $count, 2) : null;
    return ['vehicleRegNumber' => $reg, 'latestEfficiency' => $latest, 'averageEfficiency' => $average, 'efficiencyStatus' => efficiencyStatus($average), 'totalLiters' => round($totalLiters, 2), 'totalCost' => round($totalCost, 2), 'costPerKm' => $totalKm > 0 ? round($totalCost / $totalKm, 2) : null, 'fillUps' => $fillUps];
}

route('GET', '/api/fuel/my-logs', function () {
    $user = actor(); $reg = scalar('SELECT registration_no FROM vehicles WHERE driver_id = ?', [$user['id']]);
    reply(array_map('fuelDto', rows('SELECT * FROM fuel_logs WHERE COALESCE(is_deleted, 0) = 0 AND (driver_username = ? OR (driver_username IS NULL AND vehicle_reg_number = ?) OR vehicle_reg_number = ?) ORDER BY date DESC, id DESC', [$user['user_name'], $reg, $reg])));
});
route('GET', '/api/fuel/my-logs/{id}', function ($p) {
    $user = actor(); $log = byId('fuel_logs', (int)$p['id']);
    if (!fuelOwned($log, $user)) fail('Access denied', 403);
    reply(fuelDto($log));
});
route('PUT', '/api/fuel/my-logs/{id}', function ($p) {
    $user = requireRole('DRIVER'); $id = (int)$p['id']; $log = byId('fuel_logs', $id);
    if ($log['driver_username'] !== $user['user_name']) fail('You can only edit your own fuel logs', 403);
    $data = body();
    $changes = fields($data, ['fuel_type', 'liters', 'cost_per_liter', 'mileage', 'date']);
    $fuelType = trim((string)($changes['fuel_type'] ?? $log['fuel_type']));
    $liters = $changes['liters'] ?? $log['liters'];
    $costPerLiter = $changes['cost_per_liter'] ?? $log['cost_per_liter'];
    $mileage = $changes['mileage'] ?? $log['mileage'];
    $date = (string)($changes['date'] ?? $log['date']);
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if ($fuelType === '' || !is_numeric($liters) || (float)$liters <= 0 || !is_numeric($costPerLiter) || (float)$costPerLiter < 0 || !is_numeric($mileage) || (float)$mileage < 0 || !$parsedDate || $parsedDate->format('Y-m-d') !== $date) fail('Invalid fuel log values');
    $previous = row('SELECT mileage FROM fuel_logs WHERE vehicle_reg_number = ? AND COALESCE(is_deleted, 0) = 0 AND id <> ? AND (date < ? OR (date = ? AND id < ?)) ORDER BY date DESC, id DESC LIMIT 1', [$log['vehicle_reg_number'], $id, $date, $date, $id]);
    $next = row('SELECT mileage FROM fuel_logs WHERE vehicle_reg_number = ? AND COALESCE(is_deleted, 0) = 0 AND id <> ? AND (date > ? OR (date = ? AND id > ?)) ORDER BY date, id LIMIT 1', [$log['vehicle_reg_number'], $id, $date, $date, $id]);
    if (($previous && (float)$mileage < (float)$previous['mileage']) || ($next && (float)$mileage > (float)$next['mileage'])) fail('Mileage must fit between the previous and next fuel logs');
    $changes['total_cost'] = round((float)$liters * (float)$costPerLiter, 2);
    $changes += ['is_updated' => 1, 'updated_at' => now(), 'updated_by' => $user['user_name'], 'status' => 'PENDING'];
    updateRow('fuel_logs', $id, $changes);
    reply(fuelDto(byId('fuel_logs', $id)), 'Fuel log updated');
});
route('POST', '/api/fuel/add', function () {
    $user = requireRole('DRIVER'); $values = fuelValues(body());
    $assigned = row('SELECT id FROM vehicles WHERE registration_no = ? AND driver_id = ? AND is_deleted = 0', [$values['vehicle_reg_number'], $user['id']]);
    $onTrip = row("SELECT id FROM trips WHERE vehicle_reg_number = ? AND driver_username = ? AND status IN ('ASSIGNED', 'STARTED') AND is_deleted = 0 LIMIT 1", [$values['vehicle_reg_number'], $user['user_name']]);
    if (!$assigned && !$onTrip) fail('Vehicle is not assigned to you', 403);
    if (!empty($values['trip_id']) && !row("SELECT id FROM trips WHERE id = ? AND vehicle_reg_number = ? AND driver_username = ? AND status IN ('ASSIGNED', 'STARTED') AND is_deleted = 0", [$values['trip_id'], $values['vehicle_reg_number'], $user['user_name']])) fail('Trip is not assigned to you', 403);
    $values['driver_username'] = $user['user_name']; $values['uploaded_by'] = $user['user_name']; $values['status'] = 'PENDING';
    $id = insertRow('fuel_logs', $values);
    reply(fuelDto(byId('fuel_logs', $id)), 'Fuel log added', 201);
});
route('GET', '/api/fuel/summary', function () {
    actor(); $totals = ['Diesel' => 0.0, 'Petrol' => 0.0]; $cost = 0.0;
    foreach (rows('SELECT fuel_type, liters, total_cost FROM fuel_logs WHERE YEAR(date) = YEAR(CURDATE()) AND MONTH(date) = MONTH(CURDATE()) AND COALESCE(is_deleted, 0) = 0') as $log) {
        $group = fuelTypeGroup($log['fuel_type']); if ($group) $totals[$group] += (float)$log['liters']; $cost += (float)$log['total_cost'];
    }
    reply(['totalDiesel' => $totals['Diesel'], 'totalPetrol' => $totals['Petrol'], 'totalVolume' => array_sum($totals), 'totalCost' => $cost]);
});
route('GET', '/api/fuel/chart', function () {
    actor(); $months = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
    $data = ['Diesel' => array_fill(0, 12, 0.0), 'Petrol' => array_fill(0, 12, 0.0)];
    foreach (rows('SELECT MONTH(date) AS month_no, fuel_type, SUM(liters) AS liters FROM fuel_logs WHERE YEAR(date) = YEAR(CURDATE()) AND COALESCE(is_deleted, 0) = 0 GROUP BY MONTH(date), fuel_type') as $r) {
        $group = fuelTypeGroup($r['fuel_type']); if ($group) $data[$group][(int)$r['month_no'] - 1] += (float)$r['liters'];
    }
    reply(['months' => $months, 'data' => $data]);
});
route('GET', '/api/fuel/stats', function () {
    actor(); $result = [];
    foreach (rows('SELECT DISTINCT vehicle_reg_number FROM fuel_logs WHERE COALESCE(is_deleted, 0) = 0') as $r) {
        $report = vehicleFuelReport($r['vehicle_reg_number']);
        $result[] = ['vehicleRegNumber' => $r['vehicle_reg_number'], 'fuelEfficiency' => $report['latestEfficiency'], 'totalSpending' => $report['totalCost'], 'efficiencyStatus' => $report['efficiencyStatus']];
    }
    reply($result);
});
route('GET', '/api/fuel/efficiency', function () {
    requireRole('ADMIN', 'CONTROLLER'); $vehicles = []; $averages = []; $counts = ['Good' => 0, 'Moderate' => 0, 'Low Efficiency' => 0];
    foreach (rows('SELECT DISTINCT vehicle_reg_number FROM fuel_logs WHERE COALESCE(is_deleted, 0) = 0') as $r) {
        $report = vehicleFuelReport($r['vehicle_reg_number']); $vehicles[] = $report;
        if ($report['averageEfficiency'] !== null) { $averages[] = $report['averageEfficiency']; $counts[$report['efficiencyStatus']]++; }
    }
    reply(['fleetAverageEfficiency' => $averages ? round(array_sum($averages) / count($averages), 2) : null, 'totalVehicles' => count($vehicles), 'goodEfficiencyCount' => $counts['Good'], 'moderateEfficiencyCount' => $counts['Moderate'], 'lowEfficiencyCount' => $counts['Low Efficiency'], 'vehicles' => $vehicles]);
});
route('GET', '/api/fuel/controller/deleted', function () { requireRole('ADMIN', 'CONTROLLER'); reply(array_map('fuelDto', allRows('fuel_logs', true))); });
route('GET', '/api/fuel/all', function () { requireRole('ADMIN', 'CONTROLLER'); reply(array_map('fuelDto', allRows('fuel_logs'))); });
route('GET', '/api/fuel/vehicle/{vehicleRegNumber}', function ($p) { actor(); reply(array_map('fuelDto', rows('SELECT * FROM fuel_logs WHERE vehicle_reg_number = ? AND COALESCE(is_deleted, 0) = 0 ORDER BY date DESC, id DESC', [$p['vehicleRegNumber']]))); });
route('GET', '/api/fuel/controller/search/{id}', function ($p) { requireRole('ADMIN', 'CONTROLLER'); reply(fuelDto(byId('fuel_logs', (int)$p['id']))); });
route('GET', '/api/fuel/log/{id}', function ($p) { actor(); reply(fuelDto(byId('fuel_logs', (int)$p['id']))); });
route('POST', '/api/fuel/controller/add', function () {
    $user = requireRole('ADMIN', 'CONTROLLER'); $values = fuelValues(body());
    $values['uploaded_by'] = $user['user_name']; $values['status'] = 'APPROVED';
    $id = insertRow('fuel_logs', $values); reply(fuelDto(byId('fuel_logs', $id)), 'Fuel log added', 201);
});
route('PUT', '/api/fuel/controller/{id}', function ($p) {
    $user = requireRole('ADMIN', 'CONTROLLER'); $id = (int)$p['id']; $old = byId('fuel_logs', $id);
    $changes = fields(body(), FUEL_FIELDS);
    if (isset($changes['liters']) || isset($changes['cost_per_liter'])) $changes['total_cost'] = round((float)($changes['liters'] ?? $old['liters']) * (float)($changes['cost_per_liter'] ?? $old['cost_per_liter']), 2);
    $changes += ['is_updated' => 1, 'updated_at' => now(), 'updated_by' => $user['user_name']];
    updateRow('fuel_logs', $id, $changes); reply(fuelDto(byId('fuel_logs', $id)), 'Fuel log updated');
});
route('DELETE', '/api/fuel/controller/{id}', function ($p) {
    requireRole('ADMIN', 'CONTROLLER'); $id = (int)$p['id']; byId('fuel_logs', $id);
    updateRow('fuel_logs', $id, ['is_deleted' => 1, 'deleted_at' => now()]); reply(null, 'Fuel log deleted');
});
route('PATCH', '/api/fuel/controller/restore/{id}', function ($p) {
    requireRole('ADMIN', 'CONTROLLER'); $id = (int)$p['id']; byId('fuel_logs', $id, true);
    updateRow('fuel_logs', $id, ['is_deleted' => 0, 'deleted_at' => null]); reply(fuelDto(byId('fuel_logs', $id)), 'Fuel log restored');
});
foreach (['approve' => 'APPROVED', 'reject' => 'REJECTED'] as $action => $status) {
    route('PATCH', "/api/fuel/controller/{id}/$action", function ($p) use ($status) {
        requireRole('ADMIN', 'CONTROLLER'); $id = (int)$p['id']; byId('fuel_logs', $id);
        updateRow('fuel_logs', $id, ['status' => $status]); reply(fuelDto(byId('fuel_logs', $id)), 'Fuel log status updated');
    });
}
