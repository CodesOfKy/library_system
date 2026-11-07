<?php
$conn = new mysqli("localhost", "root", "", "arduino_db");
$result = $conn->query("SELECT * FROM rfid_logs ORDER BY id DESC LIMIT 20");
?>
<meta http-equiv="refresh" content="2">
<h2>RFID Logs</h2>
<table border="1" cellpadding="6">
  <tr><th>ID</th><th>UID</th><th>Time</th></tr>
  <?php while ($row = $result->fetch_assoc()): ?>
  <tr>
    <td><?= $row['id'] ?></td>
    <td><?= htmlspecialchars($row['uid']) ?></td>
    <td><?= $row['scanned_at'] ?></td>
  </tr>
  <?php endwhile; ?>
</table>