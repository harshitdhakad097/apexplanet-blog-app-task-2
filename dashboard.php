<?php
// dashboard.php
session_start();
if (empty($_SESSION['user_id'])) {
    // Not logged in
    header('Location: login.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Dashboard - Blog App</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    body{font-family: Arial, sans-serif; padding:30px; max-width:800px; margin:auto;}
    a.button{display:inline-block; padding:8px 12px; background:#0073e6; color:#fff; text-decoration:none; border-radius:4px;}
  </style>
</head>
<body>
  <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!</h2>
  <p>This is a protected page — only visible if you are logged in.</p>

  <p>
    <a class="button" href="create_post.php">Create Post</a>
    <a class="button" href="index.php">View Posts</a>
    <a class="button" href="logout.php">Logout</a>
  </p>
</body>
</html>
