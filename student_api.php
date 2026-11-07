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

$conn = new mysqli("localhost", "root", "", "arduino_db");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

function fail($msg) {
    echo json_encode(["success" => false, "message" => $msg]);
    exit;
}

$action = $_GET['action'] ?? '';

// Edit a student's name and RFID UID
if ($action === 'update') {
    $sid  = trim($_POST['student_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $uid  = trim($_POST['rfid_uid'] ?? '');

    if ($sid === '' || $name === '' || $uid === '') {
        fail("Name and RFID UID are required.");
    }
    if (strlen($name) > 100 || strlen($uid) > 50) {
        fail("Name or RFID UID is too long.");
    }

    // Student must exist
    $stmt = $conn->prepare("SELECT student_id FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $sid);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        fail("Student not found.");
    }

    // RFID UID must not belong to another student
    $stmt = $conn->prepare("SELECT student_id FROM students WHERE rfid_uid = ? AND student_id <> ?");
    $stmt->bind_param("ss", $uid, $sid);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        fail("That RFID UID is already used by another student.");
    }

    // ...or by a teacher (teachers table comes from the Borrow & Return module)
    if ($stmt = $conn->prepare("SELECT id FROM teachers WHERE rfid_uid = ?")) {
        $stmt->bind_param("s", $uid);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            fail("That RFID UID is already used by a teacher.");
        }
    }

    $stmt = $conn->prepare("UPDATE students SET name = ?, rfid_uid = ? WHERE student_id = ?");
    $stmt->bind_param("sss", $name, $uid, $sid);
    if (!$stmt->execute()) {
        fail("Could not update the student.");
    }

    echo json_encode(["success" => true, "message" => "Student updated."]);
    exit;
}

// Delete a student and their history
if ($action === 'delete') {
    $sid = trim($_POST['student_id'] ?? '');
    if ($sid === '') {
        fail("Missing student ID.");
    }

    $stmt = $conn->prepare("SELECT student_id FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $sid);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        fail("Student not found.");
    }

    function run($conn, $sql, $sid) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param("s", $sid);
        return $stmt->execute();
    }

    $conn->begin_transaction();
    $ok = run($conn, "DELETE FROM library_in WHERE student_id = ?", $sid)
       && run($conn, "DELETE FROM library_out WHERE student_id = ?", $sid)
       && run($conn, "DELETE FROM student_status WHERE student_id = ?", $sid)
       && run($conn, "DELETE FROM students WHERE student_id = ?", $sid);

    if ($ok) {
        $conn->commit();
        echo json_encode(["success" => true, "message" => "Student deleted."]);
    } else {
        $conn->rollback();
        fail("Could not delete the student.");
    }
    exit;
}

fail("Unknown action");