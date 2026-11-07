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
    <link rel="stylesheet" href="design.css">
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

                <label>Book name(s)</label>
                <div id="bookList"></div>
                <button type="button" id="addBookBtn" class="ghost">+ Add another book</button>

                <label for="days">Borrow for (days)</label>
                <input type="number" id="days" min="1" max="60" value="7" required>

                <label for="dueDate">Return date (for all books)</label>
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
                            <th>Duration</th>
                            <th>Returned</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="loansBody">
                        <tr><td colspan="7">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Popup shown after a book is borrowed or returned -->
    <div id="popup" class="popup" hidden>
        <div class="popup-box">
            <div class="popup-icon">&#10003;</div>
            <h2 id="popupTitle"></h2>
            <p id="popupText"></p>
            <button type="button" id="popupOk">OK</button>
        </div>
    </div>

    <!-- Popup: ask before returning a book -->
    <div id="confirmPopup" class="popup" hidden>
        <div class="popup-box">
            <h2 id="confirmTitle">Are you sure you want to return this book?</h2>
            <p id="confirmText"></p>
            <div class="popup-actions">
                <button type="button" id="confirmYes">Yes, return it</button>
                <button type="button" id="confirmNo" class="ghost">No, cancel</button>
            </div>
        </div>
    </div>

    <!-- Popup: ask before borrowing a book -->
    <div id="borrowConfirmPopup" class="popup" hidden>
        <div class="popup-box">
            <h2 id="borrowConfirmTitle">Are you sure you want to borrow this book?</h2>
            <p id="borrowConfirmText"></p>
            <div class="popup-actions">
                <button type="button" id="borrowYes">Yes, borrow it</button>
                <button type="button" id="borrowNo" class="ghost">No, go back</button>
            </div>
        </div>
    </div>

    <!-- Popup: shown on every scan - borrow or return? -->
    <div id="actionPopup" class="popup" hidden>
        <div class="popup-box">
            <h2 id="actionTitle"></h2>
            <p id="actionText"></p>
            <div class="popup-actions">
                <button type="button" id="actionBorrow">Borrow a book</button>
                <button type="button" id="actionReturn">Return a book</button>
                <button type="button" id="actionCancel" class="ghost">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Popup: pick which borrowed book is being returned -->
    <div id="pickPopup" class="popup" hidden>
        <div class="popup-box">
            <h2>Which books are you returning?</h2>
            <p id="pickWho"></p>
            <div id="pickList" class="pick-list"></div>
            <div class="popup-actions">
                <button type="button" id="pickAll" class="ghost">Select all</button>
                <button type="button" id="pickReturn" disabled>Return selected</button>
                <button type="button" id="pickBack" class="ghost">Back</button>
            </div>
        </div>
    </div>

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
    const MAX_DAYS = 60;                      // longest borrow time allowed in the form
    $('dueDate').min = localDate(1);
    $('dueDate').max = localDate(MAX_DAYS);
    $('days').max = MAX_DAYS;

    // Number of days from today until a YYYY-MM-DD date
    function daysUntil(dateStr) {
        const [y, m, d] = dateStr.split('-').map(Number);
        const today = new Date(); today.setHours(0, 0, 0, 0);
        return Math.round((new Date(y, m - 1, d) - today) / 86400000);
    }

    function daysText(dateStr) {
        const n = daysUntil(dateStr);
        return n + (n === 1 ? ' day' : ' days');
    }

    // typing the days fills in the return date, and picking a date fills in the days
    function syncDateFromDays() {
        const n = parseInt($('days').value, 10);
        if (n >= 1 && n <= MAX_DAYS) $('dueDate').value = localDate(n);
    }
    function syncDaysFromDate() {
        if ($('dueDate').value) $('days').value = daysUntil($('dueDate').value);
    }
    $('days').addEventListener('input', syncDateFromDays);
    $('dueDate').addEventListener('input', syncDaysFromDate);

    async function api(action, body) {
        let opt = {};
        if (body) {
            const params = new URLSearchParams();
            for (const [key, value] of Object.entries(body)) {
                if (Array.isArray(value)) value.forEach(v => params.append(key, v));   // several values, same key
                else params.append(key, value);
            }
            opt = { method: 'POST', body: params };
        }
        const res = await fetch('borrow_api.php?action=' + action, opt);
        if (res.status === 401) { location.href = 'login.php'; return null; }
        return res.json();
    }

    function setStatus(text, type = '') {
        const el = $('status');
        el.textContent = text;
        el.className = 'status ' + type;
    }

    // ---------- Popup ----------
    let popupTimer = null;

    function hidePopup() {
        $('popup').hidden = true;
        clearTimeout(popupTimer);
    }

    function showPopup(title, text) {
        $('popupTitle').textContent = title;
        $('popupText').textContent = text;
        $('popup').hidden = false;
        clearTimeout(popupTimer);
        popupTimer = setTimeout(hidePopup, 4000);   // closes by itself after 4 seconds
    }

    $('popupOk').addEventListener('click', hidePopup);

    function hideForm() {
        $('borrowForm').classList.add('hidden');
    }

    // ---------- Book rows in the borrow form ----------
    function updateBookRows() {
        const rows = document.querySelectorAll('#bookList .book-row');
        rows.forEach(r => { r.querySelector('.book-remove').hidden = rows.length === 1; });
        $('addBookBtn').disabled = rows.length >= 10;      // 10 books max at a time
    }

    function addBookRow() {
        const row = document.createElement('div');
        row.className = 'book-row';

        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'book-title';
        input.maxLength = 200;
        input.placeholder = 'Title of the book';
        input.required = true;

        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'ghost book-remove';
        del.textContent = '\u00d7';
        del.title = 'Remove this book';
        del.addEventListener('click', () => { row.remove(); updateBookRows(); });

        row.append(input, del);
        $('bookList').append(row);
        updateBookRows();
        return input;
    }

    function bookTitles() {
        return [...document.querySelectorAll('#bookList .book-title')]
            .map(i => i.value.trim()).filter(Boolean);
    }

    $('addBookBtn').addEventListener('click', () => addBookRow().focus());

    function showForm(uid, teacherName) {
        const isNew = !teacherName;
        $('uid').value = uid;
        $('uidText').value = uid;
        $('who').textContent = isNew ? 'New card: register the teacher and borrow a book.'
                                     : 'Teacher: ' + teacherName;
        $('nameRow').classList.toggle('hidden', !isNew);
        $('tName').required = isNew;
        $('tName').value = '';
        $('bookList').innerHTML = '';
        addBookRow();
        $('days').value = 7;                    // default: 7 days from today
        syncDateFromDays();
        $('borrowForm').classList.remove('hidden');
        (isNew ? $('tName') : document.querySelector('#bookList .book-title')).focus();
    }

    // ---------- Confirm return ----------
    let pendingReturn = null;

    function hideConfirm() {
        $('confirmPopup').hidden = true;
        pendingReturn = null;
    }

    function askReturn(teacherName, loans) {
        pendingReturn = { teacher_name: teacherName, loans: loans };
        const many = loans.length > 1;
        $('confirmTitle').textContent = many
            ? 'Are you sure you want to return these ' + loans.length + ' books?'
            : 'Are you sure you want to return this book?';
        $('confirmText').textContent =
            (many ? loans.map(l => '\u2022 ' + l.book_title).join('\n') : '"' + loans[0].book_title + '"') +
            '\n(borrowed by ' + teacherName + ')';
        $('confirmPopup').hidden = false;
        setStatus('Waiting for confirmation to return the book' + (many ? 's' : '') + '...');
    }

    async function returnBook(d) {
        const ids = d.loans.map(l => l.id);
        const many = ids.length > 1;
        const r = await api('return', { 'loan_ids[]': ids });
        if (r && r.success) {
            const late = l => l.overdue ? ' (late, was due ' + l.due_text + ')' : '';
            const text = many
                ? d.loans.map(l => '\u2022 ' + l.book_title + (l.overdue ? ' (late)' : '')).join('\n')
                : '"' + d.loans[0].book_title + '" was returned by ' + d.teacher_name + late(d.loans[0]) + '.';

            let msg = many ? d.teacher_name + ' returned ' + r.returned + ' books.'
                           : d.teacher_name + ' returned "' + d.loans[0].book_title + '"' + late(d.loans[0]) + '.';
            if (r.returned < ids.length) msg += ' (' + (ids.length - r.returned) + ' were already returned.)';

            setStatus(msg, 'ok');
            showPopup(many ? 'Books have been returned!' : 'Book has been returned!', text);
            loadLoans();
        } else if (r) {
            setStatus(r.message, 'error');
        }
    }

    $('confirmYes').addEventListener('click', () => {
        const d = pendingReturn;
        hideConfirm();
        if (d) returnBook(d);
    });

    $('confirmNo').addEventListener('click', () => {
        hideConfirm();
        setStatus('Return cancelled.');
    });

    // ---------- Scan popup: borrow or return? ----------
    let scanData = null;   // the card that was just scanned

    function openLoans() {
        return scanData ? (scanData.loans || []) : [];
    }

    function hideAction() { $('actionPopup').hidden = true; }
    function hidePick()   { $('pickPopup').hidden = true; }

    function closeAllPopups() {
        hideConfirm();
        hideBorrowConfirm();
        hideAction();
        hidePick();
    }

    function showActionPopup() {
        const d = scanData;
        const n = openLoans().length;

        if (d.registered) {
            $('actionTitle').textContent = 'Hi, ' + d.teacher_name + '!';
            $('actionText').textContent = n === 0
                ? 'You have no borrowed books. Do you want to borrow one?'
                : 'You have ' + n + ' borrowed book' + (n > 1 ? 's' : '') + '. Do you want to borrow or return a book?';
        } else {
            $('actionTitle').textContent = 'New card';
            $('actionText').textContent = 'This card is not registered yet. Borrow a book to register the teacher.';
        }

        $('actionReturn').hidden = n === 0;   // nothing to return
        $('actionPopup').hidden = false;
    }

    // Several books out -> tick the ones being returned
    function pickBoxes() {
        return [...document.querySelectorAll('#pickList input[type="checkbox"]')];
    }

    function updatePickButtons() {
        const boxes = pickBoxes();
        const n = boxes.filter(b => b.checked).length;
        $('pickReturn').disabled = n === 0;
        $('pickReturn').textContent = n ? 'Return selected (' + n + ')' : 'Return selected';
        $('pickAll').textContent = n === boxes.length ? 'Clear all' : 'Select all';
    }

    function showPick() {
        $('pickWho').textContent = scanData.teacher_name;
        const list = $('pickList');
        list.innerHTML = '';

        openLoans().forEach((loan, i) => {
            const row = document.createElement('label');
            row.className = 'pick-item';

            const box = document.createElement('input');
            box.type = 'checkbox';
            box.value = i;
            box.addEventListener('change', updatePickButtons);

            const info = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent = loan.book_title;
            const due = document.createElement('span');
            due.textContent = 'Due ' + loan.due_text;
            info.append(title, due);

            if (loan.overdue) {
                const late = document.createElement('span');
                late.className = 'pick-late';
                late.textContent = 'Overdue';
                info.append(late);
            }

            row.append(box, info);
            list.append(row);
        });

        updatePickButtons();
        $('pickPopup').hidden = false;
    }

    $('pickAll').addEventListener('click', () => {
        const boxes = pickBoxes();
        const all = boxes.every(b => b.checked);
        boxes.forEach(b => { b.checked = !all; });
        updatePickButtons();
    });

    $('pickReturn').addEventListener('click', () => {
        const loans = openLoans();
        const chosen = pickBoxes().filter(b => b.checked).map(b => loans[Number(b.value)]);
        if (!chosen.length) return;
        hidePick();
        askReturn(scanData.teacher_name, chosen);
    });

    $('actionBorrow').addEventListener('click', () => {
        hideAction();
        showForm(scanData.uid, scanData.registered ? scanData.teacher_name : '');
    });

    $('actionReturn').addEventListener('click', () => {
        hideAction();
        const loans = openLoans();
        if (loans.length === 1) askReturn(scanData.teacher_name, [loans[0]]);   // only one book: no need to pick
        else showPick();
    });

    $('actionCancel').addEventListener('click', () => {
        hideAction();
        setStatus('Waiting for a teacher to scan a card...');
    });

    $('pickBack').addEventListener('click', () => {
        hidePick();
        setStatus('Waiting for a teacher to scan a card...');
    });

    async function handleScan(d) {
        // Ignore the same card read again within 5 seconds
        const now = Date.now();
        if (d.uid === lastUid && now - lastTime < 5000) return;
        lastUid = d.uid;
        lastTime = now;

        closeAllPopups();   // a new scan closes any old popup
        hideForm();

        if (d.is_student) {
            setStatus('This card belongs to a student, not a teacher.', 'error');
            return;
        }

        // Every scan from a teacher (or a new card) asks: borrow or return?
        scanData = d;
        setStatus(d.registered ? 'Card scanned: ' + d.teacher_name
                               : 'New card scanned.', '');
        showActionPopup();
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

    // ---------- Confirm borrow ----------
    function hideBorrowConfirm() {
        $('borrowConfirmPopup').hidden = true;
    }

    $('borrowForm').addEventListener('submit', e => {
        e.preventDefault();
        const titles = bookTitles();
        if (!titles.length) return;
        const many = titles.length > 1;

        $('borrowConfirmTitle').textContent = many
            ? 'Are you sure you want to borrow these ' + titles.length + ' books?'
            : 'Are you sure you want to borrow this book?';
        $('borrowConfirmText').textContent =
            (many ? titles.map(t => '\u2022 ' + t).join('\n') : '"' + titles[0] + '"') +
            '\n(' + daysText($('dueDate').value) + ', due back on ' + $('dueDate').value + ')';
        $('borrowConfirmPopup').hidden = false;
    });

    $('borrowYes').addEventListener('click', () => {
        hideBorrowConfirm();
        borrowBook();
    });

    $('borrowNo').addEventListener('click', hideBorrowConfirm);   // the form stays open

    async function borrowBook() {
        const titles = bookTitles();
        const due = $('dueDate').value;
        const many = titles.length > 1;

        const r = await api('borrow', {
            uid: $('uid').value,
            name: $('tName').value,
            'book_title[]': titles,
            due_date: due
        });
        if (!r) return;
        if (r.success) {
            hideForm();
            setStatus(r.message, 'ok');
            showPopup(many ? 'Books have been borrowed!' : 'Book has been borrowed!',
                      many ? titles.map(t => '\u2022 ' + t).join('\n') + '\n(' + daysText(due) + ', due back on ' + due + ')'
                           : '"' + titles[0] + '" is borrowed for ' + daysText(due) + ', due back on ' + due + '.');
            loadLoans();
        } else {
            setStatus(r.message, 'error');
        }
    }

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
            body.innerHTML = '<tr><td colspan="7">No records yet.</td></tr>';
            return;
        }
        body.innerHTML = d.loans.map(l => `
            <tr>
                <td>${esc(l.name)}</td>
                <td>${esc(l.book_title)}</td>
                <td>${esc(l.borrowed_text)}</td>
                <td>${esc(l.due_text)}</td>
                <td>${esc(l.days)} day${Number(l.days) === 1 ? '' : 's'}</td>
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