<?php
require_once 'config.php';
requireLogin();

if (!isset($_GET['id']) || !ctype_digit((string)$_GET['id'])) {
    http_response_code(400);
    exit('Invalid DTR.');
}

$logId = (int)$_GET['id'];
$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

$stmt = $conn->prepare("
    SELECT
        wl.dtr_photo,
        wl.student_id,
        s.user_id AS student_user_id,
        s.coordinator_id
    FROM weekly_logs wl
    INNER JOIN students s ON s.id = wl.student_id
    WHERE wl.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $logId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    exit('DTR not found.');
}

$log = $result->fetch_assoc();
$stmt->close();

$allowed = false;

if ($role === 'admin') {
    $allowed = true;
} elseif ($role === 'coordinator') {
    $allowed = ((int)$log['coordinator_id'] === $userId);
} elseif ($role === 'student') {
    $allowed = ((int)$log['student_user_id'] === $userId);
}

if (!$allowed) {
    http_response_code(403);
    exit('You are not allowed to view this DTR.');
}

$storedPath = trim((string)$log['dtr_photo']);

if ($storedPath === '') {
    http_response_code(404);
    exit('No DTR file was uploaded.');
}

$fileName = basename($storedPath);

$possibleFiles = [
    __DIR__ . '/uploads/documents/' . $fileName,
    __DIR__ . '/uploads/dtr/' . $fileName,
    __DIR__ . '/uploads/' . $fileName,
    __DIR__ . '/' . ltrim($storedPath, '/\\')
];

$filePath = null;

foreach ($possibleFiles as $possibleFile) {
    if (is_file($possibleFile) && is_readable($possibleFile)) {
        $filePath = $possibleFile;
        break;
    }
}

if (!$filePath) {
    http_response_code(404);
    exit(
        'DTR file was not found on the server: ' .
        htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8')
    );
}

$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

$mimeTypes = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'pdf'  => 'application/pdf'
];

if (!isset($mimeTypes[$extension])) {
    http_response_code(415);
    exit('Unsupported DTR file type.');
}

header('Content-Type: ' . $mimeTypes[$extension]);
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . str_replace('"', '', $fileName) . '"');

readfile($filePath);
exit;