# SendGrid Setup Guide - 5 Minutes

Get your crypto platform sending professional emails with SendGrid!

---

## 📧 Step 1: Create SendGrid Account (2 minutes)

1. Go to: **https://sendgrid.com/free/**
2. Click **"Start for free"** or **"Sign up"**
3. Fill in details:
   - Email: `dianasmith6525@gmail.com` (or any email)
   - Password: Choose a strong password
   - Click **"Create Account"**
4. Verify your email (check inbox for verification link)
5. Complete phone verification (SMS code)

---

## 🔑 Step 2: Create API Key (2 minutes)

1. **Login to SendGrid Dashboard**
2. Click **Settings** (left sidebar) → **API Keys**
3. Click **"Create API Key"** (blue button, top right)
4. Configure:
   - **API Key Name:** `CryptoTrade Platform`
   - **API Key Permissions:** Select **"Full Access"** (or "Restricted Access" with Mail Send permission)
5. Click **"Create & View"**
6. **IMPORTANT:** Copy the API key NOW (starts with `SG.`)
   - Example: `SG.abc123XYZ...`
   - You can only see this ONCE!
   - Save it somewhere safe

---

## ✉️ Step 3: Verify Sender Email (1 minute)

1. In SendGrid Dashboard: **Settings** → **Sender Authentication**
2. Click **"Verify a Single Sender"**
3. Fill in:
   - **From Name:** CryptoTrade Platform
   - **From Email Address:** `dianasmith6525@gmail.com`
   - **Reply To:** `dianasmith6525@gmail.com`
   - **Company Address:** (fill in any address)
   - **City, State, ZIP, Country:** (your location)
4. Click **"Create"**
5. **Check your email** (`dianasmith6525@gmail.com`)
6. Click the verification link in the email from SendGrid

---

## ⚙️ Step 4: Configure Your Platform

Now update your `includes/config.php` file:

```php
define('SMTP_PASSWORD', 'SG.your-api-key-here'); // Paste your API key
define('EMAIL_DEBUG', false); // Change to false to enable real emails
```

**Your current config (already set up):**
```php
define('FROM_EMAIL', 'dianasmith6525@gmail.com'); ✅
define('SMTP_HOST', 'smtp.sendgrid.net');         ✅
define('SMTP_PORT', 587);                          ✅
define('SMTP_USERNAME', 'apikey');                 ✅
define('SMTP_PASSWORD', '');                       ⏳ ADD YOUR API KEY
define('EMAIL_DEBUG', true);                       ⏳ CHANGE TO false
```

---

## 🚀 Step 5: Restart Server & Test

1. **Stop the current PHP server:**
   ```powershell
   Stop-Process -Name php -Force -ErrorAction SilentlyContinue
   ```

2. **Start server again:**
   ```powershell
   cd C:\Users\USER\Downloads\crypto-master\crypto-master
   C:\php\php.exe -S localhost:8000
   ```

3. **Test by creating a new account:**
   - Go to: http://localhost:8000/register.html
   - Fill in the form and register
   - Check `dianasmith6525@gmail.com` inbox for welcome email!

---

## ✅ Quick Checklist

- [ ] Create SendGrid account at https://sendgrid.com/free/
- [ ] Verify your email address
- [ ] Complete phone verification
- [ ] Create API Key (Settings → API Keys)
- [ ] Copy API Key (starts with SG.)
- [ ] Verify sender email (Settings → Sender Authentication)
- [ ] Click verification link in email
- [ ] Update SMTP_PASSWORD with API key
- [ ] Change EMAIL_DEBUG to false
- [ ] Restart PHP server
- [ ] Test registration → Check email inbox

---

## 🧪 Test Email Sending

Create `test-sendgrid.php`:

```php
<?php
require 'includes/email.php';

echo "Testing SendGrid email...\n\n";

$emailer = new EmailNotifier();
$result = $emailer->sendWelcomeEmail(
    'dianasmith6525@gmail.com',
    'Diana Smith'
);

if ($result) {
    echo "✅ SUCCESS! Email sent via SendGrid.\n";
    echo "Check your inbox: dianasmith6525@gmail.com\n";
} else {
    echo "❌ FAILED! Check error logs.\n";
}
?>
```

Run: `C:\php\php.exe test-sendgrid.php`

---

## 📊 SendGrid Dashboard Features

After setup, you can track:
- **Email Activity:** See all sent emails
- **Statistics:** Delivery rates, opens, clicks
- **Bounce Management:** See failed deliveries
- **Spam Reports:** Monitor email quality

Access: https://app.sendgrid.com/

---

## ⚠️ Important Notes

1. **Free Tier Limits:**
   - 100 emails per day
   - Perfect for development and small projects
   - Upgrade for more: 40,000 emails/month for $19.95

2. **API Key Security:**
   - Never commit to GitHub/version control
   - Keep it secret like a password
   - Can regenerate if compromised

3. **Sender Verification Required:**
   - MUST verify sender email before sending
   - SendGrid won't send from unverified emails
   - Verification is instant (just click email link)

---

## 🐛 Troubleshooting

**"Sender email not verified" error:**
- Go to Settings → Sender Authentication
- Click verification link in your email

**Emails not arriving:**
- Check SendGrid Activity Feed (see delivery status)
- Check spam folder
- Verify API key is correct

**"Authentication failed" error:**
- SMTP_USERNAME must be literally "apikey" (lowercase)
- SMTP_PASSWORD must be full API key starting with SG.
- Check for extra spaces in config.php

**Still stuck?**
- Check PHP error logs
- View SendGrid Activity Feed for details
- Ensure EMAIL_DEBUG is set to false

---

## 🎯 What Happens After Setup

Your platform will automatically send:

1. **Welcome Email** → New user registration
2. **Login Alerts** → Each login with IP & location
3. **Price Alerts** → When crypto hits target price
4. **Trade Confirmations** → Completed trades
5. **2FA Notifications** → Security changes
6. **Password Reset** → Forgot password requests
7. **Weekly Summaries** → Portfolio performance

All professionally delivered via SendGrid! 📧

---

**Ready? Follow the steps above and paste your API key when you get it!**
