<?php
// dashboard-navbar-snippet.php
// Simple top navigation bar used by dashboard and admin pages.
// Safe, minimal — include this file where needed.
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm" style="position:sticky; top:0; z-index:1040;">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="dashboard.php" style="gap:10px;">
      <div style="width:36px;height:36px;border-radius:8px;background:#0ea5a4;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:700;">A</div>
      <span style="font-weight:700;color:#062532;">ApexPlanet</span>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-controls="navMenu" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php">View Site</a></li>
        <li class="nav-item"><a class="nav-link" href="create_post.php">Create Post</a></li>
      </ul>

      <div class="d-flex align-items-center">
        <?php if (!empty($_SESSION['username'])): ?>
          <div class="me-3 text-end" style="line-height:1;">
            <div style="font-weight:700;"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
            <div class="small-muted"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Member'); ?></div>
          </div>
          <a href="logout.php" class="btn btn-outline-primary btn-sm">Logout</a>
        <?php else: ?>
          <a href="login.php" class="btn btn-primary btn-sm">Login</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
