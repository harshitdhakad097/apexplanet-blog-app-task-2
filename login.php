<?php
// login.php
session_start();
require 'config.php';

// If user already logged in, redirect to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $errors[] = "Username and password are required.";
    } else {
        // Prepared statement to fetch user by username
        $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
        if (!$stmt) {
            $errors[] = "Database error: failed to prepare statement.";
        } else {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 1) {
                $stmt->bind_result($user_id, $hashed_password);
                $stmt->fetch();
                // Verify password
                if (password_verify($password, $hashed_password)) {
                    // Success: set session and redirect
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    // Regenerate session id for security
                    session_regenerate_id(true);
                    $stmt->close();
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $errors[] = "Invalid username or password.";
                }
            } else {
                $errors[] = "Invalid username or password.";
            }
            $stmt->close();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Login - Blog App</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    body{font-family: Arial, sans-serif; padding:30px; max-width:600px; margin:auto;}
    .error{color:#b00020;}
    form{display:grid; gap:10px;}
    input[type="text"], input[type="password"]{padding:8px; font-size:16px; width:100%;}
    button{padding:10px; font-size:16px;}
  </style>
</head>
<body>
  <h2>Login</h2>

  <?php
  if (!empty($errors)) {
      echo '<div class="error"><ul>';
      foreach ($errors as $e) {
          echo "<li>" . htmlspecialchars($e) . "</li>";
      }
      echo '</ul></div>';
  }
  if (!empty($_SESSION['success_message'])) {
      echo '<div class="success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
      unset($_SESSION['success_message']);
  }
  ?>

  <form method="post" action="login.php" autocomplete="off" novalidate>
    <label>
      Username
      <input type="text" name="username" required maxlength="100" value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>">
    </label>

    <label>
      Password
      <input type="password" name="password" required>
    </label>

    <button type="submit">Login</button>
  </form>

  <p>Don't have an account? <a href="register.php">Register here</a>.</p>
</body>
</html>
