<?php
require_once 'require_admin.php';

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli("localhost", "root", "", "arduino_db");
if ($conn->connect_error) {
    die("Database connection failed");
}
require 'audit.php';

// ---- Settings ----
const HASH_KEY = 'change-this-secret';                                       // secret used to hash RFID UIDs for display
$docs = ['student_id' => 'Student ID', 'reg_form' => 'Registration Form'];    // papers needed to confirm a re-validation

// Reasons shown in the Re-validate drop-down (order = order on screen).
// 'Other' lets the admin type their own reason.
const OTHER_REASON = 'Other';
$reasons = [
    'Lost / broken NFC sticker',
    'Change of name',
    'Change of grade / year level',
    'Change of course / strand',
    'Change of section',
    'New school year / semester enrollment',
    'Transferee',
    'Incorrect details in the record',
    OTHER_REASON,
];

function hide_uid($uid) {
    return substr(hash_hmac('sha256', $uid, HASH_KEY), 0, 12);
}

// First value of a query, or '?' when there is no row
function one($conn, $sql) {
    return ($conn->query($sql)->fetch_row() ?? ['?'])[0];
}

// Safe helpers: a failed query gives 0 / an empty list instead of a fatal error
function scalar($conn, $sql) {
    $res = $conn->query($sql);
    return $res ? (int)($res->fetch_row()[0] ?? 0) : 0;
}
function rows($conn, $sql) {
    $res = $conn->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

// ---- Form posts: filters + re-validation ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_option'])) {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '' && in_array($_POST['kind'] ?? '', ['grade', 'course'], true) && in_array($_POST['level'] ?? '', ['SHS', 'College'], true)) {
            $stmt = $conn->prepare("INSERT IGNORE INTO options (kind, level, name) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $_POST['kind'], $_POST['level'], $name);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                log_action($conn, 'Add filter', "{$_POST['kind']}: $name ({$_POST['level']})");
            }
        }

    } elseif (isset($_POST['delete_option'])) {
        $id    = (int)($_POST['id'] ?? 0);
        $label = one($conn, "SELECT CONCAT(kind, ': ', name, ' (', level, ')') FROM options WHERE id = $id");
        $conn->query("DELETE FROM options WHERE id = $id");
        log_action($conn, 'Delete filter', $label);

    } elseif (isset($_POST['request_revalidation'])) {
        $id     = trim($_POST['student_id'] ?? '');
        $choice = $_POST['reason_choice'] ?? '';
        $other  = trim($_POST['reason_other'] ?? '');

        // The reason must be one from the list; "Other" needs a typed reason
        $reason = '';
        if (in_array($choice, $reasons, true)) {
            $reason = $choice === OTHER_REASON ? ($other === '' ? '' : "Other: $other") : $choice;
        }

        if ($choice === '' || !in_array($choice, $reasons, true)) {
            $_SESSION['flash'] = ['error', 'Please choose a reason.'];
        } elseif ($choice === OTHER_REASON && mb_strlen($other) < 3) {
            $_SESSION['flash'] = ['error', 'Please type the reason (at least 3 characters).'];
        } else {
            // Only inserts if the student exists and has no pending request yet
            $stmt = $conn->prepare("INSERT INTO revalidations (student_id, reason)
                                    SELECT student_id, ? FROM students
                                    WHERE student_id = ?
                                      AND NOT EXISTS (SELECT 1 FROM revalidations WHERE student_id = ? AND status = 'pending')");
            $stmt->bind_param("sss", $reason, $id, $id);
            $ok = $stmt->execute();
            if ($ok && $stmt->affected_rows > 0) {
                log_action($conn, 'Re-validation', "$id requested: $reason");
                $_SESSION['flash'] = ['ok', "Re-validation requested for $id. It is now in the Waiting for Re-validation list."];
            } elseif (!$ok) {
                $_SESSION['flash'] = ['error', 'Could not save the request: ' . $stmt->error];
            } else {
                $_SESSION['flash'] = ['error', "Nothing was saved. $id was not found or already has a request waiting."];
            }
        }

    } elseif (isset($_POST['cancel_revalidation'])) {
        $id = (int)($_POST['id'] ?? 0);
        $conn->query("UPDATE revalidations SET status = 'cancelled' WHERE id = $id AND status = 'pending'");
        if ($conn->affected_rows > 0) {
            log_action($conn, 'Re-validation', one($conn, "SELECT student_id FROM revalidations WHERE id = $id") . ' cancelled');
            $_SESSION['flash'] = ['ok', 'Re-validation request cancelled.'];
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ---- One-time message shown after a re-validation step ----
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ---- Summary numbers ----
$totalStudents = scalar($conn, "SELECT COUNT(*) FROM students");
$insideNow     = scalar($conn, "SELECT COUNT(*) FROM student_status WHERE status = 'in'");
$visitsToday   = scalar($conn, "SELECT COUNT(*) FROM library_in WHERE DATE(login_time) = CURDATE()");
$logoutsToday  = scalar($conn, "SELECT COUNT(*) FROM library_out WHERE DATE(logout_time) = CURDATE()");

// ---- Filter options (graph filters and Manage Filters panel) ----
$options = rows($conn, "SELECT * FROM options ORDER BY kind DESC, level DESC, id");
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
$students = rows($conn, "
    SELECT s.student_id, s.name, s.first_name, s.middle_name, s.last_name, s.rfid_uid,
           s.grade, s.course, s.section,
           COALESCE(st.status, 'out') AS status,
           (SELECT DATE_FORMAT(MAX(login_time), '%b %d, %Y %h:%i %p')
              FROM library_in WHERE student_id = s.student_id) AS last_login,
           (SELECT COUNT(*) FROM library_in WHERE student_id = s.student_id) AS total_visits,
           (SELECT DATE_FORMAT(MAX(confirmed_at), '%b %d, %Y %h:%i %p')
              FROM revalidations WHERE student_id = s.student_id AND status = 'confirmed') AS last_reval,
           (SELECT COUNT(*) FROM revalidations
              WHERE student_id = s.student_id AND status = 'pending') AS pending
    FROM students s
    LEFT JOIN student_status st ON st.student_id = s.student_id
    ORDER BY s.name
");

// ---- Waiting for re-validation ----
$pending = rows($conn, "
    SELECT r.id, r.reason, s.student_id, s.name, s.first_name, s.middle_name, s.last_name,
           s.level, s.grade, s.course, s.section,
           DATE_FORMAT(r.requested_at, '%b %d, %Y %h:%i %p') AS requested
    FROM revalidations r JOIN students s ON s.student_id = r.student_id
    WHERE r.status = 'pending'
    ORDER BY r.requested_at
");

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

// Search box (student name or ID) and calendar date for Recent Activity
$q = mb_substr(trim($_GET['q'] ?? ''), 0, 60);

$fDate = $_GET['date'] ?? '';
if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fDate, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
    $fDate = '';
}
$fDateText = $fDate !== '' ? date('M d, Y', strtotime($fDate)) : '';

// Used in the links below so the search text survives clicking a range button
$qParam = $q !== '' ? '&q=' . urlencode($q) : '';

function activity($conn, $table, $col, $condition, $q = '', $date = '') {
    // A picked date replaces the Today / Week / Month / All range
    $where  = [$date !== '' ? "DATE(l.$col) = ?" : str_replace('{col}', "l.$col", $condition)];
    $types  = '';
    $params = [];
    if ($date !== '') {
        $types .= 's';
        $params[] = $date;
    }
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $where[] = "(s.name LIKE ? OR s.student_id LIKE ?)";
        $types .= 'ss';
        array_push($params, $like, $like);
    }

    $stmt = $conn->prepare("
        SELECT s.name, s.student_id, DATE_FORMAT(l.$col, '%b %d, %Y %h:%i %p') AS time_text
        FROM $table l JOIN students s ON s.student_id = l.student_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY l.$col DESC
        LIMIT 100
    ");
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$loggedIn  = activity($conn, 'library_in',  'login_time',  $rangeSql[$range], $q, $fDate);
$loggedOut = activity($conn, 'library_out', 'logout_time', $rangeSql[$range], $q, $fDate);

// Dates that have at least one log in / log out (marked in the calendar): ['2026-11-27' => ['in' => 3, 'out' => 2]]
$activityDates = [];
foreach (['in' => ['library_in', 'login_time'], 'out' => ['library_out', 'logout_time']] as $kind => [$table, $col]) {
    $res = $conn->query("SELECT DATE(l.$col) AS d, COUNT(*) AS c
                         FROM $table l JOIN students s ON s.student_id = l.student_id
                         GROUP BY d");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $activityDates[$row['d']][$kind] = (int)$row['c'];
        }
    }
}

// ---- Audit log (edits and deletes) ----
$auditLog = rows($conn, "
    SELECT admin_email, action, details, DATE_FORMAT(created_at, '%b %d, %Y %h:%i:%s %p') AS logged_at
    FROM audit_log ORDER BY id DESC LIMIT 200
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script>
    // Shows any JavaScript error as a red bar at the top, so problems are easy to see and report
    window.addEventListener('error', e => {
        if (e.target && e.target !== window) return;           // ignore failed images / files
        const bar = document.createElement('div');
        bar.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:99999;padding:10px 16px;background:#c62828;color:#fff;font:14px monospace';
        bar.textContent = 'Page error: ' + e.message + ' (line ' + e.lineno + ')';
        (document.body || document.documentElement).appendChild(bar);
    });
    </script>
    <link rel="stylesheet" href="admin.css">

</head>
<body>
    <header class="admin-header">
        <button type="button" class="menu-toggle" id="menuToggle"
                aria-label="Open admin menu" aria-controls="adminSidebar" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
        <h2>Admin Dashboard</h2>
        <span class="admin-email"><?= htmlspecialchars($_SESSION['admin_email']) ?></span>
    </header>

    <div class="sidebar-backdrop" id="sidebarBackdrop" hidden></div>
    <aside class="admin-sidebar" id="adminSidebar" aria-label="Admin navigation" aria-hidden="true">
        <div class="sidebar-heading">
            <div>
                <strong>Admin Menu</strong>
                <small>Library Management</small>
            </div>
            <button type="button" class="sidebar-close" id="sidebarClose" aria-label="Close menu">&times;</button>
        </div>
        <nav class="sidebar-nav">
            <a href="#studentsSection" class="sidebar-link"><span class="nav-icon">♙</span> Students</a>
            <a href="#pendingSection" class="sidebar-link"><span class="nav-icon">◷</span> Pending Validation</a>
            <a href="#recentActivitySection" class="sidebar-link"><span class="nav-icon">↻</span> Recent Activities</a>
            <a href="#manageFiltersSection" class="sidebar-link"><span class="nav-icon">☷</span> Manage Filters</a>
            <div class="sidebar-divider"></div>
            <button type="button" class="sidebar-action" id="pdfBtn"><span class="nav-icon">▤</span> Report</button>
            <button type="button" class="sidebar-action" id="auditBtn"><span class="nav-icon">▣</span> Activity Logs</button>
            <button type="button" class="sidebar-action logout-link" id="logoutBtn"><span class="nav-icon">⇥</span> Log Out</button>
        </nav>
    </aside>

    <main>
        <?php if ($flash): ?>
            <p class="status <?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" id="flash"><?= htmlspecialchars($flash[1]) ?></p>
        <?php endif; ?>

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
                <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
                <input type="hidden" name="date" value="<?= htmlspecialchars($fDate) ?>">
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
        <div class="panel" id="studentsSection">
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
                            <th>Last Re-validated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$students): ?>
                        <tr><td colspan="9">No students found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($students as $s): ?>
                        <tr data-id="<?= htmlspecialchars($s['student_id']) ?>"
                            data-name="<?= htmlspecialchars($s['name']) ?>"
                            data-first="<?= htmlspecialchars($s['first_name'] ?? '') ?>"
                            data-middle="<?= htmlspecialchars($s['middle_name'] ?? '') ?>"
                            data-last="<?= htmlspecialchars($s['last_name'] ?? '') ?>">
                            <td><?= htmlspecialchars($s['student_id']) ?></td>
                            <td><?= htmlspecialchars($s['name']) ?></td>
                            <td><?= htmlspecialchars(implode(' - ', array_filter([$s['grade'], $s['course'], $s['section']])) ?: '—') ?></td>
                            <td><?= hide_uid($s['rfid_uid'] ?? '') ?></td>
                            <td>
                                <span class="badge <?= $s['status'] === 'in' ? 'in' : 'out' ?>">
                                    <?= $s['status'] === 'in' ? 'Inside' : 'Outside' ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($s['last_login'] ?? '—') ?></td>
                            <td><?= (int)$s['total_visits'] ?></td>
                            <td><?= htmlspecialchars($s['last_reval'] ?? '—') ?></td>
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
        <div class="panel" id="pendingSection">
            <h3>Waiting for Re-validation</h3>
            <div class="scroll">
                <table class="limited">
                    <thead>
                        <tr><th>Student</th><th>Reason</th><th>Requested</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$pending): ?>
                        <tr><td colspan="4">No students are waiting for re-validation.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($pending as $p): ?>
                        <tr data-rid="<?= $p['id'] ?>"
                            data-id="<?= htmlspecialchars($p['student_id']) ?>"
                            data-name="<?= htmlspecialchars($p['name']) ?>"
                            data-first="<?= htmlspecialchars($p['first_name'] ?? '') ?>"
                            data-middle="<?= htmlspecialchars($p['middle_name'] ?? '') ?>"
                            data-last="<?= htmlspecialchars($p['last_name'] ?? '') ?>"
                            data-level="<?= htmlspecialchars($p['level']) ?>"
                            data-grade="<?= htmlspecialchars($p['grade'] ?? '') ?>"
                            data-course="<?= htmlspecialchars($p['course'] ?? '') ?>"
                            data-section="<?= htmlspecialchars($p['section'] ?? '') ?>"
                            data-reason="<?= htmlspecialchars($p['reason']) ?>">
                            <td><?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['student_id']) ?>)</td>
                            <td><?= htmlspecialchars($p['reason']) ?></td>
                            <td><?= $p['requested'] ?></td>
                            <td class="actions">
                                <button type="button" class="review">Review &amp; Confirm</button>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit" name="cancel_revalidation" class="delete"
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
        <div class="panel" id="recentActivitySection">
            <h3>Recent Activity</h3>

            <!-- Search by student name / ID, or pick a date with the calendar icon -->
            <form method="GET" class="activity-search" id="activityForm">
                <input type="hidden" name="range" value="<?= $range ?>">
                <input type="hidden" name="grade" value="<?= htmlspecialchars($fGrade) ?>">
                <input type="hidden" name="course" value="<?= htmlspecialchars($fCourse) ?>">
                <div class="search-wrap">
                    <input type="text" name="q" id="activitySearch" maxlength="60"
                           placeholder="Search student name or ID..." value="<?= htmlspecialchars($q) ?>">
                    <button type="button" class="cal-btn" id="dateBtn" title="Search by date" aria-label="Search by date">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    </button>
                    <input type="hidden" name="date" id="dateInput" value="<?= htmlspecialchars($fDate) ?>">
                    <div class="cal-pop" id="calendar" hidden>
                        <div class="cal-head">
                            <button type="button" id="calPrev" aria-label="Previous month">&lsaquo;</button>
                            <span id="calTitle"></span>
                            <button type="button" id="calNext" aria-label="Next month">&rsaquo;</button>
                        </div>
                        <div class="cal-grid" id="calGrid"></div>
                        <div class="cal-legend">
                            <span><i class="dot-in"></i> Log in</span>
                            <span><i class="dot-out"></i> Log out</span>
                        </div>
                    </div>
                </div>
                <button type="submit">Search</button>
                <?php if ($fDate !== ''): ?>
                    <span class="date-chip">Date: <?= htmlspecialchars($fDateText) ?></span>
                <?php endif; ?>
                <?php if ($q !== '' || $fDate !== ''): ?>
                    <a href="?range=<?= $range ?>">Clear</a>
                <?php endif; ?>
            </form>

            <div class="filters">
                <a href="?range=today<?= $qParam ?>" class="<?= !$fDate && $range === 'today' ? 'active' : '' ?>">Today</a>
                <a href="?range=week<?= $qParam ?>"  class="<?= !$fDate && $range === 'week'  ? 'active' : '' ?>">This Week</a>
                <a href="?range=month<?= $qParam ?>" class="<?= !$fDate && $range === 'month' ? 'active' : '' ?>">This Month</a>
                <a href="?range=all<?= $qParam ?>"   class="<?= !$fDate && $range === 'all'   ? 'active' : '' ?>">All</a>
            </div>

            <div class="two-col activity-columns">
                <?php foreach (['Logged In' => ['loginTable', $loggedIn], 'Logged Out' => ['logoutTable', $loggedOut]] as $title => [$tableId, $rows]): ?>
                    <div class="activity-column<?= $tableId === 'logoutTable' ? ' activity-column-secondary' : '' ?>">
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
        <div class="panel" id="manageFiltersSection">
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

            <label for="revalChoice">Reason for re-validation</label>
            <select name="reason_choice" id="revalChoice" required>
                <option value="">Select a reason...</option>
                <?php foreach ($reasons as $r): ?>
                    <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                <?php endforeach; ?>
            </select>

            <textarea name="reason_other" id="revalOther" minlength="3" maxlength="240" rows="3"
                      placeholder="Type the reason..." hidden></textarea>

            <div class="form-actions">
                <button type="submit" name="request_revalidation">Submit</button>
                <button type="button" id="revalCancel" class="ghost">Cancel</button>
            </div>
        </form>
    </dialog>

    <!-- Confirm re-validation: check details, check papers, scan the ID card -->
    <dialog id="confirmDialog">
        <form id="confirmForm">
            <h3>Confirm Re-validation</h3>
            <p id="cvWho"></p>
            <p id="cvMsg" class="form-error"></p>
            <input type="hidden" id="cvRid">

            <label for="cvId">Student Number</label>
            <input type="text" id="cvId" readonly>

            <label for="cvFirst">First Name</label>
            <input type="text" id="cvFirst" maxlength="30" required>

            <label for="cvMiddle">Middle Name (optional)</label>
            <input type="text" id="cvMiddle" maxlength="30">

            <label for="cvLast">Last Name</label>
            <input type="text" id="cvLast" maxlength="30" required>

            <label>School Level</label>
            <div class="radio-row">
                <label class="check"><input type="radio" name="cvLevel" value="SHS"> SHS</label>
                <label class="check"><input type="radio" name="cvLevel" value="College"> College</label>
            </div>

            <label for="cvGrade">Grade / Year</label>
            <select id="cvGrade" required></select>

            <label for="cvCourse">Course</label>
            <select id="cvCourse" required></select>

            <label for="cvSection">Section</label>
            <input type="text" id="cvSection" maxlength="10" required>

            <label>Papers checked</label>
            <?php foreach ($docs as $key => $label): ?>
                <label class="check"><input type="checkbox" class="cvDoc" value="<?= $key ?>"> <?= $label ?></label>
            <?php endforeach; ?>

            <label>ID card</label>
            <div class="radio-row">
                <label class="check"><input type="radio" name="cvCard" value="current" checked> Same card</label>
                <label class="check"><input type="radio" name="cvCard" value="new"> New card (lost / broken)</label>
            </div>
            <p id="cvScan" class="status">Waiting for the card...</p>

            <div class="form-actions">
                <button type="submit" id="cvConfirm" disabled>Confirm</button>
                <button type="button" id="cvCancel" class="ghost">Close</button>
            </div>
        </form>
    </dialog>

    <!-- Activity log pop-up -->
    <dialog id="auditDialog" class="wide">
        <h3>Activity Log</h3>
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
    // ---- Admin navigation drawer ----
    const sidebar = $('adminSidebar');
    const backdrop = $('sidebarBackdrop');

    function setSidebar(open) {
        sidebar.classList.toggle('is-open', open);
        backdrop.hidden = !open;
        sidebar.setAttribute('aria-hidden', String(!open));
        $('menuToggle').setAttribute('aria-expanded', String(open));
        $('menuToggle').setAttribute('aria-label', open ? 'Close admin menu' : 'Open admin menu');
        document.body.classList.toggle('sidebar-open', open);
    }

    $('menuToggle').addEventListener('click', () => {
        setSidebar(!sidebar.classList.contains('is-open'));
    });
    $('sidebarClose').addEventListener('click', () => setSidebar(false));
    backdrop.addEventListener('click', () => setSidebar(false));
    document.querySelectorAll('.sidebar-link').forEach(link => {
        link.addEventListener('click', () => setSidebar(false));
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') setSidebar(false);
    });


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
    // If the Chart.js file can't load (no internet), skip the graph so the buttons below still work
    const chart = typeof Chart === 'undefined' ? null : new Chart($('visitsChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{ label: 'Visits', data: <?= json_encode($data) ?> }]
        },
        options: { animation: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });

    // ---- PDF report (summary, graph, students, log ins, log outs) ----
    $('pdfBtn').addEventListener('click', () => {
        setSidebar(false);
        if (!window.jspdf) { alert('The PDF library could not load. Check your internet connection and refresh the page.'); return; }
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
        let h = 0;
        if (chart) {
            const w = 180;
            h = w * chart.height / chart.width;
            doc.addImage(chart.toBase64Image(), 'PNG', 14, y + 3, w, h);
        }

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
            doc.text(title + ' (' + <?= json_encode(($fDate !== '' ? $fDateText : $range) . ($q !== '' ? ' | Search: ' . $q : '')) ?> + ')', 14, y);
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
            $('revalChoice').value = '';
            $('revalOther').value = '';
            $('revalOther').hidden = true;
            $('revalOther').required = false;
            $('revalDialog').showModal();
        });
    });

    $('revalCancel').addEventListener('click', () => $('revalDialog').close());

    // "Other" shows a box to type the reason
    $('revalChoice').addEventListener('change', function () {
        const other = this.value === <?= json_encode(OTHER_REASON) ?>;
        $('revalOther').hidden = !other;
        $('revalOther').required = other;
        if (other) $('revalOther').focus();
    });

    // ---- Confirm re-validation: review details, check papers, scan the card ----
    const OPTIONS = <?= json_encode($options) ?>;
    let cvSid = '', cvScanId = 0, cvBaseline = null, cvTimer = null;

    function cvFillDropdowns(grade, course) {
        const level = document.querySelector('input[name=cvLevel]:checked')?.value;
        for (const [kind, id, chosen] of [['grade', 'cvGrade', grade], ['course', 'cvCourse', course]]) {
            $(id).replaceChildren(
                new Option('Select...', ''),
                ...OPTIONS.filter(o => o.kind === kind && o.level === level)
                          .map(o => new Option(o.name, o.name, false, o.name === chosen))
            );
        }
    }
    document.querySelectorAll('input[name=cvLevel]').forEach(r =>
        r.addEventListener('change', () => cvFillDropdowns('', '')));

    const cvMode = () => document.querySelector('input[name=cvCard]:checked')?.value || 'current';

    function cvPrompt() {
        cvScanId = 0;
        cvBaseline = null;                                  // the next scan must be a fresh tap
        cvSetScan(cvMode() === 'new'
            ? 'Waiting for the NEW card... Ask the student to tap the new card on the reader.'
            : 'Waiting for the card... Ask the student to tap their current ID on the reader.', '');
    }
    document.querySelectorAll('input[name=cvCard]').forEach(r => r.addEventListener('change', cvPrompt));

    function cvSetScan(text, state) {
        $('cvScan').textContent = text;
        $('cvScan').className = 'status' + (state ? ' ' + state : '');
        $('cvConfirm').disabled = state !== 'ok';
    }

    // Looks at the newest card scan every half second while the pop-up is open
    async function cvCheckScan() {
        try {
            const scan = await (await fetch('api.php?action=latest', { cache: 'no-store' })).json();
            const id = Number(scan.id);
            if (cvBaseline === null) { cvBaseline = id; return; }   // ignore scans from before the pop-up opened
            if (id <= cvBaseline) return;
            cvBaseline = id;

            if (cvMode() === 'new') {
                // Replacing a lost / broken card: the scanned card must be unregistered
                if (!scan.registered) {
                    cvScanId = id;
                    cvSetScan('New card detected. It will replace the old card when you confirm.', 'ok');
                } else if (scan.student_id === cvSid) {
                    cvScanId = 0;
                    cvSetScan("That is this student's current card. Choose 'Same card', or scan a new card.", 'error');
                } else {
                    cvScanId = 0;
                    cvSetScan('That card is already registered to ' + scan.name + '. Scan a new, unregistered card.', 'error');
                }
            } else if (!scan.registered) {
                cvScanId = 0;
                cvSetScan("This card is not registered. Scan the student's own card, or choose 'New card'.", 'error');
            } else if (scan.student_id !== cvSid) {
                cvScanId = 0;
                cvSetScan('This card belongs to another student. Scan again.', 'error');
            } else {
                cvScanId = id;
                cvSetScan('Card verified: ' + scan.name + '. You can confirm now.', 'ok');
            }
        } catch (e) {
            cvSetScan('Cannot reach api.php', 'error');
        }
    }

    document.querySelectorAll('.review').forEach(btn => {
        btn.addEventListener('click', () => {
            const d = btn.closest('tr').dataset;
            cvSid = d.id;
            cvScanId = 0;
            cvBaseline = null;
            $('cvRid').value = d.rid;
            $('cvWho').textContent = d.name + ' (' + d.id + ')';
            $('cvId').value = d.id;
            $('cvFirst').value = d.first;
            $('cvMiddle').value = d.middle;
            $('cvLast').value = d.last;
            $('cvSection').value = d.section;
            document.querySelectorAll('input[name=cvLevel]').forEach(r => r.checked = r.value === d.level);
            cvFillDropdowns(d.grade, d.course);
            document.querySelectorAll('.cvDoc').forEach(c => c.checked = false);
            $('cvMsg').textContent = '';
            // A lost / broken sticker request starts on "New card"
            document.querySelectorAll('input[name=cvCard]').forEach(r =>
                r.checked = r.value === (d.reason.startsWith('Lost / broken') ? 'new' : 'current'));
            cvPrompt();
            $('confirmDialog').showModal();
            cvCheckScan();
            clearInterval(cvTimer);
            cvTimer = setInterval(cvCheckScan, 500);
        });
    });

    $('confirmDialog').addEventListener('close', () => clearInterval(cvTimer));
    $('cvCancel').addEventListener('click', () => $('confirmDialog').close());

    $('confirmForm').addEventListener('submit', async e => {
        e.preventDefault();
        if (!cvScanId) { $('cvMsg').textContent = "Scan the student's ID card first."; return; }

        const data = new URLSearchParams({
            revalidation_id: $('cvRid').value,
            scan_id:     cvScanId,
            first_name:  $('cvFirst').value,
            middle_name: $('cvMiddle').value,
            last_name:   $('cvLast').value,
            level:       document.querySelector('input[name=cvLevel]:checked')?.value || '',
            grade:       $('cvGrade').value,
            course:      $('cvCourse').value,
            section:     $('cvSection').value,
            card_mode:   cvMode()
        });
        document.querySelectorAll('.cvDoc:checked').forEach(c => data.append('docs[]', c.value));

        const r = await studentsApi('confirm_revalidation', data);
        if (!r) return;
        if (r.success) {
            location.reload();
        } else {
            $('cvMsg').textContent = r.message;
            cvPrompt();                                     // scan again after any error
        }
    });

    // ---- Recent Activity calendar: dates with a log in / log out are marked with dots ----
    const ACTIVITY = <?= json_encode($activityDates, JSON_FORCE_OBJECT) ?>;   // { '2026-11-27': { in: 3, out: 2 } }
    const TODAY    = <?= json_encode(date('Y-m-d')) ?>;
    const MONTHS   = ['January', 'February', 'March', 'April', 'May', 'June', 'July',
                      'August', 'September', 'October', 'November', 'December'];
    const pad = n => String(n).padStart(2, '0');

    const [ty, tm] = TODAY.split('-').map(Number);
    let [viewYear, viewMonth] = ($('dateInput').value || TODAY).split('-').map(Number);
    viewMonth -= 1;                                          // 0-based month

    function renderCalendar() {
        const selected = $('dateInput').value;
        $('calTitle').textContent = MONTHS[viewMonth] + ' ' + viewYear;
        $('calNext').disabled = viewYear > ty || (viewYear === ty && viewMonth >= tm - 1);   // no future months

        const grid = $('calGrid');
        grid.replaceChildren();
        for (const d of ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']) {
            const h = document.createElement('div');
            h.className = 'dow';
            h.textContent = d;
            grid.appendChild(h);
        }

        const firstDay    = new Date(viewYear, viewMonth, 1).getDay();
        const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
        for (let i = 0; i < firstDay; i++) grid.appendChild(document.createElement('div'));

        for (let day = 1; day <= daysInMonth; day++) {
            const key  = viewYear + '-' + pad(viewMonth + 1) + '-' + pad(day);
            const info = ACTIVITY[key];
            const btn  = document.createElement('button');
            btn.type = 'button';
            btn.className = 'cal-day' + (key === TODAY ? ' today' : '') + (key === selected ? ' selected' : '');
            btn.textContent = day;
            btn.disabled = key > TODAY;                      // future dates have no activity yet

            if (info) {
                const dots = document.createElement('span');
                dots.className = 'dots';
                if (info.in)  dots.appendChild(Object.assign(document.createElement('i'), { className: 'dot-in' }));
                if (info.out) dots.appendChild(Object.assign(document.createElement('i'), { className: 'dot-out' }));
                btn.appendChild(dots);
                btn.title = (info.in || 0) + ' log in(s), ' + (info.out || 0) + ' log out(s)';
            }

            btn.addEventListener('click', () => {
                $('dateInput').value = key;
                $('activityForm').submit();
            });
            grid.appendChild(btn);
        }
    }

    $('dateBtn').addEventListener('click', e => {
        e.stopPropagation();
        $('calendar').hidden = !$('calendar').hidden;
        if (!$('calendar').hidden) renderCalendar();
    });
    $('calPrev').addEventListener('click', () => {
        if (--viewMonth < 0) { viewMonth = 11; viewYear--; }
        renderCalendar();
    });
    $('calNext').addEventListener('click', () => {
        if (++viewMonth > 11) { viewMonth = 0; viewYear++; }
        renderCalendar();
    });
    // Close when clicking elsewhere or pressing Escape
    document.addEventListener('click', e => {
        if (!$('calendar').hidden && !$('calendar').contains(e.target)) $('calendar').hidden = true;
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') $('calendar').hidden = true;
    });

    // Activity log pop-up
    $('auditBtn').addEventListener('click', () => { setSidebar(false); $('auditDialog').showModal(); });
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