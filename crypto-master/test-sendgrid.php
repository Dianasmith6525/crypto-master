<?php
require 'includes/email.php';

echo "Testing SendGrid Email Configuration\n";
echo "=====================================\n\n";

$emailer = new EmailNotifier();
$result = $emailer->sendWelcomeEmail(
    'dianasmith6525@gmail.com',
    'Diana Smith'
);

if ($result) {
    echo "✅ SUCCESS! Welcome email sent via SendGrid.\n\n";
    echo "Check your inbox: dianasmith6525@gmail.com\n";
    echo "Subject: Welcome to CryptoTrade Platform! 🎉\n\n";
    echo "If you don't see it:\n";
    echo "1. Check spam/junk folder\n";
    echo "2. Wait 1-2 minutes for delivery\n";
    echo "3. Check SendGrid dashboard for delivery status\n";
} else {
    echo "❌ FAILED! Email could not be sent.\n\n";
    echo "Possible issues:\n";
    echo "1. Sender email not verified in SendGrid\n";
    echo "2. Invalid API key\n";
    echo "3. Check PHP error logs for details\n";
}
?>
