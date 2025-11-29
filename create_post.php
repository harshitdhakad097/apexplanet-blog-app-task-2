<?php
session_start();
require 'config.php';

// Require login
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    // Basic validation
    if ($title === '') $errors[] = "Title is required.";
    if ($content === '') $errors[] = "Content is required.";

    // Handle optional cover image
    $cover_filename = null;
    if (!empty($_FILES['cover']['name'])) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        $file = $_FILES['cover'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Error uploading image.";
        } elseif (!in_array($file['type'], $allowed_types)) {
            $errors[] = "Only JPG, PNG or WEBP images are allowed.";
        } elseif ($file['size'] > 2 * 1024 * 1024) { // 2 MB limit
            $errors[] = "Image must be under 2 MB.";
        } else {
            // create uploads dir if not exists
            $uploadDir = __DIR__ . '/uploads';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $cover_filename = uniqid('cover_', true) . '.' . $ext;
            $dest = $uploadDir . '/' . $cover_filename;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                $errors[] = "Failed to save uploaded image.";
                $cover_filename = null;
            }
        }
    }

    // If no errors, insert post
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO posts (title, content, author_id, cover_image) VALUES (?, ?, ?, ?)");
        $author = (int)$_SESSION['user_id'];
        $stmt->bind_param('ssis', $title, $content, $author, $cover_filename);

        if ($stmt->execute()) {
            $success = "Post created successfully.";
            // Redirect to view the post (optional) - get inserted id
            $newId = $stmt->insert_id;
            $stmt->close();
            header("Location: view_post.php?id=" . (int)$newId);
            exit;
        } else {
            $errors[] = "Failed to create post. Please try again.";
            $stmt->close();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Create Post • ApexPlanet Blog</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    body { background:#f8f9fa; margin:0; font-family: Arial, sans-serif; }
    .card-centered { max-width:900px; margin:40px auto; padding:0; }
    .form-card { padding:28px; border-radius:12px; background:#fff; box-shadow:0 6px 24px rgba(0,0,0,0.08); }
    .muted { color:#6b7280; }
    .thumb-preview { max-height:180px; object-fit:cover; border-radius:8px; }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
  <div class="container">
    <a class="navbar-brand fw-bold" href="index.php">Storynest</a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#menu"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="menu">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="index.php">All Posts</a></li>
        <li class="nav-item"><a class="nav-link active" href="create_post.php">Create Post</a></li>
        <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link fw-semibold"><?php echo htmlspecialchars($_SESSION['username']); ?></a></li>
        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container card-centered">
  <div class="form-card">
    <h3 class="mb-3">Create New Post</h3>
    <div class="muted mb-3">Write something helpful — your readers will thank you.</div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" novalidate>
      <div class="mb-3">
        <label class="form-label">Title <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Cover image (optional)</label>
        <input type="file" name="cover" accept="image/*" class="form-control">
        <div class="form-text">JPG, PNG, or WEBP. Max 2 MB.</div>
        <?php if (!empty($_FILES['cover']['name']) && !empty($cover_filename) && file_exists(__DIR__ . '/uploads/' . $cover_filename)): ?>
          <div class="mt-2"><img src="<?php echo 'uploads/' . htmlspecialchars($cover_filename); ?>" class="thumb-preview"></div>
        <?php endif; ?>
      </div>

      <div class="mb-3">
        <label class="form-label">Content <span class="text-danger">*</span></label>
        <textarea name="content" rows="10" class="form-control" required><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
      </div>

      <div class="d-flex justify-content-between align-items-center">
        <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-primary" type="submit">Publish Post</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
