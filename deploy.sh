
#!/bin/bash

# Production Deployment Script for SmartOutreach Tracker
# Run this script on your Ubuntu VPS

echo "🚀 Starting SmartOutreach Tracker deployment..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
if [ "$EUID" -eq 0 ]; then
    print_error "Don't run this script as root!"
    exit 1
fi

# Update system
print_status "Updating system packages..."
sudo apt update && sudo apt upgrade -y

# Install Node.js 18
print_status "Installing Node.js 18..."
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs

# Install required packages
print_status "Installing system dependencies..."
sudo apt install -y nginx mysql-server php8.1-fpm php8.1-mysql php8.1-curl php8.1-json php8.1-mbstring php8.1-xml php8.1-zip ufw certbot python3-certbot-nginx git

# Configure firewall
print_status "Configuring firewall..."
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 8000/tcp  # For FastAPI
sudo ufw --force enable

# Setup MySQL
print_status "Setting up MySQL..."
sudo mysql_secure_installation

# Create database and user
print_status "Creating database..."
read -p "Enter MySQL root password: " -s MYSQL_ROOT_PASSWORD
echo
read -p "Enter database name [smartort_tracker]: " DB_NAME
DB_NAME=${DB_NAME:-smartort_tracker}
read -p "Enter database user [smartort_user]: " DB_USER
DB_USER=${DB_USER:-smartort_user}
read -p "Enter database password: " -s DB_PASSWORD
echo

mysql -u root -p"$MYSQL_ROOT_PASSWORD" << EOF
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF

# Import database schema
print_status "Importing database schema..."
mysql -u root -p"$MYSQL_ROOT_PASSWORD" $DB_NAME < database.sql

# Setup web directory
print_status "Setting up web directory..."
sudo mkdir -p /var/www/smartort
sudo chown -R $USER:www-data /var/www/smartort
sudo chmod -R 755 /var/www/smartort

# Copy PHP files
print_status "Copying application files..."
cp -r *.php /var/www/smartort/
cp -r *.js /var/www/smartort/
cp -r *.css /var/www/smartort/ 2>/dev/null || true

# Update config.php with database credentials
print_status "Updating configuration..."
cat > /var/www/smartort/config.php << EOF
<?php
// FORCE Pakistani timezone as default for ALL operations
date_default_timezone_set('Asia/Karachi');
ini_set('date.timezone', 'Asia/Karachi');

// Database configuration
\$host = "localhost";
\$dbname = "$DB_NAME";
\$username = "$DB_USER";
\$password = "$DB_PASSWORD";

// Create connection with Pakistani timezone
try {
    \$pdo = new PDO("mysql:host=\$host;dbname=\$dbname", \$username, \$password);
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    \$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Force MySQL to use Pakistani timezone
    \$pdo->exec("SET time_zone = '+05:00'");
    \$pdo->exec("SET SESSION time_zone = '+05:00'");
} catch(PDOException \$e) {
    die("Connection failed: " . \$e->getMessage());
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include all the helper functions from original config.php
// (Copy remaining functions from original config.php)
?>
EOF

# Build React app
print_status "Building React application..."
npm install
npm run build

# Copy React build to web directory
cp -r dist/* /var/www/smartort/

# Configure Nginx
print_status "Configuring Nginx..."
sudo tee /etc/nginx/sites-available/smartort << EOF
server {
    listen 80;
    listen [::]:80;
    
    server_name your-domain.com www.your-domain.com;
    root /var/www/smartort;
    index index.php index.html index.htm;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied expired no-cache no-store private no_etag auth;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;

    # Handle React routing
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # PHP handling
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    # Static files with long cache
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Deny access to sensitive files
    location ~ /\.(htaccess|htpasswd|env|git) {
        deny all;
    }

    # Block access to uploads directory for PHP files
    location ~* /uploads/.*\.php$ {
        deny all;
    }

    # Rate limiting
    limit_req_zone \$binary_remote_addr zone=login:10m rate=5r/m;
    location ~ /(login|dashboard|admin)\.php {
        limit_req zone=login burst=5 nodelay;
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    }
}
EOF

# Enable the site
sudo ln -sf /etc/nginx/sites-available/smartort /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

# Test and reload Nginx
sudo nginx -t && sudo systemctl reload nginx

# Configure PHP-FPM
print_status "Configuring PHP-FPM..."
sudo tee -a /etc/php/8.1/fpm/pool.d/www.conf << EOF

; Custom settings for SmartORT
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 300
php_admin_value[upload_max_filesize] = 100M
php_admin_value[post_max_size] = 100M
EOF

sudo systemctl restart php8.1-fpm

# Set correct permissions
print_status "Setting file permissions..."
sudo chown -R www-data:www-data /var/www/smartort
sudo find /var/www/smartort -type d -exec chmod 755 {} \;
sudo find /var/www/smartort -type f -exec chmod 644 {} \;
sudo chmod 600 /var/www/smartort/config.php

# Setup log rotation
print_status "Setting up log rotation..."
sudo tee /etc/logrotate.d/smartort << EOF
/var/www/smartort/logs/*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 0644 www-data www-data
}
EOF

# Create logs directory
sudo mkdir -p /var/www/smartort/logs
sudo chown www-data:www-data /var/www/smartort/logs

# Setup cron jobs
print_status "Setting up cron jobs..."
(crontab -l 2>/dev/null; echo "# SmartORT Background Tasks") | crontab -
(crontab -l 2>/dev/null; echo "*/5 * * * * curl -s http://localhost/cron_reset_locations.php > /dev/null 2>&1") | crontab -
(crontab -l 2>/dev/null; echo "0 2 * * * mysql -u$DB_USER -p$DB_PASSWORD $DB_NAME -e 'DELETE FROM locations WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY);'") | crontab -

# Install SSL certificate (optional)
read -p "Do you want to install SSL certificate with Let's Encrypt? (y/n): " INSTALL_SSL
if [ "$INSTALL_SSL" = "y" ]; then
    read -p "Enter your domain name: " DOMAIN
    sudo certbot --nginx -d $DOMAIN -d www.$DOMAIN
fi

# Create systemd service for monitoring
print_status "Creating monitoring service..."
sudo tee /etc/systemd/system/smartort-monitor.service << EOF
[Unit]
Description=SmartORT System Monitor
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/smartort
ExecStart=/usr/bin/php -f /var/www/smartort/system_monitor.php
Restart=always
RestartSec=30

[Install]
WantedBy=multi-user.target
EOF

# Enable and start services
print_status "Starting services..."
sudo systemctl enable nginx php8.1-fpm mysql smartort-monitor
sudo systemctl start smartort-monitor
sudo systemctl restart nginx php8.1-fpm

# Final security hardening
print_status "Applying security hardening..."

# Secure MySQL
sudo mysql -u root -p"$MYSQL_ROOT_PASSWORD" << EOF
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
FLUSH PRIVILEGES;
EOF

# Update system security
echo "net.ipv4.tcp_syncookies = 1" | sudo tee -a /etc/sysctl.conf
echo "net.ipv4.icmp_echo_ignore_broadcasts = 1" | sudo tee -a /etc/sysctl.conf
echo "net.ipv4.conf.all.log_martians = 1" | sudo tee -a /etc/sysctl.conf
sudo sysctl -p

print_status "🎉 Deployment completed successfully!"
print_status "🌐 Your application should be accessible at http://your-server-ip/"
print_warning "📝 Don't forget to:"
print_warning "   1. Update your domain name in Nginx config"
print_warning "   2. Configure your FastAPI endpoint URL"
print_warning "   3. Test all functionality"
print_warning "   4. Setup automated backups"
print_warning "   5. Monitor logs in /var/www/smartort/logs/"

echo
print_status "🔐 Default login credentials:"
print_status "   Username: developer"
print_status "   Password: Use the password from database.sql"

echo
print_status "📊 System Status:"
sudo systemctl status nginx --no-pager -l
sudo systemctl status php8.1-fpm --no-pager -l
sudo systemctl status mysql --no-pager -l

print_status "✅ Deployment script completed!"
EOF
