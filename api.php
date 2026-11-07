<?php
header("Content-Type: application/json");
mysqli_report(MYSQLI_REPORT_OFF);

// How long a student's in/out status lasts before it is cleared.
// Change this one line if you want a different time (e.g. "8 HOUR" or "1 MINUTE" for testing).
const STATUS_RESET_AFTER = "12 HOUR";

// A student who scans again within this many seconds of logging in gets asked
// "do you want to cancel your log in?" instead of being logged out. (180 = 3 minutes)
const CANCEL_LOGIN_WITHIN = 180;

$conn = new mysqli("localhost", "root", "", "arduino_db");
if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

$action = $_GET['action'] ?? '';

// Clear statuses older than the limit (students who forgot to tap out)
function resetOldStatus($conn) {
    $conn->query("DELETE FROM student_status
                  WHERE updated_at < (NOW() - INTERVAL " . STATUS_RESET_AFTER . ")");
}

// Delete old RFID scans (keeps the newest one), at most once every 2 minutes.
// Also clears old statuses on the same schedule.
function cleanOldScans($conn) {
    $flag = sys_get_temp_dir() . '/rfid_cleanup.txt';
    $last = file_exists($flag) ? (int)file_get_contents($flag) : 0;

    if (time() - $last < 120) return;      // not yet 2 minutes
    file_put_contents($flag, time());

    $conn->query("DELETE FROM rfid_logs
                  WHERE id < (SELECT m FROM (SELECT MAX(id) AS m FROM rfid_logs) AS x)");

    resetOldStatus($conn);
}

// Newest scan, plus whether that card is already registered
if ($action === 'latest') {
    cleanOldScans($conn);

    $row = $conn->query("SELECT id, uid FROM rfid_logs ORDER BY id DESC LIMIT 1")->fetch_assoc();
    if (!$row) {
        echo json_encode(["id" => 0, "uid" => "", "registered" => false, "name" => ""]);
        exit;
    }
    $stmt = $conn->prepare("SELECT name FROM students WHERE rfid_uid = ?");
    $stmt->bind_param("s", $row['uid']);
    $stmt->execute();
    $s = $stmt->get_result()->fetch_assoc();

    echo json_encode([
        "id" => (int)$row['id'],
        "uid" => $row['uid'],
        "registered" => (bool)$s,
        "name" => $s['name'] ?? ""
    ]);
    exit;
}

// Scan = toggle the student IN or OUT of the library
if ($action === 'login') {
    $uid = $_POST['uid'] ?? '';

    $stmt = $conn->prepare("SELECT student_id, name FROM students WHERE rfid_uid = ?");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();

    if (!$student) {
        echo json_encode(["found" => false]);
        exit;
    }

    $sid = $student['student_id'];

    // Make sure expired statuses are gone before checking this student
    resetOldStatus($conn);

    // Current status (no row = outside)
    $stmt = $conn->prepare("SELECT status FROM student_status WHERE student_id = ?");
    $stmt->bind_param("s", $sid);
    $stmt->execute();
    $st = $stmt->get_result()->fetch_assoc();
    $currentStatus = $st['status'] ?? 'out';

    // Scanned again shortly after logging in: ask whether to cancel the login
    // instead of logging the student out
    if ($currentStatus === 'in') {
        $stmt = $conn->prepare("SELECT TIMESTAMPDIFF(SECOND, login_time, NOW()) AS secs
                                FROM library_in WHERE student_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("s", $sid);
        $stmt->execute();
        $last = $stmt->get_result()->fetch_assoc();

        if ($last && (int)$last['secs'] >= 0 && (int)$last['secs'] <= CANCEL_LOGIN_WITHIN) {
            echo json_encode([
                "found" => true,
                "action" => "confirm_cancel",
                "name" => $student['name'],
                "student_id" => $sid,
                "seconds_ago" => (int)$last['secs'],
                "window" => CANCEL_LOGIN_WITHIN
            ]);
            exit;
        }
    }

    if ($currentStatus === 'out') {
        $newStatus = 'in';
        $table = 'library_in';
        $col = 'login_time';
    } else {
        $newStatus = 'out';
        $table = 'library_out';
        $col = 'logout_time';
    }

    $stmt = $conn->prepare("INSERT INTO $table (student_id) VALUES (?)");
    $stmt->bind_param("s", $sid);
    $stmt->execute();
    $newId = (int)$conn->insert_id;

    // Save the new status
    $stmt = $conn->prepare("INSERT INTO student_status (student_id, status) VALUES (?, ?)
                            ON DUPLICATE KEY UPDATE status = VALUES(status)");
    $stmt->bind_param("ss", $sid, $newStatus);
    $stmt->execute();

    $t = $conn->query("SELECT DATE_FORMAT($col, '%M %d, %Y') AS d,
                              DATE_FORMAT($col, '%h:%i %p') AS t
                       FROM $table WHERE id = $newId")->fetch_assoc();

    echo json_encode([
        "found" => true,
        "action" => $newStatus,      // "in" or "out"
        "name" => $student['name'],
        "student_id" => $sid,
        "date" => $t['d'],
        "time" => $t['t']
    ]);
    exit;
}

// Cancel a login (only allowed within CANCEL_LOGIN_WITHIN seconds after logging in)
if ($action === 'cancel_login') {
    $uid = $_POST['uid'] ?? '';

    $stmt = $conn->prepare("SELECT student_id, name FROM students WHERE rfid_uid = ?");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();

    if (!$student) {
        echo json_encode(["cancelled" => false, "reason" => "not_found"]);
        exit;
    }

    $sid = $student['student_id'];

    // Must currently be IN
    $stmt = $conn->prepare("SELECT status FROM student_status WHERE student_id = ?");
    $stmt->bind_param("s", $sid);
    $stmt->execute();
    $st = $stmt->get_result()->fetch_assoc();
    if (!$st || $st['status'] !== 'in') {
        echo json_encode(["cancelled" => false, "reason" => "not_in"]);
        exit;
    }

    // Newest login must be inside the time window
    $stmt = $conn->prepare("SELECT id, TIMESTAMPDIFF(SECOND, login_time, NOW()) AS secs
                            FROM library_in WHERE student_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("s", $sid);
    $stmt->execute();
    $last = $stmt->get_result()->fetch_assoc();
    if (!$last || (int)$last['secs'] > CANCEL_LOGIN_WITHIN) {
        echo json_encode(["cancelled" => false, "reason" => "too_late"]);
        exit;
    }

    // Remove the login record and put the student back to "outside"
    $stmt = $conn->prepare("DELETE FROM library_in WHERE id = ?");
    $stmt->bind_param("i", $last['id']);
    $stmt->execute();

    $stmt = $conn->prepare("DELETE FROM student_status WHERE student_id = ?");
    $stmt->bind_param("s", $sid);
    $stmt->execute();

    echo json_encode(["cancelled" => true, "name" => $student['name'], "student_id" => $sid]);
    exit;
}

// Recent logins and logouts (two separate lists)
if ($action === 'list') {
    $in = $conn->query("SELECT s.name, s.student_id,
                               DATE_FORMAT(l.login_time, '%M %d, %Y') AS d,
                               DATE_FORMAT(l.login_time, '%h:%i %p') AS t
                        FROM library_in l
                        JOIN students s ON s.student_id = l.student_id
                        ORDER BY l.id DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);

    $out = $conn->query("SELECT s.name, s.student_id,
                                DATE_FORMAT(l.logout_time, '%M %d, %Y') AS d,
                                DATE_FORMAT(l.logout_time, '%h:%i %p') AS t
                         FROM library_out l
                         JOIN students s ON s.student_id = l.student_id
                         ORDER BY l.id DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);

    echo json_encode(["in" => $in, "out" => $out]);
    exit;
}

// Number of visitors on a specific date (counts library_in)
if ($action === 'count') {
    $date = $_GET['date'] ?? date('Y-m-d');

    // only accept YYYY-MM-DD
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(['count' => 0]);
        exit;
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM library_in WHERE DATE(login_time) = ?");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    echo json_encode(['count' => (int)$row['total'], 'date' => $date]);
    exit;
}

echo json_encode(["error" => "Unknown action"]);