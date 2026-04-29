<?php
/**
 * email_notifications.php - Handle all email notifications
 */

require_once __DIR__ . '/config/main_config.php';

/**
 * Send email to user
 */
function sendEmail($to, $subject, $message, $headers = '') {
    if (empty($headers)) {
        $headers = "From: noreply@bitchatbot.io\r\n";
        $headers .= "Reply-To: support@bitchatbot.io\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    }
    
    return mail($to, $subject, $message, $headers);
}

/**
 * Log email sent to database
 */
function logEmail($conn, $user_id, $email_type, $status = 'sent') {
    $stmt = $conn->prepare("INSERT INTO email_logs (user_id, email_type, status) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $email_type, $status);
    $stmt->execute();
    $stmt->close();
}

/**
 * Check if email was already sent today (prevent duplicates)
 */
function hasEmailSentToday($conn, $user_id, $email_type) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM email_logs WHERE user_id = ? AND email_type = ? AND DATE(sent_at) = CURDATE()");
    $stmt->bind_param("is", $user_id, $email_type);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['count'] > 0;
}

/**
 * Send plan expiry reminder (2-3 days before)
 */
function sendExpiryReminder($conn, $user, $days_left) {
    if ($user['email_consent'] != 1) return false;
    if (hasEmailSentToday($conn, $user['id'], "reminder_{$days_left}_days")) return false;
    
    $email_type = $days_left <= 2 ? 'reminder_2_days' : 'reminder_3_days';
    
    $subject = "⚠️ Your Bitchatbot Plan Expires in {$days_left} Days";
    $message = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <h2 style='color: #6C47FF;'>Your Plan is About to Expire</h2>
        <p>Hi <strong>{$user['username']}</strong>,</p>
        <p>Your Bitchatbot plan (<strong>" . strtoupper($user['plan']) . "</strong>) will expire in <strong style='color: #F59E0B;'>{$days_left} days</strong>.</p>
        <p>To ensure uninterrupted service for your chatbot, please renew your plan before it expires.</p>
        <div style='margin: 30px 0;'>
            <a href='https://bitchatbot.io/select_plan.php?renew=1' 
               style='background: #6C47FF; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;'>
                Renew Your Plan
            </a>
        </div>
        <p style='color: #6B7280; font-size: 14px;'>If you have any questions, please contact our support team.</p>
        <hr style='border: none; border-top: 1px solid #E5E7EB; margin: 20px 0;'>
        <p style='color: #9CA3AF; font-size: 12px;'>Bitchatbot - AI Chatbot Platform</p>
    </div>
    ";
    
    $sent = sendEmail($user['email'], $subject, $message);
    if ($sent) {
        logEmail($conn, $user['id'], $email_type, 'sent');
    } else {
        logEmail($conn, $user['id'], $email_type, 'failed');
    }
    
    return $sent;
}

/**
 * Send plan expired notification
 */
function sendExpiredNotification($conn, $user) {
    if ($user['email_consent'] != 1) return false;
    if (hasEmailSentToday($conn, $user['id'], 'expired')) return false;
    
    $subject = "🚨 Your Bitchatbot Plan Has Expired";
    $message = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <h2 style='color: #EF4444;'>Your Plan Has Expired</h2>
        <p>Hi <strong>{$user['username']}</strong>,</p>
        <p>Your Bitchatbot plan (<strong>" . strtoupper($user['plan']) . "</strong>) has <strong style='color: #EF4444;'>expired</strong>.</p>
        <p>Your chatbot is no longer active. To restore service immediately, please purchase a new plan.</p>
        <div style='margin: 30px 0;'>
            <a href='https://bitchatbot.io/select_plan.php?renew=1' 
               style='background: #EF4444; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;'>
                Buy New Plan
            </a>
        </div>
        <p style='color: #6B7280; font-size: 14px;'>Need help? Contact our support team.</p>
        <hr style='border: none; border-top: 1px solid #E5E7EB; margin: 20px 0;'>
        <p style='color: #9CA3AF; font-size: 12px;'>Bitchatbot - AI Chatbot Platform</p>
    </div>
    ";
    
    $sent = sendEmail($user['email'], $subject, $message);
    if ($sent) {
        logEmail($conn, $user['id'], 'expired', 'sent');
    } else {
        logEmail($conn, $user['id'], 'expired', 'failed');
    }
    
    return $sent;
}
