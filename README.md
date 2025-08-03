# SmartOutreach Tracking System

SmartOutreach is a modern web application designed to help organizations track, manage, and empower outreach workers in the field. With real-time check-in/check-out and location tracking, it's built for teams on-the-go.

## What Makes SmartOutreach Unique?

- **FastAPI-Powered Backend:**  
  The system integrates FastAPI for high-performance background tasks, data processing, and real-time event handling. This gives outreach organizations modern speed and scalability, and supports future AI-driven enhancements.

- **Field Worker Focused:**  
  Specifically designed for outreach (rural, health, education, etc.) teams who operate outside traditional offices. It supports check-in/check-out, live location updates, and device-based security.

- **Privacy & Security:**  
  Staff location is only tracked when checked in, respecting user privacy. Device locking (IMEI support) ensures only authorized logins.

- **Admin Dashboard:**  
  Masters and developers have access to a dashboard with statistics, logs, and management tools.

- **Automatic Data Cleanup:**  
  Set up scheduled scripts to keep location logs fresh and accurate.

## Features

- Employee management
- Check-in/check-out tracking
- Real-time location monitoring (with FastAPI event handling)
- IMEI-based device locking for security
- Activity logs and statistics
- Automatic data cleanup via cron jobs

## Technical Requirements

- PHP 7.4+ for the main app
- FastAPI (Python 3.8+) for backend event processing
- MySQL database
- Modern web browser (Chrome, Firefox, Edge, Safari)
- Location services enabled on mobile devices

## How To Install

1. Upload all files to your hosting account.
2. Run `install.php` to set up the database.
3. Log in with your developer/master account.
4. Set up a cron job for `cron_reset_locations.php` for automatic data cleanup.

## Admin Access

Log in as a master or developer, then click "View Admin Dashboard" for advanced features.

## Automatic Data Cleanup (Recommended)

Set up a cron job to run `cron_reset_locations.php` every 72 hours (midnight, Pakistan time):

```
0 0 * * * /usr/bin/php /path/to/cron_reset_locations.php
```

## Why SmartOutreach?

SmartOutreach is a unique idea for outreach teams, blending web technology and FastAPI to make real-world tracking simple, secure, and scalable. It’s built for organizations who need trust and transparency for their field work.
