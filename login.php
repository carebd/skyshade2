<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

Auth::start();
if (Auth::isLoggedIn()) { header('Location: index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = Auth::login(trim($_POST['username'] ?? ''), $_POST['password'] ?? '');
    if ($result === true) { header('Location: index.php'); exit; }
    $error = $result;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — CloudDrive</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
  body { background: #0f172a; color: #e2e8f0; min-height: 100vh; display:flex; align-items:center; justify-content:center; }
  .card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 40px; width: 400px; }
  .form-control { background: #0f172a; border-color: #334155; color: #e2e8f0; }
  .form-control:focus { background: #0f172a; border-color: #3b82f6; color: #e2e8f0; box-shadow: none; }
  label { color: #94a3b8; font-size: .85rem; }
  h2 span { color: #3b82f6; }
</style>
</head>
<body>
<div class="card shadow-lg">
  <h2 class="fw-bold mb-1">☁️ Cloud<span>Drive</span></h2>
  <p class="text-secondary small mb-4">Your private file storage</p>
  <?php if ($_GET['timeout'] ?? false): ?>
    <div class="alert alert-warning py-2 small">Session expired. Please log in again.</div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <div class="mb-3">
      <label>Username or Email</label>
      <input type="text" name="username" class="form-control mt-1" autofocus required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
    </div>
    <div class="mb-4">
      <label>Password</label>
      <input type="password" name="password" class="form-control mt-1" required>
    </div>
    <button type="submit" class="btn btn-primary w-100 py-2">Sign In</button>
  </form>
</div>
</body>
</html>
