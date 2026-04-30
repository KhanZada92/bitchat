<?php
/**
 * email_notifications.php - Handle all email notifications
 * Uses PHPMailer with SMTP for reliable email delivery
 */

require_once __DIR__ . '/config/main_config.php';

// Check if PHPMailer is available
$phpmailer_available = false;
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    $phpmailer_available = class_exists('PHPMailer\PHPMailer\PHPMailer');
}

/**
 * Send email to user using PHPMailer (SMTP) or fallback to mail()
 */
function sendEmail($to, $subject, $message, $headers = '') {
    global $phpmailer_available;
    
    if ($phpmailer_available) {
        return sendEmailViaSMTP($to, $subject, $message);
    } else {
        // Fallback to PHP mail() if PHPMailer not available
        if (empty($headers)) {
            $from_email = getenv('SMTP_FROM_EMAIL') ?: 'noreply@bitchatbot.io';
            $from_name = getenv('SMTP_FROM_NAME') ?: 'Bitchatbot';
            $headers = "From: {$from_name} <{$from_email}>\r\n";
            $headers .= "Reply-To: {$from_email}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        }
        
        return mail($to, $subject, $message, $headers);
    }
}

/**
 * Send email via SMTP using PHPMailer
 */
function sendEmailViaSMTP($to, $subject, $message) {
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.yourdomain.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('SMTP_USERNAME') ?: 'noreply@bitchatbot.io';
        $mail->Password   = getenv('SMTP_PASSWORD') ?: '';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = getenv('SMTP_PORT') ?: 587;
        
        // Sender
        $from_email = getenv('SMTP_FROM_EMAIL') ?: 'noreply@bitchatbot.io';
        $from_name = getenv('SMTP_FROM_NAME') ?: 'Bitchatbot';
        $mail->setFrom($from_email, $from_name);
        
        // Recipient
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        
        // Send
        $mail->send();
        return true;
        
    } catch (\Exception $e) {
        error_log("Email sending failed: {$mail->ErrorInfo}");
        return false;
    }
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
        <h2 style='color: #F59E0B;'>Your Plan is About to Expire</h2>
        <p>Hi <strong>{$user['username']}</strong>,</p>
        <p>Your Bitchatbot plan (<strong>" . strtoupper($user['plan']) . "</strong>) will expire in <strong style='color: #F59E0B;'>{$days_left} days</strong>.</p>
        <p>To ensure uninterrupted service for your chatbot, please renew or upgrade your plan before it expires.</p>
        
        <div style='background: #FEF3C7; padding: 15px; border-radius: 8px; border-left: 4px solid #F59E0B; margin: 20px 0;'>
            <p style='margin: 0 0 10px 0; color: #92400E;'><strong>⚠️ What happens when plan expires?</strong></p>
            <ul style='margin: 0; color: #92400E; line-height: 1.8;'>
                <li>Your chatbot will stop working</li>
                <li>Embedded code will be disabled</li>
                <li>Dashboard access will be restricted</li>
            </ul>
        </div>
        
        <div style='margin: 30px 0;'>
            <a href='https://bitchatbot.io/select_plan.php?renew=1' 
               style='background: #F59E0B; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; margin-right: 10px;'>
                Renew Your Plan
            </a>
            <a href='https://bitchatbot.io/select_plan.php?upgrade=1' 
               style='background: #6C47FF; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;'>
                Upgrade Plan
            </a>
        </div>
        
        <h3 style='color: #1F2937;'>Available Plans:</h3>
        <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
            <tr style='background: #F3F4F6;'>
                <td style='padding: 12px; border: 1px solid #E5E7EB;'><strong>Basic</strong><br><span style='color: #6B7280;'>1 Chatbot</span></td>
                <td style='padding: 12px; border: 1px solid #E5E7EB; text-align: center;'><strong style='color: #10B981;'>$10/month</strong></td>
            </tr>
            <tr>
                <td style='padding: 12px; border: 1px solid #E5E7EB;'><strong>Starter</strong><br><span style='color: #6B7280;'>5 Chatbots</span></td>
                <td style='padding: 12px; border: 1px solid #E5E7EB; text-align: center;'><strong style='color: #6C47FF;'>$20/month</strong></td>
            </tr>
            <tr style='background: #F3F4F6;'>
                <td style='padding: 12px; border: 1px solid #E5E7EB;'><strong>Pro</strong><br><span style='color: #6B7280;'>10 Chatbots</span></td>
                <td style='padding: 12px; border: 1px solid #E5E7EB; text-align: center;'><strong style='color: #06B6D4;'>$30/month</strong></td>
            </tr>
        </table>
        
        <p style='color: #6B7280; font-size: 14px;'>Need help? Contact our support team.</p>
        
        <hr style='border: none; border-top: 1px solid #E5E7EB; margin: 20px 0;'>
        <p style='color: #9CA3AF; font-size: 12px;'>Bitchatbot - AI Chatbot Platform<br>
        <a href='https://bitchatbot.io' style='color: #6C47FF; text-decoration: none;'>https://bitchatbot.io</a></p>
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
    
    $subject = "🚨 Your Bitchatbot Plan Has Expired - Renew Now!";
    $message = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <h2 style='color: #EF4444;'>Your Plan Has Expired</h2>
        <p>Hi <strong>{$user['username']}</strong>,</p>
        <p>Your Bitchatbot plan (<strong>" . strtoupper($user['plan']) . "</strong>) has <strong style='color: #EF4444;'>expired</strong>.</p>
        
        <div style='background: #FEE2E2; padding: 15px; border-radius: 8px; border-left: 4px solid #EF4444; margin: 20px 0;'>
            <p style='margin: 0 0 10px 0; color: #991B1B;'><strong>❌ Service Status:</strong></p>
            <ul style='margin: 0; color: #991B1B; line-height: 1.8;'>
                <li>Your chatbot is no longer active</li>
                <li>Embedded code has been disabled</li>
                <li>Dashboard access is restricted</li>
            </ul>
        </div>
        
        <p style='margin-top: 20px;'>To restore service immediately, please renew or upgrade your plan:</p>
        
        <div style='margin: 30px 0;'>
            <a href='https://bitchatbot.io/select_plan.php?renew=1' 
               style='background: #EF4444; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; margin-right: 10px;'>
                Renew Now
            </a>
            <a href='https://bitchatbot.io/select_plan.php?upgrade=1' 
               style='background: #6C47FF; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;'>
                Upgrade Plan
            </a>
        </div>
        
        <h3 style='color: #1F2937;'>Why Renew?</h3>
        <ul style='color: #4B5563; line-height: 1.8;'>
            <li>✅ Restore your chatbot instantly</li>
            <li>✅ Keep all your training data</li>
            <li>✅ Continue serving your website visitors</li>
            <li>✅ Maintain conversation history</li>
        </ul>
        
        <p style='color: #6B7280; font-size: 14px;'>Need help? Contact our support team.</p>
        
        <hr style='border: none; border-top: 1px solid #E5E7EB; margin: 20px 0;'>
        <p style='color: #9CA3AF; font-size: 12px;'>Bitchatbot - AI Chatbot Platform<br>
        <a href='https://bitchatbot.io' style='color: #6C47FF; text-decoration: none;'>https://bitchatbot.io</a></p>
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

/**
 * Send welcome email after registration
 */
function sendWelcomeEmail($conn, $user) {
    if ($user['email_consent'] != 1) return false;
    
    $subject = "🎉 Welcome to Bitchatbot - Your AI Chatbot Platform!";
    $message = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <h2 style='color: #6C47FF;'>Welcome to Bitchatbot!</h2>
        <p>Hi <strong>{$user['username']}</strong>,</p>
        <p>Thank you for registering with Bitchatbot! Your account has been successfully created.</p>
        
        <div style='background: #F3F4F6; padding: 20px; border-radius: 8px; margin: 20px 0;'>
            <h3 style='color: #1F2937; margin-top: 0;'>What's Next?</h3>
            <ol style='color: #4B5563; line-height: 1.8;'>
                <li>Choose a plan that fits your needs</li>
                <li>Complete payment to activate your chatbot</li>
                <li>Upload your training data</li>
                <li>Embed the chatbot on your website</li>
            </ol>
        </div>
        
        <div style='margin: 30px 0;'>
            <a href='https://bitchatbot.io/select_plan.php' 
               style='background: #6C47FF; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;'>
                Choose Your Plan
            </a>
        </div>
        
        <h3 style='color: #1F2937;'>Your Account Details:</h3>
        <table style='width: 100%; background: #F9FAFB; padding: 15px; border-radius: 8px;'>
            <tr><td style='color: #6B7280; padding: 5px 0;'>Username:</td><td style='color: #1F2937; font-weight: 600;'>{$user['username']}</td></tr>
            <tr><td style='color: #6B7280; padding: 5px 0;'>Email:</td><td style='color: #1F2937; font-weight: 600;'>{$user['email']}</td></tr>
        </table>
        
        <p style='margin-top: 20px; color: #6B7280; font-size: 14px;'>If you have any questions, our support team is here to help!</p>
        
        <hr style='border: none; border-top: 1px solid #E5E7EB; margin: 20px 0;'>
        <p style='color: #9CA3AF; font-size: 12px;'>Bitchatbot - AI Chatbot Platform<br>
        <a href='https://bitchatbot.io' style='color: #6C47FF; text-decoration: none;'>https://bitchatbot.io</a></p>
    </div>
    ";
    
    $sent = sendEmail($user['email'], $subject, $message);
    if ($sent) {
        logEmail($conn, $user['id'], 'welcome', 'sent');
    } else {
        logEmail($conn, $user['id'], 'welcome', 'failed');
    }
    
    return $sent;
}

/**
 * Send payment confirmation email
 */
function sendPaymentConfirmationEmail($conn, $user, $plan, $amount, $expiry_date, $is_renewal = false) {
    if ($user['email_consent'] != 1) return false;
    
    $plan_labels = ['basic' => 'Basic', 'starter' => 'Starter', 'pro' => 'Pro'];
    $plan_prices = ['basic' => '$10', 'starter' => '$20', 'pro' => '$30'];
    
    if ($is_renewal) {
        $subject = "🔄 Plan Renewed Successfully - Your {$plan_labels[$plan]} Plan is Active!";
        $title = "Plan Renewed Successfully!";
        $intro = "Great news! Your <strong style='color: #6C47FF;'>" . strtoupper($plan_labels[$plan]) . "</strong> plan has been renewed successfully.";
        $button_text = "Go to Dashboard";
        $button_color = "#6C47FF";
    } else {
        $subject = "✅ Payment Successful - Your {$plan_labels[$plan]} Plan is Active!";
        $title = "Payment Successful!";
        $intro = "Thank you for your payment! Your <strong style='color: #6C47FF;'>" . strtoupper($plan_labels[$plan]) . "</strong> plan is now active.";
        $button_text = "Go to Dashboard";
        $button_color = "#10B981";
    }
    
    $message = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <h2 style='color: " . ($is_renewal ? '#6C47FF' : '#10B981') . ";'>{$title}</h2>
        <p>Hi <strong>{$user['username']}</strong>,</p>
        <p>{$intro}</p>
        
        <div style='background: #F3F4F6; padding: 20px; border-radius: 8px; margin: 20px 0;'>
            <h3 style='color: #1F2937; margin-top: 0;'>Plan Details:</h3>
            <table style='width: 100%;'>
                <tr><td style='color: #6B7280; padding: 8px 0;'>Plan:</td><td style='color: #1F2937; font-weight: 600;'>" . strtoupper($plan_labels[$plan]) . "</td></tr>
                <tr><td style='color: #6B7280; padding: 8px 0;'>Amount Paid:</td><td style='color: #10B981; font-weight: 700;'>{$plan_prices[$plan]}/month</td></tr>
                <tr><td style='color: #6B7280; padding: 8px 0;'>" . ($is_renewal ? 'Renewed On:' : 'Start Date:') . "</td><td style='color: #1F2937;'>" . date('d M Y') . "</td></tr>
                <tr><td style='color: #6B7280; padding: 8px 0;'>New Expiry Date:</td><td style='color: #EF4444; font-weight: 600;'>" . date('d M Y', strtotime($expiry_date)) . "</td></tr>
            </table>
        </div>
        
        <div style='background: #FEF3C7; padding: 15px; border-radius: 8px; border-left: 4px solid #F59E0B; margin: 20px 0;'>
            <p style='margin: 0; color: #92400E;'><strong>⚠️ Important:</strong> Your plan will expire in 30 days. We'll send you reminder emails before expiration.</p>
        </div>
        
        <div style='margin: 30px 0;'>
            <a href='https://bitchatbot.io/dashboard.php' 
               style='background: {$button_color}; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;'>
                {$button_text}
            </a>
        </div>
        
        <h3 style='color: #1F2937;'>What You Can Do:</h3>
        <ul style='color: #4B5563; line-height: 1.8;'>
            <li>✅ Upload your training data (PDF, DOCX, JSON)</li>
            <li>✅ Customize your chatbot appearance</li>
            <li>✅ Embed the chatbot on your website</li>
            <li>✅ Monitor conversations and analytics</li>
        </ul>
        
        <hr style='border: none; border-top: 1px solid #E5E7EB; margin: 20px 0;'>
        <p style='color: #9CA3AF; font-size: 12px;'>Bitchatbot - AI Chatbot Platform<br>
        <a href='https://bitchatbot.io' style='color: #6C47FF; text-decoration: none;'>https://bitchatbot.io</a></p>
    </div>
    ";
    
    $email_type = $is_renewal ? 'renewal_confirmation' : 'payment_confirmation';
    $sent = sendEmail($user['email'], $subject, $message);
    if ($sent) {
        logEmail($conn, $user['id'], $email_type, 'sent');
    } else {
        logEmail($conn, $user['id'], $email_type, 'failed');
    }
    
    return $sent;
}
