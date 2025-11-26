<?php
// index.php - list posts
require 'config.php';

// Pagination simple variables (optional)
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 5;
$offset = ($page - 1) * $perPage;

// Count total posts
$totalRes = $conn->query("SELECT COUNT(*) as c FROM posts");
$totalRow = $totalRes->fetch_assoc();
$total = (int)$totalRow['c'];

// Fetch posts with author username (LEFT JOIN in case author_id is null)
$sql = "SELECT p.id, p.title, p.content, p.created_at, p.author_id, u.username
        FROM posts p
        LEFT JOIN users u ON p.author_id = u.id
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $perPage, $offset);
$stmt->execute();
$res = $stmt->get_result();
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Posts</title></head>
<body>
  <h2>All Posts</h2>
  <?php if (!empty($_SESSION['username'])): ?>
    <p>Logged in as <?php echo htmlspecialchars($_SESSION['username']); ?> — <a href="dashboard.php">Dashboard</a></p>
  <?php else: ?>
    <p><a href="login.php">Login</a> or <a href="register.php">Register</a></p>
  <?php endif; ?>

  <?php while ($row = $res->fetch_assoc()): ?>
    <article style="border:1px solid #ccc;padding:12px;margin-bottom:12px;">
      <h3><?php echo htmlspecialchars($row['title']); ?></h3>
      <div style="font-size:0.9em;color:#555">by <?php echo htmlspecialchars($row['username'] ?? 'Unknown'); ?> — <?php echo $row['created_at']; ?></div>
      <p><?php echo nl2br(htmlspecialchars(mb_substr($row['content'],0,1000))); ?></p>

      <?php
        // Show edit/delete only for author
        session_start();
        $current_user = $_SESSION['user_id'] ?? null;
        if ($current_user && $current_user == $row['author_id']): ?>
          <p>
            <a href="edit_post.php?id=<?php echo $row['id']; ?>">Edit</a> |
            <a href="delete_post.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this post?');">Delete</a>
          </p>
      <?php endif; ?>
    </article>
  <?php endwhile; ?>

  <!-- Pagination links -->
  <?php
  $pages = max(1, ceil($total / $perPage));
  for ($i=1;$i<=$pages;$i++){
      if ($i == $page) echo "<strong>$i</strong> ";
      else echo '<a href="?page='.$i.'">'.$i.'</a> ';
  }
  ?>

  <p><a href="create_post.php">Create new post</a></p>
</body>
</html>
<?php $stmt->close(); ?>
