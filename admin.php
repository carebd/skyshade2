<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$admin = Auth::requireAdmin();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_user') {
        $u = trim($_POST['username']); $e = trim($_POST['email']);
        $p = $_POST['password']; $r = $_POST['role'];
        $q = (int)$_POST['quota_gb'] * 1073741824;
        $h = password_hash($p, PASSWORD_BCRYPT, ['cost'=>12]);
        try {
            DB::query("INSERT INTO cd_users (username,email,password_hash,role,storage_quota) VALUES (?,?,?,?,?)",[$u,$e,$h,$r,$q]);
            $msg = 'User created.';
        } catch(PDOException $ex) { $err = 'User already exists.'; }
    } elseif ($action === 'toggle_user') {
        $uid = (int)$_POST['user_id'];
        DB::query("UPDATE cd_users SET is_active = 1 - is_active WHERE id=? AND role!='admin'", [$uid]);
        $msg = 'User status updated.';
    } elseif ($action === 'update_quota') {
        $uid = (int)$_POST['user_id'];
        $q   = (int)$_POST['quota_gb'] * 1073741824;
        DB::query("UPDATE cd_users SET storage_quota=? WHERE id=?", [$q, $uid]);
        $msg = 'Quota updated.';
    }
}

$users    = DB::all("SELECT * FROM cd_users ORDER BY created_at DESC");
$stats    = DB::one("SELECT COUNT(*) as total_files, SUM(file_size) as total_size FROM cd_files");
$activity = DB::all("SELECT a.*, u.username FROM cd_activity a LEFT JOIN cd_users u ON a.user_id=u.id ORDER BY a.created_at DESC LIMIT 30");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin — CloudDrive</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
  body { background: #0f172a; color: #e2e8f0; }
  .navbar { background: #1e293b !important; }
  .card { background: #1e293b; border: 1px solid #334155; }
  .table { color: #e2e8f0; } .table td,.table th { border-color: #334155; }
  .form-control,.form-select { background:#0f172a; border-color:#334155; color:#e2e8f0; }
</style>
</head>
<body>
<nav class="navbar px-4 py-2">
  <span class="navbar-brand text-white fw-bold">☁️ CloudDrive Admin</span>
  <div class="d-flex gap-2">
    <a href="index.php" class="btn btn-sm btn-outline-primary">← My Drive</a>
    <a href="logout.php" class="btn btn-sm btn-outline-danger">Logout</a>
  </div>
</nav>

<div class="container-fluid px-4 py-4">

  <?php if (isset($msg)): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if (isset($err)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card p-3 text-center">
        <div style="font-size:2rem">👥</div>
        <h4><?= count($users) ?></h4><small class="text-secondary">Total Users</small>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card p-3 text-center">
        <div style="font-size:2rem">📁</div>
        <h4><?= number_format($stats['total_files'] ?? 0) ?></h4><small class="text-secondary">Total Files</small>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card p-3 text-center">
        <div style="font-size:2rem">💾</div>
        <h4><?= formatBytes((int)($stats['total_size'] ?? 0)) ?></h4><small class="text-secondary">Storage Used</small>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card p-3 text-center">
        <div style="font-size:2rem">🔑</div>
        <h4><?= count(array_filter($users, fn($u)=>$u['role']==='admin')) ?></h4><small class="text-secondary">Admins</small>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <!-- Create User -->
    <div class="col-md-4">
      <div class="card p-4">
        <h6 class="fw-bold mb-3">➕ Create New User</h6>
        <form method="POST">
          <input type="hidden" name="action" value="create_user">
          <input type="text" name="username" class="form-control mb-2" placeholder="Username" required>
          <input type="email" name="email" class="form-control mb-2" placeholder="Email" required>
          <input type="password" name="password" class="form-control mb-2" placeholder="Password (min 8)" required minlength="8">
          <select name="role" class="form-select mb-2">
            <option value="user">User</option>
            <option value="admin">Admin</option>
          </select>
          <div class="input-group mb-3">
            <input type="number" name="quota_gb" class="form-control" value="5" min="1" max="1000">
            <span class="input-group-text bg-dark text-white border-secondary">GB quota</span>
          </div>
          <button type="submit" class="btn btn-primary w-100">Create User</button>
        </form>
      </div>
    </div>

    <!-- User Table -->
    <div class="col-md-8">
      <div class="card p-4">
        <h6 class="fw-bold mb-3">👥 All Users</h6>
        <table class="table table-sm">
          <thead><tr>
            <th>User</th><th>Role</th><th>Storage</th><th>Status</th><th>Actions</th>
          </tr></thead>
          <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($u['username']) ?></strong><br>
              <small class="text-secondary"><?= htmlspecialchars($u['email']) ?></small>
            </td>
            <td><span class="badge bg-<?= $u['role']==='admin'?'warning':'secondary' ?>"><?= $u['role'] ?></span></td>
            <td>
              <small><?= formatBytes((int)$u['storage_used']) ?> / <?= formatBytes((int)$u['storage_quota']) ?></small>
            </td>
            <td><span class="badge bg-<?= $u['is_active']?'success':'danger' ?>"><?= $u['is_active']?'Active':'Disabled' ?></span></td>
            <td>
              <?php if ($u['id'] !== $admin['id']): ?>
              <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="toggle_user">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button class="btn btn-xs btn-outline-<?= $u['is_active']?'danger':'success' ?> btn-sm py-0 px-1">
                  <?= $u['is_active']?'Disable':'Enable' ?>
                </button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Activity Log -->
    <div class="col-12">
      <div class="card p-4">
        <h6 class="fw-bold mb-3">📋 Recent Activity</h6>
        <table class="table table-sm">
          <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
          <tbody>
          <?php foreach ($activity as $a): ?>
          <tr>
            <td><small><?= date('M d H:i', strtotime($a['created_at'])) ?></small></td>
            <td><small><?= htmlspecialchars($a['username'] ?? 'System') ?></small></td>
            <td><small><?= htmlspecialchars($a['action']) ?></small></td>
            <td><small class="text-secondary"><?= htmlspecialchars(mb_strimwidth($a['details']??'',0,60,'…')) ?></small></td>
            <td><small class="text-secondary"><?= htmlspecialchars($a['ip_address']??'') ?></small></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</body>
</html>
