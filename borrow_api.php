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

// Newest scan + what we know about that card
if ($action === 'latest') {
    $row = $conn->query("SELECT id, uid FROM rfid_logs ORDER BY id DESC LIMIT 1")->fetch_assoc();
    if (!$row) {
        echo json_encode(["id" => 0, "uid" => ""]);
        exit;
    }
    $uid = $row['uid'];

    // Is this card a student?
    $stmt = $conn->prepare("SELECT student_id FROM students WHERE rfid_uid = ?");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $isStudent = (bool)$stmt->get_result()->fetch_assoc();

    // Is this card a registered teacher?
    $stmt = $conn->prepare("SELECT id, name FROM teachers WHERE rfid_uid = ?");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $teacher = $stmt->get_result()->fetch_assoc();

    // Open loan (book not yet returned)
    $loan = null;
    if ($teacher) {
        $stmt = $conn->prepare("
            SELECT id, book_title,
                   DATE_FORMAT(due_date, '%b %d, %Y') AS due_text,
                   (due_date < CURDATE()) AS overdue
            FROM book_loans
            WHERE teacher_id = ? AND returned_at IS NULL
            ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("i", $teacher['id']);
        $stmt->execute();
        $loan = $stmt->get_result()->fetch_assoc();
        if ($loan) {
            $loan['id'] = (int)$loan['id'];
            $loan['overdue'] = (bool)$loan['overdue'];
        }
    }

    echo json_encode([
        "id"           => (int)$row['id'],
        "uid"          => $uid,
        "is_student"   => $isStudent,
        "registered"   => (bool)$teacher,
        "teacher_name" => $teacher['name'] ?? "",
        "loan"         => $loan
    ]);
    exit;
}

// Borrow: registers the teacher first if the card is new
if ($action === 'borrow') {
    $uid  = trim($_POST['uid'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $book = trim($_POST['book_title'] ?? '');
    $due  = $_POST['due_date'] ?? '';

    if ($uid === '' || $book === '') {
        fail("Book name is required.");
    }
    if (strlen($book) > 200 || strlen($name) > 100) {
        fail("Name or book title is too long.");
    }
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $due, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        fail("Enter a valid return date.");
    }

    // Return date must be today or later (compared by MySQL, like the rest of the app)
    $stmt = $conn->prepare("SELECT (? >= CURDATE()) AS ok");
    $stmt->bind_param("s", $due);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()['ok']) {
        fail("Return date must be today or later.");
    }

    // A student card can't be used here
    $stmt = $conn->prepare("SELECT student_id FROM students WHERE rfid_uid = ?");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        fail("This card belongs to a student.");
    }

    // Find or create the teacher
    $stmt = $conn->prepare("SELECT id, name FROM teachers WHERE rfid_uid = ?");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $teacher = $stmt->get_result()->fetch_assoc();

    if ($teacher) {
        $teacherId = (int)$teacher['id'];
        $teacherName = $teacher['name'];
    } else {
        if ($name === '') {
            fail("Teacher name is required for a new card.");
        }
        $stmt = $conn->prepare("INSERT INTO teachers (name, rfid_uid) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $uid);
        if (!$stmt->execute()) {
            fail("Could not register the teacher.");
        }
        $teacherId = (int)$conn->insert_id;
        $teacherName = $name;
    }

    // One active book at a time
    $stmt = $conn->prepare("SELECT id FROM book_loans WHERE teacher_id = ? AND returned_at IS NULL LIMIT 1");
    $stmt->bind_param("i", $teacherId);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        fail("This teacher still has an unreturned book. Scan the card to return it first.");
    }

    $stmt = $conn->prepare("INSERT INTO book_loans (teacher_id, book_title, due_date) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $teacherId, $book, $due);
    if (!$stmt->execute()) {
        fail("Could not save the loan.");
    }

    echo json_encode([
        "success" => true,
        "message" => "$teacherName borrowed \"$book\". Due on $due."
    ]);
    exit;
}

// Return a book
if ($action === 'return') {
    $loanId = (int)($_POST['loan_id'] ?? 0);

    $stmt = $conn->prepare("UPDATE book_loans SET returned_at = NOW() WHERE id = ? AND returned_at IS NULL");
    $stmt->bind_param("i", $loanId);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(["success" => true]);
    } else {
        fail("Already returned or not found.");
    }
    exit;
}

// Loan records + summary counts
if ($action === 'list') {
    $loans = $conn->query("
        SELECT l.id, t.name, l.book_title,
               DATE_FORMAT(l.borrowed_at, '%b %d, %Y %h:%i %p') AS borrowed_text,
               DATE_FORMAT(l.due_date, '%b %d, %Y') AS due_text,
               DATE_FORMAT(l.returned_at, '%b %d, %Y %h:%i %p') AS returned_text,
               CASE WHEN l.returned_at IS NOT NULL THEN 'returned'
                    WHEN l.due_date < CURDATE() THEN 'overdue'
                    ELSE 'borrowed' END AS status
        FROM book_loans l
        JOIN teachers t ON t.id = l.teacher_id
        ORDER BY l.id DESC
        LIMIT 100
    ")->fetch_all(MYSQLI_ASSOC);

    $s = $conn->query("
        SELECT SUM(returned_at IS NULL) AS borrowed,
               SUM(returned_at IS NULL AND due_date < CURDATE()) AS overdue
        FROM book_loans
    ")->fetch_assoc();

    echo json_encode([
        "loans" => $loans,
        "stats" => ["borrowed" => (int)$s['borrowed'], "overdue" => (int)$s['overdue']]
    ]);
    exit;
}

fail("Unknown action");