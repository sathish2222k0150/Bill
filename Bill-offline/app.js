// app.js - OFFLINE VERSION

document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('login-form');
    const errorMessageContainer = document.getElementById('error-message-container');

    if (loginForm) {
        loginForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            errorMessageContainer.innerHTML = '';

            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const loginData = { username, password };

            try {
                // --- THIS IS THE MAIN CHANGE ---
                // The URL now points to your app's internal server on port 8000
                const response = await fetch('http://localhost:8000/api-login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(loginData)
                });

                const result = await response.json();

                if (result.success) {
                    errorMessageContainer.innerHTML = `
                        <div class="alert alert-success">
                            <strong>Success!</strong> Welcome, ${result.user.username}. Loading dashboard...
                        </div>
                    `;

                    const token = result.token;
                    const userId = result.user.id;
                    const username = result.user.username;
                    const role = result.user.role;
                    
                    // --- THIS IS THE SECOND CHANGE ---
                    // The redirect URL also points to the internal server
                    const redirectUrl = `http://localhost:8000/auth-bridge.php?token=${token}&userId=${userId}&username=${username}&role=${role}`;

                    setTimeout(() => {
                        window.location.href = redirectUrl;
                    }, 1000);

                } else {
                    errorMessageContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> ${result.message}
                        </div>
                    `;
                }
            } catch (error) {
                errorMessageContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> An error occurred: ${error.message}
                    </div>
                `;
            }
        });
    }
});