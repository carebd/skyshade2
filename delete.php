<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$user = Auth::check();
$type = $_GET['type'] ?? '';
$id   = (int)($_GET['id'] ?? 0);

if ($type === 'file') {
    $file = DB::one("SELECT * FROM cd_files WHERE id=? AND user_id=?", [$id, $user['id']]);
    if ($file) {
        $path = STORAGE_PATH . '/' . $file['file_path'];
        if (file_exists($path)) @unlink($path);
        DB::query("DELETE FROM cd_files WHERE id=?", [$id]);
        DB::query("UPDATE cd_users SET storage_used = GREATEST(0, storage_used - ?) WHERE id=?",
            [$file['file_size'], $user['id']]);
        logActivity($user['id'], 'delete_file', $file['original_name']);
        $redirect = $file['folder_id'] ? "index.php?folder={$file['folder_id']}" : 'index.php';
        header("Location: $redirect&msg=File+deleted."); exit;
    }
} elseif ($type === 'folder') {
    $folder = DB::one("SELECT * FROM cd_folders WHERE id=? AND user_id=?", [$id, $user['id']]);
    if ($folder) {
        // Delete all files in folder recursively
        $files = DB::all("SELECT * FROM cd_files WHERE user_id=? AND folder_id=?", [$user['id'], $id]);
        $freed = 0;
        foreach ($files as $f) {
            $path = STORAGE_PATH . '/' . $f['file_path'];
            if (file_exists($path)) @unlink($path);
            $freed += $f['file_size'];
        }
        DB::query("DELETE FROM cd_files WHERE user_id=? AND folder_id=?", [$user['id'], $id]);
        DB::query("DELETE FROM cd_folders WHERE id=? AND user_id=?", [$id, $user['id']]);
        if ($freed) DB::query("UPDATE cd_users SET storage_used=GREATEST(0,storage_used-?) WHERE id=?", [$freed, $user['id']]);
        $redirect = $folder['parent_id'] ? "index.php?folder={$folder['parent_id']}" : 'index.php';
        header("Location: $redirect&msg=Folder+deleted."); exit;
    }
}
header('Location: index.php&err=Item+not+found.'); exit;
