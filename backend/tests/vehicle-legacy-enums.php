<?php
declare(strict_types=1);

require __DIR__ . '/../src/Core.php';
require __DIR__ . '/../src/Vehicles.php';
loadEnv(__DIR__ . '/../.env');

$columns = rows("SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles' AND COLUMN_NAME IN ('status', 'fuel_type')");
$types = array_column($columns, 'DATA_TYPE', 'COLUMN_NAME');
foreach (['status' => 'AVAILABLE', 'fuel_type' => 'SUPER_DIESEL'] as $column => $value) {
    $stored = vehicleStorageValues([$column => $value])[$column];
    $numeric = in_array($types[$column] ?? '', ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'], true);
    if ($stored !== ($numeric ? array_search($value, VEHICLE_ENUMS[$column], true) : $value)) {
        throw new RuntimeException("Vehicle $column storage mapping failed");
    }
}

foreach (rows('SELECT * FROM vehicles WHERE is_deleted = 0') as $vehicle) {
    $result = vehicleDto($vehicle);
    if (!in_array($result['status'], VEHICLE_ENUMS['status'], true)) throw new RuntimeException('Vehicle status read mapping failed');
    if ($result['fuelType'] !== null && !in_array($result['fuelType'], VEHICLE_ENUMS['fuel_type'], true)) throw new RuntimeException('Vehicle fuel type read mapping failed');
}

echo 'Vehicle enum compatibility checks passed; no data changed.' . PHP_EOL;
