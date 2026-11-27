<?php
declare(strict_types=1);

require __DIR__ . '/../src/Core.php';
loadEnv(__DIR__ . '/../.env');
require __DIR__ . '/../src/VehiclePasses.php';

if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }

$hodEmail = $argv[1] ?? 'hod@example.invalid';
$deanEmail = $argv[2] ?? 'dean@example.invalid';
if (!filter_var($hodEmail, FILTER_VALIDATE_EMAIL) || !filter_var($deanEmail, FILTER_VALIDATE_EMAIL)) {
    throw new RuntimeException('Pass two valid test recipient addresses');
}
putenv('PASS_HOD_EMAILS_JSON=' . json_encode(['Computing|Software Engineering' => $hodEmail]));
putenv('PASS_DEAN_EMAILS_JSON=' . json_encode(['Computing' => $deanEmail]));

function assertPass(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

function testToken(string $html): string {
    if (!preg_match('/approve-pass\?token=([a-f0-9]{64})/', $html, $matches)) {
        throw new RuntimeException('Approval email has no valid link');
    }
    return $matches[1];
}

$messages = [];
$sender = function (string $to, string $subject, string $html) use (&$messages): bool {
    $messages[] = compact('to', 'subject', 'html');
    return true;
};

db()->beginTransaction();
try {
    $created = createPassApplication([
        'formType' => 'SUSL/GA/TD/03', 'faculty' => 'Computing',
        'dept' => 'Software Engineering', 'name' => 'Approval Flow Test',
        'mob' => '0000000000', 'reg' => 'APPROVAL-TEST-' . bin2hex(random_bytes(5)),
        'status' => 'temp', 'd1' => date('Y-m-d'),
    ], $sender);
    assertPass($created['emailSent'] === true, 'HOD email was not prepared');
    $id = (int)$created['pass']['id'];
    $code = $created['pass']['passCode'];
    assertPass($created['pass']['faculty'] === 'Computing' && $created['pass']['department'] === 'Software Engineering', 'Application faculty or department was not stored');
    assertPass($created['pass']['status'] === 'PENDING_HOD', 'New pass has incorrect status');
    assertPass(count($messages) === 1 && $messages[0]['to'] === $hodEmail, 'HOD routing failed');
    $hodToken = testToken($messages[0]['html']);
    $stored = byId('vehicle_passes', $id, true);
    assertPass($stored['approval_token_hash'] === hash('sha256', $hodToken), 'HOD token hash was not stored');
    assertPass(str_contains($messages[0]['subject'], $code), 'Pass code missing from HOD email');

    $result = decidePassApproval($hodToken, 'APPROVE', $sender);
    assertPass($result === ['status' => 'PENDING_DEAN', 'emailSent' => true], 'HOD approval did not advance to Dean');
    assertPass(count($messages) === 2 && $messages[1]['to'] === $deanEmail, 'Dean email was not prepared immediately');
    $deanToken = testToken($messages[1]['html']);
    $stored = byId('vehicle_passes', $id, true);
    assertPass($stored['hod_approved_at'] !== null && $stored['dean_approved_at'] === null, 'HOD timestamp is incorrect');
    assertPass($stored['approval_token_hash'] === hash('sha256', $deanToken), 'Dean token was not stored');
    try {
        decidePassApproval($hodToken, 'APPROVE', $sender);
        throw new RuntimeException('HOD token could be reused');
    } catch (ApiError $error) {
        assertPass($error->status === 404, 'Reused HOD token returned an unexpected error');
    }

    $result = decidePassApproval($deanToken, 'APPROVE', $sender);
    assertPass($result === ['status' => 'APPROVED', 'emailSent' => null], 'Dean approval failed');
    $stored = byId('vehicle_passes', $id, true);
    assertPass($stored['dean_approved_at'] !== null && $stored['approval_token_hash'] === null, 'Dean approval did not clear token');
    assertPass(count($messages) === 2, 'Unexpected email was prepared');

    $rejectedApplication = createPassApplication([
        'formType' => 'SUSL/GA/TD/01', 'faculty' => 'Computing',
        'dept' => 'Software Engineering', 'name' => 'Rejection Flow Test',
        'tel' => '0000000000', 'vno' => 'REJECT-TEST-' . bin2hex(random_bytes(5)),
        'place' => 'Test destination', 'reason' => 'Rollback-only rejection test',
        'dep' => date('Y-m-d'),
    ], $sender);
    $rejectedId = (int)$rejectedApplication['pass']['id'];
    assertPass($rejectedApplication['pass']['formType'] === 'SUSL/GA/TD/01' && $rejectedApplication['emailSent'] === true, 'Form 01 application failed');
    $rejectToken = testToken($messages[2]['html']);
    $result = decidePassApproval($rejectToken, 'REJECT', $sender);
    assertPass($result === ['status' => 'REJECTED', 'emailSent' => null], 'HOD rejection failed');
    $rejected = byId('vehicle_passes', $rejectedId, true);
    assertPass($rejected['rejection_reason'] !== null && $rejected['approval_token_hash'] === null, 'Rejection did not invalidate link');
    assertPass(count($messages) === 3, 'Rejection unexpectedly emailed Dean');

    $failedApplication = createPassApplication([
        'formType' => 'SUSL/GA/TD/03', 'faculty' => 'Computing',
        'dept' => 'Software Engineering', 'name' => 'Failed Email Test',
        'mob' => '0000000000', 'reg' => 'FAIL-TEST-' . bin2hex(random_bytes(5)),
        'status' => 'temp', 'd1' => date('Y-m-d'),
    ], fn() => false);
    $failedId = (int)$failedApplication['pass']['id'];
    assertPass($failedApplication['emailSent'] === false, 'Failed email was reported as sent');
    $failed = byId('vehicle_passes', $failedId, true);
    assertPass($failed['approval_token_hash'] === null && $failed['status'] === 'PENDING_HOD', 'Failed email left an active approval token');

    db()->rollBack();
    echo "HOD, Dean, rejection, and failed-email checks passed for $hodEmail and $deanEmail; emails intercepted, test records rolled back.\n";
} catch (Throwable $error) {
    db()->rollBack();
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
