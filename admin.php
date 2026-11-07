<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli("localhost", "root", "", "arduino_db");
if ($conn->connect_error) {
    die("Database connection failed");
}

// ---- Summary numbers ----
$totalStudents = (int)$conn->query("SELECT COUNT(*) AS c FROM students")->fetch_assoc()['c'];
$insideNow     = (int)$conn->query("SELECT COUNT(*) AS c FROM student_status WHERE status = 'in'")->fetch_assoc()['c'];
$visitsToday   = (int)$conn->query("SELECT COUNT(*) AS c FROM library_in WHERE DATE(login_time) = CURDATE()")->fetch_assoc()['c'];
$logoutsToday  = (int)$conn->query("SELECT COUNT(*) AS c FROM library_out WHERE DATE(logout_time) = CURDATE()")->fetch_assoc()['c'];

// ---- Students table (status, last login, total visits) ----
$students = $conn->query("
    SELECT s.student_id, s.name, s.rfid_uid,
           COALESCE(st.status, 'out') AS status,
           (SELECT DATE_FORMAT(MAX(login_time), '%b %d, %Y %h:%i %p')
              FROM library_in WHERE student_id = s.student_id) AS last_login,
           (SELECT COUNT(*) FROM library_in WHERE student_id = s.student_id) AS total_visits
    FROM students s
    LEFT JOIN student_status st ON st.student_id = s.student_id
    ORDER BY s.name
")->fetch_all(MYSQLI_ASSOC);

// ---- Recent activity (filtered) ----
$range = $_GET['range'] ?? 'all';
if (!in_array($range, ['today', 'week', 'month', 'all'])) {
    $range = 'all';
}

$where = "";
if ($range === 'today') {
    $where = "WHERE DATE(a.ts) = CURDATE()";
} elseif ($range === 'week') {
    $where = "WHERE YEARWEEK(a.ts, 1) = YEARWEEK(CURDATE(), 1)";   // week starts Monday
} elseif ($range === 'month') {
    $where = "WHERE YEAR(a.ts) = YEAR(CURDATE()) AND MONTH(a.ts) = MONTH(CURDATE())";
}

$recent = $conn->query("
    SELECT * FROM (
        (SELECT s.name, s.student_id, 'in' AS type,
                DATE_FORMAT(l.login_time, '%b %d, %Y %h:%i %p') AS time_text, l.login_time AS ts
           FROM library_in l JOIN students s ON s.student_id = l.student_id)
        UNION ALL
        (SELECT s.name, s.student_id, 'out' AS type,
                DATE_FORMAT(l.logout_time, '%b %d, %Y %h:%i %p') AS time_text, l.logout_time AS ts
           FROM library_out l JOIN students s ON s.student_id = l.student_id)
    ) AS a
    $where
    ORDER BY a.ts DESC
    LIMIT 100
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <header>
        <h2>Library Admin Dashboard</h2>
        <div>
            <span><?= htmlspecialchars($_SESSION['admin_email']) ?></span>
            <button id="logoutBtn">Log Out</button>
        </div>
    </header>

    <main>
        <!-- Summary cards -->
        <div class="cards">
            <div class="card"><p>Total Students</p><h1><?= $totalStudents ?></h1></div>
            <div class="card"><p>Currently Inside</p><h1><?= $insideNow ?></h1></div>
            <div class="card"><p>Visitors Today</p><h1><?= $visitsToday ?></h1></div>
            <div class="card"><p>Logouts Today</p><h1><?= $logoutsToday ?></h1></div>
        </div>

        <!-- Students table -->
        <div class="panel">
            <h3>Students</h3>
            <input type="text" id="search" placeholder="Search name, ID, or RFID...">
            <div class="scroll">
                <table id="studentsTable">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>RFID UID</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Total Visits</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$students): ?>
                        <tr><td colspan="6">No students found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($students as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['student_id']) ?></td>
                            <td><?= htmlspecialchars($s['name']) ?></td>
                            <td><?= htmlspecialchars($s['rfid_uid']) ?></td>
                            <td>
                                <span class="badge <?= $s['status'] === 'in' ? 'in' : 'out' ?>">
                                    <?= $s['status'] === 'in' ? 'Inside' : 'Outside' ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($s['last_login'] ?? '—') ?></td>
                            <td><?= (int)$s['total_visits'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent activity -->
        <div class="panel">
            <h3>Recent Activity</h3>

            <div class="filters">
                <a href="?range=today" class="<?= $range === 'today' ? 'active' : '' ?>">Today</a>
                <a href="?range=week"  class="<?= $range === 'week'  ? 'active' : '' ?>">This Week</a>
                <a href="?range=month" class="<?= $range === 'month' ? 'active' : '' ?>">This Month</a>
                <a href="?range=all"   class="<?= $range === 'all'   ? 'active' : '' ?>">All</a>
            </div>

            <div class="scroll">
                <table>
                    <thead>
                        <tr><th>Name</th><th>Student ID</th><th>Action</th><th>Date &amp; Time</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$recent): ?>
                        <tr><td colspan="4">No activity found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['name']) ?></td>
                            <td><?= htmlspecialchars($r['student_id']) ?></td>
                            <td>
                                <span class="badge <?= $r['type'] ?>">
                                    <?= $r['type'] === 'in' ? 'Logged In' : 'Logged Out' ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($r['time_text']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
    // Live search on the students table
    document.getElementById('search').addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#studentsTable tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });

    // Log out through auth_api.php
    document.getElementById('logoutBtn').addEventListener('click', async () => {
        const res = await fetch('auth_api.php?action=logout');
        const data = await res.json();
        window.location.href = data.redirect;
    });
    </script>
</body>
</html>