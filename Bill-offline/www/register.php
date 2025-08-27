<?php
require_once 'config.php'; // includes $pdo

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'user';

    if ($username && $password && $confirm_password) {
        if ($password !== $confirm_password) {
            $error = 'Passwords do not match';
        } else {
            // Check if username already exists
            $checkQuery = "SELECT id FROM users WHERE username = :username";
            $checkStmt = $pdo->prepare($checkQuery);
            $checkStmt->bindParam(':username', $username);
            $checkStmt->execute();

            if ($checkStmt->rowCount() > 0) {
                $error = 'Username already taken';
            } else {
                // Hash the password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Insert new user
                $insertQuery = "INSERT INTO users (username, password, role) VALUES (:username, :password, :role)";
                $stmt = $pdo->prepare($insertQuery);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $hashedPassword);
                $stmt->bindParam(':role', $role);

                if ($stmt->execute()) {
                    $success = "Registration successful. You can now <a href='index.php'>login</a>.";
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    } else {
        $error = 'Please fill in all fields';
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Vehicle Billing Software</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .register-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
            overflow: hidden;
        }
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .register-body {
            padding: 40px;
        }
        .form-control {
            border-radius: 10px;
            margin-bottom: 20px;
            padding: 12px 15px;
        }
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            width: 100%;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .alert {
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h3><i class="fas fa-car"></i> SDS Automotive</h3>
            <p class="mb-0">Register a New Account</p>
        </div>
        <div class="register-body">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control" name="username" placeholder="Username" required>
                </div>

                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" name="password" placeholder="Password" required>
                </div>

                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" name="confirm_password" placeholder="Confirm Password" required>
                </div>

                <div class="input-group mb-4">
                    <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                    <select class="form-control" name="role">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary btn-register">
                    <i class="fas fa-user-plus"></i> Register
                </button>
            </form>

            <div class="text-center mt-3">
                <small>Already have an account? <a href="index.php">Login here</a></small>
            </div>
        </div>
    </div>
</body>
</html>
