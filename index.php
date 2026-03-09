<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$user      = Auth::check();
$folder_id = isset($_GET['folder']) ? (int)$_GET['folder'] : null;
$search    = trim($_GET['search'] ?? '');

// Validate folder belongs to user
if ($folder_id) {
    $folder = DB::one("SELECT * FROM cd_folders WHERE id=? AND user_id=?", [$folder_id, $user['id']]);
    if (!$folder) { header('Location: index.php'); exit; }
}

// Fetch subfolders
$folder_sql    = "SELECT * FROM cd_folders WHERE user_id=? AND parent_id " . ($folder_id ? "=?" : "IS NULL") . " ORDER BY name";
$folder_params = $folder_id ? [$user['id'], $folder_id] : [$user['id']];
$folders       = DB::all($folder_sql, $folder_params);

// Fetch files
if ($search) {
    $files = DB::all("SELECT * FROM cd_files WHERE user_id=? AND original_name LIKE ? ORDER BY created_at DESC",
        [$user['id'], "%$search%"]);
} else {
    $file_sql    = "SELECT * FROM cd_files WHERE user_id=? AND folder_id " . ($folder_id ? "=?" : "IS NULL") . " ORDER BY created_at DESC";
    $file_params = $folder_id ? [$user['id'], $folder_id] : [$user['id']];
    $files       = DB::all($file_sql, $file_params);
}

$breadcrumb = $folder_id ? getFolderPath($folder_id, $user['id']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>CloudDrive — My Files</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-dark px-4 py-2">
  <span class="navbar-brand fw-bold">☁️ CloudDrive</span>
  <div class="d-flex align-items-center gap-3">
    <form class="d-flex" method="GET">
      <input class="form-control form-control-sm search-box" type="search" name="search" placeholder="Search files…" value="<?= htmlspecialchars($search) ?>">
    </form>
    <span class="text-secondary small">👤 <?= htmlspecialchars($user['username']) ?></span>
    <?php if ($user['role']==='admin'): ?>
      <a href="admin.php" class="btn btn-sm btn-outline-warning">Admin</a>
    <?php endif; ?>
    <a href="logout.php" class="btn btn-sm btn-outline-danger">Logout</a>
  </div>
</nav>

<div class="container-fluid px-4 py-3">
  <div class="row">

    <!-- Sidebar -->
    <div class="col-md-2 sidebar py-3">
      <button class="btn btn-primary w-100 mb-3 upload-btn" data-bs-toggle="modal" data-bs-target="#uploadModal">
        + Upload Files
      </button>
      <button class="btn btn-outline-secondary w-100 mb-4" data-bs-toggle="modal" data-bs-target="#newFolderModal">
        📁 New Folder
      </button>
      <div class="px-1">
        <?= storageBar((int)$user['storage_used'], (int)$user['storage_quota']) ?>
      </div>
    </div>

    <!-- Main Content -->
    <div class="col-md-10 main-content py-3">

      <!-- Breadcrumb -->
      <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">My Drive</a></li>
          <?php foreach ($breadcrumb as $bc): ?>
            <li class="breadcrumb-item <?= ($bc['id']==$folder_id) ? 'active' : '' ?>">
              <?php if ($bc['id'] != $folder_id): ?>
                <a href="index.php?folder=<?= $bc['id'] ?>"><?= htmlspecialchars($bc['name']) ?></a>
              <?php else: echo htmlspecialchars($bc['name']); endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      </nav>

      <?php if ($search): ?>
        <div class="alert alert-info py-2 small">Search results for: <strong><?= htmlspecialchars($search) ?></strong> — <a href="index.php">Clear</a></div>
      <?php endif; ?>

      <!-- Flash messages -->
      <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible py-2 small fade show">
          <?= htmlspecialchars($_GET['msg']) ?>
          <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      <?php if (isset($_GET['err'])): ?>
        <div class="alert alert-danger alert-dismissible py-2 small fade show">
          <?= htmlspecialchars($_GET['err']) ?>
          <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <!-- Folders Grid -->
      <?php if ($folders): ?>
      <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-3 mb-4">
        <?php foreach ($folders as $f): ?>
        <div class="col">
          <div class="file-card folder-card">
            <div class="file-icon">📁</div>
            <div class="file-name" title="<?= htmlspecialchars($f['name']) ?>">
              <a href="index.php?folder=<?= $f['id'] ?>" class="stretched-link text-decoration-none">
                <?= htmlspecialchars($f['name']) ?>
              </a>
            </div>
            <div class="file-actions">
              <a href="delete.php?type=folder&id=<?= $f['id'] ?>"
                 class="text-danger small" onclick="return confirm('Delete folder and all contents?')">🗑</a>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Files Grid -->
      <?php if ($files): ?>
      <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-3">
        <?php foreach ($files as $f): ?>
        <div class="col">
          <div class="file-card">
            <div class="file-icon"><?= getMimeIcon($f['mime_type'] ?? '') ?></div>
            <div class="file-name" title="<?= htmlspecialchars($f['original_name']) ?>">
              <?= htmlspecialchars(mb_strimwidth($f['original_name'], 0, 22, '…')) ?>
            </div>
            <div class="file-size"><?= formatBytes((int)$f['file_size']) ?></div>
            <div class="file-actions d-flex justify-content-center gap-2 mt-1">
              <a href="download.php?id=<?= $f['id'] ?>" title="Download" class="text-primary">⬇</a>
              <a href="share.php?id=<?= $f['id'] ?>" title="Share Link" class="text-success">🔗</a>
              <a href="delete.php?type=file&id=<?= $f['id'] ?>"
                 title="Delete" class="text-danger" onclick="return confirm('Delete this file?')">🗑</a>
            </div>
            <?php if ($f['is_public']): ?>
              <div class="text-center mt-1"><span class="badge bg-success" style="font-size:.65rem">Shared</span></div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php elseif (!$folders): ?>
      <div class="empty-state">
        <div style="font-size:4rem">☁️</div>
        <h5 class="mt-2">This folder is empty</h5>
        <p class="text-secondary small">Upload your first file to get started.</p>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header border-secondary">
        <h5 class="modal-title">Upload Files</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form action="upload.php" method="POST" enctype="multipart/form-data" id="uploadForm">
          <input type="hidden" name="folder_id" value="<?= $folder_id ?: '' ?>">
          <div class="upload-zone" id="uploadZone">
            <div>📂 Drag & drop files here or click to browse</div>
            <div class="text-secondary small mt-1">Max <?= formatBytes(MAX_UPLOAD_SIZE) ?> per file</div>
          </div>
          <input type="file" name="files[]" id="fileInput" multiple style="display:none">
          <div id="fileList" class="mt-3"></div>
          <div id="uploadProgress" class="mt-2" style="display:none">
            <div class="progress"><div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" style="width:0%"></div></div>
          </div>
        </form>
      </div>
      <div class="modal-footer border-secondary">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="startUpload">Upload</button>
      </div>
    </div>
  </div>
</div>

<!-- New Folder Modal -->
<div class="modal fade" id="newFolderModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header border-secondary">
        <h5 class="modal-title">New Folder</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form action="folder.php" method="POST">
        <div class="modal-body">
          <input type="hidden" name="action" value="create">
          <input type="hidden" name="parent_id" value="<?= $folder_id ?: '' ?>">
          <input type="text" name="name" class="form-control bg-dark text-white border-secondary"
                 placeholder="Folder name" required autofocus maxlength="100">
        </div>
        <div class="modal-footer border-secondary">
          <button type="submit" class="btn btn-primary w-100">Create Folder</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
