# 🔧 Installation & Setup Guide

## Prerequisites
- PHP 7.2+
- MySQL 5.7+
- Web server (Apache/Nginx)

## Step 1: Create Database

```sql
CREATE DATABASE crypto_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE crypto_platform;
```

## Step 2: Import Schema

1. Import `db-schema.sql` to create all tables:
```bash
mysql -u root -p crypto_platform < db-schema.sql
```

2. Create additional security tables:
```sql
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255),
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier (identifier)
);

CREATE TABLE security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    event_type VARCHAR(50),
    ip_address VARCHAR(45),
    user_agent TEXT,
    details JSON,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_event_type (event_type)
);

CREATE TABLE api_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    endpoint VARCHAR(255),
    method VARCHAR(10),
    user_id INT,
    status INT,
    ip_address VARCHAR(45),
    response JSON,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_endpoint (endpoint),
    INDEX idx_user_id (user_id)
);
```

## Step 3: Configure PHP Settings

Edit `includes/config.php`:

```php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
define('DB_NAME', 'crypto_platform');

// Email Configuration (for verification & password reset)
define('FROM_EMAIL', 'noreply@yourdomain.com');
define('ADMIN_EMAIL', 'admin@yourdomain.com');

// Site URL
define('SITE_URL', 'http://yourdomain.com/');
```

## Step 4: Configure Email Service

### Option A: Using PHP Mail (Simple)
```php
// In includes/config.php - default mail() function is used
// Ensure your server has mail relay configured
```

### Option B: Using SMTP (Gmail)
```php
// Update send_email() function in includes/config.php to use PHPMailer or SwiftMailer
// composer require phpmailer/phpmailer
// composer require swiftmailer/swiftmailer
```

## Step 5: Create `includes` Directory
```bash
mkdir -p includes/
mkdir -p api/
chmod 755 includes/ api/
```

## Step 6: Set File Permissions
```bash
# Linux/Mac
chmod 644 *.html
chmod 644 api/*.php
chmod 644 includes/*.php
chmod 755 includes/ api/
```

## Step 7: Test the System

### 1. Register a new user
- Visit: http://yoursite.com/register.html
- Fill in the form
- Check email for verification link
- Click verification link

### 2. Login
- Visit: http://yoursite.com/login.html
- Enter verified email and password
- Redirect to dashboard.html

### 3. Add Holdings
- On dashboard, click "Add Holding"
- Select cryptocurrency
- Enter quantity and entry price
- Click "Save Holding"

## File Structure

```
crypto-master/
├── index.html                 # Homepage with auth buttons
├── register.html              # Registration page
├── login.html                 # Login page
├── dashboard.html             # Portfolio dashboard
├── api/
│   ├── register.php           # Registration API
│   ├── login.php              # Login/Logout API
│   └── portfolio.php          # Portfolio management API
├── includes/
│   └── config.php             # Database & security config
├── db-schema.sql              # Database schema
├── FEATURES_BUILT.md          # Features documentation
└── SETUP_GUIDE.md             # This file
```

## Troubleshooting

### Email Not Sending
- Check `php.ini` for mail configuration
- Ensure `sendmail_from` is set
- Check spam folder
- May need to use external SMTP service

### Database Connection Error
- Verify MySQL is running
- Check DB credentials in `config.php`
- Ensure database exists
- Check user has proper permissions

### Password Reset Not Working
- Verify email configuration
- Check token expiry is not too short
- Ensure database has `password_reset_tokens` table

### Session Issues
- Clear browser cookies/cache
- Check PHP session storage is writable
- Verify `session.gc_maxlifetime` is sufficient

## Security Checklist

- [ ] Change default database password
- [ ] Update email addresses in config
- [ ] Enable HTTPS on production
- [ ] Set proper file permissions (644/755)
- [ ] Backup database regularly
- [ ] Monitor security logs
- [ ] Keep PHP/MySQL updated
- [ ] Set secure session cookies
- [ ] Implement CSRF tokens
- [ ] Regular security audits

## Environment Variables (Optional)

Create `.env` file:
```
DB_HOST=localhost
DB_USER=root
DB_PASS=password
DB_NAME=crypto_platform
FROM_EMAIL=noreply@yourdomain.com
SITE_URL=http://localhost/crypto-master/
```

Then update `config.php` to read from `.env`:
```php
require_once '../.env';
```

## Production Deployment

1. **HTTPS**: Install SSL certificate
2. **Database**: Use strong passwords, restrict access
3. **Backups**: Automatic daily backups
4. **Monitoring**: Set up error logging & alerts
5. **Rate Limiting**: Consider nginx/apache rate limiting
6. **CDN**: Use for static assets
7. **Caching**: Implement Redis for sessions
8. **Load Balancing**: If high traffic

## Support

For issues or questions:
- Check FEATURES_BUILT.md for feature documentation
- Review security logs in database
- Check PHP error logs
- Enable debug mode in development

---

**Setup Complete!** 🎉 Your crypto trading platform is ready to use.
