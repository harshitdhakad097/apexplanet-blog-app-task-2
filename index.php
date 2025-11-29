<?php
session_start();
require 'config.php';

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 6;
$offset = ($page - 1) * $perPage;

$search = trim($_GET['search'] ?? '');

if ($search !== "") {
    $s = "%$search%";
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM posts 
        WHERE title LIKE ? OR content LIKE ?
    ");
    $stmt->bind_param("ss", $s, $s);
    $stmt->execute();
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT p.*, u.username 
        FROM posts p 
        LEFT JOIN users u ON p.author_id=u.id
        WHERE title LIKE ? OR content LIKE ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("ssii", $s, $s, $perPage, $offset);

} else {
    $result = $conn->query("SELECT COUNT(*) AS c FROM posts");
    $total = (int)$result->fetch_assoc()['c'];

    $stmt = $conn->prepare("
        SELECT p.*, u.username 
        FROM posts p 
        LEFT JOIN users u ON p.author_id=u.id
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("ii", $perPage, $offset);
}

$stmt->execute();
$posts = $stmt->get_result();
$pages = max(1, ceil($total / $perPage));
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>All Posts • ApexPlanet Blog</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
body { background:#f5f7fa; }
.post-card {
    border-radius: 15px;
    background: #fff;
    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
    overflow: hidden;
    transition: 0.2s ease;
}
.post-card:hover { transform: translateY(-6px); }
.post-img {
    height: 170px;
    object-fit: cover;
    width: 100%;
}
.badge-tag {
    background: rgba(13,110,253,0.15);
    color: #0d6efd;
    border-radius: 8px;
    padding: 4px 10px;
    margin-right: 5px;
    font-size: .8rem;
}
.avatar-sm {
    width: 40px;
    height: 40px;
    background: #fff;
    border-radius: 8px;
    display:flex; align-items:center; justify-content:center;
    font-weight: bold;
    color:#0d6efd;
}
</style>
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
  <div class="container">
    <a class="navbar-brand fw-bold" href="index.php">StoryNest</a>

    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#menu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="menu">
      <ul class="navbar-nav ms-auto">

        <li class="nav-item"><a class="nav-link active" href="index.php">All Posts</a></li>

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

<div class="container">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h4 mb-0">All Posts</h1>
      <div class="text-muted small">Explore recent posts from the blog</div>
    </div>

    <form class="d-flex" method="get">
      <input name="search" class="form-control me-2" placeholder="Search title or content..." value="<?php echo htmlspecialchars($search); ?>">
      <button class="btn btn-primary">Search</button>
    </form>
  </div>

  <div class="row g-4">

    <?php if ($posts->num_rows === 0): ?>
      <div class="col-12">
        <div class="alert alert-info">No posts found.</div>
      </div>
    <?php endif; ?>

    <?php while ($p = $posts->fetch_assoc()): 

        // Thumbnail
        if (!empty($p['cover_image']) && file_exists("uploads/".$p['cover_image'])) {
            $img = "uploads/".$p['cover_image'];
        } else {
            $color = substr(md5($p['title']), 0, 6);
            $img = "https://via.placeholder.com/800x400/$color/ffffff?text=" . urlencode(substr($p['title'],0,20));
        }

        // avatar
        $u = $p['username'] ?? "Unknown";
        $i = strtoupper($u[0]);
    ?>

      <div class="col-md-6 col-lg-4">
        <div class="post-card">
          <img src="<?php echo $img; ?>" class="post-img">

          <div class="p-3 d-flex flex-column">

            <h5 class="fw-bold"><?php echo htmlspecialchars($p['title']); ?></h5>

            <p class="text-muted small mb-2">
              <?php echo substr(strip_tags($p['content']), 0, 120); ?>...
            </p>

            <!-- tags -->
            <div class="mb-2">
              <?php 
                $words = explode(" ", $p['title']);
                foreach (array_slice($words,0,3) as $w): ?>
                <span class="badge-tag"><?php echo htmlspecialchars($w); ?></span>
              <?php endforeach; ?>
            </div>

            <div class="d-flex justify-content-between align-items-center">
              <div class="d-flex align-items-center gap-2">
                <div class="avatar-sm"><?php echo $i; ?></div>
                <span class="small text-muted"><?php echo htmlspecialchars($u); ?></span>
              </div>
              <a href="view_post.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary">Read</a>
            </div>

          </div>
        </div>
      </div>

    <?php endwhile; ?>

  </div>

  <!-- Pagination -->
  <nav class="mt-4">
    <ul class="pagination justify-content-center">

      <li class="page-item <?php echo ($page<=1?'disabled':''); ?>">
        <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
      </li>

      <?php for ($i=1;$i<=$pages;$i++): ?>
        <li class="page-item <?php echo ($i==$page?'active':''); ?>">
          <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
        </li>
      <?php endfor; ?>

      <li class="page-item <?php echo ($page>=$pages?'disabled':''); ?>">
        <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
      </li>

    </ul>
  </nav>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>



