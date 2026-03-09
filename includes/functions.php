<?php
function formatBytes(int $bytes, int $precision = 2): string {
    $units = ['B','KB','MB','GB','TB'];
    for ($i = 0; $bytes >= 1024 && $i < 4; $i++) $bytes /= 1024;
    return round($bytes, $precision) . ' ' . $units[$i];
}

function getMimeIcon(string $mime): string {
    if (str_starts_with($mime, 'image/'))  return '🖼️';
    if (str_starts_with($mime, 'video/'))  return '🎬';
    if (str_starts_with($mime, 'audio/'))  return '🎵';
    if ($mime === 'application/pdf')       return '📕';
    if (str_contains($mime, 'zip') || str_contains($mime, 'rar')) return '🗜️';
    if (str_contains($mime, 'word') || str_contains($mime, 'document')) return '📄';
    if (str_contains($mime, 'sheet') || str_contains($mime, 'excel'))   return '📊';
    if (str_contains($mime, 'presentation'))                             return '📊';
    return '📁';
}

function sanitizeFilename(string $name): string {
    $name = preg_replace('/[^a-zA-Z0-9._\- ]/', '', $name);
    return substr(trim($name), 0, 200);
}

function generateToken(int $length = 32): string {
    return bin2hex(random_bytes($length));
}

function storageBar(int $used, int $quota): string {
    $pct = $quota > 0 ? min(100, round($used / $quota * 100)) : 0;
    $color = $pct > 85 ? 'danger' : ($pct > 65 ? 'warning' : 'primary');
    return "<div class='progress' style='height:6px'>
              <div class='progress-bar bg-{$color}' style='width:{$pct}%'></div>
            </div>
            <small class='text-secondary'>" . formatBytes($used) . " of " . formatBytes($quota) . " used ({$pct}%)</small>";
}

function logActivity(int $userId, string $action, string $details = ''): void {
    DB::query("INSERT INTO cd_activity (user_id,action,details,ip_address) VALUES (?,?,?,?)",
        [$userId, $action, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
}

function isAllowedExtension(string $filename): bool {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $allowed = explode(',', ALLOWED_EXTENSIONS);
    return in_array($ext, $allowed);
}

function getFolderPath(int $folderId, int $userId): array {
    $path = [];
    $current = $folderId;
    $visited = [];
    while ($current) {
        if (in_array($current, $visited)) break;
        $visited[] = $current;
        $folder = DB::one("SELECT id, name, parent_id FROM cd_folders WHERE id=? AND user_id=?", [$current, $userId]);
        if (!$folder) break;
        array_unshift($path, $folder);
        $current = $folder['parent_id'];
    }
    return $path;
}
