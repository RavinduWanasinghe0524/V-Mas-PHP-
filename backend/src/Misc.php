<?php
declare(strict_types=1);

route('GET', '/api/alerts/dashboard', function () {
    requireRole('ADMIN', 'CONTROLLER'); $alerts = [];
    foreach (allRows('vehicles') as $vehicle) {
        $reg = $vehicle['registration_no'];
        foreach (rows('SELECT * FROM service_intervals WHERE vehicle_type = ?', [$vehicle['vehicle_type']]) as $interval) {
            $last = scalar('SELECT MAX(current_mileage_km) FROM service_records WHERE vehicle_reg_number = ? AND service_type = ? AND is_deleted = 0 AND status = ?', [$reg, $interval['service_type'], 'APPROVED']);
            $base = $last !== null && $last !== false ? (int)$last : (int)($vehicle['initial_mileage_km'] ?? 0);
            $due = $base + (int)$interval['interval_km']; $current = (int)$vehicle['current_mileage_km']; $remaining = $due - $current;
            if ($remaining > 200) continue;
            $overdue = $remaining <= 0;
            $alerts[] = ['severity' => $overdue ? 'OVERDUE' : 'UPCOMING', 'type' => 'SERVICE_DUE', 'vehicleRegNumber' => $reg, 'title' => $overdue ? 'Service Overdue' : 'Service Milestone Soon', 'message' => $interval['service_type'] . " due at $due km (current: $current km, " . abs($remaining) . ' km ' . ($overdue ? 'overdue' : 'remaining') . ')', 'daysRemaining' => $overdue ? -999 + $remaining : intdiv($remaining, 10)];
        }
        foreach (['insurance_expiry_date' => 'Insurance', 'license_expiry_date' => 'Vehicle license'] as $column => $label) {
            if (!$vehicle[$column]) continue;
            $days = (new DateTimeImmutable('today'))->diff(new DateTimeImmutable($vehicle[$column]))->format('%r%a'); $days = (int)$days;
            if ($days > 30) continue;
            $overdue = $days < 0;
            $alerts[] = ['severity' => $overdue ? 'OVERDUE' : 'UPCOMING', 'type' => 'DOCUMENT_EXPIRY', 'vehicleRegNumber' => $reg, 'title' => "$label " . ($overdue ? 'Expired' : 'Expiring Soon'), 'message' => "$label expiry: " . $vehicle[$column], 'daysRemaining' => $days];
        }
    }
    usort($alerts, fn($a, $b) => $a['daysRemaining'] <=> $b['daysRemaining']);
    $overdue = count(array_filter($alerts, fn($a) => $a['severity'] === 'OVERDUE'));
    reply(['totalAlerts' => count($alerts), 'overdueCount' => $overdue, 'upcomingCount' => count($alerts) - $overdue, 'alerts' => $alerts]);
});

route('POST', '/api/notifications', function () {
    actor(); $data = body(); notify((string)requireField($data, 'vehicleRegNumber'), (string)requireField($data, 'message'), (string)requireField($data, 'type'));
    reply(null, 'Notification created', 201);
});
route('GET', '/api/notifications/unread', function () {
    actor(); reply(array_map(fn($r) => dto($r, [], ['is_read']), rows('SELECT * FROM notifications WHERE is_read = 0 ORDER BY created_at DESC, id DESC')));
});
route('GET', '/api/notifications', function () {
    actor(); reply(array_map(fn($r) => dto($r, [], ['is_read']), rows('SELECT * FROM notifications ORDER BY created_at DESC, id DESC')));
});
route('PATCH', '/api/notifications/read-all', function () { actor(); query('UPDATE notifications SET is_read = 1 WHERE is_read = 0'); reply(null, 'Notifications marked as read'); });
route('PATCH', '/api/notifications/{id}/read', function ($p) {
    actor(); $id = (int)$p['id']; byId('notifications', $id, true); updateRow('notifications', $id, ['is_read' => 1]);
    reply(dto(byId('notifications', $id, true), [], ['is_read']), 'Notification marked as read');
});

route('GET', '/api/employees', function () { requireRole('ADMIN', 'CONTROLLER'); reply(array_map('dto', rows('SELECT * FROM employees ORDER BY id DESC'))); });
route('GET', '/api/employees/{id}', function ($p) { requireRole('ADMIN', 'CONTROLLER'); reply(dto(byId('employees', (int)$p['id'], true))); });
route('POST', '/api/employees', function () {
    requireRole('ADMIN', 'CONTROLLER'); $values = fields(body(), ['first_name', 'last_name', 'email']);
    foreach (['first_name', 'last_name', 'email'] as $key) if (empty($values[$key])) fail("$key is required");
    $id = insertRow('employees', $values); reply(dto(byId('employees', $id, true)), 'Employee created', 201);
});
route('PUT', '/api/employees/{id}', function ($p) {
    requireRole('ADMIN', 'CONTROLLER'); $id = (int)$p['id']; byId('employees', $id, true);
    updateRow('employees', $id, fields(body(), ['first_name', 'last_name', 'email'])); reply(dto(byId('employees', $id, true)), 'Employee updated');
});
route('DELETE', '/api/employees/{id}', function ($p) {
    requireRole('ADMIN', 'CONTROLLER'); $id = (int)$p['id']; byId('employees', $id, true);
    query('DELETE FROM employees WHERE id = ?', [$id]); reply(null, 'Employee deleted');
});
