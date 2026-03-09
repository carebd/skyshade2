<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$user = Auth::check();
$id   = (int)($_GET['id'] ?? 0);
$file = DB::one("SELECT * FROM cd_files WHERE id=? AND user_id=?", [$id, $user['id']]);

if (!$file) { header('Location: index.php'); exit; }

// Toggle share / generate token
if ($file['is_public'] && $file['share_token']) {
    // Revoke
    if (isset($_GET['revoke'])) {
        DB::query("UPDATE cd_files SET is_public=0, share_token=NULL WHERE id=?", [$id]);
        header('Location: index.php&msg='.urlencode('Share link revoked.')); exit;
    }
    $share_url = SITE_URL . '/download.php?token=' . $file['share_token'];
} else {
    // Generate new token
    $token = generateToken(32);
    DB::query("UPDATE cd_files SET is_public=1, share_token=? WHERE id=?", [$token, $id]);
    $file['share_token'] = $token;
    $share_url = SITE_URL . '/download.php?token=' . $token;
    logActivity($user['id'], 'share', $file['original_name']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Share — <?= htmlspecialchars($file['original_name']) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
  body { background: #0f172a; color: #e2e8f0; display:flex; align-items:center; justify-content:center; min-height:100vh; }
  .card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 36px; max-width: 540px; width: 100%; }
  .share-url { background: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 12px; font-family: monospace; font-size: .85rem; word-break: break-all; color: #60a5fa; }
</style>
</head>
<body>
<div class="card">
  <h5 class="fw-bold mb-1">🔗 Share File</h5>
  <p class="text-secondary small mb-3"><?= htmlspecialchars($file['original_name']) ?></p>

  <p class="small text-secondary mb-2">Anyone with this link can download the file:</p>
  <div class="share-url mb-3" id="shareUrl"><?= htmlspecialchars($share_url) ?></div>

  <div class="d-flex gap-2 flex-wrap">
    <button class="btn btn-primary btn-sm" onclick="copyLink()">📋 Copy Link</button>
    <a href="share.php?id=<?= $id ?>&revoke=1" class="btn btn-outline-danger btn-sm" onclick="return confirm('Revoke this share link?')">🚫 Revoke Link</a>
    <a href="index.php" class="btn btn-outline-secondary btn-sm">← Back to Drive</a>
  </div>
  <div id="copyMsg" class="text-success small mt-2" style="display:none">✅ Copied to clipboard!</div>
</div>
<script>
function copyLink() {
  navigator.clipboard.writeText(document.getElementById('shareUrl').innerText)
    .then(() => { document.getElementById('copyMsg').style.display='block'; });
}
</script>
</body>
</html>
