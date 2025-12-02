<?php
// admin_manage_users.php
session_start();
require 'config.php';
require 'helpers.php';

// require admin
if (empty($_SESSION['user_id']) || !is_admin($conn)) {
    header('Location: dashboard.php'); exit;
}

// handle POST actions
$ok_msg = $err_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) { $err_msg = "Invalid CSRF token"; }
    else {
        $action = $_POST['action'] ?? '';
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid <= 0) { $err_msg = "Invalid user"; }
        else {
            if ($action === 'verify') {
                db_exec($conn, "UPDATE users SET is_verified = 1 WHERE id = ?", "i", [$uid]);
                $ok_msg = "User verified.";
            } elseif ($action === 'unverify') {
                db_exec($conn, "UPDATE users SET is_verified = 0 WHERE id = ?", "i", [$uid]);
                $ok_msg = "User unverified.";
            } elseif ($action === 'promote') {
                db_exec($conn, "UPDATE users SET role = 'editor' WHERE id = ?", "i", [$uid]);
                $ok_msg = "User promoted to editor.";
            } elseif ($action === 'demote') {
                db_exec($conn, "UPDATE users SET role = 'member' WHERE id = ?", "i", [$uid]);
                $ok_msg = "User demoted to member.";
            } elseif ($action === 'reset_password') {
                // generate temp password
                $temp = bin2hex(random_bytes(4)); // 8 hex chars
                $hash = password_hash($temp, PASSWORD_DEFAULT);
                db_exec($conn, "UPDATE users SET password = ? WHERE id = ?", "si", [$hash, $uid]);
                $ok_msg = "Password reset. Temporary password: " . $temp;
            } elseif ($action === 'delete_user') {
                // prevent deleting yourself
                if ($uid == (int)$_SESSION['user_id']) { $err_msg = "You cannot delete yourself."; }
                else {
                    db_exec($conn, "DELETE FROM users WHERE id = ?", "i", [$uid]);
                    $ok_msg = "User deleted.";
                }
            } elseif ($action === 'set_badge') {
                $badge = trim($_POST['badge_text'] ?? '');
                db_exec($conn, "UPDATE users SET badge = ? WHERE id = ?", "si", [$badge, $uid]);
                $ok_msg = "Badge updated.";
            } else {
                $err_msg = "Unknown action.";
            }
        }
    }
}

// fetch users
// determine which "created" column exists on users table
$createdCol = first_existing_column($conn, 'users', ['created_at','created','created_on','registered_at','joined_at']);
if ($createdCol === null) {
    // fallback to id if no date column present
    $users = db_select($conn, "SELECT id, username, email, role, is_verified, badge FROM users ORDER BY id DESC", "", []);
} else {
    // use dynamic column name and alias it to created_at for consistent templating
    $sql = "SELECT id, username, email, role, is_verified, badge, `$createdCol` AS created_at FROM users ORDER BY `$createdCol` DESC";
    $users = db_select($conn, $sql, "", []);
}

$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Admin — Manage Users</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'dashboard-navbar-snippet.php' ?? ''; // optional: reuse your navbar ?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Manage Users</h3>
    <a href="dashboard.php" class="btn btn-outline-primary">Back</a>
  </div>

  <?php if ($ok_msg): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($ok_msg); ?></div>
  <?php endif; ?>
  <?php if ($err_msg): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($err_msg); ?></div>
  <?php endif; ?>

  <div class="card mb-3">
    <div class="card-body">
      <table class="table table-hover align-middle">
        <thead>
          <tr>
            <th>User</th>
            <th>Email</th>
            <th>Role</th>
            <th>Verified</th>
            <th>Badge / Batch</th>
            <th>Joined</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><strong><?php echo htmlspecialchars($u['username']); ?></strong> <?php if ($u['is_verified']): ?> <span class="badge bg-primary ms-2">✔ Verified</span> <?php endif; ?></td>
              <td><?php echo htmlspecialchars($u['email'] ?? '-'); ?></td>
              <td><?php echo htmlspecialchars($u['role']); ?></td>
              <td><?php echo $u['is_verified'] ? 'Yes' : 'No'; ?></td>
              <td><?php echo htmlspecialchars($u['badge'] ?? '-'); ?></td>
            <td>
    <?php echo htmlspecialchars($u['created_at'] ?? 'N/A'); ?>
</td>

              <td class="text-end">
                <form method="post" class="d-inline">
                  <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                  <?php if ($u['is_verified']): ?>
                    <button name="action" value="unverify" class="btn btn-sm btn-outline-primary">Unverify</button>
                  <?php else: ?>
                    <button name="action" value="verify" class="btn btn-sm btn-primary">Verify</button>
                  <?php endif; ?>

                  <?php if ($u['role'] === 'member'): ?>
                    <button name="action" value="promote" class="btn btn-sm btn-outline-primary">Promote</button>
                  <?php elseif ($u['role'] === 'editor'): ?>
                    <button name="action" value="demote" class="btn btn-sm btn-outline-primary">Demote</button>
                  <?php endif; ?>

                  <button name="action" value="reset_password" class="btn btn-sm btn-warning">Reset PW</button>

                  <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                    <button name="action" value="delete_user" class="btn btn-sm btn-danger" onclick="return confirm('Delete user? This will remove all user data.')">Delete</button>
                  <?php endif; ?>
                </form>

                <!-- quick set badge -->
                <form method="post" class="d-inline ms-1">
                  <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                  <input type="text" name="badge_text" placeholder="Badge" value="<?php echo htmlspecialchars($u['badge'] ?? ''); ?>" style="width:110px" class="form-control form-control-sm d-inline-block">
                  <button name="action" value="set_badge" class="btn btn-sm btn-outline-primary">Set</button>
                </form>

              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
