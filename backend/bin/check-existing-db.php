<?php
declare(strict_types=1);

require __DIR__ . '/../src/Core.php';
loadEnv(__DIR__ . '/../.env');

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$schema = (string)file_get_contents(__DIR__ . '/../database/schema.sql');
preg_match_all('/CREATE TABLE IF NOT EXISTS (\w+)\s*\((.*?)\) ENGINE=/s', $schema, $tables, PREG_SET_ORDER);
$problems = [];

try {
    $connection = db();
    echo 'Connected to database: ' . $connection->query('SELECT DATABASE()')->fetchColumn() . PHP_EOL;
    foreach ($tables as $table) {
        $name = $table[1];
        $expected = [];
        foreach (explode("\n", $table[2]) as $line) {
            if (preg_match('/^\s*`?(\w+)`?\s+(?:BIGINT|INT|VARCHAR|LONGTEXT|TEXT|DOUBLE|DECIMAL|DATETIME|DATE|BOOLEAN)\b/i', $line, $column)) {
                $expected[] = $column[1];
            }
        }
        $statement = $connection->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $statement->execute([$name]);
        $actual = $statement->fetchAll(PDO::FETCH_COLUMN);
        if (!$actual) {
            $problems[] = "$name: table missing";
            continue;
        }
        $missing = array_diff($expected, $actual);
        if ($missing) $problems[] = "$name: missing columns " . implode(', ', $missing);
        else echo "$name: required columns present" . PHP_EOL;
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Database check failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}

if ($problems) {
    foreach ($problems as $problem) fwrite(STDERR, $problem . PHP_EOL);
    exit(1);
}
echo 'Required tables and columns are present. This check did not modify any data.' . PHP_EOL;
