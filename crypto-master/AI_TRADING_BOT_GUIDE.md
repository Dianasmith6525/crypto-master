# 🤖 AI Trading Bot System

## Overview
The AI Trading Bot system enables users to automate their cryptocurrency trading with intelligent strategies, risk management, and performance tracking.

## Features

### 🎯 Trading Strategies
1. **Scalping** - Quick trades, small profits, high frequency
2. **Day Trading** - Intraday positions, moderate frequency
3. **Swing Trading** - Multi-day positions, trend following
4. **Grid Trading** - Buy low, sell high in ranges

### 🛡️ Risk Management
- **Conservative** - Low risk, stable returns
- **Moderate** - Balanced approach
- **Aggressive** - High risk, high reward

### 📊 Technical Analysis
- RSI (Relative Strength Index)
- Moving Averages (7-day, 14-day)
- Volatility calculation
- Trend detection
- AI confidence scoring

### 💰 Position Management
- Automatic take profit
- Stop loss protection
- Trailing stop option
- Maximum trades per day
- Min/max trade amounts
- Balance management

## Setup Instructions

### Step 1: Database Setup
```bash
# Navigate to project directory
cd C:\Users\USER\Downloads\crypto-master\crypto-master

# Run bot setup script
C:\php\php.exe setup-trading-bots.php
```

Or visit: `http://localhost:8000/setup-trading-bots.php`

This creates 5 tables:
- **trading_bots** - Bot configurations
- **bot_trades** - Individual trade records
- **bot_performance** - Daily performance metrics
- **ai_signals** - AI-generated trading signals
- **bot_logs** - Activity and error logs

### Step 2: Create Your First Bot
1. Visit `http://localhost:8000/ai-trading-bot.php`
2. Click "Create New Bot"
3. Configure:
   - Bot Name
   - Trading Strategy
   - Cryptocurrency
   - Investment Amount
   - Risk Level
   - Take Profit %
   - Stop Loss %
4. Click "Create Bot"

### Step 3: Start the Bot
1. Click "Start" on your bot card
2. Bot status changes to "Active"
3. Bot will execute when cron runs

### Step 4: Setup Automated Execution

#### Linux/Mac (Cron Job)
```bash
# Edit crontab
crontab -e

# Add this line to run every 5 minutes
*/5 * * * * /usr/bin/php /path/to/bot-executor.php >> /path/to/logs/bot-executor.log 2>&1
```

#### Windows (Task Scheduler)
1. Open Task Scheduler
2. Create Basic Task
3. Name: "AI Trading Bot Executor"
4. Trigger: Repeat every 5 minutes
5. Action: Start a program
   - Program: `C:\php\php.exe`
   - Arguments: `C:\path\to\bot-executor.php`
6. Save and enable

#### Manual Execution (Testing)
```bash
# Run once manually
C:\php\php.exe bot-executor.php
```

## How It Works

### 1. Signal Generation
Every execution cycle, the bot:
- Fetches current crypto prices from CoinGecko
- Gets 14-day price history
- Calculates technical indicators (RSI, MA, volatility)
- Analyzes market conditions
- Generates BUY/SELL/HOLD signal with confidence score

### 2. Trade Execution
If signal confidence exceeds threshold:
- **BUY Signal**: Opens new position
- **SELL Signal**: Closes existing position
- **HOLD Signal**: Maintains current positions

### 3. Position Management
Active trades are monitored for:
- Take profit targets
- Stop loss limits
- Trailing stops (if enabled)
- Time-based exits (for day trading)

### 4. Performance Tracking
Daily metrics calculated:
- Total trades executed
- Win/loss ratio
- Profit/loss amounts
- Win rate percentage
- Average profit per trade

## Strategy Details

### Scalping Strategy
- **Objective**: Quick profits from small price movements
- **Holding Time**: Minutes to hours
- **Trades/Day**: High frequency (up to max limit)
- **Risk**: Lower per trade, higher overall
- **Best For**: Volatile markets, active monitoring

### Day Trading Strategy
- **Objective**: Capture intraday trends
- **Holding Time**: Hours (closes before midnight)
- **Trades/Day**: Moderate frequency
- **Risk**: Balanced
- **Best For**: Strong daily trends

### Swing Trading Strategy
- **Objective**: Ride multi-day trends
- **Holding Time**: Days to weeks
- **Trades/Day**: Low frequency
- **Risk**: Higher per trade
- **Best For**: Trending markets, patient traders

### Grid Trading Strategy
- **Objective**: Profit from price oscillations
- **Holding Time**: Variable
- **Trades/Day**: Multiple simultaneous positions
- **Risk**: Distributed across grid
- **Best For**: Range-bound markets

## Configuration Parameters

### Bot Settings
```
bot_name: Custom name for your bot
strategy: scalping|day_trading|swing_trading|grid_trading
crypto_symbol: BTC|ETH|BNB|SOL|XRP|ADA
investment_amount: Total capital allocated ($10-$10,000)
risk_level: conservative|moderate|aggressive
```

### Risk Controls
```
take_profit_percent: Exit when profit reaches % (default: 5%)
stop_loss_percent: Exit when loss reaches % (default: 3%)
max_trades_per_day: Daily trade limit (default: 10)
min_trade_amount: Minimum per trade ($10)
max_trade_amount: Maximum per trade ($1,000)
use_trailing_stop: Enable trailing stop (true/false)
trailing_stop_percent: Trailing stop distance (default: 2%)
ai_confidence_threshold: Minimum signal confidence (default: 70%)
```

## API Endpoints

### Bot Management
```javascript
// Create bot
POST api/trading-bot.php
{
    action: 'create_bot',
    bot_name: 'My Bot',
    strategy: 'scalping',
    crypto_symbol: 'BTC',
    investment_amount: 100,
    risk_level: 'moderate'
}

// Get all bots
GET api/trading-bot.php?action=get_bots&status=all

// Start bot
POST api/trading-bot.php
{ action: 'start_bot', bot_id: 1 }

// Pause bot
POST api/trading-bot.php
{ action: 'pause_bot', bot_id: 1 }

// Stop bot (closes all trades)
POST api/trading-bot.php
{ action: 'stop_bot', bot_id: 1 }
```

### Trade History
```javascript
// Get trades
GET api/trading-bot.php?action=get_trades&bot_id=1&status=all&limit=50

// Get performance
GET api/trading-bot.php?action=get_performance&bot_id=1&days=30
```

### AI Signals
```javascript
// Get signals
GET api/trading-bot.php?action=get_signals&crypto_symbol=BTC&limit=10

// Generate new signal
POST api/trading-bot.php
{ action: 'generate_ai_signal', crypto_symbol: 'BTC' }
```

## Database Schema

### trading_bots
```sql
bot_id: Primary key
user_id: Owner
bot_name: Display name
strategy: Trading strategy type
status: active|paused|stopped
crypto_symbol, crypto_name: Asset to trade
investment_amount: Allocated capital
risk_level: conservative|moderate|aggressive
take_profit_percent, stop_loss_percent: Exit targets
max_trades_per_day: Daily limit
ai_confidence_threshold: Minimum signal confidence
total_profit_loss: Cumulative P/L
total_trades, winning_trades, losing_trades: Stats
last_trade_at: Last execution time
```

### bot_trades
```sql
trade_id: Primary key
bot_id, user_id: Ownership
trade_type: buy|sell
crypto_symbol, crypto_amount: Asset and quantity
price_at_entry, price_at_exit: Entry/exit prices
usd_amount: Trade value in USD
profit_loss, profit_loss_percent: Trade result
status: open|closed|cancelled
entry_signal, exit_signal: Trade reasons
ai_confidence: Signal strength
entry_time, exit_time: Timestamps
```

### bot_performance
```sql
performance_id: Primary key
bot_id: Bot reference
date: Performance date
trades_count: Daily trades
winning_trades, losing_trades: Win/loss count
total_profit_loss: Daily P/L
win_rate: Success percentage
avg_profit_per_trade: Average result
```

## Dashboard Integration

The main dashboard displays:
- **Active Bots Count** - Number of running bots
- **Bot Performance** - Total P/L from all bots
- **Win Rate** - Overall success percentage
- **Quick Link** - Direct access to bot management

## Safety Features

### Balance Protection
✅ Checks wallet balance before opening trades  
✅ Prevents overdraft with investment limits  
✅ Automatic position sizing based on risk level  

### Trade Limits
✅ Maximum trades per day  
✅ Minimum/maximum trade amounts  
✅ Confidence threshold filtering  

### Risk Management
✅ Automatic stop loss  
✅ Take profit targets  
✅ Trailing stop option  
✅ Portfolio exposure limits  

### Logging & Monitoring
✅ Complete trade history  
✅ Bot activity logs  
✅ Error tracking  
✅ Performance metrics  

## Production Considerations

### 1. API Rate Limits
CoinGecko free tier: 10-50 calls/minute
- Cache price data
- Batch requests
- Consider premium API key

### 2. Execution Frequency
Default: Every 5 minutes
- Scalping: 1-5 minutes
- Day trading: 5-15 minutes
- Swing trading: 30-60 minutes
- Grid trading: 5-10 minutes

### 3. Market Hours
Crypto trades 24/7, but consider:
- Peak liquidity hours
- News events
- Maintenance windows

### 4. Real Exchange Integration
Replace price fetching with:
- Binance API
- Coinbase Pro API
- Kraken API
- FTX API (for actual execution)

### 5. Advanced Features
- Multiple exchange support
- Arbitrage detection
- News sentiment analysis
- Advanced order types (limit, market, stop-limit)
- Portfolio rebalancing
- Tax reporting

## Troubleshooting

### Bot Not Executing Trades
1. Check bot status is "active"
2. Verify cron job is running
3. Check wallet balance sufficient
4. Review bot logs for errors
5. Confirm API endpoints working

### Low Profitability
1. Adjust confidence threshold
2. Review take profit/stop loss settings
3. Try different strategy
4. Increase investment amount
5. Optimize risk level

### Too Many Trades
1. Reduce max_trades_per_day
2. Increase confidence threshold
3. Adjust strategy parameters
4. Check for API issues

## Files Created

- `setup-trading-bots.php` - Database setup
- `ai-trading-bot.php` - User interface
- `api/trading-bot.php` - API endpoints
- `bot-executor.php` - Automated execution engine
- `AI_TRADING_BOT_GUIDE.md` - This documentation

## Next Steps

1. ✅ Run `setup-trading-bots.php`
2. ✅ Create your first bot
3. ✅ Configure bot settings
4. ✅ Start the bot
5. ✅ Setup cron/task scheduler
6. ✅ Monitor performance
7. ⏳ Adjust strategy based on results
8. ⏳ Scale with multiple bots

## Support & Resources

- **CoinGecko API**: https://www.coingecko.com/api/documentation
- **Technical Analysis**: https://www.investopedia.com/technical-analysis-4689657
- **Risk Management**: https://www.investopedia.com/trading/risk-management/

---

**Disclaimer**: Cryptocurrency trading involves substantial risk. This bot is for educational purposes. Always start with small amounts and never invest more than you can afford to lose.
