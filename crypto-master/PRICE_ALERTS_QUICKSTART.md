# 🔔 Price Alerts - Quick Start Guide

## What's New

Your users can now set automatic price alerts for Bitcoin, Ethereum, Litecoin, and XRP. When a price reaches their target, they get an email notification.

---

## Getting Started (3 Steps)

### Step 1: Access Alerts Page
1. Login to dashboard
2. Click **"🔔 Alerts"** button in top navigation
3. You'll see the alerts management page

### Step 2: Create Your First Alert
1. **Select Cryptocurrency** - Choose from Bitcoin, Ethereum, Litecoin, or XRP
2. **Select Alert Type** - "Price Goes ABOVE" or "Price Goes BELOW"
3. **Enter Price Threshold** - Your target price in USD
4. Click **"Create Alert"**

### Step 3: Set Up Price Checking
Choose one option to check prices:

#### Option A: Cron Job (Linux/Mac) - Recommended
```bash
# Edit crontab
crontab -e

# Add this line (runs every 5 minutes)
*/5 * * * * curl -s https://yoursite.com/check-prices.php > /dev/null 2>&1
```

#### Option B: External Service (No setup needed)
1. Go to https://easycron.com
2. Login (free account)
3. Create new cron job
4. URL: `https://yoursite.com/check-prices.php`
5. Frequency: Every 5-10 minutes
6. Done!

#### Option C: Test Manually
```bash
# From terminal/command line
curl https://yoursite.com/check-prices.php
```

---

## How It Works

### Creating an Alert
```
You set:  Bitcoin, BELOW, $40,000
         ↓
Alert stored in database
         ↓
Every 5 minutes, price check runs
         ↓
If Bitcoin price ≤ $40,000
         ↓
Email sent: "Your alert triggered! BTC is now $39,500"
         ↓
Alert marked as triggered in your account
```

### Managing Alerts
```
Active Alerts Tab
├─ See all your active price alerts
├─ Delete any alert
└─ Get real-time status

Triggered Tab
├─ See alerts that have fired
├─ Know which prices you hit
└─ Keep your alert history

All Alerts Tab
├─ See everything
├─ Manage all your alerts
└─ Search/filter history
```

---

## Features

✅ **Multiple Alerts** - Create as many as you want
✅ **Above/Below** - Set alerts for either direction
✅ **Real-Time** - Price check every 5 minutes
✅ **Email Alerts** - Get notified instantly
✅ **Managed History** - See all your alerts
✅ **Delete Anytime** - Remove alerts you don't want
✅ **Mobile Ready** - Works on phone/tablet

---

## Email Notification Example

When your alert triggers, you'll receive:

```
Subject: Price Alert Triggered: Bitcoin

Your price alert for Bitcoin has been triggered!

Alert Type: Price went ABOVE $50,000
Current Price: $50,500

Check your portfolio and make trading decisions now:
[View Dashboard Button]
```

---

## Configuration Required

### Email Setup (Choose One)

**Option 1: SendGrid (Recommended)**
- Free tier: 100 emails/day
- Go to: https://sendgrid.com
- Get API key
- Edit `check-prices.php` to use SendGrid

**Option 2: Mailgun**
- Free tier: 5,000 emails/month
- Go to: https://mailgun.com
- Get API key
- Edit `check-prices.php` to use Mailgun

**Option 3: Built-in PHP Mail**
- Just enable in `check-prices.php`
- Requires mail server on host
- Simplest option

### Update `check-prices.php` (Line ~140)

Find this line and uncomment it:
```php
// mail($user_email, $subject, $message, $headers);
```

Remove the `//` to enable:
```php
mail($user_email, $subject, $message, $headers);
```

---

## Testing

### Test Alert Creation
1. Go to Alerts page
2. Create test alert:
   - Crypto: Bitcoin
   - Type: BELOW
   - Price: Very low (e.g., $1)
3. Should see: ✓ "Alert created successfully"

### Test Price Checking
```bash
# Run price check
curl https://yoursite.com/check-prices.php

# Should get JSON response:
{
  "success": true,
  "alerts_checked": 1,
  "alerts_triggered": 1
}
```

### Verify Email
- Check inbox for alert notification
- Verify alert marked as "Triggered"

---

## Troubleshooting

### "Already have active alert"
User is trying to create duplicate alert
**Fix**: Delete existing alert first

### Alert not triggering
Price check not running
**Fix**: 
1. Verify cron job is set up
2. Test manually: `curl https://yoursite.com/check-prices.php`

### Not getting emails
Email not configured
**Fix**:
1. Enable PHP mail or SendGrid
2. Update `check-prices.php`
3. Test alert creation again

### Can't access alerts
Not logged in
**Fix**: Login to dashboard first

---

## Supported Cryptos

Currently supports:
- Bitcoin (BTC)
- Ethereum (ETH)
- Litecoin (LTC)
- XRP

To add more, edit `alerts.html` and add options to dropdown.

---

## Best Practices

✅ **Set Realistic Prices** - Research current prices first
✅ **Don't Create Duplicates** - One alert per price per crypto
✅ **Monitor Regularly** - Check triggered alerts
✅ **Delete Old Alerts** - Keep list clean
✅ **Test First** - Create test alert before important ones
✅ **Enable Notifications** - Make sure you can receive emails

---

## Features Coming Soon (Optional)

- SMS alerts
- Discord/Slack notifications  
- Percentage-based alerts
- Portfolio-linked alerts
- Alert frequency limits
- Alert templates

---

## Files Created

```
create-alert.php        - Creates alerts
get-alerts.php         - Retrieves alerts
delete-alert.php       - Deletes alerts
check-prices.php       - Price checking (runs periodically)
cron-check-prices.php  - Cron job wrapper
alerts.html            - Alerts management page
```

---

## Links

- **Alerts Page**: /alerts.html (when logged in)
- **Dashboard**: /dashboard.html
- **Price Checker** (test): /check-prices.php

---

## Need Help?

1. **Cron not working?** - Use easycron.com instead
2. **Emails not sending?** - Enable SendGrid or Mailgun
3. **Can't access alerts?** - Make sure you're logged in
4. **Alerts not triggering?** - Run price check manually

---

**Status**: ✅ **READY TO USE**

Follow the setup steps above and you're good to go! 🚀

