<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Library Login</title>
  <link rel="stylesheet" href="design.css">
</head>
<body class="library">
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

  <!-- Popup: scanned again within 3 minutes of logging in -->
  <div id="cancelModal" class="modal" hidden>
    <div class="modal-box">
      <h2 id="cancelTitle"></h2>
      <p id="cancelText"></p>
      <div class="modal-actions">
        <button type="button" id="btnCancelYes">Yes, cancel my log in</button>
        <button type="button" id="btnCancelNo">No, keep me logged in</button>
      </div>
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
      loadList();
    }

    function showYesterday() {
      currentMode = "yesterday";
      setActive("btnYesterday");
      loadCount();
      loadList();
    }

    function showSpecific() {
      const picked = document.getElementById("dateInput").value;
      if (!picked) { alert("Please pick a date first."); return; }
      currentMode = "specific";
      currentDate = picked;
      document.getElementById("rangeLabel").textContent = "Showing: " + picked;
      setActive("btnFilter");
      loadCount();
      loadList();
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

    // The date the page is currently showing (follows the real date for Today/Yesterday)
    function viewDate() {
      if (currentMode === "today") return getToday();
      if (currentMode === "yesterday") return getYesterday();
      return currentDate;
    }

    // true if a row's date value falls on the given YYYY-MM-DD day
    function sameDay(value, target) {
      const s = String(value || "");
      if (/^\d{4}-\d{2}-\d{2}/.test(s)) return s.slice(0, 10) === target;
      const p = new Date(s);
      return !isNaN(p) && fmt(p) === target;
    }

    async function loadList() {
      try {
        const day = viewDate();
        const res = await fetch("api.php?action=list&date=" + encodeURIComponent(day), { cache: "no-store" });
        const data = await res.json();
        // only keep rows from the day being shown
        fillTable("logsIn", data.in.filter(r => sameDay(r.d, day)));
        fillTable("logsOut", data.out.filter(r => sameDay(r.d, day)));
      } catch (e) {}
    }

    // ---------- Cancel login popup ----------
    let pendingUid = null;
    let modalTimer = null;

    function hideCancelModal() {
      document.getElementById("cancelModal").hidden = true;
      pendingUid = null;
      clearTimeout(modalTimer);
    }

    function showCancelModal(data, uid) {
      pendingUid = uid;
      document.getElementById("cancelTitle").textContent = "Hi, " + data.name + "!";
      document.getElementById("cancelText").textContent =
        "You logged in " + data.seconds_ago + " seconds ago. Do you want to cancel your log in?";
      document.getElementById("cancelModal").hidden = false;
      clearTimeout(modalTimer);
      modalTimer = setTimeout(hideCancelModal, 15000);   // no answer = stay logged in
    }

    async function confirmCancel() {
      const uid = pendingUid;
      hideCancelModal();
      if (!uid) return;
      try {
        const body = new URLSearchParams({ uid: uid });
        const r = await fetch("api.php?action=cancel_login", { method: "POST", body: body });
        const data = await r.json();
        if (data.cancelled) {
          showCard("<h2>Log in cancelled</h2>" + esc(data.name) + " (" + esc(data.student_id) + ")", "out");
          loadList();
          loadCount();
        } else {
          showCard("Could not cancel. The 3 minutes may have passed.", "bad");
        }
      } catch (e) {
        card.textContent = "Cannot reach api.php";
      }
    }

    document.getElementById("btnCancelYes").addEventListener("click", confirmCancel);
    document.getElementById("btnCancelNo").addEventListener("click", hideCancelModal);

    async function checkScan() {
      try {
        const res = await fetch("api.php?action=latest");
        const scan = await res.json();
        const id = Number(scan.id);

        if (lastId === null) { lastId = id; return; }
        if (id < lastId) lastId = 0;   // the scan table was emptied, so start over

        if (id > lastId) {
          lastId = id;
          hideCancelModal();   // a new scan closes any old popup
          const body = new URLSearchParams({ uid: scan.uid });
          const r = await fetch("api.php?action=login", { method: "POST", body: body });
          const data = await r.json();

          if (data.found && data.action === "confirm_cancel") {
            showCancelModal(data, scan.uid);
          } else if (data.found) {
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