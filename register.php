<?php
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli("localhost", "root", "", "arduino_db");
$message = "";
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $uid = trim($_POST['rfid_uid'] ?? '');

    if ($name === '' || $student_id === '' || $uid === '') {
        $message = "Please fill in your name, student ID, and tap your card.";
    } else {
        $stmt = $conn->prepare("INSERT INTO students (student_id, name, rfid_uid) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $student_id, $name, $uid);

        if ($stmt->execute()) {
            $message = "Registered successfully, " . $name . "!";
            $success = true;
        } elseif ($stmt->errno == 1062) {
            $message = "This student ID or RFID card is already registered.";
        } else {
            $message = "Something went wrong: " . $stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Register Student</title>
  <style>
    body { font-family: Arial, sans-serif; max-width: 420px; margin: 40px auto; }
    label { display: block; margin-top: 14px; font-weight: bold; }
    input { width: 100%; padding: 10px; margin-top: 4px; box-sizing: border-box; font-size: 16px; }
    #uid { background: #f0f0f0; }
    button { margin-top: 20px; padding: 12px; width: 100%; font-size: 16px; cursor: pointer; }
    .msg { padding: 10px; border-radius: 4px; }
    .ok { background: #d4edda; }
    .bad { background: #f8d7da; }
    #status { font-size: 14px; color: #555; }
  </style>
</head>
<body>
  <h2>Register Student</h2>

  <?php if ($message): ?>
    <p class="msg <?= $success ? 'ok' : 'bad' ?>"><?= htmlspecialchars($message) ?></p>
  <?php endif; ?>

  <form method="POST">
    <label>Full Name</label>
    <input type="text" name="name" required>

    <label>Student ID</label>
    <input type="text" name="student_id" required>

    <label>RFID Card</label>
    <input type="text" name="rfid_uid" id="uid" placeholder="Tap your card on the reader..." readonly required>
    <p id="status">Waiting for card...</p>

    <button type="submit">Submit</button>




  </form>
      <br><br><br><br>
      <a href='library.php'><button>GO TO LIBRARY</button></a>;

  <script>
    let lastId = null;

    async function checkScan() {
      try {
        const res = await fetch("api.php?action=latest");
        const scan = await res.json();
        const id = Number(scan.id);

        // First check only remembers the current newest scan, so old scans are ignored
        if (lastId === null) { lastId = id; return; }

        if (id > lastId) {
          lastId = id;
          document.getElementById("uid").value = scan.uid;
          document.getElementById("status").textContent = scan.registered
            ? "Warning: this card is already registered to " + scan.name + "."
            : "Card detected: " + scan.uid;
        }
      } catch (e) {
        document.getElementById("status").textContent = "Cannot reach api.php";
      }
    }

    setInterval(checkScan, 500);
    checkScan();
  </script>
</body>
</html>