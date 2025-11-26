<?php
// create_post.php
session_start();
require 'config.php';

// Only logged-in users can create posts
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($title === '') $errors[] = "Title is required.";
    if ($content === '') $errors[] = "Content is required.";

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO posts (title, content, author_id) VALUES (?, ?, ?)");
        if (!$stmt) {
            $errors[] = "Database error: failed to prepare.";
        } else {
            $author = (int)$_SESSION['user_id'];
            $stmt->bind_param('ssi', $title, $content, $author);
            if ($stmt->execute()) {
                $stmt->close();
                header('Location: index.php');
                exit;
            } else {
                $errors[] = "Failed to create post.";
                $stmt->close();
            }
        }
    }
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Create Post</title></head>
<body>
  <h2>Create New Post</h2>
  <?php if (!empty($errors)) { echo '<div style="color:#b00020"><ul>'; foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; echo "</ul></div>"; } ?>
  <form method="post" action="create_post.php">
    <label>Title<br><input type="text" name="title" style="width:100%" value="<?php echo isset($title)?htmlspecialchars($title):'' ?>"></label><br><br>
    <label>Content<br><textarea name="content" rows="8" style="width:100%"><?php echo isset($content)?htmlspecialchars($content):'' ?></textarea></label><br><br>
    <button type="submit">Create</button>
  </form>
  <p><a href="index.php">Back to posts</a> | <a href="dashboard.php">Dashboard</a></p>
</body>
</html>
