<?php
/**
 * Authenticate a user.  Returns the user row on success, false on failure.
 */
function loginUser($conn, $username, $password) {
    $u = $conn->real_escape_string(trim($username));
    $r = $conn->query("SELECT * FROM users WHERE username='$u' LIMIT 1");
    if ($r && $r->num_rows === 1) {
        $user = $r->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            return $user;
        }
    }
    return false;
}
