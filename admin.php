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
require 'audit.php';

// ---- Settings ----
const HASH_KEY = 'change-this-secret';                                       // secret used to hash RFID UIDs for display
$docs = ['student_id' => 'Student ID', 'reg_form' => 'Registration Form'];    // papers needed to confirm a re-validation

function hide_uid($uid) {
    return substr(hash_hmac('sha256', $uid, HASH_KEY), 0, 12);
}

// First value of a query, or '?' when there is no row
function one($conn, $sql) {
    return ($conn->query($sql)->fetch_row() ?? ['?'])[0];
}

// ---- Form posts: filters + re-validation ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_option'])) {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '' && in_array($_POST['kind'], ['grade', 'course']) && in_array($_POST['level'], ['SHS', 'College'])) {
            $stmt = $conn->prepare("INSERT IGNORE INTO options (kind, level, name) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $_POST['kind'], $_POST['level'], $name);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                log_action($conn, 'Add filter', "{$_POST['kind']}: $name ({$_POST['level']})");
            }
        }

    } elseif (isset($_POST['delete_option'])) {
        $id    = (int)$_POST['id'];
        $label = one($conn, "SELECT CONCAT(kind, ': ', name, ' (', level, ')') FROM options WHERE id = $id");
        $conn->query("DELETE FROM options WHERE id = $id");
        log_action($conn, 'Delete filter', $label);

    } elseif (isset($_POST['request_revalidation'])) {
        $id     = trim($_POST['student_id'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        if (mb_strlen($reason) >= 5) {
            // Only inserts if the student exists and has no pending request yet
            $stmt = $conn->prepare("INSERT INTO revalidations (student_id, reason)
                                    SELECT student_id, ? FROM students
                                    WHERE student_id = ?
                                      AND NOT EXISTS (SELECT 1 FROM revalidations WHERE student_id = ? AND status = 'pending')");
            $stmt->bind_param("sss", $reason, $id, $id);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                log_action($conn, 'Re-validation', "$id requested: $reason");
            }
        }

    } elseif (isset($_POST['confirm_revalidation'])) {
        $id      = (int)$_POST['id'];
        $checked = array_intersect(array_keys($docs), (array)($_POST['docs'] ?? []));
        if (count($checked) === count($docs)) {          // every paper must be checked
            $list = implode(',', $checked);
            $stmt = $conn->prepare("UPDATE revalidations SET status = 'confirmed', docs_checked = ?, confirmed_at = NOW()
                                    WHERE id = ? AND status = 'pending'");
            $stmt->bind_param("si", $list, $id);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                log_action($conn, 'Re-validation', one($conn, "SELECT student_id FROM revalidations WHERE id = $id") . ' confirmed');
            }
        }

    } elseif (isset($_POST['cancel_revalidation'])) {
        $id = (int)$_POST['id'];
        $conn->query("UPDATE revalidations SET status = 'cancelled' WHERE id = $id AND status = 'pending'");
        if ($conn->affected_rows > 0) {
            log_action($conn, 'Re-validation', one($conn, "SELECT student_id FROM revalidations WHERE id = $id") . ' cancelled');
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ---- Summary numbers ----
$totalStudents = (int)$conn->query("SELECT COUNT(*) AS c FROM students")->fetch_assoc()['c'];
$insideNow     = (int)$conn->query("SELECT COUNT(*) AS c FROM student_status WHERE status = 'in'")->fetch_assoc()['c'];
$visitsToday   = (int)$conn->query("SELECT COUNT(*) AS c FROM library_in WHERE DATE(login_time) = CURDATE()")->fetch_assoc()['c'];
$logoutsToday  = (int)$conn->query("SELECT COUNT(*) AS c FROM library_out WHERE DATE(logout_time) = CURDATE()")->fetch_assoc()['c'];

// ---- Filter options (graph filters and Manage Filters panel) ----
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

// ---- Students table ----
$students = $conn->query("
    SELECT s.student_id, s.name, s.first_name, s.middle_name, s.last_name, s.rfid_uid,
           s.grade, s.course, s.section,
           COALESCE(st.status, 'out') AS status,
           (SELECT DATE_FORMAT(MAX(login_time), '%b %d, %Y %h:%i %p')
              FROM library_in WHERE student_id = s.student_id) AS last_login,
           (SELECT COUNT(*) FROM library_in WHERE student_id = s.student_id) AS total_visits,
           (SELECT COUNT(*) FROM revalidations
              WHERE student_id = s.student_id AND status = 'pending') AS pending
    FROM students s
    LEFT JOIN student_status st ON st.student_id = s.student_id
    ORDER BY s.name
")->fetch_all(MYSQLI_ASSOC);

// ---- Waiting for re-validation ----
$pending = $conn->query("
    SELECT r.id, r.reason, s.student_id, s.name,
           DATE_FORMAT(r.requested_at, '%b %d, %Y %h:%i %p') AS requested
    FROM revalidations r JOIN students s ON s.student_id = r.student_id
    WHERE r.status = 'pending'
    ORDER BY r.requested_at
")->fetch_all(MYSQLI_ASSOC);

// ---- Recent activity: one table for log ins, one for log outs ----
$range = $_GET['range'] ?? 'all';
if (!in_array($range, ['today', 'week', 'month', 'all'])) {
    $range = 'all';
}

$rangeSql = [
    'today' => "DATE({col}) = CURDATE()",
    'week'  => "YEARWEEK({col}, 1) = YEARWEEK(CURDATE(), 1)",   // week starts Monday
    'month' => "YEAR({col}) = YEAR(CURDATE()) AND MONTH({col}) = MONTH(CURDATE())",
    'all'   => "1",
];

function activity($conn, $table, $col, $condition) {
    return $conn->query("
        SELECT s.name, s.student_id, DATE_FORMAT(l.$col, '%b %d, %Y %h:%i %p') AS time_text
        FROM $table l JOIN students s ON s.student_id = l.student_id
        WHERE " . str_replace('{col}', "l.$col", $condition) . "
        ORDER BY l.$col DESC
        LIMIT 100
    ")->fetch_all(MYSQLI_ASSOC);
}

$loggedIn  = activity($conn, 'library_in',  'login_time',  $rangeSql[$range]);
$loggedOut = activity($conn, 'library_out', 'logout_time', $rangeSql[$range]);

// ---- Audit log (edits and deletes) ----
$auditLog = $conn->query("
    SELECT admin_email, action, details, DATE_FORMAT(created_at, '%b %d, %Y %h:%i:%s %p') AS logged_at
    FROM audit_log ORDER BY id DESC LIMIT 200
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="admin.css">
    <style>
        [hidden] { display: none !important; }     /* rows hidden by "View all" */
        .view-all { display: inline-block; margin-top: .5rem; }
        dialog.wide { width: min(700px, 95vw); }
    </style>
</head>
<body>
    <header>
        <h2>Admin Dashboard</h2>
        <div>
            <span><?= htmlspecialchars($_SESSION['admin_email']) ?></span>
            <button id="pdfBtn">Download PDF</button>
            <button id="auditBtn">Activity Log</button>
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
            <input type="text" id="search" placeholder="Search name, ID, class, or hashed RFID...">
            <div class="scroll">
                <table id="studentsTable" class="limited">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>RFID (Hashed)</th>
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
                            data-last="<?= htmlspecialchars($s['last_name']) ?>">
                            <td><?= htmlspecialchars($s['student_id']) ?></td>
                            <td><?= htmlspecialchars($s['name']) ?></td>
                            <td><?= htmlspecialchars(implode(' - ', array_filter([$s['grade'], $s['course'], $s['section']])) ?: '—') ?></td>
                            <td><?= hide_uid($s['rfid_uid']) ?></td>
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
                                <?php if ($s['pending']): ?>
                                    <button type="button" disabled>Pending</button>
                                <?php else: ?>
                                    <button type="button" class="reval">Re-validate</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Waiting for re-validation -->
        <div class="panel">
            <h3>Waiting for Re-validation</h3>
            <div class="scroll">
                <table class="limited">
                    <thead>
                        <tr><th>Student</th><th>Reason</th><th>Requested</th><th>Documents &amp; Action</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$pending): ?>
                        <tr><td colspan="4">No students are waiting for re-validation.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($pending as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['student_id']) ?>)</td>
                            <td><?= htmlspecialchars($p['reason']) ?></td>
                            <td><?= $p['requested'] ?></td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <?php foreach ($docs as $key => $label): ?>
                                        <label><input type="checkbox" name="docs[]" value="<?= $key ?>" required> <?= $label ?></label>
                                    <?php endforeach; ?>
                                    <button type="submit" name="confirm_revalidation">Confirm</button>
                                    <button type="submit" name="cancel_revalidation" formnovalidate
                                            onclick="return confirm('Cancel this request?')">Cancel</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent activity: log ins on the left, log outs on the right -->
        <div class="panel">
            <h3>Recent Activity</h3>

            <div class="filters">
                <a href="?range=today" class="<?= $range === 'today' ? 'active' : '' ?>">Today</a>
                <a href="?range=week"  class="<?= $range === 'week'  ? 'active' : '' ?>">This Week</a>
                <a href="?range=month" class="<?= $range === 'month' ? 'active' : '' ?>">This Month</a>
                <a href="?range=all"   class="<?= $range === 'all'   ? 'active' : '' ?>">All</a>
            </div>

            <div class="two-col" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <?php foreach (['Logged In' => ['loginTable', $loggedIn], 'Logged Out' => ['logoutTable', $loggedOut]] as $title => [$tableId, $rows]): ?>
                    <div style="min-width: 0;<?= $tableId === 'logoutTable' ? ' border-left: 1px solid rgba(128,128,128,.5); padding-left: 1rem;' : '' ?>">
                        <h4><?= $title ?></h4>
                        <div class="scroll">
                            <table id="<?= $tableId ?>" class="limited">
                                <thead>
                                    <tr><th>Name</th><th>Student ID</th><th>Date &amp; Time</th></tr>
                                </thead>
                                <tbody>
                                <?php if (!$rows): ?>
                                    <tr><td colspan="3">No activity found.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['name']) ?></td>
                                        <td><?= htmlspecialchars($r['student_id']) ?></td>
                                        <td><?= htmlspecialchars($r['time_text']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
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
                <table class="limited">
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

    <!-- Edit student pop-up (RFID is not editable) -->
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

            <div class="form-actions">
                <button type="submit">Save</button>
                <button type="button" id="editCancel" class="ghost">Cancel</button>
            </div>
        </form>
    </dialog>

    <!-- Re-validation pop-up -->
    <dialog id="revalDialog">
        <form method="POST">
            <h3>Request Re-validation</h3>
            <p id="revalName"></p>
            <input type="hidden" name="student_id" id="revalId">

            <label for="revalReason">Reason for re-validation</label>
            <textarea name="reason" id="revalReason" minlength="5" maxlength="255" rows="4" required></textarea>

            <div class="form-actions">
                <button type="submit" name="request_revalidation">Submit</button>
                <button type="button" id="revalCancel" class="ghost">Cancel</button>
            </div>
        </form>
    </dialog>

    <!-- Activity log pop-up -->
    <dialog id="auditDialog" class="wide">
        <h3>Activity Log (Edits &amp; Deletes)</h3>
        <div class="scroll">
            <table class="limited">
                <thead>
                    <tr><th>Date &amp; Time</th><th>Admin</th><th>Action</th><th>Details</th></tr>
                </thead>
                <tbody>
                <?php if (!$auditLog): ?>
                    <tr><td colspan="4">Nothing logged yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($auditLog as $a): ?>
                    <tr>
                        <td><?= $a['logged_at'] ?></td>
                        <td><?= htmlspecialchars($a['admin_email']) ?></td>
                        <td><?= htmlspecialchars($a['action']) ?></td>
                        <td><?= htmlspecialchars($a['details']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="form-actions">
            <button type="button" id="auditClose" class="ghost">Close</button>
        </div>
    </dialog>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <script>
    const $ = id => document.getElementById(id);

    // ---- "View all": every table.limited shows its first rows only ----
    const LIMIT = 5;

    function setCollapsed(table, collapsed) {
        table.collapsed = collapsed;
        table.extra.forEach(row => row.hidden = collapsed);
        table.viewAll.textContent = collapsed ? 'View all (' + table.tBodies[0].rows.length + ')' : 'Show less';
    }

    document.querySelectorAll('table.limited').forEach(table => {
        const rows = [...table.tBodies[0].rows];
        if (rows.length <= LIMIT) return;                  // already short, no link needed

        table.extra = rows.slice(LIMIT);
        table.viewAll = document.createElement('a');
        table.viewAll.href = '#';
        table.viewAll.className = 'view-all';
        table.viewAll.addEventListener('click', e => {
            e.preventDefault();
            setCollapsed(table, !table.collapsed);
        });
        table.closest('.scroll').after(table.viewAll);
        setCollapsed(table, true);
    });

    // ---- Graph ----
    const chart = new Chart($('visitsChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{ label: 'Visits', data: <?= json_encode($data) ?> }]
        },
        options: { animation: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });

    // ---- PDF report (summary, graph, students, log ins, log outs) ----
    $('pdfBtn').addEventListener('click', () => {
        const doc = new window.jspdf.jsPDF();
        const filters = <?= json_encode('Grade: ' . ($fGrade ?: 'All') . ' | Course: ' . ($fCourse ?: 'All')) ?>;

        doc.setFontSize(16);
        doc.text('Library Report', 14, 15);
        doc.setFontSize(10);
        doc.text('Generated: ' + new Date().toLocaleString(), 14, 21);

        doc.autoTable({
            startY: 26,
            head: [['Total Students', 'Currently Inside', 'Visitors Today', 'Logouts Today']],
            body: [[<?= "$totalStudents, $insideNow, $visitsToday, $logoutsToday" ?>]]
        });

        let y = doc.lastAutoTable.finalY + 8;
        doc.text('Visits, last 14 days (' + filters + ')', 14, y);
        const w = 180, h = w * chart.height / chart.width;
        doc.addImage(chart.toBase64Image(), 'PNG', 14, y + 3, w, h);

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

        for (const [title, id] of [['Logged In', 'loginTable'], ['Logged Out', 'logoutTable']]) {
            y = doc.lastAutoTable.finalY + 10;
            doc.text(title + ' (<?= $range ?>)', 14, y);
            doc.autoTable({ startY: y + 3, html: '#' + id, includeHiddenHtml: true, styles: { fontSize: 8 } });
        }

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
        if (q && $('studentsTable').extra) setCollapsed($('studentsTable'), false);   // search looks at every student

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
            last_name: $('editLast').value
        });
        if (!r) return;
        if (r.success) {
            location.reload();
        } else {
            $('editMsg').textContent = r.message;
        }
    });

    // Re-validate: open the reason pop-up
    document.querySelectorAll('#studentsTable .reval').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = btn.closest('tr');
            $('revalId').value = row.dataset.id;
            $('revalName').textContent = row.dataset.name + ' (' + row.dataset.id + ')';
            $('revalReason').value = '';
            $('revalDialog').showModal();
        });
    });

    $('revalCancel').addEventListener('click', () => $('revalDialog').close());

    // Activity log pop-up
    $('auditBtn').addEventListener('click', () => $('auditDialog').showModal());
    $('auditClose').addEventListener('click', () => $('auditDialog').close());

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