<?php
require_once 'config.php';
requireLogin();
$user_id=(int)$_SESSION['user_id'];
$role=$_SESSION['role'];
$count=0;
if($role=="student")
{
$student=getStudentByUserId($conn,$user_id);
$stmt=$conn->prepare("
SELECT COUNT(*)
total
FROM messages m
INNER JOIN conversations c
ON c.id=m.conversation_id
WHERE
c.student_id=?
AND sender_id<>?
AND seen=0
");

$stmt->bind_param(
"ii",
$student['id'],
$user_id
);
}
else
{
$stmt=$conn->prepare("
SELECT COUNT(*)
total
FROM messages m
INNER JOIN conversations c
ON c.id=m.conversation_id
WHERE
c.coordinator_id=?
AND sender_id<>?
AND seen=0
");
$stmt->bind_param(
"ii",
$user_id,
$user_id
);
}
$stmt->execute();
$result=$stmt->get_result();
$row=$result->fetch_assoc();
echo $row['total'];