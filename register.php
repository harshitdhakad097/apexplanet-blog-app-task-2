<?php
// register.php
// Requires config.php in same folder (you already created it)

session_start();
require 'config.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and trim input
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $password2 = trim($_POST['password2'] ?? '');

    // Basic validation
    if ($username === '') {
        $errors[] = "Username is required.";
    } elseif (strlen($username) > 100) {
        $errors[] = "Username must be 100 characters or less.";
    }

    if ($password === '' || $password2 === '') {
        $errors[] = "Both password fields are required.";
    } elseif ($password !== $password2) {
        $errors[] = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }

    // If no validation errors, proceed
    if (empty($errors)) {
        // 1) Check if username already exists (prepared statement)
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        if (!$stmt) {
            $errors[] = "Database error: failed to prepare statement.";
        } else {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $errors[] = "Username already taken. Choose another.";
                $stmt->close();
            } else {
                $stmt->close();

                // 2) Insert new user with hashed password
                $hashed = password_hash($password, PASSWORD_DEFAULT);

                $ins = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                if (!$ins) {
                    $errors[] = "Database error: failed to prepare insert.";
                } else {
                    $ins->bind_param('ss', $username, $hashed);
                    if ($ins->execute()) {
                        // Registration successful
                        $ins->close();
                        $_SESSION['success_message'] = "Registration successful. You can now log in.";
                        header('Location: login.php'); // redirect to login page (we'll create it)
                        exit;
                    } else {
                        $errors[] = "Registration failed. Try again.";
                        $ins->close();
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Register - Blog App</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    body{font-family: Arial, sans-serif; padding:30px; max-width:600px; margin:auto;}
    .error{color:#b00020;}
    .success{color:green;}
    form{display:grid; gap:10px;}
    input[type="text"], input[type="password"]{padding:8px; font-size:16px; width:100%;}
    button{padding:10px; font-size:16px;}
  </style>
</head>
<body>
  <h2>Create account</h2>

  <?php
  // Show errors or success
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

  <form method="post" action="register.php" autocomplete="off" novalidate>
    <label>
      Username
      <input type="text" name="username" required maxlength="100" value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>">
    </label>

    <label>
      Password
      <input type="password" name="password" required minlength="6">
    </label>

    <label>
      Confirm Password
      <input type="password" name="password2" required minlength="6">
    </label>

    <button type="submit">Register</button>
  </form>

  <p>Already have an account? <a href="login.php">Login here</a>.</p>
</body>
</html>
