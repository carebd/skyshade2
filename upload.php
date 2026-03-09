<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$user      = Auth::check();
$folder_id = !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;
$redirect  = 'index.php' . ($folder_id ? "?folder=$folder_id" : '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['files'])) {
    header("Location: $redirect"); exit;
}

$errors   = [];
$uploaded = 0;

foreach ($_FILES['files']['tmp_name'] as $i => $tmp) {
    if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;

    $original = sanitizeFilename($_FILES['files']['name'][$i]);
    if (!$original) continue;

    if (!isAllowedExtension($original)) {
        $errors[] = "$original: File type not allowed.";
        continue;
    }

    $size = $_FILES['files']['size'][$i];
    if ($size > MAX_UPLOAD_SIZE) {
        $errors[] = "$original: Exceeds max size (" . formatBytes(MAX_UPLOAD_SIZE) . ").";
        continue;
    }

    // Check storage quota
    if (($user['storage_used'] + $size) > $user['storage_quota']) {
        $errors[] = "$original: Storage quota exceeded.";
        continue;
    }

    // Create user storage directory
    $user_dir = STORAGE_PATH . '/' . $user['id'];
    if (!is_dir($user_dir)) mkdir($user_dir, 0755, true);

    $ext         = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $stored_name = generateToken(16) . '.' . $ext;
    $full_path   = $user_dir . '/' . $stored_name;
    $rel_path    = $user['id'] . '/' . $stored_name;

    if (!move_uploaded_file($tmp, $full_path)) {
        $errors[] = "$original: Failed to save file.";
        continue;
    }

    $mime = mime_content_type($full_path) ?: 'application/octet-stream';

    DB::query(
        "INSERT INTO cd_files (user_id,folder_id,original_name,stored_name,file_path,file_size,mime_type) VALUES (?,?,?,?,?,?,?)",
        [$user['id'], $folder_id, $original, $stored_name, $rel_path, $size, $mime]
    );

    // Update quota
    DB::query("UPDATE cd_users SET storage_used = storage_used + ? WHERE id=?", [$size, $user['id']]);
    $user['storage_used'] += $size;

    logActivity($user['id'], 'upload', "$original ($size bytes)");
    $uploaded++;
}

if ($errors) {
    header("Location: $redirect&err=" . urlencode(implode('; ', $errors))); exit;
}
header("Location: $redirect&msg=" . urlencode("$uploaded file(s) uploaded successfully.")); exit;
