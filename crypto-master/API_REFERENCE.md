# 📚 API Reference

## Base URL
```
http://yoursite.com/api/
```

---

## Authentication API

### 1. Register User
**Endpoint:** `POST /api/register.php`

**Parameters:**
```json
{
  "action": "register",
  "full_name": "John Doe",
  "email": "john@example.com",
  "password": "SecurePass123!",
  "confirm_password": "SecurePass123!",
  "agree_terms": 1
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Registration successful! Please check your email to verify your account.",
  "user_id": 1
}
```

**Error Response:**
```json
{
  "success": false,
  "errors": [
    "Email already registered",
    "Password too weak"
  ]
}
```

---

### 2. Check Email Exists
**Endpoint:** `POST /api/register.php`

**Parameters:**
```json
{
  "action": "check_email_exists",
  "email": "john@example.com"
}
```

**Response:**
```json
{
  "exists": false
}
```

---

### 3. Verify Email
**Endpoint:** `POST /api/register.php`

**Parameters:**
```json
{
  "action": "verify_email",
  "token": "abc123token",
  "email": "john@example.com"
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Email verified successfully! You can now login."
}
```

---

### 4. Login User
**Endpoint:** `POST /api/login.php`

**Parameters:**
```json
{
  "action": "login",
  "email": "john@example.com",
  "password": "SecurePass123!",
  "remember_me": 1
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "user_id": 1,
  "user_name": "John Doe",
  "user_email": "john@example.com"
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Invalid email or password"
}
```

**Rate Limited Response (HTTP 429):**
```json
{
  "success": false,
  "message": "Too many login attempts. Please try again in 15 minutes."
}
```

---

### 5. Logout User
**Endpoint:** `POST /api/login.php`

**Parameters:**
```json
{
  "action": "logout"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

---

### 6. Forgot Password
**Endpoint:** `POST /api/login.php`

**Parameters:**
```json
{
  "action": "forgot_password",
  "email": "john@example.com"
}
```

**Response:**
```json
{
  "success": true,
  "message": "If an account exists with this email, a password reset link will be sent"
}
```

---

### 7. Reset Password
**Endpoint:** `POST /api/login.php`

**Parameters:**
```json
{
  "action": "reset_password",
  "token": "abc123token",
  "email": "john@example.com",
  "new_password": "NewPass123!",
  "confirm_password": "NewPass123!"
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Password reset successfully. You can now login with your new password."
}
```

---

## Portfolio API

### Authentication Required ✅
All portfolio endpoints require user to be logged in. Session will be automatically checked.

---

### 1. Add Holding
**Endpoint:** `POST /api/portfolio.php`

**Parameters:**
```json
{
  "action": "add_holding",
  "crypto_id": "bitcoin",
  "crypto_symbol": "BTC",
  "crypto_name": "Bitcoin",
  "quantity": 0.5,
  "entry_price": 45000,
  "entry_date": "2024-01-15",
  "notes": "Purchased from Coinbase"
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Holding added successfully",
  "holding_id": 42
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "You already have this cryptocurrency in your portfolio"
}
```

---

### 2. Update Holding
**Endpoint:** `POST /api/portfolio.php`

**Parameters:**
```json
{
  "action": "update_holding",
  "holding_id": 42,
  "quantity": 1.0,
  "entry_price": 45000,
  "entry_date": "2024-01-15",
  "notes": "Updated quantity"
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Holding updated successfully"
}
```

---

### 3. Remove Holding
**Endpoint:** `POST /api/portfolio.php`

**Parameters:**
```json
{
  "action": "remove_holding",
  "holding_id": 42
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Holding removed successfully"
}
```

---

### 4. Get All Holdings
**Endpoint:** `POST /api/portfolio.php`

**Parameters:**
```json
{
  "action": "get_holdings"
}
```

**Response:**
```json
{
  "success": true,
  "holdings": [
    {
      "holding_id": 1,
      "crypto_id": "bitcoin",
      "crypto_symbol": "BTC",
      "crypto_name": "Bitcoin",
      "quantity": 0.5,
      "entry_price": 45000,
      "entry_date": "2024-01-15",
      "notes": "First Bitcoin purchase",
      "date_added": "2024-01-15 10:30:00"
    },
    {
      "holding_id": 2,
      "crypto_id": "ethereum",
      "crypto_symbol": "ETH",
      "crypto_name": "Ethereum",
      "quantity": 5,
      "entry_price": 2500,
      "entry_date": "2024-01-16",
      "notes": "Ethereum holdings",
      "date_added": "2024-01-16 14:20:00"
    }
  ],
  "count": 2
}
```

---

### 5. Get Portfolio Summary
**Endpoint:** `POST /api/portfolio.php`

**Parameters:**
```json
{
  "action": "get_portfolio_summary"
}
```

**Response:**
```json
{
  "success": true,
  "portfolio": [
    {
      "crypto_id": "bitcoin",
      "crypto_symbol": "BTC",
      "crypto_name": "Bitcoin",
      "total_quantity": 0.5,
      "avg_entry_price": 45000,
      "total_invested": 22500
    },
    {
      "crypto_id": "ethereum",
      "crypto_symbol": "ETH",
      "crypto_name": "Ethereum",
      "total_quantity": 5,
      "avg_entry_price": 2500,
      "total_invested": 12500
    }
  ],
  "total_invested": 35000,
  "crypto_count": 2,
  "total_holdings": 5.5
}
```

---

## Error Codes

### HTTP Status Codes

| Code | Meaning | Example |
|------|---------|---------|
| 200 | Success | Holding added |
| 400 | Bad Request | Invalid parameters |
| 401 | Unauthorized | Not logged in |
| 404 | Not Found | Holding doesn't exist |
| 429 | Too Many Requests | Rate limited |
| 500 | Server Error | Database error |

---

## Rate Limiting

**Login Endpoint:**
- Max 5 attempts per 15 minutes per email
- Locked out after exceeding limit
- HTTP 429 response

---

## Security Notes

1. **Authentication**
   - All requests must use HTTP POST
   - Session cookie required for protected endpoints
   - No API keys needed (session-based auth)

2. **Validation**
   - All inputs are sanitized
   - All data is validated server-side
   - Passwords hashed with bcrypt

3. **HTTPS**
   - Use HTTPS in production
   - Cookies marked as secure & httponly

4. **CSRF**
   - Implement CSRF tokens for production
   - See SETUP_GUIDE.md for details

---

## Example Usage (jQuery)

### Register
```javascript
$.post('api/register.php', {
    action: 'register',
    full_name: 'John Doe',
    email: 'john@example.com',
    password: 'SecurePass123!',
    confirm_password: 'SecurePass123!',
    agree_terms: 1
}, function(response) {
    if (response.success) {
        alert('Registration successful! Check your email.');
    } else {
        alert('Error: ' + response.errors.join(', '));
    }
});
```

### Login
```javascript
$.post('api/login.php', {
    action: 'login',
    email: 'john@example.com',
    password: 'SecurePass123!',
    remember_me: 1
}, function(response) {
    if (response.success) {
        localStorage.setItem('user_id', response.user_id);
        localStorage.setItem('user_name', response.user_name);
        window.location.href = 'dashboard.html';
    }
});
```

### Add Holding
```javascript
$.post('api/portfolio.php', {
    action: 'add_holding',
    crypto_id: 'bitcoin',
    crypto_symbol: 'BTC',
    crypto_name: 'Bitcoin',
    quantity: 0.5,
    entry_price: 45000,
    entry_date: '2024-01-15',
    notes: 'My first Bitcoin'
}, function(response) {
    if (response.success) {
        alert('Holding added!');
        loadPortfolio();
    }
});
```

### Get Holdings
```javascript
$.post('api/portfolio.php', {
    action: 'get_holdings'
}, function(response) {
    if (response.success) {
        console.log('Holdings:', response.holdings);
        response.holdings.forEach(holding => {
            console.log(holding.crypto_name + ': ' + holding.quantity);
        });
    }
});
```

---

## Supported Cryptocurrencies

| ID | Symbol | Name |
|---|---|---|
| bitcoin | BTC | Bitcoin |
| ethereum | ETH | Ethereum |
| litecoin | LTC | Litecoin |
| ripple | XRP | XRP |

---

## Database Schema

### Users Table
```sql
user_id, email, password_hash, full_name, phone, country,
date_created, email_verified, email_verification_token,
last_login, status
```

### Portfolio Holdings Table
```sql
holding_id, user_id, crypto_id, crypto_symbol, crypto_name,
quantity, entry_price, entry_date, date_added, date_updated, notes
```

---

**API Version:** 1.0
**Last Updated:** November 2024
