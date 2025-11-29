<?php
// edit_post.php - redesigned edit page
session_start();
require 'config.php';

// must be logged in
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = "";
$userId = (int)$_SESSION['user_id'];

// get post id
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

// fetch post
$stmt = $conn->prepare("SELECT * FROM posts WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$post) { header('Location: index.php'); exit; }

// check author
if ((int)$post['author_id'] !== $userId) {
    // not author -> forbid
    http_response_code(403);
    echo "You are not allowed to edit this post."; exit;
}

// handle form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $remove_cover = isset($_POST['remove_cover']) && $_POST['remove_cover'] === '1';

    if ($title === '') $errors[] = "Title is required.";
    if ($content === '') $errors[] = "Content is required.";

    $new_cover = null;

    // handle new upload
    if (!empty($_FILES['cover']['name'])) {
        $allowed_types = ['image/jpeg','image/png','image/webp'];
        $file = $_FILES['cover'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Error uploading new image.";
        } elseif (!in_array($file['type'], $allowed_types)) {
            $errors[] = "Only JPG, PNG or WEBP images are allowed for cover.";
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $errors[] = "Image must be under 2 MB.";
        } else {
            $uploadDir = __DIR__ . '/uploads';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_cover = uniqid('cover_', true) . '.' . $ext;
            $dest = $uploadDir . '/' . $new_cover;
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                $errors[] = "Failed to save uploaded image.";
                $new_cover = null;
            }
        }
    }

    if (empty($errors)) {
        // if requested remove_cover, delete existing file
        if ($remove_cover && !empty($post['cover_image']) && file_exists(__DIR__.'/uploads/'.$post['cover_image'])) {
            @unlink(__DIR__.'/uploads/'.$post['cover_image']);
            $post['cover_image'] = null;
        }

        // if new cover uploaded, delete old file (if exists) and set new name
        if ($new_cover) {
            if (!empty($post['cover_image']) && file_exists(__DIR__.'/uploads/'.$post['cover_image'])) {
                @unlink(__DIR__.'/uploads/'.$post['cover_image']);
            }
            $cover_to_store = $new_cover;
        } else {
            // keep existing (or null if removed)
            $cover_to_store = $remove_cover ? null : $post['cover_image'];
        }

        // update DB
        $upd = $conn->prepare("UPDATE posts SET title = ?, content = ?, cover_image = ? WHERE id = ?");
        $upd->bind_param('sssi', $title, $content, $cover_to_store, $id);
        if ($upd->execute()) {
            $success = "Post updated successfully.";
            $upd->close();
            header("Location: view_post.php?id=" . $id);
            exit;
        } else {
            $errors[] = "Failed to update post. Try again.";
            $upd->close();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Edit Post • ApexPlanet Blog</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{background:#f8f9fa;margin:0;font-family:Arial, sans-serif;}
    .form-card{max-width:900px;margin:36px auto;padding:22px;background:#fff;border-radius:12px;box-shadow:0 8px 28px rgba(2,6,23,0.06);}
    .thumb{max-height:220px;object-fit:cover;border-radius:8px;}
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold" href="index.php">ApexPlanet Blog</a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#menu"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="menu">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="index.php">All Posts</a></li>
        <li class="nav-item"><a class="nav-link" href="create_post.php">Create Post</a></li>
        <li class="nav-item"><a class="nav-link active" href="dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link fw-semibold"><?php echo htmlspecialchars($_SESSION['username']); ?></a></li>
        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="form-card container">
  <h3 class="mb-3">Edit Post</h3>
  <div class="mb-3 text-muted">Edit title, content, or change the cover image.</div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" novalidate>
    <div class="mb-3">
      <label class="form-label">Title <span class="text-danger">*</span></label>
      <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($_POST['title'] ?? $post['title']); ?>" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Current Cover</label><br>
      <?php if (!empty($post['cover_image']) && file_exists(__DIR__.'/uploads/'.$post['cover_image'])): ?>
        <img src="<?php echo 'uploads/'.htmlspecialchars($post['cover_image']); ?>" class="thumb mb-2" alt="cover">
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="remove_cover" value="1" id="removeCover">
          <label class="form-check-label" for="removeCover">Remove existing cover image</label>
        </div>
      <?php else: ?>
        <div class="text-muted">No cover image set.</div>
      <?php endif; ?>
    </div>

    <div class="mb-3">
      <label class="form-label">Upload New Cover (optional)</label>
      <input type="file" name="cover" accept="image/*" class="form-control">
      <div class="form-text">JPG, PNG, WEBP. Max 2 MB. Uploading a new image replaces the old one.</div>
    </div>

    <div class="mb-3">
      <label class="form-label">Content <span class="text-danger">*</span></label>
      <textarea name="content" rows="10" class="form-control" required><?php echo htmlspecialchars($_POST['content'] ?? $post['content']); ?></textarea>
    </div>

    <div class="d-flex justify-content-between">
      <a href="view_post.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary">Cancel</a>
      <button class="btn btn-primary" type="submit">Update Post</button>
    </div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

