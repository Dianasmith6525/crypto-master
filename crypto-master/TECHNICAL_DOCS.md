# 🔧 Technical Documentation

## System Architecture

### Tech Stack
- **Frontend:** HTML5, CSS3, JavaScript (jQuery 3.2.1)
- **Backend:** PHP 7.2+
- **Database:** MySQL 5.7+
- **Protocol:** HTTP/HTTPS, POST requests
- **Authentication:** Session-based with localStorage

### Application Flow

```
┌─────────────────────────────────────────────┐
│        User Visits index.html               │
└────────────────┬────────────────────────────┘
                 │
                 ▼
    ┌────────────────────────┐
    │  Check localStorage    │
    │   for user_id          │
    └────────┬───────────────┘
             │
      ┌──────┴──────┐
      │             │
   Found        Not Found
      │             │
      ▼             ▼
 Show "Dashboard"  Show "Login/Signup"
 & "Logout" btn   & "Sign Up" button
      
User clicks "Sign Up" or existing user clicks "Login"
      │
      ▼
┌─────────────────────────────────────────────┐
│    register.html or login.html              │
└────────────────┬────────────────────────────┘
                 │
         Fill Form & Submit
                 │
                 ▼
    ┌────────────────────────┐
    │  Client-side Validation│
    │  (jQuery)              │
    └────────┬───────────────┘
             │
      (If Valid)
             │
             ▼
    ┌─────────────────────────────┐
    │ POST to /api/register.php   │
    │   or /api/login.php         │
    │  (AJAX - No page reload)    │
    └────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────┐
│    PHP Backend Processing                   │
├─────────────────────────────────────────────┤
│ 1. Server-side validation                   │
│ 2. Sanitize inputs                          │
│ 3. Check database                           │
│ 4. Hash passwords / Generate tokens         │
│ 5. Create/Update database records           │
│ 6. Send email verification                  │
│ 7. Return JSON response                     │
└────────┬─────────────────────────────────────┘
         │
         ▼
    ┌────────────────────────────────┐
    │ Response JSON to Frontend       │
    │ {success: true/false, ...}     │
    └────────┬─────────────────────────┘
             │
         Parse Response
             │
      ┌──────┴──────┐
      │             │
   Success      Error
      │             │
      ▼             ▼
Store in         Show Error
localStorage     Message
      │
Redirect to
dashboard.html
```

---

## Code Structure

### File Organization
```
crypto-master/
├── Frontend Files
│   ├── index.html           # Landing page
│   ├── register.html        # Registration
│   ├── login.html           # Login
│   ├── dashboard.html       # Portfolio
│   ├── css/
│   │   ├── style.css
│   │   ├── bootstrap.min.css
│   │   └── ...
│   └── js/
│       ├── main.js          # Chart functionality
│       └── jquery-3.2.1.min.js
│
├── Backend Files
│   ├── api/
│   │   ├── register.php     # Registration API
│   │   ├── login.php        # Authentication API
│   │   └── portfolio.php    # Portfolio API
│   ├── includes/
│   │   └── config.php       # Database & config
│   │
├── Database
│   └── db-schema.sql        # Schema
│
└── Documentation
    ├── SETUP_GUIDE.md
    ├── API_REFERENCE.md
    ├── FEATURES_BUILT.md
    └── DELIVERY_SUMMARY.md
```

---

## Database Design

### Users Table
```sql
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    email_verified BOOLEAN DEFAULT FALSE,
    email_verification_token VARCHAR(255),
    last_login TIMESTAMP,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active'
);
```

**Indexes:** email (UNIQUE), status

### Portfolio Holdings Table
```sql
CREATE TABLE portfolio_holdings (
    holding_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    crypto_id VARCHAR(50) NOT NULL,
    crypto_symbol VARCHAR(10) NOT NULL,
    crypto_name VARCHAR(100) NOT NULL,
    quantity DECIMAL(20, 8) NOT NULL,
    entry_price DECIMAL(20, 2) NOT NULL,
    entry_date DATE NOT NULL,
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_crypto (user_id, crypto_id)
);
```

**Indexes:** user_id, crypto_id, unique_user_crypto

---

## Security Implementation

### Password Security
```php
// Hashing (register/password reset)
$password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Verification (login)
if (password_verify($password, $hash)) {
    // Password correct
}
```

### Input Sanitization
```php
// Prevents SQL injection
$email = sanitize_input($_POST['email']);

function sanitize_input($data) {
    return $conn->real_escape_string(trim(strip_tags($data)));
}

// Database prepared statements
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
```

### Session Security
```php
// Session regeneration after login
session_regenerate_id(true);

// Session timeout
session_set_cookie_params(SESSION_TIMEOUT);
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
```

### Rate Limiting
```php
// Check login attempts
$sql = "SELECT COUNT(*) as attempts FROM login_attempts 
        WHERE identifier = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";

if ($result['attempts'] >= MAX_LOGIN_ATTEMPTS) {
    http_response_code(429);
    return false;
}
```

---

## API Endpoints

### Authentication Endpoints
```
POST /api/register.php
├── action: "register"      → Create new user
├── action: "verify_email"  → Verify email address
└── action: "check_email_exists" → Check email availability

POST /api/login.php
├── action: "login"         → User login
├── action: "logout"        → User logout
├── action: "forgot_password" → Request password reset
└── action: "reset_password" → Reset password with token
```

### Portfolio Endpoints
```
POST /api/portfolio.php
├── action: "add_holding"   → Add crypto to portfolio
├── action: "update_holding" → Update holding details
├── action: "remove_holding" → Delete holding
├── action: "get_holdings"  → Get all holdings
└── action: "get_portfolio_summary" → Portfolio summary
```

---

## Data Flow Examples

### Registration Flow
```
1. User fills form (register.html)
   ↓
2. jQuery validates locally
   ↓
3. AJAX POST to /api/register.php
   ↓
4. PHP validates input
   ↓
5. Check email uniqueness (database)
   ↓
6. Hash password (bcrypt)
   ↓
7. Generate verification token
   ↓
8. Insert into database
   ↓
9. Send verification email
   ↓
10. Return success JSON
   ↓
11. jQuery shows message
   ↓
12. Redirect to login.html
```

### Login Flow
```
1. User enters credentials (login.html)
   ↓
2. jQuery validates format
   ↓
3. AJAX POST to /api/login.php
   ↓
4. PHP rate limit check
   ↓
5. Find user by email (database)
   ↓
6. Check email verified
   ↓
7. Verify password hash
   ↓
8. Create session
   ↓
9. Return user data + session
   ↓
10. jQuery stores in localStorage
    (user_id, user_name, user_email)
   ↓
11. Redirect to dashboard.html
```

### Add Holding Flow
```
1. User clicks "Add Holding" (dashboard.html)
   ↓
2. Modal form opens
   ↓
3. User fills: Crypto, Quantity, Price, Date, Notes
   ↓
4. JavaScript validates
   ↓
5. AJAX POST to /api/portfolio.php
   ↓
6. PHP session check (must be logged in)
   ↓
7. Validate data (quantity > 0, price > 0, etc)
   ↓
8. Check duplicate (user already has this crypto)
   ↓
9. Insert into portfolio_holdings table
   ↓
10. Return success JSON with holding_id
   ↓
11. jQuery closes modal
   ↓
12. Reload holdings from database
   ↓
13. Update dashboard display
```

---

## Error Handling

### Frontend (jQuery)
```javascript
$.post('api/login.php', data, function(response) {
    if (response.success) {
        // Success handling
    } else {
        // Show error message
        showAlert(response.message, 'error');
    }
}).fail(function(xhr) {
    // Network/server error
    showAlert('Server error', 'error');
});
```

### Backend (PHP)
```php
// Validation error
http_response_code(400);
echo json_encode([
    'success' => false,
    'errors' => $errors
]);

// Authorization error
http_response_code(401);
echo json_encode([
    'success' => false,
    'message' => 'Unauthorized'
]);

// Server error
http_response_code(500);
echo json_encode([
    'success' => false,
    'message' => 'Database error'
]);
```

---

## Performance Optimizations

### Database
- Indexed columns (email, user_id, crypto_id)
- Unique constraints for fast lookups
- Foreign keys with cascading deletes
- UNIQUE combined keys (user_id, crypto_id)

### Frontend
- LocalStorage for user persistence
- Minimal AJAX calls
- Client-side validation (reduce server load)
- No unnecessary DOM manipulation
- CSS animations (GPU accelerated)

### Security
- Prepared statements (prevent SQL injection)
- Input sanitization
- Password hashing (slow by design)
- Session regeneration
- Rate limiting

---

## Scalability Considerations

### For Growing User Base

1. **Database Optimization**
   ```sql
   -- Add database replication
   -- Use connection pooling
   -- Add caching layer (Redis)
   ```

2. **Frontend Optimization**
   ```
   -- Use CDN for static files
   -- Enable gzip compression
   -- Minimize CSS/JS files
   -- Use service workers for offline
   ```

3. **Backend Optimization**
   ```php
   -- Use caching (Redis/Memcached)
   -- Implement API rate limiting
   -- Queue long-running tasks
   -- Monitor performance metrics
   ```

4. **Infrastructure**
   ```
   -- Load balancing
   -- Database replication
   -- Horizontal scaling
   -- Auto-scaling groups
   ```

---

## Monitoring & Logging

### Log Types
1. **Security Logs** - Login, registration, failed attempts
2. **API Logs** - All API calls with status
3. **Error Logs** - PHP errors and exceptions
4. **Audit Logs** - Data modifications

### Recommended Monitoring
```php
// Check security_logs table
SELECT * FROM security_logs ORDER BY timestamp DESC;

// Monitor failed attempts
SELECT * FROM login_attempts WHERE attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR);

// View API activity
SELECT * FROM api_logs WHERE status >= 400 ORDER BY timestamp DESC;
```

---

## Testing Checklist

- [ ] Register new user
- [ ] Verify email works
- [ ] Login with correct password
- [ ] Login fails with wrong password
- [ ] Rate limiting works (5+ attempts)
- [ ] Add holding works
- [ ] Edit holding works
- [ ] Delete holding works
- [ ] Portfolio summary calculates
- [ ] Logout works
- [ ] Dashboard protected (can't access without login)
- [ ] Mobile responsive
- [ ] Form validation errors show
- [ ] Success messages display
- [ ] Error messages display

---

## Common Issues & Solutions

### Email Not Sending
```php
// Check mail configuration
echo ini_get('SMTP');
echo ini_get('sendmail_from');

// Use alternative (PHPMailer)
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
```

### Session Not Working
```php
// Check session.save_path is writable
session_save_path();
chmod(session_save_path(), 0777);

// Verify session.gc_maxlifetime is sufficient
ini_set('session.gc_maxlifetime', 3600);
```

### CORS Issues (if different domain)
```php
// Add CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');
```

---

**Technical Documentation Complete** ✅
