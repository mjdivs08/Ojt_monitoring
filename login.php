<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

if (isLoggedIn()) {
    if ($_SESSION['role'] == 'admin') {
        redirect(BASE_URL . 'admin_dashboard.php');
    } elseif ($_SESSION['role'] == 'coordinator') {
        redirect(BASE_URL . 'coordinator_dashboard.php');
    } elseif ($_SESSION['role'] == 'student') {
        redirect(BASE_URL . 'student_dashboard.php');
    }
}

$error = '';

// Login only — no self-registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$username || !$password) {
        $error = 'Please enter your username and password.';
    } else {
        $user = loginUser($conn, $username, $password);
        if ($user) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];
            redirect(BASE_URL . '' . $user['role'] . '_dashboard.php');
        } else {
            $error = 'Invalid username or password. Please contact your administrator.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>OJT Monitoring System – Sign In</title>
<link rel="stylesheet" href="style.css">
<style>
.login-body{
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    padding:30px;
    background:
    linear-gradient(rgba(0,0,0,.45),rgba(0,90,60,.35)),
    url("gb.jpg")
    center center/cover no-repeat;
}
.login-wrap { width: 100%; max-width: 420px; }
.login-card {
    background: #fff;
    border-radius: 16px;
    padding: 40px;
    box-shadow: 0 25px 60px rgba(0,0,0,.35);
}
.login-logo { text-align: center; margin-bottom: 32px; }
.login-logo .icon {
    width: 64px; height: 64px;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 14px;
    box-shadow: 0 8px 20px rgba(37,99,235,.35);
}
.login-logo .icon svg { width: 32px; height: 32px; fill: #fff; }
.login-logo h1 { font-size: 28px; font-weight: 800; color: var(--text-main); }
.login-logo h2 { font-size: 20px; font-weight: 800; color: var(--text-main); }
.login-logo p  { font-size: 13px; color: var(--text-muted); margin-top: 4px; }
.login-divider {
    display: flex; align-items: center; gap: 10px;
    margin: 20px 0;
    color: var(--text-muted); font-size: 12px;
}
.login-divider::before, .login-divider::after {
    content: ''; flex: 1; height: 1px; background: var(--border);
}
.info-box {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: var(--radius);
    padding: 14px 16px;
    margin-top: 20px;
}
.info-box p {
    font-size: 12px; font-weight: 700;
    color: #1e40af; margin-bottom: 8px;
    display: flex; align-items: center; gap: 6px;
}
.info-box ul { list-style: none; padding: 0; }
.info-box ul li {
    font-size: 12px; color: #3730a3;
    padding: 4px 0;
    border-bottom: 1px solid #dbeafe;
    cursor: pointer;
    transition: color .15s;
    display: flex; align-items: center; justify-content: space-between;
}
.info-box ul li:last-child { border: none; }
.info-box ul li:hover { color: var(--primary); }
.info-box ul li .quick-fill {
    font-size: 10px; background: var(--primary-light);
    color: var(--primary); padding: 2px 7px;
    border-radius: 4px; font-weight: 700;
}
.pw-wrap { position: relative; }
.pw-wrap input { padding-right: 42px; }
.pw-toggle {
    position: absolute; right: 12px; top: 50%;
    transform: translateY(-50%);
    background: none; border: none; cursor: pointer;
    color: var(--text-muted); padding: 0;
}
.pw-toggle svg { width: 18px; height: 18px; }
</style>
</head>
<body class="login-body">

<div class="login-wrap">
    <div class="login-card">

        <div class="login-logo">
            <div class="school-logo-wrap">
                <img
                    src="logo.jpg"
                    alt="City College of Angeles Logo"
                    class="school-logo"
                >
            </div>

            <h1>City College of Angeles</h1>
            <h2>OJT Monitoring System</h2>
            <p>Sign in with your assigned account</p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <strong>Login Failed:</strong>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <form method="POST" id="loginForm">
            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter your username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    required
                    autocomplete="username"
                >
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="pw-wrap">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >
                    <button
                        type="button"
                        class="pw-toggle"
                        onclick="togglePw()"
                        aria-label="Show or hide password"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>
            <button
                type="submit"
                name="login"
                class="btn btn-primary btn-block login-submit"  > Sign In </button>
        </form>
    </div>
</div>
<script src="main.js"></script>
<script>
function quickFill(user, pass) {
    document.getElementById('username').value = user;
    document.getElementById('password').value = pass;
    document.getElementById('password').type  = 'text';
    setTimeout(() => { document.getElementById('password').type = 'password'; }, 800);
}
function togglePw() {
    const inp = document.getElementById('password');
    inp.type = inp.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
