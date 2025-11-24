<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Get CSRF Token</title>
</head>
<body>
    <h1>CSRF Token Generator</h1>
    <p>Include this in your forms and AJAX requests:</p>
    <div id="csrf-token">
        <?php
        require_once 'includes/config.php';
        $token = generate_csrf_token();
        echo '<strong>CSRF Token:</strong> ' . htmlspecialchars($token);
        echo '<br><br>';
        echo '<strong>Use in forms:</strong><br>';
        echo '<code>&lt;input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '"&gt;</code>';
        echo '<br><br>';
        echo '<strong>Use in AJAX:</strong><br>';
        echo '<code>headers: { "X-CSRF-Token": "' . htmlspecialchars($token) . '" }</code>';
        ?>
    </div>
    
    <script>
        // Make CSRF token available globally for AJAX requests
        window.CSRF_TOKEN = '<?php echo $token; ?>';
        
        // Example: Add to all fetch requests
        const originalFetch = window.fetch;
        window.fetch = function(url, options = {}) {
            options.headers = options.headers || {};
            options.headers['X-CSRF-Token'] = window.CSRF_TOKEN;
            return originalFetch(url, options);
        };
    </script>
</body>
</html>
