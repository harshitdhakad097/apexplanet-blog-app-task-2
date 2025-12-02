<?php
session_start();
require 'config.php';
require 'helpers.php';

// Validate post ID
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: index.php"); exit; }

// Fetch main post
$stmt = $conn->prepare("
    SELECT p.*, u.username 
    FROM posts p 
    LEFT JOIN users u ON p.author_id = u.id 
    WHERE p.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$post) {
    echo "Post not found";
    exit;
}

// Reading time (200 words = 1 min)
function reading_time($text) {
    $words = str_word_count(strip_tags($text));
    return max(1, ceil($words / 200)) . " min read";
}

// Avatar initials
function initials($name) {
    $parts = explode(" ", trim($name));
    $i = "";
    foreach ($parts as $p) {
        if ($p !== "") $i .= strtoupper($p[0]);
        if (strlen($i) >= 2) break;
    }
    return $i ?: "U";
}
$avatar = initials($post["username"] ?? "User");

// Previous post
$prevStmt = $conn->prepare("SELECT id,title FROM posts WHERE created_at < ? ORDER BY created_at DESC LIMIT 1");
$prevStmt->bind_param("s", $post['created_at']);
$prevStmt->execute();
$prev = $prevStmt->get_result()->fetch_assoc();
$prevStmt->close();

// Next post
$nextStmt = $conn->prepare("SELECT id,title FROM posts WHERE created_at > ? ORDER BY created_at ASC LIMIT 1");
$nextStmt->bind_param("s", $post['created_at']);
$nextStmt->execute();
$next = $nextStmt->get_result()->fetch_assoc();
$nextStmt->close();

// Related posts (simple title word match)
$words = explode(" ", $post['title']);
$words = array_filter($words, fn($w) => strlen($w) > 3);
$words = array_slice($words, 0, 3);

$related = [];
if (!empty($words)) {
    $likeSQL = [];
    foreach ($words as $w) {
        $w = $conn->real_escape_string($w);
        $likeSQL[] = "title LIKE '%$w%'";
    }
    $q = "SELECT id,title FROM posts WHERE id<>$id AND (" . implode(" OR ", $likeSQL) . ") LIMIT 4";
    $relRes = $conn->query($q);
    if ($relRes) $related = $relRes->fetch_all(MYSQLI_ASSOC);
}

// Fetch Comments (single place)
$comments = db_select($conn, "SELECT c.id, c.message, c.created_at, c.user_id, u.username
                             FROM comments c
                             LEFT JOIN users u ON c.user_id = u.id
                             WHERE c.post_id = ? ORDER BY c.created_at ASC", "i", [$post['id']]);

// Likes info for like button
$likesRow = db_select($conn, "SELECT COUNT(*) AS c FROM likes WHERE post_id = ?", "i", [$post['id']]);
$likesCount = (int)($likesRow[0]['c'] ?? 0);

$userLiked = false;
if (!empty($_SESSION['user_id'])) {
    $likedRow = db_select($conn, "SELECT id FROM likes WHERE post_id = ? AND user_id = ? LIMIT 1", "ii", [$post['id'], (int)$_SESSION['user_id']]);
    $userLiked = !empty($likedRow);
}

$shareUrl = urlencode("http://localhost/blog_app/view_post.php?id=" . (int)$post['id']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo htmlspecialchars($post['title']); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- Bootstrap + Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<!-- Bootstrap (keep existing) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Your stylesheet (cache-buster added) -->
<link rel="stylesheet" href="assets/css/style.css?v=2">

<style>
body { background: #f5f7fa; }
.post-card {
    background: #fff;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.1);
}
.avatar {
    width: 60px; height: 60px;
    border-radius: 12px;
    background: #fff;
    color: #0d6efd;
    display:flex; align-items:center; justify-content:center;
    font-weight: 700; font-size: 22px;
}
</style>
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-3">
  <div class="container">
    <a class="navbar-brand fw-bold" href="index.php">ApexPlanet Blog</a>

    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#menu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="menu">
      <ul class="navbar-nav ms-auto">

        <li class="nav-item"><a class="nav-link" href="index.php">All Posts</a></li>

        <?php if (!empty($_SESSION['user_id'])): ?>
          <li class="nav-item"><a class="nav-link" href="create_post.php">Create Post</a></li>
          <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link fw-semibold"><?php echo htmlspecialchars($_SESSION['username']); ?></a></li>
          <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
          <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
        <?php endif; ?>

      </ul>
    </div>
  </div>
</nav>

<!-- PAGE CONTENT -->
<div class="container" style="max-width:900px;">

  <!-- Navigation Buttons -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex gap-2">
      <a href="index.php" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Back</a>
      <?php if (!empty($_SESSION['user_id'])): ?>
        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="create_post.php" class="btn btn-outline-success"><i class="bi bi-plus-circle"></i> New Post</a>
      <?php endif; ?>
    </div>

    <div class="d-flex gap-2">
      <?php if ($prev): ?>
        <a class="btn btn-sm btn-light" href="view_post.php?id=<?php echo $prev['id']; ?>">&larr; Prev</a>
      <?php else: ?>
        <button class="btn btn-sm btn-light" disabled>&larr; Prev</button>
      <?php endif; ?>

      <?php if ($next): ?>
        <a class="btn btn-sm btn-light" href="view_post.php?id=<?php echo $next['id']; ?>">Next &rarr;</a>
      <?php else: ?>
        <button class="btn btn-sm btn-light" disabled>Next &rarr;</button>
      <?php endif; ?>
    </div>
  </div>

  <div class="post-card">

    <!-- Cover image -->
    <?php if (!empty($post['cover_image']) && file_exists("uploads/".$post['cover_image'])): ?>
      <img src="uploads/<?php echo htmlspecialchars($post['cover_image']); ?>" class="img-fluid rounded mb-3" style="max-height:400px; object-fit:cover;">
    <?php endif; ?>

    <h2 class="fw-bold"><?php echo htmlspecialchars($post['title']); ?></h2>

    <div class="d-flex align-items-center mt-3 mb-3">
      <div class="avatar me-3"><?php echo $avatar; ?></div>
      <div>
        <div class="fw-semibold"><?php echo htmlspecialchars($post['username'] ?? "Unknown"); ?></div>
        <small class="text-muted">
          <?php echo $post['created_at']; ?> · <?php echo reading_time($post['content']); ?>
        </small>
      </div>
    </div>

    <hr>

    <!-- Post content -->
    <div style="font-size:1.05rem; line-height:1.7; white-space:pre-wrap;">
      <?php echo nl2br(htmlspecialchars($post['content'])); ?>
    </div>

    <!-- Author-only controls -->
    <?php if (!empty($_SESSION['user_id']) && $_SESSION['user_id'] == $post['author_id']): ?>
      <div class="mt-4 d-flex gap-2">
        <a class="btn btn-warning" href="edit_post.php?id=<?php echo $post['id']; ?>">Edit</a>
        <a class="btn btn-danger" href="delete_post.php?id=<?php echo $post['id']; ?>"
           onclick="return confirm('Delete this post?')">Delete</a>
      </div>
    <?php endif; ?>

    <hr>

    <!-- Like + Share (single canonical block) -->
    <div class="d-flex gap-2 mb-3 align-items-center">
      <form action="like.php" method="post" class="d-inline">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
        <input type="hidden" name="post_id" value="<?php echo (int)$post['id']; ?>">
        <button type="submit" class="btn btn-sm <?php echo $userLiked ? 'btn-success' : 'btn-outline-primary'; ?>">
          <?php echo $userLiked ? 'Liked' : 'Like'; ?> (<?php echo $likesCount; ?>)
        </button>
      </form>

      <!-- share buttons -->
      <a class="btn btn-outline-success btn-sm" href="https://api.whatsapp.com/send?text=<?php echo $shareUrl; ?>" target="_blank">WhatsApp</a>
      <a class="btn btn-outline-info btn-sm" href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo $shareUrl; ?>" target="_blank">LinkedIn</a>
      <a class="btn btn-outline-secondary btn-sm" href="https://twitter.com/intent/tweet?url=<?php echo $shareUrl; ?>" target="_blank">Twitter</a>
    </div>

    <!-- Related posts -->
    <?php if (!empty($related)): ?>
      <h5 class="mt-4">Related Posts</h5>
      <ul>
        <?php foreach ($related as $r): ?>
          <li><a href="view_post.php?id=<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['title']); ?></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <!-- COMMENTS SECTION -->
  <div id="comments" class="mt-4">
    <h5>Comments</h5>

    <!-- Comment form (only if logged in) -->
    <?php if (!empty($_SESSION['user_id'])): ?>
      <form action="comment_submit.php" method="post" class="mb-3">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
        <input type="hidden" name="post_id" value="<?php echo (int)$post['id']; ?>">
        <div class="mb-2">
          <textarea name="message" rows="3" class="form-control" placeholder="Write your comment..." required></textarea>
        </div>
        <div>
          <button class="btn btn-primary btn-sm">Post Comment</button>
        </div>
      </form>
    <?php else: ?>
      <div class="alert alert-secondary">Please <a href="login.php">log in</a> to post a comment.</div>
    <?php endif; ?>

    <!-- List comments -->
    <?php if (empty($comments)): ?>
      <div class="text-muted">No comments yet. Be the first to comment.</div>
    <?php else: ?>
      <div class="list-group">
        <?php foreach ($comments as $c): ?>
          <div class="list-group-item d-flex justify-content-between align-items-start">
            <div>
              <div style="font-weight:700"><?php echo htmlspecialchars($c['username'] ?? 'Unknown'); ?></div>
              <div class="small text-muted"><?php echo $c['created_at']; ?></div>
              <div class="mt-2"><?php echo nl2br(htmlspecialchars($c['message'])); ?></div>
            </div>

            <div class="ms-3">
              <?php if (is_admin($conn) || (int)$c['user_id'] === (int)($_SESSION['user_id'] ?? 0)): ?>
                <a href="delete_comment.php?id=<?php echo (int)$c['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this comment?')">Delete</a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <!-- END COMMENTS -->

</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const form = document.querySelector('form[action="comment_submit.php"]');
  if (!form) return;
  form.addEventListener('submit', function(e){
    const msgEl = form.querySelector('textarea[name="message"]');
    const msg = (msgEl && msgEl.value) ? msgEl.value.trim() : '';
    if (msg.length < 2) {
      e.preventDefault();
      alert('Comment is too short.');
    }
  });
});
</script>

</body>
</html>
