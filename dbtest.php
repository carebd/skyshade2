<?php
// ── CloudDrive DB Diagnostic Tool ──────────────────────────
// Upload to your server root, visit it once, then DELETE it.

$tests = [
    // Try all common host options
    ['host' => 'localhost',   'label' => 'localhost (socket)'],
    ['host' => '127.0.0.1',  'label' => '127.0.0.1 (TCP)'],
];

// ⬇ FILL IN YOUR ACTUAL VALUES FROM cPanel
$db_name = 'cpuser_cloudrive';   // Full name with prefix
$db_user = 'cpuser_cduser';      // Full username with prefix
$db_pass = 'YourPasswordHere';   // MySQL user password (NOT cPanel password)

echo "<h2>CloudDrive — MySQL Connection Diagnostic</h2>";
echo "<table border='1' cellpadding='8' style='border-collapse:collapse;font-family:monospace'>";
echo "<tr style='background:#333;color:#fff'><th>Host Tested</th><th>Result</th><th>Error</th></tr>";

foreach ($tests as $t) {
    try {
        $pdo = new PDO(
            "mysql:host={$t['host']};dbname={$db_name};charset=utf8mb4",
            $db_user, $db_pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );
        echo "<tr style='background:#d4edda'>
                <td>{$t['label']}</td>
                <td><strong>✅ SUCCESS</strong></td>
                <td>Connected! Use host: <strong>{$t['host']}</strong></td>
              </tr>";
    } catch (PDOException $e) {
        $code = $e->getCode();
        $msg  = $e->getMessage();
        // Interpret error
        $hint = match(true) {
            str_contains($msg, '1044') => '⚠ User not assigned to DB — Fix 3 in guide',
            str_contains($msg, '1045') => '⚠ Wrong username or password',
            str_contains($msg, '1049') => '⚠ Database name not found — check prefix',
            str_contains($msg, '2002') => '⚠ Host unreachable — try different host',
            str_contains($msg, '2003') => '⚠ Port 3306 blocked — use localhost',
            default => '⚠ Unknown — see error message'
        };
        echo "<tr style='background:#f8d7da'>
                <td>{$t['label']}</td>
                <td><strong>❌ FAILED [{$code}]</strong></td>
                <td>{$msg}<br><em>{$hint}</em></td>
              </tr>";
    }
}

echo "</table>";

// Also display PHP + server info
echo "<hr><h3>Server Environment</h3>";
echo "<pre>";
echo "PHP Version:   " . PHP_VERSION . "\n";
echo "PDO MySQL:     " . (extension_loaded('pdo_mysql') ? '✅ Loaded' : '❌ Missing') . "\n";
echo "Server:        " . ($_SERVER['HTTP_HOST'] ?? 'N/A') . "\n";
echo "</pre>";
echo "<p style='color:red'><strong>⚠ DELETE this file immediately after use!</strong></p>";
?>
```