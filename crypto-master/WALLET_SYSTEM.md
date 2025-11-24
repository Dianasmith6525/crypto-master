# 💰 Crypto Wallet System - User Guide

## Overview
The wallet system allows users to deposit cryptocurrency and see their balance in USD for trading and investment purposes.

## Features Implemented

### 1. **Wallet Management**
- Multi-currency wallet support (default: USD)
- Real-time balance tracking
- Automatic wallet creation for new users

### 2. **Crypto Deposits**
- Support for 10+ major cryptocurrencies:
  - Bitcoin (BTC)
  - Ethereum (ETH)
  - Tether (USDT)
  - Binance Coin (BNB)
  - USD Coin (USDC)
  - Ripple (XRP)
  - Cardano (ADA)
  - Solana (SOL)
  - Polkadot (DOT)
  - Dogecoin (DOGE)

- Unique deposit addresses for each cryptocurrency
- QR code generation for easy deposits
- Transaction hash tracking
- Multi-confirmation support
- Automatic USD conversion based on live prices

### 3. **Transaction History**
- Complete audit trail of all transactions
- Types: Deposits, Withdrawals, Trades, Fees, Bonuses
- Balance before/after tracking
- Reference linking to original operations

### 4. **Deposit Tracking**
- Real-time deposit status (pending, confirmed, completed, failed)
- Confirmation counter
- Network selection
- Automatic balance updates on confirmation

## Setup Instructions

### Step 1: Database Setup
```bash
# Navigate to your project directory
cd C:\Users\USER\Downloads\crypto-master\crypto-master

# Run the wallet setup script
C:\php\php.exe setup-wallet.php
```

Or visit: `http://localhost:8000/setup-wallet.php`

This will create the following tables:
- `user_wallets` - User balance storage
- `crypto_deposits` - Deposit records
- `crypto_withdrawals` - Withdrawal records
- `deposit_addresses` - Unique addresses per user/crypto
- `wallet_transactions` - Complete transaction log

### Step 2: Access Wallet
1. Login to your account
2. Click "💰 Wallet" in the dashboard navigation
3. View your balance and transaction history

### Step 3: Make a Deposit
1. Click "Deposit Crypto" button
2. Select cryptocurrency from dropdown
3. Copy the deposit address or scan QR code
4. Send crypto to the provided address
5. Enter amount and transaction hash (optional)
6. Submit deposit

### Step 4: Confirm Deposit (Admin/Automated)
For testing purposes, use the API:
```javascript
POST api/wallet.php
{
    action: 'confirm_deposit',
    deposit_id: 1,
    csrf_token: 'token_here'
}
```

In production, this would be automated via:
- Blockchain webhooks
- Cron jobs checking confirmations
- Payment processor integration

## Dashboard Integration

The wallet balance is displayed on the main dashboard:
- **Wallet Balance Card** - Shows current USD balance
- **Quick Deposit Link** - Direct access to deposit page
- **Auto-refresh** - Updates every 60 seconds

## Security Features

✅ **CSRF Protection** - All POST requests validated  
✅ **Authentication Required** - User must be logged in  
✅ **SQL Injection Prevention** - Prepared statements  
✅ **Transaction Logging** - Complete audit trail  
✅ **Balance Locking** - FOR UPDATE on balance changes  

## API Endpoints

### `api/wallet.php`

**Get Balance**
```
GET api/wallet.php?action=get_balance
Response: { success: true, balance: 1000.00, formatted: "$1,000.00" }
```

**Get Deposit Address**
```
POST api/wallet.php
{ action: 'get_deposit_address', crypto: 'BTC', network: 'Bitcoin' }
Response: { success: true, address: '1A1zP1...', qr_code: 'url' }
```

**Create Deposit**
```
POST api/wallet.php
{ 
    action: 'create_deposit',
    crypto: 'BTC',
    crypto_name: 'bitcoin',
    amount: 0.5,
    tx_hash: '0x123...',
    csrf_token: 'token'
}
```

**Get Deposits**
```
GET api/wallet.php?action=get_deposits&limit=10&status=all
Response: { success: true, deposits: [...] }
```

**Get Transactions**
```
GET api/wallet.php?action=get_transactions&limit=20
Response: { success: true, transactions: [...] }
```

## Database Schema

### user_wallets
```sql
wallet_id       INT PRIMARY KEY AUTO_INCREMENT
user_id         INT NOT NULL
balance         DECIMAL(20, 8) DEFAULT 0.00000000
currency        VARCHAR(10) DEFAULT 'USD'
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

### crypto_deposits
```sql
deposit_id              INT PRIMARY KEY AUTO_INCREMENT
user_id                 INT NOT NULL
crypto_symbol           VARCHAR(20) NOT NULL
crypto_name             VARCHAR(100) NOT NULL
amount                  DECIMAL(20, 8) NOT NULL
usd_value               DECIMAL(20, 2) NOT NULL
deposit_address         VARCHAR(255) NOT NULL
transaction_hash        VARCHAR(255)
status                  ENUM('pending', 'confirmed', 'completed', 'failed')
confirmations           INT DEFAULT 0
required_confirmations  INT DEFAULT 3
network                 VARCHAR(50)
deposit_date            TIMESTAMP
confirmed_at            TIMESTAMP NULL
notes                   TEXT
```

### wallet_transactions
```sql
transaction_id    INT PRIMARY KEY AUTO_INCREMENT
user_id          INT NOT NULL
type             ENUM('deposit', 'withdrawal', 'trade', 'fee', 'bonus', 'transfer')
amount           DECIMAL(20, 8) NOT NULL
currency         VARCHAR(10) NOT NULL
balance_before   DECIMAL(20, 8) NOT NULL
balance_after    DECIMAL(20, 8) NOT NULL
reference_id     INT
reference_type   VARCHAR(50)
description      TEXT
created_at       TIMESTAMP
```

## Production Considerations

### 1. **Blockchain Integration**
Replace the mock address generator with real blockchain APIs:
- **Bitcoin**: BlockCypher, Blockchain.info API
- **Ethereum**: Infura, Alchemy, Web3.js
- **Multi-chain**: Moralis, Tatum

### 2. **Webhook Setup**
Configure payment processors to notify your system:
```php
// webhook-handler.php
if ($confirmations >= $required_confirmations) {
    confirm_deposit($deposit_id);
    update_user_balance($user_id, $usd_value);
}
```

### 3. **Cron Jobs**
Check pending deposits every 5 minutes:
```bash
*/5 * * * * php /path/to/check-deposits-cron.php
```

### 4. **Price Feeds**
Use reliable price APIs:
- CoinGecko API (free tier available)
- CoinMarketCap API
- Binance API
- Coinbase API

### 5. **Security Hardening**
- Implement withdrawal 2FA
- Add withdrawal whitelist addresses
- Set daily/weekly withdrawal limits
- Enable email confirmations
- Add rate limiting
- Implement hot/cold wallet separation

## User Flow

```
1. User logs in
   ↓
2. Navigates to Wallet page
   ↓
3. Clicks "Deposit Crypto"
   ↓
4. Selects cryptocurrency (e.g., Bitcoin)
   ↓
5. System generates unique deposit address
   ↓
6. User sends crypto to address
   ↓
7. Blockchain confirms transaction
   ↓
8. System detects deposit (webhook/cron)
   ↓
9. Converts crypto to USD at current rate
   ↓
10. Updates user wallet balance
    ↓
11. User sees updated balance
    ↓
12. Balance available for trading/investment
```

## Testing

### Manual Testing
1. Run `setup-wallet.php`
2. Login as test user
3. Go to wallet page
4. Try depositing different cryptocurrencies
5. Check deposit list updates
6. Manually confirm deposit via API
7. Verify balance increases

### Test Deposit Confirmation
```javascript
// In browser console or via Postman
fetch('api/wallet.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=confirm_deposit&deposit_id=1&csrf_token=YOUR_TOKEN'
})
.then(r => r.json())
.then(console.log);
```

## Future Enhancements

- [ ] Withdrawal functionality
- [ ] Internal transfers between users
- [ ] Multi-currency wallet support (hold actual crypto)
- [ ] Staking rewards
- [ ] Interest on balances
- [ ] Fiat deposit options (credit card, bank transfer)
- [ ] Exchange integration (buy/sell crypto)
- [ ] Mobile app support
- [ ] Push notifications for deposits
- [ ] Advanced charts and analytics

## Support

For issues or questions:
1. Check database tables are created
2. Verify PHP extensions (mysqli, curl)
3. Check error logs
4. Ensure CSRF tokens are being generated
5. Verify user authentication is working

## Files Created

- `setup-wallet.php` - Database setup script
- `api/wallet.php` - Wallet API endpoints
- `wallet.php` - User wallet interface
- `WALLET_SYSTEM.md` - This documentation

---

**Note**: This is a development implementation. For production use, integrate with actual blockchain APIs, implement proper security measures, and comply with financial regulations in your jurisdiction.
