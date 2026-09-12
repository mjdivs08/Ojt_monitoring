<?php
require_once 'config.php';
requireLogin();

if (!isset($_GET['id']) || !ctype_digit((string)$_GET['id'])) {
    http_response_code(400);
    exit('Invalid document.');
}

$submissionId = (int)$_GET['id'];
$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

$stmt = $conn->prepare("
    SELECT
        ds.id,
        ds.file_path,
        ds.student_id,
        s.user_id AS student_user_id,
        s.coordinator_id,
        dr.doc_name
    FROM document_submissions ds
    INNER JOIN students s ON s.id = ds.student_id
    INNER JOIN document_requirements dr ON dr.id = ds.requirement_id
    WHERE ds.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $submissionId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    exit('Document submission not found.');
}

$document = $result->fetch_assoc();
$stmt->close();

$allowed = false;

if ($role === 'admin') {
    $allowed = true;
} elseif ($role === 'coordinator') {
    $allowed = ((int)$document['coordinator_id'] === $userId);
} elseif ($role === 'student') {
    $allowed = ((int)$document['student_user_id'] === $userId);
}

if (!$allowed) {
    http_response_code(403);
    exit('You are not allowed to view this document.');
}

$storedPath = trim((string)$document['file_path']);

if ($storedPath === '') {
    http_response_code(404);
    exit('No file was uploaded.');
}

$fileName = basename($storedPath);

$possibleFiles = [
    __DIR__ . '/uploads/documents/' . $fileName,
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
        'Uploaded file was not found on the server: ' .
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
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];

if (!isset($mimeTypes[$extension])) {
    http_response_code(415);
    exit('Unsupported document type.');
}

header('Content-Type: ' . $mimeTypes[$extension]);
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');

$inlineTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
$disposition = in_array($extension, $inlineTypes, true) ? 'inline' : 'attachment';

header(
    'Content-Disposition: ' .
    $disposition .
    '; filename="' .
    str_replace('"', '', $fileName) .
    '"'
);

readfile($filePath);
exit;