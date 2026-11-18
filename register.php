<?php
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli("localhost", "root", "", "arduino_db");

$errors = [];
$success = "";

// Values kept in the form after an error
$first = $middle = $last = $student_id = $level = $grade = $course = $section = "";

// True if the query finds at least one row
function exists($conn, $sql, $types, ...$params) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Trim and turn repeated spaces into one
function clean($text) {
    return preg_replace('/\s+/', ' ', trim($text));
}

if ($conn->connect_error) {
    $errors[] = "Cannot connect to the database. Please try again later.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first      = clean($_POST['first_name'] ?? '');
    $middle     = clean($_POST['middle_name'] ?? '');
    $last       = clean($_POST['last_name'] ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $uid        = trim($_POST['rfid_uid'] ?? '');
    $level      = $_POST['level'] ?? '';
    $grade      = $_POST['grade'] ?? '';
    $course     = $_POST['course'] ?? '';
    $section    = clean($_POST['section'] ?? '');

    // 1. Check the form itself
    if ($first === '' || $last === '' || $student_id === '' || $uid === '' ||
        $level === '' || $grade === '' || $course === '' || $section === '') {
        $errors[] = "Please fill in all fields and tap your card.";
    }

    foreach (["First name" => $first, "Middle name" => $middle, "Last name" => $last] as $label => $value) {
        if ($value !== '' && !preg_match('/^[\p{L}][\p{L} .\'-]*$/u', $value)) {
            $errors[] = "$label can only have letters, spaces, periods, apostrophes and dashes.";
        } elseif (mb_strlen($value) > 30) {
            $errors[] = "$label is too long (30 characters max).";
        }
    }

    if ($student_id !== '' && !preg_match('/^[A-Za-z0-9-]{1,20}$/', $student_id)) {
        $errors[] = "Student number can only have letters, numbers and dashes (20 characters max).";
    }

    if ($section !== '' && !preg_match('/^[A-Za-z0-9 -]{1,10}$/', $section)) {
        $errors[] = "Section can only have letters, numbers, spaces and dashes (10 characters max).";
    }

    // Grade and course must exist in the options table for the chosen level
    foreach (['grade' => $grade, 'course' => $course] as $kind => $value) {
        if ($value !== '' && !exists($conn, "SELECT id FROM options WHERE kind = ? AND level = ? AND name = ?", "sss", $kind, $level, $value)) {
            $errors[] = "Please choose a valid $kind for $level.";
        }
    }

    if (strlen($uid) > 20) {
        $errors[] = "Invalid card. Please tap your card again.";
    }

    // 2. Check for duplicates (only if the form is fine so far)
    if (!$errors) {
        if (exists($conn, "SELECT id FROM students WHERE student_id = ?", "s", $student_id)) {
            $errors[] = "This student number is already registered.";
        }
        if (exists($conn, "SELECT id FROM students WHERE first_name = ? AND last_name = ?", "ss", $first, $last)) {
            $errors[] = "A student named $first $last is already registered.";
        }
        if (exists($conn, "SELECT id FROM students WHERE rfid_uid = ?", "s", $uid)) {
            $errors[] = "This RFID card is already registered.";
        }
    }

    // 3. Save
    if (!$errors) {
        $full = implode(' ', array_filter([$first, $middle, $last]));   // the "name" column

        $stmt = $conn->prepare("INSERT INTO students (student_id, first_name, middle_name, last_name, name, rfid_uid, level, grade, course, section)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            $errors[] = "Something went wrong: " . $conn->error;
        } else {
            $stmt->bind_param("ssssssssss", $student_id, $first, $middle, $last, $full, $uid, $level, $grade, $course, $section);

            if ($stmt->execute()) {
                $success = "Registered successfully, " . $full . "!";
                $first = $middle = $last = $student_id = $level = $grade = $course = $section = "";   // clear the form
            } elseif ($stmt->errno == 1062) {
                $errors[] = "This student, student number, or RFID card is already registered.";
            } else {
                $errors[] = "Something went wrong: " . $stmt->error;
            }
        }
    }
}

// Dropdown choices (edited from the dashboard)
$opts = $conn->connect_error ? [] : $conn->query("SELECT kind, level, name FROM options ORDER BY id")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Register Student</title>
  <link rel="stylesheet" href="design.css">
</head>
<body class="register">
  <h2>Register Student</h2>

  <?php if ($success): ?>
    <p class="msg ok"><?= htmlspecialchars($success) ?></p>
  <?php endif; ?>

  <?php foreach ($errors as $error): ?>
    <p class="msg bad"><?= htmlspecialchars($error) ?></p>
  <?php endforeach; ?>

  <form method="POST">
    <label>First Name</label>
    <input type="text" name="first_name" minlength="2" maxlength="30" value="<?= htmlspecialchars($first) ?>" required>

    <label>Middle Name</label>
    <input type="text" name="middle_name" maxlength="30" value="<?= htmlspecialchars($middle) ?>">

    <label>Last Name</label>
    <input type="text" name="last_name" minlength="2" maxlength="30" value="<?= htmlspecialchars($last) ?>" required>

    <label>Student Number</label>
    <input type="text" name="student_id" maxlength="20" value="<?= htmlspecialchars($student_id) ?>" required>

    <label>School Level</label>
    <label><input type="radio" name="level" value="SHS"     <?= $level === 'SHS'     ? 'checked' : '' ?> required> SHS</label>
    <label><input type="radio" name="level" value="College" <?= $level === 'College' ? 'checked' : '' ?>> College</label>

    <label>Grade / Year</label>
    <select name="grade" id="grade" required></select>

    <label>Course</label>
    <select name="course" id="course" required></select>

    <label>Section</label>
    <input type="text" name="section" maxlength="10" value="<?= htmlspecialchars($section) ?>" required>

    <label>RFID Card</label>
    <input type="hidden" name="rfid_uid" id="uid_real">
    <input type="text" id="uid" placeholder="Tap your card on the reader..." readonly required>
    <p id="status">Waiting for card...</p>

    <button type="submit">Submit</button>
  </form>
  <br><br><br><br>
  <a href="library.php"><button>GO TO LIBRARY</button></a>

  <script>
    // ---- Grade / course dropdowns follow the SHS / College choice ----
    const OPTIONS = <?= json_encode($opts) ?>;
    const chosen  = { grade: <?= json_encode($grade) ?>, course: <?= json_encode($course) ?> };

    function fillDropdowns() {
      const level = document.querySelector('input[name=level]:checked')?.value;
      for (const kind of ['grade', 'course']) {
        document.getElementById(kind).replaceChildren(
          new Option('Select...', ''),
          ...OPTIONS.filter(o => o.kind === kind && o.level === level)
                    .map(o => new Option(o.name, o.name, false, o.name === chosen[kind]))
        );
      }
    }
    document.querySelectorAll('input[name=level]').forEach(r =>
      r.addEventListener('change', () => { chosen.grade = chosen.course = ''; fillDropdowns(); }));
    fillDropdowns();

    // ---- RFID scan ----
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
          document.getElementById("uid_real").value = scan.uid;   // real UID that gets submitted
          document.getElementById("uid").value = scan.registered
            ? "*".repeat(String(scan.uid).length)   // already registered: hide it
            : scan.uid;
          document.getElementById("status").textContent = scan.registered
            ? "Warning: this card is already registered."
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