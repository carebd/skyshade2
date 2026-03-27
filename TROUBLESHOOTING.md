This is a very common issue on shared Linux/cPanel hosting. There are **5 possible root causes** — work through each one systematically.

***

## Root Cause Diagnosis

The error pattern from your previous session (`SQLSTATE[HY000] [1044]`, `[1045]`, or `[2002]`) each point to a different cause:

| Error Code | Meaning | Fix Needed |
|---|---|---|
| `[1044]` Access denied | User exists but has no privileges on the DB | Assign user to DB in cPanel |
| `[1045]` Access denied | Wrong username or password | Check credentials |
| `[2002]` Connection refused | Wrong DB host | Change to `localhost` or correct hostname |
| `[1049]` Unknown database | DB name is wrong / has a prefix | Confirm full DB name in cPanel |
| `[2003]` Can't connect | Port 3306 blocked | Use `localhost` not IP address |

 [unitech](https://www.unitech.qa/announcements/79/Troubleshooting-Database-Connection-Problems-in-cPanel.html)

***

## Fix 1 — Confirm the Correct DB Host

On **shared hosting**, the DB host is almost **never** an IP address. [domainindia](https://www.domainindia.com/client/knowledgebase/587/Resolving-MySQL-Connection-Refused-Error-for-Shared-Hosting-Clients.html)

In your CloudDrive `install.php`, enter:
- ✅ **`localhost`** — works on virtually all shared hosts
- ✅ **`127.0.0.1`** — try this if `localhost` fails
- ✅ **Your domain name** (e.g. `yourdomain.com`) — some hosts require this

To find your actual MySQL hostname: [hostgator](https://www.hostgator.com/help/article/troubleshooting-mysql-database-connection-issues)
1. Log in to **cPanel**
2. Scroll to the **Databases** section
3. Click **phpMyAdmin** — the hostname shown in the URL bar is your correct DB host
4. Alternatively, go to **cPanel → MySQL Databases** and scroll down — some hosts show a "MySQL Server" hostname there

***

## Fix 2 — Verify the Exact Database Name (Prefix Issue)

On cPanel, **every database name is automatically prefixed** with your cPanel username.  This is the most commonly missed issue. [hostgator](https://www.hostgator.com/help/article/troubleshooting-mysql-database-connection-issues)

**Example:**
- You type `cloudrive` in the installer
- cPanel actually creates `cpusername_cloudrive`
- The installer fails because `cloudrive` (without prefix) does not exist

**How to check:**
1. Go to **cPanel → MySQL® Databases**
2. Under the **Databases** list, look at the exact full name shown — it will look like `cpuser_cloudrive`
3. Use that **full name** (including the prefix) in `install.php`

***

## Fix 3 — Assign the User to the Database

Even if both the user and the database exist in cPanel, they are **not connected** until you link them. [liquidweb](https://www.liquidweb.com/blog/how-to-assign-a-user-to-a-mysql-database-in-cpanel/)

Follow these steps exactly:

```
cPanel → MySQL® Databases
  └── Scroll to: "Add User To Database"
        ├── User:     [select your DB user from dropdown]
        ├── Database: [select your DB from dropdown]
        └── Click "Add"

On the next page:
  └── Select "ALL PRIVILEGES" ← tick every checkbox
        └── Click "Make Changes"
```

***

## Fix 4 — Create a Quick Test File on Your Server

Upload this file as `dbtest.php` to your hosting folder to diagnose the exact issue live: [forums.24x7servermanagement](https://forums.24x7servermanagement.com/cpanel-whm/general-issues/1277-error-establishing-a-database-connection)

```php
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

Visit `https://yourdomain.com/dbtest.php` — it will show you exactly which host works and what the precise error is. [unitech](https://www.unitech.qa/announcements/79/Troubleshooting-Database-Connection-Problems-in-cPanel.html)

***

## Fix 5 — Update `install.php` Fields Correctly

Once `dbtest.php` shows a green **SUCCESS**, use exactly the same values in `install.php`:

```
┌─────────────────────────────────────────────────┐
│  DB Host:     localhost   (or 127.0.0.1)        │
│  DB Name:     cpuser_cloudrive  ← FULL name     │
│  DB User:     cpuser_cduser     ← FULL username │
│  DB Password: [MySQL user password, not cPanel] │
│  Site URL:    https://yourdomain.com/cloudrive  │
└─────────────────────────────────────────────────┘
```

> **⚠ Important Security Note:** The **DB Password** is the password you set for the MySQL user inside cPanel's MySQL section — it is **not** your cPanel account login password. These are two separate credentials.  After successful installation, delete `dbtest.php` immediately as it exposes your database credentials in plain text. [forums.24x7servermanagement](https://forums.24x7servermanagement.com/cpanel-whm/general-issues/1277-error-establishing-a-database-connection)