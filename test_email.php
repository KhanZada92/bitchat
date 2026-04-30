<?php
/**
 * test_email.php - Test email sending
 * Delete this file after testing!
 */

require_once 'config/main_config.php';
require_once 'email_notifications.php';

// Your email for testing
$test_email = 'anas@bitvengeres.com';

$subject = '✅ Bitchatbot Email System Test';
$message = "
<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
    <h2 style='color: #6C47FF;'>🎉 Email Test Successful!</h2>
    <p>If you're reading this, your Google Workspace email system is working correctly.</p>
    
    <div style='background: #F3F4F6; padding: 15px; border-radius: 8px; margin: 20px 0;'>
        <h3 style='color: #1F2937; margin-top: 0;'>✅ What's Working:</h3>
        <ul style='color: #4B5563; line-height: 1.8;'>
            <li>Google Workspace SMTP connection</li>
            <li>App Password authentication</li>
            <li>Email sending functionality</li>
            <li>HTML email formatting</li>
        </ul>
    </div>
    
    <p style='color: #6B7280; font-size: 14px;'>Your email system is ready to send:</p>
    <ol style='color: #4B5563; line-height: 1.8;'>
        <li>Welcome emails (on registration)</li>
        <li>Payment confirmation emails</li>
        <li>Plan expiry reminders (3 days before)</li>
        <li>Plan expiry urgent reminders (2 days before)</li>
        <li>Plan expired notifications</li>
    </ol>
    
    <hr style='border: none; border-top: 1px solid #E5E7EB; margin: 20px 0;'>
    <p style='color: #9CA3AF; font-size: 12px;'>Bitchatbot - AI Chatbot Platform</p>
</div>
";

echo "<h2>🧪 Email System Test</h2>";
echo "<p>Sending test email to: <strong>{$test_email}</strong></p>";
echo "<p>Using: Google Workspace SMTP (anas@bitvengeres.com)</p>";

$sent = sendEmail($test_email, $subject, $message);

if ($sent) {
    echo "<div style='background: #D1FAE5; padding: 20px; border-radius: 8px; border-left: 4px solid #10B981;'>";
    echo "<p style='color: #065F46; font-size: 18px; margin: 0;'>✅ Email sent successfully!</p>";
    echo "<p style='color: #047857; margin: 10px 0 0 0;'>Check your inbox at <strong>{$test_email}</strong> (and spam folder)</p>";
    echo "</div>";
} else {
    echo "<div style='background: #FEE2E2; padding: 20px; border-radius: 8px; border-left: 4px solid #EF4444;'>";
    echo "<p style='color: #991B1B; font-size: 18px; margin: 0;'>❌ Email sending failed!</p>";
    echo "<p style='color: #B91C1C; margin: 10px 0 0 0;'>Please check:</p>";
    echo "<ul style='color: #991B1B;'>";
    echo "<li>App Password is correct in config/.env</li>";
    echo "<li>2-Step Verification is enabled</li>";
    echo "<li>PHPMailer is installed (vendor/autoload.php exists)</li>";
    echo "<li>Error logs for details</li>";
    echo "</ul>";
    echo "</div>";
}

echo "<hr><p style='color: #EF4444;'><strong>⚠️ SECURITY:</strong> Delete this file after testing!</p>";
?>
