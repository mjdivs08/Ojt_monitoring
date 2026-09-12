# OJT Monitoring System
### PHP + MySQL + HTML/CSS/JS

---

## 📦 Features
- **3 Roles:** Admin, Coordinator, Student
- **Student:** Submit weekly hours + DTR photo, upload pre-deployment documents, view deficit/progress, notifications
- **Coordinator:** Review & approve/reject weekly logs + documents, set deployment dates & deadlines, performance overview, send notifications
- **Admin:** Full user management, document requirements setup, system settings, bulk notifications

---

## ⚙️ Installation

### Requirements
- PHP 7.4+ (PHP 8.x recommended)
- MySQL 5.7+ or MariaDB 10.3+
- Apache / Nginx with mod_rewrite
- XAMPP / WAMP / LAMP stack

### Steps

1. **Copy files** to your web server root:
   ```
   C:/xampp/htdocs/ojt_system/    (XAMPP on Windows)
   /var/www/html/ojt_system/      (Linux Apache)
   ```

2. **Import the database:**
   - Open phpMyAdmin or MySQL CLI
   - Run: `source /path/to/ojt_system/database.sql`
   - Or import `database.sql` via phpMyAdmin → Import

3. **Configure database credentials** in `includes/config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');       // your MySQL username
   define('DB_PASS', '');           // your MySQL password
   define('DB_NAME', 'ojt_monitoring');
   ```

4. **Set upload folder permissions** (Linux):
   ```bash
   chmod -R 775 uploads/
   chown -R www-data:www-data uploads/
   ```

5. **Access the system:**
   ```
   http://localhost/ojt_system/
   ```

---

## 🔐 Demo Accounts
All accounts use password: **password123**

| Username      | Role        | Name                   |
|---------------|-------------|------------------------|
| admin         | Admin       | System Administrator   |
| coordinator1  | Coordinator | Dr. Maria Santos       |
| coordinator2  | Coordinator | Prof. Jose Reyes       |
| student1      | Student     | Juan Dela Cruz         |
| student2      | Student     | Maria Reyes            |
| student3      | Student     | Pedro Santos           |

---

## 📁 File Structure
```
ojt_system/
├── index.php               # Login + Register page
├── logout.php              # Session destroy
├── database.sql            # Full DB schema + seed data
├── includes/
│   ├── config.php          # DB connection, helpers
│   ├── auth.php            # Login/register functions
│   └── sidebar.php         # Sidebar, topbar, SVG icons
├── assets/
│   ├── css/style.css       # All styles
│   └── js/main.js          # All JS (modals, uploads, tabs)
├── student/
│   ├── dashboard.php       # Progress, warnings, recent logs
│   ├── weekly_log.php      # Submit weekly hours + DTR photo
│   ├── documents.php       # Upload pre-deployment docs
│   ├── notifications.php   # View all notifications
│   └── profile.php         # Update profile, change password
├── coordinator/
│   ├── dashboard.php       # Overview, recent submissions
│   ├── students.php        # Students list + detail + edit
│   ├── weekly_logs.php     # Approve/reject logs
│   ├── documents.php       # Approve/reject documents
│   └── notifications.php   # Send/view notifications
├── admin/
│   ├── dashboard.php       # System-wide stats
│   ├── users.php           # Add/edit/delete users
│   ├── students.php        # All students overview
│   ├── documents.php       # Manage doc requirements
│   └── settings.php        # System settings, bulk notif
└── uploads/
    ├── dtr/                # DTR photo uploads
    └── documents/          # Student document uploads
```

---

## 📝 How It Works

### Weekly Hours & Deficit Detection
- Student submits rendered hours + DTR photo each week
- System compares rendered hours vs weekly target (default 40 hrs)
- If deficit exists → automatic **⚠ Warning Notification** sent to student
- Student dashboard shows cumulative deficit in red

### Pre-Deployment Documents
- Admin configures required documents
- Coordinator sets a **submission deadline** per student
- Students upload files before deadline
- Coordinator approves/rejects each document
- Students see real-time checklist status

### Deployment Dates
- Coordinator sets deployment date and document deadline per student
- Banner warning shown when deadline is approaching
- Student status: pending → deployed → completed
