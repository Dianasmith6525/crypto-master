<?php
require_once 'includes/config.php';

// Redirect if already logged in as admin
if (is_user_authenticated() && is_admin()) {
    header('Location: admin-dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Email and password are required';
    } else {
        // Check credentials and verify admin role
        $stmt = $conn->prepare("SELECT user_id, email, password_hash, full_name, role, status FROM users WHERE email = ? AND role = 'admin'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = 'Invalid admin credentials';
            log_security_event('admin_login_failed', $email, 'Invalid credentials');
        } else {
            $user = $result->fetch_assoc();
            
            if ($user['status'] !== 'active') {
                $error = 'Account is not active';
                log_security_event('admin_login_failed', $email, 'Account not active');
            } elseif (password_verify($password, $user['password_hash'])) {
                // Successful login
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];
                
                // Update last login
                $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                $stmt->bind_param("i", $user['user_id']);
                $stmt->execute();
                
                log_security_event('admin_login_success', $email, 'Admin login successful');
                log_admin_activity('login', 'system', null, 'Admin logged in');
                
                header('Location: admin-dashboard.php');
                exit;
            } else {
                $error = 'Invalid admin credentials';
                log_security_event('admin_login_failed', $email, 'Invalid password');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">
    <title>Admin Login - Crypto Trading Platform</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/font-awesome.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
        }
        
        .admin-login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
            width: 100%;
            max-width: 450px;
        }
        
        .admin-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .admin-header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .admin-header p {
            color: #666;
            font-size: 14px;
        }
        
        .admin-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 36px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        
        .footer-links {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
        }
        
        .footer-links a {
            color: #667eea;
            text-decoration: none;
        }
        
        .footer-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="admin-login-container">
        <div class="admin-header">
            <div class="admin-icon">
                <i class="fa fa-shield"></i>
            </div>
            <h1>Admin Login</h1>
            <p>Crypto Trading Platform Administration</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">
                    <i class="fa fa-envelope"></i> Admin Email
                </label>
                <input type="email" id="email" name="email" required placeholder="admin@example.com">
            </div>
            
            <div class="form-group">
                <label for="password">
                    <i class="fa fa-lock"></i> Password
                </label>
                <input type="password" id="password" name="password" required placeholder="Enter your password">
            </div>
            
            <button type="submit" class="btn-login">
                <i class="fa fa-sign-in"></i> Login to Admin Panel
            </button>
        </form>
        
        <div class="footer-links">
            <a href="index.php">← Back to Homepage</a>
        </div>
    </div>
</body>
</html>
