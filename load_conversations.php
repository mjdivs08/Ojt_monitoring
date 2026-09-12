<?php
require_once 'config.php';
requireLogin();
header('Content-Type: text/html; charset=UTF-8');
$userId = (int)($_SESSION['user_id'] ?? 0);
$role   = $_SESSION['role'] ?? '';
if (!in_array($role, ['student', 'coordinator'], true)) {
    http_response_code(403);
    exit('<div class="chat-list-error">Access denied.</div>');
}
try {
    if ($role === 'student') {
        $student = getStudentByUserId($conn, $userId);

        if (!$student) {
            throw new Exception('Student profile not found.');
        }
        $studentId     = (int)$student['id'];
        $coordinatorId = (int)$student['coordinator_id'];
        $stmt = $conn->prepare("
            SELECT
                c.id,
                u.full_name,
                (
                    SELECT m.message
                    FROM messages m
                    WHERE m.conversation_id = c.id
                      AND m.deleted = 0
                    ORDER BY m.created_at DESC, m.id DESC
                    LIMIT 1
                ) AS last_message,
                (
                    SELECT m.created_at
                    FROM messages m
                    WHERE m.conversation_id = c.id
                      AND m.deleted = 0
                    ORDER BY m.created_at DESC, m.id DESC
                    LIMIT 1
                ) AS last_time,
                (
                    SELECT COUNT(*)
                    FROM messages m
                    WHERE m.conversation_id = c.id
                      AND m.sender_id <> ?
                      AND m.seen = 0
                      AND m.deleted = 0
                ) AS unread
            FROM conversations c
            INNER JOIN users u
                ON u.id = c.coordinator_id
            WHERE c.student_id = ?
              AND c.coordinator_id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            throw new Exception($conn->error);
        }
        $stmt->bind_param(
            'iii',
            $userId,
            $studentId,
            $coordinatorId
        );
    } else {
        $stmt = $conn->prepare("
            SELECT
                c.id,
                s.id AS student_id,
                u.full_name,
                (
                    SELECT m.message
                    FROM messages m
                    WHERE m.conversation_id = c.id
                      AND m.deleted = 0
                    ORDER BY m.created_at DESC, m.id DESC
                    LIMIT 1
                ) AS last_message,
                (
                    SELECT m.created_at
                    FROM messages m
                    WHERE m.conversation_id = c.id
                      AND m.deleted = 0
                    ORDER BY m.created_at DESC, m.id DESC
                    LIMIT 1
                ) AS last_time,
                (
                    SELECT COUNT(*)
                    FROM messages m
                    WHERE m.conversation_id = c.id
                      AND m.sender_id <> ?
                      AND m.seen = 0
                      AND m.deleted = 0
                ) AS unread
            FROM conversations c
            INNER JOIN students s
                ON s.id = c.student_id
            INNER JOIN users u
                ON u.id = s.user_id
            WHERE c.coordinator_id = ?
              AND s.coordinator_id = ?
            ORDER BY c.updated_at DESC, u.full_name ASC
        ");
        if (!$stmt) {
            throw new Exception($conn->error);
        }
        $stmt->bind_param(
            'iii',
            $userId,
            $userId,
            $userId
        );
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result || $result->num_rows === 0) {
        echo '
            <div class="chat-no-conversations">
                No conversations found.
            </div>
            <div
                id="chatNoSearchResults"
                style="display:none;"
            >
                No matching conversation.
            </div>
        ';
        $stmt->close();
        exit;
    }
    while ($row = $result->fetch_assoc()) {
        $fullName = trim((string)$row['full_name']);
        $parts = preg_split('/\s+/', $fullName);
        if (count($parts) >= 2) {
            $initials =
                strtoupper(substr($parts[0], 0, 1)) .
                strtoupper(substr($parts[count($parts) - 1], 0, 1));
        } else {
            $initials = strtoupper(substr($fullName, 0, 2));
        }
        $lastMessage = trim((string)($row['last_message'] ?? ''));
        if ($lastMessage === '') {
            $lastMessage = 'No messages yet';
        }
        $conversationId = (int)$row['id'];
        $unread         = (int)$row['unread'];
        ?>
        <div
            class="chat-user"
            data-conversation-id="<?= $conversationId ?>"
            data-search="<?= htmlspecialchars(
                strtolower($fullName . ' ' . $lastMessage),
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            onclick="openChatConversation(
                <?= $conversationId ?>,
                <?= htmlspecialchars(
                    json_encode($fullName),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            )"
        >
            <div class="chat-avatar">
                <?= htmlspecialchars($initials) ?>
            </div>
            <div class="chat-user-content">
                <div class="chat-user-name">
                    <?= htmlspecialchars($fullName) ?>
                </div>
                <div class="chat-user-preview">
                    <?= htmlspecialchars(
                        mb_strimwidth(
                            $lastMessage,
                            0,
                            45,
                            '…',
                            'UTF-8'
                        )
                    ) ?>
                </div>
            </div>
            <div class="chat-user-meta">
                <?php if (!empty($row['last_time'])): ?>
                    <div class="chat-user-time">
                        <?= htmlspecialchars(timeAgo($row['last_time'])) ?>
                    </div>
                <?php endif; ?>

                <?php if ($unread > 0): ?>
                    <span class="badge">
                        <?= $unread ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    $stmt->close();
} catch (Throwable $error) {
    http_response_code(500);
    echo '
        <div class="chat-list-error">
            Conversation error:
            ' . htmlspecialchars($error->getMessage()) . '
        </div>
    ';
}
?>
<div
    id="chatNoSearchResults"
    style="display:none;"
>
    No matching conversation.
</div>