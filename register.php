<?php
session_start();
require 'config.php';

$error = "";
$success = "";

// Register Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === "" || $password === "") {
        $error = "Username and password are required.";
    } else {

        // Hash password
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        // Try inserting email (if column exists) or fallback to username-only
        if ($email !== "") {
            // Try email insert
            $stmt = @$conn->prepare("
                INSERT INTO users (username, email, password)
                VALUES (?, ?, ?)
            ");
            if ($stmt) {
                $stmt->bind_param("sss", $username, $email, $hashed);
            } else {
                // If email column doesn't exist — create without email
                $stmt = $conn->prepare("
                    INSERT INTO users (username, password)
                    VALUES (?, ?)
                ");
                $stmt->bind_param("ss", $username, $hashed);
            }
        } else {
            // Register without email
            $stmt = $conn->prepare("
                INSERT INTO users (username, password)
                VALUES (?, ?)
            ");
            $stmt->bind_param("ss", $username, $hashed);
        }

        if ($stmt->execute()) {
            $success = "Account created successfully!";
        } else {
            $error = "Username already exists. Try another.";
        }
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Register • ApexPlanet Blog</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background: #f8f9fa;
    margin: 0;
    font-family: Arial, sans-serif;
}
.register-card {
    max-width: 450px;
    margin: 60px auto;
    padding: 30px;
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
}
</style>
</head>
<body>

<div class="register-card">

    <h3 class="text-center mb-3">Create Account</h3>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success">
        <?php echo htmlspecialchars($success); ?><br>
        <a href="login.php" class="btn btn-success btn-sm mt-2">Go to Login</a>
      </div>
    <?php endif; ?>

    <form method="post">

        <div class="mb-3">
            <label class="form-label">Username (required)</label>
            <input type="text" class="form-control" name="username" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Email (optional)</label>
            <input type="email" class="form-control" name="email" placeholder="you@example.com">
        </div>

        <div class="mb-3">
            <label class="form-label">Password (required)</label>
            <input type="password" class="form-control" name="password" required>
        </div>

        <button class="btn btn-primary w-100">Register</button>

    </form>

    <div class="text-center mt-3">
        <small>Already have an account? <a href="login.php">Login</a></small>
    </div>

</div>

</body>
</html>

