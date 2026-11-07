<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In</title>
</head>
<body>
    <form id="loginForm">
        <div class="form">
            <h3>Log In</h3>

            <p id="message" style="color:red;"></p>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" placeholder="Enter your email" required>

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" placeholder="Enter your password" required>
        </div>

        <div class="button">
            <button type="submit" id="loginBtn">Log In</button>
        </div>
    </form>

    <script>
    const form = document.getElementById('loginForm');
    const message = document.getElementById('message');
    const btn = document.getElementById('loginBtn');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();               // stop the normal page submit
        message.textContent = '';
        btn.disabled = true;

        try {
            const res = await fetch('auth_api.php?action=login', {
                method: 'POST',
                body: new FormData(form)  // sends email + password as $_POST
            });
            const data = await res.json();

            if (data.success) {
                window.location.href = data.redirect;
            } else {
                message.textContent = data.message;
            }
        } catch (err) {
            message.textContent = 'Could not reach the server.';
        }

        btn.disabled = false;
    });
    </script>
</body>
</html>