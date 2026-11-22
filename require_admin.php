<?php
// Include at the very top of any admin-only file.
// Pages redirect to login.php. API files: define('ADMIN_API', true) before including to get a 401 JSON reply instead.
const IDLE_LIMIT = 30 * 60;                 // seconds of inactivity before auto log out

if (session_status() === PHP_SESSION_NONE) session_start();

$expired = isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > IDLE_LIMIT;
if ($expired) session_unset();

if (!isset($_SESSION['admin_id'])) {
    if (defined('ADMIN_API')) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Please log in.']);
    } else {
        header('Location: login.php');
    }
    exit;
}

$_SESSION['last_seen'] = time();
header('Cache-Control: no-store');          // Back button after logout won't show the old page