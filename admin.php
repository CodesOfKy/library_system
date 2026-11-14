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

// ---- Add / delete filter options ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_option'])) {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '' && in_array($_POST['kind'], ['grade', 'course']) && in_array($_POST['level'], ['SHS', 'College'])) {
            $stmt = $conn->prepare("INSERT IGNORE INTO options (kind, level, name) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $_POST['kind'], $_POST['level'], $name);
            $stmt->execute();
        }
    } elseif (isset($_POST['delete_option'])) {
        $stmt = $conn->prepare("DELETE FROM options WHERE id = ?");
        $stmt->bind_param("i", $_POST['id']);
        $stmt->execute();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ---- Summary numbers ----
$totalStudents = (int)$conn->query("SELECT COUNT(*) AS c FROM students")->fetch_assoc()['c'];
$insideNow     = (int)$conn->query("SELECT COUNT(*) AS c FROM student_status WHERE status = 'in'")->fetch_assoc()['c'];
$visitsToday   = (int)$conn->query("SELECT COUNT(*) AS c FROM library_in WHERE DATE(login_time) = CURDATE()")->fetch_assoc()['c'];
$logoutsToday  = (int)$conn->query("SELECT COUNT(*) AS c FROM library_out WHERE DATE(logout_time) = CURDATE()")->fetch_assoc()['c'];

// ---- Filter options (used by the graph filters and the Manage Filters panel) ----
$options = $conn->query("SELECT * FROM options ORDER BY kind DESC, level DESC, id")->fetch_all(MYSQLI_ASSOC);
$names = ['grade' => [], 'course' => []];
foreach ($options as $o) {
    $names[$o['kind']][] = $o['name'];
}

// ---- Graph: visits per day (last 14 days), filtered by grade / course ----
$fGrade  = $_GET['grade']  ?? '';
$fCourse = $_GET['course'] ?? '';

$sql = "SELECT DATE(l.login_time) AS d, COUNT(*) AS c
        FROM library_in l JOIN students s ON s.student_id = l.student_id
        WHERE l.login_time >= CURDATE() - INTERVAL 13 DAY";
$types = '';
$params = [];
foreach (['grade' => $fGrade, 'course' => $fCourse] as $column => $value) {
    if ($value !== '') {
        $sql .= " AND s.$column = ?";
        $types .= 's';
        $params[] = $value;
    }
}
$stmt = $conn->prepare($sql . " GROUP BY d");
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$counts = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'c', 'd');

$labels = $data = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i day"));
    $labels[] = date('M d', strtotime($day));
    $data[]   = (int)($counts[$day] ?? 0);
}

// ---- Students table (status, last login, total visits) ----
$students = $conn->query("
    SELECT s.student_id, s.name, s.first_name, s.middle_name, s.last_name, s.rfid_uid,
           s.grade, s.course, s.section,
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
            <button id="pdfBtn">Download PDF</button>
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

        <!-- Graph -->
        <div class="panel">
            <h3>Library Visits (Last 14 Days)</h3>
            <form method="GET" class="filters">
                <input type="hidden" name="range" value="<?= $range ?>">
                <?php foreach (['grade' => [$fGrade, 'All Grades'], 'course' => [$fCourse, 'All Courses']] as $key => [$current, $allLabel]): ?>
                    <select name="<?= $key ?>" onchange="this.form.submit()">
                        <option value=""><?= $allLabel ?></option>
                        <?php foreach (array_unique($names[$key]) as $n): ?>
                            <option <?= $n === $current ? 'selected' : '' ?>><?= htmlspecialchars($n) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endforeach; ?>
            </form>
            <canvas id="visitsChart" height="90"></canvas>
        </div>

        <!-- Students table -->
        <div class="panel">
            <h3>Students</h3>
            <input type="text" id="search" placeholder="Search name, ID, class, or RFID...">
            <div class="scroll">
                <table id="studentsTable">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>RFID UID</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Total Visits</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$students): ?>
                        <tr><td colspan="8">No students found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($students as $s): ?>
                        <tr data-id="<?= htmlspecialchars($s['student_id']) ?>"
                            data-name="<?= htmlspecialchars($s['name']) ?>"
                            data-first="<?= htmlspecialchars($s['first_name']) ?>"
                            data-middle="<?= htmlspecialchars($s['middle_name']) ?>"
                            data-last="<?= htmlspecialchars($s['last_name']) ?>"
                            data-uid="<?= htmlspecialchars($s['rfid_uid']) ?>">
                            <td><?= htmlspecialchars($s['student_id']) ?></td>
                            <td><?= htmlspecialchars($s['name']) ?></td>
                            <td><?= htmlspecialchars(implode(' - ', array_filter([$s['grade'], $s['course'], $s['section']])) ?: '—') ?></td>
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
                <table id="activityTable">
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

        <!-- Manage filters (these also feed the register dropdowns) -->
        <div class="panel">
            <h3>Manage Filters</h3>

            <form method="POST" class="filters">
                <select name="kind">
                    <option value="grade">Grade / Year</option>
                    <option value="course">Course</option>
                </select>
                <select name="level">
                    <option>SHS</option>
                    <option>College</option>
                </select>
                <input type="text" name="name" maxlength="30" placeholder="e.g. ABM or Grade 10" required>
                <button type="submit" name="add_option">Add</button>
            </form>

            <div class="scroll">
                <table>
                    <thead><tr><th>Type</th><th>Level</th><th>Name</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($options as $o): ?>
                        <tr>
                            <td><?= $o['kind'] === 'grade' ? 'Grade / Year' : 'Course' ?></td>
                            <td><?= $o['level'] ?></td>
                            <td><?= htmlspecialchars($o['name']) ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Delete this filter?')">
                                    <input type="hidden" name="id" value="<?= $o['id'] ?>">
                                    <button type="submit" name="delete_option" class="delete">Delete</button>
                                </form>
                            </td>
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

            <label for="editId">Student Number</label>
            <input type="text" id="editId" readonly>

            <label for="editFirst">First Name</label>
            <input type="text" id="editFirst" maxlength="30" required>

            <label for="editMiddle">Middle Name (optional)</label>
            <input type="text" id="editMiddle" maxlength="30">

            <label for="editLast">Last Name</label>
            <input type="text" id="editLast" maxlength="30" required>

            <label for="editUid">RFID UID</label>
            <input type="text" id="editUid" maxlength="20" required>

            <div class="form-actions">
                <button type="submit">Save</button>
                <button type="button" id="editCancel" class="ghost">Cancel</button>
            </div>
        </form>
    </dialog>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <script>
    const $ = id => document.getElementById(id);

    // ---- Graph ----
    const chart = new Chart($('visitsChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{ label: 'Visits', data: <?= json_encode($data) ?> }]
        },
        options: { animation: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });

    // ---- PDF report (summary, graph, students, recent activity) ----
    $('pdfBtn').addEventListener('click', () => {
        const doc = new window.jspdf.jsPDF();
        const filters = <?= json_encode('Grade: ' . ($fGrade ?: 'All') . ' | Course: ' . ($fCourse ?: 'All')) ?>;

        doc.setFontSize(16);
        doc.text('Library Report', 14, 15);
        doc.setFontSize(10);
        doc.text('Generated: ' + new Date().toLocaleString(), 14, 21);

        // Summary
        doc.autoTable({
            startY: 26,
            head: [['Total Students', 'Currently Inside', 'Visitors Today', 'Logouts Today']],
            body: [[<?= "$totalStudents, $insideNow, $visitsToday, $logoutsToday" ?>]]
        });

        // Graph
        let y = doc.lastAutoTable.finalY + 8;
        doc.text('Visits, last 14 days (' + filters + ')', 14, y);
        const w = 180, h = w * chart.height / chart.width;
        doc.addImage(chart.toBase64Image(), 'PNG', 14, y + 3, w, h);

        // Students (respects the search box)
        y += h + 14;
        doc.text('Students', 14, y);
        doc.autoTable({
            startY: y + 3,
            head: [[...document.querySelectorAll('#studentsTable thead th')].slice(0, -1).map(th => th.textContent)],
            body: [...document.querySelectorAll('#studentsTable tbody tr')]
                .filter(r => r.style.display !== 'none')
                .map(r => [...r.cells].slice(0, -1).map(c => c.textContent.trim())),
            styles: { fontSize: 8 }
        });

        // Recent activity (respects the Today / Week / Month / All filter)
        y = doc.lastAutoTable.finalY + 10;
        doc.text('Recent Activity (<?= $range ?>)', 14, y);
        doc.autoTable({ startY: y + 3, html: '#activityTable', styles: { fontSize: 8 } });

        doc.save('library-report.pdf');
    });

    async function studentsApi(action, data) {
        try {
            const res = await fetch('students_api.php?action=' + action, {
                method: 'POST',
                body: new URLSearchParams(data)
            });
            if (res.status === 401) { location.href = 'login.php'; return null; }
            return await res.json();
        } catch (e) {
            // missing file, PHP error, or a reply that is not JSON
            return { success: false, message: 'Could not reach students_api.php. Check that the file exists.' };
        }
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
            $('editFirst').value = row.dataset.first;
            $('editMiddle').value = row.dataset.middle;
            $('editLast').value = row.dataset.last;
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
            first_name: $('editFirst').value,
            middle_name: $('editMiddle').value,
            last_name: $('editLast').value,
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