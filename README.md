
# SmartOutreach Tracking New System

This application allows organizations to track their outreach employees with check-in/check-out and location tracking features.

## Installation Instructions

1. Upload all files to your Hostinger hosting account at https://healthbyasif.buylevi.xyz/
2. Navigate to `https://healthbyasif.buylevi.xyz/install.php` to set up the database
3. After installation, go to the main page and log in using the developer account

## Features

- Employee management
- Check-in/check-out tracking
- Real-time location monitoring
- IMEI-based device locking
- Comprehensive activity logs
- Automatic data cleanup

## Accessing the Admin Dashboard

After logging in as a developer or master user, click on the "View Admin Dashboard" button to access administrative features.

## Setting Up Automatic Reset

For automatic cleanup every 72 hours, set up a cron job on your Hostinger account to run the `cron_reset_locations.php` file at midnight Pakistan time (UTC+5).

Add this to your crontab:
```
0 0 * * * /usr/bin/php /path/to/cron_reset_locations.php
```

## Technical Requirements

- PHP 7.4 or higher
- MySQL database
- Modern web browser
- Location services enabled on mobile devices

