<?php
header("Content-Type: application/json");
mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli("localhost", "root", "", "arduino_db");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}
$conn->set_charset("utf8mb4");

$action = $_GET['action'] ?? '';

// All genres and the shelf each one belongs to
if ($action === 'genres') {
    $rows = $conn->query("SELECT id, name, shelf_name FROM genres ORDER BY id")
                 ->fetch_all(MYSQLI_ASSOC);
    echo json_encode($rows);
    exit;
}

// One shelf: its genre and the books on it
if ($action === 'shelf') {
    $shelf = $_GET['name'] ?? '';

    $stmt = $conn->prepare("SELECT id, name FROM genres WHERE shelf_name = ?");
    $stmt->bind_param("s", $shelf);
    $stmt->execute();
    $g = $stmt->get_result()->fetch_assoc();

    if (!$g) {
        echo json_encode(new stdClass);   // empty {}
        exit;
    }

    $stmt = $conn->prepare("SELECT title, author FROM books WHERE genre_id = ? ORDER BY title");
    $stmt->bind_param("i", $g['id']);
    $stmt->execute();
    $books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(["genre" => $g['name'], "books" => $books]);
    exit;
}

// Search by book title, author, or genre
if ($action === 'search') {
    $q = trim($_GET['q'] ?? '');
    $like = "%" . $q . "%";

    $stmt = $conn->prepare("SELECT b.title, b.author, g.name AS genre, g.shelf_name
                            FROM books b
                            JOIN genres g ON g.id = b.genre_id
                            WHERE b.title LIKE ? OR b.author LIKE ? OR g.name LIKE ?
                            ORDER BY b.title LIMIT 10");
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    exit;
}

echo json_encode(["error" => "Unknown action"]);