// app.js

document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('login-form');
    const errorMessageContainer = document.getElementById('error-message-container');

    if (loginForm) {
        loginForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            errorMessageContainer.innerHTML = '';

            // --- CHANGE 1: Get values directly ---
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            // --- CHANGE 2: Create a simple JavaScript object ---
            const loginData = {
                username: username,
                password: password
            };

            try {
                // --- CHANGE 3: Update the fetch() call ---
                const response = await fetch('http://localhostapi-login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(loginData)
                });

                const result = await response.json();

                if (result.success) {
                    // SUCCESS! Show a temporary message.
                    errorMessageContainer.innerHTML = `
                        <div class="alert alert-success">
                            <strong>Success!</strong> Welcome, ${result.user.username}. Loading dashboard...
                        </div>
                    `;

                    // --- THIS IS THE KEY CHANGE ---
                    const token = result.token;
                    const userId = result.user.id;
                    const username = result.user.username;
                    const role = result.user.role;

                    const redirectUrl = `http://localhostauth-bridge.php?token=${token}&userId=${userId}&username=${username}&role=${role}`;

                    // Redirect after 1 second
                    setTimeout(() => {
                        window.location.href = redirectUrl;
                    }, 1000);

                } else {
                    // Login failed
                    errorMessageContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> ${result.message}
                        </div>
                    `;
                }
            } catch (error) {
                // Catch network/JSON errors
                errorMessageContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> An error occurred: ${error.message}
                    </div>
                `;
            }
        });
    }
});
