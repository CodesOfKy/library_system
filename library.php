<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Library Login</title>
  <style>
    body { font-family: Arial, sans-serif; max-width: 1200px; margin: 40px auto; }
    #card { padding: 30px; text-align: center; border-radius: 8px; background: #eee; font-size: 20px; }
    #card.ok { background: #d4edda; }
    #card.out { background: #cfe2ff; }
    #card.bad { background: #f8d7da; }
    #card h2 { margin: 0 0 10px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #999; padding: 8px; text-align: left; }

    .tables { display: flex; gap: 20px; flex-wrap: wrap; }
    .tables > div { flex: 1; min-width: 320px; }

    #visitors { margin-bottom: 20px; }
    #visitors h1 { margin-bottom: 5px; }
    #rangeLabel { color: #555; margin: 0 0 10px; }
    .filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .filters button { padding: 8px 14px; cursor: pointer; border: 1px solid #999; border-radius: 4px; background: #fff; }
    .filters button.active { background: #333; color: #fff; }
    .filters input[type="date"] { padding: 7px; }
  </style>
</head>
<body>
  <h1>Library Login</h1>

  <div id="visitors">
    <h1>Number Of Visitors: <span id="count">0</span></h1>
    <p id="rangeLabel"></p>
    <div class="filters">
      <button type="button" id="btnToday">Today</button>
      <button type="button" id="btnYesterday">Yesterday</button>
      <input type="date" id="dateInput">
      <button type="button" id="btnFilter">Filter</button>
    </div>
  </div>

  <div id="card">Tap your RFID card to log in or out</div>

  <div class="tables">
    <div>
      <h3>Recent Logins (IN)</h3>
      <table id="logsIn">
        <thead>
          <tr><th>Name</th><th>Student ID</th><th>Date</th><th>Time In</th></tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>

    <div>
      <h3>Recent Logouts (OUT)</h3>
      <table id="logsOut">
        <thead>
          <tr><th>Name</th><th>Student ID</th><th>Date</th><th>Time Out</th></tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

  <script>
    let lastId = null;
    let resetTimer = null;
    const card = document.getElementById("card");

    // ---------- Visitor count ----------
    let currentDate = null;   // date (YYYY-MM-DD) the count is showing
    let currentMode = "today";

    function fmt(d) {
      // local date, not UTC
      const y = d.getFullYear();
      const m = String(d.getMonth() + 1).padStart(2, "0");
      const day = String(d.getDate()).padStart(2, "0");
      return y + "-" + m + "-" + day;
    }

    function getToday() { return fmt(new Date()); }
    function getYesterday() {
      const d = new Date();
      d.setDate(d.getDate() - 1);
      return fmt(d);
    }

    function setActive(btnId) {
      ["btnToday", "btnYesterday", "btnFilter"].forEach(id => {
        document.getElementById(id).classList.toggle("active", id === btnId);
      });
    }

    async function loadCount() {
      // "Today" and "Yesterday" always follow the real date,
      // even if this page stays open past midnight
      if (currentMode === "today") {
        currentDate = getToday();
        document.getElementById("rangeLabel").textContent = "Showing: Today (" + currentDate + ")";
      } else if (currentMode === "yesterday") {
        currentDate = getYesterday();
        document.getElementById("rangeLabel").textContent = "Showing: Yesterday (" + currentDate + ")";
      }

      try {
        const res = await fetch(
          "api.php?action=count&date=" + encodeURIComponent(currentDate),
          { cache: "no-store" }
        );
        const data = await res.json();
        document.getElementById("count").textContent = data.count;
      } catch (e) {
        document.getElementById("count").textContent = "?";
      }
    }

    function showToday() {
      currentMode = "today";
      setActive("btnToday");
      loadCount();
    }

    function showYesterday() {
      currentMode = "yesterday";
      setActive("btnYesterday");
      loadCount();
    }

    function showSpecific() {
      const picked = document.getElementById("dateInput").value;
      if (!picked) { alert("Please pick a date first."); return; }
      currentMode = "specific";
      currentDate = picked;
      document.getElementById("rangeLabel").textContent = "Showing: " + picked;
      setActive("btnFilter");
      loadCount();
    }

    document.getElementById("btnToday").addEventListener("click", showToday);
    document.getElementById("btnYesterday").addEventListener("click", showYesterday);
    document.getElementById("btnFilter").addEventListener("click", showSpecific);

    // ---------- Scan card + tables ----------
    function showCard(html, cls) {
      card.className = cls;
      card.innerHTML = html;
      clearTimeout(resetTimer);
      resetTimer = setTimeout(() => {
        card.className = "";
        card.textContent = "Tap your RFID card to log in or out";
      }, 5000);
    }

    function esc(text) {
      const d = document.createElement("div");
      d.textContent = text;
      return d.innerHTML;
    }

    function fillTable(tableId, rows) {
      const tbody = document.querySelector("#" + tableId + " tbody");
      tbody.innerHTML = "";
      rows.forEach(r => {
        tbody.innerHTML += "<tr><td>" + esc(r.name) + "</td><td>" + esc(r.student_id) +
                           "</td><td>" + esc(r.d) + "</td><td>" + esc(r.t) + "</td></tr>";
      });
    }

    async function loadList() {
      try {
        const res = await fetch("api.php?action=list", { cache: "no-store" });
        const data = await res.json();
        fillTable("logsIn", data.in);
        fillTable("logsOut", data.out);
      } catch (e) {}
    }

    async function checkScan() {
      try {
        const res = await fetch("api.php?action=latest");
        const scan = await res.json();
        const id = Number(scan.id);

        if (lastId === null) { lastId = id; return; }
        if (id < lastId) lastId = 0;   // the scan table was emptied, so start over

        if (id > lastId) {
          lastId = id;
          const body = new URLSearchParams({ uid: scan.uid });
          const r = await fetch("api.php?action=login", { method: "POST", body: body });
          const data = await r.json();

          if (data.found) {
            const isIn = data.action === "in";
            showCard(
              "<h2>" + (isIn ? "Welcome, " : "Goodbye, ") + esc(data.name) + "!</h2>" +
              "<b>" + (isIn ? "LOGGED IN" : "LOGGED OUT") + "</b><br>" +
              "Student ID: " + esc(data.student_id) + "<br>" +
              "Date: " + esc(data.date) + "<br>" +
              "Time: " + esc(data.time),
              isIn ? "ok" : "out"
            );
            loadList();
            loadCount();   // refresh the visitor number after a new login
          } else {
            showCard(
              'Card not registered. Please register first.<br><br>' +
              '<a href="register.php"><button type="button">GO TO REGISTER</button></a>',
              "bad"
            );
          }
        }
      } catch (e) {
        card.textContent = "Cannot reach api.php";
      }
    }

    showToday();   // default view
    loadList();
    setInterval(checkScan, 500);

    // refresh the number and both tables every 2 seconds
    setInterval(() => {
      loadCount();
      loadList();
    }, 2000);

    checkScan();
  </script>
</body>
</html>