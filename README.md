
# SmartOutreach Tracker - Production Ready

A comprehensive attendance tracking system with real-time location monitoring, built with React, Capacitor, PHP, and MySQL.

## 🌟 Features

- **Real-time Location Tracking**: WhatsApp-like live location sharing
- **Background Location Updates**: Works even when app is closed/killed
- **Cross-platform**: Web, Android, iOS support via Capacitor
- **Admin Dashboard**: Real-time monitoring of all users
- **Automated Check-out**: Configurable auto checkout after hours
- **Security Features**: Device tracking, IP logging, user flagging
- **Production Ready**: Optimized database, caching, monitoring

## 📱 Mobile App Capabilities

- Native background location tracking
- Push notifications
- Offline support
- Battery optimization
- High accuracy GPS

## 🚀 Quick Deployment on Ubuntu VPS

### Prerequisites
- Ubuntu 20.04+ VPS with sudo access
- Domain name (optional, for SSL)
- At least 2GB RAM and 20GB disk space

### 1. Clone and Setup

```bash
# Clone the repository
git clone <your-repo-url>
cd smartoutreach-tracker

# Make deployment script executable
chmod +x deploy.sh

# Run deployment script
./deploy.sh
```

### 2. Manual Installation Steps

If you prefer manual installation:

#### Install Dependencies
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Node.js 18
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs

# Install system packages
sudo apt install -y nginx mysql-server php8.1-fpm php8.1-mysql php8.1-curl php8.1-json php8.1-mbstring php8.1-xml php8.1-zip ufw git
```

#### Setup Database
```bash
# Secure MySQL installation
sudo mysql_secure_installation

# Create database and user
sudo mysql -u root -p
```

```sql
CREATE DATABASE smartort_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'smartort_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON smartort_tracker.* TO 'smartort_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

```bash
# Import database schema
mysql -u root -p smartort_tracker < database.sql
```

#### Build and Deploy Application
```bash
# Install dependencies and build React app
npm install
npm run build

# Setup web directory
sudo mkdir -p /var/www/smartort
sudo cp -r * /var/www/smartort/
sudo chown -R www-data:www-data /var/www/smartort
```

#### Configure Nginx
Create `/etc/nginx/sites-available/smartort`:
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/smartort;
    index index.php index.html;
    
    # PHP handling
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    }
    
    # React routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

```bash
# Enable site
sudo ln -s /etc/nginx/sites-available/smartort /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl restart nginx
```

### 3. Mobile App Setup

For native mobile app functionality:

#### Android Setup
```bash
# Add Android platform
npx cap add android

# Sync project
npx cap sync android

# Open in Android Studio
npx cap open android
```

#### iOS Setup (Mac only)
```bash
# Add iOS platform  
npx cap add ios

# Sync project
npx cap sync ios

# Open in Xcode
npx cap open ios
```

## 🔧 Configuration

### Environment Variables
Update `config.php` with your settings:

```php
$host = "localhost";
$dbname = "smartort_tracker";
$username = "smartort_user";
$password = "your_secure_password";
$fastapi_base_url = "http://your-fastapi-server:8000";
```

### FastAPI Configuration
Update the FastAPI URL in:
- `src/services/MobileLocationService.ts`
- `config.php`
- Admin settings panel

### Capacitor Configuration
Update `capacitor.config.ts` for production:

```typescript
const config: CapacitorConfig = {
  appId: 'com.yourcompany.smartort',
  appName: 'SmartORT Tracker',
  webDir: 'dist',
  server: {
    url: 'https://your-domain.com',
    cleartext: false  // Set to true for HTTP
  },
  plugins: {
    Geolocation: {
      enableBackground: true,
      backgroundPermissionRationale: "Required for attendance tracking"
    }
  }
};
```

## 🔒 Security Features

### Device Tracking
- Unique device fingerprinting
- Multiple device login prevention
- IP address logging
- User agent tracking

### User Flagging System
- Automatic flagging for suspicious activity
- Admin review and approval system
- Device lock functionality

### Data Protection
- Encrypted passwords (bcrypt)
- SQL injection protection
- XSS prevention
- CSRF protection

## 📊 Monitoring & Maintenance

### System Monitor
The system includes an automated monitor that:
- Checks database health
- Monitors location updates
- Cleans up old data
- Checks system resources
- Auto-checkout after hours

### Log Files
Monitor these log files:
- `/var/www/smartort/logs/system_monitor.log`
- `/var/log/nginx/access.log`
- `/var/log/nginx/error.log`

### Database Maintenance
```bash
# Clean old location data (run monthly)
mysql -u smartort_user -p smartort_tracker -e "DELETE FROM locations WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY);"

# Optimize tables
mysql -u smartort_user -p smartort_tracker -e "OPTIMIZE TABLE users, attendance, locations, activity_logs;"
```

## 🎯 Usage

### Admin Features
- Real-time user tracking
- Location history
- Attendance reports
- System settings
- User management
- Activity logs

### User Features
- Quick check-in/out
- Automatic location sharing
- Attendance history
- Location permission management

## 🔍 Troubleshooting

### Common Issues

#### Location Not Working
1. Check browser/app permissions
2. Verify HTTPS (required for web)
3. Check FastAPI server connectivity
4. Review console logs

#### Buttons Not Working
1. Check JavaScript console for errors
2. Verify PHP session handling
3. Check database connectivity
4. Review network requests

#### Background Tracking Issues
1. Ensure Capacitor plugins are installed
2. Check device battery optimization settings
3. Verify background permissions
4. Review service worker registration

### Debug Mode
Enable debug logging in admin settings:
```php
updateSettings('debug_mode', '1');
```

## 📈 Performance Optimization

### Database Optimization
- Indexed tables for fast queries
- Automatic data cleanup
- Connection pooling
- Query optimization

### Frontend Optimization
- Gzip compression
- Browser caching
- Minified assets
- Lazy loading

### Server Optimization
- PHP-FPM tuning
- Nginx optimization
- MySQL configuration
- Log rotation

## 🔄 Updates & Backups

### Automated Backups
Setup daily database backups:
```bash
# Add to crontab
0 2 * * * mysqldump -u smartort_user -p'password' smartort_tracker > /backups/smartort_$(date +\%Y\%m\%d).sql
```

### Update Process
1. Backup database and files
2. Pull latest changes
3. Run `npm run build`
4. Copy new files to `/var/www/smartort`
5. Run database migrations if needed
6. Restart services

## 📞 Support

### System Requirements
- **Server**: Ubuntu 20.04+, 2GB RAM, 20GB storage
- **Database**: MySQL 8.0+
- **PHP**: 8.1+
- **Node.js**: 18+
- **Web Server**: Nginx (recommended)

### Browser Support
- Chrome/Chromium 90+
- Firefox 88+
- Safari 14+
- Edge 90+

### Mobile Support
- Android 7+ (API 24+)
- iOS 13+

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🤝 Contributing

1. Fork the repository
2. Create feature branch
3. Commit changes
4. Push to branch
5. Create Pull Request

---

**Built with ❤️ using React, Capacitor, PHP & MySQL**
