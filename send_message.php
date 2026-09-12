<?php
require_once 'config.php';

requireLogin();

header('Content-Type: application/json; charset=UTF-8');

$user_id = (int)($_SESSION['user_id'] ?? 0);
$role    = $_SESSION['role'] ?? '';

$response = [
    'success' => false,
    'message' => ''
];

/*
|--------------------------------------------------------------------------
| Validate request
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    $response['message'] = 'Invalid request.';
    echo json_encode($response);
    exit;
}

if (!in_array($role, ['student', 'coordinator'], true)) {
    http_response_code(403);

    $response['message'] = 'Access denied.';
    echo json_encode($response);
    exit;
}

$conversation_id = isset($_POST['conversation_id'])
    ? (int)$_POST['conversation_id']
    : 0;

$message = trim($_POST['message'] ?? '');

if ($conversation_id <= 0) {
    $response['message'] = 'Please select a conversation first.';
    echo json_encode($response);
    exit;
}

/*
|--------------------------------------------------------------------------
| Verify conversation ownership
|--------------------------------------------------------------------------
*/

if ($role === 'student') {
    $student = getStudentByUserId($conn, $user_id);

    if (!$student) {
        $response['message'] = 'Student profile not found.';
        echo json_encode($response);
        exit;
    }

    $student_id     = (int)$student['id'];
    $coordinator_id = (int)$student['coordinator_id'];

    $verify = $conn->prepare("
        SELECT id
        FROM conversations
        WHERE id = ?
          AND student_id = ?
          AND coordinator_id = ?
        LIMIT 1
    ");

    if (!$verify) {
        $response['message'] =
            'Unable to verify conversation: ' . $conn->error;

        echo json_encode($response);
        exit;
    }

    $verify->bind_param(
        'iii',
        $conversation_id,
        $student_id,
        $coordinator_id
    );
} else {
    $verify = $conn->prepare("
        SELECT c.id
        FROM conversations c
        INNER JOIN students s
            ON s.id = c.student_id
        WHERE c.id = ?
          AND c.coordinator_id = ?
          AND s.coordinator_id = ?
        LIMIT 1
    ");

    if (!$verify) {
        $response['message'] =
            'Unable to verify conversation: ' . $conn->error;

        echo json_encode($response);
        exit;
    }

    $verify->bind_param(
        'iii',
        $conversation_id,
        $user_id,
        $user_id
    );
}

if (!$verify->execute()) {
    $response['message'] =
        'Conversation verification failed: ' . $verify->error;

    $verify->close();

    echo json_encode($response);
    exit;
}

$result = $verify->get_result();

if (!$result || $result->num_rows === 0) {
    $verify->close();

    http_response_code(403);

    $response['message'] =
        'You are not allowed to send to this conversation.';

    echo json_encode($response);
    exit;
}

$verify->close();

/*
|--------------------------------------------------------------------------
| Attachment upload
|--------------------------------------------------------------------------
*/

$attachment = null;

if (
    isset($_FILES['attachment']) &&
    $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE
) {
    if ($_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
        $response['message'] = 'Attachment upload failed.';
        echo json_encode($response);
        exit;
    }

    $allowedExtensions = [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'pdf',
        'doc',
        'docx'
    ];

    $originalName = $_FILES['attachment']['name'];

    $extension = strtolower(
        pathinfo($originalName, PATHINFO_EXTENSION)
    );

    if (!in_array($extension, $allowedExtensions, true)) {
        $response['message'] =
            'Allowed files: JPG, PNG, GIF, WEBP, PDF, DOC, and DOCX.';

        echo json_encode($response);
        exit;
    }

    $maxSize = 10 * 1024 * 1024;

    if ((int)$_FILES['attachment']['size'] > $maxSize) {
        $response['message'] =
            'Attachment must not exceed 10 MB.';

        echo json_encode($response);
        exit;
    }

    $uploadDirectory = __DIR__ . '/uploads/chat/';

    if (!is_dir($uploadDirectory)) {
        if (
            !mkdir($uploadDirectory, 0775, true) &&
            !is_dir($uploadDirectory)
        ) {
            $response['message'] =
                'Unable to create the chat upload folder.';

            echo json_encode($response);
            exit;
        }
    }

    try {
        $filename =
            date('YmdHis') . '_' .
            bin2hex(random_bytes(8)) . '.' .
            $extension;
    } catch (Throwable $error) {
        $response['message'] =
            'Unable to generate an attachment filename.';

        echo json_encode($response);
        exit;
    }

    $destination = $uploadDirectory . $filename;

    if (
        !move_uploaded_file(
            $_FILES['attachment']['tmp_name'],
            $destination
        )
    ) {
        $response['message'] =
            'Unable to save the attachment.';

        echo json_encode($response);
        exit;
    }

    $attachment = $filename;
}

/*
|--------------------------------------------------------------------------
| Validate message content
|--------------------------------------------------------------------------
*/

if ($message === '' && $attachment === null) {
    $response['message'] = 'Message is empty.';
    echo json_encode($response);
    exit;
}

if (mb_strlen($message) > 5000) {
    $response['message'] =
        'Message must not exceed 5,000 characters.';

    echo json_encode($response);
    exit;
}

/*
|--------------------------------------------------------------------------
| Save message
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    INSERT INTO messages (
        conversation_id,
        sender_id,
        message,
        attachment,
        seen,
        deleted
    )
    VALUES (?, ?, ?, ?, 0, 0)
");

if (!$stmt) {
    if ($attachment !== null) {
        $savedFile = __DIR__ . '/uploads/chat/' . $attachment;

        if (is_file($savedFile)) {
            unlink($savedFile);
        }
    }

    $response['message'] =
        'Prepare failed: ' . $conn->error;

    echo json_encode($response);
    exit;
}

$stmt->bind_param(
    'iiss',
    $conversation_id,
    $user_id,
    $message,
    $attachment
);

if (!$stmt->execute()) {
    if ($attachment !== null) {
        $savedFile = __DIR__ . '/uploads/chat/' . $attachment;

        if (is_file($savedFile)) {
            unlink($savedFile);
        }
    }

    $response['message'] =
        'Insert failed: ' . $stmt->error;

    $stmt->close();

    echo json_encode($response);
    exit;
}

$message_id = (int)$stmt->insert_id;

$stmt->close();

/*
|--------------------------------------------------------------------------
| Update conversation activity
|--------------------------------------------------------------------------
*/

$update = $conn->prepare("
    UPDATE conversations
    SET updated_at = NOW()
    WHERE id = ?
");

if ($update) {
    $update->bind_param('i', $conversation_id);
    $update->execute();
    $update->close();
}

/*
|--------------------------------------------------------------------------
| Successful response
|--------------------------------------------------------------------------
*/

$response['success']    = true;
$response['message']    = 'Message sent successfully.';
$response['message_id'] = $message_id;

echo json_encode($response);
exit;