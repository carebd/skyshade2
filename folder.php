<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$user = Auth::check();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $name      = trim($_POST['name'] ?? '');
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    if (strlen($name) < 1 || strlen($name) > 100) {
        header('Location: index.php&err=Invalid+folder+name.'); exit;
    }

    $name = preg_replace('/[<>:"/\\\\|?*]/', '', $name);

    $exists = DB::one(
        "SELECT id FROM cd_folders WHERE user_id=? AND parent_id ".($parent_id?"=?":"IS NULL")." AND name=?",
        $parent_id ? [$user['id'], $parent_id, $name] : [$user['id'], $name]
    );
    if ($exists) { header('Location: index.php'.($parent_id?"?folder=$parent_id":'').'&err=Folder+already+exists.'); exit; }

    DB::query("INSERT INTO cd_folders (user_id, parent_id, name) VALUES (?,?,?)", [$user['id'], $parent_id, $name]);
    logActivity($user['id'], 'create_folder', $name);
    header('Location: index.php'.($parent_id?"?folder=$parent_id":'').'&msg=Folder+created.'); exit;
}
header('Location: index.php'); exit;
