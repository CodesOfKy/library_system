<?php
// Writes one line to the audit log. Call it after any edit or delete.
function log_action($conn, $action, $details) {
    $email   = $_SESSION['admin_email'] ?? 'unknown';
    $details = mb_substr($details, 0, 255);

    $stmt = $conn->prepare("INSERT INTO audit_log (admin_email, action, details) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $email, $action, $details);
    $stmt->execute();
}