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

    // Open loans (books not yet returned), newest first
    $loans = [];
    if ($teacher) {
        $stmt = $conn->prepare("
            SELECT id, book_title,
                   DATE_FORMAT(due_date, '%b %d, %Y') AS due_text,
                   (due_date < CURDATE()) AS overdue
            FROM book_loans
            WHERE teacher_id = ? AND returned_at IS NULL
            ORDER BY id DESC");
        $stmt->bind_param("i", $teacher['id']);
        $stmt->execute();
        $loans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($loans as &$l) {
            $l['id'] = (int)$l['id'];
            $l['overdue'] = (bool)$l['overdue'];
        }
        unset($l);
    }

    echo json_encode([
        "id"           => (int)$row['id'],
        "uid"          => $uid,
        "is_student"   => $isStudent,
        "registered"   => (bool)$teacher,
        "teacher_name" => $teacher['name'] ?? "",
        "loans"        => $loans
    ]);
    exit;
}

// Borrow: registers the teacher first if the card is new
if ($action === 'borrow') {
    $uid  = trim($_POST['uid'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $books = $_POST['book_title'] ?? [];          // one or more book titles
    if (!is_array($books)) $books = [$books];
    $books = array_values(array_filter(array_map('trim', $books), fn($b) => $b !== ''));
    $due  = $_POST['due_date'] ?? '';

    if ($uid === '' || !$books) {
        fail("Book name is required.");
    }
    if (count($books) > 10) {
        fail("You can borrow up to 10 books at a time.");
    }
    foreach ($books as $b) {
        if (strlen($b) > 200) fail("Book title is too long.");
    }
    if (strlen($name) > 100) {
        fail("Name is too long.");
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

    // Save every book (all or nothing)
    $conn->begin_transaction();
    $stmt = $conn->prepare("INSERT INTO book_loans (teacher_id, book_title, due_date) VALUES (?, ?, ?)");
    foreach ($books as $book) {
        $stmt->bind_param("iss", $teacherId, $book, $due);
        if (!$stmt->execute()) {
            $conn->rollback();
            fail("Could not save the loan.");
        }
    }
    $conn->commit();

    $what = count($books) === 1 ? "\"{$books[0]}\"" : count($books) . " books";
    echo json_encode([
        "success" => true,
        "message" => "$teacherName borrowed $what. Due on $due."
    ]);
    exit;
}

// Return one or more books
if ($action === 'return') {
    $ids = $_POST['loan_ids'] ?? ($_POST['loan_id'] ?? []);
    if (!is_array($ids)) $ids = [$ids];
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

    if (!$ids) {
        fail("No book selected.");
    }

    $stmt = $conn->prepare("UPDATE book_loans SET returned_at = NOW() WHERE id = ? AND returned_at IS NULL");
    $returned = 0;
    foreach ($ids as $loanId) {
        $stmt->bind_param("i", $loanId);
        $stmt->execute();
        if ($stmt->affected_rows > 0) $returned++;
    }

    if ($returned > 0) {
        echo json_encode(["success" => true, "returned" => $returned]);
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
               DATEDIFF(l.due_date, DATE(l.borrowed_at)) AS days,
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