<?php
// dashboard.php - Advanced dashboard for ApexPlanet Blog (Task 3+)
// Requirements: session started and config.php in same folder
session_start();
require 'config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);

// --- ADMIN ROLE CHECK (ADDED) ---
$is_admin = false;
$roleQ = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
if ($roleQ) {
    $roleQ->bind_param("i", $userId);
    $roleQ->execute();
    $roleRes = $roleQ->get_result()->fetch_assoc();
    $is_admin = !empty($roleRes['is_admin']) && $roleRes['is_admin'] == 1;
    $roleQ->close();
}

// --- STATS ---
// Total posts
$totalPosts = (int)$conn->query("SELECT COUNT(*) AS c FROM posts")->fetch_assoc()['c'];
// Your posts
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM posts WHERE author_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$userPosts = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();
// Total users
$totalUsers = (int)$conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];

// --- RECENT POSTS (latest 8) ---
$recentStmt = $conn->prepare("SELECT p.id, p.title, p.created_at, p.author_id, u.username 
                              FROM posts p LEFT JOIN users u ON p.author_id = u.id
                              ORDER BY p.created_at DESC LIMIT 8");
$recentStmt->execute();
$recentRes = $recentStmt->get_result();
$recentPosts = $recentRes->fetch_all(MYSQLI_ASSOC);
$recentStmt->close();

// --- POSTS PER MONTH (last 6 months) ---
$months = [];
$counts = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime("-{$i} months");
    $label = date('M Y', $ts);
    $months[] = $label;
    // start and end of month
    $start = date('Y-m-01 00:00:00', $ts);
    $end = date('Y-m-t 23:59:59', $ts);
    $ps = $conn->prepare("SELECT COUNT(*) AS c FROM posts WHERE created_at BETWEEN ? AND ?");
    $ps->bind_param('ss', $start, $end);
    $ps->execute();
    $c = (int)$ps->get_result()->fetch_assoc()['c'];
    $ps->close();
    $counts[] = $c;
}

// JSON for chart
$months_json = json_encode($months);
$counts_json = json_encode($counts);

// Helper to create avatar initials
function initials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $i = '';
    foreach ($parts as $p) {
        if ($p !== '') $i .= strtoupper($p[0]);
        if (strlen($i) >= 2) break;
    }
    return $i ?: 'U';
}
$avatar_initials = initials($_SESSION['username'] ?? 'User');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Dashboard • ApexPlanet Blog</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

  <style>
    :root {
      --sidebar-width: 260px;
      --bg-muted: #f4f7fb;
    }
    body { background: var(--bg-muted); }
    .sidebar {
        width: var(--sidebar-width);
        min-height: 100vh;
        position: fixed;
        left: 0; top: 0;
        padding-top: 1rem;
        background: #0d6efd; /* primary */
        color: #fff;
    }
    .sidebar a { color: rgba(255,255,255,0.95); text-decoration: none; }
    .sidebar .nav-link { color: rgba(255,255,255,0.9); }
    .sidebar .nav-link.active { background: rgba(255,255,255,0.08); border-radius: 6px; }

    main.content { margin-left: var(--sidebar-width); padding: 28px 32px; }
    .card-hover { transition: transform .18s ease, box-shadow .18s; }
    .card-hover:hover { transform: translateY(-6px); box-shadow: 0 6px 18px rgba(0,0,0,0.08); }
    .avatar {
      width:56px; height:56px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center;
      background:#fff; color:#0b5ed7; font-weight:700; font-size:18px;
    }
    .small-muted { color:#6c757d; font-size:.9rem; }
    .dark-mode { background: #0b1220 !important; color: #dee3f0 !important; }
    /* responsive */
    @media (max-width: 991px) {
      .sidebar { position: relative; width: 100%; min-height: auto; display:none; }
      .sidebar.show { display:block; position:fixed; z-index:1050; }
      main.content { margin-left:0; }
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar d-flex flex-column p-3" id="sidebar">
    <a href="index.php" class="d-flex align-items-center mb-3 text-white text-decoration-none px-3">
      <div class="avatar me-2" style="background:#fff;color:#0d6efd;"><?php echo htmlspecialchars(substr($avatar_initials,0,2)); ?></div>
      <div>
        <div style="font-weight:700">ApexPlanet Blog</div>
        <small style="opacity:.9">by <?php echo $username; ?></small>
      </div>
    </a>

    <hr style="border-color: rgba(255,255,255,0.08)" />

    <ul class="nav nav-pills flex-column mb-auto px-2">
      <li class="nav-item mb-1"><a href="dashboard.php" class="nav-link active"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>
      <li class="nav-item mb-1"><a href="create_post.php" class="nav-link"><i class="bi bi-plus-circle me-2"></i> Create Post</a></li>
      <li class="nav-item mb-1"><a href="index.php" class="nav-link"><i class="bi bi-file-earmark-text me-2"></i> View Posts</a></li>
      
      <li class="nav-item mb-1"><a href="logout.php" class="nav-link"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
    </ul>

    <div class="mt-auto px-3 pb-4">
      <small style="opacity:.9">Built for ApexPlanet Internship</small>
    </div>
  </div>

  <!-- Topbar -->
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm" style="position:sticky; top:0; z-index:1040;">
    <div class="container-fluid">
      <button class="btn btn-outline-primary d-lg-none" id="toggleSidebar"><i class="bi bi-list"></i></button>

      <form class="d-none d-md-flex ms-2 me-auto" role="search" action="index.php" method="get">
        <input class="form-control me-2" name="search" placeholder="Search posts..." value="">
        <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button>
      </form>

      <div class="d-flex align-items-center gap-2">
        <button id="darkToggle" class="btn btn-outline-secondary btn-sm" title="Toggle dark mode"><i class="bi bi-moon"></i></button>
        <div class="d-flex align-items-center ms-2">
          <div class="me-2 text-end">
            <div style="font-weight:700"><?php echo $username; ?></div>
           <div class="small-muted">
    <?php echo ($is_admin) ? "Admin" : "Member"; ?></div>

          </div>
          <div class="avatar" style="background:#fff;color:#0d6efd;"><?php echo htmlspecialchars(substr($avatar_initials,0,2)); ?></div>
        </div>
      </div>
    </div>
  </nav>

  <!-- Main content -->
  <main class="content">
    <div class="container-fluid">
      <div class="row g-4">
        <div class="col-lg-8">
          <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
              <h3 class="mb-0">Welcome back, <?php echo $username; ?> 👋</h3>
              <div class="small-muted">Here's an overview of your project and recent activity.</div>
            </div>
            <div>
              <a href="create_post.php" class="btn btn-primary">+ New Post</a>
            </div>
          </div>

          <!-- Cards -->
          <div class="row g-3 mb-3">
            <div class="col-sm-4">
              <div class="card card-hover p-3">
                <div class="d-flex align-items-center">
                  <div class="flex-grow-1">
                    <div class="small-muted">Total Posts</div>
                    <h4 class="mb-0"><?php echo $totalPosts; ?></h4>
                  </div>
                  <div class="ms-3 display-6 text-primary"><i class="bi bi-postcard-heart"></i></div>
                </div>
              </div>
            </div>

            <div class="col-sm-4">
              <div class="card card-hover p-3">
                <div class="d-flex align-items-center">
                  <div class="flex-grow-1">
                    <div class="small-muted">Your Posts</div>
                    <h4 class="mb-0"><?php echo $userPosts; ?></h4>
                  </div>
                  <div class="ms-3 display-6 text-success"><i class="bi bi-person"></i></div>
                </div>
              </div>
            </div>

            
          </div>

          <!-- Chart -->
          <div class="card mb-3 card-hover">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Posts in last 6 months</h5>
                <small class="small-muted">Updated live</small>
              </div>
              <canvas id="postsChart" style="width:100%; height:260px;"></canvas>
            </div>
          </div>

          <!-- Recent posts table -->
          <div class="card card-hover">
            <div class="card-body">
              <h5 class="card-title">Recent Posts</h5>
              <?php if (empty($recentPosts)): ?>
                <div class="alert alert-info">No recent posts found.</div>
              <?php else: ?>
                <div class="list-group">
                  <?php foreach ($recentPosts as $p): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-start">
                      <div>
                        <div style="font-weight:700"><?php echo htmlspecialchars($p['title']); ?></div>
                        <div class="small-muted">by <?php echo htmlspecialchars($p['username'] ?? 'Unknown'); ?> · <?php echo $p['created_at']; ?></div>
                      </div>
                      <div class="d-flex gap-2">
                        <a href="view_post.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                        <?php if ($p['author_id'] == $userId): ?>
                          <a href="edit_post.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                          <a href="delete_post.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this post?')">Delete</a>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>

        </div> <!-- col-lg-8 -->

        <!-- Right column -->
        <div class="col-lg-4">
          <!-- Profile card -->
          <div class="card card-hover mb-3">
            <div class="card-body text-center">
              <div class="avatar mb-2" style="width:80px;height:80px;border-radius:18px;font-size:24px;"><?php echo htmlspecialchars(substr($avatar_initials,0,2)); ?></div>
              <h5 class="mb-0"><?php echo $username; ?></h5>
              <div class="small-muted mb-3">ApexPlanet Intern</div>
              <div class="d-grid gap-2">
                <a href="create_post.php" class="btn btn-primary">Create Post</a>
                <a href="index.php" class="btn btn-outline-secondary">View Site</a>
              </div>
            </div>
          </div>

          <!-- Recent activity (simple) -->
          <div class="card card-hover">
            <div class="card-body">
              <h6 class="card-title">Recent Activity</h6>
              <ul class="list-unstyled small-muted mb-0">
                <?php foreach ($recentPosts as $p): ?>
                  <li class="mb-2">New post: <strong><?php echo htmlspecialchars($p['title']); ?></strong><br><small><?php echo $p['created_at']; ?></small></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>

        </div> <!-- col-lg-4 -->
      </div> <!-- row -->
    </div> <!-- container -->
  </main>

<script>
// Sidebar toggle (mobile)
const sidebar = document.getElementById('sidebar');
document.getElementById('toggleSidebar').addEventListener('click', () => {
  sidebar.classList.toggle('show');
});

// Dark mode toggle
const darkToggle = document.getElementById('darkToggle');
const applyDark = (on) => {
  if (on) {
    document.documentElement.classList.add('dark-mode');
    localStorage.setItem('apex_dark', '1');
    darkToggle.innerHTML = '<i class="bi bi-sun"></i>';
  } else {
    document.documentElement.classList.remove('dark-mode');
    localStorage.removeItem('apex_dark');
    darkToggle.innerHTML = '<i class="bi bi-moon"></i>';
  }
};
darkToggle.addEventListener('click', () => applyDark(!document.documentElement.classList.contains('dark-mode')));
if (localStorage.getItem('apex_dark') === '1') applyDark(true);

// Chart.js: posts chart
const months = <?php echo $months_json; ?>;
const counts = <?php echo $counts_json; ?>;
const ctx = document.getElementById('postsChart').getContext('2d');
new Chart(ctx, {
  type: 'line',
  data: {
    labels: months,
    datasets: [{
      label: 'Posts',
      data: counts,
      tension: 0.35,
      borderWidth: 2,
      pointRadius: 4,
      fill: true,
      backgroundColor: 'rgba(13,110,253,0.08)',
      borderColor: 'rgba(13,110,253,0.9)',
      pointBackgroundColor: 'rgba(13,110,253,0.9)'
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false }},
    scales: {
      y: { beginAtZero: true, ticks: { precision:0 } }
    }
  }
});
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

