#!/usr/bin/env bash
# CloudDrive Auto-Installer for Ubuntu/Debian VPS
# Usage: sudo bash install.sh
set -e

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'

echo -e "${BLUE}"
echo "  ☁️  CloudDrive Auto-Installer"
echo "  ================================"
echo -e "${NC}"

# ── Collect inputs ───────────────────────────────────────────────────────────
read -rp "Enter domain or IP (e.g. yourdomain.com): " DOMAIN
read -rp "Enter MySQL root password to set: " -s DB_ROOT_PASS; echo
read -rp "Enter CloudDrive DB name [cloudrive]: " DB_NAME; DB_NAME=${DB_NAME:-cloudrive}
read -rp "Enter CloudDrive DB user [cduser]: "   DB_USER; DB_USER=${DB_USER:-cduser}
read -rp "Enter CloudDrive DB password: " -s DB_PASS; echo
read -rp "Enter admin username [admin]: " ADMIN_USER; ADMIN_USER=${ADMIN_USER:-admin}
read -rp "Enter admin email: " ADMIN_EMAIL
read -rp "Enter admin password (min 8 chars): " -s ADMIN_PASS; echo

WEB_ROOT="/var/www/cloudrive"
SITE_URL="https://$DOMAIN"

echo -e "\n${YELLOW}[1/8] Updating system packages...${NC}"
apt-get update -qq && apt-get upgrade -y -qq

echo -e "${YELLOW}[2/8] Installing LAMP stack...${NC}"
DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
  apache2 mysql-server php8.1 php8.1-mysql php8.1-gd php8.1-curl \
  php8.1-mbstring php8.1-xml php8.1-zip php8.1-intl php8.1-json \
  php8.1-fileinfo libapache2-mod-php8.1 unzip curl wget openssl

echo -e "${YELLOW}[3/8] Configuring MySQL...${NC}"
mysql --user=root <<EOF
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${DB_ROOT_PASS}';
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF

echo -e "${YELLOW}[4/8] Setting up web directory...${NC}"
mkdir -p "$WEB_ROOT"
mkdir -p "$WEB_ROOT/storage"
mkdir -p "$WEB_ROOT/includes"
mkdir -p "$WEB_ROOT/assets/css"
mkdir -p "$WEB_ROOT/assets/js"
echo "Deny from all" > "$WEB_ROOT/storage/.htaccess"

echo -e "${YELLOW}[5/8] Generating config.php...${NC}"
cat > "$WEB_ROOT/config.php" <<PHPEOF
<?php
define('CD_INSTALLED', true);
define('DB_HOST',  'localhost');
define('DB_NAME',  '${DB_NAME}');
define('DB_USER',  '${DB_USER}');
define('DB_PASS',  '${DB_PASS}');
define('SITE_URL', '${SITE_URL}');
define('STORAGE_PATH', __DIR__ . '/storage');
define('MAX_UPLOAD_SIZE', 524288000);
define('APP_NAME',  'CloudDrive');
define('APP_VERSION', '1.0.0');
define('SESSION_LIFETIME', 3600);
define('ALLOWED_EXTENSIONS', 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar,7z,mp4,mp3,avi,mov');
PHPEOF

echo -e "${YELLOW}[6/8] Running database schema...${NC}"
mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" <<'SQLEOF'
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
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
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
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cd_activity` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT,
  `ip_address` VARCHAR(45),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQLEOF

# Insert admin user via PHP (for proper bcrypt hashing)
php8.1 -r "
require '${WEB_ROOT}/config.php';
require '${WEB_ROOT}/includes/db.php';
\$hash = password_hash('${ADMIN_PASS}', PASSWORD_BCRYPT, ['cost'=>12]);
DB::query('INSERT INTO cd_users (username,email,password_hash,role,storage_quota) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE role=\"admin\"',
  ['${ADMIN_USER}','${ADMIN_EMAIL}',\$hash,'admin',107374182400]);
echo 'Admin user created.' . PHP_EOL;
"

echo -e "${YELLOW}[7/8] Configuring Apache virtual host...${NC}"
cat > "/etc/apache2/sites-available/cloudrive.conf" <<APACHEEOF
<VirtualHost *:80>
    ServerName ${DOMAIN}
    DocumentRoot ${WEB_ROOT}
    <Directory ${WEB_ROOT}>
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/cloudrive_error.log
    CustomLog \${APACHE_LOG_DIR}/cloudrive_access.log combined
</VirtualHost>
APACHEEOF

a2ensite cloudrive.conf
a2enmod rewrite headers deflate expires
a2dissite 000-default.conf 2>/dev/null || true

# PHP tuning
PHP_INI=$(php8.1 --ini | grep "Loaded Configuration" | awk '{print $NF}')
sed -i 's/upload_max_filesize = .*/upload_max_filesize = 500M/' "$PHP_INI"
sed -i 's/post_max_size = .*/post_max_size = 510M/' "$PHP_INI"
sed -i 's/memory_limit = .*/memory_limit = 256M/' "$PHP_INI"
sed -i 's/max_execution_time = .*/max_execution_time = 300/' "$PHP_INI"

chown -R www-data:www-data "$WEB_ROOT"
chmod -R 755 "$WEB_ROOT"
chmod -R 770 "$WEB_ROOT/storage"

systemctl restart apache2

echo -e "${YELLOW}[8/8] Installing SSL certificate (Let's Encrypt)...${NC}"
apt-get install -y -qq certbot python3-certbot-apache
certbot --apache -d "$DOMAIN" --non-interactive --agree-tos -m "$ADMIN_EMAIL" || \
  echo -e "${YELLOW}SSL skipped (DNS may not be configured yet). Run: certbot --apache -d ${DOMAIN}${NC}"

echo -e "\n${GREEN}✅ CloudDrive installation complete!${NC}"
echo -e "${GREEN}   URL:      ${SITE_URL}${NC}"
echo -e "${GREEN}   Admin:    ${ADMIN_USER}${NC}"
echo -e "${GREEN}   Web Root: ${WEB_ROOT}${NC}"
echo -e "${YELLOW}   ⚠ Delete install.sh now: rm install.sh${NC}\n"
