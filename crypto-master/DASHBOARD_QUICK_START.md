# Dashboard Quick Start Guide

## 🚀 Getting Started with Your Enhanced Dashboard

### Prerequisites
- PHP server running on `localhost:8000`
- MySQL database with `crypto_trading` schema
- User account created and logged in

---

## 📋 Step-by-Step Testing

### 1. Start Your PHP Server
```powershell
# Navigate to project directory
cd C:\Users\USER\Downloads\crypto-master\crypto-master

# Start PHP server
C:\php\php.exe -S localhost:8000
```

### 2. Access the Dashboard
```
Open browser: http://localhost:8000/dashboard.php

Note: You must be logged in first!
If redirected to login, use your credentials.
```

### 3. Test Add Holding Feature
1. Click **"+ Add Holding"** button
2. Fill in the form:
   - **Cryptocurrency**: Select "Bitcoin (BTC)"
   - **Quantity**: Enter `0.5`
   - **Entry Price**: Enter `45000`
   - **Entry Date**: Select today's date
   - **Notes**: Optional (e.g., "First Bitcoin purchase")
3. Click **"Save Holding"**
4. Verify holding appears in table with current price

### 4. Test Portfolio Visualization
- After adding holding, scroll down to see:
  - **Portfolio Distribution Chart** (doughnut chart)
  - Shows percentage of each crypto in your portfolio
  - Hover over segments for detailed values

### 5. Test Performance Stats
- Check the **Performance Stats** widget:
  - **Top Performer**: Shows your best-performing crypto
  - **Worst Performer**: Shows underperforming crypto
  - **Total Holdings**: Number of different cryptocurrencies

### 6. Test Edit Holding
1. Click **"Edit"** button on any holding
2. Modify any field (e.g., change quantity to `0.75`)
3. Click **"Save Holding"**
4. Verify changes reflected in table and charts

### 7. Test Export Feature
1. Click **"📥 Export CSV"** button
2. CSV file downloads automatically
3. Open in Excel/Google Sheets
4. Verify all holdings listed correctly

### 8. Test Delete Holding
1. Click **"Delete"** button on a holding
2. Confirm deletion in popup
3. Verify holding removed from table
4. Charts update automatically

---

## 🎨 What You Should See

### Summary Cards (Top)
```
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│ Total Invested  │  │ Current Value   │  │  Total P&L      │
│    $22,500.00   │  │   $24,750.00    │  │   +$2,250.00    │
│ In 2 cryptos    │  │  ↑ +$2,250.00   │  │    +10.00%      │
└─────────────────┘  └─────────────────┘  └─────────────────┘
```

### Portfolio Distribution Chart
```
        ╱─────╲
      ╱  BTC   ╲
     │   60%    │
      ╲  ETH   ╱
        ╲─────╱
         40%
```

### Performance Stats
```
┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│Top Performer │  │Worst Performer│  │Total Holdings│
│     BTC      │  │     ETH       │  │      2       │
│   +15.50%    │  │    -5.20%     │  │cryptocurrencies│
└──────────────┘  └──────────────┘  └──────────────┘
```

### Holdings Table
```
┌──────────────┬─────────┬────────────┬──────────────┬─────────┬───────────┬─────────┐
│Cryptocurrency│ Quantity│ Entry Price│Current Price │  Value  │    P/L    │ Actions │
├──────────────┼─────────┼────────────┼──────────────┼─────────┼───────────┼─────────┤
│Bitcoin (BTC) │0.50000000│ $45,000.00│  $52,000.00  │$26,000  │+$3,500.00│Edit Del │
│              │         │            │              │         │  (+15.56%)│         │
├──────────────┼─────────┼────────────┼──────────────┼─────────┼───────────┼─────────┤
│Ethereum(ETH) │2.00000000│ $2,500.00 │  $2,400.00   │ $4,800  │ -$200.00 │Edit Del │
│              │         │            │              │         │  (-4.00%) │         │
└──────────────┴─────────┴────────────┴──────────────┴─────────┴───────────┴─────────┘
```

---

## 🔍 What to Verify

### Security Checks
✅ **Authentication**: Can't access without login  
✅ **CSRF Protection**: All forms include hidden token  
✅ **Session Data**: User name/email displayed from PHP session  
✅ **Ownership**: Can only see/edit your own holdings  

### Functionality Checks
✅ **Add**: Can create new holdings  
✅ **Read**: Holdings display with live prices  
✅ **Update**: Can edit existing holdings  
✅ **Delete**: Can remove holdings  
✅ **Export**: CSV download works  

### UI/UX Checks
✅ **Charts**: Render correctly with data  
✅ **Colors**: Green for gains, red for losses  
✅ **Updates**: Auto-refresh every 60 seconds  
✅ **Alerts**: Success/error messages appear  
✅ **Modal**: Opens/closes smoothly  

---

## 📊 Sample Test Data

### Test Scenario 1: Profitable Portfolio
```javascript
// Add these holdings to test positive P/L

Holding 1:
- Crypto: Bitcoin (BTC)
- Quantity: 0.25
- Entry Price: 40000
- Entry Date: 2025-01-01

Holding 2:
- Crypto: Ethereum (ETH)
- Quantity: 5
- Entry Price: 2000
- Entry Date: 2025-01-15

Expected Result:
✓ Current prices higher than entry → Green P/L
✓ Total P/L positive
✓ BTC likely top performer
```

### Test Scenario 2: Mixed Portfolio
```javascript
Holding 1:
- Crypto: Bitcoin (BTC)
- Quantity: 0.1
- Entry Price: 60000 (bought at high)

Holding 2:
- Crypto: Litecoin (LTC)
- Quantity: 10
- Entry Price: 50

Expected Result:
✓ BTC in loss (red), LTC in profit (green)
✓ Shows both top and worst performers
✓ Mixed P/L colors in table
```

---

## 🐛 Troubleshooting

### Problem: "Dashboard redirects to login"
**Solution:**
```php
// Check if logged in
1. Access login.html first
2. Enter credentials
3. After successful login, navigate to dashboard.php
```

### Problem: "CSRF token validation failed"
**Solution:**
```
1. Refresh the page (F5)
2. This generates a new CSRF token
3. Try the action again
```

### Problem: "Chart not displaying"
**Solution:**
```
1. Check browser console (F12)
2. Verify Chart.js loaded: Look for chart.min.js in Network tab
3. Ensure you have at least 1 holding added
4. Chart appears only when holdings exist
```

### Problem: "Prices not updating"
**Solution:**
```
1. Check CoinGecko API is accessible
2. Open browser console, look for 429 (rate limit) errors
3. Wait 60 seconds for auto-refresh
4. If persistent, prices default to entry prices
```

### Problem: "Export button not working"
**Solution:**
```
1. Check browser allows downloads
2. Disable popup blockers
3. Must have at least 1 holding to export
4. Check browser's download folder
```

---

## 💡 Pro Tips

### Tip 1: Quick Testing
```powershell
# Add sample data quickly via SQL
mysql -u root -p crypto_trading

INSERT INTO portfolio_holdings (user_id, crypto_id, crypto_symbol, crypto_name, quantity, entry_price, entry_date)
VALUES (1, 'bitcoin', 'BTC', 'Bitcoin', 0.5, 45000, '2025-01-01');
```

### Tip 2: Monitor API Calls
```javascript
// Open browser console (F12) > Network tab
// Filter by XHR to see API requests
// Check for:
- POST to api/portfolio.php (get_holdings)
- GET to api.coingecko.com (price updates)
```

### Tip 3: Test CSRF Protection
```javascript
// Open console, try sending request without token
$.post('api/portfolio.php', {
    action: 'add_holding',
    crypto_id: 'bitcoin'
    // No csrf_token!
});
// Should return: 403 Forbidden with "Security token validation failed"
```

### Tip 4: Verify Auto-Refresh
```
1. Add a holding
2. Wait 60 seconds (or check Network tab)
3. Look for automatic AJAX call to portfolio.php
4. Prices should update without page refresh
```

---

## 📸 Expected Screenshots

### On Load (No Holdings)
```
┌────────────────────────────────────────┐
│ 📊 Crypto Portfolio         User [⚙️]  │
├────────────────────────────────────────┤
│ Welcome back! 👋                       │
│ Track and manage your portfolio        │
├────────────────────────────────────────┤
│ Total Invested  Current Value  Total P/L│
│    $0.00           $0.00        $0.00  │
├────────────────────────────────────────┤
│ Your Holdings          [+ Add Holding] │
│                                        │
│     💼                                 │
│  No holdings yet.                      │
│  Start by adding your first crypto!    │
│                                        │
│        [Add Your First Holding]        │
└────────────────────────────────────────┘
```

### With Holdings Added
```
┌────────────────────────────────────────┐
│ Summary Cards (with values)            │
├────────────────────────────────────────┤
│ [Portfolio Distribution Chart]         │
│       (Colorful doughnut)              │
├────────────────────────────────────────┤
│ [Performance Stats Widget]             │
│  Top | Worst | Total                   │
├────────────────────────────────────────┤
│ Holdings Table                         │
│  (with Edit/Delete buttons)            │
└────────────────────────────────────────┘
```

---

## ✅ Success Criteria

You've successfully tested the dashboard when:

- [ ] Can add new holdings without errors
- [ ] Edit feature loads existing data correctly
- [ ] Delete removes holding and updates UI
- [ ] Portfolio chart displays and updates
- [ ] Performance stats show correct top/worst performers
- [ ] Export CSV downloads with all data
- [ ] Real-time prices update (check after 60s)
- [ ] All buttons work without console errors
- [ ] Security: No access without login
- [ ] Security: CSRF validation working (test invalid token)

---

## 🎉 Next Steps

After testing the dashboard:

1. **Customize Crypto List**
   - Add more cryptocurrencies to dropdown
   - File: `dashboard.php` line ~485

2. **Adjust Refresh Rate**
   - Change auto-refresh interval
   - File: `dashboard.php` line ~525
   - Current: 60000ms (60 seconds)

3. **Customize Chart Colors**
   - Edit backgroundColors array
   - File: `dashboard.php` line ~845

4. **Add More Metrics**
   - Implement additional performance stats
   - Consider: ROI, Sharpe ratio, volatility

5. **Create Performance Graph**
   - Add line chart showing value over time
   - Requires historical data tracking

---

## 📞 Need Help?

### Check These Files
1. `DASHBOARD_IMPROVEMENTS.md` - Full documentation
2. Browser Console (F12) - Error messages
3. `logs/` folder - PHP error logs

### Common Questions

**Q: How do I add more cryptocurrencies?**  
A: Edit the `<select id="cryptoSelect">` in dashboard.php, add `<option>` elements

**Q: Can I track multiple purchases of the same crypto?**  
A: Not currently - database has UNIQUE constraint. Future enhancement planned.

**Q: Where is the data stored?**  
A: MySQL database → `crypto_trading` → `portfolio_holdings` table

**Q: How accurate are the prices?**  
A: Prices from CoinGecko API, updated every 60 seconds. May have slight delays.

---

**Ready to test!** 🚀

Start your PHP server and access:  
`http://localhost:8000/dashboard.php`

Happy trading! 📈💰
