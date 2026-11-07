<?php
set_time_limit(0);

$port = "COM6"; // change this to your arduino port

exec("mode $port: BAUD=9600 PARITY=N data=8 stop=1 xon=off");
$serial = fopen("\\\\.\\" . $port, "r");
if (!$serial) {
    die("Cannot open $port. Close the Serial Monitor and check the port.\n");
}
sleep(2); // Arduino restarts when the port opens

$conn = new mysqli("localhost", "root", "", "arduino_db");
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error . "\n");
}

echo "Waiting for cards...\n";

while (true) {
    $line = trim(fgets($serial));
    if ($line !== "" && ctype_xdigit($line)) {
        $stmt = $conn->prepare("INSERT INTO rfid_logs (uid) VALUES (?)");
        $stmt->bind_param("s", $line);
        $stmt->execute();
        echo "Saved UID: $line\n";
    }
}