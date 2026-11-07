<?php
session_start();
header("Content-Type: application/json");
mysqli_report(MYSQLI_REPORT_OFF);

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
// Edit a student
// ---------------------------------------------------------
if ($action === 'update') {
    $sid    = trim($_POST['student_id'] ?? '');
    $first  = clean($_POST['first_name'] ?? '');
    $middle = clean($_POST['middle_name'] ?? '');
    $last   = clean($_POST['last_name'] ?? '');
    $uid    = trim($_POST['rfid_uid'] ?? '');

    if ($sid === '' || $first === '' || $last === '' || $uid === '') {
        fail("Please fill in the first name, last name, and RFID UID.");
    }

    foreach (["First name" => $first, "Middle name" => $middle, "Last name" => $last] as $label => $value) {
        if ($value !== '' && !preg_match('/^[\p{L}][\p{L} .\'-]*$/u', $value)) {
            fail("$label can only have letters, spaces, periods, apostrophes and dashes.");
        }
        if (mb_strlen($value) > 30) {
            fail("$label is too long (30 characters max).");
        }
    }
    if (strlen($uid) > 20) {
        fail("RFID UID is too long (20 characters max).");
    }

    // The student must exist
    if (!exists($conn, "SELECT id FROM students WHERE student_id = ?", "s", $sid)) {
        fail("Student not found. It may have been deleted already.");
    }

    // No one else may have the same first + last name or the same card
    if (exists($conn, "SELECT id FROM students WHERE first_name = ? AND last_name = ? AND student_id <> ?", "sss", $first, $last, $sid)) {
        fail("Another student named $first $last is already registered.");
    }
    if (exists($conn, "SELECT id FROM students WHERE rfid_uid = ? AND student_id <> ?", "ss", $uid, $sid)) {
        fail("This RFID card is already registered to another student.");
    }

    $full = implode(' ', array_filter([$first, $middle, $last]));   // the "name" column

    $stmt = $conn->prepare("UPDATE students
                            SET first_name = ?, middle_name = ?, last_name = ?, name = ?, rfid_uid = ?
                            WHERE student_id = ?");
    if (!$stmt) {
        fail("Something went wrong: " . $conn->error);
    }
    $stmt->bind_param("ssssss", $first, $middle, $last, $full, $uid, $sid);

    if (!$stmt->execute()) {
        if ($stmt->errno == 1062) {
            fail("That name or RFID card is already registered.");
        }
        fail("Something went wrong: " . $stmt->error);
    }

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
    if (!exists($conn, "SELECT id FROM students WHERE student_id = ?", "s", $sid)) {
        fail("Student not found. It may have been deleted already.");
    }

    // History first, then the student - all or nothing
    $conn->begin_transaction();

    foreach (["library_in", "library_out", "student_status", "students"] as $table) {
        $stmt = $conn->prepare("DELETE FROM $table WHERE student_id = ?");
        if (!$stmt || !$stmt->bind_param("s", $sid) || !$stmt->execute()) {
            $conn->rollback();
            fail("Could not delete (" . $table . "): " . $conn->error);
        }
    }

    $conn->commit();
    echo json_encode(["success" => true]);
    exit;
}

fail("Unknown action.");
