<?php
session_start();
header("Content-Type: application/json");
mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli("localhost", "root", "", "arduino_db");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}
require 'audit.php';

$action = $_GET['action'] ?? '';

// Failed logins have no session yet, so write the row directly with the typed email
function log_failed_login($conn, $email, $why) {
    $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $details = "$why | IP $ip";
    $stmt = $conn->prepare("INSERT INTO audit_log (admin_email, action, details) VALUES (?, 'Failed login', ?)");
    $stmt->bind_param("ss", $email, $details);
    $stmt->execute();
}

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

        // Session is set first, so log_action can read the admin email
        log_action($conn, 'Admin login', 'Logged in from IP ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

        echo json_encode(["success" => true, "redirect" => "admin.php"]);
    } else {
        log_failed_login($conn, $email, $row ? 'Wrong password' : 'Unknown email');
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
    // Log BEFORE destroying the session, because the email is stored in it
    if (isset($_SESSION['admin_id'])) {
        log_action($conn, 'Admin logout', 'Logged out');
    }
    session_destroy();
    echo json_encode(["success" => true, "redirect" => "login.php"]);
    exit;
}

echo json_encode(["success" => false, "message" => "Unknown action"]);