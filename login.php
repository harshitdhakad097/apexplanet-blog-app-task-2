<?php
session_start();
require 'config.php';

$error = "";

// Login logic (accept username OR email)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = trim($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        $error = "Please enter both username/email and password.";
    } else {
        // Try find by username or email (if email column exists)
        // Safe: if 'email' column doesn't exist, this query will fail. In that case we fallback below.
        $sql = "SELECT id, username, password 
                FROM users 
                WHERE username = ? OR (COALESCE(email, '') = ?)
                LIMIT 1";
        $stmt = @$conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('ss', $identifier, $identifier);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            // Fallback if email column not present — use username only
            $stmt2 = $conn->prepare("SELECT id, username, password FROM users WHERE username = ? LIMIT 1");
            $stmt2->bind_param('s', $identifier);
            $stmt2->execute();
            $user = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
        }

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = "Invalid username/email or password.";
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Login • ApexPlanet Blog</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
  :root{
    --card-radius:14px;
  }
  html,body{height:100%;margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;}
  /* background image with dark overlay for readability */
  body{
    background:
      linear-gradient(rgba(8,10,16,0.55), rgba(8,10,16,0.65)),
      url("https://images.pexels.com/photos/1054218/pexels-photo-1054218.jpeg") center/cover no-repeat fixed;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:24px;
  }

  /* container */
  .wrap {
    width:100%;
    max-width:920px;
    display:grid;
    grid-template-columns: 1fr;
    gap:20px;
  }

  /* two-column layout on wide screens */
  @media(min-width:900px){
    .wrap { grid-template-columns: 420px 1fr; align-items:center; }
  }

  /* left card (login) */
  .login-card {
    background:#ffffff;
    border-radius: var(--card-radius);
    padding:28px;
    box-shadow: 0 12px 40px rgba(2,6,23,0.45);
  }
  .login-card h2{font-weight:700;margin-bottom:10px;}
  .login-card .form-label{font-weight:600;}
  .form-control{border-radius:10px;padding:10px 12px;}
  .btn-primary{border-radius:10px;padding:10px 12px;font-weight:700;}

  /* right promo / text */
  .hero {
    color: #fff;
    padding:28px;
    border-radius: var(--card-radius);
    display:flex;
    flex-direction:column;
    justify-content:center;
    background: linear-gradient(180deg, rgba(255,255,255,0.04), rgba(255,255,255,0.02));
    box-shadow: 0 8px 28px rgba(2,6,23,0.35);
  }
  .hero h3{font-weight:700;margin-bottom:8px;}
  .hero p{opacity:0.92;line-height:1.5;}

  .muted { color: #6b7280; font-size:0.95rem; }

  .small-link { font-size:0.9rem; color:#0d6efd; text-decoration:none; font-weight:600; }
  .error-box { background:#fff2f2; color:#880000; padding:10px; border-radius:8px; margin-bottom:12px; font-weight:600; }
</style>
</head>
<body>

<div class="wrap">

  <!-- LOGIN CARD -->
  <div class="login-card">
    <h2>Welcome back</h2>
    <div class="muted mb-3">Log in to manage posts and share your experiences.</div>

    <?php if (!empty($error)): ?>
      <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="on" novalidate>
      <div class="mb-3">
        <label class="form-label">Username or Email</label>
        <input name="identifier" class="form-control" placeholder="your username or email" required />
      </div>

      <div class="mb-3">
        <label class="form-label">Password</label>
        <input name="password" type="password" class="form-control" placeholder="Your password" required />
      </div>

      <div class="d-grid mb-3">
        <button class="btn btn-primary" type="submit">Login</button>
      </div>

      <div class="d-flex justify-content-between align-items-center">
        <small class="muted">Don't have an account?</small>
        <a class="small-link" href="register.php">Create account</a>
      </div>
    </form>
  </div>

  <!-- RIGHT PROMO / INFO -->
  <div class="hero">
    <h3>Share your experiences</h3>
    <p>Write about places you visit, lessons you learn, and things you care about. This blog is your space — simple tools, beautiful output.</p>
    <div style="margin-top:18px;">
      <div class="muted"></div>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



