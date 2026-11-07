<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Borrow &amp; Return</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <header>
        <h2>Teacher Borrow &amp; Return</h2>
        <div>
            <a href="admin.php" class="btn-link">&larr; Dashboard</a>
            <span><?= htmlspecialchars($_SESSION['admin_email']) ?></span>
            <button id="logoutBtn">Log Out</button>
        </div>
    </header>

    <main>
        <!-- Summary -->
        <div class="cards">
            <div class="card"><p>Currently Borrowed</p><h1 id="cBorrowed">0</h1></div>
            <div class="card"><p>Overdue</p><h1 id="cOverdue">0</h1></div>
        </div>

        <!-- Scan panel -->
        <div class="panel">
            <h3>Scan Card</h3>
            <p id="status" class="status">Waiting for a teacher to scan a card...</p>

            <form id="borrowForm" class="hidden">
                <input type="hidden" id="uid">

                <label>RFID UID</label>
                <input type="text" id="uidText" readonly>

                <p id="who"></p>

                <div id="nameRow">
                    <label for="tName">Teacher name</label>
                    <input type="text" id="tName" maxlength="100" placeholder="Full name">
                </div>

                <label for="bookTitle">Book name</label>
                <input type="text" id="bookTitle" maxlength="200" placeholder="Title of the book" required>

                <label for="dueDate">Return date</label>
                <input type="date" id="dueDate" required>

                <div class="form-actions">
                    <button type="submit">Borrow</button>
                    <button type="button" id="cancelBtn" class="ghost">Cancel</button>
                </div>
            </form>
        </div>

        <!-- Records -->
        <div class="panel">
            <h3>Borrow Records</h3>
            <input type="text" id="search" placeholder="Search teacher or book...">
            <div class="scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>Book</th>
                            <th>Borrowed</th>
                            <th>Return By</th>
                            <th>Returned</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="loansBody">
                        <tr><td colspan="6">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
    const $ = id => document.getElementById(id);
    const esc = s => String(s).replace(/[&<>"']/g, c =>
        ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    let lastId = null;       // first poll only sets the baseline (old scans are ignored)
    let lastUid = '';
    let lastTime = 0;

    // Date helpers (local time)
    function localDate(offsetDays = 0) {
        const d = new Date();
        d.setDate(d.getDate() + offsetDays);
        d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
        return d.toISOString().slice(0, 10);
    }
    $('dueDate').min = localDate(0);

    async function api(action, body) {
        const opt = body ? { method: 'POST', body: new URLSearchParams(body) } : {};
        const res = await fetch('borrow_api.php?action=' + action, opt);
        if (res.status === 401) { location.href = 'login.php'; return null; }
        return res.json();
    }

    function setStatus(text, type = '') {
        const el = $('status');
        el.textContent = text;
        el.className = 'status ' + type;
    }

    function hideForm() {
        $('borrowForm').classList.add('hidden');
    }

    function showForm(uid, teacherName) {
        const isNew = !teacherName;
        $('uid').value = uid;
        $('uidText').value = uid;
        $('who').textContent = isNew ? 'New card: register the teacher and borrow a book.'
                                     : 'Teacher: ' + teacherName;
        $('nameRow').classList.toggle('hidden', !isNew);
        $('tName').required = isNew;
        $('tName').value = '';
        $('bookTitle').value = '';
        $('dueDate').value = localDate(7);      // default: 7 days from today
        $('borrowForm').classList.remove('hidden');
        (isNew ? $('tName') : $('bookTitle')).focus();
    }

    async function handleScan(d) {
        // Ignore the same card read again within 5 seconds
        const now = Date.now();
        if (d.uid === lastUid && now - lastTime < 5000) return;
        lastUid = d.uid;
        lastTime = now;

        if (d.is_student) {
            hideForm();
            setStatus('This card belongs to a student, not a teacher.', 'error');
            return;
        }

        // Registered teacher with a book out -> return it
        if (d.registered && d.loan) {
            hideForm();
            const r = await api('return', { loan_id: d.loan.id });
            if (r && r.success) {
                setStatus(d.teacher_name + ' returned "' + d.loan.book_title + '"' +
                          (d.loan.overdue ? ' (late, was due ' + d.loan.due_text + ').' : '.'), 'ok');
                loadLoans();
            } else if (r) {
                setStatus(r.message, 'error');
            }
            return;
        }

        // New card or registered teacher with no book out -> borrow form
        setStatus(d.registered ? 'Card scanned: ' + d.teacher_name
                               : 'New card scanned. Fill in the form.', '');
        showForm(d.uid, d.registered ? d.teacher_name : '');
    }

    async function poll() {
        try {
            const d = await api('latest');
            if (!d) return;
            if (lastId === null) { lastId = d.id; return; }
            if (d.id > lastId) {
                lastId = d.id;
                await handleScan(d);
            }
        } catch (e) { /* ignore and try again on the next tick */ }
    }

    $('borrowForm').addEventListener('submit', async e => {
        e.preventDefault();
        const r = await api('borrow', {
            uid: $('uid').value,
            name: $('tName').value,
            book_title: $('bookTitle').value,
            due_date: $('dueDate').value
        });
        if (!r) return;
        if (r.success) {
            hideForm();
            setStatus(r.message, 'ok');
            loadLoans();
        } else {
            setStatus(r.message, 'error');
        }
    });

    $('cancelBtn').addEventListener('click', () => {
        hideForm();
        setStatus('Waiting for a teacher to scan a card...');
    });

    async function loadLoans() {
        const d = await api('list');
        if (!d) return;
        $('cBorrowed').textContent = d.stats.borrowed;
        $('cOverdue').textContent = d.stats.overdue;

        const body = $('loansBody');
        if (!d.loans.length) {
            body.innerHTML = '<tr><td colspan="6">No records yet.</td></tr>';
            return;
        }
        body.innerHTML = d.loans.map(l => `
            <tr>
                <td>${esc(l.name)}</td>
                <td>${esc(l.book_title)}</td>
                <td>${esc(l.borrowed_text)}</td>
                <td>${esc(l.due_text)}</td>
                <td>${esc(l.returned_text || '—')}</td>
                <td><span class="badge ${l.status}">${l.status.charAt(0).toUpperCase() + l.status.slice(1)}</span></td>
            </tr>`).join('');
        filterRows();
    }

    function filterRows() {
        const q = $('search').value.toLowerCase();
        document.querySelectorAll('#loansBody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    }
    $('search').addEventListener('input', filterRows);

    $('logoutBtn').addEventListener('click', async () => {
        const res = await fetch('auth_api.php?action=logout');
        const data = await res.json();
        window.location.href = data.redirect;
    });

    loadLoans();
    setInterval(poll, 1000);        // check for a new card every second
    setInterval(loadLoans, 15000);  // refresh the table
    </script>
</body>
</html>