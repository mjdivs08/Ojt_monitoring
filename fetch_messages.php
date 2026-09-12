<?php
require_once 'config.php';

requireLogin();

header('Content-Type: text/html; charset=UTF-8');

$user_id         = (int)($_SESSION['user_id'] ?? 0);
$role            = $_SESSION['role'] ?? '';
$conversation_id = isset($_GET['conversation_id'])
    ? (int)$_GET['conversation_id']
    : 0;

if (
    $user_id <= 0 ||
    $conversation_id <= 0 ||
    !in_array($role, ['student', 'coordinator'], true)
) {
    http_response_code(400);

    exit('
        <div class="chat-empty-state">
            Invalid conversation.
        </div>
    ');
}

/*
|--------------------------------------------------------------------------
| Verify conversation access
|--------------------------------------------------------------------------
*/

if ($role === 'student') {
    $student = getStudentByUserId($conn, $user_id);

    if (!$student) {
        http_response_code(403);
        exit('Student profile not found.');
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

    $verify->bind_param(
        'iii',
        $conversation_id,
        $user_id,
        $user_id
    );
}

$verify->execute();
$verifyResult = $verify->get_result();

if (!$verifyResult || $verifyResult->num_rows === 0) {
    $verify->close();

    http_response_code(403);

    exit('
        <div class="chat-empty-state">
            You cannot view this conversation.
        </div>
    ');
}

$verify->close();

/*
|--------------------------------------------------------------------------
| Mark received messages as seen
|--------------------------------------------------------------------------
*/

$seen = $conn->prepare("
    UPDATE messages
    SET seen = 1
    WHERE conversation_id = ?
      AND sender_id <> ?
      AND seen = 0
      AND deleted = 0
");

if ($seen) {
    $seen->bind_param(
        'ii',
        $conversation_id,
        $user_id
    );

    $seen->execute();
    $seen->close();
}

/*
|--------------------------------------------------------------------------
| Load messages
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        m.id,
        m.sender_id,
        m.message,
        m.attachment,
        m.seen,
        m.deleted,
        m.created_at,
        u.full_name
    FROM messages m
    INNER JOIN users u
        ON u.id = m.sender_id
    WHERE m.conversation_id = ?
    ORDER BY m.created_at ASC, m.id ASC
");

if (!$stmt) {
    http_response_code(500);

    exit('
        <div class="chat-empty-state">
            Unable to prepare messages:
            ' . htmlspecialchars($conn->error) . '
        </div>
    ');
}

$stmt->bind_param('i', $conversation_id);
$stmt->execute();

$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    $stmt->close();

    exit('
        <div class="chat-empty-state">
            <div class="chat-empty-icon">💬</div>
            <strong>No messages yet</strong>
            <span>Send the first message in this conversation.</span>
        </div>
    ');
}

while ($row = $result->fetch_assoc()) {
    $isMine    = (int)$row['sender_id'] === $user_id;
    $isDeleted = (int)$row['deleted'] === 1;

    $messageText = trim((string)$row['message']);
    $attachment  = trim((string)$row['attachment']);
    ?>

    <div class="chat-message <?= $isMine ? 'mine' : 'other' ?>">

        <?php if (!$isMine): ?>
            <div class="chat-message-sender">
                <?= htmlspecialchars(
                    $row['full_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>
        <?php endif; ?>

        <div class="chat-bubble">

            <?php if ($isDeleted): ?>

                <span class="chat-deleted-message">
                    This message was deleted.
                </span>

            <?php else: ?>

                <?php if ($messageText !== ''): ?>
                    <div class="chat-message-text">
                        <?= nl2br(
                            htmlspecialchars(
                                $messageText,
                                ENT_QUOTES,
                                'UTF-8'
                            )
                        ) ?>
                    </div>
                <?php endif; ?>

                <?php if ($attachment !== ''): ?>
                    <?php
                    $safeFile = basename($attachment);
                    $extension = strtolower(
                        pathinfo($safeFile, PATHINFO_EXTENSION)
                    );

                    $fileUrl =
                        BASE_URL .
                        'uploads/chat/' .
                        rawurlencode($safeFile);

                    $imageTypes = [
                        'jpg',
                        'jpeg',
                        'png',
                        'gif',
                        'webp'
                    ];
                    ?>

                    <?php if (in_array($extension, $imageTypes, true)): ?>

                        <a
                            href="<?= htmlspecialchars($fileUrl) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="chat-image-link"
                        >
                            <img
                                src="<?= htmlspecialchars($fileUrl) ?>"
                                alt="Chat attachment"
                                class="chat-attachment-image"
                            >
                        </a>

                    <?php else: ?>

                        <a
                            href="<?= htmlspecialchars($fileUrl) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="chat-file-card"
                        >
                            <span class="chat-file-icon">📄</span>

                            <span class="chat-file-details">
                                <strong>
                                    <?= htmlspecialchars($safeFile) ?>
                                </strong>

                                <small>
                                    <?= strtoupper(
                                        htmlspecialchars($extension)
                                    ) ?> file
                                </small>
                            </span>
                        </a>

                    <?php endif; ?>
                <?php endif; ?>

            <?php endif; ?>

        </div>

        <div class="chat-message-meta">
            <span>
                <?= date(
                    'M d, Y · h:i A',
                    strtotime($row['created_at'])
                ) ?>
            </span>

            <?php if ($isMine && !$isDeleted): ?>
                <span class="chat-seen-status">
                    <?= (int)$row['seen'] === 1
                        ? 'Seen'
                        : 'Sent'
                    ?>
                </span>
            <?php endif; ?>
        </div>

    </div>

    <?php
}

$stmt->close();