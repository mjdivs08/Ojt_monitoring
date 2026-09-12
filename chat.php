<?php
session_start();

require_once 'config.php';
require_once 'sidebar.php';

requireLogin();

$userId = (int)($_SESSION['user_id'] ?? 0);
$role   = $_SESSION['role'] ?? '';

if (!in_array($role, ['student', 'coordinator'], true)) {
    redirect(BASE_URL . 'login.php');
}

$conversationId = 0;
$selectedName   = '';

/*
|--------------------------------------------------------------------------
| Student conversation
|--------------------------------------------------------------------------
*/

if ($role === 'student') {
    $student = getStudentByUserId($conn, $userId);

    if (!$student) {
        die('Student profile not found.');
    }

    $studentId     = (int)$student['id'];
    $coordinatorId = (int)$student['coordinator_id'];

    if ($coordinatorId <= 0) {
        die('No coordinator has been assigned to this student.');
    }

    $stmt = $conn->prepare("
        SELECT id
        FROM conversations
        WHERE student_id = ?
          AND coordinator_id = ?
        LIMIT 1
    ");

    $stmt->bind_param('ii', $studentId, $coordinatorId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $conversationId = (int)$result->fetch_assoc()['id'];
    } else {
        $insert = $conn->prepare("
            INSERT INTO conversations (
                student_id,
                coordinator_id
            )
            VALUES (?, ?)
        ");

        $insert->bind_param('ii', $studentId, $coordinatorId);
        $insert->execute();

        $conversationId = (int)$conn->insert_id;
        $insert->close();
    }

    $stmt->close();

    $nameStmt = $conn->prepare("
        SELECT full_name
        FROM users
        WHERE id = ?
          AND role = 'coordinator'
        LIMIT 1
    ");

    $nameStmt->bind_param('i', $coordinatorId);
    $nameStmt->execute();

    $nameResult = $nameStmt->get_result();

    if ($nameResult && $nameResult->num_rows > 0) {
        $selectedName = $nameResult->fetch_assoc()['full_name'];
    }

    $nameStmt->close();
}

/*
|--------------------------------------------------------------------------
| Coordinator conversation
|--------------------------------------------------------------------------
|
| A coordinator may open:
| chat.php?student=5
|
| The student parameter contains students.id.
|
*/

if ($role === 'coordinator' && isset($_GET['student'])) {
    $studentId = (int)$_GET['student'];

    $stmt = $conn->prepare("
        SELECT
            s.id,
            s.user_id,
            u.full_name
        FROM students s
        INNER JOIN users u
            ON u.id = s.user_id
        WHERE s.id = ?
          AND s.coordinator_id = ?
        LIMIT 1
    ");

    $stmt->bind_param('ii', $studentId, $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $studentRow  = $result->fetch_assoc();
        $selectedName = $studentRow['full_name'];

        $conversationStmt = $conn->prepare("
            SELECT id
            FROM conversations
            WHERE student_id = ?
              AND coordinator_id = ?
            LIMIT 1
        ");

        $conversationStmt->bind_param(
            'ii',
            $studentId,
            $userId
        );

        $conversationStmt->execute();

        $conversationResult = $conversationStmt->get_result();

        if (
            $conversationResult &&
            $conversationResult->num_rows > 0
        ) {
            $conversationId = (int)$conversationResult
                ->fetch_assoc()['id'];
        } else {
            $insert = $conn->prepare("
                INSERT INTO conversations (
                    student_id,
                    coordinator_id
                )
                VALUES (?, ?)
            ");

            $insert->bind_param(
                'ii',
                $studentId,
                $userId
            );

            $insert->execute();

            $conversationId = (int)$conn->insert_id;

            $insert->close();
        }

        $conversationStmt->close();
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>Messages</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>style.css?v=<?= time() ?>">
<body>
<div class="wrapper">

    <?php renderSidebar($role, 'messages', $conn); ?>

    <div class="main-content">

        <?php renderTopbar('Messages', $conn); ?>

        <div class="page-content">

            <div class="chat-container">

                <!-- Conversation list -->
                <aside class="chat-sidebar">

                    <div class="chat-sidebar-header">
                        <h3>Messages</h3>

                        <div class="chat-search">
                            <span class="chat-search-icon">&#128269;</span>

                            <input
                                type="text"
                                id="searchStudent"
                                placeholder="Search conversation..."
                                autocomplete="off"
                            >
                        </div>
                    </div>

                    <div
                        class="chat-users"
                        id="conversationList"
                    >
                        <div class="chat-no-conversations">
                            Loading conversations...
                        </div>
                    </div>

                </aside>

                <!-- Main chat -->
                <section class="chat-main">

                    <header class="chat-header">
                        <div class="chat-header-user">

                            <div>
                                <h3 id="chatName">
                                    <?= $selectedName !== ''
                                        ? htmlspecialchars($selectedName)
                                        : 'Select a conversation'
                                    ?>
                                </h3>

                                <small class="chat-status">
                                    Communication Module
                                </small>
                            </div>

                        </div>
                    </header>

                    <div
                        class="chat-body"
                        id="chatBody"
                    >
                        <div class="chat-empty-state">
                            <div class="chat-empty-icon">💬</div>

                            <strong>
                                <?= $conversationId > 0
                                    ? 'Loading messages...'
                                    : 'Select a conversation'
                                ?>
                            </strong>

                            <span>
                                Messages between students and their
                                assigned coordinator appear here.
                            </span>
                        </div>
                    </div>

                    <input
                        type="hidden"
                        id="conversationId"
                        value="<?= $conversationId ?>"
                    >
                    <div
                        id="chatAttachmentPreview"
                        style="display:none;"
                    ></div>
                    <div class="chat-input">

                        <input
                            type="file"
                            id="attachment"
                            accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx"
                            hidden
                        >
                        <button
                            type="button"
                            id="chatAttachmentButton"
                            class="chat-attach-btn"
                            title="Attach file"
                            onclick="document.getElementById('attachment').click()"
                            <?= $conversationId <= 0 ? 'disabled' : '' ?>
                        >
                            📎
                        </button>

                        <textarea
                            id="message"
                            name="message"
                            rows="1"
                            maxlength="5000"
                            placeholder="Type a message..."
                            <?= $conversationId <= 0 ? 'disabled' : '' ?>
                        ></textarea>

                        <button
                            type="button"
                            id="chatSendButton"
                            class="chat-send-btn"
                            onclick="sendChatMessage()"
                            <?= $conversationId <= 0 ? 'disabled' : '' ?>
                        >
                            Send
                        </button>

                    </div>
                </section>
            </div>
        </div>
    </div>
</div>

<script>
window.OJT_BASE_URL = <?= json_encode(BASE_URL) ?>;
window.initialConversationId = <?= (int)$conversationId ?>;
</script>

<script src="<?= BASE_URL ?>main.js?v=<?= time() ?>"></script>

</body>
</html>