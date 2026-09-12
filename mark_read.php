<?php
require_once 'config.php';
requireLogin();
header("Content-Type: application/json");
$user_id=(int)$_SESSION['user_id'];
$conversation_id=(int)($_POST['conversation_id'] ?? 0);
if($conversation_id<=0){
echo json_encode([
"success"=>false
]);
exit;
}

/*
|--------------------------------------------------------------------------
| Mark Messages Read
|--------------------------------------------------------------------------
*/
$stmt=$conn->prepare("
UPDATE messages
SET
seen=1
WHERE
conversation_id=?
AND sender_id<>?
AND seen=0
");
$stmt->bind_param(
"ii",
$conversation_id,
$user_id
);
$stmt->execute();
echo json_encode([
"success"=>true
]);