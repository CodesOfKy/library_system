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
            <a href="borrow.php" class="btn-link">Borrow &amp; Return</a>
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
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$students): ?>
                        <tr><td colspan="7">No students found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($students as $s): ?>
                        <tr data-id="<?= htmlspecialchars($s['student_id']) ?>"
                            data-name="<?= htmlspecialchars($s['name']) ?>"
                            data-uid="<?= htmlspecialchars($s['rfid_uid']) ?>">
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
                            <td class="actions">
                                <button type="button" class="edit">Edit</button>
                                <button type="button" class="delete">Delete</button>
                            </td>
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
                        <tr><td colspan="4">No activity found for this filter.</td></tr>
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

    <!-- Edit student pop-up -->
    <dialog id="editDialog">
        <form id="editForm">
            <h3>Edit Student</h3>
            <p id="editMsg" class="form-error"></p>

            <label for="editId">Student ID</label>
            <input type="text" id="editId" readonly>

            <label for="editName">Name</label>
            <input type="text" id="editName" maxlength="100" required>

            <label for="editUid">RFID UID</label>
            <input type="text" id="editUid" maxlength="50" required>

            <div class="form-actions">
                <button type="submit">Save</button>
                <button type="button" id="editCancel" class="ghost">Cancel</button>
            </div>
        </form>
    </dialog>

    <script>
    const $ = id => document.getElementById(id);

    async function studentsApi(action, data) {
        const res = await fetch('students_api.php?action=' + action, {
            method: 'POST',
            body: new URLSearchParams(data)
        });
        if (res.status === 401) { location.href = 'login.php'; return null; }
        return res.json();
    }

    // Live search on the students table (ignores the Actions column)
    $('search').addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#studentsTable tbody tr').forEach(row => {
            const text = Array.from(row.cells).slice(0, -1).map(c => c.textContent).join(' ').toLowerCase();
            row.style.display = text.includes(q) ? '' : 'none';
        });
    });

    // Edit: open the pop-up filled with the row's data
    document.querySelectorAll('#studentsTable .edit').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = btn.closest('tr');
            $('editId').value = row.dataset.id;
            $('editName').value = row.dataset.name;
            $('editUid').value = row.dataset.uid;
            $('editMsg').textContent = '';
            $('editDialog').showModal();
        });
    });

    $('editCancel').addEventListener('click', () => $('editDialog').close());

    // Edit: save
    $('editForm').addEventListener('submit', async e => {
        e.preventDefault();
        const r = await studentsApi('update', {
            student_id: $('editId').value,
            name: $('editName').value,
            rfid_uid: $('editUid').value
        });
        if (!r) return;
        if (r.success) {
            location.reload();
        } else {
            $('editMsg').textContent = r.message;
        }
    });

    // Delete (with confirmation)
    document.querySelectorAll('#studentsTable .delete').forEach(btn => {
        btn.addEventListener('click', async () => {
            const row = btn.closest('tr');
            const ok = confirm(
                'Delete ' + row.dataset.name + ' (' + row.dataset.id + ')?\n\n' +
                'Their login and logout history will also be deleted. This cannot be undone.'
            );
            if (!ok) return;

            const r = await studentsApi('delete', { student_id: row.dataset.id });
            if (!r) return;
            if (r.success) {
                location.reload();
            } else {
                alert(r.message);
            }
        });
    });

    // Log out through auth_api.php
    $('logoutBtn').addEventListener('click', async () => {
        const res = await fetch('auth_api.php?action=logout');
        const data = await res.json();
        window.location.href = data.redirect;
    });
    </script>
</body>
</html>