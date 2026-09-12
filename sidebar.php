<?php
function renderSidebar($role, $active = '', $conn = null) {
    $unread = 0;
    $chatUnread=0;
    if($conn && isset($_SESSION['user_id']))
    { $uid=(int)$_SESSION['user_id'];
    $role=$_SESSION['role'];
    if($role=="student")
    { $s=getStudentByUserId($conn,$uid);
    $r=$conn->query("
    SELECT COUNT(*) cnt FROM messages m INNER JOIN conversations c ON c.id=m.conversation_id
    WHERE c.student_id=".$s['id']." AND sender_id<>".$uid." AND seen=0
    ");

    }
    else
    {
    $r=$conn->query("
    SELECT COUNT(*) cnt FROM messages m INNER JOIN conversations c ON c.id=m.conversation_id WHERE
    c.coordinator_id=".$uid." AND sender_id<>".$uid." AND seen=0
    ");
    }
    if($r)
    $chatUnread=$r->fetch_assoc()['cnt'];
    }
    if ($conn && isset($_SESSION['user_id'])) {
        $uid = (int)$_SESSION['user_id'];
        $r = $conn->query("SELECT COUNT(*) as cnt FROM notifications WHERE user_id=$uid AND is_read=0");
        if ($r) $unread = $r->fetch_assoc()['cnt'];
    }
    $name = $_SESSION['full_name'] ?? 'User';
    $initials = strtoupper(substr($name, 0, 1));
    if (strpos($name, ' ') !== false) {
        $parts = explode(' ', $name);
        $initials = strtoupper(substr($parts[0],0,1) . substr(end($parts),0,1));
    }
    $baseUrl = BASE_URL;
?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <img
            src="<?= BASE_URL ?>cca.png?v=<?= time() ?>"
            alt="City College of Angeles"
            class="sidebar-school-logo"
        >
        <h2>OJT Monitoring</h2>
        <p>A.Y. <?= htmlspecialchars(getSetting($conn, 'academic_year') ?? '2026-2027') ?></p>
    </div>
    <div class="sidebar-user">
        <div class="avatar"><?= $initials ?></div>
        <div class="uinfo">
            <div class="name"><?= htmlspecialchars($name) ?></div>
            <div class="role"><?= ucfirst($role) ?></div>
        </div>
        <div
            class="sidebar-overlay"
            id="sidebarOverlay"
        ></div>
    </div>
    <nav class="sidebar-nav">
        <?php if ($role === 'admin'): ?>
        <div class="nav-label">Main</div>
        <a href="admin_dashboard.php" class="nav-item <?= $active==='dashboard'?'active':'' ?>">
            <?= svgIcon('grid') ?> Dashboard
        </a>
        <a href="admin_users.php" class="nav-item <?= $active==='users'?'active':'' ?>">
            <?= svgIcon('users') ?> Users
        </a>
        
        <?php elseif ($role === 'coordinator'): ?>
        <div class="nav-label">Main</div>
        <a href="coordinator_dashboard.php" class="nav-item <?= $active==='dashboard'?'active':'' ?>">
            <?= svgIcon('grid') ?> Dashboard
        </a>
        <a href="coordinator_students.php" class="nav-item <?= $active==='students'?'active':'' ?>">
            <?= svgIcon('users') ?> My Students
        </a>
        <a href="coordinator_docsreq.php" class="nav-item <?= $active==='doc_requirements'?'active':'' ?>">
            <?= svgIcon('file') ?> Document Requirements
        </a>
        <a href="weekly_logs.php" class="nav-item <?= $active==='weekly_logs'?'active':'' ?>">
            <?= svgIcon('clock') ?> Weekly Logs
        </a>
        <a href="coordinator_documents.php" class="nav-item <?= $active==='documents'?'active':'' ?>">
            <?= svgIcon('file') ?> Documents
        </a>
        <a href="coordinator_companies.php" class="nav-item <?= $active==='companies'?'active':'' ?>">
            <?= svgIcon('companies_moa') ?> Companies & MOA
        </a>
        <a href="chat.php" class="nav-item <?= $active==='messages'?'active':'' ?>">
             <?= svgIcon('message') ?> Messages
            <?php if($chatUnread>0): ?>
         <span class="badge"><?= $chatUnread ?></span>
            <?php endif; ?>
        </a>
        <a href="coordinator_notifications.php" class="nav-item <?= $active==='notifications'?'active':'' ?>">
            <?= svgIcon('bell') ?> Notifications
            <?php if ($unread > 0): ?><span class="badge"><?= $unread ?></span><?php endif; ?>
        </a>
        <a href="coordinator_settings.php" class="nav-item <?= $active==='settings'?'active':'' ?>">
            <?= svgIcon('settings') ?> Settings
        </a>


        <?php elseif ($role === 'student'): ?>
        <div class="nav-label">Main</div>
        <a href="student_dashboard.php" class="nav-item <?= $active==='dashboard'?'active':'' ?>">
            <?= svgIcon('grid') ?> Dashboard
        </a>
        <a href="weekly_log.php" class="nav-item <?= $active==='weekly_log'?'active':'' ?>">
            <?= svgIcon('clock') ?> Weekly Hours
        </a>
        <a href="student_documents.php" class="nav-item <?= $active==='documents'?'active':'' ?>">
            <?= svgIcon('file') ?> My Documents
        </a>
        <a href="chat.php" class="nav-item <?= $active==='messages'?'active':'' ?>">
             <?= svgIcon('message') ?> Messages
            <?php if($chatUnread>0): ?>
         <span class="badge"><?= $chatUnread ?></span>
            <?php endif; ?>
        </a>
        <a href="student_notifications.php" class="nav-item <?= $active==='notifications'?'active':'' ?>">
            <?= svgIcon('bell') ?> Notifications
            <?php if ($unread > 0): ?><span class="badge"><?= $unread ?></span><?php endif; ?>
        </a>
        <a href="student_profile.php" class="nav-item <?= $active==='profile'?'active':'' ?>">
            <?= svgIcon('person') ?> My Profile
        </a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="logout.php" class="nav-item">
            <?= svgIcon('logout') ?> Logout
        </a>
    </div>
</div>
<?php
}

function svgIcon($name) {
    $icons = [
        'grid' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>',
        'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'person' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'file' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
        'clock' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        'bell' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93A10 10 0 1 0 4.93 19.07 10 10 0 0 0 19.07 4.93z"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
        'warning' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        'check' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
        'eye' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
        'plus' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
        'edit' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
        'trash' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>',
        'calendar' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        'chart' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
        'upload' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>',
        'message' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',       
        'companies_moa' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v16"/><path d="M9 7h2M9 11h2M9 15h2"/><path d="M15 9h4v12"/><path d="M17 13h2M17 17h2"/></svg>',
        ];
    return $icons[$name] ?? '';
}

function renderTopbar($title, $conn = null) {
    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $unread = 0;
    if ($conn && $uid) {
        $r = $conn->query("SELECT COUNT(*) as cnt FROM notifications WHERE user_id=$uid AND is_read=0");
        if ($r) $unread = $r->fetch_assoc()['cnt'];
    }
    ?>
    <div class="topbar">
        <h1><?= htmlspecialchars($title) ?></h1>
        <button
            type="button"
            class="mobile-menu-btn"
            id="mobileMenuBtn"
            aria-label="Open navigation menu"
        >
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
            >
                <path d="M4 6h16"/>
                <path d="M4 12h16"/>
                <path d="M4 18h16"/>
            </svg>
        </button>
        <div class="topbar-right">
            <button class="notif-btn" onclick="toggleNotifPanel()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <?php if ($unread > 0): ?><span class="dot"></span><?php endif; ?>
            </button>
        </div>
    </div>
    <?php
}

function renderNotifPanel($conn) {
    $uid = (int)$_SESSION['user_id'];
    $result = $conn->query("SELECT * FROM notifications WHERE user_id=$uid ORDER BY created_at DESC LIMIT 20");
    ?>
    <div class="notif-panel" id="notifPanel">
        <div class="notif-panel-header">
            <h4>Notifications</h4>
            <a href="?action=mark_notifs_read" style="font-size:12px;color:var(--primary);">Mark all read</a>
        </div>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($n = $result->fetch_assoc()): ?>
            <div class="notif-item <?= !$n['is_read'] ? 'unread' : '' ?>">
                <div class="notif-dot <?= $n['type'] ?>"></div>
                <div class="notif-text">
                    <div class="title"><?= htmlspecialchars($n['title']) ?></div>
                    <div class="msg"><?= htmlspecialchars($n['message']) ?></div>
                    <div class="time"><?= timeAgo($n['created_at']) ?></div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:13px;">No notifications</div>
        <?php endif; ?>
    </div>
    <?php
}
?>
