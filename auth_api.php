<?php
session_start();
header("Content-Type: application/json");
mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli("localhost", "root", "", "arduino_db");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

$action = $_GET['action'] ?? '';

// Admin login
if ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        echo json_encode(["success" => false, "message" => "Email and password are required."]);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, password FROM admins WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row && password_verify($password, $row['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $row['id'];
        $_SESSION['admin_email'] = $email;
        echo json_encode(["success" => true, "redirect" => "admin.php"]);
    } else {
        echo json_encode(["success" => false, "message" => "Invalid email or password."]);
    }
    exit;
}

// Is the admin logged in? (use this from your admin pages)
if ($action === 'check') {
    echo json_encode([
        "loggedIn" => isset($_SESSION['admin_id']),
        "email" => $_SESSION['admin_email'] ?? ""
    ]);
    exit;
}

// Logout
if ($action === 'logout') {
    session_destroy();
    echo json_encode(["success" => true, "redirect" => "login.php"]);
    exit;
}

echo json_encode(["success" => false, "message" => "Unknown action"]);