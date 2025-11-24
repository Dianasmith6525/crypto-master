# 💰 Referral System Documentation

## Overview
The CryptoTrade Pro referral system allows users to earn commissions by inviting friends to join the platform. Referrers earn a percentage of their referrals' trading volume, while new users receive a welcome bonus.

---

## 🎯 Features

### For Referrers
- **Unique Referral Code**: Each user gets a unique code (e.g., `AB12CD34`)
- **Custom Referral Link**: Shareable URL with tracking
- **Commission Earnings**: Earn 10% of referred users' trading volume
- **Real-time Dashboard**: Track clicks, conversions, and earnings
- **Social Sharing**: One-click sharing to Twitter, Facebook, WhatsApp, etc.
- **Unlimited Referrals**: No cap on number of referrals

### For Referees (New Users)
- **Welcome Bonus**: $5 credited upon registration
- **No Minimum Deposit**: Start trading immediately
- **Same Platform Features**: Full access to all trading tools

---

## 📊 Database Schema

### Tables

#### `users` (Extended)
```sql
referral_code VARCHAR(20) UNIQUE          -- User's unique referral code
referred_by INT                           -- ID of user who referred them
total_referral_earnings DECIMAL(20, 2)    -- Lifetime earnings
```

#### `referral_settings`
```sql
setting_id INT PRIMARY KEY
referrer_bonus_percentage DECIMAL(5, 2)   -- Default: 10.00%
referee_bonus_amount DECIMAL(10, 2)       -- Default: $5.00
min_trade_volume DECIMAL(10, 2)           -- Default: $100.00
max_referral_earnings DECIMAL(10, 2)      -- Default: $1000.00
is_active BOOLEAN
```

#### `referral_transactions`
```sql
transaction_id INT PRIMARY KEY
referrer_id INT                           -- User earning the bonus
referee_id INT                            -- User who signed up
bonus_type ENUM                           -- 'referrer_percentage' or 'referee_welcome'
bonus_amount DECIMAL(10, 2)               -- Amount earned/credited
trade_volume DECIMAL(20, 2)               -- Trade that triggered bonus
status ENUM                               -- 'pending', 'credited', 'cancelled'
created_at TIMESTAMP
credited_at TIMESTAMP
```

#### `referral_clicks`
```sql
click_id INT PRIMARY KEY
referrer_id INT                           -- Link owner
referral_code VARCHAR(20)                 -- Code used
ip_address VARCHAR(45)                    -- Visitor IP
user_agent TEXT                           -- Browser info
clicked_at TIMESTAMP                      -- Click timestamp
converted BOOLEAN                         -- Whether they signed up
converted_at TIMESTAMP                    -- Conversion timestamp
```

---

## 🚀 Setup & Installation

### 1. Database Setup
Run the database schema to create required tables:
```bash
mysql -u root -p crypto_trading < db-schema.sql
```

### 2. Initialize Referral System
Run the setup script to configure default settings and generate codes for existing users:
```bash
php setup-referrals.php
```

This will:
- Create default referral settings (10% commission, $5 bonus)
- Generate unique referral codes for all existing users
- Verify database structure

### 3. Update Registration Flow
The registration API (`api/register.php`) automatically:
- Generates a unique referral code for new users
- Tracks the referring user if `?ref=CODE` parameter is present
- Credits welcome bonus to new user
- Creates referral transaction record

---

## 💻 API Endpoints

### Base URL: `/api/referrals.php`

#### Get Referral Statistics
```javascript
POST /api/referrals.php
{
  "action": "get_stats"
}

Response:
{
  "success": true,
  "data": {
    "referral_code": "AB12CD34",
    "referral_link": "https://example.com/register.html?ref=AB12CD34",
    "total_referrals": 15,
    "total_earnings": 250.50,
    "pending_earnings": 45.00,
    "total_clicks": 87,
    "conversion_rate": 17.24
  }
}
```

#### Get Referrals List
```javascript
POST /api/referrals.php
{
  "action": "get_referrals"
}

Response:
{
  "success": true,
  "data": [
    {
      "name": "John Doe",
      "email": "john@example.com",
      "join_date": "2025-11-20",
      "total_trades": 25,
      "total_volume": 5000.00,
      "total_earnings": 50.00
    }
  ]
}
```

#### Get Earnings History
```javascript
POST /api/referrals.php
{
  "action": "get_earnings"
}

Response:
{
  "success": true,
  "data": [
    {
      "transaction_id": 123,
      "referee_name": "Jane Smith",
      "bonus_type": "referrer_percentage",
      "bonus_amount": 12.50,
      "trade_volume": 125.00,
      "status": "credited",
      "created_at": "2025-11-24 10:30:00"
    }
  ]
}
```

#### Generate Sharing Link
```javascript
POST /api/referrals.php
{
  "action": "generate_link",
  "platform": "twitter"  // twitter, facebook, linkedin, whatsapp, telegram, email
}

Response:
{
  "success": true,
  "selected_link": "https://twitter.com/intent/tweet?text=..."
}
```

#### Track Referral Click
```javascript
POST /api/referrals.php
{
  "action": "track_click",
  "referral_code": "AB12CD34"
}
```

---

## 🎨 User Interface

### Referral Dashboard (`referrals.html`)

**Stats Cards:**
- Total Referrals
- Total Earnings
- Conversion Rate
- Pending Bonuses

**Sections:**
1. **Referral Link** - Copy/share buttons
2. **Your Referrals** - Table of referred users with stats
3. **Earnings History** - Transaction log with status badges

**Features:**
- Dark/light theme toggle
- Responsive design for mobile
- Real-time data updates
- Social media integration

---

## 💡 How It Works

### Registration Flow
1. User A shares referral link: `register.html?ref=AB12CD34`
2. User B clicks link → Click tracked in `referral_clicks`
3. User B registers → Conversion marked, `referred_by` set
4. Welcome bonus ($5) credited to User B
5. Transaction created with status 'pending'

### Commission Flow
1. User B makes a trade (e.g., $150)
2. System checks if trade volume ≥ min_trade_volume ($100)
3. Calculates commission: $150 × 10% = $15
4. Creates transaction for User A
5. Credits earnings to User A's account
6. Updates `total_referral_earnings`
7. Transaction status → 'credited'

### Earning Limits
- **Min Trade Volume**: $100 (configurable)
- **Max Earnings**: $1000 per user (configurable)
- **No limit** on number of referrals
- Earnings stop when max limit reached

---

## ⚙️ Configuration

### Modify Referral Settings
```sql
UPDATE referral_settings SET
  referrer_bonus_percentage = 15.00,  -- Change commission to 15%
  referee_bonus_amount = 10.00,       -- Change welcome bonus to $10
  min_trade_volume = 50.00,           -- Lower minimum trade
  max_referral_earnings = 2000.00     -- Increase earnings cap
WHERE setting_id = 1;
```

### Disable Referral Program
```sql
UPDATE referral_settings SET is_active = 0;
```

---

## 📈 Analytics & Reporting

### Top Referrers Query
```sql
SELECT 
  u.full_name,
  u.email,
  COUNT(r.user_id) as total_referrals,
  u.total_referral_earnings
FROM users u
JOIN users r ON r.referred_by = u.user_id
GROUP BY u.user_id
ORDER BY total_referrals DESC
LIMIT 10;
```

### Conversion Rate by User
```sql
SELECT 
  u.user_id,
  u.full_name,
  COUNT(c.click_id) as total_clicks,
  SUM(c.converted) as conversions,
  ROUND(SUM(c.converted) / COUNT(c.click_id) * 100, 2) as conversion_rate
FROM users u
JOIN referral_clicks c ON c.referrer_id = u.user_id
GROUP BY u.user_id
ORDER BY conversion_rate DESC;
```

### Monthly Referral Report
```sql
SELECT 
  DATE_FORMAT(u.date_created, '%Y-%m') as month,
  COUNT(*) as new_referrals,
  SUM(rt.bonus_amount) as total_bonuses
FROM users u
JOIN referral_transactions rt ON rt.referee_id = u.user_id
WHERE u.referred_by IS NOT NULL
GROUP BY month
ORDER BY month DESC;
```

---

## 🔒 Security Considerations

1. **Unique Codes**: 8-character alphanumeric codes (3.6 trillion combinations)
2. **IP Tracking**: Prevent click fraud
3. **Conversion Validation**: Only genuine registrations count
4. **Earnings Cap**: Prevents abuse with max limit
5. **Status Verification**: Pending → Credited workflow
6. **Session Validation**: All API calls require authentication

---

## 🐛 Troubleshooting

### Referral Code Not Generated
```php
// Run setup script
php setup-referrals.php
```

### Earnings Not Credited
- Check if trade volume ≥ min_trade_volume
- Verify transaction status in `referral_transactions`
- Check if max_referral_earnings reached

### Link Not Tracking Clicks
- Ensure JavaScript is enabled
- Check `referral_clicks` table for entries
- Verify referral_code exists in URL parameter

---

## 📞 Support

For issues or questions:
- Check transaction logs in `referral_transactions`
- Review click tracking in `referral_clicks`
- Monitor earnings in user dashboard
- Contact support@cryptotrade.pro

---

## 🎁 Promotional Ideas

1. **Limited Time Bonuses**: Increase referee bonus to $20
2. **Leaderboard**: Display top referrers publicly
3. **Milestone Rewards**: Bonus at 10, 50, 100 referrals
4. **Seasonal Campaigns**: Double commission periods
5. **Referral Contests**: Prizes for most conversions

---

**Version**: 1.0  
**Last Updated**: November 24, 2025  
**Status**: ✅ Production Ready
