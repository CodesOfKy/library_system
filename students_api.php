<?php
define('ADMIN_API', true);
require_once 'require_admin.php';

session_start();
header("Content-Type: application/json");
mysqli_report(MYSQLI_REPORT_OFF);
require 'audit.php';

// Admin only
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit;
}

function fail($msg) {
    echo json_encode(["success" => false, "message" => $msg]);
    exit;
}

// True if the query finds at least one row
function exists($conn, $sql, $types, ...$params) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// The student's name, or null if there is no such student
function student_name($conn, $sid) {
    $stmt = $conn->prepare("SELECT name FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $sid);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['name'] ?? null;
}

// Trim and turn repeated spaces into one
function clean($text) {
    return preg_replace('/\s+/', ' ', trim($text));
}

$conn = new mysqli("localhost", "root", "", "arduino_db");
if ($conn->connect_error) {
    fail("Database connection failed.");
}
$conn->set_charset("utf8mb4");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail("Invalid request.");
}

$action = $_GET['action'] ?? '';

// ---------------------------------------------------------
// Edit a student (the RFID card cannot be changed here)
// ---------------------------------------------------------
if ($action === 'update') {
    $sid    = trim($_POST['student_id'] ?? '');
    $first  = clean($_POST['first_name'] ?? '');
    $middle = clean($_POST['middle_name'] ?? '');
    $last   = clean($_POST['last_name'] ?? '');

    if ($sid === '' || $first === '' || $last === '') {
        fail("Please fill in the first name and last name.");
    }

    foreach (["First name" => $first, "Middle name" => $middle, "Last name" => $last] as $label => $value) {
        if ($value !== '' && !preg_match('/^[\p{L}][\p{L} .\'-]*$/u', $value)) {
            fail("$label can only have letters, spaces, periods, apostrophes and dashes.");
        }
        if (mb_strlen($value) > 30) {
            fail("$label is too long (30 characters max).");
        }
    }

    // The student must exist
    $oldName = student_name($conn, $sid);
    if ($oldName === null) {
        fail("Student not found. It may have been deleted already.");
    }

    // No one else may have the same first + last name
    if (exists($conn, "SELECT id FROM students WHERE first_name = ? AND last_name = ? AND student_id <> ?", "sss", $first, $last, $sid)) {
        fail("Another student named $first $last is already registered.");
    }

    $full = implode(' ', array_filter([$first, $middle, $last]));   // the "name" column

    $stmt = $conn->prepare("UPDATE students
                            SET first_name = ?, middle_name = ?, last_name = ?, name = ?
                            WHERE student_id = ?");
    if (!$stmt) {
        fail("Something went wrong: " . $conn->error);
    }
    $stmt->bind_param("sssss", $first, $middle, $last, $full, $sid);

    if (!$stmt->execute()) {
        if ($stmt->errno == 1062) {
            fail("That name is already registered.");
        }
        fail("Something went wrong: " . $stmt->error);
    }

    log_action($conn, 'Edit student', "$sid: $oldName to $full");
    echo json_encode(["success" => true]);
    exit;
}

// ---------------------------------------------------------
// Delete a student (and their login / logout history)
// ---------------------------------------------------------
if ($action === 'delete') {
    $sid = trim($_POST['student_id'] ?? '');

    if ($sid === '') {
        fail("No student selected.");
    }
    $oldName = student_name($conn, $sid);
    if ($oldName === null) {
        fail("Student not found. It may have been deleted already.");
    }

    // History first, then the student - all or nothing
    $conn->begin_transaction();

    foreach (["library_in", "library_out", "student_status", "revalidations", "students"] as $table) {
        $stmt = $conn->prepare("DELETE FROM $table WHERE student_id = ?");
        if (!$stmt || !$stmt->bind_param("s", $sid) || !$stmt->execute()) {
            $conn->rollback();
            fail("Could not delete (" . $table . "): " . $conn->error);
        }
    }

    $conn->commit();
    log_action($conn, 'Delete student', "$sid: $oldName");
    echo json_encode(["success" => true]);
    exit;
}

// ---------------------------------------------------------
// Confirm a re-validation:
//   1. every paper is checked
//   2. the student's details (name, level, grade, course, section) are saved
//   3. the student's own RFID card was scanned just now
// All or nothing: the request is only marked "confirmed" if everything passes.
// ---------------------------------------------------------
if ($action === 'confirm_revalidation') {
    $rid     = (int)($_POST['revalidation_id'] ?? 0);
    $scanId  = (int)($_POST['scan_id'] ?? 0);
    $first   = clean($_POST['first_name'] ?? '');
    $middle  = clean($_POST['middle_name'] ?? '');
    $last    = clean($_POST['last_name'] ?? '');
    $level   = $_POST['level'] ?? '';
    $grade   = $_POST['grade'] ?? '';
    $course  = $_POST['course'] ?? '';
    $section = clean($_POST['section'] ?? '');
    $newCard = ($_POST['card_mode'] ?? 'current') === 'new';   // true = replace a lost / broken card

    // Papers needed (same list as admin.php)
    $docs    = ['student_id' => 'Student ID', 'reg_form' => 'Registration Form'];
    $checked = array_intersect(array_keys($docs), (array)($_POST['docs'] ?? []));
    if (count($checked) !== count($docs)) {
        fail("Please check every paper: " . implode(' and ', $docs) . ".");
    }

    // The request must still be waiting
    $stmt = $conn->prepare("SELECT student_id FROM revalidations WHERE id = ? AND status = 'pending'");
    $stmt->bind_param("i", $rid);
    $stmt->execute();
    $sid = $stmt->get_result()->fetch_assoc()['student_id'] ?? null;
    if ($sid === null) {
        fail("This request is no longer waiting. It may have been confirmed or cancelled already.");
    }

    // Check the details (same rules as register.php)
    if ($first === '' || $last === '' || $level === '' || $grade === '' || $course === '' || $section === '') {
        fail("Please fill in every field (middle name is optional).");
    }
    foreach (["First name" => $first, "Middle name" => $middle, "Last name" => $last] as $label => $value) {
        if ($value !== '' && !preg_match('/^[\p{L}][\p{L} .\'-]*$/u', $value)) {
            fail("$label can only have letters, spaces, periods, apostrophes and dashes.");
        }
        if (mb_strlen($value) > 30) {
            fail("$label is too long (30 characters max).");
        }
    }
    if (!preg_match('/^[A-Za-z0-9 -]{1,10}$/', $section)) {
        fail("Section can only have letters, numbers, spaces and dashes (10 characters max).");
    }
    foreach (['grade' => $grade, 'course' => $course] as $kind => $value) {
        if (!exists($conn, "SELECT id FROM options WHERE kind = ? AND level = ? AND name = ?", "sss", $kind, $level, $value)) {
            fail("Please choose a valid $kind for $level.");
        }
    }
    if (exists($conn, "SELECT id FROM students WHERE first_name = ? AND last_name = ? AND student_id <> ?", "sss", $first, $last, $sid)) {
        fail("Another student named $first $last is already registered.");
    }

    // Card check. The scan must be from the last 5 minutes.
    $newUid = null;
    if ($newCard) {
        // Replacing a lost / broken card: the scanned card must not belong to anyone yet
        $stmt = $conn->prepare("SELECT uid FROM rfid_logs WHERE id = ? AND scanned_at >= (NOW() - INTERVAL 5 MINUTE)");
        $stmt->bind_param("i", $scanId);
        $stmt->execute();
        $newUid = $stmt->get_result()->fetch_assoc()['uid'] ?? null;
        if ($newUid === null || $newUid === '' || strlen($newUid) > 20) {
            fail("Please scan the new card again. The scan is missing or too old.");
        }
        if (exists($conn, "SELECT id FROM students WHERE rfid_uid = ?", "s", $newUid)
            || exists($conn, "SELECT id FROM teachers WHERE rfid_uid = ?", "s", $newUid)) {
            fail("That card is already registered. Use a new, unregistered card.");
        }
    } else {
        // Same card: the student's own card must have been scanned
        $stmt = $conn->prepare("SELECT l.uid FROM rfid_logs l
                                JOIN students s ON s.rfid_uid = l.uid
                                WHERE l.id = ? AND s.student_id = ?
                                  AND l.scanned_at >= (NOW() - INTERVAL 5 MINUTE)");
        $stmt->bind_param("is", $scanId, $sid);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            fail("Please scan this student's ID card again. The scan is missing, too old, or from another card.");
        }
    }

    // Save the details and close the request together
    $old  = $conn->query("SELECT name, level, grade, course, section FROM students WHERE student_id = '" . $conn->real_escape_string($sid) . "'")->fetch_assoc();
    $full = implode(' ', array_filter([$first, $middle, $last]));
    $list = implode(',', $checked);

    $conn->begin_transaction();

    $stmt = $conn->prepare("UPDATE students
                            SET first_name = ?, middle_name = ?, last_name = ?, name = ?,
                                level = ?, grade = ?, course = ?, section = ?
                            WHERE student_id = ?");
    $ok = $stmt && $stmt->bind_param("sssssssss", $first, $middle, $last, $full, $level, $grade, $course, $section, $sid) && $stmt->execute();
    if (!$ok) {
        $err = $stmt ? $stmt->errno : $conn->errno;
        $conn->rollback();
        fail($err == 1062 ? "That name is already registered." : "Could not save the details.");
    }

    if ($newUid !== null) {
        $stmt = $conn->prepare("UPDATE students SET rfid_uid = ? WHERE student_id = ?");
        $ok = $stmt && $stmt->bind_param("ss", $newUid, $sid) && $stmt->execute();
        if (!$ok) {
            $err = $stmt ? $stmt->errno : $conn->errno;
            $conn->rollback();
            fail($err == 1062 ? "That card is already registered." : "Could not save the new card.");
        }
    }

    $stmt = $conn->prepare("UPDATE revalidations SET status = 'confirmed', docs_checked = ?, confirmed_at = NOW()
                            WHERE id = ? AND status = 'pending'");
    $ok = $stmt && $stmt->bind_param("si", $list, $rid) && $stmt->execute() && $stmt->affected_rows > 0;
    if (!$ok) {
        $conn->rollback();
        fail("This request is no longer waiting.");
    }

    $conn->commit();

    // Audit log: say what changed
    $changes = [];
    foreach (['name' => $full, 'level' => $level, 'grade' => $grade, 'course' => $course, 'section' => $section] as $col => $new) {
        if (($old[$col] ?? '') !== $new) {
            $changes[] = "$col: " . (($old[$col] ?? '') !== '' ? $old[$col] : '(blank)') . " to $new";
        }
    }
    log_action($conn, 'Re-validation', "$sid confirmed, " . ($newCard ? 'new card registered (old card no longer works)' : 'card scanned') . ($changes ? '; ' . implode('; ', $changes) : '; no changes'));

    $_SESSION['flash'] = ['ok', "$full ($sid) re-validated. " . ($newCard ? 'New card registered; the old card no longer works' : 'Card verified') . ($changes ? '; details updated.' : '; details were already correct.')];

    echo json_encode(["success" => true]);
    exit;
}

fail("Unknown action.");