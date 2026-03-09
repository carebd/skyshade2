<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Support both authenticated downloads and public share links
$token = trim($_GET['token'] ?? '');

if ($token) {
    // Public shared file
    $file = DB::one("SELECT * FROM cd_files WHERE share_token=? AND is_public=1", [$token]);
    if (!$file) { http_response_code(404); die('File not found or sharing disabled.'); }
} else {
    $user = Auth::check();
    $id   = (int)($_GET['id'] ?? 0);
    $file = DB::one("SELECT * FROM cd_files WHERE id=? AND user_id=?", [$id, $user['id']]);
    if (!$file) { http_response_code(404); die('File not found.'); }
}

$full_path = STORAGE_PATH . '/' . $file['file_path'];
if (!file_exists($full_path)) { http_response_code(404); die('File missing from storage.'); }

DB::query("UPDATE cd_files SET downloads = downloads + 1 WHERE id=?", [$file['id']]);

$mime = $file['mime_type'] ?: 'application/octet-stream';
header('Content-Type: '        . $mime);
header('Content-Disposition: attachment; filename="' . addslashes($file['original_name']) . '"');
header('Content-Length: '      . filesize($full_path));
header('Cache-Control: no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

ob_clean(); flush();
readfile($full_path);
exit;
