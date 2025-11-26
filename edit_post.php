<?php
// edit_post.php
session_start();
require 'config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

// Fetch post and check ownership
$stmt = $conn->prepare("SELECT id, title, content, author_id FROM posts WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$post = $res->fetch_assoc();
$stmt->close();

if (!$post) { echo "Post not found."; exit; }

if ($post['author_id'] != $_SESSION['user_id']) {
    echo "You are not allowed to edit this post."; exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($title === '') $errors[] = "Title required.";
    if ($content === '') $errors[] = "Content required.";

    if (empty($errors)) {
        $upd = $conn->prepare("UPDATE posts SET title = ?, content = ? WHERE id = ?");
        $upd->bind_param('ssi', $title, $content, $id);
        if ($upd->execute()) {
            $upd->close();
            header('Location: index.php');
            exit;
        } else {
            $errors[] = "Failed to update.";
            $upd->close();
        }
    }
} else {
    $title = $post['title'];
    $content = $post['content'];
}
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Edit Post</title></head>
<body>
  <h2>Edit Post</h2>
  <?php if (!empty($errors)) { echo '<div style="color:#b00020"><ul>'; foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; echo "</ul></div>"; } ?>
  <form method="post" action="edit_post.php?id=<?php echo $id; ?>">
    <label>Title<br><input type="text" name="title" style="width:100%" value="<?php echo htmlspecialchars($title); ?>"></label><br><br>
    <label>Content<br><textarea name="content" rows="10" style="width:100%"><?php echo htmlspecialchars($content); ?></textarea></label><br><br>
    <button type="submit">Save</button>
  </form>
  <p><a href="index.php">Back</a></p>
</body></html>
