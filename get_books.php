<?php
// Put this at: xampp/htdocs/library/get_books.php
// Godot can't talk to MariaDB directly, so it asks this file over HTTP.
// Test in a browser: http://127.0.0.1/library/get_books.php?shelf=Shelf_Fiction

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// default XAMPP login (change if you set a password)
$db = new mysqli("127.0.0.1", "root", "", "arduino_db");
$db->set_charset("utf8mb4");

$shelf = $_GET["shelf"] ?? "";

$stmt = $db->prepare(
    "SELECT b.title, b.author
     FROM books b
     JOIN genres g ON g.id = b.genre_id
     WHERE g.shelf_name = ?"
);
$stmt->bind_param("s", $shelf);
$stmt->execute();

echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
