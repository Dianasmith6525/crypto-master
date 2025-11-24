# Gmail SMTP Email Setup Guide

Your crypto platform is now configured to send real emails via Gmail SMTP!

## 📧 Setup Instructions

### Step 1: Create Gmail App Password

**You CANNOT use your regular Gmail password.** You need an App Password:

1. Go to your Google Account: https://myaccount.google.com/
2. Click on **Security** (left sidebar)
3. Enable **2-Step Verification** if not already enabled
4. Scroll down to **2-Step Verification** section
5. Click on **App passwords** (you'll see this only after enabling 2FA)
6. In the "Select app" dropdown, choose **Mail**
7. In the "Select device" dropdown, choose **Windows Computer**
8. Click **Generate**
9. **Copy the 16-character password** (it will look like: `abcd efgh ijkl mnop`)

### Step 2: Configure Email Settings

Edit the file: `includes/config.php`

Find these lines (around line 14-20):

```php
// Email Configuration
define('FROM_EMAIL', 'noreply@cryptotrading.com'); // Your Gmail address
define('ADMIN_EMAIL', 'admin@cryptotrading.com');
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', ''); // Your Gmail address
define('SMTP_PASSWORD', ''); // Your Gmail App Password (NOT regular password)
define('EMAIL_DEBUG', true); // Set to false to send real emails
```

**Update these values:**

```php
define('FROM_EMAIL', 'youremail@gmail.com'); // Replace with YOUR Gmail
define('SMTP_USERNAME', 'youremail@gmail.com'); // Same Gmail address
define('SMTP_PASSWORD', 'abcd efgh ijkl mnop'); // Your App Password from Step 1
define('EMAIL_DEBUG', false); // IMPORTANT: Change to false to send real emails
```

### Step 3: Test Email Sending

After configuring, restart the PHP server:

```powershell
Stop-Process -Name php -Force -ErrorAction SilentlyContinue
cd C:\Users\USER\Downloads\crypto-master\crypto-master
C:\php\php.exe -S localhost:8000
```

Then test by:
1. Creating a new account at http://localhost:8000/register.html
2. Check your Gmail inbox for the welcome email

---

## 🔧 Current Configuration Status

✅ PHPMailer library installed
✅ SMTP settings configured (Gmail smtp.gmail.com:587)
⏳ **ACTION NEEDED:** Add your Gmail credentials
⏳ **ACTION NEEDED:** Change `EMAIL_DEBUG` from `true` to `false`

---

## 📝 Email Types That Will Be Sent

Once configured, these emails will be sent automatically:

1. **Welcome Email** - When user registers
2. **Login Notification** - Each time user logs in (with IP & browser info)
3. **Price Alert** - When crypto price reaches user's alert threshold
4. **Trade Confirmation** - When user completes a trade
5. **2FA Enabled** - When two-factor auth is activated
6. **2FA Disabled** - When two-factor auth is deactivated
7. **Password Reset** - When user requests password reset
8. **Password Changed** - When password is successfully changed
9. **Weekly Summary** - Weekly portfolio performance report

---

## ⚠️ Important Notes

- **Never use your regular Gmail password** - Always use App Password
- Gmail has a limit of **500 emails per day** for free accounts
- For production, consider using services like:
  - SendGrid (100 free emails/day)
  - Mailgun (5,000 free emails/month)
  - Amazon SES (62,000 free emails/month)

---

## 🐛 Troubleshooting

**If emails don't send:**

1. Check PHP error logs for SMTP errors
2. Verify App Password is correct (no spaces)
3. Ensure 2FA is enabled on your Google Account
4. Make sure `EMAIL_DEBUG = false` in config.php
5. Check Gmail "Less secure app access" is not blocking

**Test connection manually:**
```php
// Create test-email.php
<?php
require 'includes/email.php';
$emailer = new EmailNotifier();
$result = $emailer->sendWelcomeEmail('your-test@email.com', 'Test User');
echo $result ? "Email sent!" : "Email failed!";
?>
```

---

## ✅ Quick Start Checklist

- [ ] Enable 2FA on Google Account
- [ ] Generate Gmail App Password
- [ ] Update `FROM_EMAIL` in config.php
- [ ] Update `SMTP_USERNAME` in config.php  
- [ ] Update `SMTP_PASSWORD` in config.php
- [ ] Change `EMAIL_DEBUG` to `false`
- [ ] Restart PHP server
- [ ] Test by creating new account
- [ ] Check your Gmail inbox

**Need help?** The email system will log errors to PHP error logs if sending fails.
