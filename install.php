<?php
// CloudDrive Web Installer v1.0
// Visit this file once, then it self-destructs.

define('INSTALLER_RUNNING', true);
$step = isset($_POST['step']) ? (int)$_POST['step'] : 1;
$errors = [];
$success = false;

function testDBConnection($host, $user, $pass, $name) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        return $pdo;
    } catch (PDOException $e) {
        return $e->getMessage();
    }
}

function getServerRequirements() {
    return [
        'PHP >= 7.4'        => version_compare(PHP_VERSION, '7.4.0', '>='),
        'PDO MySQL'         => extension_loaded('pdo_mysql'),
        'FileInfo'          => extension_loaded('fileinfo'),
        'Mbstring'          => extension_loaded('mbstring'),
        'OpenSSL'           => extension_loaded('openssl'),
        'JSON'              => extension_loaded('json'),
        'storage/ writable' => is_writable(dirname(__FILE__)) || @mkdir(dirname(__FILE__).'/storage', 0755, true),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $db_host  = trim($_POST['db_host'] ?? 'localhost');
    $db_name  = trim($_POST['db_name'] ?? '');
    $db_user  = trim($_POST['db_user'] ?? '');
    $db_pass  = $_POST['db_pass'] ?? '';
    $site_url = rtrim(trim($_POST['site_url'] ?? ''), '/');
    $admin_u  = trim($_POST['admin_username'] ?? '');
    $admin_p  = $_POST['admin_password'] ?? '';
    $admin_e  = trim($_POST['admin_email'] ?? '');

    if (!$db_name) $errors[] = 'Database name is required.';
    if (!$db_user) $errors[] = 'Database user is required.';
    if (strlen($admin_u) < 3) $errors[] = 'Admin username must be at least 3 characters.';
    if (strlen($admin_p) < 8) $errors[] = 'Admin password must be at least 8 characters.';
    if (!filter_var($admin_e, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid admin email is required.';

    if (empty($errors)) {
        $pdo = testDBConnection($db_host, $db_user, $db_pass, $db_name);
        if (is_string($pdo)) {
            $errors[] = 'Database connection failed: ' . $pdo;
        } else {
            // Run SQL schema
            $schema = "
            CREATE TABLE IF NOT EXISTS `cd_users` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) NOT NULL UNIQUE,
                `email` VARCHAR(120) NOT NULL UNIQUE,
                `password_hash` VARCHAR(255) NOT NULL,
                `role` ENUM('admin','user') DEFAULT 'user',
                `storage_quota` BIGINT DEFAULT 5368709120,
                `storage_used` BIGINT DEFAULT 0,
                `is_active` TINYINT(1) DEFAULT 1,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `last_login` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `cd_folders` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT UNSIGNED NOT NULL,
                `parent_id` INT UNSIGNED DEFAULT NULL,
                `name` VARCHAR(255) NOT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY `user_id` (`user_id`),
                KEY `parent_id` (`parent_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `cd_files` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT UNSIGNED NOT NULL,
                `folder_id` INT UNSIGNED DEFAULT NULL,
                `original_name` VARCHAR(255) NOT NULL,
                `stored_name` VARCHAR(255) NOT NULL,
                `file_path` VARCHAR(500) NOT NULL,
                `file_size` BIGINT NOT NULL DEFAULT 0,
                `mime_type` VARCHAR(100) DEFAULT NULL,
                `share_token` VARCHAR(64) DEFAULT NULL UNIQUE,
                `is_public` TINYINT(1) DEFAULT 0,
                `downloads` INT DEFAULT 0,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY `user_id` (`user_id`),
                KEY `folder_id` (`folder_id`),
                KEY `share_token` (`share_token`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `cd_activity` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT UNSIGNED DEFAULT NULL,
                `action` VARCHAR(100) NOT NULL,
                `details` TEXT,
                `ip_address` VARCHAR(45),
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY `user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";

            try {
                foreach (explode(';', $schema) as $sql) {
                    $sql = trim($sql);
                    if ($sql) $pdo->exec($sql);
                }

                // Create admin user
                $hash = password_hash($admin_p, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $pdo->prepare("INSERT INTO cd_users (username, email, password_hash, role, storage_quota) VALUES (?, ?, ?, 'admin', ?) ON DUPLICATE KEY UPDATE role='admin'");
                $stmt->execute([$admin_u, $admin_e, $hash, 107374182400]); // 100GB for admin

                // Create storage directory
                $storage_dir = __DIR__ . '/storage';
                if (!is_dir($storage_dir)) mkdir($storage_dir, 0755, true);
                file_put_contents($storage_dir . '/.htaccess', "Deny from all\n");

                // Write config.php
                $config = "<?php\n";
                $config .= "// CloudDrive Configuration — Auto-generated by installer\n";
                $config .= "// DO NOT share this file.\n\n";
                $config .= "define('CD_INSTALLED', true);\n";
                $config .= "define('DB_HOST',  " . var_export($db_host, true) . ");\n";
                $config .= "define('DB_NAME',  " . var_export($db_name, true) . ");\n";
                $config .= "define('DB_USER',  " . var_export($db_user, true) . ");\n";
                $config .= "define('DB_PASS',  " . var_export($db_pass, true) . ");\n";
                $config .= "define('SITE_URL', " . var_export($site_url, true) . ");\n";
                $config .= "define('STORAGE_PATH', __DIR__ . '/storage');\n";
                $config .= "define('MAX_UPLOAD_SIZE', 524288000); // 500MB\n";
                $config .= "define('APP_NAME', 'CloudDrive');\n";
                $config .= "define('APP_VERSION', '1.0.0');\n";
                $config .= "define('SESSION_LIFETIME', 3600);\n";
                $config .= "define('ALLOWED_EXTENSIONS', 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar,7z,mp4,mp3,avi,mov');\n";

                file_put_contents(__DIR__ . '/config.php', $config);

                $success = true;
                // Self-delete installer
                @unlink(__FILE__);

            } catch (PDOException $e) {
                $errors[] = 'Schema creation failed: ' . $e->getMessage();
            }
        }
    }
}

$requirements = getServerRequirements();
$all_met = !in_array(false, $requirements, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CloudDrive Installer</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
  body { background: #0f172a; color: #e2e8f0; }
  .installer-card { max-width: 640px; margin: 60px auto; background: #1e293b; border-radius: 16px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.5); }
  .req-ok  { color: #22c55e; } .req-fail { color: #ef4444; }
  h1 span { color: #3b82f6; }
  .form-control, .form-select { background: #0f172a; border-color: #334155; color: #e2e8f0; }
  .form-control:focus { background: #0f172a; border-color: #3b82f6; color: #e2e8f0; box-shadow: 0 0 0 0.2rem rgba(59,130,246,.25); }
  label { color: #94a3b8; font-size: .85rem; margin-bottom: 4px; }
  .alert-success { background: #052e16; border-color: #166534; color: #86efac; }
  .alert-danger  { background: #450a0a; border-color: #991b1b; color: #fca5a5; }
  .alert-info    { background: #0c1a2e; border-color: #1e40af; color: #93c5fd; }
  code { background: #0f172a; padding: 2px 5px; border-radius: 4px; font-size: .8rem; color: #60a5fa; }
</style>
</head>
<body>
<div class="installer-card">
  <h1 class="mb-1 fw-bold">☁️ Cloud<span>Drive</span></h1>
  <p class="text-secondary mb-4">1-Click Installer — Shared Linux / LAMP Hosting</p>

  <?php if ($success): ?>
    <div class="alert alert-success">
      <h5>✅ Installation Complete!</h5>
      <p class="mb-2">Your CloudDrive is ready. The installer has been deleted for security.</p>
      <p class="mb-3 small">Log in with the admin username and password you set above.</p>
      <a href="login.php" class="btn btn-success btn-sm">Go to Login →</a>
    </div>
  <?php else: ?>

  <!-- Requirements Check -->
  <h6 class="text-uppercase text-secondary small mb-2">Server Requirements</h6>
  <ul class="list-unstyled mb-4">
    <?php foreach ($requirements as $req => $met): ?>
      <li class="<?= $met ? 'req-ok' : 'req-fail' ?>">
        <?= $met ? '✓' : '✗' ?> <?= htmlspecialchars($req) ?>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if (!$all_met): ?>
    <div class="alert alert-danger">Please fix the failed requirements before installing.</div>
  <?php else: ?>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $e): ?><div>⚠ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="POST">
    <input type="hidden" name="step" value="2">
    <h6 class="text-uppercase text-secondary small mb-3 mt-2">Database Configuration</h6>
    <div class="alert alert-info py-2 small mb-3">
      💡 <strong>cPanel / Shared Hosting tip:</strong> Database names and usernames always include your cPanel account prefix, e.g. <code>cpuser_cloudrive</code> — check <em>cPanel → MySQL® Databases</em> for the exact names.
    </div>
    <div class="row g-3 mb-3">
      <div class="col-6">
        <label>DB Host</label>
        <input type="text" name="db_host" class="form-control" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
      </div>
      <div class="col-6">
        <label>Database Name</label>
        <input type="text" name="db_name" class="form-control" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required>
      </div>
      <div class="col-6">
        <label>DB Username</label>
        <input type="text" name="db_user" class="form-control" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required>
      </div>
      <div class="col-6">
        <label>DB Password</label>
        <input type="password" name="db_pass" class="form-control">
      </div>
    </div>

    <h6 class="text-uppercase text-secondary small mb-3">Site & Admin Setup</h6>
    <div class="row g-3 mb-4">
      <div class="col-12">
        <label>Site URL (no trailing slash)</label>
        <input type="url" name="site_url" class="form-control" value="<?= htmlspecialchars($_POST['site_url'] ?? 'https://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF'])) ?>" required>
      </div>
      <div class="col-4">
        <label>Admin Username</label>
        <input type="text" name="admin_username" class="form-control" value="<?= htmlspecialchars($_POST['admin_username'] ?? 'Admin') ?>" required>
      </div>
      <div class="col-4">
        <label>Admin Password <small class="text-secondary">(min 8 chars)</small></label>
        <input type="password" name="admin_password" class="form-control" required minlength="8">
      </div>
      <div class="col-4">
        <label>Admin Email</label>
        <input type="email" name="admin_email" class="form-control" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required>
      </div>
    </div>

    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold" <?= $all_met ? '' : 'disabled' ?>>
      🚀 Install CloudDrive Now
    </button>
  </form>
  <?php endif; ?>
  <?php endif; ?>
</div>
</body>
</html>
